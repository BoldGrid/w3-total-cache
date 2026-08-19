#!/usr/bin/env bash
# Run W3TC-specific Semgrep / PHPCS / Conftest regression checks.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

fail=0

run_semgrep() {
  if ! command -v semgrep >/dev/null 2>&1; then
    echo "SKIP semgrep (not installed)"
    return 0
  fi
  echo "== semgrep (production rules on plugin sources) =="
  semgrep --config rules/w3tc-semgrep.yml --error \
    --exclude=vendor --exclude=node_modules --exclude=tests \
    --exclude=ci --exclude=phpcs-rules || fail=1

  echo "== semgrep fixtures (expect findings in bad/) =="
  if semgrep --config rules/w3tc-semgrep.yml --error ci/testdata/semgrep/bad >/dev/null 2>&1; then
    echo "FAIL: expected semgrep findings in ci/testdata/semgrep/bad"
    fail=1
  else
    echo "OK: bad fixtures flagged"
  fi
  echo "== semgrep fixtures (expect clean good/) =="
  semgrep --config rules/w3tc-semgrep.yml --error ci/testdata/semgrep/good || fail=1
}

run_phpcs() {
  if [[ ! -x vendor/bin/phpcs ]]; then
    echo "SKIP phpcs (vendor/bin/phpcs missing)"
    return 0
  fi
  echo "== phpcs W3TC security sniffs on fixtures =="
  if vendor/bin/phpcs --standard=phpcs-rules/W3TC/ruleset.xml \
      --runtime-set installed_paths phpcs-rules \
      ci/testdata/phpcs/bad >/dev/null 2>&1; then
    echo "FAIL: expected phpcs findings in bad fixtures"
    fail=1
  else
    echo "OK: bad phpcs fixtures flagged"
  fi
  vendor/bin/phpcs --standard=phpcs-rules/W3TC/ruleset.xml \
    --runtime-set installed_paths phpcs-rules \
    ci/testdata/phpcs/good || fail=1

  echo "== phpcs W3TC security sniffs on production sources =="
  vendor/bin/phpcs --standard=phpcs-rules/W3TC/ruleset.xml \
    --runtime-set installed_paths phpcs-rules \
    Cache_File_Generic.php SetupGuide_Plugin_Admin.php Generic_AdminActions_Default.php || fail=1
}

run_conftest() {
  if ! command -v conftest >/dev/null 2>&1; then
    echo "SKIP conftest (not installed)"
    return 0
  fi
  echo "== conftest good fixture =="
  conftest test --policy policies/w3tc ci/testdata/conftest/good-config.json || fail=1
  conftest test --policy policies/w3tc ci/testdata/conftest/good-pro-with-license.json || fail=1

  echo "== conftest bad fixtures (expect deny) =="
  for f in bad-pro-no-license.json bad-cdn-php-mask.json bad-exceptions-newline.json; do
    if conftest test --policy policies/w3tc "ci/testdata/conftest/$f" >/dev/null 2>&1; then
      echo "FAIL: expected deny for $f"
      fail=1
    else
      echo "OK: denied $f"
    fi
  done
}

run_semgrep
run_phpcs
run_conftest

if [[ "$fail" -ne 0 ]]; then
  echo "security checks FAILED"
  exit 1
fi
echo "security checks PASSED"
