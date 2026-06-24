#!/usr/bin/env bash
#
# Sandbox *.sandbox hostnames must resolve ONLY to RFC1918, not loopback, so
# W3TC CDN test-button host validation (Util_Url::host_resolves_safe_internal)
# accepts them while still refusing metadata / loopback SSRF targets.
#
# Hostname lookups are case-insensitive (captial boxes use Wp.SandBox while
# CDN tests post wp.sandbox), so loopback scrubbing must match *.sandbox
# without regard to case.

set -e

SANDBOX_IP='10.127.0.1'
SANDBOX_HOSTS=(
	wp.sandbox
	b2.wp.sandbox
	for-tests.wp.sandbox
	for-tests.sandbox
	system.sandbox
)

set -a
[ -r /etc/environment ] && . /etc/environment
set +a
if [ -n "${W3D_WP_HOST:-}" ]; then
	SANDBOX_HOSTS+=("$W3D_WP_HOST")
fi

ip addr add "${SANDBOX_IP}/32" dev lo 2>/dev/null || true

# Strip *.sandbox names (any case) from loopback / link-local lines.
awk '
function is_sandbox(h) {
	return (tolower(h) ~ /\.sandbox$/)
}
/^127\.0\.0\.1|^::1/ {
	out = $1
	n = 0
	for (i = 2; i <= NF; i++) {
		if (!is_sandbox($i)) {
			out = out " " $i
			n++
		}
	}
	if (n > 0) {
		print out
	}
	next
}
{ print }
' /etc/hosts >/etc/hosts.tmp && mv /etc/hosts.tmp /etc/hosts

# Drop managed RFC1918 sandbox lines before re-adding canonical entries.
awk -v ip="${SANDBOX_IP}" '
function is_sandbox(h) {
	return (tolower(h) ~ /\.sandbox$/)
}
$1 == ip {
	all = (NF >= 2)
	for (i = 2; i <= NF; i++) {
		if (!is_sandbox($i)) {
			all = 0
		}
	}
	if (all) {
		next
	}
}
{ print }
' /etc/hosts >/etc/hosts.tmp && mv /etc/hosts.tmp /etc/hosts

declare -A _seen_sandbox_host=
for entry in "${SANDBOX_HOSTS[@]}"; do
	key=$(printf '%s' "$entry" | tr '[:upper:]' '[:lower:]')
	if [ -n "${_seen_sandbox_host[$key]:-}" ]; then
		continue
	fi
	_seen_sandbox_host[$key]=1
	echo "${SANDBOX_IP} ${entry}" >>/etc/hosts
done

if command -v nscd >/dev/null 2>&1 && nscd -i hosts 2>/dev/null; then
	:
fi
