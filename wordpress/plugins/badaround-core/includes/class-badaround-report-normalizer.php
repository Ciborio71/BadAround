<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deterministic normalizer for badaround-report/v1.
 * Reads normalization metadata from BadAround_Report_Schema only.
 */
class BadAround_Report_Normalizer {
	public function normalize( $report ) {
		if ( ! is_array( $report ) ) {
			return array();
		}

		$normalized = array();
		foreach ( BadAround_Report_Schema::fields() as $path => $definition ) {
			if ( ! $this->has_path( $report, $path ) ) {
				continue;
			}
			$value = $this->get_path( $report, $path );
			$value = $this->normalize_value( $path, $value, $definition );
			$this->set_path( $normalized, $path, $value );
		}

		// Inactive conditional fields are never carried into validated persistence.
		foreach ( BadAround_Report_Schema::fields() as $path => $definition ) {
			$conditions = isset( $definition['conditions'] ) ? $definition['conditions'] : array();
			if ( $conditions && ! $this->conditions_match( $normalized, $conditions ) ) {
				$this->unset_path( $normalized, $path );
			}
		}

		return $normalized;
	}

	private function normalize_value( $path, $value, $definition ) {
		$type = isset( $definition['type'] ) ? $definition['type'] : 'string';

		if ( 'media.items' === $path ) {
			if ( ! is_array( $value ) ) {
				return $value;
			}
			$out = array();
			foreach ( $value as $item ) {
				if ( ! is_array( $item ) ) {
					$out[] = $item;
					continue;
				}
				$out[] = array(
					'media_id' => isset( $item['media_id'] ) && is_scalar( $item['media_id'] ) ? trim( (string) $item['media_id'] ) : '',
					'mime_type' => isset( $item['mime_type'] ) && is_scalar( $item['mime_type'] ) ? strtolower( trim( (string) $item['mime_type'] ) ) : '',
					'file_size' => isset( $item['file_size'] ) && is_numeric( $item['file_size'] ) ? (int) $item['file_size'] : ( isset( $item['file_size'] ) ? $item['file_size'] : null ),
					'extension' => isset( $item['extension'] ) && is_scalar( $item['extension'] ) ? strtolower( ltrim( trim( (string) $item['extension'] ), '.' ) ) : '',
				);
			}
			return array_values( $out );
		}

		switch ( $type ) {
			case 'string':
				return is_scalar( $value ) ? sanitize_textarea_field( trim( (string) $value ) ) : $value;
			case 'email':
				return is_scalar( $value ) ? strtolower( sanitize_email( trim( (string) $value ) ) ) : $value;
			case 'phone':
				if ( ! is_scalar( $value ) ) {
					return $value;
				}
				return trim( preg_replace( '/[^0-9+().\s-]/u', '', (string) $value ) );
			case 'enum':
				return is_scalar( $value ) ? sanitize_key( strtolower( trim( (string) $value ) ) ) : $value;
			case 'uuid':
				return is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : $value;
			case 'date':
				return $this->normalize_date( $value );
			case 'time':
				return $this->normalize_time( $value );
			case 'number':
			case 'decimal':
				return is_numeric( $value ) ? (float) $value : $value;
			case 'bool':
				return $this->normalize_bool( $value );
			case 'plate':
				return is_scalar( $value ) ? strtoupper( preg_replace( '/[^A-Z0-9?]/i', '', trim( (string) $value ) ) ) : $value;
			case 'array':
				if ( ! is_array( $value ) ) {
					return $value;
				}
				$constraints = isset( $definition['constraints'] ) ? $definition['constraints'] : array();
				if ( isset( $constraints['item_enum'] ) ) {
					return array_values( array_unique( array_map( static function ( $item ) {
						return is_scalar( $item ) ? sanitize_key( strtolower( trim( (string) $item ) ) ) : $item;
					}, $value ) ) );
				}
				return array_values( $value );
		}
		return $value;
	}

	private function normalize_date( $value ) {
		if ( ! is_scalar( $value ) ) {
			return $value;
		}
		$value = trim( (string) $value );
		foreach ( array( 'Y-m-d', 'd/m/Y', 'd-m-Y' ) as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $value );
			if ( $date && $date->format( $format ) === $value ) {
				return $date->format( 'Y-m-d' );
			}
		}
		return $value;
	}

	private function normalize_time( $value ) {
		if ( ! is_scalar( $value ) ) {
			return $value;
		}
		$value = trim( (string) $value );
		if ( preg_match( '/\b([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\b/', $value, $matches ) ) {
			return sprintf( '%02d:%02d', (int) $matches[1], (int) $matches[2] );
		}
		return $value;
	}

	private function normalize_bool( $value ) {
		if ( is_bool( $value ) ) return $value;
		if ( in_array( $value, array( 1, '1', 'true', 'yes', 'on' ), true ) ) return true;
		if ( in_array( $value, array( 0, '0', 'false', 'no', 'off', '', null ), true ) ) return false;
		return $value;
	}

	private function conditions_match( $report, $groups ) {
		foreach ( (array) $groups as $group ) {
			$matched = true;
			foreach ( (array) $group as $rule ) {
				$actual = $this->get_path( $report, $rule['path'] );
				$ok = 'eq' === $rule['op'] ? $actual === $rule['value'] : ( 'in' === $rule['op'] && in_array( $actual, (array) $rule['value'], true ) );
				if ( ! $ok ) { $matched = false; break; }
			}
			if ( $matched ) return true;
		}
		return false;
	}

	private function get_path( $array, $path ) {
		$current = $array;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) return null;
			$current = $current[ $segment ];
		}
		return $current;
	}
	private function has_path( $array, $path ) {
		$current = $array;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) return false;
			$current = $current[ $segment ];
		}
		return true;
	}
	private function set_path( &$array, $path, $value ) {
		$current =& $array;
		$segments = explode( '.', $path );
		foreach ( $segments as $i => $segment ) {
			if ( $i === count( $segments ) - 1 ) { $current[ $segment ] = $value; return; }
			if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) $current[ $segment ] = array();
			$current =& $current[ $segment ];
		}
	}
	private function unset_path( &$array, $path ) {
		$current =& $array;
		$segments = explode( '.', $path );
		foreach ( $segments as $i => $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) return;
			if ( $i === count( $segments ) - 1 ) { unset( $current[ $segment ] ); return; }
			$current =& $current[ $segment ];
		}
	}
}
