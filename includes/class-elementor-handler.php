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
		$form_id = $record->get_form_settings( 'id' );

		if ( empty( $form_id ) ) {
			return;
		}

		$mapping = $this->get_mapping_for_form( $form_id );

		if ( ! $mapping ) {
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
	 * Normaliza os campos do Elementor para ['field_id' => 'value'].
	 */
	private function normalize_fields( array $raw_fields ) {
		$normalized = array();

		foreach ( $raw_fields as $id => $field ) {
			$value = isset( $field['value'] ) ? $field['value'] : '';
			$normalized[ $id ] = $value;
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
			$this->log( 'API não configurada.' );
			return;
		}

		$org_id    = null;
		$person_id = null;

		// Cria Organização se houver campos mapeados.
		if ( ! empty( $data['organization'] ) ) {
			$result = $api->create_organization( $data['organization'] );

			if ( $result['success'] ) {
				$org_id = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
				$this->log( "Organização criada: #{$org_id}" );
			} else {
				$this->log( 'Erro ao criar organização: ' . $result['error'] );
			}
		}

		// Cria Pessoa se houver campos mapeados.
		if ( ! empty( $data['person'] ) ) {
			$person_data = $data['person'];

			if ( $org_id ) {
				$person_data['org_id'] = $org_id;
			}

			$result = $api->create_person( $person_data );

			if ( $result['success'] ) {
				$person_id = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
				$this->log( "Pessoa criada: #{$person_id}" );
			} else {
				$this->log( 'Erro ao criar pessoa: ' . $result['error'] );
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

		$result = $api->create_deal( $deal_data );

		if ( $result['success'] ) {
			$deal_id = isset( $result['data']['data']['id'] ) ? $result['data']['data']['id'] : null;
			$this->log( "Negociação criada: #{$deal_id}" );
		} else {
			$this->log( 'Erro ao criar negociação: ' . $result['error'] );
		}
	}

	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[EPD] ' . $message );
		}
	}
}
