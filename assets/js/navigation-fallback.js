/*
 * Progressive interaction enhancements for the Techzei header and ticker.
 *
 * The WordPress Navigation block owns menu state, focus, and its mobile
 * overlay. This file intentionally does not intercept navigation clicks.
 */
(function () {
	'use strict';

	var doc = document;
	var win = window;
	var idCounter = 0;

	/* Keep the full search form available until the enhancement is ready. */
	doc.documentElement.classList.add('tz-js');

	function forEachNode(nodes, callback) {
		var index;

		for (index = 0; index < nodes.length; index += 1) {
			callback(nodes[index], index);
		}
	}

	function nextId(prefix) {
		idCounter += 1;
		return prefix + '-' + idCounter;
	}

	function setSearchLabel(toggle, isOpen) {
		var label = isOpen ? 'Close search' : 'Open search';
		var screenReaderText = toggle.querySelector('.screen-reader-text');

		toggle.setAttribute('aria-label', label);

		if (screenReaderText) {
			screenReaderText.textContent = label;
		}
	}

	function setSearchFocusability(search, isOpen) {
		var controls = search.querySelectorAll('a, button, input, select, textarea, [tabindex]');

		if ('inert' in search) {
			search.inert = !isOpen;
		}

		forEachNode(controls, function (control) {
			var originalTabIndex;

			if (isOpen) {
				if (!control.hasAttribute('data-tz-search-tabindex')) {
					return;
				}

				originalTabIndex = control.getAttribute('data-tz-search-tabindex');

				if ('' === originalTabIndex) {
					control.removeAttribute('tabindex');
				} else {
					control.setAttribute('tabindex', originalTabIndex);
				}

				control.removeAttribute('data-tz-search-tabindex');
				return;
			}

			if (!control.hasAttribute('data-tz-search-tabindex')) {
				control.setAttribute(
					'data-tz-search-tabindex',
					control.hasAttribute('tabindex') ? control.getAttribute('tabindex') : ''
				);
			}

			control.setAttribute('tabindex', '-1');
		});
	}

	function initSearch(masthead) {
		var toggle = masthead.querySelector('[data-tz-search-toggle], .tz-search-toggle');
		var search = masthead.querySelector('[data-tz-search-region], .tz-header-search');
		var input;
		var searchId;
		var isOpen = false;
		var compactMode = false;

		if (!toggle || !search || toggle.hasAttribute('data-tz-search-initialized')) {
			return;
		}

		toggle.setAttribute('data-tz-search-initialized', 'true');
		searchId = search.getAttribute('id');

		if (!searchId) {
			searchId = nextId('tz-search');
			search.setAttribute('id', searchId);
		}

		toggle.setAttribute('aria-controls', searchId);
		toggle.setAttribute('aria-expanded', 'false');
		search.setAttribute('role', 'search');
		setSearchLabel(toggle, false);
		input = search.querySelector('input[type="search"]');

		function isCompactMode() {
			return 'none' !== win.getComputedStyle(toggle).display;
		}

		function refreshSearchMode() {
			compactMode = isCompactMode();

			if (!compactMode) {
				isOpen = false;
				masthead.classList.remove('is-search-open');
				toggle.setAttribute('aria-expanded', 'false');
				search.setAttribute('aria-hidden', 'false');
				setSearchFocusability(search, true);
				return;
			}

			if (!isOpen) {
				search.setAttribute('aria-hidden', 'true');
				setSearchFocusability(search, false);
			}
		}

		function closeSearch(returnFocus) {
			isOpen = false;
			masthead.classList.remove('is-search-open');
			toggle.setAttribute('aria-expanded', 'false');
			search.setAttribute('aria-hidden', compactMode ? 'true' : 'false');
			setSearchLabel(toggle, false);
			setSearchFocusability(search, !compactMode);

			if (returnFocus) {
				toggle.focus();
			}
		}

		function openSearch() {
			if (!compactMode) {
				return;
			}

			isOpen = true;
			masthead.classList.add('is-search-open');
			toggle.setAttribute('aria-expanded', 'true');
			search.setAttribute('aria-hidden', 'false');
			setSearchLabel(toggle, true);
			setSearchFocusability(search, true);

			win.setTimeout(function () {
				if (isOpen && input) {
					input.focus();
				}
			}, 0);
		}

		toggle.addEventListener('click', function (event) {
			event.preventDefault();

			if (isOpen) {
				closeSearch(true);
			} else {
				openSearch();
			}
		});

		doc.addEventListener('click', function (event) {
			if (compactMode && isOpen && !masthead.contains(event.target)) {
				/* Do not move focus away from the control the reader clicked. */
				closeSearch(false);
			}
		});

		doc.addEventListener('keydown', function (event) {
			var activeElement = doc.activeElement;
			var focusIsInSearch = search.contains(event.target) || search.contains(activeElement);

			if (compactMode && isOpen && 'Escape' === event.key && focusIsInSearch) {
				event.preventDefault();
				closeSearch(true);
			}
		});

		win.addEventListener('resize', refreshSearchMode);
		refreshSearchMode();
	}

	function getMediaQuery(query, fallback) {
		if (!win.matchMedia) {
			return {
				matches: fallback,
				addListener: function () {},
				removeListener: function () {}
			};
		}

		return win.matchMedia(query);
	}

	function initTicker(root) {
		var list;
		var control;
		var mode;
		var speedName;
		var speed;
		var reducedMotion;
		var mobile;
		var inView = true;
		var documentVisible = !doc.hidden;
		var manualPause = false;
		var interactionPause = false;
		var animationId = 0;
		var lastFrame = 0;
		var direction = 1;
		var touchResumeId = 0;
		var reduceQuery;
		var mobileQuery;
		var observer;

		if (!root || root.hasAttribute('data-tz-ticker-initialized')) {
			return;
		}

		list = root.matches && root.matches('ul, ol')
			? root
			: root.querySelector('[data-tz-ticker-list], .wp-block-latest-posts');

		if (!list) {
			return;
		}

		root.setAttribute('data-tz-ticker-initialized', 'true');
		mode = root.getAttribute('data-tz-ticker-mode') || 'marquee';
		speedName = root.getAttribute('data-tz-ticker-speed') || 'slow';
		speed = 'standard' === speedName ? 34 : 22;
		reduceQuery = getMediaQuery('(prefers-reduced-motion: reduce)', false);
		mobileQuery = getMediaQuery('(max-width: 600px)', false);
		reducedMotion = reduceQuery.matches;
		mobile = mobileQuery.matches;

		if (!list.id) {
			list.id = nextId('tz-ticker-list');
		}

		root.setAttribute('role', root.getAttribute('role') || 'region');
		if (!root.getAttribute('aria-label')) {
			root.setAttribute('aria-label', 'Trending headlines');
		}

		control = root.querySelector('[data-tz-ticker-control], .tz-ticker-toggle');

		if (!control && 'marquee' === mode) {
			control = doc.createElement('button');
			control.type = 'button';
			control.className = 'tz-ticker-toggle';
			control.setAttribute('data-tz-ticker-control', 'true');
			root.appendChild(control);
		}

		if (control) {
			control.setAttribute('aria-controls', list.id);
			control.setAttribute('aria-pressed', 'false');
		}

		function cancelAnimation() {
			if (animationId) {
				if (win.cancelAnimationFrame) {
					win.cancelAnimationFrame(animationId);
				} else {
					win.clearTimeout(animationId);
				}
			}

			animationId = 0;
			lastFrame = 0;
		}

		function hasOverflow() {
			return list.scrollWidth > list.clientWidth + 1;
		}

		function shouldAnimate() {
			return 'marquee' === mode && !mobile && !reducedMotion && !manualPause && !interactionPause && inView && documentVisible && hasOverflow();
		}

		function requestFrame(callback) {
			if (win.requestAnimationFrame) {
				return win.requestAnimationFrame(callback);
			}

			return win.setTimeout(function () {
				callback(new Date().getTime());
			}, 16);
		}

		function updateControl() {
			var pauseLabel;
			var resumeLabel;

			if (!control) {
				return;
			}

			pauseLabel = control.getAttribute('data-tz-pause-label') || root.getAttribute('data-tz-pause-label') || 'Pause headlines';
			resumeLabel = control.getAttribute('data-tz-resume-label') || root.getAttribute('data-tz-resume-label') || 'Resume headlines';
			control.textContent = manualPause ? resumeLabel : pauseLabel;
			control.setAttribute('aria-pressed', manualPause ? 'true' : 'false');
		}

		function setControlVisible(visible) {
			if (!control) {
				return;
			}

			control.hidden = !visible;
			control.setAttribute('aria-hidden', visible ? 'false' : 'true');
		}

		function startAnimation() {
			if (!animationId && shouldAnimate()) {
				animationId = requestFrame(frame);
			}
		}

		function frame(timestamp) {
			var elapsed;
			var maximum;
			var distance;

			animationId = 0;

			if (!shouldAnimate()) {
				lastFrame = 0;
				return;
			}

			if (!lastFrame) {
				lastFrame = timestamp;
			}

			elapsed = Math.min(0.1, (timestamp - lastFrame) / 1000);
			lastFrame = timestamp;
			maximum = list.scrollWidth - list.clientWidth;
			distance = speed * elapsed * direction;
			list.scrollLeft += distance;

			if (list.scrollLeft >= maximum) {
				list.scrollLeft = maximum;
				direction = -1;
			} else if (list.scrollLeft <= 0) {
				list.scrollLeft = 0;
				direction = 1;
			}

			animationId = requestFrame(frame);
		}

		function setStaticState(isStatic) {
			root.classList.toggle('tz-ticker-static', isStatic);
			list.classList.toggle('tz-ticker-static', isStatic);
			root.setAttribute('data-tz-ticker-state', isStatic ? 'static' : 'marquee');
			setControlVisible(!isStatic && hasOverflow() && 'marquee' === mode);

			if (isStatic) {
				cancelAnimation();
			} else if (shouldAnimate()) {
				startAnimation();
			}
		}

		function refreshState() {
			mobile = mobileQuery.matches;
			reducedMotion = reduceQuery.matches;
			setStaticState(mobile || reducedMotion || 'static' === mode || 'hidden' === mode);

			if ('hidden' === mode) {
				root.hidden = true;
			}
		}

		if (control) {
			updateControl();
			control.addEventListener('click', function () {
				manualPause = !manualPause;
				updateControl();

				if (manualPause) {
					cancelAnimation();
				} else {
					startAnimation();
				}
			});
		}

		list.addEventListener('mouseenter', function () {
			interactionPause = true;
			cancelAnimation();
		});

		list.addEventListener('mouseleave', function () {
			interactionPause = false;
			startAnimation();
		});

		list.addEventListener('focusin', function () {
			interactionPause = true;
			cancelAnimation();
		});

		list.addEventListener('focusout', function (event) {
			if (!event.relatedTarget || !list.contains(event.relatedTarget)) {
				interactionPause = false;
				startAnimation();
			}
		});

		list.addEventListener('touchstart', function () {
			interactionPause = true;
			cancelAnimation();
		}, { passive: true });

		list.addEventListener('touchend', function () {
			if (touchResumeId) {
				win.clearTimeout(touchResumeId);
			}

			touchResumeId = win.setTimeout(function () {
				interactionPause = false;
				startAnimation();
			}, 1600);
		});

		list.addEventListener('wheel', function () {
			interactionPause = true;
			cancelAnimation();

			if (touchResumeId) {
				win.clearTimeout(touchResumeId);
			}

			touchResumeId = win.setTimeout(function () {
				interactionPause = false;
				startAnimation();
			}, 1200);
		}, { passive: true });

		doc.addEventListener('visibilitychange', function () {
			documentVisible = !doc.hidden;

			if (documentVisible) {
				startAnimation();
			} else {
				cancelAnimation();
			}
		});

		win.addEventListener('resize', refreshState);

		if (reduceQuery.addEventListener) {
			reduceQuery.addEventListener('change', refreshState);
		} else if (reduceQuery.addListener) {
			reduceQuery.addListener(refreshState);
		}

		if (mobileQuery.addEventListener) {
			mobileQuery.addEventListener('change', refreshState);
		} else if (mobileQuery.addListener) {
			mobileQuery.addListener(refreshState);
		}

		if (win.IntersectionObserver) {
			observer = new win.IntersectionObserver(function (entries) {
				if (entries[0]) {
					inView = entries[0].isIntersecting;
				}

				if (inView) {
					startAnimation();
				} else {
					cancelAnimation();
				}
			}, { threshold: [0, 0.01] });
			observer.observe(root);
		}

		refreshState();
	}

	function init() {
		var mastheads = doc.querySelectorAll('[data-tz-masthead], .tz-masthead');
		var tickerRoots = doc.querySelectorAll('[data-tz-ticker], .tz-trending');

		forEachNode(mastheads, initSearch);
		forEachNode(tickerRoots, initTicker);
	}

	if ('loading' === doc.readyState) {
		doc.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
