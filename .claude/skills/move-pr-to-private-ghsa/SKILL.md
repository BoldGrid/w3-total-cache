---
name: move-pr-to-private-ghsa
description: Move an in-progress public GitHub PR for a security fix into a GitHub Security Advisory's temporary private fork (TPF) so the remaining work continues privately. Use when a security-fix PR was opened on a public repo and the team decides further review and additional commits should happen in a draft GHSA's TPF, with the original public PR closed and its branch deleted. Requires repo-admin (or `repository_advisories:write` + `repo`) GitHub auth. Encodes the create-advisory → start-private-fork → push-branch → open-private-PR → back-link-to-Jira → close-public-PR sequence plus the gotchas (async fork creation, no CI on TPFs, all-or-nothing merge in the advisory UI, public PR history is already public, contributor-fork branches can't be deleted by the maintainer, Jira-side bidirectional cross-link must be posted explicitly). Pair with `pr-content-to-jira` when the public PR has accumulated sensitive descriptive content that also needs an audit-trail home.
---

# Move PR to private GHSA

Move an in-progress public GitHub PR for a security fix into a GitHub Security Advisory's temporary private fork (TPF) so review and additional commits continue privately. This skill encodes the workflow plus every gotcha discovered along the way.

This skill is the GHSA counterpart to `.claude/skills/pr-content-to-jira/SKILL.md`. The two are often used together: this one moves the **code** (commits, branch, future review) to a private surface; that one moves the **descriptive content** (audit-finding IDs, kill-chain text, exploit primitives in review) off the public PR into Jira. When both apply, run `pr-content-to-jira` first so the audit-trail content is preserved before the public PR is closed.

---

## When to use this

- A public-repo PR for a security fix exists, and the team has decided the remaining review and commits should be private.
- The decision is recent enough that "make it private now" still has value — i.e., the public PR's commits are still on a deletable branch (the diff isn't merged yet) and the critical exploit detail isn't already plastered across an indexed review thread.
- You have repo-admin (or `repository_advisories:write` + `repo`) on the public repo.

Do **not** assume this rolls back public exposure. Anything visible on the original PR — title, description, branch name, commits, file diffs, review comments — was already in GitHub search, GHArchive, code-search mirrors, and frequently external indexes before you ran this. This skill prevents *future* exposure: additional commits, additional review, follow-up findings.

---

## Prerequisites

- `gh` CLI logged in with repo-admin scope (or specifically `repository_advisories:write` + `repo`). Verify:
  ```bash
  gh auth status
  gh api repos/{owner}/{repo}/collaborators/{login}/permission --jq '.role_name'
  ```
- `gh`'s git credential helper installed so HTTPS pushes to the (private) TPF authenticate transparently:
  ```bash
  gh auth setup-git
  ```
- `git` and `jq` available locally.
- A writable `.cursor/working/` directory (gitignored).
- **If a Jira ticket will receive the GHSA back-link** (almost always true when paired with `pr-content-to-jira`): Atlassian Jira MCP server authenticated. Verify by calling `getAccessibleAtlassianResources` and capturing the `cloudId`. Without this, Phase 5b cannot run and the cross-link must be posted manually after the workflow.

---

## Filename convention for scratch files

Per AGENTS.md "Working Files", prefix every scratch file with the originating PR number. Once the GHSA ID is known, append it for downstream files:

- `{PR}-pr-snapshot.json` — original PR metadata
- `{PR}-branch-info.json` — branch ref, head SHA, base, commits
- `{PR}-advisory-payload.json` — POST body for `/security-advisories`
- `{PR}-advisory-response.json` — response (capture `ghsa_id`, `html_url`)
- `{PR}-{GHSA}-fork-response.json` — temp private fork details (capture `full_name`)
- `{PR}-{GHSA}-private-pr-body.md` — body for new PR in TPF
- `{PR}-{GHSA}-advisory-description.md` — long-form description posted into advisory
- `{PR}-{GHSA}-{KEY}-jira-backlink.md` — body of the back-link comment posted to Jira (Phase 5b)
- `{PR}-{GHSA}-{KEY}-jira-backlink-response.json` — `addCommentToJiraIssue` response for verify
- `{PR}-close-comment.md` — public-facing close comment for original PR

