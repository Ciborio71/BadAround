<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Transactional_Mailer {
	const TURBOSMTP_API_ENDPOINT = 'https://api.turbo-smtp.com/api/v2/mail/send';

	public function send( $recipient, $subject, $message, $html_message = '' ) {
		$consumer_key    = defined( 'BADAROUND_TURBOSMTP_CONSUMER_KEY' ) ? trim( (string) BADAROUND_TURBOSMTP_CONSUMER_KEY ) : '';
		$consumer_secret = defined( 'BADAROUND_TURBOSMTP_CONSUMER_SECRET' ) ? trim( (string) BADAROUND_TURBOSMTP_CONSUMER_SECRET ) : '';
		$from_email      = defined( 'BADAROUND_TURBOSMTP_FROM_EMAIL' ) ? sanitize_email( BADAROUND_TURBOSMTP_FROM_EMAIL ) : '';
		$recipient       = sanitize_email( $recipient );

		if ( '' === $consumer_key || '' === $consumer_secret || ! $from_email || ! is_email( $from_email ) || ! $recipient || ! is_email( $recipient ) ) {
			return new WP_Error( 'ba_turbosmtp_not_configured', 'Transactional email transport is not configured.' );
		}

		$response = wp_remote_post(
			self::TURBOSMTP_API_ENDPOINT,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Accept'         => 'application/json',
					'Consumerkey'    => $consumer_key,
					'Consumersecret' => $consumer_secret,
					'Content-Type'   => 'application/json',
				),
				'body' => wp_json_encode(
					array(
						'from'         => $from_email,
						'to'           => $recipient,
						'subject'      => (string) $subject,
						'content'      => (string) $message,
						'html_content' => (string) $html_message,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ba_turbosmtp_request_failed', 'Transactional email request failed.' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'ba_turbosmtp_rejected', 'Transactional email provider rejected the request.', array( 'http_status' => $status ) );
		}

		return true;
	}
}
