---
name: repost-pr-reviews-to-tpf
description: Repost GitHub review content (Copilot bot reviews, human-reviewer threads, etc.) from a closed public PR onto its GHSA temporary-private-fork (TPF) PR, anchored to the source's `original_commit_id` at `original_line` so each comment renders as a historical outdated comment matching the original review UX exactly. Use after `move-pr-to-private-ghsa` to restore reviewer-thread continuity on the TPF, since reviews/inline comments do not migrate with the commits. Encodes the pull-via-`gh api` → group-by-review → anchor-to-original-commit → attribute-via-preface → post-as-COMMENT-event sequence plus the gotchas (commit must still be in the TPF chain, line must be in that commit's diff, your account is the apparent author so attribution lives in the body preface, never re-use APPROVE / REQUEST_CHANGES events from the source review). Requires repo-admin (or `repo` scope) on the TPF and `repo` scope on the public source repo.
---

# Repost PR reviews onto the TPF

Restore reviewer-thread continuity on a GHSA temporary private fork (TPF) PR by replaying the closed public PR's review history. Each source review becomes one repost-review on the TPF, with its inline comments anchored to the original commits at the original line numbers — GitHub then renders them as "outdated on commit X" exactly the way they appeared on the original public PR.

This skill is the natural follow-up to `move-pr-to-private-ghsa`. That skill moves the *code* into the TPF; this one restores the *review context* that was deliberately left behind on the public PR.

Tested against `BoldGrid/w3-total-cache#1313` → `BoldGrid/w3-total-cache-ghsa-rgpr-5m2g-gvh2#1` on 2026-05-20: 2 Copilot reviews, 16 inline comments, anchored to `a2b94e5d` (round 1) and `498aa3e8` (round 2). See "Caveats to surface every time" for the line-drift / event-state / attribution issues this skill exists to avoid re-encountering.

---

## When to use this

- A public PR has been moved to a TPF via `move-pr-to-private-ghsa` (the source PR is closed; its branch may be deleted).
- Review threads on the public PR — Copilot bot reviews, human reviewer comments minimized/resolved before close, anchor-context inline findings — are valuable for ongoing TPF review but did **not** migrate with the commits.
- The original commits the reviewers anchored to are still reachable from the TPF PR's chain. True by construction for `move-pr-to-private-ghsa` (the TPF starts as a fork of the public repo *with all branches*), so all `original_commit_id` SHAs should still be in the TPF chain.
- The audit-trail home for the descriptive content already lives in Jira (via `pr-content-to-jira`), but you want *operational* continuity in the TPF reviewer UI on top of the audit-trail copy.

Do **not** use this to manufacture review consensus — the reposts will be authored by *you*, not the original reviewer. Attribution lives in an inline preface on every body so the audit trail is grep-able, but anyone scanning the reviewer column in the timeline sees your login. The flip side: never use `APPROVE` or `REQUEST_CHANGES` events on the repost; that would inject your account into the TPF's review state on someone else's behalf. Always `COMMENT`.

---

## Prerequisites

- `gh` CLI logged in. Two distinct permission surfaces:
  - `repo` scope on the public source repo (to read closed PR reviews + comments — public read is enough, but ratelimit-friendliness wants auth).
  - `repo` (or `pull-requests:write` via GitHub App) on the TPF repo. Repo-admin on the public repo usually carries through to the TPF; verify:
  ```bash
  gh api repos/{TPF-OWNER}/{TPF-REPO} --jq '{full_name, permissions}'
  ```
- Python 3 (the workable pattern is a small Python reposter that shells out to `gh api`).
- `jq` available locally.
- A writable `.cursor/working/` directory (gitignored).
- The closed public PR's `{owner}/{repo}#{n}`, the TPF PR's `{owner}/{repo}#{n}`, and the parent Jira key (used in attribution prefaces so reposts are bidirectionally linkable to the audit trail).

---

## Filename convention for scratch files

