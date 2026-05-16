<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
/** @var EPD_Admin $this */
$submissions = $this->get_submissions( 100 );
?>

<div class="wrap epd-wrap">
	<h1><?php esc_html_e( 'Elementor Pipedrive — Envios', 'elementor-pipedrive' ); ?></h1>

	<div class="epd-card">
		<div class="epd-card-header">
			<h2><?php esc_html_e( 'Últimos 100 envios', 'elementor-pipedrive' ); ?></h2>
		</div>

		<?php if ( empty( $submissions ) ) : ?>
			<p class="epd-empty"><?php esc_html_e( 'Nenhum envio registrado ainda.', 'elementor-pipedrive' ); ?></p>
		<?php else : ?>
			<table class="widefat fixed striped epd-table epd-submissions-table">
				<thead>
					<tr>
						<th style="width:140px;"><?php esc_html_e( 'Data/Hora', 'elementor-pipedrive' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Formulário', 'elementor-pipedrive' ); ?></th>
						<th style="width:200px;"><?php esc_html_e( 'Pipedrive', 'elementor-pipedrive' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Webhook', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Ações', 'elementor-pipedrive' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $submissions as $sub ) :
						$pd_result   = $sub->pipedrive_result ? json_decode( $sub->pipedrive_result, true ) : array();
						$stored_data = $sub->submitted_data ? json_decode( $sub->submitted_data, true ) : array();
					?>
					<tr class="epd-submission-row" data-id="<?php echo (int) $sub->id; ?>">
						<td>
							<code><?php echo esc_html( substr( $sub->created_at, 0, 16 ) ); ?></code>
						</td>
						<td>
							<?php echo esc_html( $sub->form_name ?: $sub->form_id ); ?>
						</td>
						<td class="epd-pd-cell">
							<?php echo epd_pipedrive_badge( $sub->pipedrive_status, $pd_result ); ?>
						</td>
						<td class="epd-wh-cell">
							<?php echo epd_webhook_badge( $sub->webhook_status, $sub->webhook_result, $sub->webhook_url ); ?>
						</td>
						<td class="epd-actions-cell">
							<?php if ( $sub->pipedrive_status === 'error' || $sub->pipedrive_status === 'pending' ) : ?>
								<button
									type="button"
									class="button button-small epd-retry-pipedrive"
									data-id="<?php echo (int) $sub->id; ?>"
								>&#8635; <?php esc_html_e( 'Retentar Pipedrive', 'elementor-pipedrive' ); ?></button>
							<?php elseif ( ! empty( $sub->webhook_url ) && ( $sub->webhook_status === 'error' || $sub->webhook_status === 'pending' ) ) : ?>
								<button
									type="button"
									class="button button-small epd-retry-webhook"
									data-id="<?php echo (int) $sub->id; ?>"
								>&#8635; <?php esc_html_e( 'Retentar Webhook', 'elementor-pipedrive' ); ?></button>
							<?php endif; ?>

							<?php if ( ! empty( $stored_data ) ) : ?>
								<button
									type="button"
									class="button button-small epd-toggle-details"
									data-id="<?php echo (int) $sub->id; ?>"
									style="margin-left:4px;"
								><?php esc_html_e( 'Detalhes', 'elementor-pipedrive' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>

					<?php if ( ! empty( $stored_data ) ) : ?>
					<tr class="epd-details-row" id="epd-details-<?php echo (int) $sub->id; ?>" style="display:none;">
						<td colspan="5" style="background:#f9f9f9; padding:12px 16px;">
							<?php if ( ! empty( $stored_data['fields'] ) ) : ?>
								<strong><?php esc_html_e( 'Campos do formulário:', 'elementor-pipedrive' ); ?></strong>
								<ul style="margin:6px 0 12px 16px;">
									<?php foreach ( $stored_data['fields'] as $k => $v ) :
										if ( strpos( $k, 'epd_utm' ) === 0 ) continue;
									?>
										<li><code><?php echo esc_html( $k ); ?></code>: <?php echo esc_html( $v ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( ! empty( $stored_data['utms'] ) ) : ?>
								<strong><?php esc_html_e( 'UTMs:', 'elementor-pipedrive' ); ?></strong>
								<ul style="margin:6px 0 0 16px;">
									<?php foreach ( $stored_data['utms'] as $k => $v ) : ?>
										<li><code><?php echo esc_html( $k ); ?></code>: <?php echo esc_html( $v ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</td>
					</tr>
					<?php endif; ?>

					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<?php
function epd_pipedrive_badge( $status, $result ) {
	$deal_id = isset( $result['deal_id'] ) ? $result['deal_id'] : null;

	switch ( $status ) {
		case 'success':
			$label = $deal_id ? "Deal #{$deal_id}" : __( 'Enviado', 'elementor-pipedrive' );
			return '<span class="epd-badge epd-badge-active">✓ ' . esc_html( $label ) . '</span>';
		case 'error':
			$error = isset( $result['error'] ) ? $result['error'] : __( 'Erro', 'elementor-pipedrive' );
			return '<span class="epd-badge epd-badge-inactive" title="' . esc_attr( $error ) . '">✗ ' . esc_html( __( 'Erro', 'elementor-pipedrive' ) ) . '</span>';
		case 'pending':
			return '<span class="epd-badge epd-badge-pending">⏳ ' . esc_html__( 'Pendente', 'elementor-pipedrive' ) . '</span>';
		default:
			return '<span class="epd-badge epd-badge-inactive">—</span>';
	}
}

function epd_webhook_badge( $status, $result, $url ) {
	if ( empty( $url ) ) {
		return '<span class="epd-badge epd-badge-inactive">—</span>';
	}

	switch ( $status ) {
		case 'success':
			return '<span class="epd-badge epd-badge-active">✓ HTTP ' . esc_html( $result ) . '</span>';
		case 'error':
			return '<span class="epd-badge epd-badge-inactive" title="' . esc_attr( $result ) . '">✗ ' . esc_html( $result ?: __( 'Erro', 'elementor-pipedrive' ) ) . '</span>';
		case 'pending':
			return '<span class="epd-badge epd-badge-pending">⏳ ' . esc_html__( 'Pendente', 'elementor-pipedrive' ) . '</span>';
		default:
			return '<span class="epd-badge epd-badge-inactive">—</span>';
	}
}
?>
