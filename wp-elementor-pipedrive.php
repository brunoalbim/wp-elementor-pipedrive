<?php
/**
 * Plugin Name: Elementor Pipedrive Integration
 * Plugin URI:  https://bruno.art.br
 * Description: Captura dados de formulários Elementor Pro e cria Pessoa, Empresa e Negociação no Pipedrive.
 * Version:     1.2.0
 * Author:      Bruno Albim
 * Author URI:  https://bruno.art.br
 * License:     GPL-2.0+
 * Text Domain: elementor-pipedrive
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPD_VERSION', '1.2.0' );
define( 'EPD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EPD_PLUGIN_FILE', __FILE__ );

require_once EPD_PLUGIN_DIR . 'includes/class-activator.php';
require_once EPD_PLUGIN_DIR . 'includes/class-pipedrive-api.php';
require_once EPD_PLUGIN_DIR . 'includes/class-field-mapper.php';
require_once EPD_PLUGIN_DIR . 'includes/class-elementor-handler.php';
require_once EPD_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'EPD_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EPD_Activator', 'deactivate' ) );

function epd_init() {
	load_plugin_textdomain( 'elementor-pipedrive', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	epd_maybe_upgrade_schema();

	new EPD_Admin();
	new EPD_Elementor_Handler();
}
add_action( 'plugins_loaded', 'epd_init' );

/**
 * Aplica alterações de schema sem exigir desativar/reativar o plugin.
 * dbDelta não adiciona colunas em tabelas existentes, então fazemos
 * ALTER TABLE explícito verificando se a coluna já existe.
 */
function epd_maybe_upgrade_schema() {
	if ( get_option( 'epd_version' ) === EPD_VERSION ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'epd_mappings';

	// Adiciona webhook_url se não existir (introduzida na v1.1.0).
	$col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'webhook_url'" );
	if ( empty( $col ) ) {
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `webhook_url` VARCHAR(2048) NOT NULL DEFAULT ''" );
	}

	// Garante que as tabelas base existam (nova instalação ou tabela de logs).
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	EPD_Activator::activate();
}
