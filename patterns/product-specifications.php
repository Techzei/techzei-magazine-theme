<?php
/**
 * Title: Product Specifications
 * Slug: techzei-magazine/product-specifications
 * Categories: text
 * Keywords: product, specifications, table
 * Description: A responsive product specification table with accessible headers.
 * Viewport Width: 600
 */
?>
<!-- wp:group {"className":"tz-product-specifications","layout":{"type":"constrained"}} -->
<div class="wp-block-group tz-product-specifications">
<!-- wp:paragraph {"className":"tz-section-kicker"} -->
<p class="tz-section-kicker"><?php esc_html_e( 'At a glance', 'techzei-magazine-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Product specifications', 'techzei-magazine-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"tz-product-specifications-scroll","ariaLabel":"<?php echo esc_attr__( 'Scrollable product specifications', 'techzei-magazine-theme' ); ?>","layout":{"type":"default"},"style":{"overflow":{"x":"auto"}}} -->
<div class="wp-block-group tz-product-specifications-scroll" style="overflow-x:auto">
<!-- wp:paragraph {"className":"screen-reader-text"} -->
<p class="screen-reader-text"><?php esc_html_e( 'On narrow screens, scroll horizontally to view all specifications.', 'techzei-magazine-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:table {"hasFixedLayout":false,"className":"tz-product-specifications-table"} -->
<figure class="wp-block-table tz-product-specifications-table"><table><thead><tr><th scope="col"><?php esc_html_e( 'Specification', 'techzei-magazine-theme' ); ?></th><th scope="col"><?php esc_html_e( 'Details', 'techzei-magazine-theme' ); ?></th></tr></thead><tbody><tr><td><?php esc_html_e( 'Model', 'techzei-magazine-theme' ); ?></td><td><?php esc_html_e( 'Add model or generation', 'techzei-magazine-theme' ); ?></td></tr><tr><td><?php esc_html_e( 'Dimensions', 'techzei-magazine-theme' ); ?></td><td><?php esc_html_e( 'Add dimensions and weight', 'techzei-magazine-theme' ); ?></td></tr><tr><td><?php esc_html_e( 'Highlights', 'techzei-magazine-theme' ); ?></td><td><?php esc_html_e( 'Add the specifications readers need most', 'techzei-magazine-theme' ); ?></td></tr></tbody></table></figure>
<!-- /wp:table -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
