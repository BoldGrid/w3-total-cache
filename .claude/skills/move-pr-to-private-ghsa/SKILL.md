---
name: move-pr-to-private-ghsa
description: Move an in-progress public GitHub PR for a security fix into a GitHub Security Advisory's temporary private fork (TPF) so the remaining work continues privately. Use when a security-fix PR was opened on a public repo and the team decides further review and additional commits should happen in a draft GHSA's TPF, with the original public PR closed and its branch deleted. Requires repo-admin (or `repository_advisories:write` + `repo`) GitHub auth. Encodes the create-advisory → start-private-fork → push-branch → open-private-PR → back-link-to-Jira → close-public-PR sequence plus the gotchas (async fork creation, no CI on TPFs, all-or-nothing merge in the advisory UI, public PR history is already public, contributor-fork branches can't be deleted by the maintainer, Jira-side bidirectional cross-link must be posted explicitly). Standing rule: always run `pr-content-to-jira` first as the paired content-relocation step — never as a per-run "should we?" question.
---

# Move PR to private GHSA

Move an in-progress public GitHub PR for a security fix into a GitHub Security Advisory's temporary private fork (TPF) so review and additional commits continue privately. This skill encodes the workflow plus every gotcha discovered along the way.

This skill is the GHSA counterpart to `.claude/skills/pr-content-to-jira/SKILL.md`. **Standing rule: always run `pr-content-to-jira` first, then this skill — never ask "should we?".** The two skills move different artifacts (this one moves the **code** — commits, branch, future review — to a private surface; that one moves the **descriptive content** — audit-finding IDs, kill-chain text, exploit primitives in review — off the public PR into Jira), and the right order is always content-first / code-second so the audit-trail content is preserved before the public PR is closed. If the public PR has no sensitive descriptive content (rare but possible — e.g., a one-line fix with a neutral title), `pr-content-to-jira` becomes a near-noop run that still establishes the Jira ↔ PR cross-link the back-link in Phase 5b depends on. Run it anyway.

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

Per AGENTS.md "Working Files", every scratch file follows `{KEY}-{REPO}-{PR}-{role}.{ext}`. Capture `KEY`/`REPO`/`PR` once in Phase 0 and derive a single prefix that expands cleanly for the rest of the workflow:

```bash
KEY=ENG7-2908                       # Jira ticket — required for this skill (see Phase 0)
REPO=BoldGrid/w3-total-cache        # owner/repo from the PR URL
PR=1313                             # public PR number
SLUG="${REPO#*/}"                   # → "w3-total-cache"
PFX="${KEY}-${SLUG}-${PR}"          # canonical scratch-file prefix
```

Every file path below expands as `.cursor/working/${PFX}-{role}.{ext}` (the workflow originated on the public PR, so the public PR's `{REPO}/{PR}` is the keying triple even for files that describe downstream GHSA artifacts — embed the GHSA slug in `{role}` rather than rotating the keying):

- `${PFX}-pr-snapshot.json` — original PR metadata
- `${PFX}-branch-info.json` — branch ref, head SHA, base, commits
- `${PFX}-worktree/` — linked git worktree where the temp branch is checked out (Phase 1 isolation default; removed in Phase 7 teardown)
- `${PFX}-advisory-payload.json` — POST body for `/security-advisories`
- `${PFX}-advisory-response.json` — response (capture `ghsa_id`, `html_url`)
- `${PFX}-ghsa-{ghsa-slug}-fork-response.json` — temp private fork details (capture `full_name`); `{ghsa-slug}` is the lowercase advisory ID suffix, e.g. `rgpr-5m2g-gvh2`
- `${PFX}-ghsa-{ghsa-slug}-private-pr-body.md` — body for new PR in TPF
- `${PFX}-ghsa-{ghsa-slug}-advisory-description.md` — long-form description posted into advisory
- `${PFX}-ghsa-{ghsa-slug}-jira-backlink.md` — body of the back-link comment posted to Jira (Phase 5b)
- `${PFX}-ghsa-{ghsa-slug}-jira-backlink-response.json` — `addCommentToJiraIssue` response for verify
- `${PFX}-close-comment.md` — public-facing close comment for original PR

The git branch name used for the worktree (`pr-${PR}-tmp`) intentionally stays as-is — it's a local ref, not a file, and is namespaced to this repo's `.git/`. Don't confuse it with a scratch-file path.

Never write to `/tmp/` or anywhere outside the project tree.

---

## Workflow

### Phase 0 — Confirm scope and inputs

Capture from the user (or ask):

- PR URL → derive `{owner}`, `{repo}`, `{n}`.
- Severity (`critical` / `high` / `medium` / `low`) — required for the advisory unless you supply `cvss_vector_string` instead.
- One-line summary (≤1024 chars) for the advisory. The standing-rule title shape is **`{JIRA_KEY}: {clean summary}`** — see Phase 2's summary/description standing rule below.
- Long description (≤65535 chars) — usually the original PR description plus any review context worth preserving. Its first line MUST be the canonical Jira link, per the Phase 2 standing rule.
- Optional: pre-allocated CVE ID, CWE IDs, credits (login + role).
- Confirm `pr-content-to-jira` has already been run for this PR (standing rule — see "When to use this"). If it hasn't, run it first and return here. The Jira side becomes the audit-trail home for descriptive content; the advisory description should focus on the change itself (what's fixed, scope, residual risk) rather than re-housing the same finding-IDs/kill-chain content that pr-content-to-jira just moved into Jira.
- **Jira ticket key (e.g., `ENG7-2908`)** — this drives THREE things: (1) the Phase 5b back-link comment, (2) the advisory summary prefix (`{KEY}: ...`), and (3) the TPF private PR title prefix (`{KEY}: ...`). It is therefore required for any GHSA the workflow produces, not optional. If `pr-content-to-jira` was already run on this PR, the same ticket key carries over. If there genuinely is no Jira ticket, stop and create one before continuing — the prefixed-title and Jira-link-at-top conventions assume one exists.
- Public-facing close-message wording for the original PR (must reveal nothing about the vulnerability or the GHSA).

Once `KEY`, `REPO`, and `PR` are pinned, expand the canonical scratch-file prefix (used by every shell snippet from here on; see "Filename convention for scratch files" above):

```bash
KEY=ENG7-XXXX                        # Jira ticket captured in this phase
REPO=BoldGrid/w3-total-cache         # owner/repo from the PR URL
PR=1234                              # public PR number
SLUG="${REPO#*/}"                    # → "w3-total-cache"
PFX="${KEY}-${SLUG}-${PR}"           # → "ENG7-XXXX-w3-total-cache-1234"
```

Then run in parallel:

- `gh auth status` and the permission check above.
- ```bash
  gh pr view $PR --repo $REPO \
    --json number,title,body,headRefName,headRefOid,baseRefName,author,labels,state,isCrossRepository,headRepositoryOwner \
    > .cursor/working/${PFX}-pr-snapshot.json
  ```
- ```bash
  gh api repos/$REPO/pulls/$PR/commits \
    --jq '[.[] | {sha, message: .commit.message, author: .commit.author.name}]' \
    > .cursor/working/${PFX}-branch-info.json
  ```
- If a Jira ticket is in scope: `getAccessibleAtlassianResources` (capture `cloudId`) and `getJiraIssue` for `{KEY}` (confirm the ticket exists, capture its current state for the user's mental model).

### Phase 1 — Pre-flight on the original branch (worktree-isolated)

Two cases, handle differently based on `isCrossRepository`:

| Case | `isCrossRepository` | Where the branch lives |
|---|---|---|
| Same-repo PR | `false` | `{owner}/{repo}:{headRefName}` |
| Fork PR | `true` | `{contributor-fork}:{headRefName}` |

Either way, fetch the PR's head into a local working branch using the canonical `refs/pull/{n}/head` ref — it works for both cases — and check it out in a **linked worktree** under `.cursor/working/` so the relocation never disturbs the main project tree:

```bash
git fetch origin "+refs/pull/$PR/head:refs/heads/pr-${PR}-tmp"
git worktree add .cursor/working/${PFX}-worktree pr-${PR}-tmp
( cd .cursor/working/${PFX}-worktree && git log -1 --format='%H %s' )   # confirm matches headRefOid from snapshot
```

The worktree shares `.git/` with the main repo, so the fetched ref is visible from either location, but the actual checkout — the only step that touches a working tree — happens inside `.cursor/working/${PFX}-worktree/`. **Standing rule: this is the default.** No stash dance, no branch switch in your main tree, no risk of leaving `pr-${PR}-tmp` checked out if the relocation is interrupted mid-flight. The worktree directory lives under `.cursor/working/`, which is already gitignored per AGENTS.md "Working Files" — and per the same convention, **scratch files stay in the main project's `.cursor/working/`** (the `${PFX}-*.json` / `.md` artifacts from "Filename convention for scratch files" above), not inside the worktree. The worktree is a transient git checkout; the scratch files are the audit trail of the run and belong in the canonical location.

If `git worktree add` fails because the destination already exists from a prior aborted run, clean up first:

```bash
git worktree remove --force .cursor/working/${PFX}-worktree 2>/dev/null || true
rm -rf .cursor/working/${PFX}-worktree
git branch -D pr-${PR}-tmp 2>/dev/null || true
```

then re-run the fetch + worktree-add.

The remaining git operation in this skill (the Phase 4 push) does **not** require cwd to be inside the worktree — the `pr-${PR}-tmp` ref is shared between the main repo and the worktree. Run it from wherever is convenient.

### Phase 2 — Create the draft advisory + start TPF (one shot)

Build the request body. The `start_private_fork: true` flag avoids a second round trip:

```jsonc
// .cursor/working/${PFX}-advisory-payload.json
{
  "summary": "{JIRA_KEY}: <≤1024 char descriptive title; no trailing (ENG7-####) suffix>",
  "description": "Jira: https://imh-internal.atlassian.net/browse/{JIRA_KEY}\n\n<rest of description>",
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
    { "login": "<original PR assignee>", "type": "remediation_developer" },
    { "login": "<original requested human reviewer>", "type": "remediation_reviewer" }
  ],
  "start_private_fork": true
}
```

Notes on the payload:

- `vulnerabilities` is **required** and must be a non-null array. The `package.ecosystem` enum: `rubygems`, `npm`, `pip`, `maven`, `nuget`, `composer`, `go`, `rust`, `erlang`, `actions`, `pub`, `other`, `swift`. For WordPress plugins, use `composer` if listed on Packagist, otherwise `other`.
- Set exactly one of `severity` (categorical enum) or `cvss_vector_string` (vector). Don't set both.
- `cve_id: null` is fine for a brand-new draft; reserve a CVE later via `POST /repos/{owner}/{repo}/security-advisories/{ghsa_id}/cve`.
- `credits[].type` enum: `analyst`, `finder`, `reporter`, `coordinator`, `remediation_developer`, `remediation_reviewer`, `remediation_verifier`, `tool`, `sponsor`, `other`.
- **Standing rule for summary + description shape (advisory side).** The `summary` MUST be prefixed with `{JIRA_KEY}: ` (key + colon + space), where `{JIRA_KEY}` is the Jira ticket captured in Phase 0 (e.g., `ENG7-3019: Sanitize newlines from config values written to .htaccess/nginx.conf`). Do not also tack the key on the end as `(ENG7-####)` — earlier advisories used that shape and have since been normalized; only the front-prefix is canonical now. The `description` MUST start with the bare line `Jira: https://imh-internal.atlassian.net/browse/{JIRA_KEY}` followed by a blank line, before any heading or prose. This pairing exists so anyone landing on the advisory (collaborator, future maintainer, auditor) sees the audit-trail home in zero clicks. The same prefix is reused on the private PR's title in Phase 5; the description's Jira-line is the canonical machine-detectable signal for idempotency on re-runs / re-edits.
- **Standing rule for credits**: derive the credit set from the original public PR's sidebar — every assignee on the PR is credited as `remediation_developer`, every requested reviewer is credited as `remediation_reviewer`. Filter out bot logins (`Copilot`, anything ending in `[bot]`) — those are not creditable GitHub accounts and the API will 422 on them. Do **not** ask the user to confirm; do **not** drop a human credit because of a perceived conflict (e.g., the same login being both assignee and reviewer, or the relocator being credited as `remediation_reviewer` because they were the original public PR's reviewer — the relocator becomes the *private PR's* author, but their role on the *advisory* is still "reviewer of the underlying remediation"). Just include the full filtered set. If GitHub dedupes or rejects an entry, the create call will surface that and you can adjust then; don't preemptively filter for hypothetical conflicts.

Submit:

```bash
gh api -X POST repos/$REPO/security-advisories \
  --input .cursor/working/${PFX}-advisory-payload.json \
  > .cursor/working/${PFX}-advisory-response.json

GHSA=$(jq -r '.ghsa_id' .cursor/working/${PFX}-advisory-response.json)
ADVISORY_URL=$(jq -r '.html_url' .cursor/working/${PFX}-advisory-response.json)
# Derive the slug suffix used in downstream filenames and the TPF repo slug.
# GHSA returns "GHSA-rgpr-5m2g-gvh2"; the slug is "rgpr-5m2g-gvh2", all-lowercase.
GHSA_SLUG=$(echo "${GHSA#GHSA-}" | tr '[:upper:]' '[:lower:]')
echo "Created $GHSA at $ADVISORY_URL (slug: $GHSA_SLUG)"
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
    gh api "repos/$FORK_GUESS" > .cursor/working/${PFX}-ghsa-${GHSA_SLUG}-fork-response.json
    FORK_FULL=$(jq -r '.full_name' .cursor/working/${PFX}-ghsa-${GHSA_SLUG}-fork-response.json)
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

Push directly to the TPF's URL without registering a remote — keeps `.git/config` untouched and complements the Phase 1 isolation default:

```bash
HEAD_REF_NAME=$(jq -r '.headRefName' .cursor/working/${PFX}-pr-snapshot.json)

git push "https://github.com/$FORK_FULL.git" pr-${PR}-tmp:${HEAD_REF_NAME}
```

(Earlier revisions of this skill registered a transient `ghsa` remote — `git remote add ghsa … / push ghsa … / git remote remove ghsa` — which worked but mutated the main repo's config briefly. Direct-URL push is the canonical form now. If you specifically want a named remote — e.g., for debugging a failed push and re-running interactively — you can still `git remote add ghsa "https://github.com/$FORK_FULL.git"`, push that way, and `git remote remove ghsa` afterward.)

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
JIRA_KEY=ENG7-XXXX  # captured in Phase 0; the advisory summary uses this same prefix.
RAW_TITLE=$(jq -r '.title' .cursor/working/${PFX}-pr-snapshot.json)
# Strip any existing `{KEY}: ` prefix or trailing `(KEY)` suffix from the public PR's
# title before re-applying the canonical front-prefix — keeps the rule idempotent if
# the public PR title was already manually prefixed.
CLEAN_TITLE=$(echo "$RAW_TITLE" | sed -E "s/^${JIRA_KEY}: ?//; s/ *\(${JIRA_KEY}\) *$//")
TITLE="${JIRA_KEY}: ${CLEAN_TITLE}"
BASE=$(jq -r '.default_branch' .cursor/working/${PFX}-ghsa-${GHSA_SLUG}-fork-response.json)

gh pr create \
  --repo $FORK_FULL \
  --base "$BASE" \
  --head ${HEAD_REF_NAME} \
  --title "$TITLE" \
  --body-file .cursor/working/${PFX}-ghsa-${GHSA_SLUG}-private-pr-body.md
```

**Standing rule for the TPF private PR's title.** It MUST be prefixed with `{JIRA_KEY}: ` to match the advisory's summary prefix — the two surfaces share the same "what is this advisory tracking" identifier and they should not drift. The closed public PR's title is independently renamed to the bare `{JIRA_KEY}` (no descriptive suffix) in Phase 6a, so the three closely-related GitHub surfaces end up with three distinct-but-related titles: bare key on the closed public PR (strips the search-engine signpost), prefixed + descriptive title on both the advisory and the TPF private PR (collaborators need to see what this is at a glance).

GitHub automatically links the PR to the parent advisory because the fork is the advisory's TPF. The PR shows up under "Collaborate on a patch" on the advisory page.

If you want to migrate review threads from the public PR onto the TPF for ongoing-review continuity, that is a separate skill: `.claude/skills/repost-pr-reviews-to-tpf/SKILL.md`. It pulls source reviews + inline comments via `gh api`, groups them by review, and posts each anchored to the source's `original_commit_id` at `original_line` so GitHub renders them as historical outdated comments matching the original review UX exactly. Attribution lives in an inline preface on every body. Run it after Phase 6 (when the public PR is closed) so the framing of "continuity restoration" is accurate; running it earlier just duplicates content that's still readable on the open public PR. The original review/inline comments do **not** travel with the commits — without that follow-up skill, the TPF starts from a blank Conversation tab.

#### 5a. Carry assignees and reviewers from the original PR

`gh pr create` creates the private PR with the relocator (whoever runs this skill, typically a repo admin like `cssjoe`) as the **author** and with no assignees or reviewers. The original public PR's sidebar metadata does *not* travel with the commits. If the original had `jacobd91` as the contributor / assignee and `cssjoe` as the requested reviewer, the private PR's sidebar starts empty and the PR's only natural participant is the relocator.

Carry the metadata across with two nuances:

1. **Assignees copy verbatim.** Whoever was assigned on the public PR should be assigned on the private PR too — they are the people who own the work and need it on their dashboard. Filter to only logins that are advisory collaborators (or members of a collaborating team), otherwise GitHub silently drops the assignment.

2. **Requested reviewers transpose.** GitHub forbids requesting review from the PR author, so any login that was a reviewer on the public PR but is now the private PR's author must be skipped — typically the relocator. The remaining original reviewers (Copilot bot, etc.) usually shouldn't carry over either: bots can't review the TPF, and the team membership already grants the natural human reviewers access without a formal request.

   **Standing rule: leave reviewers empty on the private PR.** Rely on team membership (e.g., `w3-total-cache-developers` for the BoldGrid repo — see "Repo-specific collaborator defaults" below) for access. Do **not** ask the user whether to add a specific human reviewer; the team membership covers the natural human reviewers, and asking on every run is the kind of question whose answer never changes. If a future engagement legitimately needs a non-team human reviewer surfaced in their own dashboard for the relocated work, the team can request that explicitly on the GHSA after Phase 5 — it's a one-line `gh pr edit --add-reviewer` follow-up against the private PR rather than a per-run prompt.

```bash
PRIVATE_PR_NUMBER=$(gh pr list --repo $FORK_FULL --json number --jq '.[0].number')
RELOCATOR=$(gh api user --jq '.login')

# Pull original assignees/reviewers from the public PR snapshot taken in Phase 0.
ORIG_ASSIGNEES=$(jq -r '.assignees[]?.login' .cursor/working/${PFX}-pr-snapshot.json | tr '\n' ' ')
ORIG_REVIEWERS=$(jq -r '.reviewRequests[]? | (.login // empty)' .cursor/working/${PFX}-pr-snapshot.json | tr '\n' ' ')

# Optional: filter assignees to advisory collaborators / team members so the
# assignment doesn't silently drop. (Cheap to skip — GitHub will just no-op
# unauthorized assignees rather than error.)
for LOGIN in $ORIG_ASSIGNEES; do
  gh pr edit $PRIVATE_PR_NUMBER --repo $FORK_FULL --add-assignee "$LOGIN"
done

# Reviewer transpose: standing rule is LEAVE EMPTY and rely on team
# membership. This loop is intentionally a noop; the filter exists
# only to document which logins would be skipped if a future variant
# of this skill ever decides to add specific reviewers automatically.
# Don't uncomment without team sign-off — see Phase 5a header.
for LOGIN in $ORIG_REVIEWERS; do
  if [ "$LOGIN" != "$RELOCATOR" ] && [ "$LOGIN" != "Copilot" ] && ! echo "$LOGIN" | grep -q '\[bot\]$'; then
    : # gh pr edit $PRIVATE_PR_NUMBER --repo $FORK_FULL --add-reviewer "$LOGIN"
  fi
done

# Verify
gh pr view $PRIVATE_PR_NUMBER --repo $FORK_FULL --json assignees,reviewRequests \
  | jq '{assignees: [.assignees[]?.login], reviewRequests: [.reviewRequests[]?.login // .reviewRequests[]?.slug]}'
```

Three gotchas worth memorizing:

- **`Review cannot be requested from pull request author.`** REST `POST /pulls/{n}/requested_reviewers` with the author's login returns `422` immediately. The transpose-or-skip step exists specifically because the relocator becomes the new author and would have otherwise been the natural inheritor of the reviewer slot.
- **Bot accounts are not addable as TPF reviewers.** Copilot's `copilot-pull-request-reviewer[bot]` is not a TPF collaborator and the API will silently drop or 422 the request. Never carry bot reviewers across.
- **Assignment silently drops if the assignee lacks TPF access.** If `jacobd91` is not in the collaborating team and not in `collaborating_users[]`, the `--add-assignee jacobd91` call appears to succeed but the assignment never lands. Either add them as `collaborating_users[]` first (per the advisory-collaborator section below), or rely on the team membership.

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
    > .cursor/working/${PFX}-ghsa-${GHSA_SLUG}-collab-patch.json
  gh api -X PATCH repos/$REPO/security-advisories/$GHSA \
    --input .cursor/working/${PFX}-ghsa-${GHSA_SLUG}-collab-patch.json
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

Build the comment body in `${PFX}-ghsa-${GHSA_SLUG}-jira-backlink.md`:

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
  "commentBody": "<contents of ${PFX}-ghsa-${GHSA_SLUG}-jira-backlink.md>",
  "contentFormat": "markdown",
  "responseContentFormat": "markdown"
}
```

Save the response to `${PFX}-ghsa-${GHSA_SLUG}-jira-backlink-response.json`. Then verify:

- The response `body` contains the literal advisory URL (search for `/security/advisories/${GHSA}` substring).
- The response `body` contains the private PR URL.
- The response `body` does not contain stray blockquote `>` markers from a botched conversion.

If the verify fails: see the `pr-content-to-jira` skill's "Phase 3 — CRITICAL: Jira markdown caveat" — the markdown→ADF converter silently drops blockquotes. Strip any `>`-prefixed lines, re-post, and reference the broken comment ID in the corrected one ("supersedes comment XYZ"); MCP cannot edit Jira comments after the fact.

### Phase 6 — Close the original public PR and delete its branch

#### 6a. Rename the PR title to the parent Jira ticket key

Before closing, replace the PR's descriptive title with just the parent Jira ticket key from Phase 5b (e.g., `ENG7-2908`). The original title was already indexed before this skill ran (GHArchive, search engines, etc. — see caveat #1), but the closed-PR landing page sits at a stable URL that search engines will keep re-crawling indefinitely. Replacing the descriptive title with just the Jira key removes the public signpost that says "the patch is in the commits below" while still leaving an admin a breadcrumb to the audit-trail ticket. Anyone outside Atlassian SSO sees a useless identifier; anyone inside SSO has one click to the full context.

```bash
JIRA_KEY=ENG7-2908   # parent ticket from Phase 5b
gh pr edit $PR --repo $REPO --title "$JIRA_KEY"

gh pr view $PR --repo $REPO --json title,state | jq
```

If Phase 5b was skipped (no Jira ticket in scope), use a neutral bracket-prefix instead — e.g., `[Withdrawn] Internal change`. **Never** anything that hints at the security context (no "vuln", "security", "access control", "CVE", "auth", "nonce", "capability", etc.).

Title rename is *forward-defense only*. It does not unindex the original title from search engines, GHArchive, or webarchive snapshots that already captured the old title; those copies live outside GitHub's control. What it does change is the stable closed-PR landing page that future crawlers re-read.

#### 6b. Strip sidebar metadata and shrink the description to just the Jira URL

**Standing rule:** before closing, strip every non-essential field from the PR sidebar and shrink the description to a single line — the Jira URL. The sidebar fields (assignees, requested reviewers, requested teams, labels, milestone, linked issues in the "Development" section) outlive the close: they keep the PR appearing in dashboards, milestone burndowns, "PRs assigned to me" panels, and Jira/GitHub-integration cards weeks after the relocation. They imply the PR is still in someone's workflow when it isn't. Same logic for the description — once the workstream has moved private, every paragraph beyond the Jira URL is an excuse for an onlooker to read further.

This step is also forward-defense only: people watching the repo received notifications when these fields were originally set, and any external mirror that crawled the PR before this run captured the original sidebar. The goal is to remove the *current state* signposts, not to retract the historical record.

What to strip and how:

```bash
PR=1315
REPO=BoldGrid/w3-total-cache
JIRA_KEY=ENG7-2909   # parent ticket from Phase 5b
URL="https://imh-internal.atlassian.net/browse/$JIRA_KEY"

# Snapshot current state so we can see what we're removing.
gh pr view $PR --repo $REPO \
  --json assignees,reviewRequests,labels,milestone,closingIssuesReferences \
  > .cursor/working/${PFX}-sidebar-snapshot.json

# 1. Assignees: remove every assignee.
for LOGIN in $(jq -r '.assignees[]?.login' .cursor/working/${PFX}-sidebar-snapshot.json); do
  gh pr edit $PR --repo $REPO --remove-assignee "$LOGIN"
done

# 2. Requested reviewers (individuals): remove each.
for LOGIN in $(jq -r '.reviewRequests[]? | (.login // empty)' .cursor/working/${PFX}-sidebar-snapshot.json); do
  gh pr edit $PR --repo $REPO --remove-reviewer "$LOGIN"
done

# 3. Requested reviewer teams: remove each. (Teams use the slug, not login.)
for SLUG in $(jq -r '.reviewRequests[]? | (.slug // empty)' .cursor/working/${PFX}-sidebar-snapshot.json); do
  gh pr edit $PR --repo $REPO --remove-reviewer "$REPO_OWNER/$SLUG"
done

# 4. Labels: remove every label.
for LABEL in $(jq -r '.labels[]?.name' .cursor/working/${PFX}-sidebar-snapshot.json); do
  gh pr edit $PR --repo $REPO --remove-label "$LABEL"
done

# 5. Milestone: clear if set. (--milestone "" doesn't always work; PATCH the
#    underlying issue with milestone=null instead.)
if [ "$(jq -r '.milestone.title // empty' .cursor/working/${PFX}-sidebar-snapshot.json)" != "" ]; then
  gh api -X PATCH repos/$REPO/issues/$PR -F milestone= --jq '.milestone'
fi

# 6. Linked issues (Development section). Two ways issues become linked:
#    a) Closing-keyword in PR body (Closes #123) — handled by the body
#       replacement below; once the body has no closing keywords, those
#       links drop on the next refresh.
#    b) Manually linked via the GitHub UI ("Link issues" button) — these
#       persist across body edits. Disconnect each via GraphQL.
LINKED_NUMBERS=$(jq -r '.closingIssuesReferences[]?.number // empty' .cursor/working/${PFX}-sidebar-snapshot.json)
if [ -n "$LINKED_NUMBERS" ]; then
  PR_NODE_ID=$(gh api repos/$REPO/pulls/$PR --jq '.node_id')
  for NUM in $LINKED_NUMBERS; do
    ISSUE_NODE_ID=$(gh api repos/$REPO/issues/$NUM --jq '.node_id')
    gh api graphql -f query='mutation($pr: ID!, $issue: ID!){
      disconnectIssueLinkedToPullRequest(input: {linkedIssueId: $issue, pullRequestId: $pr}) {
        pullRequest { id }
      }
    }' -F pr="$PR_NODE_ID" -F issue="$ISSUE_NODE_ID"
  done
