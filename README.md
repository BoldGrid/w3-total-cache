[![CI](https://github.com/BoldGrid/w3-total-cache/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/BoldGrid/w3-total-cache/actions/workflows/ci.yml)

Welcome to the W3 Total Cache repository on GitHub. Here you can browse the source, look at open issues and keep track of development.

If you are not a developer, please use the [W3 Total Cache plugin page](https://wordpress.org/plugins/w3-total-cache/) on WordPress.org.

## Developer documentation

* [W3 Total Cache actions and filters](docs/hooks.md)

## Support
This repository is not suitable for support. Please don't use our issue tracker for support requests. Support can take place through the appropriate channels:

* The support form can be found in Performance -> Support page of your wp-admin.
* [Our community forum on wp.org](https://wordpress.org/support/plugin/w3-total-cache).

Support requests in issues on this repository will be closed on sight.

## Contributing to W3 Total Cache
If you have a patch or have stumbled upon an issue with W3 Total Cache, you can contribute this back to the code. Please read our [contributor guidelines](https://github.com/BoldGrid/w3-total-cache/wiki/Contributor-Guidelines) for more information how you can do this.

## Continuous Integration

### PHPUnit
The plugin's automated tests run on [PHPUnit](https://phpunit.de/) `^8.5` paired with [Yoast PHPUnit Polyfills](https://github.com/Yoast/PHPUnit-Polyfills) `^3.0`, which adapts PHPUnit 8.5's assertion surface to the full PHP 7.4 → 8.5 range supported by the plugin. The suite is configured in [`phpunit.xml`](phpunit.xml) and lives under [`tests/`](tests/), with [`tests/bootstrap.php`](tests/bootstrap.php) loading the plugin into the WordPress test environment.

Most tests boot the [WordPress test scaffolding](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/), which is downloaded into `WP_TESTS_DIR` by [`bin/install-wp-tests.sh`](bin/install-wp-tests.sh). A handful of standalone tests (e.g. `tests/test-cache-unserialize.php`) can be invoked with `php tests/<file>` directly, without the WordPress bootstrap.

To run the suite locally:

```bash
yarn run install:deps
bash bin/install-wp-tests.sh wordpress_test root '' localhost
./vendor/bin/phpunit
```

A single test file can be targeted by passing its path, e.g. `./vendor/bin/phpunit tests/test-generic-plugin.php`. Code-coverage output is written to `coverage.xml` in [Clover](https://www.atlassian.com/software/clover) format and is what the CI job below feeds to Codecov.

### GitHub Actions
[GitHub Actions](https://github.com/BoldGrid/w3-total-cache/actions/workflows/ci.yml) runs the PHPUnit suite on every pull request and push to `master`. The workflow lives in [`.github/workflows/ci.yml`](.github/workflows/ci.yml) and exercises both ends of the supported PHP version range:

* **PHP 7.4** — the lower bound of supported PHP. This job also uploads the PHPUnit coverage report to [Codecov](https://codecov.io/).
* **PHP 8.5** — the upper bound of supported PHP.

Each job installs Composer and yarn dependencies, downloads the WordPress test scaffolding via `bin/install-wp-tests.sh`, checks generated hooks documentation, runs the PHPUnit suite described above (plus the multisite group), installs production Composer dependencies, and PHP-lints every plugin source file. Tag pushes run [`.github/workflows/release.yml`](.github/workflows/release.yml), which repeats the test gate and then builds the release zip via `bin/release.sh`. The live build status is shown by the badge at the top of this README.
