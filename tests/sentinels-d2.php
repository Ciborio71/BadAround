<?php
/**
 * D2 static architecture tests. These intentionally inspect only Core source
 * contracts and do not need a WordPress installation or live provider.
 */
$root = dirname( __DIR__ );
function d2_read( $rel ) {
	global $root;
	$path = $root . '/' . $rel;
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "FAIL missing: {$rel}\n" );
		exit( 1 );
	}
	return file_get_contents( $path );
}
function d2_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$publication = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php' );
$contract    = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-discovery-contract.php' );
$discovery   = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php' );
$matching    = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-sentinel-matching-service.php' );
$matches     = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-sentinel-match-repository.php' );
$installer   = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-installer.php' );
$sentinels   = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-sentinel-repository.php' );
$mailer      = d2_read( 'wordpress/plugins/badaround-core/includes/class-badaround-transactional-mailer.php' );

$published_meta_pos = strpos( $publication, "update_post_meta( \$event_id, '_ba_moderation_status', self::STATUS_PUBLISHED )" );
$trigger_pos = strpos( $publication, "do_action( 'badaround_event_published', \$event_id )" );
d2_assert( false !== $published_meta_pos && false !== $trigger_pos && $trigger_pos > $published_meta_pos, 'D2 trigger occurs only after published moderation gate is committed' );

d2_assert( false !== strpos( $installer, 'UNIQUE KEY sentinel_event (sentinel_id,event_id)' ), 'database enforces UNIQUE Sentinel x Event' );
d2_assert( false !== strpos( $matches, 'INSERT IGNORE' ), 'duplicate match creation is idempotent at database write' );
d2_assert( false !== strpos( $matches, "notification_status IN (%s,%s)" ), 'notification worker uses conditional atomic claim' );

d2_assert( false !== strpos( $matching, "add_action( 'badaround_event_published'" ), 'matching listens only to explicit B3 application event' );
d2_assert( false !== strpos( $matching, 'BadAround_Discovery_Contract::event_matches_ids' ), 'matching delegates semantics to canonical Discovery Contract' );
d2_assert( false !== strpos( $discovery, 'BadAround_Discovery_Contract::tax_query_from_slugs' ), 'C0 query delegates taxonomy semantics to same contract' );
d2_assert( false !== strpos( $contract, "'include_children' => true" ), 'territory/category ancestor semantics are retained' );
d2_assert( false !== strpos( $contract, "'include_children' => false" ), 'specific event type remains exact' );

d2_assert( false !== strpos( $sentinels, 'self::STATUS_ACTIVE' ), 'only active sentinels are enumerated' );
d2_assert( false !== strpos( $sentinels, 'confirmed_at IS NOT NULL' ), 'only verified sentinels are enumerated' );
d2_assert( false !== strpos( $sentinels, 'disabled_at IS NULL' ) && false !== strpos( $sentinels, 'deleted_at IS NULL' ), 'disabled/deleted sentinels are excluded' );

d2_assert( false !== strpos( $matching, 'wp_schedule_single_event' ), 'matching and delivery are asynchronous via WP-Cron' );
d2_assert( false !== strpos( $matching, 'BadAround_Transactional_Mailer' ), 'D2 reuses shared D1 turboSMTP transport' );
d2_assert( false !== strpos( $mailer, 'wp_remote_post' ) && false !== strpos( $mailer, 'BADAROUND_TURBOSMTP_CONSUMER_SECRET' ), 'shared transport remains turboSMTP HTTP API' );

d2_assert( false !== strpos( $matching, 'MAX_ATTEMPTS     = 4' ), 'retry count is finite' );
d2_assert( false !== strpos( $matching, '5 * MINUTE_IN_SECONDS' ) && false !== strpos( $matching, '30 * MINUTE_IN_SECONDS' ) && false !== strpos( $matching, '2 * HOUR_IN_SECONDS' ), 'approved retry backoff is encoded' );
d2_assert( false !== strpos( $matching, 'event_not_public' ) && false !== strpos( $matching, 'sentinel_not_active' ), 'pre-send cancellation guards are present' );

foreach ( array( 'ba_reports', 'author_email', 'author_phone', 'exact_lat', 'exact_lng', 'full_plate', 'content_original' ) as $private_marker ) {
	d2_assert( false === strpos( $matching, $private_marker ), "D2 notification service does not access private marker {$private_marker}" );
}
d2_assert( false !== strpos( $matching, 'public_projection_for_event' ), 'notification content comes from canonical public projection' );
d2_assert( false !== strpos( $matching, "['permalink']" ), 'notification includes B4 public detail link' );

echo "D2 static architecture tests complete.\n";
