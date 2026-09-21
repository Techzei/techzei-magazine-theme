# Optional WordPress integration tests

The default repository checks are intentionally lightweight and do not download
WordPress or PHPUnit. For runtime coverage, install a WordPress test suite and
PHPUnit in a separate development environment, make Twenty Twenty-Five
available there, then run:

```sh
WP_TESTS_DIR=/path/to/wordpress-tests-lib vendor/bin/phpunit -c tests/phpunit.xml.dist
```

The suite creates posts and taxonomy terms in the test database. It covers
discovery ID caching, overlap suppression, TOC anchor matching, settings-cache
invalidation, featured-image filter registration, and legacy shortcode
registration. It is not part of the release ZIP and is not a substitute for
the manual browser checks in `DEPLOYMENT.md`.
