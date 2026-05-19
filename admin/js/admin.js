/* globals epdData, epdEditData, jQuery */
(function ($) {
	'use strict';

	var EPD = {

		// Cache de campos por entidade para evitar requisições repetidas.
		_fieldsCache: {},

		// Campos do formulário Elementor selecionado.
		_formFields: [],

		init: function () {
			this.bindSettingsPage();
			this.bindMappingPage();
			this.bindSubmissionsPage();

			if (typeof epdEditData !== 'undefined') {
				this.initEditPage();
			}
		},

		// -------------------------------------------------------------------------
		// Settings page
		// -------------------------------------------------------------------------

		bindSettingsPage: function () {
			$(document).on('click', '#epd-test-connection', function () {
				var $btn = $(this);
				var $result = $('#epd-connection-result');

				$btn.prop('disabled', true).text(epdData.i18n.loading);
				$result.removeClass('success error').text('');

				$.post(epdData.ajaxUrl, {
					action: 'epd_test_connection',
					nonce: epdData.nonce,
				}, function (response) {
					if (response.success) {
						$result.addClass('success').text(epdData.i18n.connectionOk + ' ' + response.data.message);
					} else {
						$result.addClass('error').text(epdData.i18n.connectionFail + response.data);
					}
				}).fail(function () {
					$result.addClass('error').text(epdData.i18n.connectionFail + 'Erro de rede.');
				}).always(function () {
					$btn.prop('disabled', false).text('Testar Conexão');
				});
			});
		},

		// -------------------------------------------------------------------------
		// Mapping list page
		// -------------------------------------------------------------------------

		bindMappingPage: function () {
			$(document).on('click', '.epd-delete-btn', function (e) {
				if (!confirm(epdData.i18n.confirmDelete)) {
					e.preventDefault();
				}
			});
		},

		// -------------------------------------------------------------------------
		// Submissions page
		// -------------------------------------------------------------------------

		bindSubmissionsPage: function () {
			var self = this;

			// Toggle linha de detalhes.
			$(document).on('click', '.epd-toggle-details', function () {
				var id = $(this).data('id');
				$('#epd-details-' + id).toggle();
			});

			// Retentar Pipedrive.
			$(document).on('click', '.epd-retry-pipedrive', function () {
				var $btn = $(this);
				var id   = $btn.data('id');
				var $row = $btn.closest('tr');

				$btn.addClass('is-loading').text('Enviando...');

				$.post(epdData.ajaxUrl, {
					action: 'epd_retry_pipedrive',
					nonce: epdData.nonce,
					submission_id: id,
				}, function (response) {
					if (response.success) {
						var dealId = response.data.deal_id ? ' Deal #' + response.data.deal_id : '';
						$row.find('.epd-pd-cell').html('<span class="epd-badge epd-badge-active">✓ Enviado' + self.esc(dealId) + '</span>');

						if (response.data.webhook_fired) {
							var whHtml = response.data.webhook_ok
								? '<span class="epd-badge epd-badge-active">✓ HTTP ' + self.esc(String(response.data.webhook_result)) + '</span>'
								: '<span class="epd-badge epd-badge-inactive">✗ Erro</span>';
							$row.find('.epd-wh-cell').html(whHtml);
						}

						$btn.remove();
					} else {
						$row.find('.epd-pd-cell').html('<span class="epd-badge epd-badge-inactive">✗ Erro</span>');
						$btn.removeClass('is-loading').text('↺ Retentar Pipedrive');
						alert('Erro: ' + response.data);
					}
				}).fail(function () {
					$btn.removeClass('is-loading').text('↺ Retentar Pipedrive');
					alert('Erro de conexão.');
				});
			});

			// Retentar Webhook.
			$(document).on('click', '.epd-retry-webhook', function () {
				var $btn = $(this);
				var id   = $btn.data('id');
				var $row = $btn.closest('tr');

				$btn.addClass('is-loading').text('Enviando...');

				$.post(epdData.ajaxUrl, {
					action: 'epd_retry_webhook',
					nonce: epdData.nonce,
					submission_id: id,
				}, function (response) {
					if (response.success) {
						var code = response.data.http_code || '200';
						$row.find('.epd-wh-cell').html('<span class="epd-badge epd-badge-active">✓ HTTP ' + self.esc(String(code)) + '</span>');
						$btn.remove();
					} else {
						$row.find('.epd-wh-cell').html('<span class="epd-badge epd-badge-inactive">✗ Erro</span>');
						$btn.removeClass('is-loading').text('↺ Retentar Webhook');
						alert('Erro: ' + response.data);
					}
				}).fail(function () {
					$btn.removeClass('is-loading').text('↺ Retentar Webhook');
					alert('Erro de conexão.');
				});
			});
		},

		// -------------------------------------------------------------------------
		// Mapping edit page
		// -------------------------------------------------------------------------

		initEditPage: function () {
			var self = this;

			// Carrega formulários Elementor e inicializa o select.
			self.loadElementorForms(function (forms) {
				self._forms = forms;
				var $select = $('#epd-form-select');
				$select.empty().append('<option value="">' + '— Selecione o formulário —' + '</option>');

				forms.forEach(function (form) {
					var selected = (form.id === epdEditData.formId) ? ' selected' : '';
					$select.append('<option value="' + form.id + '"' + selected + '>' + self.esc(form.name) + '</option>');
				});

				if (epdEditData.formId) {
					self.onFormSelected(epdEditData.formId);
				}
			});

			// Carrega pipelines e inicializa.
			self.loadPipelines(function () {
				if (epdEditData.pipelineId) {
					$('#epd-pipeline-select').val(epdEditData.pipelineId);
					$('#epd-pipeline-id').val(epdEditData.pipelineId);
					$('#epd-pipeline-select').trigger('change');
				}
			});

			// Eventos de formulário selecionado.
			$(document).on('change', '#epd-form-select', function () {
				var formId = $(this).val();
				var formName = $(this).find('option:selected').text();
				$('#epd-form-id').val(formId);
				$('#epd-form-name').val(formName);
				self.onFormSelected(formId);
			});

			// Eventos de pipeline selecionado.
			$(document).on('change', '#epd-pipeline-select', function () {
				var pipelineId = $(this).val();
				$('#epd-pipeline-id').val(pipelineId);
				$('#epd-stage-id').val(''); // limpa stage ao trocar pipeline
				self.loadStages(pipelineId, function () {
					if (epdEditData.stageId) {
						$('#epd-stage-select').val(epdEditData.stageId);
						$('#epd-stage-id').val(epdEditData.stageId);
						epdEditData.stageId = null; // só aplica uma vez.
					}
				});
			});

			// Eventos de stage selecionado.
			$(document).on('change', '#epd-stage-select', function () {
				$('#epd-stage-id').val($(this).val());
			});

			// Adicionar nova linha de mapeamento.
			$(document).on('click', '#epd-add-row', function () {
				self.addFieldRow();
			});

			// Remover linha.
			$(document).on('click', '.epd-remove-row', function () {
				$(this).closest('tr').remove();
			});

			// Adicionar linha do webhook field map.
			$(document).on('click', '#epd-add-wh-row', function () {
				self.addWebhookFieldRow();
			});

			// Remover linha do webhook field map.
			$(document).on('click', '.epd-remove-wh-row', function () {
				$(this).closest('tr').remove();
			});

			// Mudança de entidade → recarrega campos do Pipedrive.
			$(document).on('change', '.epd-entity', function () {
				var $row = $(this).closest('tr');
				var entity = $(this).val();
				self.loadPipedriveFieldsInRow($row, entity);
			});
		},

		onFormSelected: function (formId) {
			var self = this;

			if (!formId) {
				self._formFields = [];
				self.refreshElementorFieldSelects();
				return;
			}

			// Encontra campos do form selecionado.
			var form = (self._forms || []).find(function (f) { return f.id === formId; });

			if (form) {
				self._formFields = form.fields;
				self.refreshElementorFieldSelects();
			} else {
				// Busca via AJAX se não encontrou no cache.
				$.post(epdData.ajaxUrl, {
					action: 'epd_get_form_fields',
					nonce: epdData.nonce,
					form_id: formId,
				}, function (response) {
					if (response.success) {
						self._formFields = response.data;
						self.refreshElementorFieldSelects();
					}
				});
			}
		},

		refreshElementorFieldSelects: function () {
			var self = this;
			var fields = self._formFields;

			$('.epd-elementor-field, .epd-wh-elementor-field').each(function () {
				var $select = $(this);
				var currentVal = $select.val();

				$select.empty().append('<option value="">' + epdData.i18n.selectField + '</option>');

				fields.forEach(function (field) {
					var selected = (field.id === currentVal) ? ' selected' : '';
					$select.append('<option value="' + self.esc(field.id) + '"' + selected + '>' + self.esc(field.label) + ' (' + self.esc(field.id) + ')' + '</option>');
				});
			});
		},

		addFieldRow: function () {
			var self = this;
			var $template = $('#epd-row-template');

			if (!$template.length) { return; }

			var $clone = $($template.innerHTML || $template[0].innerHTML);
			$('#epd-fields-body').append($clone);

			// Preenche campos do Elementor no select recém-criado.
			var $newRow = $('#epd-fields-body tr:last-child');
			var $efSelect = $newRow.find('.epd-elementor-field');
			$efSelect.empty().append('<option value="">' + epdData.i18n.selectField + '</option>');

			self._formFields.forEach(function (field) {
				$efSelect.append('<option value="' + self.esc(field.id) + '">' + self.esc(field.label) + ' (' + self.esc(field.id) + ')' + '</option>');
			});
		},

		addWebhookFieldRow: function () {
			var self = this;
			var $template = $('#epd-wh-row-template');

			if (!$template.length) { return; }

			var $clone = $($template[0].innerHTML);
			$('#epd-webhook-fields-body').append($clone);

			var $newRow = $('#epd-webhook-fields-body tr:last-child');
			var $efSelect = $newRow.find('.epd-wh-elementor-field');
			$efSelect.empty().append('<option value="">' + epdData.i18n.selectField + '</option>');

			self._formFields.forEach(function (field) {
				$efSelect.append('<option value="' + self.esc(field.id) + '">' + self.esc(field.label) + ' (' + self.esc(field.id) + ')' + '</option>');
			});
		},

		loadPipedriveFieldsInRow: function ($row, entity) {
			var self = this;
			var $select = $row.find('.epd-pipedrive-field');
			var savedValue = $select.data('saved-value') || $select.val();

			if (!entity) {
				$select.empty().append('<option value="">' + epdData.i18n.selectEntity + '</option>');
				return;
			}

			if (self._fieldsCache[entity]) {
				self.populatePipedriveSelect($select, self._fieldsCache[entity], savedValue);
				return;
			}

			$select.empty().append('<option value="">' + epdData.i18n.loading + '</option>');

			$.post(epdData.ajaxUrl, {
				action: 'epd_get_pipedrive_fields',
				nonce: epdData.nonce,
				entity: entity,
			}, function (response) {
				if (response.success) {
					self._fieldsCache[entity] = response.data;
					self.populatePipedriveSelect($select, response.data, savedValue);
				} else {
					$select.empty().append('<option value="">Erro: ' + response.data + '</option>');
				}
			});
		},

		populatePipedriveSelect: function ($select, fields, selectedValue) {
			var self = this;
			$select.empty().append('<option value="">' + epdData.i18n.selectField + '</option>');

			fields.forEach(function (field) {
				var selected = (field.key === selectedValue) ? ' selected' : '';
				var label = field.name + ' (' + field.key + ')' + (field.is_custom ? ' ★' : '');
				$select.append('<option value="' + self.esc(field.key) + '"' + selected + '>' + self.esc(label) + '</option>');
			});
		},

		// -------------------------------------------------------------------------
		// API loaders
		// -------------------------------------------------------------------------

		loadElementorForms: function (callback) {
			$.post(epdData.ajaxUrl, {
				action: 'epd_get_elementor_forms',
				nonce: epdData.nonce,
			}, function (response) {
				if (response.success) {
					callback(response.data);
				}
			});
		},

		loadPipelines: function (callback) {
			var self = this;
			var $select = $('#epd-pipeline-select');

			$select.empty().append('<option value="">' + epdData.i18n.loading + '</option>');

			$.post(epdData.ajaxUrl, {
				action: 'epd_get_pipelines',
				nonce: epdData.nonce,
			}, function (response) {
				$select.empty().append('<option value="">— Pipeline —</option>');

				if (response.success) {
					response.data.forEach(function (pipeline) {
						$select.append('<option value="' + pipeline.id + '">' + self.esc(pipeline.name) + '</option>');
					});
				}

				if (callback) { callback(); }
			});
		},

		loadStages: function (pipelineId, callback) {
			var self = this;
			var $select = $('#epd-stage-select');

			if (!pipelineId) {
				$select.empty().append('<option value="">— Selecione o pipeline primeiro —</option>');
				return;
			}

			$select.empty().append('<option value="">' + epdData.i18n.loading + '</option>');

			$.post(epdData.ajaxUrl, {
				action: 'epd_get_stages',
				nonce: epdData.nonce,
				pipeline_id: pipelineId,
			}, function (response) {
				$select.empty().append('<option value="">— Etapa —</option>');

				if (response.success) {
					response.data.forEach(function (stage) {
						$select.append('<option value="' + stage.id + '">' + self.esc(stage.name) + '</option>');
					});
				}

				if (callback) { callback(); }
			});
		},

		// -------------------------------------------------------------------------
		// Re-hidratação das linhas salvas ao editar
		// -------------------------------------------------------------------------

		rehydrateSavedRows: function () {
			var self = this;

			if (epdEditData && epdEditData.savedRows && epdEditData.savedRows.length) {
				$('#epd-fields-body .epd-field-row').each(function (i) {
					var $row = $(this);
					var saved = epdEditData.savedRows[i];

					if (!saved) { return; }

					// Marca o valor do campo Pipedrive para ser selecionado após o carregamento.
					$row.find('.epd-pipedrive-field').data('saved-value', saved.pipedrive_field);

					// Aciona o carregamento dos campos do Pipedrive para a entidade salva.
					var $entity = $row.find('.epd-entity');
					$entity.val(saved.entity);
					self.loadPipedriveFieldsInRow($row, saved.entity);
				});
			}

			// Re-hidrata linhas do webhook field map.
			if (epdEditData && epdEditData.savedWebhookFields && epdEditData.savedWebhookFields.length) {
				$('#epd-webhook-fields-body .epd-wh-field-row').each(function (i) {
					var $row = $(this);
					var saved = epdEditData.savedWebhookFields[i];

					if (!saved) { return; }

					var $efSelect = $row.find('.epd-wh-elementor-field');
					// Repopula o select com os campos do formulário (já carregados).
					$efSelect.empty().append('<option value="">' + epdData.i18n.selectField + '</option>');
					self._formFields.forEach(function (field) {
						var selected = (field.id === saved.elementor_field) ? ' selected' : '';
						$efSelect.append('<option value="' + self.esc(field.id) + '"' + selected + '>' + self.esc(field.label) + ' (' + self.esc(field.id) + ')' + '</option>');
					});
				});
			}
		},

		// -------------------------------------------------------------------------
		// Util
		// -------------------------------------------------------------------------

		esc: function (str) {
			if (!str) { return ''; }
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		},
	};

	$(document).ready(function () {
		EPD.init();

		// Re-hidrata após formulários e campos carregarem.
		setTimeout(function () {
			EPD.rehydrateSavedRows();
		}, 1200);
	});

}(jQuery));
