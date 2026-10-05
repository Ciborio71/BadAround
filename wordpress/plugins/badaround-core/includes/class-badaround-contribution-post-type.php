<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Post_Type {
	const POST_TYPE = 'ba_contributo';

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name' => __( 'Proiezioni contributi', 'badaround-core' ),
					'singular_name' => __( 'Proiezione contributo', 'badaround-core' ),
				),
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => false,
				'show_in_menu' => false,
				'show_in_rest' => false,
				'exclude_from_search' => true,
				'has_archive' => false,
				'rewrite' => false,
				'supports' => array( 'title', 'editor', 'author' ),
				'capability_type' => 'post',
				'map_meta_cap' => true,
			)
		);
	}
}
