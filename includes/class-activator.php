<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Activator {

	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_mappings = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}epd_mappings (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id     VARCHAR(100)        NOT NULL,
			form_name   VARCHAR(255)        NOT NULL DEFAULT '',
			pipeline_id BIGINT(20)          NOT NULL,
			stage_id    BIGINT(20)          NOT NULL,
			deal_title  VARCHAR(255)        NOT NULL DEFAULT 'Lead',
			mappings    LONGTEXT            NOT NULL,
			active      TINYINT(1)          NOT NULL DEFAULT 1,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}epd_logs (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			message    TEXT                NOT NULL,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_mappings );
		dbDelta( $sql_logs );

		update_option( 'epd_version', EPD_VERSION );
	}

	public static function deactivate() {
		// Intencional: não remove dados ao desativar (apenas ao desinstalar).
	}
}
