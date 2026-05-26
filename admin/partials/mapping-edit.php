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

$saved_webhook_field_map = $is_edit && ! empty( $mapping->webhook_field_map )
	? json_decode( $mapping->webhook_field_map, true )
	: array();
if ( ! is_array( $saved_webhook_field_map ) ) {
	$saved_webhook_field_map = array();
}

// Verifica quais integrações estão configuradas nas Configurações globais.
$pipedrive_active = ! empty( get_option( 'epd_api_token', '' ) ) && ! empty( get_option( 'epd_company_domain', '' ) );
$brevo_active     = ! empty( get_option( 'epd_brevo_api_key', '' ) );

// Dados salvos do Brevo para este mapeamento.
$brevo_enabled = $is_edit ? (bool) ( $mapping->brevo_enabled ?? 0 ) : false;
$brevo_list_id = $is_edit && ! empty( $mapping->brevo_list_id ) ? (int) $mapping->brevo_list_id : '';
$saved_brevo_field_map = $is_edit && ! empty( $mapping->brevo_field_map )
	? json_decode( $mapping->brevo_field_map, true )
	: array();
if ( ! is_array( $saved_brevo_field_map ) ) {
	$saved_brevo_field_map = array();
}

$val_defaults = array(
	'phone_validation'        => false,
	'email_block_enabled'     => false,
	'email_block_domains'     => array( 'gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com', 'live.com', 'icloud.com', 'uol.com.br', 'terra.com.br', 'bol.com.br', 'ig.com.br' ),
	'email_block_suffixes'    => array( '.ru', '.online', '.xyz' ),
	'email_block_words'       => array( 'teste', 'malware', 'phish' ),
	'email_msg_domains'       => 'E-mails de domínio público não são permitidos. Por favor, use um e-mail corporativo.',
	'email_msg_suffixes'      => 'O domínio do seu e-mail não é permitido. Por favor, use um e-mail corporativo.',
	'email_msg_words'         => 'O endereço de e-mail informado não é válido. Por favor, use um e-mail corporativo.',
);

$val = $is_edit && ! empty( $mapping->validation_config )
	? array_merge( $val_defaults, json_decode( $mapping->validation_config, true ) ?: array() )
	: $val_defaults;
?>

