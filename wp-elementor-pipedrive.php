<?php
/**
 * Plugin Name: Elementor Pipedrive Integration
 * Plugin URI:  https://bruno.art.br
 * Description: Captura dados de formulários Elementor Pro e cria Pessoa, Empresa e Negociação no Pipedrive.
 * Version:     1.8.2
 * Author:      Bruno Albim
 * Author URI:  https://bruno.art.br
 * License:     GPL-2.0+
 * Text Domain: elementor-pipedrive
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPD_VERSION', '1.8.2' );
define( 'EPD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EPD_PLUGIN_FILE', __FILE__ );

require_once EPD_PLUGIN_DIR . 'includes/class-activator.php';
require_once EPD_PLUGIN_DIR . 'includes/class-pipedrive-api.php';
require_once EPD_PLUGIN_DIR . 'includes/class-brevo-api.php';
require_once EPD_PLUGIN_DIR . 'includes/class-field-mapper.php';
require_once EPD_PLUGIN_DIR . 'includes/class-elementor-handler.php';
require_once EPD_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'EPD_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EPD_Activator', 'deactivate' ) );

function epd_init() {
	load_plugin_textdomain( 'elementor-pipedrive', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Roda upgrade do banco sempre que a versão salva for diferente da atual.
	// Isso garante que atualizações de plugin (sem reativação) também migrem o schema.
	if ( get_option( 'epd_version' ) !== EPD_VERSION ) {
		EPD_Activator::activate();
	}

	new EPD_Admin();
	new EPD_Elementor_Handler();
}
add_action( 'plugins_loaded', 'epd_init' );

function epd_get_validation_map() {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT form_id, validation_config FROM {$wpdb->prefix}epd_mappings WHERE active = 1"
	);

	$map = array();
	foreach ( $rows as $row ) {
		if ( empty( $row->validation_config ) ) {
			continue;
		}
		$config = json_decode( $row->validation_config, true );
		if ( ! is_array( $config ) ) {
			continue;
		}
		// Somente inclui se ao menos uma validação estiver ativa.
		if ( empty( $config['phone_validation'] ) && empty( $config['email_block_enabled'] ) ) {
			continue;
		}
		$map[ $row->form_id ] = array(
			'phone'           => ! empty( $config['phone_validation'] ),
			'emailBlock'      => ! empty( $config['email_block_enabled'] ),
			'emailDomains'    => isset( $config['email_block_domains'] )  ? $config['email_block_domains']  : array(),
			'emailSuffixes'   => isset( $config['email_block_suffixes'] ) ? $config['email_block_suffixes'] : array(),
			'emailWords'      => isset( $config['email_block_words'] )    ? $config['email_block_words']    : array(),
			'emailMsgDomains' => ! empty( $config['email_msg_domains'] )  ? $config['email_msg_domains']  : 'E-mails de domínio público não são permitidos. Por favor, use um e-mail corporativo.',
			'emailMsgSuffixes'=> ! empty( $config['email_msg_suffixes'] ) ? $config['email_msg_suffixes'] : 'O domínio do seu e-mail não é permitido. Por favor, use um e-mail corporativo.',
			'emailMsgWords'   => ! empty( $config['email_msg_words'] )    ? $config['email_msg_words']    : 'O endereço de e-mail informado não é válido. Por favor, use um e-mail corporativo.',
		);
	}
	return $map;
}

function epd_enqueue_frontend_scripts() {
	wp_enqueue_script(
		'epd-utm',
		EPD_PLUGIN_URL . 'public/js/epd-utm.js',
		array(),
		EPD_VERSION,
		false // carrega no <head> para capturar UTMs o mais cedo possível
	);

	$validation_map = epd_get_validation_map();

	// Só carrega o script se houver ao menos um mapeamento com validação ativa.
	if ( ! empty( $validation_map ) ) {
		wp_enqueue_script(
			'epd-form-validation',
			EPD_PLUGIN_URL . 'public/js/epd-form-validation.js',
			array(),
			EPD_VERSION,
			true // footer — depende do DOM
		);
		wp_localize_script( 'epd-form-validation', 'epdValidation', array( 'forms' => $validation_map ) );
	}
}
add_action( 'wp_enqueue_scripts', 'epd_enqueue_frontend_scripts' );