fi

# 7. Description: replace with the Jira URL alone. Nothing else.
gh pr edit $PR --repo $REPO --body "$URL"
```

Verify the strip:

```bash
gh pr view $PR --repo $REPO \
  --json body,assignees,reviewRequests,labels,milestone,closingIssuesReferences \
  | jq '{body, assignees: [.assignees[]?.login], reviewRequests: [.reviewRequests[]?.login // .reviewRequests[]?.slug], labels: [.labels[]?.name], milestone: .milestone.title, closingIssuesReferences: [.closingIssuesReferences[]?.number]}'
```

Expected end state for every field other than `body`: `[]` or `null`. `body` should be the bare Jira URL.

Three gotchas worth memorizing:

- **`--milestone ""` is unreliable.** `gh pr edit --milestone ""` sometimes errors and sometimes is a noop depending on the gh version. The REST PATCH with `milestone=` (empty value) is the reliable clearer.
- **Removing requested reviewers does not retract the original notification.** Reviewers were emailed when first requested; removing them now only stops the PR from showing up in their "PRs awaiting your review" panel going forward. This is the desired outcome (clear the dashboard) but is not a "they never knew" operation.
- **Removing a requested reviewer does NOT clear the "requested changes" badge.** If that reviewer already submitted a `CHANGES_REQUESTED` review, the Reviewers sidebar keeps showing them with a red "requested changes" indicator forever — that's driven by the submitted *review*'s state, not by the requested-reviewer field. Phase 6b.5 below covers this; it must run before the close (Phase 6e) because GitHub silently no-ops `dismiss` on closed PRs (both REST and GraphQL).

#### 6b.5. Dismiss any blocking reviews before closing

GitHub locks the dismiss action once a PR is closed. Both REST `PUT /pulls/{n}/reviews/{id}/dismissals` and GraphQL `dismissPullRequestReview` return success without errors but silently no-op — the only way to clear a stale `CHANGES_REQUESTED` badge after the fact is reopen → dismiss → re-close, which costs two timeline events and (typically) two notifications per PR per watcher.

Avoid that by dismissing here, before Phase 6e closes the PR. Target every review whose `state` is `CHANGES_REQUESTED` (or `APPROVED`, if you want to drop those too — judgment call; the typical case is you only need to clear the blocking ones). Bot reviewers are already `COMMENTED` by default and don't show a state badge in the sidebar.

```bash
# Find every blocking review (CHANGES_REQUESTED). APPROVED is optional —
# leaving approvals visible on a closed-then-relocated PR is usually
# fine, but include them in the loop if you want a fully neutral sidebar.
gh api repos/$REPO/pulls/$PR/reviews \
  --jq '[.[] | select(.state == "CHANGES_REQUESTED") | .id]' \
  > .cursor/working/${PFX}-blocking-reviews.json

