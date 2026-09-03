/* Lightweight fallback for the core Navigation block's mobile overlay. */
(function () {
	'use strict';

	/* Keep mobile search visible until JavaScript has initialized. */
	document.documentElement.classList.add('tz-js');

	function initTrendingTicker() {
		var ticker = document.querySelector('.tz-trending .wp-block-latest-posts');
		var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		if (!ticker || reduceMotion || ticker.scrollWidth <= ticker.clientWidth) {
			return;
		}

		var paused = false;
		var direction = 1;

		ticker.addEventListener('mouseenter', function () { paused = true; });
		ticker.addEventListener('mouseleave', function () { paused = false; });
		ticker.addEventListener('focusin', function () { paused = true; });
		ticker.addEventListener('focusout', function () { paused = false; });

		window.setInterval(function () {
			if (paused) {
				return;
			}

			ticker.scrollLeft += direction;

			if (ticker.scrollLeft >= ticker.scrollWidth - ticker.clientWidth - 1) {
				direction = -1;
			} else if (ticker.scrollLeft <= 0) {
				direction = 1;
			}
		}, 45);
	}

	function initMobileSearch() {
		var masthead = document.querySelector('.tz-masthead');
		var toggle = masthead && masthead.querySelector('.tz-search-toggle');
		var search = masthead && masthead.querySelector('.tz-header-search');

		if (!masthead || !toggle || !search) {
			return;
		}

		function closeSearch() {
			masthead.classList.remove('is-search-open');
			toggle.setAttribute('aria-expanded', 'false');
		}

		toggle.addEventListener('click', function () {
			var isOpen = masthead.classList.toggle('is-search-open');
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			if (isOpen) {
				window.setTimeout(function () {
					var input = search.querySelector('input[type="search"]');
					if (input) {
						input.focus();
					}
				}, 0);
			}
		});

		document.addEventListener('click', function (event) {
			if (!masthead.contains(event.target)) {
				closeSearch();
			}
		});

		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key) {
				closeSearch();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initTrendingTicker();
			initMobileSearch();
		});
	} else {
		initTrendingTicker();
		initMobileSearch();
	}

}());
