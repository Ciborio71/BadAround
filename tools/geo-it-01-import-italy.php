<?php
/**
 * GEO-IT-01 — Offline, guarded and idempotent ISTAT municipality importer.
 *
 * Usage (from project root, with a local copy of ISTAT's official CSV):
 *   wp eval-file tools/geo-it-01-import-italy.php /absolute/path/Elenco-comuni-italiani.csv
 *   wp eval-file tools/geo-it-01-import-italy.php /absolute/path/Elenco-comuni-italiani.csv apply confirm-national-terms start=0 limit=100
 *
 * NEVER run --apply on staging or production without a separately approved
 * backup, reviewed preflight counts and reversible rollout plan.
 *
 * Data source:
 * https://www.istat.it/classificazione/codici-dei-comuni-delle-province-e-delle-regioni/
 * Official source is XLSX (2026-02-21). Convert the original workbook to
 * semicolon-separated UTF-8 CSV without modifying labels or ISTAT codes;
 * record the source workbook SHA256 and visually verify column headers.
 *
 * This script does not read Google Places results or modify the report schema.
 * It only adds missing ba_territorio terms and ISTAT identities. It never
 * deletes, renames, reparents or merges existing terms.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	throw new RuntimeException( 'GEO-IT-01 requires WP-CLI inside WordPress.' );
}
if ( ! taxonomy_exists( 'ba_territorio' ) ) {
	WP_CLI::error( 'Canonical ba_territorio taxonomy unavailable.' );
}
$arguments = isset( $args ) && is_array( $args ) ? $args : array();
$csv_path = isset( $arguments[0] ) ? (string) $arguments[0] : '';
$apply = in_array( 'apply', $arguments, true );
$start = null;
$batch_limit = null;
foreach ( $arguments as $argument ) {
  if ( preg_match( '/^start=(\\d+)$/', (string) $argument, $matches ) ) {
    $start = (int) $matches[1];
  } elseif ( preg_match( '/^limit=(\\d+)$/', (string) $argument, $matches ) ) {
    $batch_limit = (int) $matches[1];
  }
}
if ( $apply && ( null === $start || null === $batch_limit || $batch_limit < 1 || $batch_limit > 100 || $start < 0 || $start >= 7894 ) ) {
  WP_CLI::error( 'Apply must specify positional start=N (0-7893) and limit=N (1-100).' );
}
if ( ! $csv_path || ! is_file( $csv_path ) || ! is_readable( $csv_path ) ) {
	WP_CLI::error( 'Pass an existing readable local ISTAT CSV file.' );
}
$expected_csv_sha256 = 'd33642d067b70060dd4e03a8504742e784908591e1e54b67cd7e486daeeb50f7';
if ( ! hash_equals( $expected_csv_sha256, hash_file( 'sha256', $csv_path ) ) ) {
	WP_CLI::error( 'Unapproved or modified CSV; no changes performed.' );
}
if ( $apply && 'staging.badaround.it' !== wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
	WP_CLI::error( 'GEO-IT-01 apply is restricted to staging.badaround.it.' );
}
if ( $apply && ! in_array( 'confirm-national-terms', $arguments, true ) ) {
	WP_CLI::error( 'Apply requires positional confirm-national-terms after reviewing dry-run and backup.' );
}
$handle = fopen( $csv_path, 'rb' );
if ( ! $handle ) {
	WP_CLI::error( 'CSV cannot be opened.' );
}
function geo_it_01_decode( $value ) {
	$value = preg_replace( '/^\\xEF\\xBB\\xBF/', '', trim( (string) $value ) );
	if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
		$value = iconv( 'Windows-1252', 'UTF-8//IGNORE', $value );
	}
	return trim( (string) $value );
}
function geo_it_01_header( $value ) {
	$value = geo_it_01_decode( $value );
	$value = mb_strtolower( preg_replace( '/\\s+/u', ' ', $value ), 'UTF-8' );
	return trim( $value );
}
$raw_headers = fgetcsv( $handle, 0, ';', '"', '\\' );
if ( ! is_array( $raw_headers ) ) {
	WP_CLI::error( 'CSV headers missing.' );
}
$headers = array_map( 'geo_it_01_header', $raw_headers );
$find_header = static function ( $needles ) use ( $headers ) {
	foreach ( $needles as $needle ) {
		$needle = geo_it_01_header( $needle );
		foreach ( $headers as $index => $value ) {
			if ( false !== mb_strpos( $value, $needle ) ) {
				return $index;
			}
		}
	}
	return null;
};
$indices = array(
	'region_code' => $find_header( array( 'codice regione' ) ),
	'region'      => $find_header( array( 'denominazione regione', 'denominazione (italiana) regione' ) ),
	'province_code' => $find_header( array( 'codice unità territoriale sovracomunale', 'codice dell’unità territoriale sovracomunale', 'codice dell\'unità territoriale sovracomunale', 'codice provincia' ) ),
	'province'    => $find_header( array( 'denominazione dell’unità territoriale sovracomunale', 'denominazione dell\'unità territoriale sovracomunale', 'denominazione provincia' ) ),
	'municipality_code' => $find_header( array( 'codice comune formato alfanumerico', 'codice comune formato numerico', 'codice comune' ) ),
	'municipality' => $find_header( array( 'denominazione in italiano', 'denominazione (italiana) del comune', 'denominazione comune' ) ),
);
foreach ( $indices as $field => $index ) {
	if ( null === $index ) {
		WP_CLI::error( 'Official CSV missing required column: ' . $field );
	}
}
$rows = array();
$codes = array();
while ( ( $cells = fgetcsv( $handle, 0, ';', '"', '\\' ) ) !== false ) {
	if ( ! array_filter( $cells, static function ( $cell ) { return '' !== trim( (string ) $cell ); } ) ) {
		continue;
	}
	$row = array();
	foreach ( $indices as $key => $index ) {
		$row[ $key ] = geo_it_01_decode( isset( $cells[ $index ] ) ? $cells[ $index ] : '' );
	}
	if ( ! preg_match( '/^\\d{6}$/', $row['municipality_code'] ) || ! $row['region'] || ! $row['province'] || ! $row['municipality'] || ! $row['region_code'] || ! $row['province_code'] ) {
		WP_CLI::error( 'Malformed ISTAT row at municipality: ' . $row['municipality_code'] );
	}
	if ( isset( $codes[ $row['municipality_code'] ] ) ) {
		WP_CLI::error( 'Duplicate ISTAT municipality identity: ' . $row['municipality_code'] );
	}
	$codes[ $row['municipality_code'] ] = true;
	$rows[] = $row;
}
fclose( $handle );
if ( 7894 !== count( $rows ) ) {
	WP_CLI::error( 'Expected 7894 active municipalities at the approved 2026-02-21 ISTAT baseline; parsed ' . count( $rows ) );
}
WP_CLI::log( 'ISTAT_SOURCE_SHA256=' . hash_file( 'sha256', $csv_path ) );
WP_CLI::log( 'ISTAT_ACTIVE_MUNICIPALITIES=' . count( $rows ) );
$pilot = array( array( 'Lazio', 0, 2 ), array( 'Roma', 2, 3 ), array( 'Pomezia', 3, 4 ), array( 'Torvaianica', 4, 5 ) );
foreach ( $pilot as $expected ) {
  $term = get_term( $expected[2], 'ba_territorio' );
  if ( ! $term || is_wp_error( $term ) || $term->name !== $expected[0] || (int) $term->parent !== $expected[1] ) {
    WP_CLI::error( 'Certified pilot taxonomy identity changed; no import permitted.' );
  }
}
WP_CLI::log( 'PILOT_PATH_IDS=2>3>4>5' );
$tax = 'ba_territorio';
$created = array( 'region' => 0, 'province' => 0, 'municipality' => 0 );
$existing = array( 'region' => 0, 'province' => 0, 'municipality' => 0 );
$planned = array();
$by_parent = array();
$find = static function ( $name, $parent ) use ( $tax, &$by_parent ) {
	$key = $parent . '|' . $name;
	if ( array_key_exists( $key, $by_parent ) ) {
		return $by_parent[ $key ];
	}
	$found = get_terms( array( 'taxonomy' => $tax, 'parent' => $parent, 'hide_empty' => false, 'name' => $name ) );
	if ( is_wp_error( $found ) ) {
		WP_CLI::error( 'Cannot inspect canonical terms: ' . $name );
	}
	$exact = array_values( array_filter( $found, static function ( $term ) use ( $parent, $name ) {
		return $term->name === $name && (int) $term->parent === $parent;
	} ) );
	if ( count( $exact ) > 1 ) {
		WP_CLI::error( 'Ambiguous existing territory: ' . $name );
	}
	$by_parent[ $key ] = $exact ? (int) $exact[0]->term_id : 0;
	return $by_parent[ $key ];
};
$resolve = static function ( $name, $parent, $kind, $istat_code ) use ( $tax, $apply, &$find, &$created, &$existing, &$planned, &$by_parent ) {
	$id = $find( $name, $parent );
	if ( $id ) {
		$existing[ $kind ]++;
		$current_code = get_term_meta( $id, '_ba_istat_' . $kind . '_code', true );
		if ( $current_code && (string) $current_code !== (string) $istat_code ) {
			WP_CLI::error( 'ISTAT code collision on existing term: ' . $name );
		}
		if ( $apply && ! $current_code ) {
			add_term_meta( $id, '_ba_istat_' . $kind . '_code', (string) $istat_code, true );
		}
		return $id;
	}
	$planned[ $kind . '|' . $parent . '|' . $name ] = true;
	if ( ! $apply ) {
		return -1; // Missing parent in dry-run; counts are only indicative.
	}
	$result = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
	if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
		WP_CLI::error( 'Cannot create canonical ' . $kind . ': ' . $name );
	}
	$id = (int) $result['term_id'];
	$by_parent[ $parent . '|' . $name ] = $id;
	add_term_meta( $id, '_ba_istat_' . $kind . '_code', (string) $istat_code, true );
	$created[ $kind ]++;
	return $id;
};
if ( ! $apply ) {
	// Safe read-only preview: count current inventory; no negative-parent lookups.
	$existing_terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $existing_terms ) ) { WP_CLI::error( 'Unable to enumerate existing territory.' ); }
	WP_CLI::log( 'MODE=DRY_RUN; existing_territory_terms=' . count( $existing_terms ) );
	WP_CLI::log( 'Proposed coverage: ' . count( $rows ) . ' ISTAT municipalities, without deleting or renaming existing terms.' );
	WP_CLI::log( 'Apply is deliberately a separate approval gate; no WordPress mutations performed.' );
	return;
}
$last = min( count( $rows ), $start + $batch_limit );
$processed = 0;
$started_at = microtime( true );
for ( $i = $start; $i < $last; ++$i ) {
  // Stop voluntarily before the 60-second WP-CLI execution ceiling.
  if ( $processed > 0 && microtime( true ) - $started_at > 35 ) {
    break;
  }
  $row = $rows[$i];
	$region_id = $resolve( $row['region'], 0, 'region', $row['region_code'] );
	$province_id = $resolve( $row['province'], $region_id, 'province', $row['province_code'] );
	$resolve( $row['municipality'], $province_id, 'municipality', $row['municipality_code'] );
  ++$processed;
}
WP_CLI::success( 'GEO_IT_01_BATCH=' . wp_json_encode( array( 'start' => $start, 'processed' => $processed, 'next_start' => $start + $processed, 'total' => count($rows), 'created' => $created, 'existing' => $existing ) ) );