for REVIEW_ID in $(jq -r '.[]' .cursor/working/${PFX}-blocking-reviews.json); do
  gh api -X PUT "repos/$REPO/pulls/$PR/reviews/$REVIEW_ID/dismissals" \
    -f message="Workstream relocated; review no longer applicable." \
    -f event=DISMISS \
    --jq '{id, state}'
done
```

Verify the dismissal landed (state should be `DISMISSED`):

```bash
gh api repos/$REPO/pulls/$PR/reviews \
  --jq '[.[] | select(.user.login != "" ) | {id, user: .user.login, state}]'
```

The dismissal message is public — keep it neutral and free of vuln/GHSA context.

#### 6c. Build and post the close comment

Build a terse, non-revealing close comment in `${PFX}-close-comment.md`. The comment is public; it must not name the GHSA, link the advisory URL, or restate the vulnerability:

```markdown
Closing this PR. Continued work on this fix is being handled through a private review process. A new public PR will be opened when the change is ready to merge. Thanks to everyone who reviewed here.
```

#### 6d. Minimize every bot-authored review / inline / issue comment as `OUTDATED`

**Standing rule:** any PR being relocated to a GHSA gets every bot-authored comment surface (Copilot / `copilot-pull-request-reviewer[bot]`, Dependabot, GitHub Actions bots, etc.) minimized as `OUTDATED` before the PR is closed — regardless of whether the individual comment looks sensitive in isolation. The PR is being closed because the workstream moved private; bot review threads anchored against a now-closed public branch are stale by definition, and leaving Copilot's review summaries + inline comments visible on a closed-then-relocated PR signals to onlookers that there was substantive review activity worth re-reading. Hide them.

**Carve-out: codecov coverage reports are intentionally left visible.** Coverage reports posted by `codecov-commenter` (or any future `codecov`-prefixed login such as `codecov[bot]`) are *not* minimized on a relocated PR. Coverage data is a diagnostic signal that doesn't telegraph security review activity — and the same numbers are reachable on codecov.io regardless of what the PR thread shows, so hiding the comment changes nothing useful while breaking the link some teammates rely on for "did the move drop coverage?" follow-ups. The bot-minimization filter below explicitly skips any login starting with `codecov`; everything else gets the standing rule. Note that `codecov-commenter` historically has `user.type: "User"` (not `Bot`) and a login without a `[bot]` suffix, so the carve-out is mostly belt-and-braces against a future GitHub-App migration that would change the login shape — but bake the explicit skip in regardless so a future filter rewrite can't accidentally absorb codecov.

This step is independent of (and additive to) any per-item categorization the `pr-content-to-jira` skill ran earlier. Even if `pr-content-to-jira` was skipped entirely, run this. Even if the non-codecov bot comments were left visible because they were "harmless coding-standards nits," minimize them anyway — the closed-PR context is what changes the calculus.

Pull node IDs for every bot surface across all four PR comment surfaces, then minimize:

```bash
PR=1315
REPO=BoldGrid/w3-total-cache

