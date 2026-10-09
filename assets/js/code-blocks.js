(function (win, doc) {
	'use strict';

	var labels = win.TechzeiCodeI18n || {};
	var ready = function () {
		var blocks = doc.querySelectorAll('.tz-code:not([data-tz-ready])');
		var live = doc.querySelector('.tz-code-live');
		var i;

		if (!live) {
			live = doc.createElement('span');
			live.className = 'tz-code-live screen-reader-text';
			live.setAttribute('aria-live', 'polite');
			live.setAttribute('aria-atomic', 'true');
			doc.body.appendChild(live);
		}

		for (i = 0; i < blocks.length; i++) {
			(function (block) {
				var code = block.querySelector('code');
				var language = block.getAttribute('data-lang') || 'plain';
				var button = doc.createElement('button');
				var timer;

				block.setAttribute('data-tz-ready', 'true');
				if (!code) {
					return;
				}
				if (!block.getAttribute('aria-label')) {
					block.setAttribute('role', 'group');
					block.setAttribute('aria-label', labels.code || 'Code');
				}
				if (language !== 'plain' && win.Prism && win.Prism.languages && win.Prism.languages[language]) {
					win.Prism.highlightElement(code);
				}

				button.type = 'button';
				button.className = 'tz-code__copy';
				button.setAttribute('aria-label', labels.copyCode || 'Copy code');
				button.textContent = labels.copy || 'Copy';
				button.addEventListener('click', function () {
					var text = code.textContent || '';
					var copyPromise = null;

					if (win.navigator.clipboard && win.isSecureContext && win.navigator.clipboard.writeText) {
						try {
							copyPromise = win.navigator.clipboard.writeText(text);
						} catch (error) {
							copyPromise = null;
						}
					}

					if (copyPromise && typeof copyPromise.then === 'function') {
						copyPromise.then(function () {
							feedback(true);
						}, function () {
							feedback(fallbackCopy(text, code));
						});
					} else {
						feedback(fallbackCopy(text, code));
					}

					function feedback(success) {
						button.textContent = success ? (labels.copied || 'Copied') : (labels.copy || 'Copy');
						button.setAttribute('aria-label', success ? (labels.copied || 'Copied') : (labels.copyCode || 'Copy code'));
						live.textContent = success ? (labels.copied || 'Copied') : (labels.copyFailed || 'Copy failed. Text selected.');
						win.clearTimeout(timer);
						timer = win.setTimeout(function () {
							button.textContent = labels.copy || 'Copy';
							button.setAttribute('aria-label', labels.copyCode || 'Copy code');
						}, 2000);
					}
			});
				block.insertBefore(button, block.firstChild);
			})(blocks[i]);
		}
	};

	function fallbackCopy(text, code) {
		var textarea = doc.createElement('textarea');
		var copied = false;

		textarea.value = text;
		textarea.setAttribute('readonly', 'readonly');
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		doc.body.appendChild(textarea);
		textarea.select();
		try {
			copied = doc.execCommand('copy');
		} catch (error) {
			copied = false;
		}
		doc.body.removeChild(textarea);

		if (!copied) {
			var range = doc.createRange();
			var selection = win.getSelection();
			range.selectNodeContents(code);
			selection.removeAllRanges();
			selection.addRange(range);
		}

		return copied;
	}

	if (doc.readyState === 'loading') {
		doc.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})(window, document);
