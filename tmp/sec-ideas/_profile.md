# Security remediation profile — w3-total-cache (RT19 / ENG3-584)

Commit-safe engagement profile. Absolute clone paths stay local (hub `current-target.md` + fill-ins below). Remotes and repo slugs match `.sec-project.yaml`.

Do not commit post-triage rewrites of this file that inject absolute `plugin_root` / `findings_root` / `tpf_root` host paths — keep placeholders here; machine-local roots belong in hub `current-target.md` only.

| Field | Value |
|-------|-------|
| plugin_root | Set locally via hub `.claude/current-target.md` (`plugin_root`) |
| plugin_slug | w3-total-cache |
| public_repo | BoldGrid/w3-total-cache |
| private_repo | BoldGrid/w3-total-cache-secops |
| tpf_repo | BoldGrid/w3-total-cache-secops |
| source_root | w3-total-cache |
| text_domain | w3-total-cache |
| bead_prefix | rt / red-team-template (RT19 archive) |
| findings_root | `.cursor/working/rt19-all-findings/findings/confirmed` (relative to plugin root; see `.sec-project.yaml`) |
| hardening_root | `.cursor/working/rt19-all-findings/findings/code-review` |
| unreplicated_root | `.cursor/working/rt19-all-findings/findings/unable-to-replicate` |
| ideas_dir | tmp/sec-ideas |
| progress_file | `.cursor/working/sec-progress.json` |
| scratch_dir | `.cursor/working` |
| branch_prefix | ENG3-584 |
| jira_key | ENG3-584 |
| base_branch | master |
| default_branch | master |
| public_remote | origin |
| tpf_remote | secops |
| private_remote | secops |
| work_surface | persistent-secops |
| tpf_root | Set locally to the working tree that pushes to `BoldGrid/w3-total-cache-secops` |
| ghsa_id | n/a for greenfield group PRs (optional at publish) |
| autoloader | plugin bootstrap / manual requires |
| proof_env | n/a (proofs under findings scratch) |

## Shared tooling (do not duplicate into this repo)

Canonical agents, commands, and skills live in `inmotionhosting/internal-rt-tools` (hub `.claude/`). Prefer hub copies of `sec-*`, `pr-content-to-jira`, `move-pr-to-private-ghsa`, and `repost-pr-reviews-to-tpf`. Keep only W3TC-specific local skills until replaced (`analyze-w3tcqa-environment`, `update-changelog`).

## Smoke profile IDs

See `.sec-project.yaml` → `smoke.profiles`: `dev_single`, `dev_multisite`, `external_single` (env names + `wp_qa_site_id` only). Path/role manifest: hub `.cursor/projects/w3-total-cache/smoke-paths.yaml`.
