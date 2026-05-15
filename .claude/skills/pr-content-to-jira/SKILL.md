---
name: pr-content-to-jira
description: Move sensitive descriptive content (vuln scope, finding IDs, exploit-shaped review commentary) off a public GitHub PR and into an internal Jira ticket as the audit-trail record of truth. Use when a public-repo PR has accumulated content that should not stay in the default public view — enumerated audit-finding IDs, kill-chain summaries, partial/known-unfixed vuln descriptions, reviewer-flagged blockers that double as exploit primitives against the in-flight fix — and an internal Jira ticket exists (or can be created) to receive it. Requires repo-admin GitHub auth and authenticated Atlassian Jira MCP. Do not use as a substitute for a real GHSA / private-fork workflow when the disclosure is severe; this skill redacts the current default view, not the public history.
---

# Move Sensitive PR Content to Jira

Move security-sensitive descriptive content off a public GitHub PR and into an internal Jira ticket. This skill encodes the workflow plus every gotcha discovered the hard way during prior runs.

Tested against `BoldGrid/w3-total-cache#1313` ⇄ `ENG7-2908` on 2026-05-14. See "Caveats to surface every time" for the public-already-leaked / minimize≠delete / blockquote-loss issues this skill exists to avoid re-encountering.

---

## When to use this

