<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Brevo_API {

	private $api_key;
	private $base_url = 'https://api.brevo.com/v3';

	public function __construct() {
		$this->api_key = get_option( 'epd_brevo_api_key', '' );
	}

	/**
	 * Verifica se a integração está configurada.
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Testa a conexão com a API do Brevo.
	 *
	 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'code' => int]
	 */
	public function test_connection() {
		return $this->request( 'GET', '/account' );
	}

	/**
	 * Cria ou atualiza um contato no Brevo.
	 *
	 * @param string $email       E-mail do contato (obrigatório).
	 * @param array  $attributes  Atributos do contato. Ex: ['FNAME' => 'João', 'LNAME' => 'Silva'].
	 * @param array  $list_ids    IDs das listas para adicionar o contato. Ex: [11].
	 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null, 'code' => int]
	 */
	public function create_contact( $email, array $attributes = array(), array $list_ids = array() ) {
		$body = array(
			'email'         => $email,
			'updateEnabled' => true,
		);

		if ( ! empty( $attributes ) ) {
			$body['attributes'] = $attributes;
		}

		if ( ! empty( $list_ids ) ) {
			$body['listIds'] = array_map( 'intval', $list_ids );
		}

		return $this->request( 'POST', '/contacts', $body );
	}

	/**
	 * Realiza uma requisição à API do Brevo.
	 */
	private function request( $method, $endpoint, array $body = array() ) {
		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'api-key'      => $this->api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->base_url . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
				'code'    => 0,
				'data'    => null,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = ! empty( $body ) ? json_decode( $body, true ) : null;

		// 2xx = sucesso (200 = OK, 201 = criado, 204 = atualizado sem body).
		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'data'    => $data,
				'error'   => null,
				'code'    => $code,
			);
		}

		$message = isset( $data['message'] ) ? $data['message'] : "HTTP {$code}";

		return array(
			'success' => false,
			'data'    => $data,
			'error'   => $message,
			'code'    => $code,
		);
	}
}
