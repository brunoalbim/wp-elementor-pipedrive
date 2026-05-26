<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Elementor_Handler {

	public function __construct() {
		add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_form_submit' ), 10, 2 );
		add_action( 'elementor_pro/forms/validation', array( $this, 'validate_form' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Entry point
	// -------------------------------------------------------------------------

	public function handle_form_submit( $record, $ajax_handler ) {
		$form_id = $record->get_form_settings( 'id' );

		if ( empty( $form_id ) ) {
			$form_id = $record->get_form_settings( '_id' );
		}

		if ( empty( $form_id ) ) {
			$this->log( 'form_id não encontrado no record do Elementor.' );
			return;
		}

		$mapping = $this->get_mapping_for_form( $form_id );

		if ( ! $mapping ) {
			$this->log( "Nenhum mapeamento ativo encontrado para form_id: {$form_id}" );
			return;
		}

		$raw_fields     = $record->get( 'fields' );
		$submitted      = $this->normalize_fields( $raw_fields );
		$saved_mappings = json_decode( $mapping->mappings, true );

		if ( ! is_array( $saved_mappings ) ) {
			return;
		}

		$utms = $this->extract_utms();
		foreach ( $utms as $key => $value ) {
			$submitted[ 'epd_' . $key ] = $value;
		}

		// Persiste o submission com todos os dados necessários para retentativa.
		$raw_wh_map   = ! empty( $mapping->webhook_field_map ) ? json_decode( $mapping->webhook_field_map, true ) : array();
		$wh_field_map = is_array( $raw_wh_map ) ? $raw_wh_map : array();

		$submission_id = $this->create_submission(
			$form_id,
			$mapping->form_name,
			$submitted,
			$utms,
			$saved_mappings,
			$mapping,
			$mapping->webhook_url,
			$wh_field_map
		);

		// 1. Pipedrive — somente se configurado (token + domínio preenchidos).
		$pipedrive_active = ! empty( get_option( 'epd_api_token', '' ) ) && ! empty( get_option( 'epd_company_domain', '' ) );

		if ( $pipedrive_active ) {
			$mapper = new EPD_Field_Mapper();
			$data   = $mapper->map(
				$submitted,
				$saved_mappings,
				array(
					'pipeline_id' => $mapping->pipeline_id,
					'stage_id'    => $mapping->stage_id,
					'deal_title'  => $mapping->deal_title,
				)
			);
			$created = $this->send_to_pipedrive( $data );
		} else {
			$created = array( 'person' => null, 'organization' => null, 'deal' => null );
		}

		$this->update_submission_pipedrive( $submission_id, $created );

		// 2. Brevo — somente se habilitado no mapeamento E API key configurada.
		// Roda ANTES do webhook para que seus dados sejam incluídos no payload.
		$brevo_payload = null;
		if ( ! empty( $mapping->brevo_enabled ) && ! empty( get_option( 'epd_brevo_api_key', '' ) ) ) {
			$raw_brevo_map   = ! empty( $mapping->brevo_field_map ) ? json_decode( $mapping->brevo_field_map, true ) : array();
			$brevo_field_map = is_array( $raw_brevo_map ) ? $raw_brevo_map : array();
			$brevo_http      = $this->send_to_brevo( $submitted, $brevo_field_map, (int) $mapping->brevo_list_id );
			$this->update_submission_brevo( $submission_id, $brevo_http );
			$brevo_payload   = array( 'status' => $brevo_http );
		}

		// 3. Webhook — roda por último, recebe dados do Pipedrive e do Brevo.
		if ( ! empty( $mapping->webhook_url ) ) {
			$webhook_result = $this->fire_webhook(
				$mapping->webhook_url,
				$created,
				$submitted,
				$utms,
				$wh_field_map,
				$brevo_payload
			);
			$this->update_submission_webhook( $submission_id, $webhook_result );
		}
	}

	// -------------------------------------------------------------------------
	// Normalize / helpers
	// -------------------------------------------------------------------------

	private function normalize_fields( array $raw_fields ) {
		$normalized = array();

		foreach ( $raw_fields as $key => $field ) {
			$field_id = isset( $field['id'] ) && $field['id'] !== '' ? $field['id'] : $key;
			$value    = isset( $field['value'] ) ? $field['value'] : ( isset( $field['raw_value'] ) ? $field['raw_value'] : '' );
			$normalized[ $field_id ] = $value;
		}

		return $normalized;
	}

	private function get_mapping_for_form( $form_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'epd_mappings';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE form_id = %s AND active = 1 LIMIT 1",
				$form_id
			)
		);
	}

	// -------------------------------------------------------------------------
	// Submission persistence
	// -------------------------------------------------------------------------

	private function create_submission( $form_id, $form_name, $submitted, $utms, $saved_mappings, $mapping, $webhook_url, $webhook_field_map = array() ) {
		global $wpdb;

		$submitted_data = wp_json_encode( array(
			'fields'           => $submitted,
			'utms'             => $utms,
			'webhook_field_map'=> $webhook_field_map,
			'mapping'          => array(
				'pipeline_id' => $mapping->pipeline_id,
				'stage_id'    => $mapping->stage_id,
				'deal_title'  => $mapping->deal_title,
				'mappings'    => $saved_mappings,
			),
		) );

		$wpdb->insert(
			$wpdb->prefix . 'epd_submissions',
			array(
				'form_id'          => $form_id,
				'form_name'        => $form_name,
				'submitted_data'   => $submitted_data,
				'pipedrive_status' => 'pending',
				'pipedrive_result' => '',
				'webhook_status'   => empty( $webhook_url ) ? 'skipped' : 'pending',
				'webhook_url'      => $webhook_url,
				'webhook_result'   => '',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function update_submission_pipedrive( $submission_id, array $created ) {
		if ( ! $submission_id ) {
			return;
		}

		global $wpdb;

		$pipedrive_active = ! empty( get_option( 'epd_api_token', '' ) ) && ! empty( get_option( 'epd_company_domain', '' ) );

		// Se o Pipedrive não está configurado, marca como skipped.
		if ( ! $pipedrive_active ) {
			$wpdb->update(
				$wpdb->prefix . 'epd_submissions',
				array(
					'pipedrive_status' => 'skipped',
					'pipedrive_result' => '',
				),
				array( 'id' => $submission_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		$deal    = $created['deal'];
		$person  = $created['person'];
		$org     = $created['organization'];
		$has_deal = ! empty( $deal['id'] );

		$result = array(
			'deal_id'      => $has_deal ? $deal['id'] : null,
			'person_id'    => ! empty( $person['id'] ) ? $person['id'] : null,
			'org_id'       => ! empty( $org['id'] ) ? $org['id'] : null,
			'deal'         => $deal ?: null,
			'person'       => $person ?: null,
			'organization' => $org ?: null,
		);

		if ( ! $has_deal ) {
			$result['error'] = 'Negociação não criada';
		}

		$wpdb->update(
			$wpdb->prefix . 'epd_submissions',
			array(
				'pipedrive_status' => $has_deal ? 'success' : 'error',
				'pipedrive_result' => wp_json_encode( $result ),
			),
			array( 'id' => $submission_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	private function update_submission_webhook( $submission_id, $result ) {
		if ( ! $submission_id ) {
			return;
		}

		global $wpdb;

		$is_success = is_numeric( $result ) && (int) $result >= 200 && (int) $result < 300;

		$wpdb->update(
			$wpdb->prefix . 'epd_submissions',
			array(
				'webhook_status' => $is_success ? 'success' : 'error',
				'webhook_result' => (string) $result,
			),
			array( 'id' => $submission_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	private function update_submission_brevo( $submission_id, $result ) {
		if ( ! $submission_id ) {
			return;
		}

		global $wpdb;

		$is_success = is_numeric( $result ) && (int) $result >= 200 && (int) $result < 300;

		$wpdb->update(
			$wpdb->prefix . 'epd_submissions',
			array(
				'brevo_status' => $is_success ? 'success' : 'error',
				'brevo_result' => (string) $result,
			),
			array( 'id' => $submission_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	// -------------------------------------------------------------------------
	// Retry (chamados via AJAX pelo admin)
	// -------------------------------------------------------------------------

	public function retry_pipedrive( $submission_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}epd_submissions WHERE id = %d",
				(int) $submission_id
			)
		);

		if ( ! $row ) {
			return array( 'success' => false, 'error' => 'Submission não encontrada.' );
		}

		$stored = json_decode( $row->submitted_data, true );

		if ( ! $stored ) {
			return array( 'success' => false, 'error' => 'Dados do submission inválidos.' );
		}

		$mapper  = new EPD_Field_Mapper();
		$data    = $mapper->map(
			$stored['fields'],
			$stored['mapping']['mappings'],
			$stored['mapping']
		);

		// Marca como pending antes de tentar.
		$wpdb->update(
			$wpdb->prefix . 'epd_submissions',
			array( 'pipedrive_status' => 'pending', 'pipedrive_result' => '' ),
			array( 'id' => (int) $submission_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$created = $this->send_to_pipedrive( $data );
		$this->update_submission_pipedrive( (int) $submission_id, $created );

		$deal_id = ! empty( $created['deal']['id'] ) ? $created['deal']['id'] : null;

		if ( ! $deal_id ) {
			return array( 'success' => false, 'error' => 'Falha ao criar negociação.' );
		}

		$utms        = isset( $stored['utms'] ) ? $stored['utms'] : array();
		$form_fields = isset( $stored['fields'] ) ? $stored['fields'] : array();

		// Brevo: reacionar antes do webhook para incluir no payload.
		$brevo_payload   = null;
		$mapping_for_brevo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT brevo_enabled, brevo_list_id, brevo_field_map FROM {$wpdb->prefix}epd_mappings WHERE form_id = %s AND active = 1 LIMIT 1",
				$row->form_id
			)
		);

		if ( $mapping_for_brevo && ! empty( $mapping_for_brevo->brevo_enabled ) && ! empty( get_option( 'epd_brevo_api_key', '' ) ) ) {
			$raw_brevo_map   = ! empty( $mapping_for_brevo->brevo_field_map ) ? json_decode( $mapping_for_brevo->brevo_field_map, true ) : array();
			$brevo_field_map = is_array( $raw_brevo_map ) ? $raw_brevo_map : array();
			$brevo_http      = $this->send_to_brevo( $form_fields, $brevo_field_map, (int) $mapping_for_brevo->brevo_list_id );
			$this->update_submission_brevo( (int) $submission_id, $brevo_http );
			$brevo_payload   = array( 'status' => $brevo_http );
		}

		// Webhook: dispara automaticamente após Pipedrive OK.
		if ( ! empty( $row->webhook_url ) ) {
			$wh_field_map   = isset( $stored['webhook_field_map'] ) ? $stored['webhook_field_map'] : array();
			$webhook_result = $this->fire_webhook( $row->webhook_url, $created, $form_fields, $utms, $wh_field_map, $brevo_payload );
			$this->update_submission_webhook( (int) $submission_id, $webhook_result );

			$webhook_ok = is_numeric( $webhook_result ) && (int) $webhook_result >= 200 && (int) $webhook_result < 300;

			return array(
				'success'        => true,
				'deal_id'        => $deal_id,
				'webhook_fired'  => true,
				'webhook_ok'     => $webhook_ok,
				'webhook_result' => $webhook_result,
			);
		}

		return array( 'success' => true, 'deal_id' => $deal_id );
	}

	public function retry_webhook( $submission_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}epd_submissions WHERE id = %d",
				(int) $submission_id
			)
		);

		if ( ! $row || empty( $row->webhook_url ) ) {
			return array( 'success' => false, 'error' => 'Submission não encontrada ou sem webhook configurado.' );
		}

		$stored           = json_decode( $row->submitted_data, true );
		$utms             = isset( $stored['utms'] ) ? $stored['utms'] : array();
		$pipedrive_result = $row->pipedrive_result ? json_decode( $row->pipedrive_result, true ) : array();

		// Reconstrói os objetos completos salvos no momento do envio original.
		$pipedrive_created = array(
			'deal'         => isset( $pipedrive_result['deal'] )         ? $pipedrive_result['deal']         : null,
			'person'       => isset( $pipedrive_result['person'] )       ? $pipedrive_result['person']       : null,
			'organization' => isset( $pipedrive_result['organization'] ) ? $pipedrive_result['organization'] : null,
		);

		// Marca como pending antes de tentar.
		$wpdb->update(
			$wpdb->prefix . 'epd_submissions',
			array( 'webhook_status' => 'pending', 'webhook_result' => '' ),
			array( 'id' => (int) $submission_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$form_fields  = isset( $stored['fields'] ) ? $stored['fields'] : array();
		$wh_field_map = isset( $stored['webhook_field_map'] ) ? $stored['webhook_field_map'] : array();
		$result = $this->fire_webhook( $row->webhook_url, $pipedrive_created, $form_fields, $utms, $wh_field_map );
		$this->update_submission_webhook( (int) $submission_id, $result );

		$is_success = is_numeric( $result ) && (int) $result >= 200 && (int) $result < 300;

		return array( 'success' => $is_success, 'http_code' => $result );
	}

	public function retry_brevo( $submission_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}epd_submissions WHERE id = %d",
				(int) $submission_id
			)
		);

		if ( ! $row ) {
			return array( 'success' => false, 'error' => 'Submission não encontrada.' );
		}

		$stored = json_decode( $row->submitted_data, true );
		if ( ! $stored ) {
			return array( 'success' => false, 'error' => 'Dados do submission inválidos.' );
		}

		$mapping = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT brevo_enabled, brevo_list_id, brevo_field_map FROM {$wpdb->prefix}epd_mappings WHERE form_id = %s AND active = 1 LIMIT 1",
				$row->form_id
			)
		);

		if ( ! $mapping || empty( $mapping->brevo_enabled ) ) {
			return array( 'success' => false, 'error' => 'Brevo não habilitado para este mapeamento.' );
		}

		if ( empty( get_option( 'epd_brevo_api_key', '' ) ) ) {
			return array( 'success' => false, 'error' => 'API Key do Brevo não configurada.' );
		}

		$raw_brevo_map   = ! empty( $mapping->brevo_field_map ) ? json_decode( $mapping->brevo_field_map, true ) : array();
		$brevo_field_map = is_array( $raw_brevo_map ) ? $raw_brevo_map : array();
		$form_fields     = isset( $stored['fields'] ) ? $stored['fields'] : array();

		// Marca como pending antes de tentar.
		$wpdb->update(
			$wpdb->prefix . 'epd_submissions',
			array( 'brevo_status' => 'pending', 'brevo_result' => '' ),
			array( 'id' => (int) $submission_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$result = $this->send_to_brevo( $form_fields, $brevo_field_map, (int) $mapping->brevo_list_id );
		$this->update_submission_brevo( (int) $submission_id, $result );

		$is_success = is_numeric( $result ) && (int) $result >= 200 && (int) $result < 300;
		return array( 'success' => $is_success, 'http_code' => $result );
	}

	// -------------------------------------------------------------------------
	// Pipedrive API
	// -------------------------------------------------------------------------

	private function send_to_pipedrive( array $data ) {
		$api = new EPD_Pipedrive_API();

		$created = array(
			'person'       => null,
			'organization' => null,
			'deal'         => null,
		);

		if ( ! $api->is_configured() ) {
			$this->log( 'API não configurada. Verifique o token e o company domain nas configurações do plugin.' );
			return $created;
		}

		$org_id    = null;
		$person_id = null;

		if ( ! empty( $data['organization'] ) ) {
			$this->log( 'Enviando organização: ' . wp_json_encode( $data['organization'] ) );
			$result = $api->create_organization( $data['organization'] );

			if ( $result['success'] ) {
				$org_id                  = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
				$created['organization'] = isset( $result['data']['data'] ) ? $result['data']['data'] : null;
				$this->log( "Organização criada: #{$org_id}" );
			} else {
				$error = isset( $result['error'] ) ? $result['error'] : wp_json_encode( $result );
				$this->log( 'Erro ao criar organização: ' . $error );
			}
		}

		if ( ! empty( $data['person'] ) ) {
			$person_data = $data['person'];

			if ( $org_id ) {
				$person_data['org_id'] = $org_id;
			}

			$this->log( 'Enviando pessoa: ' . wp_json_encode( $person_data ) );
			$result = $api->create_person( $person_data );

			if ( $result['success'] ) {
				$person_id        = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
				$created['person'] = isset( $result['data']['data'] ) ? $result['data']['data'] : null;
				$this->log( "Pessoa criada: #{$person_id}" );
			} else {
				$error = isset( $result['error'] ) ? $result['error'] : wp_json_encode( $result );
				$this->log( 'Erro ao criar pessoa: ' . $error );
			}
		}

		$deal_data = $data['deal'];

		if ( $person_id ) {
			$deal_data['person_id'] = $person_id;
		}

		if ( $org_id ) {
			$deal_data['org_id'] = $org_id;
		}

		$this->log( 'Enviando negociação: ' . wp_json_encode( $deal_data ) );
		$result = $api->create_deal( $deal_data );

		if ( $result['success'] ) {
			$deal_id         = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
			$created['deal'] = isset( $result['data']['data'] ) ? $result['data']['data'] : null;
			$this->log( "Negociação criada com sucesso: #{$deal_id}" );
		} else {
			$error = isset( $result['error'] ) ? $result['error'] : wp_json_encode( $result );
			$this->log( 'Erro ao criar negociação: ' . $error );
		}

		return $created;
	}

	// -------------------------------------------------------------------------
	// UTMs
	// -------------------------------------------------------------------------

	private function extract_utms() {
		$keys        = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
		$utms        = array();
		$post_utms   = isset( $_POST['epd_utm'] ) && is_array( $_POST['epd_utm'] ) ? $_POST['epd_utm'] : array();
		$cookie_utms = array();

		if ( ! empty( $_COOKIE['epd_utm'] ) ) {
			$decoded = json_decode( stripslashes( $_COOKIE['epd_utm'] ), true );
			if ( is_array( $decoded ) ) {
				$cookie_utms = $decoded;
			}
		}

		foreach ( $keys as $key ) {
			$value = '';

			if ( ! empty( $post_utms[ $key ] ) ) {
				$value = sanitize_text_field( $post_utms[ $key ] );
			} elseif ( ! empty( $cookie_utms[ $key ] ) ) {
				$value = sanitize_text_field( $cookie_utms[ $key ] );
			}

			if ( $value !== '' ) {
				$utms[ $key ] = $value;
			}
		}

		if ( ! empty( $utms ) ) {
			$this->log( 'UTMs capturados: ' . wp_json_encode( $utms ) );
		}

		return $utms;
	}

	// -------------------------------------------------------------------------
	// Brevo
	// -------------------------------------------------------------------------

	/**
	 * Envia um contato ao Brevo com base no mapeamento configurado.
	 * Retorna o HTTP status code (string) em caso de sucesso, ou mensagem de erro.
	 */
	private function send_to_brevo( array $submitted, array $brevo_field_map, $list_id ) {
		$api = new EPD_Brevo_API();

		if ( ! $api->is_configured() ) {
			$this->log( 'Brevo: API Key não configurada.' );
			return 'api_not_configured';
		}

		$email      = '';
		$attributes = array();

		foreach ( $brevo_field_map as $row ) {
			if ( empty( $row['elementor_field'] ) || empty( $row['brevo_attribute'] ) ) {
				continue;
			}

			$elementor_field = $row['elementor_field'];
			$brevo_key       = $row['brevo_attribute'];

			if ( ! isset( $submitted[ $elementor_field ] ) ) {
				continue;
			}

			$value = sanitize_text_field( $submitted[ $elementor_field ] );

			if ( $value === '' ) {
				continue;
			}

			// Chave "EMAIL" (case-insensitive) → campo de email do contato.
			if ( strtoupper( $brevo_key ) === 'EMAIL' ) {
				$email = $value;
			} else {
				$attributes[ $brevo_key ] = $value;
			}
		}

		if ( empty( $email ) ) {
			$this->log( 'Brevo: campo de e-mail não encontrado no mapeamento. Contato não criado.' );
			return 'email_not_mapped';
		}

		$list_ids = $list_id > 0 ? array( $list_id ) : array();

		$this->log( "Brevo: enviando contato {$email}" );
		$result = $api->create_contact( $email, $attributes, $list_ids );

		if ( $result['success'] ) {
			$code = isset( $result['code'] ) ? $result['code'] : 201;
			$this->log( "Brevo: contato criado/atualizado. HTTP {$code}" );
			return (string) $code;
		}

		$error = isset( $result['error'] ) ? $result['error'] : 'Erro desconhecido';
		$this->log( "Brevo: erro ao criar contato. {$error}" );
		return $error;
	}

	// -------------------------------------------------------------------------
	// Webhook
	// -------------------------------------------------------------------------

	/**
	 * Dispara o webhook e retorna o HTTP status code (string) ou mensagem de erro.
	 *
	 * @param string     $url              URL do webhook.
	 * @param array      $created          Entidades criadas no Pipedrive.
	 * @param array      $form_fields      Campos do formulário.
	 * @param array      $utms             Parâmetros UTM.
	 * @param array      $webhook_field_map Mapeamento de renomeação de campos.
	 * @param array|null $brevo_result     Resultado do Brevo (null se desabilitado).
	 */
	private function fire_webhook( $url, array $created, array $form_fields = array(), array $utms = array(), array $webhook_field_map = array(), $brevo_result = null ) {
		// Remove campos internos de UTM dos campos do formulário antes de enviar.
		$clean_fields = array();
		foreach ( $form_fields as $key => $value ) {
			if ( strpos( $key, 'epd_utm' ) !== 0 ) {
				$clean_fields[ $key ] = $value;
			}
		}

		// Aplica mapeamento de campos do webhook: renomeia chaves conforme configurado.
		if ( ! empty( $webhook_field_map ) ) {
			$mapped_fields = array();
			// Índice para lookup rápido: elementor_field → webhook_key.
			$wh_map_index = array();
			foreach ( $webhook_field_map as $row ) {
				if ( ! empty( $row['elementor_field'] ) && ! empty( $row['webhook_key'] ) ) {
					$wh_map_index[ $row['elementor_field'] ] = $row['webhook_key'];
				}
			}
			foreach ( $clean_fields as $key => $value ) {
				$mapped_key = isset( $wh_map_index[ $key ] ) ? $wh_map_index[ $key ] : $key;
				$mapped_fields[ $mapped_key ] = $value;
			}
			$clean_fields = $mapped_fields;
		}

		$form_data = $clean_fields;
		if ( ! empty( $utms ) ) {
			$form_data['utm'] = $utms;
		}

		$payload = array(
			'source'    => 'elementor-pipedrive',
			'form'      => $form_data,
			'pipedrive' => array(
				'person'       => $created['person'],
				'organization' => $created['organization'],
				'deal'         => $created['deal'],
			),
		);

		// Inclui dados do Brevo no payload se a integração estiver ativa neste mapeamento.
		if ( $brevo_result !== null ) {
			$payload['brevo'] = $brevo_result;
		}

		$this->log( 'Disparando webhook: ' . $url );

		$response = wp_remote_post( $url, array(
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload ),
			'timeout'     => 15,
			'redirection' => 3,
		) );

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			$this->log( 'Erro no webhook: ' . $error );
			return $error;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$this->log( "Webhook disparado. HTTP {$code}" );

		return (string) $code;
	}

	// -------------------------------------------------------------------------
	// Validação de formulário (backend)
	// -------------------------------------------------------------------------

	public function validate_form( $record, $ajax_handler ) {
		$form_id = $record->get_form_settings( 'id' );
		if ( empty( $form_id ) ) {
			$form_id = $record->get_form_settings( '_id' );
		}
		if ( empty( $form_id ) ) {
			return;
		}

		$mapping = $this->get_mapping_for_form( $form_id );
		if ( ! $mapping || empty( $mapping->validation_config ) ) {
			return;
		}

		$config = json_decode( $mapping->validation_config, true );
		if ( ! is_array( $config ) ) {
			return;
		}

		$raw_fields = $record->get( 'fields' );

		// Validação de telefone.
		if ( ! empty( $config['phone_validation'] ) ) {
			foreach ( array( 'telefone', 'celular' ) as $phone_id ) {
				foreach ( $raw_fields as $field ) {
					$field_custom_id = isset( $field['id'] ) ? $field['id'] : '';
					if ( $field_custom_id !== $phone_id ) {
						continue;
					}
					$value  = isset( $field['value'] ) ? $field['value'] : '';
					$digits = preg_replace( '/\D/', '', $value );
					if ( $value !== '' && strlen( $digits ) < 10 ) {
						$ajax_handler->add_error(
							$field['id'],
							__( 'Telefone incompleto. Informe DDD + número (mínimo 10 dígitos).', 'elementor-pipedrive' )
						);
					}
				}
			}
		}

		// Validação de e-mail corporativo.
		if ( ! empty( $config['email_block_enabled'] ) ) {
			$blocked_domains  = isset( $config['email_block_domains'] )  ? (array) $config['email_block_domains']  : array();
			$blocked_suffixes = isset( $config['email_block_suffixes'] ) ? (array) $config['email_block_suffixes'] : array();
			$blocked_words    = isset( $config['email_block_words'] )    ? (array) $config['email_block_words']    : array();
			$msg_domains      = ! empty( $config['email_msg_domains'] )  ? $config['email_msg_domains']  : __( 'E-mails de domínio público não são permitidos. Por favor, use um e-mail corporativo.', 'elementor-pipedrive' );
			$msg_suffixes     = ! empty( $config['email_msg_suffixes'] ) ? $config['email_msg_suffixes'] : __( 'O domínio do seu e-mail não é permitido. Por favor, use um e-mail corporativo.', 'elementor-pipedrive' );
			$msg_words        = ! empty( $config['email_msg_words'] )    ? $config['email_msg_words']    : __( 'O endereço de e-mail informado não é válido. Por favor, use um e-mail corporativo.', 'elementor-pipedrive' );

			foreach ( $raw_fields as $field ) {
				$field_custom_id = isset( $field['id'] ) ? $field['id'] : '';
				if ( $field_custom_id !== 'email' ) {
					continue;
				}
				$value = isset( $field['value'] ) ? trim( $field['value'] ) : '';
				if ( ! $value || strpos( $value, '@' ) === false ) {
					continue;
				}

				$domain      = strtolower( explode( '@', $value )[1] );
				$error_msg   = null;

				// Hierarquia: domínios > sufixos > palavras.
				if ( in_array( $domain, $blocked_domains, true ) ) {
					$error_msg = $msg_domains;
				} else {
					foreach ( $blocked_suffixes as $suffix ) {
						if ( $suffix && substr( $domain, -strlen( $suffix ) ) === strtolower( $suffix ) ) {
							$error_msg = $msg_suffixes;
							break;
						}
					}
				}
				if ( ! $error_msg ) {
					foreach ( $blocked_words as $word ) {
						if ( $word && stripos( $domain, $word ) !== false ) {
							$error_msg = $msg_words;
							break;
						}
					}
				}

				if ( $error_msg ) {
					$ajax_handler->add_error( $field['id'], $error_msg );
				}
			}
		}
	}

	// -------------------------------------------------------------------------
	// Log
	// -------------------------------------------------------------------------

	private function log( $message ) {
		$entry = '[EPD ' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message;
		error_log( $entry );

		global $wpdb;
		$table = $wpdb->prefix . 'epd_logs';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
			$wpdb->insert(
				$table,
				array( 'message' => $message, 'created_at' => current_time( 'mysql', true ) ),
				array( '%s', '%s' )
			);
		}
	}
}