Per AGENTS.md "Working Files", prefix every scratch file with the TPF PR number (the one you're posting *into*). Once the Jira key is known, append it. Optionally include the source PR number for clarity:

- `{TPF-PR}-{KEY}-source-pr-{SRC-PR}-reviews.json` — source review summaries (`/reviews`, filtered by author regex)
- `{TPF-PR}-{KEY}-source-pr-{SRC-PR}-comments.json` — source inline comments (`/comments`, filtered by author regex)
- `{TPF-PR}-{KEY}-repost-reviews.py` — the reposter script
- `{TPF-PR}-{KEY}-repost-{round}-payload.json` — POST body for `/pulls/{n}/reviews` (one per source review)
- `{TPF-PR}-{KEY}-repost-{round}-response.json` — API response for verify

Never write to `/tmp/` or anywhere outside the project tree.

---

## Workflow

### Phase 0 — Confirm scope and inputs

Capture from the user (or ask):

- Source public PR URL → derive `{SRC-OWNER}`, `{SRC-REPO}`, `{SRC-PR}`.
- TPF PR URL → derive `{TPF-OWNER}`, `{TPF-REPO}`, `{TPF-PR}`.
- Parent Jira ticket key (e.g., `ENG7-2908`) — used in attribution prefaces.
- Author filter regex for the source content to repost — e.g., `[Cc]opilot` for Copilot bot reviews, a specific human login, or `.` to include all reviewers. Be explicit; never just default to "all" without confirming.
- Whether the source PR's branch has been deleted (informational — doesn't affect the API; reviews/comments live on the PR, not the branch).

Verify GitHub permissions on the TPF:

```bash
gh api repos/{TPF-OWNER}/{TPF-REPO} --jq '{full_name, permissions, default_branch}'
# expect permissions.push: true and permissions.admin: true (or maintain: true)
```

### Phase 1 — Pull source reviews + comments via gh api

Two calls, both filtered by the same author regex. The `/reviews` endpoint gives you the review-level data (state, body, commit_id, submitted_at); `/comments` gives you the per-inline-comment data (path, original_line, original_commit_id, pull_request_review_id, body, side):

```bash
SRC_REPO=BoldGrid/w3-total-cache
SRC_PR=1313
FILTER='[Cc]opilot'   # or specific login, etc.

gh api "repos/${SRC_REPO}/pulls/${SRC_PR}/reviews" --paginate \
  > .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-reviews-raw.json

jq "[.[] | select(.user.login | test(\"${FILTER}\")) | {id, state, commit_id, submitted_at, body, user_login: .user.login}]" \
  .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-reviews-raw.json \
  > .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-reviews.json

gh api "repos/${SRC_REPO}/pulls/${SRC_PR}/comments" --paginate \
  > .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-comments-raw.json

jq "[.[] | select(.user.login | test(\"${FILTER}\")) | {id, path, line, original_line, original_commit_id, side, pull_request_review_id, body, user_login: .user.login}]" \
  .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-comments-raw.json \
  > .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-comments.json
```

Quick sanity check — comment count grouped by review:

```bash
jq '[.[] | {id, review_id: .pull_request_review_id}] | group_by(.review_id) | map({review_id: .[0].review_id, count: length})' \
  .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-comments.json
```

The count per review should match the body of the source review (Copilot reviews usually include a "generated N comments" line you can cross-reference).

### Phase 2 — Verify every original_commit_id is reachable from the TPF chain

Inline comments anchored to a commit that's no longer in the TPF PR's chain will be rejected by the API with 422. Verify up front:

```bash
gh api "repos/${TPF_REPO}/pulls/${TPF_PR}/commits" --paginate --jq '[.[] | .sha]' \
  > .cursor/working/${TPF_PR}-${KEY}-tpf-chain.json

jq -r '[.[] | .original_commit_id] | unique | .[]' \
  .cursor/working/${TPF_PR}-${KEY}-source-pr-${SRC_PR}-comments.json \
| while read sha; do
    if jq -e --arg sha "$sha" 'index($sha)' \
         .cursor/working/${TPF_PR}-${KEY}-tpf-chain.json > /dev/null; then
      echo "OK   $sha"
    else
      echo "MISS $sha   (will need fallback to PR-level comment)"
    fi
  done
```

Any `MISS` rows need to be handled separately — see "Caveats" #6. Most of the time everything resolves to `OK` because `move-pr-to-private-ghsa` preserves the full commit chain.

### Phase 3 — Build the reposter script

One payload per source review. For each comment, anchor to `original_commit_id` + `original_line`. Attribution wrapper goes on both the review body and each inline comment body, so attribution survives every UI surface (timeline, file-tree review, individual comment permalink).

The template script (write to `.cursor/working/${TPF_PR}-${KEY}-repost-reviews.py`):

