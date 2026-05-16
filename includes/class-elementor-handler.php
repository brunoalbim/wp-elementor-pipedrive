<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Elementor_Handler {

	public function __construct() {
		add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_form_submit' ), 10, 2 );
	}

	/**
	 * Disparado pelo Elementor Pro após o envio de um formulário.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function handle_form_submit( $record, $ajax_handler ) {
		// O Elementor Pro expõe o ID do formulário via get_form_settings('id'),
		// mas esse campo nem sempre está preenchido. Fallback para o ID do widget.
		$form_id = $record->get_form_settings( 'id' );

		if ( empty( $form_id ) ) {
			// Tenta pegar via meta do post atual como último recurso.
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

		$raw_fields      = $record->get( 'fields' );
		$submitted       = $this->normalize_fields( $raw_fields );
		$saved_mappings  = json_decode( $mapping->mappings, true );

		if ( ! is_array( $saved_mappings ) ) {
			return;
		}

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

		$this->send_to_pipedrive( $data );
	}

	/**
	 * Normaliza os campos do Elementor para ['custom_id' => 'value'].
	 *
	 * O Elementor indexa $raw_fields pela chave numérica interna do widget,
	 * mas cada campo tem um 'id' (= custom_id definido pelo usuário) e um 'raw_value'.
	 * Construímos um mapa pelo custom_id para que o mapeamento funcione corretamente.
	 */
	private function normalize_fields( array $raw_fields ) {
		$normalized = array();

		foreach ( $raw_fields as $key => $field ) {
			// O Elementor Pro popula 'id' com o custom_id do campo.
			$field_id = isset( $field['id'] ) && $field['id'] !== '' ? $field['id'] : $key;
			$value    = isset( $field['value'] ) ? $field['value'] : ( isset( $field['raw_value'] ) ? $field['raw_value'] : '' );

			$normalized[ $field_id ] = $value;
		}

		return $normalized;
	}

	/**
	 * Busca o mapeamento ativo para o form_id no banco.
	 */
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

	/**
	 * Executa a sequência: Organização → Pessoa → Negociação.
	 */
	private function send_to_pipedrive( array $data ) {
		$api = new EPD_Pipedrive_API();

		if ( ! $api->is_configured() ) {
			$this->log( 'API não configurada. Verifique o token e o company domain nas configurações do plugin.' );
			return;
		}

		$org_id    = null;
		$person_id = null;

		// Cria Organização se houver campos mapeados.
		if ( ! empty( $data['organization'] ) ) {
			$this->log( 'Enviando organização: ' . wp_json_encode( $data['organization'] ) );
			$result = $api->create_organization( $data['organization'] );

			if ( $result['success'] ) {
				$org_id = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
				$this->log( "Organização criada: #{$org_id}" );
			} else {
				$error = isset( $result['error'] ) ? $result['error'] : wp_json_encode( $result );
				$this->log( 'Erro ao criar organização: ' . $error );
			}
		}

		// Cria Pessoa se houver campos mapeados.
		if ( ! empty( $data['person'] ) ) {
			$person_data = $data['person'];

			if ( $org_id ) {
				$person_data['org_id'] = $org_id;
			}

			$this->log( 'Enviando pessoa: ' . wp_json_encode( $person_data ) );
			$result = $api->create_person( $person_data );

			if ( $result['success'] ) {
				$person_id = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
				$this->log( "Pessoa criada: #{$person_id}" );
			} else {
				$error = isset( $result['error'] ) ? $result['error'] : wp_json_encode( $result );
				$this->log( 'Erro ao criar pessoa: ' . $error );
			}
		}

		// Sempre cria Negociação.
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
			$deal_id = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
			$this->log( "Negociação criada com sucesso: #{$deal_id}" );
		} else {
			$error = isset( $result['error'] ) ? $result['error'] : wp_json_encode( $result );
			$this->log( 'Erro ao criar negociação: ' . $error );
		}
	}

	/**
	 * Grava log sempre (não depende de WP_DEBUG) no error_log do servidor
	 * e opcionalmente na tabela de logs do plugin.
	 */
	private function log( $message ) {
		$entry = '[EPD ' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message;
		error_log( $entry );

		global $wpdb;
		$table = $wpdb->prefix . 'epd_logs';
		// Insere apenas se a tabela existir (criada pela versão 1.1+ do ativador).
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
			$wpdb->insert(
				$table,
				array( 'message' => $message, 'created_at' => current_time( 'mysql', true ) ),
				array( '%s', '%s' )
			);
		}
	}
}
