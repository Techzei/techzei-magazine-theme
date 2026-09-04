# Changelog

## 3.1.1

- Rebuilt the 404 template as a focused editorial recovery page with a controlled responsive layout, branded signal graphic, accessible search action, and clear return-home link.

## 3.1.0

- Added Appearance → Techzei Settings with one versioned, capability-protected option and a scoped defaults reset.
- Added selectable Article with sidebar and Article without sidebar templates while preserving automatic legacy treatment and Site Editor ownership.
- Consolidated image role handling with safe `WP_HTML_Tag_Processor` attributes, responsive sizes, and no lazy/high-priority conflicts.
- Improved mobile logo/search interaction, sticky-header settings, controlled ticker pause/reduced-motion behaviour, and editor/frontend style parity.
- Connected article discovery, sharing, reading time, metadata, and homepage feed/headline behaviour to the central settings resolver.
- Removed the unused newsletter template part and corrected generic pages so comments remain post-only.

## 3.0.0

- Rename the distributable child theme to Techzei Magazine Theme.
- Align the WordPress folder name, package name, text domain, asset handles, and installation documentation.

## 2.9.0

- Defined the system and editorial font presets used by `theme.json`.
- Made mobile search visible when JavaScript is unavailable, while preserving the compact search toggle when JavaScript is active.
- Corrected the fallback homepage Open Graph URL for posts-index configurations.
- Registered reusable template parts for the Site Editor.
- Added RSS, X/Twitter, and YouTube links to the footer.
- Removed the unused breadcrumb template and refreshed package documentation.

## 2.6.0 — 2026-09-03

- Build an automatic legacy-post treatment for pre-relaunch and Valenti-shortcode articles.
- Restore the article-end sequence of share links, post navigation, author profile, related reading, and comments.
- Add a classic desktop sidebar with current stories and latest reviews.

## 2.5.12 — 2026-09-03

- Tone article H2 and H3 styling down to match Techzei's original understated post treatment while preserving useful heading structure.

## 2.5.11 — 2026-09-03

- Make legacy `column` first/last markers create a responsive grid row.
- Keep one-half, one-third, two-thirds, one-fourth, and three-fourths layouts intact on desktop and stack them on mobile.

## 2.5.10 — 2026-09-03

- Ensure Yoast does not leave the homepage document title as the bare site name.

## 2.5.9 — 2026-09-03

- Add correctly sized lazy thumbnails to related and latest-story lists.
- Prefer recent posts in the article sidebar and exclude the current article.
- Make article subheadings visibly bold and present the author section as a card.
- Add front-page document-title fallbacks for WordPress and Yoast.

## 2.5.8 — 2026-09-03

- Improve thumbnail loading with accurate responsive sizes and lazy loading for non-hero cards.
- Keep only the primary hero image eager-loaded.

## 2.5.7 — 2026-09-03

- Turn the trending rail into an auto-scrolling, scrollbar-free ticker.
- Pause the ticker for hover, keyboard focus, and reduced-motion preferences.

## 2.5.6 — 2026-09-03

- Disable the theme metadata fallback when Yoast SEO or another supported SEO provider is active.

## 2.5.5 — 2026-09-03

- Add meaningful fallback alt text to featured images missing attachment metadata.

## 2.5.4 — 2026-09-03

- Remove all theme-owned advertisement slots and placeholder ad styling.

## 2.5.3 — 2026-09-03

- Make the post author card explicit while retaining the core comments and share components.

## 2.5.2 — 2026-09-03

- Improve article H2 and H3 hierarchy with stronger type, scale, and spacing.

## 2.5.1 — 2026-09-03

- Prioritize single-post hero images with eager loading and high fetch priority.
- Reserve the hero aspect ratio to prevent blank space while the image loads.

## 2.5.0 — 2026-09-03

- Added server-rendered description, Open Graph, and Twitter Card metadata for homepage, posts, archives, and search pages.
- Social URLs use clean canonical paths without request query strings.

## 2.4.1 — 2026-09-03