Never write to `/tmp/` or anywhere outside the project tree.

---

## Workflow

### Phase 0 — Confirm scope and inputs

Capture from the user (or ask):

- PR URL → derive `{owner}`, `{repo}`, `{n}`.
- Severity (`critical` / `high` / `medium` / `low`) — required for the advisory unless you supply `cvss_vector_string` instead.
- One-line summary (≤1024 chars) for the advisory.
- Long description (≤65535 chars) — usually the original PR description plus any review context worth preserving.
- Optional: pre-allocated CVE ID, CWE IDs, credits (login + role).
- Whether to migrate review/comment context from the public PR into the advisory description (recommended) or leave it for separate Jira-side preservation via `pr-content-to-jira`.
- **Jira ticket key (e.g., `ENG7-2908`)** for the back-link in Phase 5b. If `pr-content-to-jira` was already run on this PR, the same ticket key carries over. If there is no Jira ticket, skip Phase 5b but say so explicitly to the user — the bidirectional link is otherwise easy to forget.
- Public-facing close-message wording for the original PR (must reveal nothing about the vulnerability or the GHSA).

Run in parallel:

- `gh auth status` and the permission check above.
- ```bash
  gh pr view $PR --repo $REPO \
    --json number,title,body,headRefName,headRefOid,baseRefName,author,labels,state,isCrossRepository,headRepositoryOwner \
    > .cursor/working/${PR}-pr-snapshot.json
  ```
- ```bash
  gh api repos/$REPO/pulls/$PR/commits \
    --jq '[.[] | {sha, message: .commit.message, author: .commit.author.name}]' \
    > .cursor/working/${PR}-branch-info.json
  ```