# All review summaries authored by any *[bot] login.
gh api repos/$REPO/pulls/$PR/reviews \
  --jq '[.[] | select(.user.login | endswith("[bot]")) | .node_id]' \
  > .cursor/working/${PFX}-bot-review-nodes.json

# All inline review comments authored by Copilot or any *[bot] login.
# (Note: GitHub renders Copilot's inline-comment author as the literal "Copilot",
#  not "copilot-pull-request-reviewer[bot]". Match both.)
gh api --paginate repos/$REPO/pulls/$PR/comments \
  --jq '[.[] | select(.user.login == "Copilot" or (.user.login | endswith("[bot]"))) | .node_id]' \
  > .cursor/working/${PFX}-bot-inline-nodes.json

# All issue comments authored by any *[bot] login (or literal "Copilot"),
# EXCLUDING codecov-commenter / codecov[bot] — coverage reports are
# intentionally left visible per the carve-out above. The startswith
# guard catches both the current "codecov-commenter" (user.type=User,
# no [bot] suffix — which the [bot]-suffix filter already misses, but
# being explicit is forward-defense) and any future "codecov[bot]"
# spelling that the filter WOULD otherwise absorb.
gh api repos/$REPO/issues/$PR/comments \
  --jq '[.[] | select((.user.login | endswith("[bot]")) or .user.login == "Copilot") | select(.user.login | startswith("codecov") | not) | .node_id]' \
  > .cursor/working/${PFX}-bot-issue-nodes.json