- A public-repo PR (or its review thread) contains content that should not stay in the default public view: enumerated audit-finding IDs, kill-chain summaries, partial / known-unfixed vuln descriptions, reviewer-flagged blockers that double as exploit primitives against the in-flight fix.
- An internal Jira ticket already exists (or you can create one) to receive the moved content.
- You are an admin on the public repo (you can edit the PR body and minimize/delete others' comments). If you are *only* the comment author, you can still do your own redactions; cross-author redactions require admin.

Do **not** use this as a substitute for a real GHSA / private-fork workflow if the disclosure is severe. This redacts the *current default view*; the public PR's history was already indexed by GitHub search and external mirrors before this action.

## Prerequisites

- `gh` CLI logged in with repo-admin scope. Verify:
  ```bash
  gh auth status
  gh api repos/{owner}/{repo}/collaborators/{login}/permission --jq '.role_name'
  ```
- Atlassian Jira MCP server authenticated. Verify by calling `getAccessibleAtlassianResources` and capturing the `cloudId`.
- `jq` available locally.
- A writable `.cursor/working/` directory (gitignored).

---

## Filename convention for scratch files

All temporary working files for this task **must** be prefixed with the PR number followed (optionally) by the Jira key, separated by hyphens. See AGENTS.md "Working Files" for the workspace-wide rule.

Examples for this skill:
- `{PR}-pr-body.json` (raw PR description snapshot)
- `{PR}-issue-comments.json`, `{PR}-reviews.json`, `{PR}-inline-comments.json` (inventory)
- `{PR}-jira-{author}-comment.md` (Jira draft per author)
- `{PR}-pointer-body.md` (PR description replacement)
- `{PR}-anchor-comment.md` (new pinned PR comment explaining the move)

Never write to `/tmp/` or anywhere outside the project tree.

---

## Workflow

### Phase 0 — Confirm scope and inputs

Capture from the user (or ask):

- PR URL → derive `{owner}`, `{repo}`, `{n}`
- Jira ticket key (e.g., `ENG7-2908`)
- Any pre-decided constraints (e.g., "leave Copilot bot content alone", "keep codecov visible")

Run in parallel:

- `gh auth status` and the permission check above
- `getAccessibleAtlassianResources` → capture `cloudId`
- `getJiraIssue` with `issueIdOrKey: {KEY}` → confirm the ticket exists, capture its current comment count and parent epic for the user's mental model

### Phase 1 — Inventory the PR

Pull structured data into prefixed scratch files:

```bash
PR=1313
REPO=BoldGrid/w3-total-cache
mkdir -p .cursor/working

gh api repos/$REPO/pulls/$PR --jq '{body, user: .user.login, title}' \
  > .cursor/working/${PR}-pr-body.json
gh api repos/$REPO/issues/$PR/comments \
  --jq '[.[] | {id, user: .user.login, created_at, body}]' \
  > .cursor/working/${PR}-issue-comments.json
gh api repos/$REPO/pulls/$PR/reviews \
  --jq '[.[] | {id, user: .user.login, state, submitted_at, body}]' \
  > .cursor/working/${PR}-reviews.json
gh api repos/$REPO/pulls/$PR/comments \
  --jq '[.[] | {id, user: .user.login, path, line, created_at, body, in_reply_to_id, pull_request_review_id}]' \
  > .cursor/working/${PR}-inline-comments.json
```

There are **four** distinct comment surfaces — make sure all four files are pulled. They are *not* a superset of each other:

| File | API endpoint | What lives here |
|---|---|---|
| `${PR}-pr-body.json` | `/pulls/{n}` | The PR description |
| `${PR}-issue-comments.json` | `/issues/{n}/comments` | Top-level conversation comments (codecov, "thanks", etc.) |
| `${PR}-reviews.json` | `/pulls/{n}/reviews` | Review summary bodies (one per review submission) |
| `${PR}-inline-comments.json` | `/pulls/{n}/comments` | Per-line inline review comments |

### Phase 2 — Categorize sensitivity

Build a categorization table. Suggested buckets:

- **HIGH**: PR description with vuln scope or finding IDs; reviewer comments that describe an active bug in the proposed fix that an attacker could weaponize; lists of intentional partials (e.g., "rt9-101 still vulnerable, file-inclusion PR coming later").
- **MEDIUM**: per-file vuln-fix technique commentary (bot reviews summarizing what was hardened where).
- **NONE**: codecov coverage reports; review stubs ("See comments."); generic "thanks" / "LGTM".

**Standing rule for bot comments on PRs that will be relocated to a GHSA, with a codecov carve-out.** If `move-pr-to-private-ghsa` will follow this skill (or is the reason this skill is being run), every `*[bot]` / `Copilot`-authored comment surface is in the `MEDIUM` bucket *minimum* and the action is **always** `minimize OUTDATED`, regardless of how individually-harmless the body looks. This includes empty stubs ("Copilot reviewed N files…") and inline coding-style nits.

**The carve-out: codecov coverage reports are intentionally left visible.** Comments authored by `codecov-commenter` (or any future `codecov`-prefixed login such as `codecov[bot]`) drop back to the `NONE` bucket and the action is **`leave visible`** — do not minimize, do not delete. Coverage data is diagnostic, not security-context; the same numbers live at codecov.io anyway, so hiding the PR comment changes nothing useful while breaking the link teammates rely on for "did the move change coverage?" follow-ups. The Jira-side audit-trail comment for the codecov author should still capture the body for completeness (so the audit record is faithful to what was visible at relocation time), but the PR-side action is "no-op."

The reasoning for the minimize side of the rule belongs to the closing-side workflow — a closed-then-relocated PR with visible Copilot/Dependabot review activity invites onlookers to dig — and is encoded in `move-pr-to-private-ghsa` Phase 6d. Run the minimize+carve-out here so the Jira-side audit-trail comments faithfully reflect what is hidden on the public PR; or leave it to the GHSA skill's Phase 6d, which is idempotent and applies the same carve-out. Either way: do not present non-codecov bot comments to the user as "leave visible" candidates when a relocation is in scope, and conversely, do not present codecov coverage reports as "minimize" candidates.

Present this to the user as a table before acting. Recommended `AskQuestion` shape:

1. Multi-select: which sensitivity buckets to move/redact.
2. Single-select: PR-side action — `redact` (replace own content + minimize others') vs `delete_or_hide` (REST DELETE where possible) vs `nothing` (copy-only) vs `ask_per_item`.
3. Single-select: Jira format — one comment per item, consolidated, or grouped per author.

### Phase 3 — CRITICAL: Jira markdown caveat

Jira's markdown-to-ADF converter on `addCommentToJiraIssue` **silently drops blockquotes**. Lines beginning with `>` are dropped entirely (headers and rules survive, the quoted content is gone).

What survives the conversion:

- Headings (`#`, `##`, `###`)
- Paragraphs
- Lists (`-`, `*`, `1.`)
- Code blocks (\`\`\`)
- Tables (`|`)
- Bold / italic / inline code
- Horizontal rules (`---`)

What does NOT survive:

- Blockquotes (`>`) → silently dropped
- Some emoji / arrow characters render as their literal text representation (`→`, `↔` are fine; older emoji may not be).

**Always** verify a posted comment by re-fetching the issue with `getJiraIssue` (`fields: ["comment"]`) and inspecting the ADF body, especially when the source originally used markdown blockquotes (the natural shape for "here is the original content I'm moving").

When converting "quote of original content" sections, strip leading `> ` and represent the source as plain paragraphs / lists / code blocks, with an introductory heading like `## 1. Original PR description (posted YYYY-MM-DD by USER)`.

Mechanical strip:
```bash
sed 's/^> //; s/^>$//' source.md > destination.md
```

### Phase 4 — Permission matrix (memorize)

GitHub REST does NOT let *anyone* edit another user's comment body, even repo admins. Cross-author actions are limited to delete (REST) and minimize (GraphQL). The PR description, however, is editable by anyone with `push` access.

| Surface | Owner | Edit body (PATCH/PUT) | Delete | GraphQL `minimizeComment` |
|---|---|---|---|---|
| PR description | author | Anyone with push access | n/a | n/a |
| Issue comment | you | Yes | Yes | Yes |
| Issue comment | other | **No** | Admin only | Admin only |
| Review summary | you | Yes (PUT review) | Yes | Yes |
| Review summary | other | **No** | Admin only | Admin only |
| Inline review comment | you | Yes | Yes | Yes |
| Inline review comment | other | **No** | Admin only | Admin only |

Pick the action per item based on intent:

- Want the body actually gone from the API → DELETE (also drops the comment row from the thread; structure is lost).
- Want it hidden from default view but the audit trail preserved → minimize as `OUTDATED` (visible behind a "Show hidden item" disclosure; body still retrievable via REST).
- Own content + want a visible pointer → PATCH with pointer text.

### Phase 5 — Post to Jira first (safer; reversible)

Order matters: post to Jira before redacting on GitHub. If Jira fails or the format is wrong, you haven't lost the PR-side source yet.

For each author group (per the chosen format), build a markdown file `${PR}-jira-{author}-comment.md` with:

- H1 header: `# Moved from public PR #{n} — author: {login}`
- A one-paragraph provenance note explaining what's moved and why.
- `---` divider.
- For each item, a `## N. {Original location} (posted {timestamp})` header followed by the body, **without blockquote markers**, preserving lists / code / tables.

Post via `addCommentToJiraIssue` with `contentFormat: "markdown"` and `responseContentFormat: "markdown"` so you can verify what landed in one round-trip.

After each post, scan the `body` field in the response and confirm key phrases survived (search for specific tokens like finding IDs, code-block contents, etc.).

If anything is missing → it was probably a blockquote you forgot to strip. Re-post (you can't edit Jira comments via MCP; budget for a "supersedes comment XYZ" stub).

### Phase 6 — Redact on GitHub

In this order:

1. **Edit the PR body** with a pointer to the Jira ticket:
   ```bash
   gh pr edit $PR --repo $REPO --body-file .cursor/working/${PR}-pointer-body.md
   ```

2. **Edit your own** issue comments / review summary bodies / inline review comments with the pointer template:
   ```bash
   POINTER='_Original content moved to internal ticket [{KEY}]({URL}) for the security audit trail._'
   gh api -X PATCH repos/$REPO/issues/comments/{id} -f body="$POINTER"
   gh api -X PUT   repos/$REPO/pulls/$PR/reviews/{id} -f body="$POINTER"
   gh api -X PATCH repos/$REPO/pulls/comments/{id}   -f body="$POINTER"
   ```

3. **Get GraphQL node IDs** for everything you can't edit but want to minimize:
   ```bash
   gh api repos/$REPO/issues/comments/{id} --jq '.node_id'
   gh api repos/$REPO/pulls/$PR/reviews/{id} --jq '.node_id'
   gh api repos/$REPO/pulls/comments/{id} --jq '.node_id'
   ```

4. **Minimize via GraphQL**, classifier `OUTDATED` (cleanest semantically for "this conversation moved elsewhere"):
   ```bash
   gh api graphql -f query='mutation($id: ID!){
     minimizeComment(input: {subjectId: $id, classifier: OUTDATED}) {
       minimizedComment { isMinimized minimizedReason }
     }
   }' -F id="{node_id}"
   ```
   Valid classifiers: `OUTDATED`, `RESOLVED`, `OFF_TOPIC`, `DUPLICATE`, `SPAM`, `ABUSE`. Pick `OUTDATED` unless context suggests otherwise.

5. **Post a single anchor comment** explaining the move so anyone landing on the PR understands the visible blanks. Template:
   ```markdown
   **Conversation moved to internal tracking.**

   For audit-trail reasons, the descriptive context on this PR — {short list of what} — has been moved to [{KEY}]({URL}).

   What was redacted:
   - PR description → pointer
   - {author}'s {what} → pointer / minimized

   What is NOT affected: the diff, commits, "Files changed" tab, CI/codecov reports. Review continues on this PR; post new review comments here as usual.
   ```

### Phase 7 — Verify

Re-fetch all four surfaces and confirm expected state. Pattern:

```bash
gh api repos/$REPO/pulls/$PR --jq '.body' | head -5
gh api repos/$REPO/issues/$PR/comments --jq \
  '[.[] | {id, user: .user.login, len: (.body|length), preview: (.body[:80])}]'
gh api repos/$REPO/pulls/$PR/reviews --jq \
  '[.[] | {id, user: .user.login, len: (.body|length), preview: (.body[:80])}]'
gh api repos/$REPO/pulls/$PR/comments --jq \
  '[.[] | {id, user: .user.login, path, len: (.body|length), preview: (.body[:80])}]'
```

Then re-fetch Jira:

```
getJiraIssue cloudId={CID} issueIdOrKey={KEY} fields=["comment"]
```

Confirm each moved item is either (a) replaced with pointer text, (b) returned in the Jira comment body fully, or (c) flagged with the GitHub `minimized` state. Optionally browse the PR in a real browser — minimized comments collapse under a "Show hidden item" disclosure on the conversation tab.

---

## Pointer / anchor text templates

### `${PR}-pointer-body.md` — PR description replacement

If the PR will stay open after this skill runs (no GHSA relocation in scope), use the explainer form:

```markdown
Internal tracking: {JIRA_URL}

The original PR description was moved to the internal Jira ticket for the security audit trail. The code changes remain visible in the diff and the "Files changed" tab — only the descriptive context that enumerated audit finding IDs and architectural partials has been redacted here.
```

If the PR will be closed in this same workflow (the typical case when paired with `move-pr-to-private-ghsa`), the body should be **only** the Jira URL — no surrounding paragraph. Skip writing the explainer form above and write the URL alone:

```
{JIRA_URL}
```

That matches the `move-pr-to-private-ghsa` Phase 6b standing rule. Anything more invites a closed-PR onlooker to read further. The `move-pr-to-private-ghsa` skill's Phase 6b will overwrite the body with the bare URL anyway, so writing the explainer form here when a relocation is in scope just creates two consecutive body edits in the PR's history for no benefit.

### `${PR}-pointer-comment.txt` — own-comment replacement (one-liner)

```markdown
_Original content moved to internal ticket [{KEY}]({JIRA_URL}) for the security audit trail._
```

### `${PR}-anchor-comment.md` — new pinned anchor on the PR

```markdown
**Conversation moved to internal tracking.**

For audit-trail reasons, the descriptive context on this PR — {short list of what} — has been moved to [{KEY}]({JIRA_URL}).

What was redacted:
- PR description → pointer
- {author X}'s {role of content} → pointer
- {author Y}'s {role of content} → minimized as Outdated

What is NOT affected: the diff, commits, "Files changed" tab, CI/codecov reports. Review continues on this PR; post new review comments here as usual.
```

---

## Caveats to surface to the user every time

1. **A public PR is public.** Whatever was visible before this action was indexed by GitHub search, forks, package registries (Packagist, WPScan), security crawlers, and at least one Internet Archive crawl. Redacting the current view does not retract the historical disclosure. For genuine vuln suppression, use a GHSA + private temp fork.

2. **Minimize ≠ delete.** Bodies of minimized comments are still returned by `GET .../comments/{id}`. They are only hidden from the default web UI. If the user wants the content actually purged from the API, escalate to DELETE.

3. **Edit permissions are author-scoped, not role-scoped.** Repo admins cannot edit other users' comment bodies — only delete or minimize. Plan around this; do not promise the user "we'll just rewrite the Copilot bot's review".

4. **Jira markdown converter eats blockquotes.** Always verify Jira comments by re-fetching the issue's `comment` field with `fields: ["comment"]`. Build draft markdown without `> ` markers from the start; use headings + paragraphs + code blocks instead.

5. **Edits are visible.** GitHub shows an "edited" indicator on every changed comment, with full edit history accessible to anyone with read access. The original content is in the edit history. This is fine for audit-trail purposes but is not stealth-redaction.

6. **Commit messages on the branch remain public.** This skill does not rewrite git history. If commit messages contain sensitive content, that's a separate, more invasive operation (force-push with rewritten messages, plus a release note acknowledging the rewrite).

7. **The branch name itself may leak.** Branches like `ENG7-2797-access-control-vul` advertise their purpose. Renaming the branch is possible but requires updating the PR base and is best-effort.

---

## Quick reference: per-item action chooser

```
For each item in inventory:
  if owner == me:
    if intent == redact-but-keep-visible:    -> REST PATCH with pointer
    if intent == remove:                     -> REST DELETE
  else:
    if intent == redact-but-keep-visible:    -> GraphQL minimizeComment OUTDATED
    if intent == remove:                     -> REST DELETE (admin)
    edit body is NOT an option, period.

Always post to Jira FIRST. PR-side redaction is harder to undo than Jira-side replanting.
```

---

## Reference run

- Date: 2026-05-14
- PR: `BoldGrid/w3-total-cache#1313`
- Jira: `ENG7-2908` (parent epic `ENG7-2796`)
- Items moved: 1 PR body + 5 cssjoe-owned (1 issue comment + 1 review summary + 3 inline) + 1 jacobd91 issue comment + 17 Copilot (1 review summary + 16 inline) = 24 surfaces.
- Jira comments created: 3 (one per author) + 1 broken stub from a blockquote-conversion misfire = 4. The stub (`#420586`) is unfixable from MCP today; the corrected jacobd91 comment (`#420587`) explicitly supersedes it in its first line.