- If a Jira ticket is in scope: `getAccessibleAtlassianResources` (capture `cloudId`) and `getJiraIssue` for `{KEY}` (confirm the ticket exists, capture its current state for the user's mental model).

### Phase 1 — Pre-flight on the original branch

Two cases, handle differently based on `isCrossRepository`:

| Case | `isCrossRepository` | Where the branch lives |
|---|---|---|
| Same-repo PR | `false` | `{owner}/{repo}:{headRefName}` |
| Fork PR | `true` | `{contributor-fork}:{headRefName}` |

Either way, fetch the PR's head into a local working branch using the canonical `refs/pull/{n}/head` ref — it works for both cases:

```bash
git fetch origin "+refs/pull/$PR/head:refs/heads/pr-${PR}-tmp"
git checkout pr-${PR}-tmp
git log -1 --format='%H %s'   # confirm matches headRefOid from snapshot
```

If the working tree has uncommitted changes, stash before this — `git checkout` will refuse otherwise.

### Phase 2 — Create the draft advisory + start TPF (one shot)

Build the request body. The `start_private_fork: true` flag avoids a second round trip:

```jsonc
// .cursor/working/${PR}-advisory-payload.json
{
  "summary": "<≤1024 char title>",
  "description": "<full description; can include the original PR body>",
  "severity": "high",
  "cve_id": null,
  "vulnerabilities": [
    {
      "package": { "ecosystem": "composer", "name": "boldgrid/w3-total-cache" },
      "vulnerable_version_range": "<= 2.9.1",
      "patched_versions": null,
      "vulnerable_functions": []
    }
  ],
  "cwe_ids": [],
  "credits": [
    { "login": "cssjoe", "type": "remediation_developer" }
  ],
  "start_private_fork": true
}
```

Notes on the payload:

- `vulnerabilities` is **required** and must be a non-null array. The `package.ecosystem` enum: `rubygems`, `npm`, `pip`, `maven`, `nuget`, `composer`, `go`, `rust`, `erlang`, `actions`, `pub`, `other`, `swift`. For WordPress plugins, use `composer` if listed on Packagist, otherwise `other`.
- Set exactly one of `severity` (categorical enum) or `cvss_vector_string` (vector). Don't set both.
- `cve_id: null` is fine for a brand-new draft; reserve a CVE later via `POST /repos/{owner}/{repo}/security-advisories/{ghsa_id}/cve`.
- `credits[].type` enum: `analyst`, `finder`, `reporter`, `coordinator`, `remediation_developer`, `remediation_reviewer`, `remediation_verifier`, `tool`, `sponsor`, `other`.

Submit:

```bash
gh api -X POST repos/$REPO/security-advisories \
  --input .cursor/working/${PR}-advisory-payload.json \
  > .cursor/working/${PR}-advisory-response.json

GHSA=$(jq -r '.ghsa_id' .cursor/working/${PR}-advisory-response.json)
ADVISORY_URL=$(jq -r '.html_url' .cursor/working/${PR}-advisory-response.json)
echo "Created $GHSA at $ADVISORY_URL"
```

The advisory is now in `draft` state, only visible to repo admins + advisory collaborators.

### Phase 3 — Wait for the temp private fork to materialize

Fork creation is **async** (docs say up to 5 minutes). Even with `start_private_fork: true`, the fork may take a minute to appear. Per docs, the fork's name is `{repo}-ghsa-xxxx-xxxx-xxxx`, with `{repo}` truncated to 80 chars.

Always **read `full_name` from the fork response** rather than reconstructing from convention — the truncation rule and the lowercasing of the GHSA ID make hand-construction error-prone:

```bash
GHSA_LC=$(echo "$GHSA" | tr '[:upper:]' '[:lower:]')
REPO_NAME=${REPO#*/}
REPO_OWNER=${REPO%/*}
FORK_GUESS="${REPO_OWNER}/${REPO_NAME:0:80}-${GHSA_LC}"

for i in $(seq 1 30); do
  if gh api "repos/$FORK_GUESS" >/dev/null 2>&1; then
    gh api "repos/$FORK_GUESS" > .cursor/working/${PR}-${GHSA}-fork-response.json
    FORK_FULL=$(jq -r '.full_name' .cursor/working/${PR}-${GHSA}-fork-response.json)
    echo "Fork ready: $FORK_FULL"
    break
  fi
  echo "Waiting for fork... ($i/30)"
  sleep 10
done
```

If `start_private_fork: true` was *not* set on the create call, kick the fork explicitly:

```bash
gh api -X POST repos/$REPO/security-advisories/$GHSA/forks
```

### Phase 4 — Push the branch into the temp private fork

Add the TPF as a remote, push, then remove the remote so it doesn't linger in your local config:

```bash
HEAD_REF_NAME=$(jq -r '.headRefName' .cursor/working/${PR}-pr-snapshot.json)

git remote add ghsa "https://github.com/$FORK_FULL.git"
git push ghsa pr-${PR}-tmp:${HEAD_REF_NAME}
git remote remove ghsa
```

**`Everything up-to-date` is expected for same-repo PRs.** The TPF is created by forking the public repo *with all of its branches*, so the head branch already exists in the TPF at the same SHA before the push runs. The push is a noop in that case but still correct. For cross-repo (contributor-fork) PRs the branch genuinely doesn't exist on the TPF until you push, so the push will report new objects.

The destination branch name is your choice. Keeping the original `headRefName` is fine inside the private fork; consider renaming if the original branch name itself was descriptive of the vuln (e.g., `cve-2026-XXXX-rce-fix`) and the rename will make collaborators' lives easier going forward — but understand the original name was already public.

Verify the push:

```bash
gh api repos/$FORK_FULL/branches/${HEAD_REF_NAME} --jq '{name, commit: .commit.sha}'
```

The SHA should match `headRefOid` from the original PR snapshot.

### Phase 5 — Open the private PR inside the advisory

The TPF inherits the **parent repo's default branch**, not necessarily `main`. Read it from the fork response rather than hardcoding (e.g., this repo defaults to `master`, so a hardcoded `--base main` would 404):

```bash
TITLE=$(jq -r '.title' .cursor/working/${PR}-pr-snapshot.json)
BASE=$(jq -r '.default_branch' .cursor/working/${PR}-${GHSA}-fork-response.json)

gh pr create \
  --repo $FORK_FULL \
  --base "$BASE" \
  --head ${HEAD_REF_NAME} \
  --title "$TITLE" \
  --body-file .cursor/working/${PR}-${GHSA}-private-pr-body.md
```

GitHub automatically links the PR to the parent advisory because the fork is the advisory's TPF. The PR shows up under "Collaborate on a patch" on the advisory page.

If you want to migrate review threads from the public PR, do it **now** — post them as comments on the new private PR or stitch them into the advisory description via `PATCH /repos/{owner}/{repo}/security-advisories/{ghsa_id}`. The original review/inline comments do **not** travel with the commits.

Add reviewers as advisory collaborators *before* requesting review on the private PR — they'll 404 on the TPF otherwise. There is **no** `.../security-advisories/{ghsa}/collaborators` sub-endpoint (it returns 404). Use `PATCH` on the advisory itself with `collaborating_users[]` and/or `collaborating_teams[]`:

```bash
gh api -X PATCH repos/$REPO/security-advisories/$GHSA \
  -f 'collaborating_users[]=external-reviewer-login' \
  -f 'collaborating_teams[]=team-slug'

# Verify
gh api repos/$REPO/security-advisories/$GHSA \
  --jq '{collaborating_users: [.collaborating_users[]?.login], collaborating_teams: [.collaborating_teams[]?.slug]}'
```

`PATCH` replaces the lists rather than appending, so always include the full set of users/teams you want present, not just the new addition. `collaborating_teams[]` takes the team slug (not numeric id, not full path) and the team must live in the same organization as the repo.

Two PATCH gotchas worth memorizing:

- **Read state immediately before you mutate.** Replace-semantics + a UI that other admins use means whatever you saw five minutes ago may already be stale. Run `gh api repos/$REPO/security-advisories/$GHSA --jq '{collaborating_users: [.collaborating_users[]?.login], collaborating_teams: [.collaborating_teams[]?.slug]}'` right before the PATCH so the lists you build are based on present state, not a remembered state.
- **To genuinely clear an array, send a JSON body — not `-f 'field[]='`.** The form-encoded shape `--raw-field 'collaborating_users[]='` sends a literal empty string as a user login and the API rejects it with `422 — User '' not found, collaborating_users cannot be modified`. If you need to clear (or fully reset) a list, build a JSON file and pass `--input`:
  ```bash
  echo '{"collaborating_users": [], "collaborating_teams": ["w3-total-cache-developers"]}' \
    > .cursor/working/${PR}-${GHSA}-collab-patch.json
  gh api -X PATCH repos/$REPO/security-advisories/$GHSA \
    --input .cursor/working/${PR}-${GHSA}-collab-patch.json
  ```
  Or just omit the field entirely when you only want to change the other one — the API treats omitted fields as "leave unchanged" on this endpoint.

**Prefer teams over enumerating individual users** when a maintained group exists for the repo — it's less to maintain when membership shifts, and the advisory page lists the team as a single line rather than a noisy fan-out of usernames. Use `collaborating_users[]` only for individuals who are not already in the relevant team (e.g., a contributor whose PR you're moving in, an external auditor, an off-team reviewer).

