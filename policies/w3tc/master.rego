package main

import rego.v1

# Fail when Pro type is set without a license key (license bypass persistence).
deny contains msg if {
  input["plugin.type"] == "pro"
  not has_license
  msg := "plugin.type=pro requires a non-empty plugin.license_key"
}

has_license if {
  is_string(input["plugin.license_key"])
  count(input["plugin.license_key"]) > 0
}

# CDN import masks must not admit PHP execution extensions.
deny contains msg if {
  files := input["cdn.import.files"]
  is_string(files)
  contains_php_mask(files)
  msg := "cdn.import.files must not include PHP/phtml masks"
}

deny contains msg if {
  files := input["cdn.import.files"]
  is_array(files)
  some i
  contains_php_mask(files[i])
  msg := "cdn.import.files must not include PHP/phtml masks"
}

contains_php_mask(v) if {
  is_string(v)
  regex.match(`(?i)\*\.(php|phtml|php[0-9]*)(\b|$)`, v)
}

# BrowserCache exception entries must not carry newlines (directive injection).
deny contains msg if {
  ex := input["browsercache.no404wp.exceptions"]
  is_array(ex)
  some i
  is_string(ex[i])
  regex.match(`[\r\n]`, ex[i])
  msg := sprintf("browsercache.no404wp.exceptions[%d] contains a newline", [i])
}
