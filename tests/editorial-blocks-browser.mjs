/**
 * Optional geometry check for native article tables, long TOCs and callouts.
 * Run `node tests/editorial-blocks-browser.mjs` with Playwright Chromium.
 * Set TECHZEI_SCREENSHOT_DIR to keep fixture screenshots for visual review.
 */
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { chromium } from 'playwright';

const style = await readFile(new URL('../style.css', import.meta.url), 'utf8');
const articleStyle = await readFile(new URL('../assets/css/article.css', import.meta.url), 'utf8');
const screenshotDir = process.env.TECHZEI_SCREENSHOT_DIR;
const browser = await chromium.launch({ headless: true });
const tocItems = Array.from({ length: 31 }, (_, index) => `<li><a href="#section-${index + 1}">Step ${index + 1}: A useful section heading</a></li>`).join('');

if (screenshotDir) {
	await mkdir(screenshotDir, { recursive: true });
}

try {
	for (const width of [320, 375, 390, 768, 1280]) {
		const page = await browser.newPage({ viewport: { width, height: 812 } });

		try {
			await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
				<main class="tz-article-layout tz-layout-sidebar"><div class="tz-article-columns"><article class="tz-article-primary">
				<div class="tz-article-toc-part"><nav class="tz-article-toc"><details><summary>On this page</summary><ol>${tocItems}</ol></details></nav></div>
				<div class="tz-article-content">
				<p>A readable opening paragraph with enough text to represent a real article. The layout should remain steady at narrow widths.</p>
				<figure class="wp-block-table"><table><thead><tr><th>Device</th><th>Gemini for Home</th><th>Gemini Live</th></tr></thead><tbody><tr><td>Original Google Home, Google Home Max, Nest Wifi point</td><td>Most features</td><td>No</td></tr></tbody></table></figure>
				<figure class="wp-block-table"><table><tbody><tr><th>Model</th><td>Nest Mini</td></tr></tbody></table></figure>
				<div class="wp-block-group tz-editorial-callout tz-editorial-callout--prepare"><p class="tz-callout-label">Before you start</p><p class="tz-callout-body">Check the exact model and back up anything you cannot replace.</p></div>
				<div class="wp-block-group tz-editorial-callout tz-editorial-callout--important"><p class="tz-callout-label">Important</p><p class="tz-callout-body">Test the shutdown on your own hardware before relying on it.</p></div>
				</div></article><aside class="tz-article-sidebar"><div class="tz-article-toc-sidebar-part"><nav class="tz-article-toc"><details open><summary>On this page</summary><ol>${tocItems}</ol></details></nav></div></aside></div></main>
			</body></html>`);
			await page.addStyleTag({ content: style });
			await page.addStyleTag({ content: articleStyle });
			await page.addStyleTag({ content: 'body{margin:0}.tz-article-primary{min-width:0}.tz-article-sidebar{min-width:0}' });

			const result = await page.evaluate(() => {
				const tables = [...document.querySelectorAll('.tz-article-content figure.wp-block-table')];
				const wide = tables[0];
				const narrow = tables[1];
				const mobileToc = document.querySelector('.tz-article-toc-part');
				const desktopToc = document.querySelector('.tz-article-toc-sidebar-part');
				return {
					pageWidth: document.documentElement.scrollWidth,
					wideClient: wide.clientWidth,
					wideScroll: wide.scrollWidth,
					wordBreak: getComputedStyle(wide.querySelector('td:nth-child(2)')).wordBreak,
					wordFragments: (() => {
						const node = wide.querySelector('td:nth-child(2)').firstChild;
						const start = node.textContent.indexOf('features');
						const range = document.createRange();
						range.setStart(node, start);
						range.setEnd(node, start + 'features'.length);
						return range.getClientRects().length;
					})(),
					narrowClient: narrow.clientWidth,
					narrowScroll: narrow.scrollWidth,
					mobileTocVisible: getComputedStyle(mobileToc).display !== 'none',
					mobileTocOpen: mobileToc.querySelector('details').open,
					desktopTocVisible: getComputedStyle(desktopToc).display !== 'none',
					desktopListClient: desktopToc.querySelector('ol').clientHeight,
					desktopListScroll: desktopToc.querySelector('ol').scrollHeight,
					calloutWidth: document.querySelector('.tz-editorial-callout').getBoundingClientRect().width,
					contentWidth: document.querySelector('.tz-article-content').getBoundingClientRect().width,
				};
			});

			assert.ok(result.pageWidth <= width + 1, `${width}px page overflow: ${JSON.stringify(result)}`);
			assert.ok(result.calloutWidth <= result.contentWidth + 1, `${width}px callout overflow: ${JSON.stringify(result)}`);
			assert.ok(result.narrowScroll <= result.narrowClient + 1, `${width}px two-column table should fit: ${JSON.stringify(result)}`);
			if (width <= 600) {
				assert.ok(result.wideScroll > result.wideClient, `${width}px three-column table did not scroll: ${JSON.stringify(result)}`);
				assert.equal(result.wordBreak, 'normal');
				assert.equal(result.wordFragments, 1, `${width}px table broke a normal word: ${JSON.stringify(result)}`);
			}
			if (width <= 900) {
				assert.equal(result.mobileTocVisible, true);
				assert.equal(result.mobileTocOpen, false);
				await page.locator('.tz-article-toc-part summary').click();
				const openedToc = await page.locator('.tz-article-toc-part ol').evaluate((list) => ({ client: list.clientHeight, scroll: list.scrollHeight }));
				assert.ok(openedToc.scroll > openedToc.client, `${width}px expanded TOC was not bounded: ${JSON.stringify(openedToc)}`);
				await page.locator('.tz-article-toc-part summary').click();
			} else {
				assert.equal(result.desktopTocVisible, true);
				assert.ok(result.desktopListScroll > result.desktopListClient, `Desktop long TOC did not scroll: ${JSON.stringify(result)}`);
			}

			if (screenshotDir && (width === 375 || width === 1280)) {
				await page.screenshot({ path: join(screenshotDir, `editorial-blocks-${width}.png`), fullPage: true });
			}
			console.log(`${width}px: ${JSON.stringify(result)}`);
		} finally {
			await page.close();
		}
	}
} finally {
	await browser.close();
}
