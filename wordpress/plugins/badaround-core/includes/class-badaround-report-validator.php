<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authoritative server-side validation for BadAround canonical reports.
 */
class BadAround_Report_Validator {
	public function validate_and_normalize( $report ) {
		if ( ! is_array( $report ) ) {
			return new WP_Error( 'ba_report_invalid_payload', __( 'La segnalazione non è valida.', 'badaround-core' ) );
		}

		$report = $this->normalize( $report );
		$error  = $this->validate( $report );
		return is_wp_error( $error ) ? $error : $report;
	}

	public function normalize( $report ) {
		$input      = is_array( $report ) ? $report : array();
		$normalized = array();
		$schema_version = isset( $input['schema_version'] ) && is_scalar( $input['schema_version'] )
			? sanitize_text_field( (string) $input['schema_version'] )
			: '';
		$this->set_path( $normalized, 'schema_version', $schema_version );

		foreach ( BadAround_Report_Schema::fields() as $path => $definition ) {
			if ( 'schema_version' === $path || ! $this->has_path( $input, $path ) ) {
				continue;
			}

			$value = $this->get_path( $input, $path );
			switch ( $definition['type'] ) {
				case 'string':
					$value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
					break;
				case 'email':
					$value = is_scalar( $value ) ? sanitize_email( (string) $value ) : '';
					break;
				case 'phone':
					$value = is_scalar( $value ) ? preg_replace( '/[^0-9+().\s-]/u', '', (string) $value ) : '';
					$value = trim( (string) $value );
					break;
				case 'enum':
					$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
					break;
				case 'uuid':
					$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
					break;
				case 'date':
					$value = $this->normalize_date( $value );
					break;
				case 'time':
					$value = $this->normalize_time( $value );
					break;
				case 'number':
				case 'decimal':
					$value = is_numeric( $value ) ? (float) $value : $value;
					break;
				case 'bool':
					$value = $this->normalize_bool( $value );
					break;
				case 'plate':
					$value = is_scalar( $value )
						? strtoupper( preg_replace( '/[^A-Z0-9?]/i', '', (string) $value ) )
						: '';
					break;
				case 'array':
					if ( ! is_array( $value ) ) {
						$value = array();
					} else {
						$value = array_values(
							array_unique(
								array_filter(
									array_map(
										static function ( $item ) {
											return is_scalar( $item ) ? sanitize_key( (string) $item ) : '';
										},
										$value
									)
								)
							)
						);
					}
					break;
			}
			$this->set_path( $normalized, $path, $value );
		}

		/*
		 * Conditional values are server-authoritative. Stale/forged values for
		 * inactive branches are removed before persistence or public projection.
		 */
		foreach ( BadAround_Report_Schema::fields() as $path => $definition ) {
			$conditions = isset( $definition['conditions'] ) ? $definition['conditions'] : array();
			if ( $conditions && ! $this->conditions_match( $normalized, $conditions ) ) {
				$this->unset_path( $normalized, $path );
			}
		}

		return $normalized;
	}

	public function validate( $report ) {
		if ( ! is_array( $report ) ) {
			return new WP_Error( 'ba_report_invalid_payload', __( 'La segnalazione non è valida.', 'badaround-core' ) );
		}

		foreach ( BadAround_Report_Schema::fields() as $path => $definition ) {
			$conditions = isset( $definition['conditions'] ) ? $definition['conditions'] : array();
			$active     = empty( $conditions ) || $this->conditions_match( $report, $conditions );
			$required   = ! empty( $definition['required'] ) && $active;
			$present    = $this->has_meaningful_value( $report, $path );

			if ( $required && ! $present ) {
				return new WP_Error(
					'ba_report_required_field',
					sprintf( __( 'Dato obbligatorio mancante: %s', 'badaround-core' ), $path ),
					array( 'field' => $path )
				);
			}

			if ( ! $active || ! $present ) {
				continue;
			}

			$value = $this->get_path( $report, $path );
			$error = $this->validate_field( $path, $value, $definition );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}

		$category = $this->get_path( $report, 'event.category' );
		$subtype  = $this->get_path( $report, 'event.subtype' );
		$matrix   = BadAround_Report_Schema::category_subtypes();
		if ( ! isset( $matrix[ $category ] ) || ! in_array( $subtype, $matrix[ $category ], true ) ) {
			return new WP_Error(
				'ba_report_category_subtype_mismatch',
				__( 'Categoria e sottocategoria non sono compatibili.', 'badaround-core' ),
				array( 'field' => 'event.subtype' )
			);
		}

		$lat_present = $this->has_meaningful_value( $report, 'location.exact_lat' );
		$lng_present = $this->has_meaningful_value( $report, 'location.exact_lng' );
		if ( $lat_present xor $lng_present ) {
			return new WP_Error(
				'ba_report_incomplete_coordinates',
				__( 'Latitudine e longitudine devono essere fornite insieme.', 'badaround-core' ),
				array( 'field' => 'location.exact_lat' )
			);
		}

		$time_error = $this->validate_time_rules( $report );
		if ( is_wp_error( $time_error ) ) {
			return $time_error;
		}

		$reward_error = $this->validate_reward_rules( $report );
		if ( is_wp_error( $reward_error ) ) {
			return $reward_error;
		}

		foreach ( array(
			'consents.truthfulness',
			'consents.media_rights',
			'consents.publication_rules',
			'consents.terms',
			'consents.privacy',
		) as $consent_path ) {
			if ( true !== $this->get_path( $report, $consent_path ) ) {
				return new WP_Error(
					'ba_report_consent_required',
					sprintf( __( 'Conferma obbligatoria mancante: %s', 'badaround-core' ), $consent_path ),
					array( 'field' => $consent_path )
				);
			}
		}

		return true;
	}

