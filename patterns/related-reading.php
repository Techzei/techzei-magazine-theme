<?php
/**
 * Title: Related Reading
 * Slug: techzei-magazine/related-reading
 * Categories: text
 * Keywords: related, reading, stories
 * Description: A related-reading module powered by Techzei's bounded editorial resolver.
 * Viewport Width: 600
 */
?>
<!-- wp:group {"className":"tz-related-reading","layout":{"type":"constrained"}} -->
<div class="wp-block-group tz-related-reading">
<!-- wp:paragraph {"className":"tz-section-kicker"} -->
<p class="tz-section-kicker"><?php esc_html_e( 'Keep reading', 'techzei-magazine-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Related reading', 'techzei-magazine-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[techzei_related_stories heading="0"]
<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
