/**
 * Read-only browser probe for real rendered pages. Run this function through a
 * browser's evaluate API (see tests/README.md); no WordPress bootstrap needed.
 * This tests browser layout, not PHP integration or subjective visual quality.
 */
export function auditTypography() {
	const style = element => {
		const css = getComputedStyle(element);
		return { size: css.fontSize, line: css.lineHeight, tracking: css.letterSpacing, color: css.color };
	};
	const prices = [...document.querySelectorAll('.tz-article-content td')]
		.filter(cell => /^₹[\d,]+(?:\.\d+)?$/.test(cell.textContent.trim()))
		.map(cell => {
			const range = document.createRange();
			range.selectNodeContents(cell);
			const lines = new Set([...range.getClientRects()].filter(rect => rect.width > 0).map(rect => Math.round(rect.top)));
			return { text: cell.textContent.trim(), lines: lines.size, ...style(cell) };
		});
	const cards = [...document.querySelectorAll('.tz-feature-card')].map(card => {
		const copy = card.querySelector('.tz-card-copy');
		const title = copy?.querySelector('.wp-block-post-title');
		if (!copy || !title) return { fits: false };
		const outer = card.getBoundingClientRect();
		const inner = copy.getBoundingClientRect();
		return { text: title.textContent.trim(), fits: inner.top >= outer.top - 1 && inner.bottom <= outer.bottom + 1, ...style(title) };
	});
	const title = document.querySelector('.tz-article-title');
	const prose = document.querySelector('.tz-article-content p');
	return {
		viewport: innerWidth,
		pageWidth: document.documentElement.scrollWidth,
		noPageOverflow: document.documentElement.scrollWidth <= innerWidth + 1,
		prices,
		pricesIntact: prices.every(price => price.lines === 1),
		cards,
		cardsFit: cards.every(card => card.fits),
		imagePriorityConflicts: document.querySelectorAll('img[loading="lazy"][fetchpriority="high"]').length,
		title: title ? style(title) : null,
		prose: prose ? { ...style(prose), width: prose.getBoundingClientRect().width } : null,
		content: document.querySelector('main')?.textContent.trim(),
	};
}
