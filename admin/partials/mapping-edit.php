<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
/** @var EPD_Admin $this */
$mapping_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$mapping    = $mapping_id ? $this->get_mapping( $mapping_id ) : null;
$is_edit    = (bool) $mapping;

$saved_mappings = $is_edit ? json_decode( $mapping->mappings, true ) : array();
if ( ! is_array( $saved_mappings ) ) {
	$saved_mappings = array();
}
?>

<div class="wrap epd-wrap">
	<h1>
		<?php echo $is_edit
			? esc_html__( 'Editar Mapeamento', 'elementor-pipedrive' )
			: esc_html__( 'Novo Mapeamento', 'elementor-pipedrive' );
		?>
	</h1>

	<div class="epd-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-settings' ) ); ?>" class="epd-tab">
			<?php esc_html_e( 'Configurações', 'elementor-pipedrive' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-mappings' ) ); ?>" class="epd-tab active">
			<?php esc_html_e( 'Mapeamentos', 'elementor-pipedrive' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-logs' ) ); ?>" class="epd-tab">
			<?php esc_html_e( 'Logs', 'elementor-pipedrive' ); ?>
		</a>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="epd-mapping-form">
		<input type="hidden" name="action" value="epd_save_mapping">
		<input type="hidden" name="mapping_id" value="<?php echo $mapping_id; ?>">
		<?php wp_nonce_field( 'epd_save_mapping' ); ?>

		<!-- Formulário Elementor -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Formulário Elementor', 'elementor-pipedrive' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><label for="epd-form-select"><?php esc_html_e( 'Selecionar Formulário', 'elementor-pipedrive' ); ?></label></th>
					<td>
						<select id="epd-form-select" class="regular-text">
							<option value=""><?php esc_html_e( '— Carregando formulários... —', 'elementor-pipedrive' ); ?></option>
						</select>
						<input type="hidden" name="form_id" id="epd-form-id" value="<?php echo esc_attr( $is_edit ? $mapping->form_id : '' ); ?>">
						<input type="hidden" name="form_name" id="epd-form-name" value="<?php echo esc_attr( $is_edit ? $mapping->form_name : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Selecione o formulário cujos dados serão enviados ao Pipedrive.', 'elementor-pipedrive' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Pipeline & Stage -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Pipeline e Etapa', 'elementor-pipedrive' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><label for="epd-pipeline-select"><?php esc_html_e( 'Pipeline (Funil)', 'elementor-pipedrive' ); ?></label></th>
					<td>
						<select id="epd-pipeline-select" name="pipeline_id" class="regular-text" required>
							<option value=""><?php esc_html_e( '— Carregando pipelines... —', 'elementor-pipedrive' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="epd-stage-select"><?php esc_html_e( 'Etapa (Stage)', 'elementor-pipedrive' ); ?></label></th>
					<td>
						<select id="epd-stage-select" name="stage_id" class="regular-text" required>
							<option value=""><?php esc_html_e( '— Selecione o pipeline primeiro —', 'elementor-pipedrive' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="epd-deal-title"><?php esc_html_e( 'Título da Negociação', 'elementor-pipedrive' ); ?></label></th>
					<td>
						<input
							type="text"
							id="epd-deal-title"
							name="deal_title"
							value="<?php echo esc_attr( $is_edit ? $mapping->deal_title : 'Lead: {nome}' ); ?>"
							class="regular-text"
						>
						<p class="description">
							<?php esc_html_e( 'Use {id_do_campo} para inserir valores dinâmicos. Ex: Lead: {nome}', 'elementor-pipedrive' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Mapeamento de Campos -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Mapeamento de Campos', 'elementor-pipedrive' ); ?></h2>
			<p class="description" style="margin-bottom:15px;">
				<?php esc_html_e( 'Relacione cada campo do formulário Elementor com o campo correspondente no Pipedrive. Nem todos os campos precisam ser mapeados.', 'elementor-pipedrive' ); ?>
			</p>

			<table class="widefat epd-fields-table" id="epd-fields-table">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e( 'Campo do Elementor', 'elementor-pipedrive' ); ?></th>
						<th style="width:20%"><?php esc_html_e( 'Destino', 'elementor-pipedrive' ); ?></th>
						<th style="width:35%"><?php esc_html_e( 'Campo do Pipedrive', 'elementor-pipedrive' ); ?></th>
						<th style="width:15%"></th>
					</tr>
				</thead>
				<tbody id="epd-fields-body">
					<?php if ( ! empty( $saved_mappings ) ) : ?>
						<?php foreach ( $saved_mappings as $row ) : ?>
						<tr class="epd-field-row">
							<td>
								<select name="elementor_field[]" class="epd-elementor-field widefat">
									<option value="<?php echo esc_attr( $row['elementor_field'] ); ?>" selected>
										<?php echo esc_html( $row['elementor_field'] ); ?>
									</option>
								</select>
							</td>
							<td>
								<select name="entity[]" class="epd-entity widefat">
									<option value="person" <?php selected( $row['entity'], 'person' ); ?>><?php esc_html_e( 'Pessoa', 'elementor-pipedrive' ); ?></option>
									<option value="organization" <?php selected( $row['entity'], 'organization' ); ?>><?php esc_html_e( 'Empresa', 'elementor-pipedrive' ); ?></option>
									<option value="deal" <?php selected( $row['entity'], 'deal' ); ?>><?php esc_html_e( 'Negociação', 'elementor-pipedrive' ); ?></option>
								</select>
							</td>
							<td>
								<select name="pipedrive_field[]" class="epd-pipedrive-field widefat">
									<option value="<?php echo esc_attr( $row['pipedrive_field'] ); ?>" selected>
										<?php echo esc_html( $row['pipedrive_field'] ); ?>
									</option>
								</select>
							</td>
							<td>
								<button type="button" class="button button-small epd-remove-row">
									<?php esc_html_e( 'Remover', 'elementor-pipedrive' ); ?>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<p style="margin-top:15px;">
				<button type="button" id="epd-add-row" class="button">
					<?php esc_html_e( '+ Adicionar campo', 'elementor-pipedrive' ); ?>
				</button>
			</p>
		</div>

		<p class="submit">
			<?php submit_button( $is_edit ? __( 'Atualizar Mapeamento', 'elementor-pipedrive' ) : __( 'Salvar Mapeamento', 'elementor-pipedrive' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-mappings' ) ); ?>" class="button button-secondary" style="margin-left:10px;">
				<?php esc_html_e( 'Cancelar', 'elementor-pipedrive' ); ?>
			</a>
		</p>
	</form>
</div>

<!-- Template de linha (hidden) -->
<template id="epd-row-template">
	<tr class="epd-field-row">
		<td>
			<select name="elementor_field[]" class="epd-elementor-field widefat">
				<option value=""><?php esc_html_e( '— Campo do formulário —', 'elementor-pipedrive' ); ?></option>
			</select>
		</td>
		<td>
			<select name="entity[]" class="epd-entity widefat">
				<option value=""><?php esc_html_e( '— Destino —', 'elementor-pipedrive' ); ?></option>
				<option value="person"><?php esc_html_e( 'Pessoa', 'elementor-pipedrive' ); ?></option>
				<option value="organization"><?php esc_html_e( 'Empresa', 'elementor-pipedrive' ); ?></option>
				<option value="deal"><?php esc_html_e( 'Negociação', 'elementor-pipedrive' ); ?></option>
			</select>
		</td>
		<td>
			<select name="pipedrive_field[]" class="epd-pipedrive-field widefat">
				<option value=""><?php esc_html_e( '— Selecione a entidade primeiro —', 'elementor-pipedrive' ); ?></option>
			</select>
		</td>
		<td>
			<button type="button" class="button button-small epd-remove-row">
				<?php esc_html_e( 'Remover', 'elementor-pipedrive' ); ?>
			</button>
		</td>
	</tr>
</template>

<script>
// Dados iniciais para re-hidratação ao editar
var epdEditData = {
	formId:     '<?php echo esc_js( $is_edit ? $mapping->form_id : '' ); ?>',
	pipelineId: '<?php echo esc_js( $is_edit ? (string) $mapping->pipeline_id : '' ); ?>',
	stageId:    '<?php echo esc_js( $is_edit ? (string) $mapping->stage_id : '' ); ?>',
	savedRows:  <?php echo wp_json_encode( $saved_mappings ); ?>
};
</script>
