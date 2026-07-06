#!/usr/bin/env bash
# Add or verify changelog entries in readme.txt and changelog.txt.
#
# Usage:
#   bin/update-changelog.sh check <version>
#   bin/update-changelog.sh add <version> [bullets-file]
#   bin/update-changelog.sh add-upgrade-notice <version> <notice-text>
#
# Bullets file (or stdin for add): one line per bullet, each starting with "* ".
# Blank lines and lines starting with "#" are ignored.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
README="${ROOT}/readme.txt"
CHANGELOG="${ROOT}/changelog.txt"

usage() {
	cat <<'EOF'
Usage:
  bin/update-changelog.sh check <version>
      Verify changelog bullets for <version> match in readme.txt and changelog.txt.

  bin/update-changelog.sh add <version> [bullets-file]
      Insert a new version block into both files (fails if version already exists).
      Reads bullets from bullets-file or stdin.

  bin/update-changelog.sh add-upgrade-notice <version> <notice-text>
      Insert an Upgrade Notice block for <version> in readme.txt only.
EOF
}

die() {
	echo "update-changelog.sh: $*" >&2
	exit 1
}

validate_version() {
	local version="$1"
	[[ "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.]+)?$ ]] || die "invalid version: ${version}"
}

extract_readme_changelog() {
	local version="$1"
	awk -v ver="${version}" '
		$0 == "== Changelog ==" { in_changelog = 1; next }
		in_changelog && $0 ~ /^== / { exit }
		in_changelog && $0 == "= " ver " =" { in_version = 1; next }
		in_changelog && in_version && $0 ~ /^= [0-9]/ { exit }
		in_changelog && in_version && $0 ~ /^\* / { print }
	' "${README}"
}

extract_changelog_txt() {
	local version="$1"
	awk -v ver="${version}" '
		$0 == "= " ver " =" { in_version = 1; next }
		in_version && $0 ~ /^= [0-9]/ { exit }
		in_version && $0 ~ /^\* / { print }
	' "${CHANGELOG}"
}

version_exists_in_readme() {
	local version="$1"
	grep -qFx "= ${version} =" "${README}"
}

version_exists_in_changelog() {
	local version="$1"
	grep -qFx "= ${version} =" "${CHANGELOG}"
}

upgrade_notice_exists() {
	local version="$1"
	awk -v ver="${version}" '
		$0 == "== Upgrade Notice ==" { in_section = 1; next }
		in_section && $0 ~ /^== / { exit }
		in_section && $0 == "= " ver " =" { found = 1 }
		END { exit !found }
	' "${README}"
}

read_bullets() {
	local source="${1:--}"
	if [[ "${source}" == "-" ]]; then
		grep -E '^\* ' || true
	else
		[[ -f "${source}" ]] || die "bullets file not found: ${source}"
		grep -E '^\* ' "${source}" || true
	fi
}

insert_after_changelog_header() {
	local file="$1"
	local version="$2"
	local bullets_file="$3"
	local tmp
	tmp="$(mktemp)"
	awk -v ver="${version}" -v bullets="${bullets_file}" '
		{ print }
		$0 == "== Changelog ==" {
			print ""
			print "= " ver " ="
			while ((getline line < bullets) > 0) {
				print line
			}
			close(bullets)
			print ""
		}
	' "${file}" > "${tmp}"
	mv "${tmp}" "${file}"
}

insert_after_changelog_title() {
	local version="$1"
	local bullets_file="$2"
	local tmp
	tmp="$(mktemp)"
	awk -v ver="${version}" -v bullets="${bullets_file}" '
		NR == 2 && $0 == "" {
			print ""
			print "= " ver " ="
			while ((getline line < bullets) > 0) {
				print line
			}
			close(bullets)
			print ""
			next
		}
		{ print }
	' "${CHANGELOG}" > "${tmp}"
	mv "${tmp}" "${CHANGELOG}"
}

insert_upgrade_notice() {
	local version="$1"
	local notice="$2"
	local tmp
	tmp="$(mktemp)"
	awk -v ver="${version}" -v notice="${notice}" '
		{ print }
		$0 == "== Upgrade Notice ==" {
			print ""
			print "= " ver " ="
			print notice
			print ""
		}
	' "${README}" > "${tmp}"
	mv "${tmp}" "${README}"
}

cmd_check() {
	local version="${1:-}"
	[[ -n "${version}" ]] || die "check requires <version>"
	validate_version "${version}"

	version_exists_in_readme "${version}" || die "version ${version} missing from readme.txt changelog"
	version_exists_in_changelog "${version}" || die "version ${version} missing from changelog.txt"

	local readme_bullets changelog_bullets
	readme_bullets="$(extract_readme_changelog "${version}")"
	changelog_bullets="$(extract_changelog_txt "${version}")"

	[[ -n "${readme_bullets}" ]] || die "no changelog bullets found for ${version} in readme.txt"
	[[ -n "${changelog_bullets}" ]] || die "no changelog bullets found for ${version} in changelog.txt"

	if [[ "${readme_bullets}" != "${changelog_bullets}" ]]; then
		echo "update-changelog.sh: changelog mismatch for ${version}" >&2
		echo "--- readme.txt ---" >&2
		echo "${readme_bullets}" >&2
		echo "--- changelog.txt ---" >&2
		echo "${changelog_bullets}" >&2
		exit 1
	fi

	echo "OK: readme.txt and changelog.txt match for ${version} ($(echo "${readme_bullets}" | wc -l | tr -d ' ') bullets)"
}

cmd_add() {
	local version="${1:-}"
	local bullets_source="${2:--}"
	[[ -n "${version}" ]] || die "add requires <version>"
	validate_version "${version}"

	version_exists_in_readme "${version}" && die "version ${version} already exists in readme.txt"
	version_exists_in_changelog "${version}" && die "version ${version} already exists in changelog.txt"

	local bullets_tmp
	bullets_tmp="$(mktemp)"
	if [[ "${bullets_source}" == "-" ]]; then
		read_bullets "-" > "${bullets_tmp}"
	else
		read_bullets "${bullets_source}" > "${bullets_tmp}"
	fi
	[[ -s "${bullets_tmp}" ]] || die "no bullets provided (lines must start with \"* \")"

	insert_after_changelog_header "${README}" "${version}" "${bullets_tmp}"
	insert_after_changelog_title "${version}" "${bullets_tmp}"
	rm -f "${bullets_tmp}"

	echo "Added ${version} to readme.txt and changelog.txt"
	cmd_check "${version}"
}

cmd_add_upgrade_notice() {
	local version="${1:-}"
	local notice="${2:-}"
	[[ -n "${version}" && -n "${notice}" ]] || die "add-upgrade-notice requires <version> <notice-text>"
	validate_version "${version}"
	upgrade_notice_exists "${version}" && die "upgrade notice for ${version} already exists in readme.txt"

	insert_upgrade_notice "${version}" "${notice}"
	echo "Added upgrade notice for ${version} to readme.txt"
}

main() {
	local cmd="${1:-}"
	case "${cmd}" in
		check)
			shift
			cmd_check "$@"
			;;
		add)
			shift
			cmd_add "$@"
			;;
		add-upgrade-notice)
			shift
			cmd_add_upgrade_notice "$@"
			;;
		-h|--help|help|"")
			usage
			;;
		*)
			die "unknown command: ${cmd} (try --help)"
			;;
	esac
}

main "$@"
