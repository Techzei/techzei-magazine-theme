/**
 * Optional browser regression for mobile article hero geometry.
 *
 * Run `node tests/article-viewport-browser.mjs` with Playwright Chromium
 * available locally. The fixture models WordPress's constrained-layout
 * alignwide margins, which previously cancelled the theme's negative margin
 * and pushed a viewport-wide hero beyond the right edge.
 */
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../style.css', import.meta.url), 'utf8');
const widths = [320, 375, 390, 600, 601, 768];
const layouts = ['tz-layout-sidebar', 'tz-layout-no-sidebar'];
const browser = await chromium.launch({ headless: true });

try {
	for (const layout of layouts) {
		for (const width of widths) {
			const page = await browser.newPage({ viewport: { width, height: 812 } });

			try {
				await page.setContent(`
					<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>
					<body><div class="wp-site-blocks">
						<main class="wp-block-group alignwide tz-shell tz-article-layout ${layout} is-layout-constrained">
							<div class="tz-article-head"><h1 class="tz-article-title">A representative article title</h1></div>
							<figure class="alignwide tz-feature-image wp-block-post-featured-image">
								<img alt="" width="1440" height="810" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1440' height='810'/%3E">
							</figure>
							<div class="tz-article-columns"><div class="tz-article-primary"><p>Article body.</p></div><aside>Sidebar.</aside></div>
						</main>
					</div></body></html>
				`);
				await page.addStyleTag({ content: stylesheet });
				// WordPress's global constrained-layout rule wins over the old
				// margin-left declaration on an alignwide featured image.
				await page.addStyleTag({ content: 'body{margin:0}.is-layout-constrained > .alignwide{margin-inline:auto !important}' });

				const result = await page.evaluate(() => {
					const hero = document.querySelector('.tz-feature-image').getBoundingClientRect();
					return {
						viewport: innerWidth,
						pageWidth: document.documentElement.scrollWidth,
						heroLeft: hero.left,
						heroRight: hero.right,
						heroWidth: hero.width,
					};
				});

				assert.ok(result.pageWidth <= width + 1, `${layout} at ${width}px overflows: ${JSON.stringify(result)}`);
				assert.ok(result.heroLeft >= -1 && result.heroRight <= width + 1, `${layout} at ${width}px clips the hero: ${JSON.stringify(result)}`);
				if ('tz-layout-sidebar' === layout && width <= 600) {
					assert.ok(Math.abs(result.heroWidth - width) <= 1, `Mobile sidebar hero lost its full-bleed width: ${JSON.stringify(result)}`);
				}
				console.log(`${layout} ${width}px: page ${result.pageWidth}px, hero ${Math.round(result.heroLeft)}–${Math.round(result.heroRight)}px`);
			} finally {
				await page.close();
			}
		}
	}
} finally {
	await browser.close();
}
