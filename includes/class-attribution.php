<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Attribution {

	const COOKIE_NAME        = 'epd_attribution';
	const LEGACY_COOKIE_NAME = 'epd_utm';
	const CARRIER_NAME       = 'epd_attribution';

	private static $utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
	private static $touch_keys = array(
		'utm_source',
		'normalized_source',
		'utm_medium',
		'utm_channel',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'attribution_method',
		'gclid',
		'gbraid',
		'wbraid',
		'msclkid',
		'ttclid',
		'fbclid',
	);

	public static function from_request() {
		$data = array();

		if ( isset( $_POST[ self::CARRIER_NAME ] ) && is_scalar( $_POST[ self::CARRIER_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw     = wp_unslash( $_POST[ self::CARRIER_NAME ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$decoded = json_decode( (string) $raw, true );
			if ( is_array( $decoded ) ) {
				$data = $decoded;
			}
		}

		if ( empty( $data ) ) {
			$data = self::legacy_data();
		}

		return self::sanitize( $data );
	}

	public static function utms( array $attribution ) {
		$touch = ! empty( $attribution['last'] ) && is_array( $attribution['last'] )
			? $attribution['last']
			: ( isset( $attribution['first'] ) && is_array( $attribution['first'] ) ? $attribution['first'] : array() );
		$utms  = array();

		foreach ( self::$utm_keys as $key ) {
			$utms[ $key ] = isset( $touch[ $key ] ) ? $touch[ $key ] : '';
		}

		return $utms;
	}

	public static function flatten( array $attribution ) {
		$flat  = array();
		$first = isset( $attribution['first'] ) && is_array( $attribution['first'] ) ? $attribution['first'] : array();
		$last  = isset( $attribution['last'] ) && is_array( $attribution['last'] ) ? $attribution['last'] : array();

		foreach ( array( 'first' => $first, 'last' => $last ) as $prefix => $touch ) {
			foreach ( self::$touch_keys as $key ) {
				if ( isset( $touch[ $key ] ) && $touch[ $key ] !== '' ) {
					$flat[ 'epd_attribution_' . $prefix . '_' . $key ] = $touch[ $key ];
				}
			}
		}

		foreach ( array( 'gclid', 'gbraid', 'wbraid', 'msclkid', 'ttclid', 'fbclid' ) as $key ) {
			$value = isset( $last[ $key ] ) && $last[ $key ] !== ''
				? $last[ $key ]
				: ( isset( $first[ $key ] ) ? $first[ $key ] : '' );
			if ( $value !== '' ) {
				$flat[ 'epd_attribution_' . $key ] = $value;
			}
		}

		foreach ( array( 'landing_url', 'landing_referrer', 'submission_url', 'submission_title' ) as $key ) {
			if ( isset( $attribution[ $key ] ) && $attribution[ $key ] !== '' ) {
				$flat[ 'epd_attribution_' . $key ] = $attribution[ $key ];
			}
		}

		return $flat;
	}

	public static function source_definitions() {
		$defaults = array(
			'google'     => array( 'family' => 'search', 'aliases' => array( 'google', 'googleads', 'google_ads', 'adwords' ) ),
			'bing'       => array( 'family' => 'search', 'aliases' => array( 'bing', 'microsoft', 'microsoft_ads' ) ),
			'yahoo'      => array( 'family' => 'search', 'aliases' => array( 'yahoo' ) ),
			'duckduckgo' => array( 'family' => 'search', 'aliases' => array( 'duckduckgo', 'duck_duck_go' ) ),
			'ecosia'     => array( 'family' => 'search', 'aliases' => array( 'ecosia' ) ),
			'instagram'  => array( 'family' => 'social', 'aliases' => array( 'instagram', 'ig', 'insta', 'instragram' ) ),
			'facebook'   => array( 'family' => 'social', 'aliases' => array( 'facebook', 'fb', 'meta', 'meta_ads' ) ),
			'youtube'    => array( 'family' => 'social', 'aliases' => array( 'youtube', 'youtu_be' ) ),
			'linkedin'   => array( 'family' => 'social', 'aliases' => array( 'linkedin', 'linked_in', 'lnkd_in' ) ),
			'tiktok'     => array( 'family' => 'social', 'aliases' => array( 'tiktok', 'tik_tok' ) ),
			'x'          => array( 'family' => 'social', 'aliases' => array( 'x', 'twitter', 't_co' ) ),
			'pinterest'  => array( 'family' => 'social', 'aliases' => array( 'pinterest' ) ),
			'threads'    => array( 'family' => 'social', 'aliases' => array( 'threads' ) ),
			'email'      => array( 'family' => 'email', 'aliases' => array( 'email', 'newsletter', 'mailchimp' ) ),
		);

		$definitions = apply_filters( 'epd_attribution_source_definitions', $defaults );
		$definitions = is_array( $definitions ) ? $definitions : $defaults;
		$allowed     = array( 'search', 'social', 'email', 'referral' );
		$sanitized   = array();

		foreach ( $definitions as $canonical => $definition ) {
			$canonical = sanitize_key( $canonical );
			if ( ! $canonical || ! is_array( $definition ) ) {
				continue;
			}

			$family = isset( $definition['family'] ) ? sanitize_key( $definition['family'] ) : '';
			if ( ! in_array( $family, $allowed, true ) ) {
				continue;
			}

			$aliases     = array();
			$raw_aliases = isset( $definition['aliases'] ) && is_array( $definition['aliases'] ) ? $definition['aliases'] : array();
			foreach ( $raw_aliases as $alias ) {
				if ( is_scalar( $alias ) ) {
					$alias = substr( sanitize_text_field( (string) $alias ), 0, 100 );
					if ( $alias !== '' ) {
						$aliases[] = $alias;
					}
				}
			}

			if ( ! empty( $aliases ) ) {
				$sanitized[ $canonical ] = array(
					'family'  => $family,
					'aliases' => array_slice( array_values( array_unique( $aliases ) ), 0, 50 ),
				);
			}
		}

		return $sanitized;
	}

	private static function legacy_data() {
		$legacy = array();

		if ( isset( $_POST['epd_utm'] ) && is_array( $_POST['epd_utm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$legacy = wp_unslash( $_POST['epd_utm'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( ! empty( $_COOKIE[ self::LEGACY_COOKIE_NAME ] ) && is_scalar( $_COOKIE[ self::LEGACY_COOKIE_NAME ] ) ) {
			$decoded = json_decode( wp_unslash( $_COOKIE[ self::LEGACY_COOKIE_NAME ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$legacy  = is_array( $decoded ) ? $decoded : array();
		}

		if ( empty( $legacy ) ) {
			return array();
		}

		$legacy['attribution_method'] = 'legacy_utm';
		return array( 'first' => $legacy, 'last' => $legacy );
	}

	private static function sanitize( array $data ) {
		$sanitized = array();

		foreach ( array( 'first', 'last' ) as $touch_name ) {
			if ( empty( $data[ $touch_name ] ) || ! is_array( $data[ $touch_name ] ) ) {
				continue;
			}

			$touch = array();
			foreach ( self::$touch_keys as $key ) {
				if ( ! isset( $data[ $touch_name ][ $key ] ) || ! is_scalar( $data[ $touch_name ][ $key ] ) ) {
					continue;
				}
				$value = substr( sanitize_text_field( (string) $data[ $touch_name ][ $key ] ), 0, 500 );
				if ( $value !== '' ) {
					$touch[ $key ] = $value;
				}
			}

			if ( ! empty( $touch ) ) {
				$sanitized[ $touch_name ] = $touch;
			}
		}

		foreach ( array( 'landing_url', 'landing_referrer', 'submission_url' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				$value = substr( esc_url_raw( (string) $data[ $key ] ), 0, 2048 );
				if ( $value !== '' ) {
					$sanitized[ $key ] = $value;
				}
			}
		}

		if ( isset( $data['submission_title'] ) && is_scalar( $data['submission_title'] ) ) {
			$sanitized['submission_title'] = substr( sanitize_text_field( (string) $data['submission_title'] ), 0, 500 );
		}

		return $sanitized;
	}
}
