<?php
$post_id = get_queried_object_id();

if ( $post_id && 'publish' !== get_post_status( $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	get_template_part( '404' );
	return;
}

get_header();
get_template_part( 'template-parts/single-event-layout' );
get_footer();
