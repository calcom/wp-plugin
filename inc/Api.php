<?php

namespace CalCom;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal Cal.com API v2 client using WordPress HTTP API.
 */
class Api {

	const BASE_URL    = 'https://api.cal.com/v2/';
	const API_VERSION = '2026-06-12';

	private $api_key;

	public function __construct( $api_key = null ) {
		$this->api_key = $api_key;
	}

	/**
	 * GET /event-types — returns meeting types
	 */
	public function get_event_types() {
		$result = $this->get( '/event-types' );

		if ( ! $result['success'] ) {
			return array(
				'success'     => false,
				'event_types' => array(),
				'error'       => $result['error'] ?? __( 'Failed to retrieve event types.', 'cal-com' ),
			);
		}

		$data = $result['data'];
		if ( ! is_array( $data ) ) {
			return array(
				'success'     => false,
				'event_types' => array(),
				'error'       => __( 'Unexpected API response format.', 'cal-com' ),
			);
		}

		return array(
			'success'     => true,
			'event_types' => $this->extract_event_types( $data ),
			'error'       => null,
		);
	}

	/**
	 * Extract event types from the nested eventTypeGroups structure.
	 */
	private function extract_event_types( $data ) {
		$event_types = array();

		if ( isset( $data['eventTypeGroups'] ) && is_array( $data['eventTypeGroups'] ) ) {
			foreach ( $data['eventTypeGroups'] as $group ) {
				$profile_slug = isset( $group['profile']['slug'] ) ? (string) $group['profile']['slug'] : '';
				$group_events = isset( $group['eventTypes'] ) ? $group['eventTypes'] : array();

				if ( ! is_array( $group_events ) ) {
					continue;
				}

				foreach ( $group_events as $et ) {
					if ( ! is_array( $et ) || ! isset( $et['id'] ) ) {
						continue;
					}

					if ( ! isset( $et['bookingUrl'] ) && isset( $et['slug'] ) && $profile_slug ) {
						$et['bookingUrl'] = 'https://cal.com/' . $profile_slug . '/' . $et['slug'];
					}

					$event_types[] = $et;
				}
			}
		} elseif ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['id'] ) ) {
			foreach ( $data as $et ) {
				$event_types[] = $et;
			}
		}

		return $event_types;
	}

	/**
	 * Make an authenticated GET request to the Cal.com API.
	 */
	private function get( $endpoint ) {
		if ( empty( $this->api_key ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'API key is missing.', 'cal-com' ),
			);
		}

		$url = self::BASE_URL . ltrim( $endpoint, '/' );

		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array(
					'Authorization'   => 'Bearer ' . $this->api_key,
					'cal-api-version' => self::API_VERSION,
				),
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			return array(
				'success' => false,
				'data'    => null,
				// translators: %d is the HTTP status code from the API request.
				'error'   => sprintf( __( 'API request failed with HTTP status %d.', 'cal-com' ), $status_code ),
			);
		}

		$parsed = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || $parsed === null ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Invalid JSON response from API.', 'cal-com' ),
			);
		}

		if ( isset( $parsed['status'] ) && $parsed['status'] === 'error' ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => isset( $parsed['error'] ) ? $parsed['error'] : __( 'Cal.com API returned an error.', 'cal-com' ),
			);
		}

		return array(
			'success' => true,
			'data'    => isset( $parsed['data'] ) ? $parsed['data'] : null,
			'error'   => null,
		);
	}
}
