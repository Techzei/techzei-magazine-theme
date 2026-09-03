=== Techzei Magazine Theme ===
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later

== Required Parent Theme ==
This is a child theme of Twenty Twenty-Five. Install the official Twenty Twenty-Five theme from WordPress.org before activating this theme. The parent does not need to be active.

== Installation ==
1. In Appearance > Themes, install Twenty Twenty-Five from WordPress.org.
2. Upload techzei-magazine-theme.zip and activate it.
3. Open Appearance > Editor to set the real Site Logo and edit navigation.
4. Review the Header, Footer, and Newsletter template parts in the Site Editor.
5. Regenerate thumbnails once for older Media Library images.
6. Clear all WordPress, host, and CDN caches.

== Version 2.6.0 ==

* Improve thumbnail loading with accurate responsive sizes and lazy loading for non-hero cards.
* Keep only the primary hero image eager-loaded.

== Version 2.5.7 ==

* Turn the trending rail into an auto-scrolling, scrollbar-free ticker.
* Pause the ticker for hover, keyboard focus, and reduced-motion preferences.

== Version 2.5.6 ==

* Disable the theme metadata fallback when Yoast SEO or another supported SEO provider is active.

== Version 2.5.5 ==

* Add meaningful fallback alt text to featured images missing attachment metadata.

== Version 2.5.4 ==

* Remove all theme-owned advertisement slots and placeholder ad styling.

== Version 2.5.3 ==

* Make the post author card explicit while retaining the core comments and share components.

== Version 2.5.2 ==

* Improve article H2 and H3 hierarchy with stronger type, scale, and spacing.

== Version 2.5.1 ==

* Prioritize single-post hero images so they load in the initial viewport.
* Reserve the hero aspect ratio before the image finishes loading.

== Version 2.5.0 ==

* Added server-rendered description, Open Graph, and Twitter Card metadata for homepage, posts, archives, and search pages.

== Version 2.4.1 ==

* Put the mobile search control on its own full-width row so its placeholder remains readable at 375px.

== Version 2.4.0 ==

* Added a lightweight, theme-owned mobile navigation fallback with keyboard and scroll-lock support.

== Version 2.3.0 ==

* Standardized the fallback and posts-page templates with the curated discovery sidebar.
* Cleaned the individual article hierarchy so the title is followed by metadata and the featured image.

== Version 2.2.0 ==

* Standardized article, page, archive, search, and blog-index presentation.
* Curated discovery navigation and removed the legacy full category dump from shared sidebars.
* Removed the article deck from between the title and featured image.

== Version 2.1.6 ==

* Removed the dangling header link to the retired newsletter section.

== Version 2.1.5 ==

* Cleaned responsive sizing, keyboard focus states, and long-content overflow handling.

== Version 2.1.4 ==

* Added responsive compatibility handlers for legacy pullquote, hr, and attention shortcodes.

== Version 2.1.3 ==

* Removed the placeholder newsletter section from the front page, archives, and articles until a real signup provider is connected.

== Version 2.1.2 ==

* Added a linked “Built by jashjacob.com” footer credit.

== Version 2.1.1 ==

* Updated the footer signature to “Proudly made in India” with the archived icon.

== Version 2.1.0 ==

* Added responsive compatibility handlers for legacy Valenti column, alert, and button shortcodes.

== Version 2.0.5 ==

* Replaced the text heart with Techzei’s original archived India PNG icon.

== Version 2.0.4 ==

* Added the linked “Made with love in India” footer signature.

== Version 2.0.3 ==

* Removed the duplicate text site title from the footer; the uploaded Techzei logo is now the sole footer brand mark.

== Version 2.0.2 ==

* Fixed the mobile Popular stories list so dates no longer collapse into the number column.

== Version 2.0.1 ==

* Restored the homepage topic navigation rail after the 2.0 template cleanup.

== Version 2.0.0 ==
This is a cohesive editorial rebuild rather than an incremental patch. It reorganizes the theme into setup and editorial PHP modules, replaces compressed CSS with a documented responsive layout system, redesigns the header, footer, newsletter, archives, search pages, discovery sidebar, and article components, and retains public /topics/ URLs, category labels, breadcrumbs, related stories, reading time, and server-rendered sharing.

After updating, visit Appearance > Editor > Templates. If the Home template does not change, open the template options menu and choose Clear customizations so WordPress uses the updated theme template. Also edit the existing Home page and set its Template to Default; this removes the legacy Valenti Builder assignment left in the database.

== Performance ==
The child theme adds no JavaScript, external font, icon library, page builder, tracker, or front-end framework. It inherits Twenty Twenty-Five’s locally hosted variable fonts, responsive blocks, image markup, accessibility behavior, and editor support. WordPress chooses responsive image sources and loading priority.

The homepage uses native Query, Latest Posts, Featured Image, Navigation, Search, Category, and Template Part blocks. This keeps the design editable and avoids a proprietary options framework.

== Editing ==
Use Appearance > Editor to change templates visually. Changes saved in the Site Editor override files in this child theme; use the Editor’s Clear customizations option if a future theme update does not appear.

The Newsletter template part intentionally contains instructions rather than a fake form. Replace that paragraph with the Form or Shortcode block supplied by your newsletter provider.

== Updating ==
Update Twenty Twenty-Five normally through WordPress. Techzei-specific layout changes remain in this child theme. Back up Site Editor customizations before major redesigns.