```python
#!/usr/bin/env python3
"""
Repost reviews from {SRC_REPO}#{SRC_PR} onto TPF PR {TPF_REPO}#{TPF_PR}.

Each review is anchored to its source's original_commit_id, with each inline
comment placed on its original_line. GitHub then renders them as historical
outdated comments anchored on those commits, matching the original review UX.

Audit-trail copy of the same content lives on Jira {KEY} (added via
pr-content-to-jira) so this is operational continuity, not the audit trail.
"""

import json
import subprocess
import sys
from pathlib import Path

WORKING        = Path("/home/.../.cursor/working")
TPF_REPO       = "{TPF-OWNER}/{TPF-REPO}"
TPF_PR         = {TPF-PR}
SOURCE_PR_URL  = "https://github.com/{SRC-OWNER}/{SRC-REPO}/pull/{SRC-PR}"
JIRA_KEY       = "{KEY}"
PREFIX         = f"{TPF_PR}-{JIRA_KEY}"   # filename prefix for scratch files


def load_reviews():
    with open(WORKING / f"{PREFIX}-source-pr-{{SRC-PR}}-reviews.json") as fh:
        return json.load(fh)


def load_comments():
    with open(WORKING / f"{PREFIX}-source-pr-{{SRC-PR}}-comments.json") as fh:
        return json.load(fh)


def build_payload(review, comments):
    login = review["user_login"]
    header = (
        f"_Reposted from [{login}] review on {SOURCE_PR_URL} "
        f"(review id {review['id']}, submitted {review['submitted_at']}). "
        f"Anchored to commit `{review['commit_id'][:10]}` so file/line positions match the original review. "
        f"Audit-trail copy also lives on Jira {JIRA_KEY}._\n\n"
        f"---\n\n"
    )
    body = header + (review["body"] or "")

    comments_payload = []
    for c in comments:
        if c["pull_request_review_id"] != review["id"]:
            continue
        inline_body = (
            f"_From {c['user_login']} inline comment on PR #{{SRC-PR}} "
            f"(comment id {c['id']}, originally anchored at L{c['original_line']} of `{c['path']}` "
            f"at commit `{c['original_commit_id'][:10]}`). Verbatim text below._\n\n"
            f"{c['body']}"
        )
        comments_payload.append({
            "path": c["path"],
            "line": c["original_line"],
            "side": c.get("side") or "RIGHT",
            "body": inline_body,
        })

    return {
        "commit_id": review["commit_id"],   # the source's original commit
        "event":     "COMMENT",             # ALWAYS COMMENT — never re-use APPROVE / REQUEST_CHANGES
        "body":      body,
        "comments":  comments_payload,
    }


def post(payload, round_label):
    payload_path = WORKING / f"{PREFIX}-repost-{round_label}-payload.json"
    payload_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2))

    print(f"\n=== Posting {round_label} ===")
    print(f"  commit_id: {payload['commit_id'][:10]}")
    print(f"  comments:  {len(payload['comments'])}")

    result = subprocess.run(
        ["gh", "api", "--method", "POST",
         f"/repos/{TPF_REPO}/pulls/{TPF_PR}/reviews",
         "--input", str(payload_path)],
        capture_output=True, text=True,
    )
    if result.returncode != 0:
        print("STDERR:", result.stderr)
        sys.exit(result.returncode)

    response = json.loads(result.stdout) if result.stdout.strip() else {}
    (WORKING / f"{PREFIX}-repost-{round_label}-response.json").write_text(
        json.dumps(response, ensure_ascii=False, indent=2)
    )
    print(f"  Review ID: {response.get('id')}")
    print(f"  URL:       {response.get('html_url')}")


def main():
    reviews = sorted(load_reviews(), key=lambda r: r["submitted_at"])
    comments = load_comments()
    for i, r in enumerate(reviews, start=1):
        post(build_payload(r, comments), f"round{i}")


if __name__ == "__main__":
    main()
```

Fill in the curly-brace tokens for the specific run. Keep `event: "COMMENT"` regardless of the source review's `state` (see "Caveats" #2).

### Phase 4 — Post the reposts

```bash
python3 .cursor/working/${TPF_PR}-${KEY}-repost-reviews.py
```

Expect one POST per source review, each returning a review id + html_url. Save responses to `${TPF_PR}-${KEY}-repost-{round}-response.json`.

If a POST fails with `422`:

- `Pull request review thread line must be part of a diff` → the `original_line` isn't in the `original_commit_id`'s diff against base. Most often a data integrity issue from the source PR (force-push or rebase) rather than the script. Pull the comment out of that review's payload and fall back to a PR-level conversation comment for it (see "Caveats" #6).
- `Pull request review thread commit_oid is not part of the pull request` → the `original_commit_id` isn't in the TPF chain. Same fallback.
- `Pull request comment body is too long` → 65536-char ceiling per body; trim the verbatim content or split into multiple comments.