# Minimize every node, idempotent (already-minimized nodes return success).
for FILE in .cursor/working/${PFX}-bot-{review,inline,issue}-nodes.json; do
  for NODE_ID in $(jq -r '.[]' "$FILE"); do
    gh api graphql -f query='mutation($id: ID!){
      minimizeComment(input: {subjectId: $id, classifier: OUTDATED}) {
        minimizedComment { isMinimized minimizedReason }
      }
    }' -F id="$NODE_ID"
  done
done
```

Verify before proceeding to 6d:

```bash
# Should print isMinimized=true for every bot-authored node.
for FILE in .cursor/working/${PFX}-bot-{review,inline,issue}-nodes.json; do
  for NODE_ID in $(jq -r '.[]' "$FILE"); do
    gh api graphql -f query='query($id: ID!){
      node(id: $id) {
        ... on Minimizable { isMinimized minimizedReason }
      }
    }' -F id="$NODE_ID" --jq '.data.node | "\(.isMinimized) \(.minimizedReason)"'
  done
done
```

**Codecov coverage comments are not minimized and not deleted — they stay visible.** This is the same carve-out the standing rule above codifies; the filter already skips them. Don't second-guess it on a per-PR basis ("but this codecov comment quotes the changed file paths…") — the file paths are visible in the diff anyway, the coverage numbers themselves don't telegraph security context, and the codecov.io report linked from the comment renders the same data regardless of whether the PR comment is collapsed. Leaving codecov visible also preserves the "did the relocated change drop coverage?" answer for anyone who revisits the closed PR weeks later.

**Why not delete the (non-codecov) bot comments outright?** A few reasons:

- Deletes destroy the comment row; minimize keeps the row so the PR's conversation timeline still has shape (other comments may reply to / quote the bot review).
- Some bot integrations (Copilot review session links, etc.) are actively referenced from internal docs / alert routes; preserving the row keeps those links resolvable.
- `OUTDATED` is the truthful classifier here — the PR is closed, the branch is gone, the review is by definition out of date.

#### 6e. Post the close comment, close the PR, and delete the branch

```bash
gh pr comment $PR --repo $REPO --body-file .cursor/working/${PFX}-close-comment.md
gh pr close   $PR --repo $REPO --delete-branch
```

Note: do not also minimize this freshly-posted close comment — it's a maintainer-authored signpost meant to be visible at the top of the closed-PR landing page. The bot-comment standing rule above (Phase 6d) only applies to `*[bot]` and `Copilot` author logins.

Inverse note for the maintainer-authored content the `pr-content-to-jira` skill replaced with one-line pointer text earlier (own review summaries + own inline comments): once the PR is closed, those pointer-bodied surfaces should also be minimized as `OUTDATED`. They survived edit-instead-of-delete because the bodies aren't bot-authored, but on a closed-then-relocated PR they're now just visual noise pointing at a Jira ticket the casual onlooker can't open. The `pr-content-to-jira` skill's Phase 6 covers the edit-to-pointer step; this skill's Phase 6d quietly subsumes the bot ones; the maintainer-authored pointer bodies need a separate sweep here. Idempotent — if any are already minimized, the mutation returns success.

```bash
# Maintainer-authored own surfaces that were edited to the pointer line by
# pr-content-to-jira's Phase 6. Identify them by current body length and
# the substring "moved to internal ticket".
for KIND in reviews comments; do
  if [ "$KIND" = "reviews" ]; then
    URL_PATH="repos/$REPO/pulls/$PR/reviews"
  else
    URL_PATH="repos/$REPO/pulls/$PR/comments"
  fi
  gh api --paginate "$URL_PATH" \
    --jq '[.[] | select(.body | contains("moved to internal ticket")) | .node_id]' \
    | jq -r '.[]' \
    | while read -r NODE_ID; do
      [ -n "$NODE_ID" ] || continue
      gh api graphql -f query='mutation($id: ID!){
        minimizeComment(input: {subjectId: $id, classifier: OUTDATED}) {
          minimizedComment { isMinimized minimizedReason }
        }
      }' -F id="$NODE_ID"
    done