<div class="wrap epd-wrap">
	<h1>
		<?php echo $is_edit
			? esc_html__( 'Editar Mapeamento', 'elementor-pipedrive' )
			: esc_html__( 'Novo Mapeamento', 'elementor-pipedrive' );
		?>
	</h1>

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
						<p class="description"><?php esc_html_e( 'Selecione o formulário cujos dados serão capturados e processados pelas integrações ativas.', 'elementor-pipedrive' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php if ( ! $pipedrive_active ) : ?>
		<div class="notice notice-info" style="margin:0 0 20px; padding:10px 15px;">
			<p>
				<?php esc_html_e( '⚡ As integrações são controladas pelas ', 'elementor-pipedrive' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=epd-settings' ) ); ?>">
					<?php esc_html_e( 'Configurações do plugin', 'elementor-pipedrive' ); ?>
				</a>.
				<?php esc_html_e( 'Configure as API Keys para ativar Pipedrive e/ou Brevo.', 'elementor-pipedrive' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( $pipedrive_active ) : ?>
		<!-- Pipeline & Stage -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Pipeline e Etapa', 'elementor-pipedrive' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><label for="epd-pipeline-select"><?php esc_html_e( 'Pipeline (Funil)', 'elementor-pipedrive' ); ?></label></th>
					<td>
						<select id="epd-pipeline-select" class="regular-text">
							<option value=""><?php esc_html_e( '— Carregando pipelines... —', 'elementor-pipedrive' ); ?></option>
						</select>
						<input type="hidden" name="pipeline_id" id="epd-pipeline-id" value="<?php echo esc_attr( $is_edit ? $mapping->pipeline_id : '' ); ?>">
					</td>
				</tr>
				<tr>
					<th><label for="epd-stage-select"><?php esc_html_e( 'Etapa (Stage)', 'elementor-pipedrive' ); ?></label></th>
					<td>
						<select id="epd-stage-select" class="regular-text">
							<option value=""><?php esc_html_e( '— Selecione o pipeline primeiro —', 'elementor-pipedrive' ); ?></option>
						</select>
						<input type="hidden" name="stage_id" id="epd-stage-id" value="<?php echo esc_attr( $is_edit ? $mapping->stage_id : '' ); ?>">
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

		<!-- Mapeamento de Campos Pipedrive -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Mapeamento de Campos do Pipedrive', 'elementor-pipedrive' ); ?></h2>
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

		<?php endif; // fim: $pipedrive_active ?>

		<!-- Validações -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Validações', 'elementor-pipedrive' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Telefone', 'elementor-pipedrive' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="phone_validation" value="1" <?php checked( $val['phone_validation'] ); ?>>
							<?php esc_html_e( 'Formatar e validar campos de telefone', 'elementor-pipedrive' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Aplica máscara (99) 99999-9999 e bloqueia envio se incompleto. Para funcionar, o campo no Elementor deve ter o ID customizado "telefone" ou "celular".', 'elementor-pipedrive' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'E-mail corporativo', 'elementor-pipedrive' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="email_block_enabled" value="1" id="epd-email-block-toggle" <?php checked( $val['email_block_enabled'] ); ?>>
							<?php esc_html_e( 'Bloquear e-mails de domínios não-corporativos', 'elementor-pipedrive' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'O campo de e-mail no Elementor deve ter o ID customizado "email".', 'elementor-pipedrive' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div id="epd-email-block-fields" style="<?php echo $val['email_block_enabled'] ? '' : 'display:none;'; ?> margin-top:10px; padding-left:20px; border-left:3px solid #f0f0f1;">
				<table class="form-table" style="margin-top:0;">
					<tr>
						<th style="width:220px;"><label for="epd-email-block-domains"><?php esc_html_e( 'Domínios bloqueados', 'elementor-pipedrive' ); ?></label></th>
						<td>
							<textarea id="epd-email-block-domains" name="email_block_domains" rows="6" class="regular-text" style="font-family:monospace;"><?php echo esc_textarea( implode( "\n", $val['email_block_domains'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Um domínio por linha. Ex: gmail.com', 'elementor-pipedrive' ); ?></p>
							<input type="text" name="email_msg_domains" value="<?php echo esc_attr( $val['email_msg_domains'] ); ?>" class="large-text" style="margin-top:6px;" placeholder="<?php esc_attr_e( 'Mensagem de erro para domínio bloqueado', 'elementor-pipedrive' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="epd-email-block-suffixes"><?php esc_html_e( 'Sufixos bloqueados', 'elementor-pipedrive' ); ?></label></th>
						<td>
							<textarea id="epd-email-block-suffixes" name="email_block_suffixes" rows="4" class="regular-text" style="font-family:monospace;"><?php echo esc_textarea( implode( "\n", $val['email_block_suffixes'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Um sufixo por linha. Ex: .ru', 'elementor-pipedrive' ); ?></p>
							<input type="text" name="email_msg_suffixes" value="<?php echo esc_attr( $val['email_msg_suffixes'] ); ?>" class="large-text" style="margin-top:6px;" placeholder="<?php esc_attr_e( 'Mensagem de erro para sufixo bloqueado', 'elementor-pipedrive' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="epd-email-block-words"><?php esc_html_e( 'Palavras bloqueadas no domínio', 'elementor-pipedrive' ); ?></label></th>
						<td>
							<textarea id="epd-email-block-words" name="email_block_words" rows="4" class="regular-text" style="font-family:monospace;"><?php echo esc_textarea( implode( "\n", $val['email_block_words'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Uma palavra por linha. Bloqueia domínios que contenham este texto. Ex: teste', 'elementor-pipedrive' ); ?></p>
							<input type="text" name="email_msg_words" value="<?php echo esc_attr( $val['email_msg_words'] ); ?>" class="large-text" style="margin-top:6px;" placeholder="<?php esc_attr_e( 'Mensagem de erro para palavra bloqueada', 'elementor-pipedrive' ); ?>">
						</td>
					</tr>
				</table>
			</div>

			<script>
			document.getElementById('epd-email-block-toggle').addEventListener('change', function () {
				document.getElementById('epd-email-block-fields').style.display = this.checked ? '' : 'none';
			});
			</script>
		</div>

		<!-- Webhook -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Webhook (opcional)', 'elementor-pipedrive' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><label for="epd-webhook-url"><?php esc_html_e( 'URL do Webhook', 'elementor-pipedrive' ); ?></label></th>
					<td>
						<input
							type="url"
							id="epd-webhook-url"
							name="webhook_url"
							value="<?php echo esc_attr( $is_edit ? $mapping->webhook_url : '' ); ?>"
							class="large-text"
							placeholder="https://..."
						>
						<p class="description">
							<?php esc_html_e( 'O plugin envia um POST com todos os dados do formulário (e das integrações ativas) para esta URL. Deixe em branco para desativar.', 'elementor-pipedrive' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- Mapeamento de Campos do Webhook -->
			<hr style="margin:20px 0; border:none; border-top:1px solid #f0f0f1;">

			<h3 style="margin-top:0;"><?php esc_html_e( 'Mapeamento de Campos do Webhook (Opcional)', 'elementor-pipedrive' ); ?></h3>
			<p class="description" style="margin-bottom:15px;">
				<?php esc_html_e( 'Renomeie os campos enviados no objeto "form" do webhook. Se um campo não estiver mapeado aqui, será enviado com o nome original do Elementor.', 'elementor-pipedrive' ); ?>
			</p>

			<table class="widefat epd-fields-table" id="epd-webhook-fields-table">
				<thead>
					<tr>
						<th style="width:45%"><?php esc_html_e( 'Campo do Elementor', 'elementor-pipedrive' ); ?></th>
						<th style="width:40%"><?php esc_html_e( 'Chave no Webhook', 'elementor-pipedrive' ); ?></th>
						<th style="width:15%"></th>
					</tr>
				</thead>
				<tbody id="epd-webhook-fields-body">
					<?php foreach ( $saved_webhook_field_map as $wh_row ) : ?>
					<tr class="epd-wh-field-row">
						<td>
							<select name="wh_elementor_field[]" class="epd-wh-elementor-field widefat">
								<option value="<?php echo esc_attr( $wh_row['elementor_field'] ); ?>" selected>
									<?php echo esc_html( $wh_row['elementor_field'] ); ?>
								</option>
							</select>
						</td>
						<td>
							<input type="text" name="wh_webhook_key[]" class="widefat" value="<?php echo esc_attr( $wh_row['webhook_key'] ); ?>" placeholder="<?php esc_attr_e( 'ex: phone_number', 'elementor-pipedrive' ); ?>">
						</td>
						<td>
							<button type="button" class="button button-small epd-remove-wh-row">
								<?php esc_html_e( 'Remover', 'elementor-pipedrive' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top:15px;">
				<button type="button" id="epd-add-wh-row" class="button">
					<?php esc_html_e( '+ Adicionar mapeamento', 'elementor-pipedrive' ); ?>
				</button>
			</p>
		</div>

		<?php if ( $brevo_active ) : ?>
		<!-- Brevo -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Brevo (opcional)', 'elementor-pipedrive' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Habilitar Brevo', 'elementor-pipedrive' ); ?></th>
					<td>
						<label>
							<input
								type="checkbox"
								name="brevo_enabled"
								id="epd-brevo-toggle"
								value="1"
								<?php checked( $brevo_enabled ); ?>
							>
							<?php esc_html_e( 'Criar/atualizar contato no Brevo ao receber este formulário', 'elementor-pipedrive' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<div id="epd-brevo-fields" style="<?php echo $brevo_enabled ? '' : 'display:none;'; ?> margin-top:15px; padding-left:20px; border-left:3px solid #f0f0f1;">

				<table class="form-table" style="margin-top:0;">
					<tr>
						<th style="width:220px;">
							<label for="epd-brevo-list-id"><?php esc_html_e( 'ID da Lista Brevo', 'elementor-pipedrive' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="epd-brevo-list-id"
								name="brevo_list_id"
								value="<?php echo esc_attr( $brevo_list_id ); ?>"
								class="small-text"
								min="1"
								placeholder="ex: 11"
							>
							<p class="description">
								<?php esc_html_e( 'Encontre em: Brevo → Contatos → Listas. Deixe em branco para não adicionar a nenhuma lista.', 'elementor-pipedrive' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<hr style="margin:20px 0; border:none; border-top:1px solid #f0f0f1;">

				<h3 style="margin-top:0;"><?php esc_html_e( 'Mapeamento de Campos do Brevo', 'elementor-pipedrive' ); ?></h3>
				<p class="description" style="margin-bottom:15px;">
					<?php esc_html_e( 'Mapeie os campos do formulário para os atributos do Brevo. O campo de e-mail é obrigatório (use o atributo: EMAIL).', 'elementor-pipedrive' ); ?>
				</p>

				<table class="widefat epd-fields-table" id="epd-brevo-fields-table">
					<thead>
						<tr>
							<th style="width:45%"><?php esc_html_e( 'Campo do Elementor', 'elementor-pipedrive' ); ?></th>
							<th style="width:40%"><?php esc_html_e( 'Atributo Brevo', 'elementor-pipedrive' ); ?></th>
							<th style="width:15%"></th>
						</tr>
					</thead>
					<tbody id="epd-brevo-fields-body">
						<?php foreach ( $saved_brevo_field_map as $brevo_row ) : ?>
						<tr class="epd-brevo-field-row">
							<td>
								<select name="brevo_elementor_field[]" class="epd-brevo-elementor-field widefat">
									<option value="<?php echo esc_attr( $brevo_row['elementor_field'] ); ?>" selected>
										<?php echo esc_html( $brevo_row['elementor_field'] ); ?>
									</option>
								</select>
							</td>
							<td>
								<input
									type="text"
									name="brevo_attribute[]"
									class="widefat"
									value="<?php echo esc_attr( $brevo_row['brevo_attribute'] ); ?>"
									placeholder="<?php esc_attr_e( 'ex: EMAIL, FNAME, LNAME, SMS', 'elementor-pipedrive' ); ?>"
								>
							</td>
							<td>
								<button type="button" class="button button-small epd-remove-brevo-row">
									<?php esc_html_e( 'Remover', 'elementor-pipedrive' ); ?>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top:15px;">
					<button type="button" id="epd-add-brevo-row" class="button">
						<?php esc_html_e( '+ Adicionar campo', 'elementor-pipedrive' ); ?>
					</button>
				</p>

			</div>

			<script>
			document.getElementById('epd-brevo-toggle').addEventListener('change', function () {
				document.getElementById('epd-brevo-fields').style.display = this.checked ? '' : 'none';
			});
			</script>
		</div>
		<?php endif; // fim: $brevo_active ?>

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

<!-- Template de linha do webhook -->
<template id="epd-wh-row-template">
	<tr class="epd-wh-field-row">
		<td>
			<select name="wh_elementor_field[]" class="epd-wh-elementor-field widefat">
				<option value=""><?php esc_html_e( '— Campo do formulário —', 'elementor-pipedrive' ); ?></option>
			</select>
		</td>
		<td>
			<input type="text" name="wh_webhook_key[]" class="widefat" placeholder="<?php esc_attr_e( 'ex: phone_number', 'elementor-pipedrive' ); ?>">
		</td>
		<td>
			<button type="button" class="button button-small epd-remove-wh-row">
				<?php esc_html_e( 'Remover', 'elementor-pipedrive' ); ?>
			</button>
		</td>
	</tr>
</template>

<!-- Template de linha do Brevo -->
<template id="epd-brevo-row-template">
	<tr class="epd-brevo-field-row">
		<td>
			<select name="brevo_elementor_field[]" class="epd-brevo-elementor-field widefat">
				<option value=""><?php esc_html_e( '— Campo do formulário —', 'elementor-pipedrive' ); ?></option>
			</select>
		</td>
		<td>
			<input type="text" name="brevo_attribute[]" class="widefat" placeholder="<?php esc_attr_e( 'ex: EMAIL, FNAME, LNAME, SMS', 'elementor-pipedrive' ); ?>">
		</td>
		<td>
			<button type="button" class="button button-small epd-remove-brevo-row">
				<?php esc_html_e( 'Remover', 'elementor-pipedrive' ); ?>
			</button>
		</td>
	</tr>
</template>

<script>
// Dados iniciais para re-hidratação ao editar
var epdEditData = {
	formId:             '<?php echo esc_js( $is_edit ? $mapping->form_id : '' ); ?>',
	pipelineId:         '<?php echo esc_js( $is_edit && $pipedrive_active ? (string) $mapping->pipeline_id : '' ); ?>',
	stageId:            '<?php echo esc_js( $is_edit && $pipedrive_active ? (string) $mapping->stage_id : '' ); ?>',
	savedRows:          <?php echo wp_json_encode( $pipedrive_active ? $saved_mappings : array() ); ?>,
	savedWebhookFields: <?php echo wp_json_encode( $saved_webhook_field_map ); ?>,
	savedBrevoFields:   <?php echo wp_json_encode( $saved_brevo_field_map ); ?>,
	pipedriveActive:    <?php echo $pipedrive_active ? 'true' : 'false'; ?>,
	brevoActive:        <?php echo $brevo_active ? 'true' : 'false'; ?>
};
</script>
