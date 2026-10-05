<?php
/**
 * E5 B4 Community Contribution integration tests.
 */
define( 'ABSPATH', __DIR__ . '/' );

function e5_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$service = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-service.php' );
$template = file_get_contents( dirname( __DIR__ ) . '/wordpress/themes/badaround-child/template-parts/single-event-layout.php' );
$js = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/assets/js/contributions.js' );
$css = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/assets/css/contributions.css' );

e5_assert( false !== strpos( $template, 'render_event_contribution_block' ), 'B4 invokes canonical contribution renderer' );
e5_assert( false === strpos( $template, 'ba_contributions' ), 'B4 template does not query private contribution table directly' );
e5_assert( false === strpos( $template, 'author_email' ), 'B4 template does not access reporter email' );

e5_assert( false !== strpos( $service, 'Sai qualcosa su questa segnalazione?' ), 'B4 shows approved E0 contribution CTA' );
e5_assert( false !== strpos( $service, 'Condividi un’informazione' ), 'B4 provides single primary contribution CTA' );
e5_assert( false !== strpos( $service, 'data-ba-contribution-form' ), 'E5 uses native badaround-core contribution form' );
e5_assert( false !== strpos( $service, 'rest_url( self::REST_NAMESPACE . self::REST_ROUTE )' ), 'native form submits to E1 REST contract' );
e5_assert( false !== strpos( $service, 'multipart/form-data' ), 'native form supports private image uploads' );
e5_assert( false !== strpos( $service, 'public_anonymous' ) && false !== strpos( $service, 'public_alias' ), 'identity modes are exposed without account requirement' );
e5_assert( false !== strpos( $service, 'visibility_requested' ) && false !== strpos( $service, 'reserved' ), 'public vs reserved contribution choice is exposed' );
e5_assert( false !== strpos( $service, 'Non seguire persone o veicoli' ), 'B4 includes safety microcopy' );
e5_assert( false !== strpos( $service, 'ba_contribution_status' ), 'verification status returns to B4 UX' );

e5_assert( false !== strpos( $service, 'BadAround_Contribution_Post_Type::POST_TYPE' ), 'B4 public list reads contribution public projections' );
e5_assert( false !== strpos( $service, "'post_parent' => $event_id" ), 'public projections are scoped to current event' );
e5_assert( false !== strpos( $service, "'post_status' => 'publish'" ), 'only published contribution projections are rendered' );
e5_assert( false !== strpos( $service, '_ba_public_display_name' ), 'public contribution identity uses approved projection metadata' );
e5_assert( false !== strpos( $service, '_ba_public_media_ids' ), 'public contribution media uses approved derivative IDs only' );

e5_assert( false !== strpos( $js, 'fetch(form.action' ), 'frontend submits asynchronously' );
e5_assert( false !== strpos( $js, 'FormData(form)' ), 'frontend preserves multipart upload payload' );
e5_assert( false !== strpos( $js, 'aria-expanded' ), 'CTA interaction exposes accessibility state' );
e5_assert( false !== strpos( $css, '@media(max-width:720px)' ), 'contribution UI has mobile layout rule' );

echo "E5 B4 integration tests complete.\n";
