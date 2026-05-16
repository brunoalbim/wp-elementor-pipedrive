<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_epd_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_epd_save_mapping', array( $this, 'save_mapping' ) );
		add_action( 'admin_post_epd_delete_mapping', array( $this, 'delete_mapping' ) );
		add_action( 'admin_post_epd_duplicate_mapping', array( $this, 'duplicate_mapping' ) );
		add_action( 'admin_post_epd_clear_logs', array( $this, 'clear_logs' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_epd_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_epd_get_pipelines', array( $this, 'ajax_get_pipelines' ) );
		add_action( 'wp_ajax_epd_get_stages', array( $this, 'ajax_get_stages' ) );
		add_action( 'wp_ajax_epd_get_pipedrive_fields', array( $this, 'ajax_get_pipedrive_fields' ) );
		add_action( 'wp_ajax_epd_get_elementor_forms', array( $this, 'ajax_get_elementor_forms' ) );
		add_action( 'wp_ajax_epd_get_form_fields', array( $this, 'ajax_get_form_fields' ) );
		add_action( 'wp_ajax_epd_retry_pipedrive', array( $this, 'ajax_retry_pipedrive' ) );
		add_action( 'wp_ajax_epd_retry_webhook', array( $this, 'ajax_retry_webhook' ) );
	}

	// -------------------------------------------------------------------------
	// Menu & Assets
	// -------------------------------------------------------------------------

	public function register_menu() {
		add_menu_page(
			__( 'Elementor Pipedrive', 'elementor-pipedrive' ),
			__( 'Elementor Pipedrive', 'elementor-pipedrive' ),
			'manage_options',
			'epd-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-share-alt',
			58
		);

		add_submenu_page(
			'epd-settings',
			__( 'Configurações', 'elementor-pipedrive' ),
			__( 'Configurações', 'elementor-pipedrive' ),
			'manage_options',
			'epd-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'epd-settings',
			__( 'Mapeamentos', 'elementor-pipedrive' ),
			__( 'Mapeamentos', 'elementor-pipedrive' ),
			'manage_options',
			'epd-mappings',
			array( $this, 'render_mappings_page' )
		);

		add_submenu_page(
			'epd-settings',
			__( 'Envios', 'elementor-pipedrive' ),
			__( 'Envios', 'elementor-pipedrive' ),
			'manage_options',
			'epd-submissions',
			array( $this, 'render_submissions_page' )
		);

		add_submenu_page(
			'epd-settings',
			__( 'Logs', 'elementor-pipedrive' ),
			__( 'Logs', 'elementor-pipedrive' ),
			'manage_options',
			'epd-logs',
			array( $this, 'render_logs_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		$epd_hooks = array( 'toplevel_page_epd-settings', 'elementor-pipedrive_page_epd-mappings' );

		if ( ! in_array( $hook, $epd_hooks, true ) && strpos( $hook, 'epd' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'epd-admin',
			EPD_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			EPD_VERSION
		);

		wp_enqueue_script(
			'epd-admin',
			EPD_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			EPD_VERSION,
			true
		);

		wp_localize_script(
			'epd-admin',
			'epdData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'epd_ajax_nonce' ),
				'i18n'    => array(
					'confirmDelete'  => __( 'Tem certeza que deseja excluir este mapeamento?', 'elementor-pipedrive' ),
					'connectionOk'   => __( 'Conexão bem-sucedida!', 'elementor-pipedrive' ),
					'connectionFail' => __( 'Falha na conexão: ', 'elementor-pipedrive' ),
					'loading'        => __( 'Carregando...', 'elementor-pipedrive' ),
					'addRow'         => __( '+ Adicionar campo', 'elementor-pipedrive' ),
					'remove'         => __( 'Remover', 'elementor-pipedrive' ),
					'selectField'    => __( '— Selecione o campo —', 'elementor-pipedrive' ),
					'selectEntity'   => __( '— Entidade —', 'elementor-pipedrive' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Render Pages
	// -------------------------------------------------------------------------

	public function render_settings_page() {
		require_once EPD_PLUGIN_DIR . 'admin/partials/settings-page.php';
	}

	public function render_mappings_page() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

		if ( $action === 'edit' || $action === 'new' ) {
			require_once EPD_PLUGIN_DIR . 'admin/partials/mapping-edit.php';
		} else {
			require_once EPD_PLUGIN_DIR . 'admin/partials/mapping-page.php';
		}
	}

	public function render_submissions_page() {
		require_once EPD_PLUGIN_DIR . 'admin/partials/submissions-page.php';
	}

	public function render_logs_page() {
		require_once EPD_PLUGIN_DIR . 'admin/partials/logs-page.php';
	}

	// -------------------------------------------------------------------------
	// Form Handlers
	// -------------------------------------------------------------------------

	public function save_settings() {
		check_admin_referer( 'epd_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		update_option( 'epd_api_token', sanitize_text_field( $_POST['epd_api_token'] ?? '' ) );
		update_option( 'epd_company_domain', sanitize_text_field( $_POST['epd_company_domain'] ?? '' ) );

		wp_redirect( add_query_arg( array( 'page' => 'epd-settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_mapping() {
		check_admin_referer( 'epd_save_mapping' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'epd_mappings';

		$mapping_id  = isset( $_POST['mapping_id'] ) ? (int) $_POST['mapping_id'] : 0;
		$form_id     = sanitize_text_field( $_POST['form_id'] ?? '' );
		$form_name   = sanitize_text_field( $_POST['form_name'] ?? '' );
		$pipeline_id = (int) ( $_POST['pipeline_id'] ?? 0 );
		$stage_id    = (int) ( $_POST['stage_id'] ?? 0 );
		$deal_title  = sanitize_text_field( $_POST['deal_title'] ?? 'Lead' );
		$webhook_url = esc_url_raw( $_POST['webhook_url'] ?? '' );

		// Monta array de mapeamentos a partir dos campos do form.
		$elementor_fields = $_POST['elementor_field'] ?? array();
		$entities         = $_POST['entity'] ?? array();
		$pipedrive_fields = $_POST['pipedrive_field'] ?? array();
		$mappings         = array();

		foreach ( $elementor_fields as $i => $ef ) {
			$ef  = sanitize_text_field( $ef );
			$ent = sanitize_key( $entities[ $i ] ?? '' );
			$pf  = sanitize_text_field( $pipedrive_fields[ $i ] ?? '' );

			if ( $ef && $ent && $pf ) {
				$mappings[] = array(
					'elementor_field' => $ef,
					'entity'          => $ent,
					'pipedrive_field' => $pf,
				);
			}
		}

		$data = array(
			'form_id'     => $form_id,
			'form_name'   => $form_name,
			'pipeline_id' => $pipeline_id,
			'stage_id'    => $stage_id,
			'deal_title'  => $deal_title,
			'mappings'    => wp_json_encode( $mappings ),
			'webhook_url' => $webhook_url,
			'active'      => 1,
		);

		if ( $mapping_id > 0 ) {
			$result = $wpdb->update(
				$table,
				$data,
				array( 'id' => $mapping_id ),
				array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert(
				$table,
				$data,
				array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
			);
		}

		if ( $result === false ) {
			wp_die(
				'<strong>Erro ao salvar mapeamento:</strong> ' . esc_html( $wpdb->last_error )
				. '<br><br><a href="' . esc_url( admin_url( 'admin.php?page=epd-mappings' ) ) . '">&larr; Voltar</a>'
			);
		}

		wp_redirect( add_query_arg( array( 'page' => 'epd-mappings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_mapping() {
		check_admin_referer( 'epd_delete_mapping' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		global $wpdb;
		$table      = $wpdb->prefix . 'epd_mappings';
		$mapping_id = (int) ( $_POST['mapping_id'] ?? 0 );

		if ( $mapping_id > 0 ) {
			$wpdb->delete( $table, array( 'id' => $mapping_id ), array( '%d' ) );
		}

		wp_redirect( add_query_arg( array( 'page' => 'epd-mappings', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function duplicate_mapping() {
		check_admin_referer( 'epd_duplicate_mapping' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		global $wpdb;
		$table      = $wpdb->prefix . 'epd_mappings';
		$mapping_id = (int) ( $_POST['mapping_id'] ?? 0 );

		if ( ! $mapping_id ) {
			wp_redirect( admin_url( 'admin.php?page=epd-mappings' ) );
			exit;
		}

		$original = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $mapping_id ) );

		if ( ! $original ) {
			wp_redirect( admin_url( 'admin.php?page=epd-mappings' ) );
			exit;
		}

		$wpdb->insert(
			$table,
			array(
				'form_id'     => $original->form_id,
				'form_name'   => $original->form_name . ' (Cópia)',
				'pipeline_id' => $original->pipeline_id,
				'stage_id'    => $original->stage_id,
				'deal_title'  => $original->deal_title,
				'mappings'    => $original->mappings,
				'webhook_url' => $original->webhook_url,
				'active'      => 0,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d' )
		);

		$new_id = (int) $wpdb->insert_id;

		wp_redirect( add_query_arg( array( 'page' => 'epd-mappings', 'action' => 'edit', 'id' => $new_id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function clear_logs() {
		check_admin_referer( 'epd_clear_logs' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}epd_logs" );

		wp_redirect( add_query_arg( array( 'page' => 'epd-logs', 'cleared' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers
	// -------------------------------------------------------------------------

	private function verify_ajax_nonce() {
		if ( ! check_ajax_referer( 'epd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Nonce inválido.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sem permissão.' );
		}
	}

	public function ajax_test_connection() {
		$this->verify_ajax_nonce();

		$api    = new EPD_Pipedrive_API();
		$result = $api->test_connection();

		if ( $result['success'] ) {
			$name = isset( $result['data']['data']['name'] ) ? $result['data']['data']['name'] : '';
			wp_send_json_success( array( 'message' => "Conectado como: {$name}" ) );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	public function ajax_get_pipelines() {
		$this->verify_ajax_nonce();

		$api    = new EPD_Pipedrive_API();
		$result = $api->get_pipelines();

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	public function ajax_get_stages() {
		$this->verify_ajax_nonce();

		$pipeline_id = (int) ( $_POST['pipeline_id'] ?? 0 );

		if ( ! $pipeline_id ) {
			wp_send_json_error( 'pipeline_id inválido.' );
		}

		$api    = new EPD_Pipedrive_API();
		$result = $api->get_stages( $pipeline_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	public function ajax_get_pipedrive_fields() {
		$this->verify_ajax_nonce();

		$entity = sanitize_key( $_POST['entity'] ?? '' );
		$api    = new EPD_Pipedrive_API();

		if ( $entity === 'person' ) {
			$result = $api->get_person_fields();
		} elseif ( $entity === 'organization' ) {
			$result = $api->get_organization_fields();
		} elseif ( $entity === 'deal' ) {
			$result = $api->get_deal_fields();
		} else {
			wp_send_json_error( 'Entidade inválida.' );
			return;
		}

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	public function ajax_get_elementor_forms() {
		$this->verify_ajax_nonce();

		$forms = $this->get_all_elementor_forms();
		wp_send_json_success( $forms );
	}

	public function ajax_get_form_fields() {
		$this->verify_ajax_nonce();

		$form_id = sanitize_text_field( $_POST['form_id'] ?? '' );

		if ( ! $form_id ) {
			wp_send_json_error( 'form_id inválido.' );
		}

		$forms  = $this->get_all_elementor_forms();
		$fields = array();

		foreach ( $forms as $form ) {
			if ( $form['id'] === $form_id ) {
				$fields = $form['fields'];
				break;
			}
		}

		wp_send_json_success( $fields );
	}

	// -------------------------------------------------------------------------
	// Helpers
	public function ajax_retry_pipedrive() {
		$this->verify_ajax_nonce();

		$submission_id = (int) ( $_POST['submission_id'] ?? 0 );

		if ( ! $submission_id ) {
			wp_send_json_error( 'submission_id inválido.' );
		}

		$handler = new EPD_Elementor_Handler();
		$result  = $handler->retry_pipedrive( $submission_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['error'] );
		}
	}

	public function ajax_retry_webhook() {
		$this->verify_ajax_nonce();

		$submission_id = (int) ( $_POST['submission_id'] ?? 0 );

		if ( ! $submission_id ) {
			wp_send_json_error( 'submission_id inválido.' );
		}

		$handler = new EPD_Elementor_Handler();
		$result  = $handler->retry_webhook( $submission_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( isset( $result['error'] ) ? $result['error'] : 'Erro no webhook.' );
		}
	}

	// -------------------------------------------------------------------------

	/**
	 * Retorna todos os formulários Elementor Pro encontrados nos posts/páginas.
	 */
	public function get_all_elementor_forms() {
		$forms = array();

		$posts = get_posts( array(
			'post_type'      => array( 'page', 'post', 'elementor_library' ),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_key'       => '_elementor_data',
		) );

		foreach ( $posts as $post ) {
			$data = get_post_meta( $post->ID, '_elementor_data', true );

			if ( empty( $data ) ) {
				continue;
			}

			$elements = json_decode( $data, true );

			if ( ! is_array( $elements ) ) {
				continue;
			}

			$this->find_forms_in_elements( $elements, $forms );
		}

		return $forms;
	}

	private function find_forms_in_elements( array $elements, array &$forms ) {
		foreach ( $elements as $element ) {
			if (
				isset( $element['widgetType'] ) &&
				$element['widgetType'] === 'form' &&
				isset( $element['settings'] )
			) {
				$settings  = $element['settings'];

				// O Elementor Pro dispara o hook com o valor de get_form_settings('id'),
				// que é settings['id'] quando preenchido, ou o _id do widget como fallback.
				// Salvamos o mesmo valor para garantir o match na busca do mapeamento.
				$form_id   = ( isset( $settings['id'] ) && $settings['id'] !== '' )
					? $settings['id']
					: ( isset( $element['id'] ) ? $element['id'] : '' );

				$form_name = isset( $settings['form_name'] ) && $settings['form_name'] !== ''
					? $settings['form_name']
					: 'Formulário sem nome';

				$fields = array();
				if ( ! empty( $settings['form_fields'] ) ) {
					foreach ( $settings['form_fields'] as $field ) {
						// custom_id é o que aparece como 'id' no record do Elementor.
						$field_id = '';
						if ( ! empty( $field['custom_id'] ) ) {
							$field_id = $field['custom_id'];
						} elseif ( ! empty( $field['_id'] ) ) {
							$field_id = $field['_id'];
						}

						$fields[] = array(
							'id'    => $field_id,
							'label' => isset( $field['field_label'] ) && $field['field_label'] !== ''
								? $field['field_label']
								: ( isset( $field['placeholder'] ) ? $field['placeholder'] : 'Campo' ),
							'type'  => isset( $field['field_type'] ) ? $field['field_type'] : 'text',
						);
					}
				}

				if ( $form_id ) {
					// Adiciona campos UTM fixos ao final de cada formulário.
					// Eles são injetados pelo epd-utm.js e sempre disponíveis para mapeamento.
					foreach ( $this->get_utm_fields() as $utm_field ) {
						$fields[] = $utm_field;
					}

					$forms[] = array(
						'id'     => $form_id,
						'name'   => $form_name,
						'fields' => $fields,
					);
				}
			}

			if ( ! empty( $element['elements'] ) ) {
				$this->find_forms_in_elements( $element['elements'], $forms );
			}
		}
	}

	/**
	 * Retorna os campos UTM fixos que o plugin captura automaticamente.
	 * Sempre disponíveis para mapeamento em qualquer formulário.
	 */
	public function get_utm_fields() {
		return array(
			array( 'id' => 'epd_utm_source',   'label' => '📍 UTM Source',   'type' => 'utm' ),
			array( 'id' => 'epd_utm_medium',   'label' => '📍 UTM Medium',   'type' => 'utm' ),
			array( 'id' => 'epd_utm_campaign', 'label' => '📍 UTM Campaign', 'type' => 'utm' ),
			array( 'id' => 'epd_utm_term',     'label' => '📍 UTM Term',     'type' => 'utm' ),
			array( 'id' => 'epd_utm_content',  'label' => '📍 UTM Content',  'type' => 'utm' ),
		);
	}

	/**
	 * Retorna os últimos N submissions.
	 */
	public function get_submissions( $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'epd_submissions';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", (int) $limit )
		);
	}

	/**
	 * Retorna todos os mapeamentos salvos no banco.
	 */
	public function get_all_mappings() {
		global $wpdb;
		$table = $wpdb->prefix . 'epd_mappings';
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}

	/**
	 * Retorna um mapeamento pelo ID.
	 */
	public function get_mapping( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'epd_mappings';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}
}