### Phase 5 — Verify

Read back the comments on the TPF PR and assert each reposted comment's `original_line` matches the source. Same script pattern works for both — query, then sort by `id`, then diff:

```bash
gh api "repos/${TPF_REPO}/pulls/${TPF_PR}/comments" --paginate \
  --jq "[.[] | select(.body | test(\"Reposted from\\\\b|From ${FILTER} inline comment\")) | {path, original_line, original_commit_id: (.original_commit_id // \"\")[0:10], review_id: .pull_request_review_id}]" \
  > .cursor/working/${TPF_PR}-${KEY}-verify-reposted.json

jq '. | length' .cursor/working/${TPF_PR}-${KEY}-verify-reposted.json
# expect: same as total inline-comment count across all source reviews
```

Optional sanity check: open `https://github.com/${TPF_REPO}/pull/${TPF_PR}` in a browser, click "Conversation", and confirm the reposted reviews appear in chronological order with their inline comments rendered as outdated against the historical commits.

### Phase 6 — Optional: note the repost in the Jira back-link

The Jira back-link comment from `move-pr-to-private-ghsa` Phase 5b is the place to record that the TPF now has the original review threads as well. Add a short follow-up comment (Jira MCP cannot edit existing comments) referencing the reposted review URLs so future ticket-readers know the operational view of the reviews lives on the TPF, not just the audit-trail view that's in Jira:

```markdown
## Follow-up to GHSA back-link: source PR reviews reposted on the TPF

For ongoing-review continuity, the original [{login}] reviews from {SOURCE_PR_URL}
have been reposted on the TPF, anchored to the historical commits so file/line
positions match the source review exactly:

- Round 1 ({source review id, source commit_id[:10]}): {tpf-review-1-url}
- Round 2 ({source review id, source commit_id[:10]}): {tpf-review-2-url}

Each comment is attributed to its original author via an inline preface; the
reposts are authored by my account on the TPF.

Audit-trail copy of the same content remains as comment {original-jira-comment-id}
on this ticket — Jira is the durable record; the TPF reposts are the operational
view for active reviewers.
```

Skip this step if there's no Jira ticket in scope.

---

## Caveats to surface to the user every time

1. **Anchor to the source's `original_commit_id`, not the current head.** The whole point of the anchored-to-historical-commit approach is to avoid line drift. If you anchor to the current head, GitHub will try to map the line through subsequent diffs, fail on fixed-and-removed code, and either reject the comment or display it at a misleading position. Always use `original_commit_id` + `original_line` from the source data.

2. **Always use `event: "COMMENT"` on the repost, regardless of the source review's `state`.** Re-using `APPROVE` or `REQUEST_CHANGES` would put *your* account in those review states on the TPF on someone else's behalf — wrong attribution and wrong semantics. Even if Copilot's source review was a `COMMENTED` review (the typical case), keep the explicit `COMMENT` event hardcoded so a future copy-paste run against a human-reviewer source doesn't accidentally APPROVE the TPF.

3. **Attribution lives in the body preface, not the author column.** The repost is authored by your GitHub login. The "_Reposted from [{login}] review on {SOURCE_PR_URL}..._" preface on the review body and the "_From {login} inline comment on PR #{N} (comment id ..., originally anchored at L###...)..._" preface on each inline comment are what preserve the original authorship. Make these prefaces grep-able and unambiguous — they're the only attribution surface a reader has.

4. **Reviews/inline comments are PR-scoped, not branch-scoped.** Even after `move-pr-to-private-ghsa` Phase 6 closed the source PR and deleted its branch, `/repos/{owner}/{repo}/pulls/{n}/reviews` and `/comments` continue to return historical content indefinitely. Pulling from a closed-deleted PR works exactly the same as from an open one.

5. **`side` may be `null` in the source data; default to `"RIGHT"`.** GitHub's `/comments` response sometimes omits `side` for older comments. The vast majority of review comments anchor to additions (RIGHT). If you have specific knowledge that a source comment was on a deletion, set `LEFT`; otherwise default to `RIGHT`.

6. **Unreachable `original_commit_id` → fall back to PR-level conversation comment.** If a source PR was force-pushed or had commits orphaned before the move, an `original_commit_id` may not be in the TPF chain. The Phase 2 reachability check catches this. For each affected comment, drop it from the review's `comments[]` payload and post a separate `gh pr comment ${TPF_PR}` with body text like "_From {login} inline comment on PR #{N} (comment id ..., originally anchored at `{path}` L{N} on a commit no longer in the chain). Verbatim text below._\n\n{body}". The line anchor is lost; the content is preserved.