#### Repo-specific collaborator defaults

When this skill runs against one of the maintained repos below, default to including the listed team in `collaborating_teams[]` rather than enumerating its members individually. Extend this list as new repos pick up the workflow.

| Repo | Default team slug | Team URL |
|---|---|---|
| `BoldGrid/w3-total-cache` | `w3-total-cache-developers` | https://github.com/orgs/BoldGrid/teams/w3-total-cache-developers |

For `BoldGrid/w3-total-cache` specifically, a complete advisory collaborator setup typically looks like:

```bash
gh api -X PATCH repos/BoldGrid/w3-total-cache/security-advisories/$GHSA \
  -f 'collaborating_teams[]=w3-total-cache-developers' \
  -f 'collaborating_users[]=<original PR author, if not in the team>' \
  -f 'collaborating_users[]=<external reviewer, if any>'
```

Repo admins (e.g., `cssjoe`) inherit access via the admin role and do **not** need to be re-added as collaborators.

### Phase 5b — Back-link the GHSA into the Jira ticket

The advisory description was written outbound (Jira → GHSA → Jira via the ENG link in the description). The Jira ticket itself does **not** automatically learn about the new GHSA. Without an explicit back-link, anyone navigating to the Jira ticket sees the public PR pointer (planted by `pr-content-to-jira`) and has no path forward to the live private workstream — a very easy thing to discover six weeks later when the ticket needs reviewing.

