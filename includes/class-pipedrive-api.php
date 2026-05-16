<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Pipedrive_API {

	private $api_token;
	private $company_domain;
	private $base_url_v2;
	private $base_url_v1;

	public function __construct() {
		$this->api_token      = get_option( 'epd_api_token', '' );
		$this->company_domain = get_option( 'epd_company_domain', '' );
		$this->base_url_v2    = 'https://' . $this->company_domain . '/api/v2';
		$this->base_url_v1    = 'https://' . $this->company_domain . '/api/v1';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function request( $method, $url, $body = array() ) {
		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'x-api-token' => $this->api_token,
				'Content-Type' => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) && in_array( $args['method'], array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['error'] ) ? $data['error'] : "HTTP {$code}";
			return array( 'success' => false, 'error' => $message, 'code' => $code );
		}

		return array( 'success' => true, 'data' => $data );
	}

	private function get( $endpoint, $params = array(), $version = 'v2' ) {
		$base = $version === 'v1' ? $this->base_url_v1 : $this->base_url_v2;
		$url  = $base . $endpoint;

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		return $this->request( 'GET', $url );
	}

	private function post( $endpoint, $body = array(), $version = 'v2' ) {
		$base = $version === 'v1' ? $this->base_url_v1 : $this->base_url_v2;
		$url  = $base . $endpoint;

		return $this->request( 'POST', $url, $body );
	}

	public function is_configured() {
		return ! empty( $this->api_token ) && ! empty( $this->company_domain );
	}

	// -------------------------------------------------------------------------
	// Connection
	// -------------------------------------------------------------------------

	public function test_connection() {
		return $this->get( '/users/me' );
	}

	// -------------------------------------------------------------------------
	// Pipelines & Stages
	// -------------------------------------------------------------------------

	public function get_pipelines() {
		$result = $this->get( '/pipelines', array( 'limit' => 500 ) );

		if ( ! $result['success'] ) {
			return $result;
		}

		$pipelines = array();
		$items     = isset( $result['data']['data'] ) ? $result['data']['data'] : array();

		foreach ( $items as $item ) {
			$pipelines[] = array(
				'id'   => $item['id'],
				'name' => $item['name'],
			);
		}

		return array( 'success' => true, 'data' => $pipelines );
	}

	public function get_stages( $pipeline_id ) {
		$result = $this->get( '/stages', array( 'pipeline_id' => (int) $pipeline_id, 'limit' => 500 ) );

		if ( ! $result['success'] ) {
			return $result;
		}

		$stages = array();
		$items  = isset( $result['data']['data'] ) ? $result['data']['data'] : array();

		foreach ( $items as $item ) {
			$stages[] = array(
				'id'   => $item['id'],
				'name' => $item['name'],
			);
		}

		return array( 'success' => true, 'data' => $stages );
	}

	// -------------------------------------------------------------------------
	// Fields
	// -------------------------------------------------------------------------

	public function get_person_fields() {
		return $this->_get_fields( '/personFields', 'v1' );
	}

	public function get_organization_fields() {
		return $this->_get_fields( '/organizationFields', 'v1' );
	}

	public function get_deal_fields() {
		return $this->_get_fields( '/dealFields', 'v1' );
	}

	private function _get_fields( $endpoint, $version ) {
		$result = $this->get( $endpoint, array( 'limit' => 500 ), $version );

		if ( ! $result['success'] ) {
			return $result;
		}

		$fields = array();
		$items  = isset( $result['data']['data'] ) ? $result['data']['data'] : array();

		foreach ( $items as $item ) {
			// Ignora campos de sistema que não fazem sentido mapear.
			if ( isset( $item['edit_flag'] ) && ! $item['edit_flag'] && ! isset( $item['key'] ) ) {
				continue;
			}

			$fields[] = array(
				'key'          => $item['key'],
				'name'         => $item['name'],
				'field_type'   => isset( $item['field_type'] ) ? $item['field_type'] : 'varchar',
				'is_custom'    => isset( $item['edit_flag'] ) ? (bool) $item['edit_flag'] : false,
			);
		}

		return array( 'success' => true, 'data' => $fields );
	}

	// -------------------------------------------------------------------------
	// Create entities
	// -------------------------------------------------------------------------

	public function create_person( $data ) {
		return $this->post( '/persons', $data );
	}

	public function create_organization( $data ) {
		return $this->post( '/organizations', $data );
	}

	public function create_deal( $data ) {
		return $this->post( '/deals', $data );
	}
}
