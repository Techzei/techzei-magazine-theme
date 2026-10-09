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
