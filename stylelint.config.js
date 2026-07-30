// @wordpress/stylelint-config@23.x peer-deps stylelint ^16.8.2; keep package.json aligned until WP ships stylelint 17 support.
module.exports = {
  extends: "@wordpress/stylelint-config",
  rules: {
    // WP standard is hyphenated selectors, but long-standing admin markup also ships snake_case class/id names that are contracts with PHP and JS; allow underscores alongside hyphens instead of renaming across the codebase.
    "selector-class-pattern": [
      "^([a-z][a-z0-9]*)([_-][a-z0-9]+)*$",
      {
        message:
          "Selector should be lowercase words separated by hyphens or underscores (selector-class-pattern)",
      },
    ],
    "selector-id-pattern": [
      "^([a-z][a-z0-9]*)([_-][a-z0-9]+)*$",
      {
        message:
          "Selector should be lowercase words separated by hyphens or underscores (selector-id-pattern)",
      },
    ],
    // Code-quality rules from stylelint-config-recommended (not part of the WP CSS handbook). Enabling them on this legacy CSS would require risky source reordering and cross-file selector merges, including in vendored Bootstrap styles.
    "no-descending-specificity": null,
    "no-duplicate-selectors": null,
  },
};
