<?php
/**
 * Optional runtime checks for a WordPress test suite.
 *
 * These tests intentionally require WP_UnitTestCase. The repository's normal
 * quality gate remains dependency-free; see tests/README.md for setup.
 *
 * @package Techzei_TT5
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	return;
}

class Techzei_Editorial_Test extends WP_UnitTestCase {
	public function test_related_ids_are_cached_and_sidebar_excludes_in_flow_results() {
		$category = self::factory()->category->create( array( 'name' => 'How To', 'slug' => 'how-to' ) );
		$tag      = self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'iPhone' ) );
		$current  = self::factory()->post->create( array( 'post_category' => array( $category ), 'tags_input' => array( $tag ) ) );
		$related  = self::factory()->post->create_many( 5, array( 'post_category' => array( $category ), 'tags_input' => array( $tag ) ) );
		go_to( get_permalink( $current ) );
		techzei_tt5_invalidate_editorial_cache();
		$GLOBALS['techzei_tt5_editorial_query_count'] = 0;

		$first = techzei_tt5_related_story_ids( $current, 3 );
		$queries_after_first = techzei_tt5_editorial_query_count();
		$second = techzei_tt5_related_story_ids( $current, 3 );

		$this->assertSame( $first, $second );
		$this->assertGreaterThanOrEqual( 1, $queries_after_first );
		$this->assertSame( $queries_after_first, techzei_tt5_editorial_query_count() );
		$this->assertNotContains( $current, $first );
		$this->assertNotEmpty( array_intersect( $first, $related ) );

		techzei_tt5_related_stories();
		$topic_html = techzei_tt5_more_in_topic();
		foreach ( $first as $post_id ) {
			$this->assertStringNotContainsString( get_permalink( $post_id ), $topic_html );
		}
	}

	public function test_toc_ids_are_unique_and_match_rendered_headings() {
		$category = self::factory()->category->create( array( 'name' => 'How To', 'slug' => 'how-to' ) );
		$content  = '<h2>Repeat</h2><h3 id="manual">Repeat</h3><h2 id="manual">Café setup</h2><h3 id="bad id">⚙</h3>';
		$post_id  = self::factory()->post->create( array( 'post_category' => array( $category ), 'post_content' => $content ) );
		go_to( get_permalink( $post_id ) );
		$items = techzei_tt5_article_toc_items( $post_id );
		$ids   = wp_list_pluck( $items, 'id' );

		$this->assertCount( count( $ids ), array_unique( $ids ) );
		$this->assertSame( 'manual', $ids[1] );
		$this->assertSame( 'manual-2', $ids[2] );
		$this->assertNotSame( '', $ids[3] );

		$rendered = techzei_tt5_add_article_toc_ids( $content, array() );
		preg_match_all( '/<h[23][^>]*\bid="([^"]+)"/i', $rendered, $matches );
		$this->assertSame( $ids, $matches[1] );
	}

	public function test_settings_update_invalidates_discovery_namespace() {
		$before = techzei_tt5_editorial_cache_version();
		update_option( TECHZEI_TT5_SETTINGS_OPTION, techzei_tt5_settings_defaults() );
		$this->assertGreaterThan( $before, techzei_tt5_editorial_cache_version() );
	}

	public function test_featured_image_has_one_registered_default_processor() {
		$this->assertSame( 10, has_filter( 'render_block_core/post-featured-image', 'techzei_tt5_process_featured_image' ) );
		$this->assertFalse( has_filter( 'render_block_core/post-featured-image', 'techzei_tt5_prioritize_article_hero' ) );
		$this->assertFalse( has_filter( 'render_block_core/post-featured-image', 'techzei_tt5_ensure_featured_image_alt' ) );
		$this->assertFalse( has_filter( 'render_block_core/post-featured-image', 'techzei_tt5_optimize_card_images' ) );
	}

	public function test_legacy_shortcodes_remain_registered() {
		foreach ( array( 'column', 'alert', 'button', 'pullquote', 'hr', 'attention' ) as $shortcode ) {
			$this->assertArrayHasKey( $shortcode, $GLOBALS['shortcode_tags'] );
		}
	}
}
