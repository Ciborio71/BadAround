<?php
/** NF-UX-02C mandatory persistence boundary gate; isolated synthetic database only. */
require __DIR__ . '/native-media-harness.php';
require_once $inc . 'class-badaround-report-v2-validator.php';
$repo = new BadAround_Report_Repository();
$failures = 0;
foreach ( array( 'active_value', 'schema_version' ) as $case ) {
    $p = f13_fixture(); $p['schema_version'] = 'badaround-report/v2'; $p['submission_id'] = BadAround_Native_Media_Config::new_uuid();
    $canonical = BadAround_Report_Version_Registry::prepare( $p );
    if ( is_wp_error( $canonical ) ) { throw new RuntimeException( 'Invalid synthetic input' ); }
    $idem = new BadAround_Report_Idempotency_Service( $repo );
    $reservation = $idem->reserve( $canonical['submission_id'], $canonical );
    if ( is_wp_error( $reservation ) || ! $reservation['report_id'] ) { throw new RuntimeException( 'Reservation failed' ); }
    $id = $reservation['report_id'];
    $sql = $db->prepare( 'SELECT content_original FROM test_ba_reports WHERE id=%d', $id );
    $before = $db->get_var( $sql );
    $changed = $p;
    if ( 'active_value' === $case ) { $changed['content']['description'] = 'Different active synthetic content'; }
    else { $changed['schema_version'] = 'badaround-report/v1'; }
    $changed = BadAround_Report_Version_Registry::prepare( $changed );
    if ( is_wp_error( $changed ) ) { throw new RuntimeException( 'Changed input must be independently canonical-valid' ); }
    $accepted = $repo->persist_canonical_private_data( $id, $changed );
    $after = $db->get_var( $sql );
    $idem->release( $canonical['submission_id'] );
    $stored = json_decode( $after, true );
    $ok = $before === $after && false === $accepted;
    $result = array( 'case' => $case, 'write_accepted' => $accepted, 'snapshot_unchanged' => $before === $after,
        'before_sha256' => hash( 'sha256', $before ), 'after_sha256' => hash( 'sha256', $after ),
        'stored_version' => $stored['schema_version'], 'canonical_version' => $stored['canonical']['schema_version'],
        'invariant_pass' => $ok );
    echo json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n";
    if ( ! $ok ) { ++$failures; }
}
// This test never accesses staging, creates real media, or edits application sources.
foreach ( array( $root, $public ) as $dir ) { if ( is_dir( $dir ) ) { rmdir( $dir ); } }
echo 'NF_UX_02C_PERSISTENCE_GATE=' . ( $failures ? 'FAIL' : 'PASS' ) . ' violations=' . $failures . "\n";
exit( $failures ? 1 : 0 );