7. **Body length ceiling: 65536 chars per comment.** Most review/inline bodies are well under, but Copilot's "Pull request overview" body with the file-table can approach the ceiling on large PRs. If exceeded, split the body into a primary repost-review with the overview + the first half of comments, and a follow-up PR-level conversation comment with the rest. Don't truncate the verbatim text — that breaks audit traceability.

8. **Re-running the script is non-idempotent.** Posting twice creates two reviews and duplicates every inline comment. There's no easy "delete all reposted reviews" undo — `gh api -X DELETE /repos/.../pulls/.../comments/{id}` works per-comment, but the review wrapper itself stays. Verify the source JSON before running and dry-run by inspecting the payload files first.

9. **GitHub Copilot review content travels in two places: `/reviews` (review wrapper + body) and `/comments` (inline anchors). Both must be pulled and rejoined via `pull_request_review_id`.** Don't try to reconstruct from `/comments` alone — you'll miss the review-level summary body (which for Copilot includes the "Pull request overview" + file-table that contextualizes the inline findings).

10. **The Jira audit-trail copy is the source of truth; the TPF repost is operational continuity.** Don't skip preserving content in Jira just because you've also reposted on the TPF. Jira is durable and bidirectionally linkable across the workflow (ticket → public PR → GHSA → TPF). The TPF reposts live or die with the TPF, which itself goes away once the advisory is merged or withdrawn.

11. **Reviewers see the reposts as "outdated" by default.** Because every repost is anchored to a historical commit, GitHub renders them with the "Outdated" badge and the "Show outdated" toggle collapsed by default. This is the correct UX — matches how Copilot's original reviews appeared on the closed public PR once subsequent commits landed. Don't try to "fix" this by re-anchoring to head; you'd lose line accuracy.

12. **This skill is downstream of `move-pr-to-private-ghsa` and (usually) `pr-content-to-jira`.** Run them in that order. Trying to repost while the public PR is still open is technically possible but loses the framing of "continuity restoration" — at that point the comments are still readable on the open public PR and the duplicate on the TPF is just noise.

---

## Quick reference: end-to-end sequence

```
0. Inputs:
   - Source PR: {SRC-OWNER}/{SRC-REPO}#{SRC-PR}   (closed; branch may be deleted)
   - TPF    PR: {TPF-OWNER}/{TPF-REPO}#{TPF-PR}
   - Jira ticket key (for attribution prefaces + back-link follow-up)
   - Author filter regex (e.g. "[Cc]opilot" or specific reviewer login)

1. Pull source reviews + inline comments via `gh api`, filtered by author regex.
   `gh api repos/{SRC}/pulls/{N}/reviews  --paginate | jq filter > reviews.json`
   `gh api repos/{SRC}/pulls/{N}/comments --paginate | jq filter > comments.json`
   Sanity-check inline counts per review match the source review body.

2. Verify each unique `original_commit_id` is still in the TPF chain:
   `gh api repos/{TPF}/pulls/{N}/commits --paginate --jq '.[].sha'`
   Flag any MISS — those become PR-level conversation comments in Phase 4.

3. Build one payload per source review:
   { commit_id: source.original_commit_id,
     event:     "COMMENT",                       # never APPROVE / REQUEST_CHANGES
     body:      attribution-preface + source.body,
     comments:  [{path, line: original_line, side: side or "RIGHT",
                  body: attribution-preface + source.body}, ...] }

4. Post each payload:
   `gh api -X POST /repos/{TPF}/pulls/{TPF-N}/reviews --input payload.json`
   For any MISS commits from Phase 2: `gh pr comment {TPF-N}` with body referencing
   the lost line anchor + verbatim text.

5. Verify:
   `gh api repos/{TPF}/pulls/{TPF-N}/comments --paginate` and confirm:
   - reposted-comment count matches the sum across source reviews,
   - each reposted comment's `original_line` == source's `original_line`,
   - each reposted comment's `original_commit_id` is in the TPF chain.

6. Optional: append a follow-up Jira comment on the parent ticket recording
   the reposted-review URLs, so the Jira back-link from move-pr-to-private-ghsa
   Phase 5b also knows where the operational copies live.

Always run AFTER `move-pr-to-private-ghsa` (so the TPF exists and the source PR
is closed). Pair with `pr-content-to-jira` so the audit-trail copy in Jira and
the operational copy on the TPF are both in place.
```
