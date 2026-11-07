/**
 * Lightweight article-footer layout check using the actual theme stylesheet.
 * Run with `node tests/article-chrome-browser.mjs` after installing Playwright.
 */
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const stylesheet = await readFile(new URL('../style.css', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
	for (const width of [320, 375, 768, 1280]) {
		const page = await browser.newPage({ viewport: { width, height: 900 } });
		try {
			await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
				<main class="tz-article-flow" style="max-width:780px;margin-inline:auto">
					<div class="tz-share"><p>Share this story</p><nav class="tz-share-links"><a class="tz-share-reddit" href="#">Reddit</a></nav></div>
					<div class="tz-author-profile"><p>About the author</p><p>Author details.</p></div>
					<div class="tz-post-tags"><a href="#">Topic</a></div>
					<div class="tz-post-navigation" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
						<div class="tz-post-navigation-inner is-layout-flex">
							<div class="post-navigation-link-previous"><span>Previous story</span><a rel="prev" href="#">A previous story with a long enough title to wrap naturally</a></div>
							<div class="post-navigation-link-next has-text-align-right"><span>Next story</span><a rel="next" href="#">The next story also has a reasonably long title</a></div>
						</div>
					</div>
				</main></body></html>`);
			await page.addStyleTag({ content: stylesheet });
			// Emulate WordPress's later-generated flex layout rule.
			await page.addStyleTag({ content: '.is-layout-flex{display:flex;gap:1rem}body{margin:0}' });
			const geometry = await page.evaluate(() => {
				const rect = (selector) => document.querySelector(selector).getBoundingClientRect();
				const border = (selector, edge) => getComputedStyle(document.querySelector(selector))[edge];
				return {
					pageWidth: document.documentElement.scrollWidth,
					previousTop: rect('.post-navigation-link-previous').top,
					nextTop: rect('.post-navigation-link-next').top,
					shareBottom: border('.tz-share', 'borderBottomWidth'),
					authorTop: border('.tz-author-profile', 'borderTopWidth'),
					authorBottom: border('.tz-author-profile', 'borderBottomWidth'),
					tagsTop: border('.tz-post-tags', 'borderTopWidth'),
				};
			});
			assert.ok(geometry.pageWidth <= width, `${width}px article chrome overflows: ${JSON.stringify(geometry)}`);
			assert.equal(geometry.shareBottom, '1px');
			assert.equal(geometry.authorTop, '0px');
			assert.equal(geometry.authorBottom, '1px');
			assert.equal(geometry.tagsTop, '0px');
			if (width > 600) {
				assert.ok(Math.abs(geometry.previousTop - geometry.nextTop) < 1, `${width}px navigation did not fit on one row`);
			} else {
				assert.ok(geometry.nextTop > geometry.previousTop, `${width}px navigation should stack`);
			}
			if (process.env.TECHZEI_CHROME_SCREENSHOT_DIR) {
				await page.screenshot({ path: `${process.env.TECHZEI_CHROME_SCREENSHOT_DIR}/techzei-article-chrome-${width}.png` });
			}
			console.log(`${width}px article chrome: no overflow, single borders, ${width > 600 ? 'two-column' : 'stacked'} story links`);
		} finally {
			await page.close();
		}
	}
} finally {
	await browser.close();
}
