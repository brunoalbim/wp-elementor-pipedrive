<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPD_Activator {

	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_mappings = "CREATE TABLE {$wpdb->prefix}epd_mappings (
			id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id             VARCHAR(100)        NOT NULL,
			form_name           VARCHAR(255)        NOT NULL DEFAULT '',
			pipeline_id         BIGINT(20)          NOT NULL DEFAULT 0,
			stage_id            BIGINT(20)          NOT NULL DEFAULT 0,
			deal_title          VARCHAR(255)        NOT NULL DEFAULT 'Lead',
			mappings            LONGTEXT            NOT NULL DEFAULT '',
			webhook_url         VARCHAR(2048)       NOT NULL DEFAULT '',
			webhook_field_map   LONGTEXT            NOT NULL DEFAULT '',
			brevo_enabled       TINYINT(1)          NOT NULL DEFAULT 0,
			brevo_list_id       INT(11)             NULL DEFAULT NULL,
			brevo_field_map     LONGTEXT            NOT NULL DEFAULT '',
			validation_config   LONGTEXT            NOT NULL DEFAULT '',
			active              TINYINT(1)          NOT NULL DEFAULT 1,
			created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
			pipedrive_status VARCHAR(20)         NOT NULL DEFAULT 'skipped',
			pipedrive_result LONGTEXT            NOT NULL DEFAULT '',
			webhook_status   VARCHAR(20)         NOT NULL DEFAULT 'skipped',
			webhook_url      VARCHAR(2048)       NOT NULL DEFAULT '',
			webhook_result   VARCHAR(255)        NOT NULL DEFAULT '',
			brevo_status     VARCHAR(20)         NOT NULL DEFAULT 'skipped',
			brevo_result     VARCHAR(255)        NOT NULL DEFAULT '',
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

		// Upgrade: adiciona colunas do Brevo para instalações existentes (< 1.7.0).
		$has_brevo_mapping = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}epd_mappings LIKE 'brevo_enabled'"
		);
		if ( empty( $has_brevo_mapping ) ) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}epd_mappings
				 ADD COLUMN brevo_enabled   TINYINT(1)  NOT NULL DEFAULT 0   AFTER webhook_field_map,
				 ADD COLUMN brevo_list_id   INT(11)     NULL     DEFAULT NULL AFTER brevo_enabled,
				 ADD COLUMN brevo_field_map LONGTEXT    NOT NULL DEFAULT ''   AFTER brevo_list_id"
			);
		}

		$has_brevo_submission = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}epd_submissions LIKE 'brevo_status'"
		);
		if ( empty( $has_brevo_submission ) ) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}epd_submissions
				 ADD COLUMN brevo_status  VARCHAR(20)  NOT NULL DEFAULT 'skipped' AFTER webhook_result,
				 ADD COLUMN brevo_result  VARCHAR(255) NOT NULL DEFAULT ''        AFTER brevo_status"
			);
		}

		// Upgrade: ajusta pipedrive_status default para 'skipped' em novas colunas (< 1.7.0).
		$webhook_result_col = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}epd_submissions LIKE 'webhook_result'"
		);
		if ( ! empty( $webhook_result_col ) ) {
			$col_def = reset( $webhook_result_col );
			if ( isset( $col_def->Type ) && strtolower( $col_def->Type ) === 'varchar(20)' ) {
				$wpdb->query(
					"ALTER TABLE {$wpdb->prefix}epd_submissions
					 MODIFY COLUMN webhook_result VARCHAR(255) NOT NULL DEFAULT ''"
				);
			}
		}
	}

	public static function deactivate() {
		// Intencional: não remove dados ao desativar (apenas ao desinstalar).
	}
}
