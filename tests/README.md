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
registration, plus the optional `CodeBlocksTest.php` WordPress renderer tests.
It is not part of the release ZIP and is not a substitute for the manual
browser checks in `DEPLOYMENT.md`.

Code-rendering compatibility fixtures can be run independently with
`php tests/code-blocks-static.php`; Enlighter's active-plugin guard can be
checked with `php tests/code-blocks-enlighter-active.php`. The fixture check
exercises rendering callbacks with minimal WordPress API stubs, while
`CodeBlocksTest.php` uses the optional WordPress integration bootstrap. The
fixture checks are included in both quality and release workflows.

## Author portfolios: optional real-site integration runner

No PHPUnit dependency is required for this additional runner. Use an isolated,
disposable local WordPress install with Twenty Twenty-Five and this child theme
active. Its wp-config.php must define `WP_ENVIRONMENT_TYPE` as `local` and
`TECHZEI_AUTHOR_TEST_SITE` as `true`. Never target production or a valuable
development database. The runner creates temporary users/posts/terms and removes
only its own tracked fixtures in a `finally` block.

```sh
TECHZEI_WP_TEST_ROOT=/absolute/path/to/disposable/wordpress php tests/authors-wordpress.php
```

It exercises published-only aggregation, distinct review counts, first-story
ordering, cold/warm query counts, persistent transients without an external object
cache, author-specific coverage, native query filtering and pagination, malformed
controls, profile validation/nonce/capabilities, selected-work ownership/status,
queried-author identity, actual block-template shortcode formatting, empty
authors, responsive image priority and status/author/taxonomy/deletion invalidation.
It is a real WordPress API integration runner, not the PHPUnit suite above.

Locally verified on WordPress 6.8.3 with official SQLite Database Integration
3.1.0 and PHP 8.5.10. MySQL/MariaDB, PHP 7.4 runtime, newer WordPress releases and
third-party avatar/translation/SEO combinations still need staging verification.

## Mobile article viewport regression

With Playwright Chromium available locally, run:

```sh
node tests/article-viewport-browser.mjs
```

This optional fixture check loads the actual theme stylesheet with a simulated
WordPress constrained-layout rule and asserts that sidebar and no-sidebar
article heroes stay inside 320, 375, 390, 600, 601, and 768px viewports. It
also checks that the sidebar hero retains its full-bleed mobile treatment.
The old negative-margin rule fails the 320px case with a 335px page; the
corrected relative offset does not. Test a real post as well, since saved
Site Editor templates and plugin CSS can differ from the fixture.

## Typography browser probe

`typography-browser.mjs` exports `auditTypography()`, a dependency-free function
to run in a rendered page's read-only browser evaluation context. For example,
with a browser handle that supports `evaluate`:

```js
const { auditTypography } = await import('./tests/typography-browser.mjs');
const result = await page.evaluate(`(${auditTypography.toString()})()`);
if (!result.noPageOverflow || !result.cardsFit || !result.pricesIntact || result.imagePriorityConflicts) {
  throw new Error('Typography regression: ' + JSON.stringify(result));
}
```

Run on the homepage, an article with price tables, sidebar/no-sidebar articles,
a legacy article, archive, search and 404 at mobile and desktop widths. Compare
the returned `content` strings before/after to ensure text is unchanged, and
inspect screenshots: a geometry check cannot establish visual quality. The
probe detects whole-page overflow, featured-copy clipping, split currency cells
and invalid lazy/high-priority images; it also reports computed type metrics.
It is not a WordPress integration test and does not install a browser runner.
