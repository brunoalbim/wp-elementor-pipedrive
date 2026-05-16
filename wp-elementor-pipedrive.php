<?php
/**
 * Plugin Name: Elementor Pipedrive Integration
 * Plugin URI:  https://bruno.art.br
 * Description: Captura dados de formulários Elementor Pro e cria Pessoa, Empresa e Negociação no Pipedrive.
 * Version:     1.4.4
 * Author:      Bruno Albim
 * Author URI:  https://bruno.art.br
 * License:     GPL-2.0+
 * Text Domain: elementor-pipedrive
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPD_VERSION', '1.4.4' );
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

	new EPD_Admin();
	new EPD_Elementor_Handler();
}
add_action( 'plugins_loaded', 'epd_init' );

function epd_enqueue_frontend_scripts() {
	wp_enqueue_script(
		'epd-utm',
		EPD_PLUGIN_URL . 'public/js/epd-utm.js',
		array(),
		EPD_VERSION,
		false // carrega no <head> para capturar UTMs o mais cedo possível
	);
}
add_action( 'wp_enqueue_scripts', 'epd_enqueue_frontend_scripts' );