Skip this phase only if there is no Jira ticket in scope. Otherwise: do this **before** Phase 6 (closing the public PR). If the Jira post fails for any reason, you have not yet thrown away the public PR, so retry/recovery is simple.

Build the comment body in `${PR}-${GHSA}-${KEY}-jira-backlink.md`:

```markdown
## Moved to a private GitHub Security Advisory

Continued review and additional commits for this fix have been relocated to a private GitHub Security Advisory ("temporary private fork" / TPF). The public PR linked from this ticket is being closed and its branch deleted.

**Live surfaces:**

- Advisory: {ADVISORY_URL}  (state: draft, severity: {SEV})
- Temp private fork: https://github.com/{FORK_FULL}
- Private PR (replaces the public one for ongoing review): {PRIVATE_PR_URL}
- Originating public PR (now closed): https://github.com/{REPO}/pull/{PR}

**Reviewers:** must be added as advisory collaborators (`PATCH /repos/{REPO}/security-advisories/{GHSA}` with `collaborating_teams[]` and/or `collaborating_users[]`) before they can see the TPF or the private PR. Prefer the maintained team for the repo where one exists (e.g., `BoldGrid/w3-total-cache-developers` for `BoldGrid/w3-total-cache`); use `collaborating_users[]` only for individuals outside that team. Currently added: teams=`{TEAM_LIST}`, users=`{USER_LIST}`.

**Caveats baked in (don't relearn these):**

- CI / status checks / codecov do not run on TPFs by design.
- The GHSA UI's "Merge pull request(s)" action is all-or-nothing across every open PR in the TPF.
- Closing the original public PR and deleting its branch does not retract the public history of the diff/title/branch-name/commits/reviews; that content was already in GitHub search and external mirrors.
```

Render and post via the Atlassian MCP `addCommentToJiraIssue` tool. Use `contentFormat: "markdown"` and `responseContentFormat: "markdown"` so you can verify what landed in one round-trip:

```jsonc
// Tool args for addCommentToJiraIssue
{
  "cloudId": "<CID from getAccessibleAtlassianResources>",
  "issueIdOrKey": "ENG7-2908",
  "commentBody": "<contents of ${PR}-${GHSA}-${KEY}-jira-backlink.md>",
  "contentFormat": "markdown",
  "responseContentFormat": "markdown"
}
```

Save the response to `${PR}-${GHSA}-${KEY}-jira-backlink-response.json`. Then verify:

- The response `body` contains the literal advisory URL (search for `/security/advisories/${GHSA}` substring).
- The response `body` contains the private PR URL.
- The response `body` does not contain stray blockquote `>` markers from a botched conversion.

If the verify fails: see the `pr-content-to-jira` skill's "Phase 3 — CRITICAL: Jira markdown caveat" — the markdown→ADF converter silently drops blockquotes. Strip any `>`-prefixed lines, re-post, and reference the broken comment ID in the corrected one ("supersedes comment XYZ"); MCP cannot edit Jira comments after the fact.

### Phase 6 — Close the original public PR and delete its branch

