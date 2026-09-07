<?php
/**
 * Title: Review Verdict
 * Slug: techzei-magazine/review-verdict
 * Categories: text
 * Keywords: review, verdict, rating
 * Description: A compact, accessible verdict callout for product reviews.
 * Viewport Width: 600
 */
?>
<!-- wp:group {"className":"tz-review-verdict","layout":{"type":"constrained"}} -->
<div class="wp-block-group tz-review-verdict">
<!-- wp:paragraph {"className":"tz-section-kicker"} -->
<p class="tz-section-kicker"><?php esc_html_e( 'Review verdict', 'techzei-magazine-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Our verdict', 'techzei-magazine-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"tz-review-verdict-summary"} -->
<p class="tz-review-verdict-summary"><?php esc_html_e( 'A concise summary of what this product does well, where it falls short, and who should buy it.', 'techzei-magazine-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"tz-review-verdict-score"} -->
<p class="tz-review-verdict-score"><strong><?php esc_html_e( 'Score', 'techzei-magazine-theme' ); ?>:</strong> <span aria-label="<?php esc_attr_e( 'Score out of five', 'techzei-magazine-theme' ); ?>">—/5</span></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
