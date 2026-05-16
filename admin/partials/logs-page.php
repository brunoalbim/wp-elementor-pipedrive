<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
global $wpdb;
$logs = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}epd_logs ORDER BY id DESC LIMIT 200"
);
?>

<div class="wrap epd-wrap">
	<h1><?php esc_html_e( 'Elementor Pipedrive — Logs', 'elementor-pipedrive' ); ?></h1>

	<?php if ( isset( $_GET['cleared'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Logs apagados.', 'elementor-pipedrive' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="epd-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-settings' ) ); ?>" class="epd-tab">
			<?php esc_html_e( 'Configurações', 'elementor-pipedrive' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-mappings' ) ); ?>" class="epd-tab">
			<?php esc_html_e( 'Mapeamentos', 'elementor-pipedrive' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-logs' ) ); ?>" class="epd-tab active">
			<?php esc_html_e( 'Logs', 'elementor-pipedrive' ); ?>
		</a>
	</div>

	<div class="epd-card">
		<div class="epd-card-header">
			<h2><?php esc_html_e( 'Últimos 200 registros', 'elementor-pipedrive' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="epd_clear_logs">
				<?php wp_nonce_field( 'epd_clear_logs' ); ?>
				<button type="submit" class="button button-secondary" onclick="return confirm('Apagar todos os logs?')">
					<?php esc_html_e( 'Limpar logs', 'elementor-pipedrive' ); ?>
				</button>
			</form>
		</div>

		<?php if ( empty( $logs ) ) : ?>
			<p class="epd-empty"><?php esc_html_e( 'Nenhum log registrado ainda. Os logs aparecem aqui após o envio de um formulário.', 'elementor-pipedrive' ); ?></p>
		<?php else : ?>
			<table class="widefat fixed striped epd-table">
				<thead>
					<tr>
						<th style="width:160px;"><?php esc_html_e( 'Data/Hora (UTC)', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Mensagem', 'elementor-pipedrive' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><code><?php echo esc_html( $log->created_at ); ?></code></td>
						<td style="word-break:break-all;"><?php echo esc_html( $log->message ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<p class="description">
		<?php esc_html_e( 'Os logs também são gravados no error_log do servidor PHP (prefixo [EPD]).', 'elementor-pipedrive' ); ?>
	</p>
</div>
