<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Field_Mapper {

	/**
	 * Recebe os campos submetidos do Elementor e o array de mapeamentos salvos.
	 * Retorna dados separados por entidade: person, organization, deal.
	 *
	 * @param array $submitted_fields  ['field_id' => 'value', ...]
	 * @param array $mappings          [['elementor_field' => '', 'entity' => '', 'pipedrive_field' => ''], ...]
	 * @param array $mapping_config    ['pipeline_id', 'stage_id', 'deal_title']
	 * @return array ['person' => [], 'organization' => [], 'deal' => []]
	 */
	public function map( array $submitted_fields, array $mappings, array $mapping_config ) {
		$result = array(
			'person'       => array(),
			'organization' => array(),
			'deal'         => array(),
		);

		foreach ( $mappings as $map ) {
			$elementor_field  = isset( $map['elementor_field'] ) ? $map['elementor_field'] : '';
			$entity           = isset( $map['entity'] ) ? $map['entity'] : '';
			$pipedrive_field  = isset( $map['pipedrive_field'] ) ? $map['pipedrive_field'] : '';

			if ( ! $elementor_field || ! $entity || ! $pipedrive_field ) {
				continue;
			}

			if ( ! isset( $submitted_fields[ $elementor_field ] ) ) {
				continue;
			}

			$value = sanitize_text_field( $submitted_fields[ $elementor_field ] );

			if ( ! isset( $result[ $entity ] ) ) {
				continue;
			}

			$result[ $entity ] = $this->assign_field( $result[ $entity ], $pipedrive_field, $value );
		}

		// Monta o título da negociação com variáveis {field_id}.
		$deal_title = $this->resolve_title(
			isset( $mapping_config['deal_title'] ) ? $mapping_config['deal_title'] : 'Lead',
			$submitted_fields
		);

		$result['deal']['title']       = $deal_title;
		$result['deal']['pipeline_id'] = (int) $mapping_config['pipeline_id'];
		$result['deal']['stage_id']    = (int) $mapping_config['stage_id'];

		return $result;
	}

	/**
	 * Atribui o valor ao campo correto do payload.
	 *
	 * Campos nativos do Pipedrive são enviados no nível raiz do objeto.
	 * Campos customizados (chave hex de 40 chars) devem ficar dentro de
	 * custom_fields conforme exigido pela API v2.
	 * Campos especiais como email e phone são arrays estruturados.
	 */
	private function assign_field( array $entity_data, $field_key, $value ) {
		if ( $field_key === 'email' ) {
			$entity_data['emails'] = array(
				array( 'value' => $value, 'primary' => true, 'label' => 'work' ),
			);
		} elseif ( $field_key === 'phone' ) {
			$entity_data['phones'] = array(
				array( 'value' => $value, 'primary' => true, 'label' => 'work' ),
			);
		} elseif ( $this->is_custom_field( $field_key ) ) {
			if ( ! isset( $entity_data['custom_fields'] ) ) {
				$entity_data['custom_fields'] = array();
			}
			$entity_data['custom_fields'][ $field_key ] = $value;
		} else {
			$entity_data[ $field_key ] = $value;
		}

		return $entity_data;
	}

	/**
	 * Campos customizados do Pipedrive são hashes hexadecimais de 40 caracteres.
	 */
	private function is_custom_field( $key ) {
		return (bool) preg_match( '/^[0-9a-f]{40}$/', $key );
	}

	/**
	 * Substitui variáveis do tipo {field_id} pelo valor submetido.
	 */
	private function resolve_title( $template, array $fields ) {
		return preg_replace_callback(
			'/\{([^}]+)\}/',
			function ( $matches ) use ( $fields ) {
				$key = $matches[1];
				return isset( $fields[ $key ] ) ? sanitize_text_field( $fields[ $key ] ) : $matches[0];
			},
			$template
		);
	}
}