done

# Same sweep on issue comments (own comments edited to the pointer).
gh api repos/$REPO/issues/$PR/comments \
  --jq '[.[] | select(.body | contains("moved to internal ticket")) | .node_id]' \
  | jq -r '.[]' \
  | while read -r NODE_ID; do
    [ -n "$NODE_ID" ] || continue
    gh api graphql -f query='mutation($id: ID!){
      minimizeComment(input: {subjectId: $id, classifier: OUTDATED}) {
        minimizedComment { isMinimized minimizedReason }
      }
    }' -F id="$NODE_ID"
  done
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

Tear down the local worktree once you're satisfied the relocation is complete. The temp branch `pr-${PR}-tmp` is no longer referenced by anything you'll need:

```bash
git worktree remove .cursor/working/${PFX}-worktree
git branch -D pr-${PR}-tmp
```

(Optional — if you'd rather keep the local branch around for manual cherry-picking against the TPF later, skip the `git branch -D`. The worktree should still go.)

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

7. **Comments don't migrate with commits.** Reviews, inline comments, and conversation threads from the public PR stay on the public PR. Migrate any context worth preserving into the advisory description or as comments on the new private PR — and consider running `pr-content-to-jira` to also copy that context into Jira for the audit trail. For the specific case of replaying the source PR's review threads onto the TPF for ongoing-review continuity (Copilot bot reviews, human reviewer threads, etc.), use the dedicated follow-up skill `.claude/skills/repost-pr-reviews-to-tpf/SKILL.md` — it handles the anchor-to-original-commit pattern that makes the reposts render correctly as historical outdated comments.

8. **`gh` has no native security-advisory subcommand** as of this writing. Everything here goes through `gh api`. If a future `gh security` (or `gh repo security`) subcommand exists, prefer it over the raw REST calls.

9. **Contributor-fork PRs are messy.** When `isCrossRepository: true`, `--delete-branch` only closes/cleans the merge target in the public repo. The contributor's fork branch is unaffected and remains visible. The contributor must delete from their own fork.

10. **The close comment is public.** Don't put the GHSA ID, advisory URL, vuln summary, or any "we moved this because…" detail in it. A bland "moving to private review" is enough.

11. **Run `pr-content-to-jira` first if applicable.** Once the public PR is closed and its branch deleted, redacting individual comments on the closed PR is harder to justify and has weaker UI affordances. Move sensitive descriptive content to Jira while the PR is still open.

12. **The Jira back-link is not automatic.** The advisory description points outbound to Jira, but Jira does not learn about the GHSA on its own. Phase 5b is the explicit back-link step; without it, anyone landing on the Jira ticket weeks later sees only the closed public PR and has no path forward to the live private workstream. Always run Phase 5b before Phase 6 so a failure doesn't leave Jira out of date with a closed public PR pointing at it.

13. **Minimize bot comments on relocated PRs, with a codecov carve-out (standing rule).** Phase 6d minimizes every `*[bot]` / `Copilot`-authored review summary, inline comment, and issue comment as `OUTDATED` before the PR is closed — *except* codecov coverage reports (`codecov-commenter` or any `codecov`-prefixed login), which are intentionally left visible. The trigger for the minimize side of the rule is "this PR is being closed because the workstream moved to a private GHSA," not "this comment looked dangerous"; closed-then-relocated PRs should not advertise stale Copilot/Dependabot review activity to anyone landing on the closed-PR landing page weeks later. The trigger for the codecov carve-out is "coverage data is diagnostic, not security-context — and the same numbers live at codecov.io anyway, so hiding the PR comment changes nothing useful while breaking the link teammates rely on for follow-ups." The bot filter in Phase 6d explicitly excludes `codecov`-prefixed logins to make the carve-out durable against any future Codecov GitHub-App migration that switches the login from `codecov-commenter` (current) to `codecov[bot]` (which the `[bot]`-suffix filter would otherwise absorb). The minimize mutation is idempotent; safe to re-run on a PR where some bot comments are already minimized. The maintainer-authored close comment stays visible. Maintainer-authored *pointer* comments (the ones `pr-content-to-jira` edited from sensitive content down to a single "moved to internal ticket..." line) get a separate minimize sweep at the bottom of Phase 6e — same logic, different author class, so a different code path.

14. **Strip the sidebar and shrink the description on relocated PRs (standing rule).** Phase 6b clears assignees, requested reviewers (users *and* teams), labels, milestone, and any manually-linked issues in the "Development" section, and replaces the PR description with **only** the Jira URL — no surrounding paragraph, no audit-trail explainer, no template prose. The sidebar fields outlive the close: they keep the PR appearing in dashboards, milestone burndowns, "PRs assigned to me" panels, and Jira/GitHub-integration cards weeks after the relocation. The description's job after relocation is to point at the live workstream and nothing else; every paragraph beyond the bare URL is an invitation for an onlooker to read further. Forward-defense only — fields and prose that were visible before this run are already in notification emails, GHArchive, and external mirrors.

15. **Dismiss blocking reviews BEFORE closing (standing rule).** GitHub silently no-ops `dismiss` on closed PRs — both REST `PUT /pulls/{n}/reviews/{id}/dismissals` and GraphQL `dismissPullRequestReview` return success without errors but the review's `state` stays `CHANGES_REQUESTED` (or `APPROVED`). The "requested changes" red badge on the closed-PR Reviewers sidebar is driven by the submitted review's state, not the requested-reviewer field, so removing the requested reviewer in Phase 6b doesn't clear it. Phase 6b.5 must dismiss every blocking review while the PR is still open. The only post-close fix is reopen → dismiss → re-close, which costs ~2 notifications per watcher per PR — usually not worth it for a historical badge. Keep the dismissal message neutral; it's public.

16. **The private PR's author is the relocator, not the original PR author (transpose rule).** `gh pr create` in Phase 5 sets the *relocator* as the author of the private PR — typically a repo admin like `cssjoe` who is shepherding the relocation, not the original contributor (`jacobd91` etc.). This inverts the original PR's author/reviewer relationship: the original author has no formal role on the private PR by default, and the original reviewer (often the relocator) is now blocked from being a reviewer because GitHub forbids `Review cannot be requested from pull request author`. Phase 5a carries assignees verbatim and **standing rule is leave reviewers empty on the private PR** — rely on team membership (e.g., `w3-total-cache-developers` for the BoldGrid repo) for access. Don't ask per-run whether to add a specific human reviewer; the team membership covers the natural human reviewers, and asking on every run is the kind of question whose answer never changes. Never carry bot reviewers (Copilot, etc.) — they aren't TPF collaborators and the API will silently drop or 422 the request. If a future engagement legitimately needs a non-team human reviewer surfaced in their own dashboard, the team can request that explicitly post-Phase-5 — it's a one-line `gh pr edit --add-reviewer` follow-up rather than a per-run prompt.

17. **Credits derive from the original PR's sidebar — don't ask, ignore conflicts (standing rule).** Phase 2's `credits[]` payload is built mechanically from the original public PR's sidebar: every assignee → `remediation_developer`, every requested reviewer → `remediation_reviewer`. Filter only bot logins (`Copilot`, anything ending in `[bot]`) since the API will 422 on those. Do **not** ask the user to confirm the credit set, and do **not** preemptively drop human entries because of a perceived conflict — the same login being both assignee and reviewer is fine, the relocator being credited as `remediation_reviewer` because they were the original public PR's reviewer is fine (the relocator becomes the *private PR's* author but their role on the *advisory* is still "reviewer of the underlying remediation"). If GitHub dedupes or rejects an entry, the create call's response surfaces that and you can adjust on the failure; preemptive filtering for hypothetical conflicts just produces under-credited advisories and per-run questions whose answers never change.