- Put the mobile search control on its own full-width row so its placeholder remains readable at 375px.

## 2.4.0 — 2026-09-03

- Added a lightweight, theme-owned mobile navigation fallback with keyboard and scroll-lock support.
- Intercepts the core Navigation block button during capture to avoid double-toggle conflicts.

## 2.3.0 — 2026-09-03

- Standardized the fallback and posts-page templates with the curated discovery sidebar.
- Cleaned the individual article hierarchy so the title is followed by metadata and the featured image.

## 2.2.0 — 2026-09-03

- Standardized article, page, archive, search, and blog-index presentation.
- Curated discovery navigation and removed the legacy full category dump from shared sidebars.
- Removed the article deck from between the title and featured image.

## 2.1.6 — 2026-09-03

- Removed the dangling header link to the retired newsletter section.

## 2.1.5 — 2026-09-03

- Cleaned responsive sizing, keyboard focus states, and long-content overflow handling.
- Updated documentation to reflect the shortcode compatibility layer and removed placeholder newsletter templates.

## 2.1.4 — 2026-09-03

- Added responsive compatibility handlers for legacy pullquote, hr, and attention shortcodes.

## 2.1.3 — 2026-09-03

- Removed the placeholder newsletter section from the front page, archives, and articles until a real signup provider is connected.

## 2.1.2 — 2026-09-03

- Added a linked “Built by jashjacob.com” footer credit.

## 2.1.1 — 2026-09-03

- Updated the footer signature to “Proudly made in India” with the archived icon.

## 2.1.0 — 2026-09-03

- Added responsive compatibility handlers for legacy Valenti column, alert, and button shortcodes.
- Added safe handling for external button targets and legacy callout variants.

## 2.0.5 — 2026-09-03

- Replaced the text heart with Techzei’s original archived India PNG icon.

## 2.0.4 — 2026-09-03

- Added the linked “Made with love in India” footer signature.

## 2.0.3 — 2026-09-03

- Removed the duplicate text site title from the footer, keeping the uploaded Techzei logo as the sole brand mark.

## 2.0.2 — 2026-09-03

- Fixed the mobile Popular stories list so dates keep their own readable row.

## 2.0.1 — 2026-09-03

- Restored the homepage topic navigation rail after the 2.0 template cleanup.

## 2.0.0 — 2026-09-03

- Rebuilt global header, footer, newsletter, archive, and search components as a unified editorial system.
- Replaced compressed layout CSS with a readable responsive design system.
- Split theme bootstrapping, setup, and editorial rendering into focused PHP modules.
- Added the reusable discovery sidebar across archives and search.

## 1.0.16 — 2026-09-03

- Replaced the single-post sidebar’s generic latest-posts block with a lightweight list that excludes the current post.

## 1.0.15 — 2026-09-03

- Corrected editorial labels, breadcrumbs, and related stories to use Techzei's underlying WordPress categories while preserving public `/topics/` URLs.

## 1.0.14 — 2026-09-02

- Added a production deployment and rollback checklist.

## 1.0.13 — 2026-09-02

- Added a server-rendered reading-time estimate to post metadata.
- Limited post-specific shortcodes to single post views.

## 1.0.12 — 2026-09-02

- Added a server-rendered related-stories module based on the current post's `topics` terms.

## 1.0.11 — 2026-09-02

- Added a dedicated long-form article stylesheet for images, captions, tables, code, lists, and mobile reading.

## 1.0.10 — 2026-09-02

- Added GitHub-ready documentation and project structure notes.
- Documented Techzei’s public `/topics/` URL structure and theme update points.

## 1.0.9

- Replaced profile-style social links with real server-rendered post share links.

## 1.0.8

- Added breadcrumbs to individual posts.

## 1.0.7

- Added the native post-sharing row.

## 1.0.6

- Added editor controls for responsive type and spacing.
- Added modified-date metadata to posts.

## 1.0.5

- Moved topic shortcuts out of the header into homepage and archive rails.
- Aligned visible topic labels with Techzei’s live taxonomy.
