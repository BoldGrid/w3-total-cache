# Update changelog

Read `.claude/skills/update-changelog/SKILL.md` and follow it.

When adding a new version:
1. Draft bullets from `git log <prev-tag>..HEAD --no-merges`.
2. Update `readme.txt` (`== Changelog ==` and optional `== Upgrade Notice ==`).
3. Add the same changelog bullets to `changelog.txt` (newest version first, after the title block).
4. Run `bin/update-changelog.sh check <version>` and fix any mismatch.

Use `bin/update-changelog.sh add <version> [bullets-file]` when inserting a brand-new version block into both files from a bullets file.
