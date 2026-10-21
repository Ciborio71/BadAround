<?php
require __DIR__ . '/native-media-harness.php';
require_once $inc . 'class-badaround-report-v2-validator.php';
$checks = 0;
function mvcheck( $ok, $message ) { global $checks; ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } }
function mvcode( $r ) { return is_wp_error( $r ) ? $r->get_error_code() : ( $r['error']['code'] ?? 'success' ); }
function mvsession() { global $service; $uuid = BadAround_Native_Media_Config::new_uuid(); $s = $service->create( $uuid, BadAround_Native_Media_Capability::encode( random_bytes( 32 ) ), hash( 'sha256', $uuid ) ); mvcheck( ! is_wp_error( $s ), 'media session available' ); $s['submission_id'] = $uuid; return $s; }
function mvupload( $s ) { global $service; $im = imagecreatetruecolor( 2, 2 ); ob_start(); imagepng( $im ); $bytes = ob_get_clean(); $f = fopen( 'php://temp', 'w+b' ); fwrite( $f, $bytes ); rewind( $f ); try { $r = $service->upload( $s['media_session_id'], BadAround_Native_Media_Config::new_uuid(), $s['capability'], $f, 'synthetic.png', hash( 'sha256', $s['submission_id'] ) ); mvcheck( ! is_wp_error( $r ), 'verified private media upload' ); return $r['descriptor']; } finally { fclose( $f ); } }
function mvp( $s, $items, $version ) { $p = f13_fixture(); $p['submission_id'] = $s['submission_id']; $p['schema_version'] = $version; $p['media'] = array( 'availability' => 'yes', 'items' => $items ); return $p; }
function mvgold( $repo, $persistence = null ) { $persistence = $persistence ?: new BadAround_Report_Persistence_Service( $repo, new TestTax(), new TestTerritory(), new BadAround_Media_Repository(), new TestProjection() ); return new BadAround_Native_Report_Golden_Path_Service( new BadAround_Native_Report_Intake_Service( null, null, $repo ), $persistence, $repo ); }
$repo = new BadAround_Report_Repository();
foreach ( array( 'badaround-report/v1', 'badaround-report/v2' ) as $version ) {
	$s = mvsession(); $descriptor = mvupload( $s ); $p = mvp( $s, array( $descriptor ), $version );
	$canonical = BadAround_Report_Version_Registry::prepare( $p ); mvcheck( ! is_wp_error( $canonical ), 'both media payload versions validate' );
	$pin = $service->pin( $canonical, $s['capability'], $repo ); mvcheck( ! is_wp_error( $pin ) && $pin['state'] === 'pinned', 'both versions pin with unchanged ownership policy' );
	mvcheck( ! $repo->find_by_native_submission( $s['submission_id'] ), 'pin still precedes report reservation' );
	$other = mvp( $s, array(), $version === 'badaround-report/v1' ? 'badaround-report/v2' : 'badaround-report/v1' );
	$db->queries = array(); $cross = mvgold( $repo )->submit( $other ); mvcheck( mvcode( $cross ) === 'duplicate_submission', 'pre-report pinned intent rejects cross-version no-media' );
	foreach ( $db->queries as $q ) { mvcheck( strpos( $q, 'ba_native_media_' ) === false, 'no-media version guard never reads media ledger' ); }
	$linker = new BadAround_Native_Media_Linker( $ledger );
	$persist = new BadAround_Report_Persistence_Service( $repo, new TestTax(), new TestTerritory(), $linker, new TestProjection() );
	$adapter = new BadAround_Native_Media_Report_Adapter( $service, $repo, mvgold( $repo, new BadAround_Native_Media_Persistence( $service, $repo, $persist, $linker ) ) );
	$first = $adapter->submit( $p, $s['capability'] ); mvcheck( mvcode( $first ) === 'success', 'both media versions persist through production adapter' );
	$again = $adapter->submit( $p, $s['capability'] ); mvcheck( mvcode( $again ) === 'success' && $again['duplicate'] && $again['report_id'] === $first['report_id'] && $again['event_id'] === $first['event_id'], 'media receipt retry version/identity stable' );
	mvcheck( get_post_status( $first['event_id'] ) === 'pending', 'media event never auto-published' );
	mvcheck( mvcode( BadAround_Native_Media_Fence::check( $first['event_id'] ) ) === 'ba_native_media_publication_blocked', 'both versions remain fenced' );
	$stored = json_decode( $db->get_var( $db->prepare( 'SELECT content_original FROM test_ba_reports WHERE id=%d', $first['report_id'] ) ), true );
	mvcheck( $stored['schema_version'] === $version && $stored['canonical']['schema_version'] === $version && $stored['canonical']['media']['items'] === $canonical['media']['items'], 'media descriptors and markers preserved' );
	$bad = $p; $bad['media']['items'][0]['file_size']++; mvcheck( mvcode( $adapter->submit( $bad, $s['capability'] ) ) === 'media_manifest_conflict', 'pinned descriptor cannot change' );
}
echo "NF_UX_02B_MEDIA=PASS assertions={$checks} versions=2 pre_report_version_conflicts=2\n";
foreach ( array( $root, $public ) as $dir ) { $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $it as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); } rmdir( $dir ); }