	private function validate_field( $path, $value, $definition ) {
		$type        = $definition['type'];
		$constraints = isset( $definition['constraints'] ) ? $definition['constraints'] : array();

		switch ( $type ) {
			case 'uuid':
				if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value ) ) {
					return $this->field_error( 'ba_report_invalid_uuid', $path, __( 'Identificatore submission non valido.', 'badaround-core' ) );
				}
				break;
			case 'email':
				if ( ! is_email( $value ) ) {
					return $this->field_error( 'ba_report_invalid_email', $path, __( 'Indirizzo email non valido.', 'badaround-core' ) );
				}
				break;
			case 'phone':
				if ( '' !== $value && ! preg_match( '/^[0-9+().\s-]{6,64}$/u', (string) $value ) ) {
					return $this->field_error( 'ba_report_invalid_phone', $path, __( 'Numero di telefono non valido.', 'badaround-core' ) );
				}
				break;
			case 'date':
				if ( ! $this->valid_date( $value ) ) {
					return $this->field_error( 'ba_report_invalid_date', $path, __( 'Data non valida.', 'badaround-core' ) );
				}
				break;
			case 'time':
				if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $value ) ) {
					return $this->field_error( 'ba_report_invalid_time', $path, __( 'Orario non valido.', 'badaround-core' ) );
				}
				break;
			case 'number':
			case 'decimal':
				if ( ! is_numeric( $value ) ) {
					return $this->field_error( 'ba_report_invalid_number', $path, __( 'Valore numerico non valido.', 'badaround-core' ) );
				}
				break;
			case 'bool':
				if ( ! is_bool( $value ) ) {
					return $this->field_error( 'ba_report_invalid_boolean', $path, __( 'Valore booleano non valido.', 'badaround-core' ) );
				}
				break;
			case 'array':
				if ( ! is_array( $value ) ) {
					return $this->field_error( 'ba_report_invalid_array', $path, __( 'Elenco non valido.', 'badaround-core' ) );
				}
				break;
			case 'plate':
				if ( ! preg_match( '/^[A-Z0-9?]{1,32}$/', (string) $value ) ) {
					return $this->field_error( 'ba_report_invalid_plate', $path, __( 'Targa non valida.', 'badaround-core' ) );
				}
				break;
		}

		if ( isset( $constraints['enum'] ) && ! in_array( $value, $constraints['enum'], true ) ) {
			return $this->field_error( 'ba_report_invalid_enum', $path, __( 'Valore non consentito.', 'badaround-core' ) );
		}
		if ( isset( $constraints['item_enum'] ) && is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! in_array( $item, $constraints['item_enum'], true ) ) {
					return $this->field_error( 'ba_report_invalid_enum', $path, __( 'Valore dell’elenco non consentito.', 'badaround-core' ) );
				}
			}
		}

		if ( is_string( $value ) ) {
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			if ( isset( $constraints['min_length'] ) && $length < (int) $constraints['min_length'] ) {
				return $this->field_error( 'ba_report_value_too_short', $path, __( 'Valore troppo breve.', 'badaround-core' ) );
			}
			if ( isset( $constraints['max_length'] ) && $length > (int) $constraints['max_length'] ) {
				return $this->field_error( 'ba_report_value_too_long', $path, __( 'Valore troppo lungo.', 'badaround-core' ) );
			}
		}

		if ( is_numeric( $value ) ) {
			if ( isset( $constraints['min'] ) && (float) $value < (float) $constraints['min'] ) {
				return $this->field_error( 'ba_report_value_too_small', $path, __( 'Valore inferiore al minimo consentito.', 'badaround-core' ) );
			}
			if ( isset( $constraints['max'] ) && (float) $value > (float) $constraints['max'] ) {
				return $this->field_error( 'ba_report_value_too_large', $path, __( 'Valore superiore al massimo consentito.', 'badaround-core' ) );
			}
		}

		if ( is_array( $value ) && isset( $constraints['max_items'] ) && count( $value ) > (int) $constraints['max_items'] ) {
			return $this->field_error( 'ba_report_too_many_items', $path, __( 'Troppi elementi.', 'badaround-core' ) );
		}

		return true;
	}

	private function validate_time_rules( $report ) {
		$date = $this->get_path( $report, 'time.date' );
		if ( $date && $this->valid_date( $date ) ) {
			$today = wp_date( 'Y-m-d', current_time( 'timestamp' ) );
			if ( $date > $today ) {
				return $this->field_error( 'ba_report_future_date', 'time.date', __( 'La data dell’evento non può essere nel futuro.', 'badaround-core' ) );
			}

			if ( $date === $today ) {
				$now = wp_date( 'H:i', current_time( 'timestamp' ) );
				$exact = $this->get_path( $report, 'time.exact_time' );
				$start = $this->get_path( $report, 'time.range_start' );
				$end   = $this->get_path( $report, 'time.range_end' );
				foreach ( array( 'time.exact_time' => $exact, 'time.range_start' => $start, 'time.range_end' => $end ) as $path => $time ) {
					if ( $time && $time > $now ) {
						return $this->field_error( 'ba_report_future_time', $path, __( 'L’orario dell’evento non può essere nel futuro.', 'badaround-core' ) );
					}
				}
			}
		}

		$start = $this->get_path( $report, 'time.range_start' );
		$end   = $this->get_path( $report, 'time.range_end' );
		if ( $start && $end && $end < $start ) {
			return $this->field_error( 'ba_report_invalid_time_range', 'time.range_end', __( 'L’orario finale non può precedere quello iniziale.', 'badaround-core' ) );
		}

		return true;
	}

	private function validate_reward_rules( $report ) {
		$status = $this->get_path( $report, 'reward.status' );
		if ( 'fixed' === $status ) {
			$amount = $this->get_path( $report, 'reward.amount' );
			if ( ! is_numeric( $amount ) || (float) $amount <= 0 ) {
				return $this->field_error( 'ba_report_reward_amount_required', 'reward.amount', __( 'Indicare un importo valido per la ricompensa.', 'badaround-core' ) );
			}
		}

		if ( in_array( $status, array( 'fixed', 'negotiable' ), true ) ) {
			if ( true !== $this->get_path( $report, 'reward.confirmed' ) ) {
				return $this->field_error( 'ba_report_reward_confirmation_required', 'reward.confirmed', __( 'La conferma relativa alla ricompensa è obbligatoria.', 'badaround-core' ) );
			}
		}
		return true;
	}

	private function conditions_match( $report, $groups ) {
		if ( empty( $groups ) || ! is_array( $groups ) ) {
			return false;
		}

		foreach ( $groups as $group ) {
			$matched = true;
			foreach ( $group as $rule ) {
				$actual = $this->get_path( $report, $rule['path'] );
				$target = $rule['value'];
				switch ( $rule['op'] ) {
					case 'eq':
						$ok = $actual === $target;
						break;
					case 'in':
						$ok = in_array( $actual, (array) $target, true );
						break;
					default:
						$ok = false;
				}
				if ( ! $ok ) {
					$matched = false;
					break;
				}
			}
			if ( $matched ) {
				return true;
			}
		}
		return false;
	}

	private function has_meaningful_value( $report, $path ) {
		if ( ! $this->has_path( $report, $path ) ) {
			return false;
		}
		$value = $this->get_path( $report, $path );
		if ( is_bool( $value ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}
		return '' !== trim( (string) $value );
	}

	private function get_path( $array, $path ) {
		$current = $array;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return null;
			}
			$current = $current[ $segment ];
		}
		return $current;
	}

	private function has_path( $array, $path ) {
		$current = $array;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return false;
			}
			$current = $current[ $segment ];
		}
		return true;
	}

	private function unset_path( &$array, $path ) {
		$segments = explode( '.', $path );
		$current  =& $array;
		foreach ( $segments as $index => $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return;
			}
			if ( $index === count( $segments ) - 1 ) {
				unset( $current[ $segment ] );
				return;
			}
			$current =& $current[ $segment ];
		}
	}

	private function set_path( &$array, $path, $value ) {
		$segments = explode( '.', $path );
		$current  =& $array;
		foreach ( $segments as $index => $segment ) {
			if ( $index === count( $segments ) - 1 ) {
				$current[ $segment ] = $value;
				return;
			}
			if ( ! isset( $current[ $segment ] ) || ! is_array( $current[ $segment ] ) ) {
				$current[ $segment ] = array();
			}
			$current =& $current[ $segment ];
		}
	}

	private function normalize_date( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
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
			return '';
		}
		$value = trim( (string) $value );
		if ( preg_match( '/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $value, $matches ) ) {
			return sprintf( '%02d:%02d', (int) $matches[1], (int) $matches[2] );
		}
		return $value;
	}

	private function normalize_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( in_array( $value, array( 1, '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( 0, '0', 'false', 'no', 'off', '', null ), true ) ) {
			return false;
		}
		return $value;
	}

	private function valid_date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	private function field_error( $code, $path, $message ) {
		return new WP_Error( $code, $message, array( 'field' => $path ) );
	}
}
