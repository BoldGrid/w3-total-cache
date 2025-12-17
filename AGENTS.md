# Contributor Guide

## Project Overview
This project is a WordPress plugin designed to enhance website performance through caching and other optimization techniques.

## Coding Standards
- Follow the coding standards defined in the ./phpcs.xml file.
- This is a WordPress plugin, so the coding standards must adhere to the WordPress coding standards.
- This plugin must be compatible with PHP 7.2.5 through 8.3, as defined in the main plugin file "w3-total-cache.php" and "readme.txt".
- This plugin must be compatible with WordPress 5.3 and up, as defined in the main plugin file "w3-total-cache.php" and "readme.txt".
- Do not use spaces for indentation; use 4-space tabs instead.
- Use single quotes for strings unless double quotes are necessary (e.g., when using variables inside the string).
- Do not make coding standards changes in changed files unless it is directly related to the functionality being modified.
- Opening parenthesis of a multi-line function call must be the last content on the line (PEAR.Functions.FunctionCallSignature.ContentAfterOpenBracket).
- Prefix all global namespace functions with a backslash.

## References
- WordPress Coding Standards: https://developer.wordpress.org/coding-standards/
- WordPress Coding Standards for PHP: https://developer.wordpress.org/coding-standards/php/
- WordPress Coding Standards for JavaScript: https://developer.wordpress.org/coding-standards/javascript/
- WordPress Coding Standards for HTML: https://developer.wordpress.org/coding-standards/html/
- WordPress Coding Standards for CSS: https://developer.wordpress.org/coding-standards/css/
- WordPress Coding Standards for Accessibility: https://developer.wordpress.org/coding-standards/accessibility
- WordPress Documentation Standards for PHP: https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/
- WordPress Documentation Standards for JavaScript: https://developer.wordpress.org/coding-standards/inline-documentation-standards/javascript/

## Contribution Process
- Add `@since X.X.X` to all new doc blocks -- it's updated in our build process.
- Do not update POT files -- it's done in our build process.
- Do not change the `readme.txt` file -- it's done on release branches.
- Do not increment the plugin version number -- it's done in our build process.
- All changes must be submitted via pull requests.
- Public-facing work may originate from GitHub issues to ensure public visibility.
  - Create a GitHub issue describing the change, implement the change in a branch, and open a pull request that references the GitHub issue.
- Internal work may originate from JIRA issues for internal tracking.
  - Create a JIRA issue, implement the change in a branch, and open a pull request that references the JIRA issue.
- Ensure each pull request references its originating issue (GitHub or JIRA) and includes a clear description of the change.

## Dependency Management
- Use `yarn run upgrade:deps` to refresh JS packages and Composer libraries in one step; this enforces the PHP 7.2.5–8.3 constraint declared in `composer.json`.
- When running Composer directly, keep `composer update --with-all-dependencies` targeted at the repo root so the generated lock file honors the configured PHP platform (7.2.5).