18. **Advisory summary + TPF PR title MUST start with `{JIRA_KEY}: `, and advisory description MUST start with `Jira: <URL>` (standing rule).** Phase 2's summary string is `{JIRA_KEY}: {clean descriptive title}` (no trailing `(ENG7-####)` parenthetical — that older shape has been retired, only the front-prefix is canonical). Phase 2's description's first line is the bare canonical link `Jira: https://imh-internal.atlassian.net/browse/{JIRA_KEY}` followed by one blank line, then the rest of the description. Phase 5's `gh pr create` re-uses the same `{JIRA_KEY}: {clean title}` prefix on the TPF private PR. Together these make the audit-trail home reachable in zero clicks from either the advisory page or the TPF PR list — a much shorter path than "scan the description for whichever URL the writer happened to mention first." The Phase 6a rename of the *closed public PR* to the bare `{JIRA_KEY}` (no descriptive suffix) is intentionally different: it strips the public-search signpost, where the advisory + TPF PR want the descriptive title preserved for collaborator UX. Idempotency: if Phase 2 / Phase 5 ever re-run against an advisory or TPF PR that already has the prefix, strip an existing `{KEY}: ` prefix or trailing `(KEY)` suffix from the source title before re-applying — the sed pattern is in the Phase 5 snippet.