Build a terse, non-revealing close comment in `${PR}-close-comment.md`. The comment is public; it must not name the GHSA, link the advisory URL, or restate the vulnerability:

```markdown
Closing this PR. Continued work on this fix is being handled through a private review process. A new public PR will be opened when the change is ready to merge. Thanks to everyone who reviewed here.
```

Post it, then close + delete:

```bash
gh pr comment $PR --repo $REPO --body-file .cursor/working/${PR}-close-comment.md
gh pr close   $PR --repo $REPO --delete-branch
```

If the original branch lived in a contributor's fork (cross-repo PR — `isCrossRepository: true`), `--delete-branch` deletes the public-repo `refs/pull/{n}/head` pointer but **cannot** delete the contributor's fork branch. Surface this to the user; the contributor must delete from their own fork.

### Phase 7 — Verify

Confirm end state:

```bash
gh pr view $PR --repo $REPO --json state,headRefName | jq
# expect state: CLOSED

gh api repos/$REPO/branches/${HEAD_REF_NAME} 2>&1 | head -3
# expect 404 for same-repo PRs; for cross-repo, the ref never lived here

gh api repos/$FORK_FULL/branches/${HEAD_REF_NAME} --jq '.commit.sha'
# expect headRefOid from the snapshot

gh api repos/$REPO/security-advisories/$GHSA \
  --jq '{state, ghsa_id, html_url, private_fork: .private_fork.full_name}'
# expect state: draft, private_fork: $FORK_FULL

gh pr list --repo $FORK_FULL --json number,title,headRefName,baseRefName
# expect the new private PR
```

If a Jira back-link was posted in Phase 5b, also verify it landed and contains the expected URLs:

```
getJiraIssue cloudId={CID} issueIdOrKey={KEY} fields=["comment"]
```

