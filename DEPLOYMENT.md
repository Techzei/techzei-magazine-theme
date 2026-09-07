# Production deployment checklist

Use this checklist when moving Techzei to a new release.

## Before activation

1. Back up the database and `wp-content/uploads`.
2. Confirm **Twenty Twenty-Five** is installed. This child theme depends on it but the parent theme does not need to be active.
3. Download the intended release ZIP and keep the previously active theme ZIP available for rollback.

## Activate

1. Go to **Appearance → Themes → Add New → Upload Theme**.
2. Upload the release ZIP and activate **Techzei Magazine Theme**.
3. Go to **Appearance → Editor → Patterns** and check Header, Footer, Topic navigation, and the article template parts.
4. Set the Site Logo and update the primary navigation links if needed.

## Clear inherited editor overrides

The old Valenti home-page assignment and Site Editor customizations can override files included with this theme.

1. Go to **Appearance → Editor → Templates**.
2. For Home, Front Page, Single Posts, and Archive templates, open the options menu and choose **Clear customizations** when it is available.
3. Edit the existing Home page and select the **Default** template.
4. Verify that the primary navigation contains only one row. Topic shortcuts should appear in the content rail, not under the header.
5. Open **Appearance → Techzei Settings** and review the behaviour defaults. The Restore action resets only Techzei’s option, not Site Editor customisations.

## 3.2.0 review

1. In **Appearance → Techzei Settings**, review the mobile discovery-sidebar and related-story selection controls.
2. Open **Read-only diagnostics** and inspect any saved template or template-part overrides before judging a theme-file change absent.
3. In the post editor, verify the article flow on both **Article with sidebar** and **Article without sidebar** templates. On mobile, sharing and author information should appear immediately after the story before optional discovery content.
4. Check the block inserter for the Review Verdict, Product Specifications, and Related Reading patterns. These are optional editor patterns and do not modify existing posts.

## Performance and visual checks

1. Regenerate thumbnails for the existing Media Library.
2. Clear WordPress object/page caches, hosting cache, and CDN cache.
3. Test the homepage, a post with a featured image, a post with no image, a topic archive, search results, and a 404 page.
4. Check at desktop and mobile widths. Verify the menu overlay, search, reading time, related stories, share links, footer RSS/social links, and comments.
5. Confirm images are served at their intended crop sizes after regeneration.

## Rollback

If a problem appears, activate the last working theme from **Appearance → Themes**, clear caches again, and restore the database backup only if settings or content were changed during the deployment.
