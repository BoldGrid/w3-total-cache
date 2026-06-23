#!/usr/bin/env bash
# Parse W3TCQA summary.html failure section: spec family → failure count.
# Usage: summary-spec-counts.sh path/to/summary.html

set -euo pipefail

if [[ $# -ne 1 ]]; then
	echo "usage: $0 summary.html" >&2
	exit 1
fi

file="$1"
current=""
in_failed=0
declare -A counts

while IFS= read -r line; do
	if [[ "$line" == *'Failed tests'* ]]; then
		in_failed=1
		continue
	fi
	if [[ $in_failed -eq 1 && "$line" == *'All tests'* ]]; then
		break
	fi
	if [[ $in_failed -eq 0 ]]; then
		continue
	fi
	if [[ "$line" == '<p>' ]]; then
		current=""
		continue
	fi
	if [[ -z "$current" && "$line" != *'<'* && -n "${line// }" ]]; then
		current="$line"
		counts["$current"]=0
		continue
	fi
	if [[ -n "$current" && "$line" == *'<li>'* && "$line" != *'color: green'* ]]; then
		counts["$current"]=$(( counts["$current"] + 1 ))
	fi
done < "$file"

total=0
for spec in $(printf '%s\n' "${!counts[@]}" | sort); do
	n=${counts["$spec"]}
	total=$(( total + n ))
	printf '%5d  %s\n' "$n" "$spec"
done

echo "---"
echo "total failures (failed section only): $total"
