<?php
/**
 * GEO-PRIV-03A.5A: structural Classic Editor form isolation regression.
 * No WordPress installation or database changes.
 */
$source = file_get_contents( __DIR__ . '/../wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php' );
function verify_case( $condition, $name ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $name\n" ); exit(1); }
    echo "PASS: $name\n";
}
preg_match('/public function render_actions_box\(.*?\n\tpublic function render_independent_rejection_form\(/s', $source, $matches);
$box = $matches[0] ?? '';
verify_case( '' !== $box, 'moderation box found' );
verify_case( ! str_contains( $box, "<form" ), 'moderation metabox never emits nested form' );
verify_case( str_contains( $box, 'form="ba-reject-event-form" required' ), 'required reason belongs only to external reject form' );
verify_case( str_contains( $box, 'type="submit" form="ba-reject-event-form"' ), 'rejection button targets external form' );
verify_case( str_contains( $source, "add_action( 'admin_footer-post.php', array( \$this, 'render_independent_rejection_form' ) )" ), 'independent form renders outside post form' );
verify_case( str_contains( $source, "wp_nonce_field( 'ba_moderate_event_' . \$event_id )" ), 'reject-only form includes event-specific CSRF nonce' );
verify_case( str_contains( $source, "name=\"target\" value=" ), 'reject-only form has explicit moderation target' );
verify_case( str_contains( $source, "BadAround_Moderation_Service::STATUS_REJECTED" ), 'rejection target is restricted to rejected state' );
verify_case( str_contains( $source, "check_admin_referer( 'ba_moderate_event_' . \$event_id )" ), 'server handler checks rejection nonce' );
verify_case( str_contains( $source, "new BadAround_Moderation_Service() )->transition" ), 'server moderation service remains authoritative' );
verify_case( ! str_contains( $box, 'formnovalidate' ), 'no formnovalidate workaround' );
echo "GEO-PRIV-03A.5A structural checks complete.\n";
