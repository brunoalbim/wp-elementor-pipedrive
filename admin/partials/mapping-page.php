<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
/** @var EPD_Admin $this */
$mappings = $this->get_all_mappings();
?>

<div class="wrap epd-wrap">
	<h1><?php esc_html_e( 'Elementor Pipedrive — Mapeamentos', 'elementor-pipedrive' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Mapeamento salvo com sucesso.', 'elementor-pipedrive' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Mapeamento excluído.', 'elementor-pipedrive' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="epd-card">
		<div class="epd-card-header">
			<h2><?php esc_html_e( 'Mapeamentos de Formulários', 'elementor-pipedrive' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-mappings&action=new' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ Novo Mapeamento', 'elementor-pipedrive' ); ?>
			</a>
		</div>

		<?php if ( empty( $mappings ) ) : ?>
			<p class="epd-empty"><?php esc_html_e( 'Nenhum mapeamento criado ainda.', 'elementor-pipedrive' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped epd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Formulário', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Form ID', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Pipeline ID', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Stage ID', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Campos mapeados', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Status', 'elementor-pipedrive' ); ?></th>
						<th><?php esc_html_e( 'Ações', 'elementor-pipedrive' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $mappings as $mapping ) :
						$mapped_count = count( json_decode( $mapping->mappings, true ) ?? array() );
					?>
					<tr>
						<td><strong><?php echo esc_html( $mapping->form_name ?: __( 'Sem nome', 'elementor-pipedrive' ) ); ?></strong></td>
						<td><code><?php echo esc_html( $mapping->form_id ); ?></code></td>
						<td><?php echo esc_html( $mapping->pipeline_id ); ?></td>
						<td><?php echo esc_html( $mapping->stage_id ); ?></td>
						<td><?php echo esc_html( $mapped_count ); ?> <?php esc_html_e( 'campos', 'elementor-pipedrive' ); ?></td>
						<td>
							<?php if ( $mapping->active ) : ?>
								<span class="epd-badge epd-badge-active"><?php esc_html_e( 'Ativo', 'elementor-pipedrive' ); ?></span>
							<?php else : ?>
								<span class="epd-badge epd-badge-inactive"><?php esc_html_e( 'Inativo', 'elementor-pipedrive' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<div style="display:flex; gap:6px; align-items:center; flex-wrap:nowrap;">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-mappings&action=edit&id=' . $mapping->id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Editar', 'elementor-pipedrive' ); ?>
								</a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="epd_duplicate_mapping">
									<input type="hidden" name="mapping_id" value="<?php echo (int) $mapping->id; ?>">
									<?php wp_nonce_field( 'epd_duplicate_mapping' ); ?>
									<button type="submit" class="button button-small">
										<?php esc_html_e( 'Duplicar', 'elementor-pipedrive' ); ?>
									</button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="epd_delete_mapping">
									<input type="hidden" name="mapping_id" value="<?php echo (int) $mapping->id; ?>">
									<?php wp_nonce_field( 'epd_delete_mapping' ); ?>
									<button type="submit" class="button button-small button-link-delete epd-delete-btn">
										<?php esc_html_e( 'Excluir', 'elementor-pipedrive' ); ?>
									</button>
								</form>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
