<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Activator {

	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_mappings = "CREATE TABLE {$wpdb->prefix}epd_mappings (
			id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id           VARCHAR(100)        NOT NULL,
			form_name         VARCHAR(255)        NOT NULL DEFAULT '',
			pipeline_id       BIGINT(20)          NOT NULL,
			stage_id          BIGINT(20)          NOT NULL,
			deal_title        VARCHAR(255)        NOT NULL DEFAULT 'Lead',
			mappings          LONGTEXT            NOT NULL,
			webhook_url       VARCHAR(2048)       NOT NULL DEFAULT '',
			validation_config LONGTEXT            NOT NULL DEFAULT '',
			active            TINYINT(1)          NOT NULL DEFAULT 1,
			created_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$wpdb->prefix}epd_logs (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			message    TEXT                NOT NULL,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset_collate};";

		$sql_submissions = "CREATE TABLE {$wpdb->prefix}epd_submissions (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id          VARCHAR(100)        NOT NULL,
			form_name        VARCHAR(255)        NOT NULL DEFAULT '',
			submitted_data   LONGTEXT            NOT NULL,
			pipedrive_status VARCHAR(20)         NOT NULL DEFAULT 'pending',
			pipedrive_result LONGTEXT            NOT NULL DEFAULT '',
			webhook_status   VARCHAR(20)         NOT NULL DEFAULT 'skipped',
			webhook_url      VARCHAR(2048)       NOT NULL DEFAULT '',
			webhook_result   VARCHAR(20)         NOT NULL DEFAULT '',
			created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY pipedrive_status (pipedrive_status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_mappings );
		dbDelta( $sql_logs );
		dbDelta( $sql_submissions );

		update_option( 'epd_version', EPD_VERSION );
	}

	public static function deactivate() {
		// Intencional: não remove dados ao desativar (apenas ao desinstalar).
	}
}