---

## Quick reference: end-to-end sequence

```
0. Capture: PR URL, severity, summary, description, optional CVE/CWE/credits,
            Jira ticket key (REQUIRED — drives the back-link in step 6b,
              the advisory summary prefix `{KEY}: ...` in step 2, and
              the TPF private PR title prefix `{KEY}: ...` in step 5),
            close-message wording.
1. Fetch PR head + worktree:
                            git fetch origin +refs/pull/$PR/head:refs/heads/pr-${PR}-tmp
                            git worktree add .cursor/working/${PFX}-worktree pr-${PR}-tmp
                            (Standing rule: worktree-isolated default — no
                             stash, no branch switch in the main tree.
                             Scratch files still go in the main project's
                             .cursor/working/, NOT inside the worktree.)
2. Create draft GHSA + TPF: gh api -X POST repos/$REPO/security-advisories --input payload.json
                             (with start_private_fork: true)
                             Standing rule: summary starts with `{KEY}: `;
                              description starts with `Jira: <URL>` line +
                              blank line BEFORE any heading or prose.
                              See caveat #18.
3. Poll until fork is reachable; capture full_name from response.
4. Push branch:             git push "https://github.com/$FORK_FULL.git" pr-${PR}-tmp:<branch>
                            (direct URL push — no transient remote in .git/config.
                             same-repo PRs: expect "Everything up-to-date" —
                             TPF inherits all branches.)
5. Open private PR:         gh pr create --repo <fork> --base "$(jq -r .default_branch <fork-response>)" --head <branch> --title "$JIRA_KEY: $CLEAN_TITLE" ...
                            (TPF default branch == parent repo's default; not always "main")
                            Standing rule: title is `{JIRA_KEY}: {clean title}` —
                              same prefix as the advisory summary. Strip any
                              existing `{KEY}: ` prefix or trailing `(KEY)`
                              from the public PR's title before re-applying
                              for idempotency. See caveat #18.
                            Then carry over assignees/reviewers from the
                              public PR snapshot (Phase 5a). Assignees
                              copy verbatim. Reviewers TRANSPOSE: skip
                              the relocator (now the new PR author —
                              GitHub 422s "Review cannot be requested
                              from pull request author"), skip Copilot
                              and any *[bot]. Standing rule: leave
                              reviewers EMPTY and rely on team membership
                              for access — don't ask per-run.
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
7. Public-side cleanup:     gh pr edit    $PR --title "$JIRA_KEY"
                            (rename to the parent Jira key from Phase 5b,
                             e.g. ENG7-2908; strips the descriptive
                             search-engine signpost off the closed-PR
                             landing page. Use "[Withdrawn] Internal change"
                             if no Jira ticket. Never use vuln-revealing
                             words in the new title.)
                            Strip sidebar metadata: clear assignees,
                              requested reviewers (users + teams),
                              labels, milestone, and any manually-linked
                              issues in the Development section. Set body
                              to ONLY the Jira URL — every paragraph
                              beyond it is an excuse for an onlooker to
                              read further. Standing rule for relocated
                              PRs — see Phase 6b.
                            Dismiss every CHANGES_REQUESTED review (and
                              optionally APPROVED ones) BEFORE closing.
                              GitHub silently no-ops dismiss on closed
                              PRs (both REST and GraphQL); the only
                              alternative is reopen → dismiss → re-close,
                              which costs notifications. See Phase 6b.5.
                            Minimize every *[bot] / Copilot review +
                              inline + issue comment as OUTDATED via
                              GraphQL minimizeComment, EXCEPT codecov
                              coverage comments (codecov-commenter /
                              future codecov[bot]) — those are
                              intentionally left visible (carve-out:
                              coverage data is diagnostic, not
                              security-context, and codecov.io has
                              the same numbers anyway). The Phase 6d
                              filter explicitly excludes codecov-
                              prefixed logins. Standing rule for
                              relocated PRs — closed-then-relocated
                              PRs should not advertise stale bot review
                              activity. Idempotent; safe to re-run.
                              Also minimize any maintainer-authored
                              pointer-body comments (those edited by
                              pr-content-to-jira to "moved to internal
                              ticket ...") — same logic, separate sweep.
                            gh pr comment $PR (bland public message)
                            gh pr close   $PR --delete-branch
8. Verify: PR closed, public branch gone, fork branch present at headRefOid,
   advisory state draft, new private PR listed under the advisory,
   Jira's most recent comment contains the advisory + private PR URLs.
   Then tear down: git worktree remove .cursor/working/${PFX}-worktree
                   git branch -D pr-${PR}-tmp

9. (Optional follow-up) Replay the source PR's review threads onto the TPF for
   ongoing-review continuity:
   See `.claude/skills/repost-pr-reviews-to-tpf/SKILL.md` — pulls source reviews
   + inline comments via `gh api`, posts each anchored to the source's
   `original_commit_id` at `original_line` so GitHub renders them as historical
   outdated comments matching the original review UX. Attribution lives in an
   inline preface on every body. Without this step, the TPF starts from a blank
   Conversation tab; with it, Copilot bot reviews / human reviewer threads on
   the now-closed public PR are also available to reviewers on the TPF.

Standing rule: ALWAYS run `pr-content-to-jira` first (Phase 0 / before step 1),
not just before step 7. Don't ask the user — even a PR with no obvious sensitive
content benefits from the pre-relocation Jira ↔ PR cross-link the back-link in
step 6b depends on.
```