Inspect the most recent comment on the issue — it should contain both the advisory URL and the private PR URL, with no surviving `>` blockquote markers (those would have been silently dropped — see the `pr-content-to-jira` skill's Phase 3 for the converter caveat).

Optional sanity check: open `$ADVISORY_URL` in a browser, scroll to "Collaborate on a patch", confirm the new private PR is listed.

---

## Caveats to surface to the user every time

1. **Public history is public.** The original PR's title, description, branch name, commits, diff, and reviews were already in GitHub search, GHArchive, code-search mirrors, and frequently external indexes before this skill ran. Closing the PR and deleting the branch removes the *current* working ref but does not retract anything that was visible. Commits often remain reachable via `refs/pull/{n}/head` even after branch deletion. Genuine purge requires GitHub Support and is best-effort even then.

2. **CI does not run on temporary private forks.** Status checks, codecov, custom GitHub Actions — none of it runs on the TPF, by design (to keep vuln content from leaking through CI providers). Plan for local testing or a separate trusted private mirror before the GHSA's "Merge pull request(s)" step.

3. **Merging is all-or-nothing in the GHSA UI.** Individual PRs in the TPF do not merge through the normal "Merge" button. The advisory page has a single "Merge pull request(s)" action that merges all open PRs at once into the parent repo's default branch. Only one PR per TPF can target `main`; additional PRs must target intermediate branches.

4. **Fork creation is async.** `start_private_fork: true` (or `POST .../forks`) returns 202; the fork is not immediately usable. Poll for up to ~5 minutes before pushing.

5. **Branch name leak.** If your original branch was named after the vuln (`cve-2026-XXXX-rce-fix`, `ENG7-2797-access-control-vuln`), that name was indexed before deletion. Renaming on the TPF is fine but doesn't unleak the original.

6. **Reviewers must be advisory collaborators.** Add them via `PATCH /repos/{owner}/{repo}/security-advisories/{ghsa_id}` with `collaborating_teams[]` (preferred when a maintained team exists for the repo — see "Repo-specific collaborator defaults") and/or `collaborating_users[]` *before* asking them to review the private PR, otherwise they 404 on the TPF. The intuitive `POST .../collaborators` sub-endpoint does not exist and returns 404. `PATCH` is replace-semantics on these arrays — always include the full intended membership, not just the delta. Repo admins do not need to be re-added; their access comes from the role.

7. **Comments don't migrate with commits.** Reviews, inline comments, and conversation threads from the public PR stay on the public PR. Migrate any context worth preserving into the advisory description or as comments on the new private PR — and consider running `pr-content-to-jira` to also copy that context into Jira for the audit trail.

8. **`gh` has no native security-advisory subcommand** as of this writing. Everything here goes through `gh api`. If a future `gh security` (or `gh repo security`) subcommand exists, prefer it over the raw REST calls.

9. **Contributor-fork PRs are messy.** When `isCrossRepository: true`, `--delete-branch` only closes/cleans the merge target in the public repo. The contributor's fork branch is unaffected and remains visible. The contributor must delete from their own fork.

10. **The close comment is public.** Don't put the GHSA ID, advisory URL, vuln summary, or any "we moved this because…" detail in it. A bland "moving to private review" is enough.

11. **Run `pr-content-to-jira` first if applicable.** Once the public PR is closed and its branch deleted, redacting individual comments on the closed PR is harder to justify and has weaker UI affordances. Move sensitive descriptive content to Jira while the PR is still open.

12. **The Jira back-link is not automatic.** The advisory description points outbound to Jira, but Jira does not learn about the GHSA on its own. Phase 5b is the explicit back-link step; without it, anyone landing on the Jira ticket weeks later sees only the closed public PR and has no path forward to the live private workstream. Always run Phase 5b before Phase 6 so a failure doesn't leave Jira out of date with a closed public PR pointing at it.

---

## Quick reference: end-to-end sequence

```
0. Capture: PR URL, severity, summary, description, optional CVE/CWE/credits,
            Jira ticket key (for the Phase 5b back-link), close-message wording.
1. Fetch PR head:           git fetch origin +refs/pull/$PR/head:refs/heads/pr-${PR}-tmp
2. Create draft GHSA + TPF: gh api -X POST repos/$REPO/security-advisories --input payload.json
                             (with start_private_fork: true)
3. Poll until fork is reachable; capture full_name from response.
4. Push branch:             git remote add ghsa <fork URL>
                            git push ghsa pr-${PR}-tmp:<branch>
                            git remote remove ghsa
                            (same-repo PRs: expect "Everything up-to-date" — TPF inherits all branches)
5. Open private PR:         gh pr create --repo <fork> --base "$(jq -r .default_branch <fork-response>)" --head <branch> ...
                            (TPF default branch == parent repo's default; not always "main")
6. Add advisory collaborators (if reviewers aren't already):
                            gh api -X PATCH repos/$REPO/security-advisories/$GHSA \
                              -f 'collaborating_teams[]=<maintained-team-slug>' \
                              -f 'collaborating_users[]=<off-team-individual>' ...
                            (prefer team over enumerating users where one exists;
                             see "Repo-specific collaborator defaults" — for
                             BoldGrid/w3-total-cache the default team is
                             w3-total-cache-developers.
                             NOT POST .../collaborators — that endpoint returns 404)
6b. Back-link to Jira:      addCommentToJiraIssue cloudId={CID} issueIdOrKey={KEY}
                            commentBody=<advisory + private-PR URLs + caveats>
                            contentFormat=markdown
                            (skip only if there is no Jira ticket; do this BEFORE step 7
                             so a Jira failure doesn't leave Jira pointing at a closed public PR)
7. Public-side cleanup:     gh pr comment $PR (bland public message)
                            gh pr close   $PR --delete-branch
8. Verify: PR closed, public branch gone, fork branch present at headRefOid,
   advisory state draft, new private PR listed under the advisory,
   Jira's most recent comment contains the advisory + private PR URLs.

Always run `pr-content-to-jira` BEFORE step 7 if the public PR has sensitive
descriptive content worth preserving in the audit trail.
```
