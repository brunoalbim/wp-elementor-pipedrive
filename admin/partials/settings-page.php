<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap epd-wrap">
	<h1><?php esc_html_e( 'Elementor Pipedrive — Configurações', 'elementor-pipedrive' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Configurações salvas com sucesso.', 'elementor-pipedrive' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="epd_save_settings">
		<?php wp_nonce_field( 'epd_save_settings' ); ?>

		<!-- Pipedrive -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Integração Pipedrive', 'elementor-pipedrive' ); ?></h2>
			<p class="description" style="margin-bottom:15px;">
				<?php esc_html_e( 'Preencha os campos abaixo para ativar a integração com o Pipedrive. Deixe em branco para desativar.', 'elementor-pipedrive' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="epd_company_domain"><?php esc_html_e( 'Company Domain', 'elementor-pipedrive' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="epd_company_domain"
							name="epd_company_domain"
							value="<?php echo esc_attr( get_option( 'epd_company_domain', '' ) ); ?>"
							class="regular-text"
							placeholder="suaempresa.pipedrive.com"
						>
						<p class="description">
							<?php esc_html_e( 'Ex: suaempresa.pipedrive.com (sem https://)', 'elementor-pipedrive' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="epd_api_token"><?php esc_html_e( 'API Token', 'elementor-pipedrive' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="epd_api_token"
							name="epd_api_token"
							value="<?php echo esc_attr( get_option( 'epd_api_token', '' ) ); ?>"
							class="regular-text"
							autocomplete="new-password"
						>
						<p class="description">
							<?php esc_html_e( 'Encontre em: Pipedrive → Configurações → Preferências pessoais → API', 'elementor-pipedrive' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div style="padding:12px 0 4px;">
				<button type="button" id="epd-test-connection" class="button button-secondary">
					<?php esc_html_e( 'Testar Conexão', 'elementor-pipedrive' ); ?>
				</button>
				<span id="epd-connection-result" style="margin-left:10px; vertical-align:middle;"></span>
			</div>
		</div>

		<!-- Brevo -->
		<div class="epd-card">
			<h2><?php esc_html_e( 'Integração Brevo', 'elementor-pipedrive' ); ?></h2>
			<p class="description" style="margin-bottom:15px;">
				<?php esc_html_e( 'Preencha a API Key abaixo para ativar a integração com o Brevo. Deixe em branco para desativar.', 'elementor-pipedrive' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="epd_brevo_api_key"><?php esc_html_e( 'API Key', 'elementor-pipedrive' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="epd_brevo_api_key"
							name="epd_brevo_api_key"
							value="<?php echo esc_attr( get_option( 'epd_brevo_api_key', '' ) ); ?>"
							class="regular-text"
							autocomplete="new-password"
						>
						<p class="description">
							<?php esc_html_e( 'Encontre em: Brevo → Configurações → SMTP & API → Chaves de API', 'elementor-pipedrive' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div style="padding:12px 0 4px;">
				<button type="button" id="epd-test-brevo-connection" class="button button-secondary">
					<?php esc_html_e( 'Testar Conexão', 'elementor-pipedrive' ); ?>
				</button>
				<span id="epd-brevo-connection-result" style="margin-left:10px; vertical-align:middle;"></span>
			</div>
		</div>

		<p class="submit">
			<?php submit_button( __( 'Salvar configurações', 'elementor-pipedrive' ), 'primary', 'submit', false ); ?>
		</p>

	</form>
</div>
