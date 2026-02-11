/**
 * Admin JavaScript for Users Toolkit
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// Limpiar selección de roles
		$('#users-toolkit-clear-roles').on('click', function() {
			$('#users-toolkit-user-roles option:selected').prop('selected', false);
		});

		// Manejar checkbox "Cualquier tipo de contenido"
		// Si se selecciona "any", deseleccionar los específicos y viceversa
		$(document).on('change', '.users-toolkit-post-type-any', function() {
			var $container = $(this).closest('[id^="users-toolkit-post-types"]');
			var isChecked = $(this).prop('checked');
			
			if (isChecked) {
				// Deseleccionar todos los tipos específicos en este contenedor
				$container.find('.users-toolkit-post-type-specific').prop('checked', false);
			}
		});

		$(document).on('change', '.users-toolkit-post-type-specific', function() {
			var $container = $(this).closest('[id^="users-toolkit-post-types"]');
			var $anyCheckbox = $container.find('.users-toolkit-post-type-any');
			
			// Si se selecciona un tipo específico, deseleccionar "any"
			if ($(this).prop('checked')) {
				$anyCheckbox.prop('checked', false);
			}
		});

		// Identify spam users
		$('#users-toolkit-identify-spam').on('click', function() {
			var $button = $(this);
				var $results = $('#users-toolkit-identify-results');
				var $content = $('#users-toolkit-identify-content');
				var $progressContainer = $('#users-toolkit-progress-container');
				var $progressBar = $('#users-toolkit-progress-bar');
				var $progressMsg = $('#users-toolkit-progress-message');
				var $progressLog = $('#users-toolkit-progress-log');
				var operationId = null;
				var pollInterval = null;
				var pollErrors = 0;

				$button.prop('disabled', true).html('<span class="users-toolkit-loading"></span> ' + usersToolkit.strings.identifying);
				$results.hide();
				$progressContainer.show();
				$progressLog.html('');
				$progressLog.removeData('usersToolkitLastMessage').removeData('usersToolkitLastPercent');
				updateProgress($progressBar, $progressMsg, $progressLog, 0, usersToolkit.strings.identifying);

			// Función de polling para obtener progreso
			function startPolling(operation_id) {
				operationId = operation_id;
				pollInterval = setInterval(function() {
					$.ajax({
						url: usersToolkit.ajaxurl,
						type: 'POST',
						data: {
							action: 'users_toolkit_get_progress',
							nonce: usersToolkit.nonce,
							operation_id: operation_id
						},
							success: function(response) {
								if (response.success && response.data) {
									pollErrors = 0;
									updateProgress($progressBar, $progressMsg, $progressLog, response.data.current, response.data.message);
									if (response.data.completed) {
										clearInterval(pollInterval);
										$progressContainer.hide();

										if (response.data.data && response.data.data.error) {
											showError($content, response.data.message || usersToolkit.strings.error);
											$results.removeClass('success warning info').addClass('error').show();
											return;
										}
										
										// Verificar si hay resultados - mejor manejo de la estructura de datos
										var count = 0;
										var loadFile = null;
									
									// Intentar obtener count y file_json de diferentes ubicaciones posibles
									if (response.data.data) {
										count = response.data.data.count !== undefined ? response.data.data.count : 0;
										loadFile = response.data.data.file_json || null;
									}
									if (count === 0 && response.data.count !== undefined) {
										count = response.data.count;
									}
									if (!loadFile && response.data.file_json) {
										loadFile = response.data.file_json;
									}
									if (!loadFile && response.data.file) {
										// Si solo hay file, intentar construir file_json
										var fileBase = response.data.file.replace(/\.txt$/, '');
										loadFile = fileBase + '.json';
									}
									
									if (count === 0 && !loadFile) {
										// No hay resultados
										var html = '<div class="users-toolkit-message info">';
										html += '<p><strong>' + (usersToolkit.strings.no_results || 'No se encontraron resultados') + '</strong></p>';
										html += '<p>' + (response.data.message || 'No se encontraron usuarios que coincidan con los criterios seleccionados.') + '</p>';
										html += '<p style="font-size: 13px; color: #646970; margin-top: 10px;">';
										html += '<em>💡 Sugerencias:</em><ul style="margin: 10px 0; padding-left: 20px;">';
										html += '<li>Intenta seleccionar solo un rol (ej: "Suscriptor" o "Administrador") para ver todos los usuarios de ese rol</li>';
										html += '<li>Ajusta los criterios de búsqueda o selecciona diferentes opciones</li>';
										html += '<li>Si solo quieres ver usuarios por rol, no selecciones otros criterios</li>';
										html += '</ul></p>';
										html += '</div>';
										
										$content.html(html);
										$results.removeClass('error warning success').addClass('info').show();
									} else if (loadFile) {
										// Hay resultados, redirigir a la lista (incluso si count es 0, puede haber archivo)
										setTimeout(function() {
											window.location.href = '?page=users-toolkit-spam&load_file=' + encodeURIComponent(loadFile);
										}, 2000);
									} else {
										// Caso especial: hay count pero no file_json, mostrar mensaje
										var html = '<div class="users-toolkit-message success">';
										html += '<p><strong>' + usersToolkit.strings.success + ':</strong> ' + (response.data.message || 'Operación completada') + '</p>';
										html += '<p>' + count + ' usuarios encontrados.</p>';
										html += '<p style="font-size: 13px; color: #646970;">Revisa la carpeta wp-content/uploads/users-toolkit/ para encontrar el archivo JSON generado.</p>';
										html += '</div>';
										
											$content.html(html);
											$results.removeClass('error warning').addClass('success').show();
										}
									}
								} else if (response.success === false) {
									pollErrors++;
									if (pollErrors < 15) {
										return;
									}
									clearInterval(pollInterval);
									$progressContainer.hide();
									showError($content, response.data && response.data.message ? response.data.message : usersToolkit.strings.error);
									$results.removeClass('success warning info').addClass('error').show();
								}
							},
							error: function() {
								pollErrors++;
								if (pollErrors < 15) {
									return;
								}
								clearInterval(pollInterval);
								$progressContainer.hide();
								showError($content, 'No se pudo consultar el progreso de la búsqueda. Revisa conectividad del servidor y logs PHP.');
								$results.removeClass('success warning info').addClass('error').show();
							}
						});
					}, 2000);
				}

			// Obtener criterios seleccionados
			var criteriaPositive = [];
			$('input[name="criteria_positive[]"]:checked').each(function() {
				var val = $(this).val();
				if (val) {
					criteriaPositive.push(val);
				}
			});
			var criteriaNegative = [];
			$('input[name="criteria_negative[]"]:checked').each(function() {
				var val = $(this).val();
				if (val) {
					criteriaNegative.push(val);
				}
			});
			
			// Obtener tipos de post seleccionados
			var postTypesPositive = [];
			$('input[name="post_types_positive[]"]:checked').each(function() {
				var val = $(this).val();
				if (val) {
					postTypesPositive.push(val);
				}
			});
			var postTypesNegative = [];
			$('input[name="post_types_negative[]"]:checked').each(function() {
				var val = $(this).val();
				if (val) {
					postTypesNegative.push(val);
				}
			});
			
			// Obtener roles seleccionados
			var userRoles = [];
			$('#users-toolkit-user-roles option:selected').each(function() {
				var val = $(this).val();
				if (val) {
					userRoles.push(val);
				}
			});
			
				var matchAll = $('#users-toolkit-match-all').prop('checked') ? '1' : '0';

				operationId = 'spam_identify_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
				startPolling(operationId);

				$.ajax({
					url: usersToolkit.ajaxurl,
					type: 'POST',
					timeout: 600000, // 10 minutos
					data: {
						action: 'users_toolkit_identify_spam',
						nonce: usersToolkit.nonce,
						operation_id: operationId,
						criteria_positive: criteriaPositive,
						criteria_negative: criteriaNegative,
						post_types_positive: postTypesPositive,
						post_types_negative: postTypesNegative,
						user_roles: userRoles,
						match_all: matchAll
					},
					success: function(response) {
						if (response.success) {
							if (response.data.operation_id) {
								if (response.data.operation_id !== operationId) {
									clearInterval(pollInterval);
									startPolling(response.data.operation_id);
								}
								operationId = response.data.operation_id;
								updateProgress($progressBar, $progressMsg, $progressLog, 3, response.data.message || 'Operación iniciada. Verificando progreso...');
							} else {
								// Operación completada inmediatamente
								clearInterval(pollInterval);
								$progressContainer.hide();
							
							// Obtener count y file_json con mejor manejo
							var count = response.data.count !== undefined ? response.data.count : 0;
							var loadFile = response.data.file_json || (response.data.file ? response.data.file.replace(/\.txt$/, '') + '.json' : null);
							
							// Si no hay resultados ni archivo, mostrar mensaje informativo
							if ((count === 0 || !count) && !loadFile) {
								var html = '<div class="users-toolkit-message info">';
								html += '<p><strong>' + (usersToolkit.strings.no_results || 'No se encontraron resultados') + '</strong></p>';
								html += '<p>' + (response.data.message || 'No se encontraron usuarios que coincidan con los criterios seleccionados.') + '</p>';
								html += '<p style="font-size: 13px; color: #646970; margin-top: 10px;">';
								html += '<em>Intenta ajustar los criterios de búsqueda o seleccionar diferentes opciones.</em>';
								html += '</p>';
								html += '</div>';
								
								$content.html(html);
								$results.removeClass('error warning success').addClass('info').show();
							} else {
								// Hay resultados o archivo generado
								var html = '<div class="users-toolkit-message success">';
								html += '<p><strong>' + usersToolkit.strings.success + ':</strong> ' + (response.data.message || 'Operación completada') + '</p>';
								if (count > 0) {
									html += '<p>' + count + ' usuarios spam encontrados.</p>';
								} else {
									html += '<p>Lista generada correctamente.</p>';
								}
								html += '</div>';

								if (loadFile) {
									html += '<p><a href="?page=users-toolkit-spam&load_file=' + encodeURIComponent(loadFile) + '" class="button">Ver lista completa</a></p>';
									setTimeout(function() {
										window.location.href = '?page=users-toolkit-spam&load_file=' + encodeURIComponent(loadFile);
									}, 1500);
								}

								$content.html(html);
								$results.removeClass('error warning').addClass('success').show();
							}
							}
						} else {
							clearInterval(pollInterval);
							$progressContainer.hide();
							showError($content, response.data.message || usersToolkit.strings.error);
							$results.removeClass('success warning').addClass('error').show();
						}
					},
					error: function(xhr, status) {
						if (status === 'timeout') {
							updateProgress($progressBar, $progressMsg, $progressLog, 3, 'La solicitud inicial tardó demasiado, pero la búsqueda puede seguir en segundo plano. Monitoreando progreso...');
							var timeoutHtml = '<div class="users-toolkit-message warning">';
							timeoutHtml += '<p>La conexión inicial excedió el tiempo límite.</p>';
							timeoutHtml += '<p>La búsqueda puede seguir ejecutándose en segundo plano; mantén esta pantalla abierta para ver el progreso.</p>';
							timeoutHtml += '</div>';
							$content.html(timeoutHtml);
							$results.removeClass('error success').addClass('warning').show();
							return;
						}

						updateProgress($progressBar, $progressMsg, $progressLog, 3, 'Hubo un error de comunicación al iniciar, intentando recuperar progreso...');
						var warnHtml = '<div class="users-toolkit-message warning">';
						warnHtml += '<p>Error de comunicación al iniciar la búsqueda.</p>';
						warnHtml += '<p>Se seguirá consultando el progreso por unos instantes.</p>';
						warnHtml += '</div>';
						$content.html(warnHtml);
						$results.removeClass('error success').addClass('warning').show();
					},
				complete: function() {
					// No deshabilitar botón hasta que termine la operación
					setTimeout(function() {
						$button.prop('disabled', false).text('Identificar Usuarios Spam');
					}, 3000);
				}
			});
		});

		// NUEVO SISTEMA: Selector de acciones unificado
		// Definir función antes de usarla
		function updateActionButton() {
			var action = $('#users-toolkit-action-selector').val();
			var $button = $('#users-toolkit-execute-action');
			var $info = $('#users-toolkit-action-info');
			var $warningText = $('#users-toolkit-action-warning-text');
			
			if ($button.length === 0) {
				console.warn('Users Toolkit: Botón de ejecutar acción no encontrado');
				return;
			}
			
			// Si no hay acción seleccionada, deshabilitar botón
			if (!action) {
				$button.prop('disabled', true).text('Ejecutar Acción');
				$info.hide();
				return;
			}
			
			// Verificar usuarios disponibles
			var applyToAll = $('#users-toolkit-apply-to-all').prop('checked');
			var userIds = applyToAll ? getAllUserIds() : getSelectedUserIds();
			var count = userIds.length;
			
			// Si no hay usuarios seleccionados y no está marcado "aplicar a todos", deshabilitar
			if (!applyToAll && count === 0) {
				$button.prop('disabled', true);
				$info.show();
				if ($warningText.length) {
					$warningText.text('Por favor, selecciona al menos un usuario o marca "Aplicar a TODOS".');
				}
				return;
			}
			
			// Habilitar botón y actualizar texto según acción
			$button.prop('disabled', false);
			$info.show();
			
			if (action === 'simulate') {
				$button.removeClass('button-danger').addClass('button-secondary').text('🔍 Simular Acción');
				if ($warningText.length) {
					$warningText.text('Esta acción NO borrará usuarios. Solo mostrará qué pasaría si se ejecutara la acción de borrado.');
				}
			} else if (action === 'export') {
				$button.removeClass('button-danger').addClass('button-primary').text('📥 Exportar Usuarios');
				if ($warningText.length) {
					$warningText.text('Se exportarán ' + count + ' usuario(s) con todos sus metadatos a un archivo JSON.');
				}
			} else if (action === 'delete') {
				$button.removeClass('button-secondary button-primary').addClass('button-danger').text('🗑️ Eliminar Usuarios');
				if ($warningText.length) {
					$warningText.html('<strong style="color: #d63638;">⚠️ PELIGRO:</strong> Se eliminarán PERMANENTEMENTE ' + count + ' usuario(s). Esta acción NO se puede deshacer fácilmente.');
				}
			}
		}
		
		// Select all checkbox - solo seleccionar usuarios visibles
		$(document).off('change.users-toolkit', '#users-toolkit-select-all, .users-toolkit-select-all-checkbox').on('change.users-toolkit', '#users-toolkit-select-all, .users-toolkit-select-all-checkbox', function() {
			var $table = $('#users-toolkit-spam-table');
			if ($table.length === 0) {
				return;
			}

			var isChecked = $(this).prop('checked');
			var $visibleCheckboxes = $table.find('tbody tr:visible .spam-user-checkbox');

			if ($visibleCheckboxes.length === 0) {
				$(this).prop('checked', false).prop('indeterminate', false);
				updateVisibleCount();
				return;
			}

			$visibleCheckboxes.prop('checked', isChecked);

			// Actualizar contador y estado del checkbox
			updateVisibleCount();

			// Actualizar botón después de seleccionar/deseleccionar todos
			if (!$('#users-toolkit-apply-to-all').prop('checked')) {
				updateActionButton();
			}
		});
		
		// Actualizar botón cuando cambia el selector o el checkbox - usar delegación de eventos
		$(document).on('change', '#users-toolkit-action-selector', function() {
			updateActionButton();
		});
		
		$(document).on('change', '#users-toolkit-apply-to-all', function() {
			// Si se marca "aplicar a todos", desmarcar todos los checkboxes individuales
			if ($(this).prop('checked')) {
				$('.spam-user-checkbox').prop('checked', false);
			}
			updateActionButton();
		});
		
		// Cuando se selecciona/deselecciona un usuario, actualizar botón y checkbox "Seleccionar todos"
		$(document).on('change', '.spam-user-checkbox', function() {
			// Actualizar estado del checkbox "Seleccionar todos"
			updateVisibleCount();
			// Solo actualizar si no está marcado "aplicar a todos"
			if (!$('#users-toolkit-apply-to-all').prop('checked')) {
				updateActionButton();
			}
		});
		
		// Inicializar botón al cargar la página (ya estamos dentro de document.ready)
		// Esperar un momento para que la tabla se cargue completamente
		setTimeout(function() {
			updateActionButton();
			showPendingDeleteResumeNotice();
		}, 500);
		
		// Ejecutar acción
		$('#users-toolkit-execute-action').on('click', function() {
			var action = $('#users-toolkit-action-selector').val();
			var applyToAll = $('#users-toolkit-apply-to-all').prop('checked');
			var userIds = applyToAll ? getAllUserIds() : getSelectedUserIds();
			var count = userIds.length;
			
			if (!action) {
				alert('Por favor, selecciona una acción primero.');
				return;
			}
			
			if (!applyToAll && count === 0) {
				alert('Por favor, selecciona al menos un usuario o marca "Aplicar a TODOS".');
				return;
			}
			
			if (action === 'simulate') {
				// SIMULACIÓN: Confirmación simple
				if (!confirm('🔍 SIMULACIÓN\n\nVas a SIMULAR la eliminación de ' + count + ' usuario(s).\n\nEsto NO eliminará ningún usuario, solo mostrará qué pasaría.\n\n¿Continuar con la simulación?')) {
					return;
				}
				deleteUsers(userIds, true); // dry_run = true
				
			} else if (action === 'export') {
				// EXPORTAR: Sin confirmación crítica
				exportUsers(userIds);
				
			} else if (action === 'delete') {
				// BORRAR: Confirmaciones múltiples y explícitas
				var scope = applyToAll ? 'TODOS los usuarios de la lista' : 'los usuarios seleccionados';
				
				if (!confirm('⚠️ ADVERTENCIA CRÍTICA\n\nEstás a punto de ELIMINAR PERMANENTEMENTE ' + count + ' usuario(s) (' + scope + ').\n\nEsta acción NO se puede deshacer fácilmente.\n\n¿Estás completamente seguro?')) {
					return;
				}
				
				// Segunda confirmación
				if (!confirm('🚨 CONFIRMACIÓN FINAL\n\nÚltima oportunidad de cancelar.\n\n¿Realmente deseas eliminar ' + count + ' usuario(s)?\n\nEscribe "ELIMINAR" para continuar (o cualquier otra cosa para cancelar).')) {
					return;
				}
				
				// Tercera confirmación con prompt
				var confirmation = prompt('🚨 CONFIRMACIÓN FINAL\n\nPara confirmar, escribe exactamente: ELIMINAR\n\n(En mayúsculas, sin espacios adicionales)');
				if (confirmation !== 'ELIMINAR') {
					alert('Operación cancelada. No se eliminó ningún usuario.');
					return;
				}
				
				deleteUsers(userIds, false); // dry_run = false
			}
		});

		// Auto-load stats checkbox
		$('#users-toolkit-auto-load-stats').on('change', function() {
			var autoLoad = $(this).prop('checked');
			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_save_auto_load_pref',
					nonce: usersToolkit.nonce,
					auto_load: autoLoad
				}
			});
			if (!autoLoad) {
				$('#users-toolkit-db-stats-container').hide();
			}
		});

		// Load statistics
		$('#users-toolkit-load-stats').on('click', function() {
			var $button = $(this);
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> Cargando...');
			
			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_load_stats',
					nonce: usersToolkit.nonce
				},
				success: function(response) {
					if (response.success && response.data.stats) {
						var stats = response.data.stats;
						$('#users-toolkit-transients-count').text(stats.transients_count.toLocaleString());
						$('#users-toolkit-spam-comments-count').text(stats.spam_comments.toLocaleString());
						$('#users-toolkit-trash-comments-count').text(stats.trash_comments.toLocaleString());
						$('#users-toolkit-transients-stats, #users-toolkit-comments-stats').show();
						$('#users-toolkit-db-stats-container').show();
					}
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + usersToolkit.strings.reload_stats || 'Recargar Estadísticas');
				}
			});
		});

		// Preview buttons (dry run)
		$(document).on('click', '.users-toolkit-preview-btn', function() {
			var actionType = $(this).data('action');
			var $button = $(this);
			var $results = $('#users-toolkit-db-results');
			var $content = $('#users-toolkit-db-content');

			$button.prop('disabled', true).html('<span class="users-toolkit-loading"></span> ' + usersToolkit.strings.previewing || 'Previsualizando...');
			$results.hide();

			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_preview_db',
					nonce: usersToolkit.nonce,
					action_type: actionType
				},
				success: function(response) {
					if (response.success && response.data.results) {
						var html = '<div class="users-toolkit-message info" style="background: #e5f5fa; border-left: 4px solid #2271b1; padding: 15px; margin: 10px 0;">';
						html += '<h4 style="margin-top: 0;">' + (usersToolkit.strings.preview_results || 'Resultados de Previsualización') + '</h4>';
						
						var results = response.data.results;
						
						if (results.transients) {
							html += '<div style="margin: 15px 0; padding: 10px; background: #fff; border-radius: 4px;">';
							html += '<strong>Transients:</strong><ul>';
							html += '<li>Transients expirados: ' + results.transients.expired_transients.toLocaleString() + '</li>';
							html += '<li>Transients huérfanos: ' + results.transients.orphaned_transients.toLocaleString() + '</li>';
							html += '<li>Site transients expirados: ' + results.transients.expired_site_transients.toLocaleString() + '</li>';
							html += '<li>Site transients huérfanos: ' + results.transients.orphaned_site_transients.toLocaleString() + '</li>';
							html += '<li><strong>Total a eliminar: ' + results.transients.total.toLocaleString() + '</strong></li>';
							html += '</ul></div>';
							
							// Update stats display
							$('#users-toolkit-transients-count').text(results.transients.transients_count.toLocaleString());
							$('#users-toolkit-transients-stats').show();
						}
						
						if (results.comments) {
							html += '<div style="margin: 15px 0; padding: 10px; background: #fff; border-radius: 4px;">';
							html += '<strong>Comentarios:</strong><ul>';
							html += '<li>Comentarios spam: ' + results.comments.spam.toLocaleString() + '</li>';
							html += '<li>Comentarios en papelera: ' + results.comments.trash.toLocaleString() + '</li>';
							html += '<li><strong>Total a eliminar: ' + results.comments.total.toLocaleString() + '</strong></li>';
							html += '</ul></div>';
							
							// Update stats display
							$('#users-toolkit-spam-comments-count').text(results.comments.spam.toLocaleString());
							$('#users-toolkit-trash-comments-count').text(results.comments.trash.toLocaleString());
							$('#users-toolkit-comments-stats').show();
						}
						
						if (results.cron) {
							html += '<div style="margin: 15px 0; padding: 10px; background: #fff; border-radius: 4px;">';
							html += '<strong>Cron:</strong><ul>';
							html += '<li>Eventos cron obsoletos: ' + results.cron.deleted.toLocaleString() + '</li>';
							html += '<li>Total de eventos: ' + results.cron.total.toLocaleString() + '</li>';
							if (results.cron.obsolete_events && results.cron.obsolete_events.length > 0) {
								html += '<li>Ejemplos de eventos a eliminar:<ul>';
								results.cron.obsolete_events.slice(0, 5).forEach(function(event) {
									html += '<li>' + event.date + ' - ' + (event.hooks.length > 0 ? event.hooks[0] : 'N/A') + '</li>';
								});
								html += '</ul></li>';
							}
							html += '</ul></div>';
							
							// Update stats display
							$('#users-toolkit-cron-count').text(results.cron.deleted.toLocaleString());
							$('#users-toolkit-cron-stats').show();
						}
						
						if (results.autoload) {
							html += '<div style="margin: 15px 0; padding: 10px; background: #fff; border-radius: 4px;">';
							html += '<strong>Opciones Autoloaded:</strong><ul>';
							html += '<li>Total de opciones autoloaded: ' + results.autoload.total_autoloaded.toLocaleString() + '</li>';
							html += '<li>Tamaño total: ' + results.autoload.total_size_mb.toLocaleString() + ' MB</li>';
							if (results.autoload.large_options && results.autoload.large_options.length > 0) {
								html += '<li>Top 5 opciones más grandes:<ul>';
								results.autoload.large_options.slice(0, 5).forEach(function(option) {
									html += '<li><code>' + option.option_name + '</code> - ' + parseFloat(option.size_kb).toFixed(2) + ' KB</li>';
								});
								html += '</ul></li>';
							}
							html += '</ul></div>';
							
							// Update stats display
							$('#users-toolkit-autoload-total').text(results.autoload.total_autoloaded.toLocaleString());
							$('#users-toolkit-autoload-size-mb').text(results.autoload.total_size_mb.toLocaleString());
							$('#users-toolkit-autoload-stats').show();
						}
						
						html += '<p style="margin-top: 15px; font-size: 13px; color: #646970;"><em>Esta es solo una previsualización. No se han realizado cambios en la base de datos.</em></p>';
						html += '</div>';
						
						$content.html(html);
						$results.removeClass('error warning success').addClass('info').show();
					} else {
						showError($content, response.data.message || usersToolkit.strings.error);
						$results.removeClass('success warning info').addClass('error').show();
					}
				},
				error: function() {
					showError($content, usersToolkit.strings.error + ' Error de comunicación.');
					$results.removeClass('success warning info').addClass('error').show();
				},
				complete: function() {
					var buttonText = 'Previsualizar';
					if (actionType === 'all') {
						buttonText = 'Previsualizar Todo';
					}
					$button.prop('disabled', false).text(buttonText);
				}
			});
		});

		// Clean transients
		$('#users-toolkit-clean-transients').on('click', function() {
			optimizeDatabase('transients');
		});

		// Clean comments
		$('#users-toolkit-clean-comments').on('click', function() {
			optimizeDatabase('comments');
		});

		// Clean cron
		$('#users-toolkit-clean-cron').on('click', function() {
			var $button = $(this);
			var $results = $('#users-toolkit-db-results');
			var $content = $('#users-toolkit-db-content');

			$button.prop('disabled', true).html('<span class="users-toolkit-loading"></span> ' + usersToolkit.strings.cleaning_cron);
			$results.hide();

			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_clean_cron',
					nonce: usersToolkit.nonce
				},
				success: function(response) {
					if (response.success) {
						var html = '<div class="users-toolkit-message success">';
						html += '<p>' + response.data.message + '</p>';
						html += '</div>';
						$content.html(html);
						$results.removeClass('error warning').addClass('success').show();
					} else {
						showError($content, response.data.message || usersToolkit.strings.error);
						$results.removeClass('success warning').addClass('error').show();
					}
				},
				error: function() {
					showError($content, usersToolkit.strings.error + ' Error de comunicación. Revisa el log de errores de PHP.');
					$results.removeClass('success warning').addClass('error').show();
				},
				complete: function() {
					$button.prop('disabled', false).text('Limpiar Cron');
				}
			});
		});

		// Optimize tables
		$('#users-toolkit-optimize-tables').on('click', function() {
			optimizeDatabase('optimize');
		});

		// Optimize all
		$('#users-toolkit-optimize-all').on('click', function() {
			if (!confirm('¿Estás seguro de que deseas ejecutar todas las optimizaciones? Esto puede tomar varios minutos.')) {
				return;
			}
			optimizeDatabase('all');
		});

		// Helper functions
		function getSelectedUserIds() {
			var ids = [];
			var $checkboxes = $('.spam-user-checkbox:checked');
			$checkboxes.each(function() {
				var val = $(this).val();
				if (val) {
					ids.push(val);
				}
			});
			return ids;
		}

		function getAllUserIds() {
			var ids = [];
			var $checkboxes = $('.spam-user-checkbox');
			$checkboxes.each(function() {
				var val = $(this).val();
				if (val) {
					ids.push(val);
				}
			});
			return ids;
		}

		var USERS_TOOLKIT_DELETE_QUEUE_KEY = 'users_toolkit_delete_queue_v1';
		var USERS_TOOLKIT_DELETE_BATCH_SIZE = 100;

		function sanitizeUserIds(ids) {
			var result = [];
			var seen = {};
			(ids || []).forEach(function(id) {
				var normalized = parseInt(id, 10);
				if (!normalized || normalized <= 0 || seen[normalized]) {
					return;
				}
				seen[normalized] = true;
				result.push(normalized);
			});
			return result;
		}

		function saveDeleteQueueState(state) {
			if (!state || state.dry_run || !Array.isArray(state.pending_ids) || state.pending_ids.length === 0) {
				localStorage.removeItem(USERS_TOOLKIT_DELETE_QUEUE_KEY);
				return;
			}
			localStorage.setItem(USERS_TOOLKIT_DELETE_QUEUE_KEY, JSON.stringify(state));
		}

		function loadDeleteQueueState() {
			try {
				var raw = localStorage.getItem(USERS_TOOLKIT_DELETE_QUEUE_KEY);
				if (!raw) {
					return null;
				}
				var parsed = JSON.parse(raw);
				if (!parsed || !Array.isArray(parsed.pending_ids)) {
					return null;
				}
				parsed.pending_ids = sanitizeUserIds(parsed.pending_ids);
				return parsed;
			} catch (e) {
				return null;
			}
		}

		function clearDeleteQueueState() {
			localStorage.removeItem(USERS_TOOLKIT_DELETE_QUEUE_KEY);
		}

		function showDeleteProgress(state, batchIds, message) {
			var $results = $('#users-toolkit-delete-results');
			var $content = $('#users-toolkit-delete-content');
			var total = state.total || 0;
			var processed = state.processed || 0;
			var percent = total > 0 ? Math.min(100, Math.floor((processed / total) * 100)) : 0;

			var html = '<div class="users-toolkit-message info" style="padding: 15px; margin: 10px 0; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">';
			html += '<h4 style="margin-top: 0; color: #2271b1;">⏳ Procesando eliminación por lotes</h4>';
			html += '<p><strong>' + (message || (state.dry_run ? 'Simulando eliminación...' : 'Eliminando usuarios...')) + '</strong></p>';
			html += '<p>Progreso: <strong>' + processed + ' de ' + total + '</strong> (' + percent + '%)</p>';
			html += '<p>Lote actual: <strong>' + batchIds.length + '</strong> usuario(s)</p>';
			html += '<p>Pendientes: <strong>' + state.pending_ids.length + '</strong></p>';
			html += '</div>';

			$content.html(html);
			$results.removeClass('error warning success').addClass('info').show();
		}

		function finalizeDeleteQueue(state) {
			var $results = $('#users-toolkit-delete-results');
			var $content = $('#users-toolkit-delete-content');
			var dryRun = state.dry_run === true;
			var messageClass = dryRun ? 'warning' : 'success';
			var bgColor = dryRun ? '#f0f6fc' : '#d4edda';
			var borderColor = dryRun ? '#2271b1' : '#00a32a';
			var titleColor = dryRun ? '#2271b1' : '#00a32a';

			var html = '<div class="users-toolkit-message ' + messageClass + '" style="padding: 15px; margin: 10px 0; background: ' + bgColor + '; border-left: 4px solid ' + borderColor + '; border-radius: 4px;">';
			html += '<h4 style="margin-top: 0; color: ' + titleColor + ';">' + (dryRun ? '🔍 Simulación completada' : '✅ Eliminación completada') + '</h4>';
			if (state.last_message) {
				html += '<p><strong>' + state.last_message + '</strong></p>';
			}
			html += '<p><strong>Total objetivo:</strong> ' + state.total + '</p>';
			html += '<p><strong>Procesados:</strong> ' + state.processed + '</p>';
			html += '<p><strong>Eliminados:</strong> ' + state.deleted + '</p>';
			html += '<p><strong>Omitidos:</strong> ' + state.skipped + '</p>';
			html += '<p><strong>Errores:</strong> ' + state.errors + '</p>';

			if (state.errors > 0 && Array.isArray(state.errors_details) && state.errors_details.length > 0) {
				html += '<div style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #f0bfc0; border-radius: 4px;">';
				html += '<p style="margin: 0 0 6px 0; color: #b32d2e;"><strong>Primeros errores:</strong></p>';
				html += '<ul style="margin: 0; padding-left: 18px;">';
				state.errors_details.slice(0, 12).forEach(function(err) {
					html += '<li style="font-size: 12px; color: #646970;">' + err + '</li>';
				});
				html += '</ul>';
				html += '</div>';
			}

			if (Array.isArray(state.backup_files) && state.backup_files.length > 0) {
				html += '<div style="margin-top: 15px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
				html += '<p style="margin: 0 0 8px 0; font-weight: bold;">📦 Archivos generados:</p><ul style="margin: 0; padding-left: 18px;">';
				state.backup_files.forEach(function(fileName) {
					html += '<li style="font-family: monospace; font-size: 12px;">' + fileName + '</li>';
				});
				html += '</ul></div>';
			}

			if (dryRun) {
				html += '<div style="margin-top: 15px; padding: 12px; background: #d1ecf1; border-left: 4px solid #2271b1; border-radius: 4px;">';
				html += '<p style="margin: 0;"><strong>ℹ️ Esta fue una simulación. Ningún usuario fue eliminado.</strong></p>';
				html += '</div>';
			} else if (state.deleted > 0) {
				html += '<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 4px;">';
				html += '<p style="margin: 0;"><strong>⏱️ La página se recargará automáticamente en 5 segundos para mostrar los cambios...</strong></p>';
				html += '</div>';
			}

			html += '</div>';

			$content.html(html);
			$results.removeClass('error warning success info').addClass(messageClass).show();
			$('html, body').animate({
				scrollTop: $results.offset().top - 100
			}, 500);

			clearDeleteQueueState();

			if (!dryRun && state.deleted > 0) {
				setTimeout(function() {
					window.location.reload();
				}, 5000);
			}
		}

		function showDeleteErrorState(state, message) {
			var $results = $('#users-toolkit-delete-results');
			var $content = $('#users-toolkit-delete-content');

			var html = '<div class="users-toolkit-message error" style="padding: 15px; margin: 10px 0; background: #fbeaea; border-left: 4px solid #d63638; border-radius: 4px;">';
			html += '<h4 style="margin-top: 0; color: #b32d2e;">❌ Error durante eliminación por lotes</h4>';
			html += '<p><strong>' + (message || 'Ocurrió un error durante la operación.') + '</strong></p>';
			html += '<p>Avance guardado: ' + state.processed + ' de ' + state.total + ' procesados.</p>';
			html += '<p>Pendientes: ' + state.pending_ids.length + '</p>';
			html += '<div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">';
			html += '<button type="button" id="users-toolkit-resume-delete" class="button button-primary">Reanudar proceso</button>';
			html += '<button type="button" id="users-toolkit-discard-delete" class="button">Descartar progreso</button>';
			html += '</div>';
			html += '</div>';

			$content.html(html);
			$results.removeClass('success warning info').addClass('error').show();
			$('html, body').animate({
				scrollTop: $results.offset().top - 100
			}, 500);
		}

		function processDeleteQueue(state) {
			var $results = $('#users-toolkit-delete-results');
			var $content = $('#users-toolkit-delete-content');

			if (!state || !Array.isArray(state.pending_ids)) {
				showError($content, 'No se encontró estado válido para el procesamiento por lotes.');
				$results.removeClass('success warning info').addClass('error').show();
				return;
			}

			state.pending_ids = sanitizeUserIds(state.pending_ids);
			if (state.pending_ids.length === 0) {
				finalizeDeleteQueue(state);
				return;
			}

			var batchIds = state.pending_ids.slice(0, USERS_TOOLKIT_DELETE_BATCH_SIZE);
			showDeleteProgress(state, batchIds, state.dry_run ? 'Simulando lote...' : 'Eliminando lote...');

			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_delete_spam',
					nonce: usersToolkit.nonce,
					user_ids_json: JSON.stringify(batchIds),
					batch_size: USERS_TOOLKIT_DELETE_BATCH_SIZE,
					dry_run: state.dry_run === true ? 'true' : 'false'
				},
				success: function(response) {
					if (!response || !response.success || !response.data) {
						state.last_message = (response && response.data && response.data.message) ? response.data.message : 'Respuesta inválida del servidor.';
						saveDeleteQueueState(state);
						showDeleteErrorState(state, state.last_message);
						return;
					}

					var data = response.data;
					var deletedInBatch = parseInt(data.deleted !== undefined ? data.deleted : (data.count || 0), 10) || 0;
					var errorsInBatch = parseInt(data.errors || 0, 10) || 0;
					var skippedInBatch = parseInt(data.skipped || 0, 10) || 0;

					state.deleted += deletedInBatch;
					state.errors += errorsInBatch;
					state.skipped += skippedInBatch;
					state.processed += batchIds.length;
					state.pending_ids = state.pending_ids.slice(batchIds.length);
					state.last_message = data.message || '';

					if (Array.isArray(data.errors_details) && data.errors_details.length > 0) {
						if (!Array.isArray(state.errors_details)) {
							state.errors_details = [];
						}
						state.errors_details = state.errors_details.concat(data.errors_details).slice(-50);
					}

					if (data.backup_file) {
						if (!Array.isArray(state.backup_files)) {
							state.backup_files = [];
						}
						if (state.backup_files.indexOf(data.backup_file) === -1) {
							state.backup_files.push(data.backup_file);
						}
					}

					saveDeleteQueueState(state);

					if (state.pending_ids.length > 0) {
						processDeleteQueue(state);
					} else {
						finalizeDeleteQueue(state);
					}
				},
				error: function(xhr, status, error) {
					state.last_message = 'Error de comunicación: ' + (error || status || 'desconocido');
					saveDeleteQueueState(state);
					showDeleteErrorState(state, state.last_message);
				}
			});
		}

		$(document).off('click', '#users-toolkit-resume-delete').on('click', '#users-toolkit-resume-delete', function() {
			var savedState = loadDeleteQueueState();
			if (!savedState || !Array.isArray(savedState.pending_ids) || savedState.pending_ids.length === 0) {
				alert('No hay un proceso pendiente para reanudar.');
				clearDeleteQueueState();
				return;
			}
			processDeleteQueue(savedState);
		});

		$(document).off('click', '#users-toolkit-discard-delete').on('click', '#users-toolkit-discard-delete', function() {
			clearDeleteQueueState();
			alert('Progreso de eliminación descartado.');
		});

		function showPendingDeleteResumeNotice() {
			var savedState = loadDeleteQueueState();
			if (!savedState || !Array.isArray(savedState.pending_ids) || savedState.pending_ids.length === 0) {
				return;
			}

			var $results = $('#users-toolkit-delete-results');
			var $content = $('#users-toolkit-delete-content');

			var html = '<div class="users-toolkit-message warning" style="padding: 15px; margin: 10px 0; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 4px;">';
			html += '<h4 style="margin-top: 0; color: #856404;">⚠️ Proceso pendiente detectado</h4>';
			html += '<p>Hay una eliminación por lotes incompleta.</p>';
			html += '<p>Procesados: <strong>' + (savedState.processed || 0) + '</strong> de <strong>' + (savedState.total || 0) + '</strong>.</p>';
			html += '<p>Pendientes: <strong>' + savedState.pending_ids.length + '</strong>.</p>';
			html += '<div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">';
			html += '<button type="button" id="users-toolkit-resume-delete" class="button button-primary">Reanudar eliminación</button>';
			html += '<button type="button" id="users-toolkit-discard-delete" class="button">Descartar progreso</button>';
			html += '</div>';
			html += '</div>';

			$content.html(html);
			$results.removeClass('error success info').addClass('warning').show();
		}

		function deleteUsers(userIds, dryRun) {
			// SEGURIDAD: Validación estricta de dryRun
			if (dryRun !== true && dryRun !== false) {
				console.error('ERROR: deleteUsers llamado con dryRun inválido:', dryRun);
				alert('Error interno: modo de simulación no válido. Operación cancelada por seguridad.');
				return;
			}

			var normalizedIds = sanitizeUserIds(userIds);
			if (normalizedIds.length === 0) {
				alert('No hay usuarios válidos para procesar.');
				return;
			}

			var deleteQueueState = {
				dry_run: dryRun === true,
				total: normalizedIds.length,
				pending_ids: normalizedIds.slice(),
				processed: 0,
				deleted: 0,
				skipped: 0,
				errors: 0,
				errors_details: [],
				backup_files: [],
				last_message: ''
			};

			saveDeleteQueueState(deleteQueueState);
			processDeleteQueue(deleteQueueState);
		}

		function optimizeDatabase(actionType) {
			var $button = $('[data-action="' + actionType + '"]');
			var $results = $('#users-toolkit-db-results');
			var $content = $('#users-toolkit-db-content');

			$button.prop('disabled', true).html('<span class="users-toolkit-loading"></span> ' + usersToolkit.strings.optimizing);
			$results.hide();

			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_optimize_db',
					nonce: usersToolkit.nonce,
					action_type: actionType
				},
				success: function(response) {
					if (response.success) {
						var html = '<div class="users-toolkit-message success">';
						html += '<p>' + response.data.message + '</p>';
						
						if (response.data.results) {
							html += '<ul>';
							if (response.data.results.transients) {
								html += '<li>Transients eliminados: ' + response.data.results.transients.total + '</li>';
							}
							if (response.data.results.comments) {
								html += '<li>Comentarios eliminados: ' + response.data.results.comments.total + '</li>';
							}
							if (response.data.results.optimize) {
								html += '<li>Tablas optimizadas: ' + response.data.results.optimize.optimized + '</li>';
							}
							html += '</ul>';
						}

						html += '</div>';
						$content.html(html);
						$results.removeClass('error warning').addClass('success').show();

						// Reload page after a delay to refresh stats
						if (actionType === 'all') {
							setTimeout(function() {
								window.location.reload();
							}, 3000);
						}
					} else {
						showError($content, response.data.message || usersToolkit.strings.error);
						$results.removeClass('success warning').addClass('error').show();
					}
				},
				error: function() {
					showError($content, usersToolkit.strings.error + ' Error de comunicación. Revisa el log de errores de PHP.');
					$results.removeClass('success warning').addClass('error').show();
				},
				complete: function() {
					var buttonText = $button.data('action');
					if (buttonText === 'transients') {
						$button.prop('disabled', false).text('Limpiar Transients');
					} else if (buttonText === 'comments') {
						$button.prop('disabled', false).text('Limpiar Comentarios');
					} else if (buttonText === 'optimize') {
						$button.prop('disabled', false).text('Optimizar Tablas');
					} else if (buttonText === 'all') {
						$button.prop('disabled', false).text('Optimización Completa');
					}
				}
			});
		}

		function showError($container, message) {
			var html = '<div class="users-toolkit-message error">';
			html += '<p>' + message + '</p>';
			html += '</div>';
			$container.html(html);
		}

		function updateProgress($bar, $msg, $log, percent, message) {
			$bar.css('width', percent + '%').text(percent + '%');
			if ($msg) {
				$msg.text(message);
			}
			if ($log && message) {
				var lastMessage = $log.data('usersToolkitLastMessage');
				var lastPercent = $log.data('usersToolkitLastPercent');
				if (lastMessage === message && Number(lastPercent) === Number(percent)) {
					return;
				}
				$log.data('usersToolkitLastMessage', message);
				$log.data('usersToolkitLastPercent', percent);
				var timestamp = new Date().toLocaleTimeString();
				var logEntry = $('<p>').text('[' + timestamp + '] ' + message);
				$log.append(logEntry);
				$log.scrollTop($log[0].scrollHeight);
			}
		}

			// Create backup
			$('#users-toolkit-create-backup').on('click', function() {
				var $button = $(this);
				var $results = $('#users-toolkit-backup-results');
				var $content = $('#users-toolkit-backup-content');
				var $progressContainer = $('#users-toolkit-backup-progress-container');
				var $progressBar = $('#users-toolkit-backup-progress-bar');
				var $progressMsg = $('#users-toolkit-backup-progress-message');
				var $progressLog = $('#users-toolkit-backup-progress-log');
				var operationId = null;
				var pollInterval = null;
				var pollErrors = 0;

					$button.prop('disabled', true).html('<span class="users-toolkit-loading"></span> Creando backup...');
					$results.hide();
					$progressContainer.show();
					$progressLog.html('');
					$progressLog.removeData('usersToolkitLastMessage').removeData('usersToolkitLastPercent');
					updateProgress($progressBar, $progressMsg, $progressLog, 0, 'Iniciando backup...');

			// Función de polling para obtener progreso
			function startPolling(operation_id) {
				operationId = operation_id;
				pollInterval = setInterval(function() {
					$.ajax({
						url: usersToolkit.ajaxurl,
						type: 'POST',
						data: {
							action: 'users_toolkit_get_progress',
							nonce: usersToolkit.nonce,
							operation_id: operation_id
						},
							success: function(response) {
								if (response.success && response.data) {
									pollErrors = 0;
									updateProgress($progressBar, $progressMsg, $progressLog, response.data.current, response.data.message);
									if (response.data.completed) {
										clearInterval(pollInterval);
										$progressContainer.hide();
										if (response.data.data && response.data.data.error) {
											showError($content, response.data.message || 'El backup finalizó con error.');
											$results.removeClass('success warning').addClass('error').show();
											return;
										}
										var html = '<div class="users-toolkit-message success">';
										html += '<p>' + response.data.message + '</p>';
										if (response.data.data && response.data.data.method) {
											html += '<p><strong>Método:</strong> ' + response.data.data.method + '</p>';
										}
										html += '</div>';
										html += '<p>La página se recargará automáticamente en 2 segundos...</p>';
										$content.html(html);
										$results.removeClass('error warning').addClass('success').show();

										setTimeout(function() {
											window.location.reload();
										}, 2000);
									}
								} else if (response.success === false) {
								pollErrors++;
								if (pollErrors < 15) {
									return;
								}
								clearInterval(pollInterval);
								$progressContainer.hide();
								showError($content, (response.data && response.data.message ? response.data.message : 'No se pudo leer el progreso del backup.'));
								$results.removeClass('success warning').addClass('error').show();
							}
						},
						error: function() {
							pollErrors++;
							if (pollErrors < 15) {
								return;
							}
							clearInterval(pollInterval);
							$progressContainer.hide();
							showError($content, 'No se pudo consultar el progreso del backup. Revisa la conectividad del servidor y el log de PHP.');
							$results.removeClass('success warning').addClass('error').show();
						}
					});
				}, 2000);
			}

				operationId = 'backup_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
				startPolling(operationId);

				$.ajax({
					url: usersToolkit.ajaxurl,
					type: 'POST',
					timeout: 1800000,
					data: {
						action: 'users_toolkit_create_backup',
						nonce: usersToolkit.nonce,
						operation_id: operationId
					},
					success: function(response) {
						if (response.success) {
							if (response.data && response.data.message) {
								updateProgress($progressBar, $progressMsg, $progressLog, 3, response.data.message);
							}
							if (!response.data.operation_id) {
								// Operación completada inmediatamente
								clearInterval(pollInterval);
								$progressContainer.hide();
								var html = '<div class="users-toolkit-message success">';
								html += '<p>' + response.data.message + '</p>';
								html += '<p><strong>Método:</strong> ' + response.data.method + '</p>';
								html += '</div>';
								html += '<p>La página se recargará automáticamente en 2 segundos...</p>';
								$content.html(html);
								$results.removeClass('error warning').addClass('success').show();

								setTimeout(function() {
									window.location.reload();
								}, 2000);
							}
						} else {
							clearInterval(pollInterval);
							$progressContainer.hide();
							showError($content, response.data.message || usersToolkit.strings.error);
							$results.removeClass('success warning').addClass('error').show();
						}
					},
					error: function(xhr, status) {
						if (status === 'timeout') {
							updateProgress($progressBar, $progressMsg, $progressLog, 95, 'La solicitud tardó demasiado, pero el backup puede seguir ejecutándose. Monitoreando progreso...');
							var timeoutHtml = '<div class="users-toolkit-message warning">';
							timeoutHtml += '<p>La conexión AJAX excedió el tiempo límite, pero el backup puede seguir en curso.</p>';
							timeoutHtml += '<p>Mantén esta pantalla abierta; se actualizará automáticamente cuando termine.</p>';
							timeoutHtml += '</div>';
							$content.html(timeoutHtml);
							$results.removeClass('error success').addClass('warning').show();
							return;
						}

						clearInterval(pollInterval);
						$progressContainer.hide();
						var msg = usersToolkit.strings.error + ' ';
						msg += 'Error de comunicación. Revisa el log de errores de PHP. En algunos servidores exec() y shell están deshabilitados; el plugin usará el método PHP.';
						showError($content, msg);
						$results.removeClass('success warning').addClass('error').show();
					},
					complete: function() {
						setTimeout(function() {
							$button.prop('disabled', false).text('Crear Backup');
						}, 3000);
					}
				});
			});

		// Delete backup
		$(document).on('click', '.users-toolkit-delete-backup-btn', function() {
			var $button = $(this);
			var filename = $button.data('filename');
			
			if (!confirm('¿Estás seguro de que deseas eliminar este backup? Esta acción no se puede deshacer.')) {
				return;
			}

			$button.prop('disabled', true).text('Eliminando...');

			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_delete_backup',
					nonce: usersToolkit.nonce,
					filename: filename
				},
				success: function(response) {
					if (response.success) {
						$button.closest('tr').fadeOut(300, function() {
							$(this).remove();
							if ($('tbody tr').length === 0) {
								window.location.reload();
							}
						});
					} else {
						alert(response.data.message || usersToolkit.strings.error);
						$button.prop('disabled', false).text('Eliminar');
					}
				},
				error: function() {
					alert(usersToolkit.strings.error + ' Error de comunicación.');
					$button.prop('disabled', false).text('Eliminar');
				}
			});
		});

		// Load autoload statistics
		$('#users-toolkit-load-autoload-stats').on('click', function() {
			var $button = $(this);
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> Cargando...');
			
			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_get_autoload_stats',
					nonce: usersToolkit.nonce
				},
				success: function(response) {
					if (response.success && response.data.stats) {
						var stats = response.data.stats;
						$('#users-toolkit-autoload-total').text(stats.total_autoloaded.toLocaleString());
						$('#users-toolkit-autoload-size-mb').text(stats.total_size_mb.toLocaleString());
						$('#users-toolkit-autoload-stats').show();
						
						// Show top 20 largest options if available
						if (stats.large_options && stats.large_options.length > 0) {
							var html = '<h4 style="margin-top: 15px;">' + (usersToolkit.strings.top_large_options || 'Top 20 Opciones Más Grandes') + ':</h4>';
							html += '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
							html += '<thead><tr><th>Opción</th><th>Tamaño</th><th>Vista Previa</th></tr></thead><tbody>';
							
							stats.large_options.forEach(function(option) {
								var preview = option.preview ? option.preview.substring(0, 80) + '...' : '-';
								html += '<tr>';
								html += '<td><code>' + option.option_name + '</code></td>';
								html += '<td>' + parseFloat(option.size_kb).toFixed(2) + ' KB (' + option.size_bytes.toLocaleString() + ' bytes)</td>';
								html += '<td><small>' + preview + '</small></td>';
								html += '</tr>';
							});
							
							html += '</tbody></table>';
							$('#users-toolkit-autoload-stats').append(html);
						}
					}
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-chart-bar"></span> ' + (usersToolkit.strings.view_stats || 'Ver Estadísticas'));
				}
			});
		});

		// Preview disable autoload
		$('#users-toolkit-preview-autoload').on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var $button = $(this);
			var $preview = $('#users-toolkit-autoload-preview');
			var $content = $('#users-toolkit-autoload-preview-content');
			var threshold = parseInt($('#users-toolkit-autoload-threshold').val()) || 1;
			


			
			if ($preview.length === 0 || $content.length === 0) {
				alert('Error: No se encontraron los elementos necesarios en la página.');
				return;
			}
			
			$button.prop('disabled', true).html('<span class="users-toolkit-loading"></span> Analizando...');
			$preview.hide();
			$content.html('');
			
			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				data: {
					action: 'users_toolkit_preview_disable_autoload',
					nonce: usersToolkit.nonce,
					threshold_kb: threshold
				},
				success: function(response) {
					
					if (!response || !response.success) {
						var errorMsg = response && response.data && response.data.message ? response.data.message : 'Error desconocido en la respuesta';
						console.error('Error in response:', errorMsg, response);
						showError($content, errorMsg);
						$preview.show();
						return;
					}
					
					if (!response.data || !response.data.preview) {
						console.error('No preview data in response:', response);
						showError($content, 'No se recibieron datos de previsualización. Revisa la consola para más detalles.');
						$preview.show();
						return;
					}
					
					var preview = response.data.preview;


					
					if (!preview.suggested_options || preview.suggested_options.length === 0 || preview.suggested_count === 0) {
						var html = '<p style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 4px;">';
						html += '<strong>' + (usersToolkit.strings.no_options_found || 'No se encontraron opciones que cumplan los criterios.') + '</strong><br>';
						html += '<em>Intenta reducir el umbral (por ejemplo, a 1 KB) para encontrar más opciones.</em><br>';
						html += '<strong>Umbral usado:</strong> ' + threshold + ' KB';
						html += '</p>';
						$content.html(html);
						$preview.show();
					} else {
						var html = '<p>';
						html += '<strong>' + (usersToolkit.strings.suggested_count || 'Opciones sugeridas') + ':</strong> ' + preview.suggested_count.toLocaleString() + '<br>';
						html += '<strong>' + (usersToolkit.strings.total_size || 'Tamaño total') + ':</strong> ' + preview.total_size_mb.toLocaleString() + ' MB<br>';
						html += '<strong>' + (usersToolkit.strings.threshold || 'Umbral') + ':</strong> ' + threshold + ' KB';
						html += '</p>';
						
						html += '<p style="font-size: 13px; color: #646970; margin-top: 10px;">';
						html += '<em>' + (usersToolkit.strings.autoload_warning || 'Estas opciones son grandes y pueden no necesitar cargarse automáticamente. Desactivar autoload puede mejorar el rendimiento.') + '</em>';
						html += '</p>';
						
						html += '<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">';
						html += '<thead><tr>';
						html += '<th style="width: 50px;"><input type="checkbox" id="users-toolkit-select-all-autoload"></th>';
						html += '<th>Opción</th>';
						html += '<th>Tamaño</th>';
						html += '<th>Vista Previa</th>';
						html += '</tr></thead><tbody>';
						
						preview.suggested_options.forEach(function(option) {
							var previewText = option.preview ? option.preview.substring(0, 100) + '...' : '-';
							var sizeText = '';
							if (option.size_mb >= 1) {
								sizeText = parseFloat(option.size_mb).toFixed(2) + ' MB';
							} else {
								sizeText = parseFloat(option.size_kb).toFixed(2) + ' KB';
							}
							
							html += '<tr>';
							html += '<td><input type="checkbox" class="users-toolkit-autoload-option" value="' + option.option_name + '"></td>';
							html += '<td><code>' + option.option_name + '</code></td>';
							html += '<td>' + sizeText + '</td>';
							html += '<td><small>' + previewText + '</small></td>';
							html += '</tr>';
						});
						
						html += '</tbody></table>';
						
						html += '<div class="users-toolkit-action-buttons" style="margin-top: 15px;">';
						html += '<button type="button" id="users-toolkit-disable-autoload-selected" class="button button-secondary">';
						html += usersToolkit.strings.disable_selected || 'Desactivar Autoload en Seleccionados';
						html += '</button>';
						html += '</div>';
						
						$content.html(html);
						$preview.show();
						
						// Select all checkbox - usar delegación de eventos
						$(document).off('change', '#users-toolkit-select-all-autoload').on('change', '#users-toolkit-select-all-autoload', function() {
							$('.users-toolkit-autoload-option').prop('checked', $(this).prop('checked'));
						});
						
						// Disable autoload on selected - usar delegación de eventos
						$(document).off('click', '#users-toolkit-disable-autoload-selected').on('click', '#users-toolkit-disable-autoload-selected', function() {
							var selected = [];
							$('.users-toolkit-autoload-option:checked').each(function() {
								selected.push($(this).val());
							});
							
							if (selected.length === 0) {
								alert(usersToolkit.strings.select_options || 'Por favor, selecciona al menos una opción.');
								return;
							}
							
							if (!confirm(usersToolkit.strings.confirm_disable_autoload || '¿Estás seguro de que deseas desactivar autoload en ' + selected.length + ' opciones?')) {
								return;
							}
							
							var $btn = $(this);
							$btn.prop('disabled', true).text('Procesando...');
							
							$.ajax({
								url: usersToolkit.ajaxurl,
								type: 'POST',
								data: {
									action: 'users_toolkit_disable_autoload',
									nonce: usersToolkit.nonce,
									option_names: selected
								},
								success: function(response) {
									if (response.success) {
										alert(response.data.message);
										$('#users-toolkit-preview-autoload').click();
									} else {
										alert(response.data.message || usersToolkit.strings.error);
									}
								},
								error: function() {
									alert(usersToolkit.strings.error + ' Error de comunicación.');
								},
								complete: function() {
									$btn.prop('disabled', false).text(usersToolkit.strings.disable_selected || 'Desactivar Autoload en Seleccionados');
								}
							});
						});
					}
				},
				error: function(xhr, status, error) {
					console.error('Preview autoload error:', xhr, status, error);
					var errorMsg = usersToolkit.strings.error + ' Error de comunicación.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}
					showError($content, errorMsg);
					$preview.show();
				},
				complete: function() {
					$button.prop('disabled', false).html(usersToolkit.strings.preview_large_options || 'Previsualizar Opciones Grandes');
				}
			});
		});

		// Función para actualizar contador de usuarios visibles y seleccionados
		function updateVisibleCount() {
			var $table = $('#users-toolkit-spam-table');
			var $rows = $table.find('tbody tr');
			var visibleCount = $rows.filter(':visible').length;
			var totalCount = $rows.length;
			
			// Contar usuarios seleccionados (visibles y no visibles)
			var $allCheckboxes = $table.find('tbody tr .spam-user-checkbox');
			var $visibleCheckboxes = $rows.filter(':visible').find('.spam-user-checkbox');
			var selectedCount = $allCheckboxes.filter(':checked').length;
			var selectedVisible = $visibleCheckboxes.filter(':checked').length;
			
			// Actualizar contador de usuarios visibles
			$('#users-toolkit-visible-count').text('Mostrando: ' + visibleCount + ' de ' + totalCount);
			$('#users-toolkit-visible-count-header').html(
				'Mostrando: <strong>' + visibleCount + '</strong> de <strong>' + totalCount + '</strong>' +
				(selectedCount > 0 ? ' | Seleccionados: <strong style="color: #00a32a;">' + selectedCount + '</strong>' + 
					(selectedVisible !== selectedCount ? ' (<strong>' + selectedVisible + '</strong> visibles)' : '') : '')
			);
			
			// Actualizar estado del checkbox "Seleccionar todos" basado en checkboxes visibles
			var $selectAll = $('#users-toolkit-select-all');
			if ($selectAll.length) {
				var checkedVisible = $visibleCheckboxes.filter(':checked').length;
				var totalVisible = $visibleCheckboxes.length;
				
				if (totalVisible === 0) {
					$selectAll.prop('checked', false).prop('indeterminate', false);
				} else if (checkedVisible === totalVisible) {
					$selectAll.prop('checked', true).prop('indeterminate', false);
				} else if (checkedVisible > 0) {
					$selectAll.prop('checked', false).prop('indeterminate', true);
				} else {
					$selectAll.prop('checked', false).prop('indeterminate', false);
				}
			}
			
			// Actualizar también el selector de acciones
			if (typeof updateActionButton === 'function') {
				updateActionButton();
			}
		}
		
		function normalizeFilterText(value) {
			var normalized = (value || '').toString().toLowerCase().trim();
			if (normalized && typeof normalized.normalize === 'function') {
				normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
			}
			return normalized;
		}

		var latamCountryAliases = [
			'mx', 'mexico', 'méxico',
			'ar', 'argentina',
			'bo', 'bolivia',
			'br', 'brazil', 'brasil',
			'cl', 'chile',
			'co', 'colombia',
			'cr', 'costa rica',
			'cu', 'cuba',
			'do', 'dominican republic', 'republica dominicana', 'república dominicana',
			'ec', 'ecuador',
			'sv', 'el salvador',
			'gt', 'guatemala',
			'hn', 'honduras',
			'ni', 'nicaragua',
			'pa', 'panama', 'panamá',
			'py', 'paraguay',
			'pe', 'peru', 'perú',
			'pr', 'puerto rico',
			'uy', 'uruguay',
			've', 'venezuela'
		];
		var latamCountryAliasesMap = {};
		latamCountryAliases.forEach(function(alias) {
			latamCountryAliasesMap[normalizeFilterText(alias)] = true;
		});

		function applySpamUsersFilters() {
			var searchText = normalizeFilterText($('#users-toolkit-search-input').val());
			var firstNameFilter = normalizeFilterText($('#users-toolkit-filter-first-name').val());
			var lastNameFilter = normalizeFilterText($('#users-toolkit-filter-last-name').val());
			var cityFilter = normalizeFilterText($('#users-toolkit-filter-city').val());
			var countryFilter = normalizeFilterText($('#users-toolkit-filter-country').val());
			var selectedCountries = ($('#users-toolkit-filter-country-multi').val() || []).map(function(country) {
				return normalizeFilterText(country);
			}).filter(function(country) {
				return country !== '';
			});
			var excludeSelectedCountries = $('#users-toolkit-filter-country-exclude').prop('checked');
			var $table = $('#users-toolkit-spam-table');
			var $rows = $table.find('tbody tr');
			var $clearButton = $('#users-toolkit-clear-search');
			
			var hasAnyFilter = searchText !== '' || firstNameFilter !== '' || lastNameFilter !== '' || cityFilter !== '' || countryFilter !== '' || selectedCountries.length > 0;
			if (!hasAnyFilter) {
				$rows.show();
				$clearButton.hide();
			} else {
				$clearButton.show();
				$rows.each(function() {
					var $row = $(this);
					var rowText = normalizeFilterText($row.text());
					var firstName = normalizeFilterText($row.attr('data-first_name'));
					var lastName = normalizeFilterText($row.attr('data-last_name'));
					var city = normalizeFilterText($row.attr('data-city'));
					var country = normalizeFilterText($row.attr('data-country'));

					var matchesSearch = (searchText === '' || rowText.indexOf(searchText) !== -1);
					var matchesFirstName = (firstNameFilter === '' || firstName.indexOf(firstNameFilter) !== -1);
					var matchesLastName = (lastNameFilter === '' || lastName.indexOf(lastNameFilter) !== -1);
					var matchesCity = (cityFilter === '' || city.indexOf(cityFilter) !== -1);
					var matchesCountry = (countryFilter === '' || country.indexOf(countryFilter) !== -1);
					var matchesCountryList = true;

					if (selectedCountries.length > 0) {
						var countryFound = selectedCountries.some(function(selectedCountry) {
							if (!selectedCountry) {
								return false;
							}
							return country === selectedCountry || country.indexOf(selectedCountry) !== -1 || selectedCountry.indexOf(country) !== -1;
						});
						matchesCountryList = excludeSelectedCountries ? !countryFound : countryFound;
					}

					var isVisible = matchesSearch && matchesFirstName && matchesLastName && matchesCity && matchesCountry && matchesCountryList;

					$row.toggle(isVisible);
				});
			}

			// Actualizar contador y estado del checkbox "Seleccionar todos"
			updateVisibleCount();
		}

		// Búsqueda/filtrado en la tabla de usuarios spam
		$('#users-toolkit-search-input, #users-toolkit-filter-first-name, #users-toolkit-filter-last-name, #users-toolkit-filter-city, #users-toolkit-filter-country, #users-toolkit-filter-country-multi, #users-toolkit-filter-country-exclude').on('keyup change', function() {
			applySpamUsersFilters();
		});

		$('#users-toolkit-select-latam').on('click', function() {
			var $countrySelect = $('#users-toolkit-filter-country-multi');
			if ($countrySelect.length === 0 || $countrySelect.prop('disabled')) {
				return;
			}

			$countrySelect.find('option').prop('selected', false);
			$countrySelect.find('option').each(function() {
				var $option = $(this);
				var optionText = normalizeFilterText($option.text());
				var optionValue = normalizeFilterText($option.val());
				if (latamCountryAliasesMap[optionText] || latamCountryAliasesMap[optionValue]) {
					$option.prop('selected', true);
				}
			});

			$('#users-toolkit-filter-country-exclude').prop('checked', false);
			applySpamUsersFilters();
		});

		$('#users-toolkit-clear-country-multi').on('click', function() {
			var $countrySelect = $('#users-toolkit-filter-country-multi');
			if ($countrySelect.length === 0) {
				return;
			}
			$countrySelect.find('option').prop('selected', false);
			$('#users-toolkit-filter-country-exclude').prop('checked', false);
			applySpamUsersFilters();
		});
		
		// Botón para limpiar filtros
		$('#users-toolkit-clear-search').on('click', function() {
			$('#users-toolkit-search-input').val('');
			$('#users-toolkit-filter-first-name').val('');
			$('#users-toolkit-filter-last-name').val('');
			$('#users-toolkit-filter-city').val('');
			$('#users-toolkit-filter-country').val('');
			$('#users-toolkit-filter-country-multi option').prop('selected', false);
			$('#users-toolkit-filter-country-exclude').prop('checked', false);
			applySpamUsersFilters();
			$(this).hide();
		});
		
		// Limpiar búsqueda con tecla Escape
		$('#users-toolkit-search-input, #users-toolkit-filter-first-name, #users-toolkit-filter-last-name, #users-toolkit-filter-city, #users-toolkit-filter-country, #users-toolkit-filter-country-multi').on('keydown', function(e) {
			if (e.key === 'Escape') {
				$('#users-toolkit-search-input').val('');
				$('#users-toolkit-filter-first-name').val('');
				$('#users-toolkit-filter-last-name').val('');
				$('#users-toolkit-filter-city').val('');
				$('#users-toolkit-filter-country').val('');
				$('#users-toolkit-filter-country-multi option').prop('selected', false);
				$('#users-toolkit-filter-country-exclude').prop('checked', false);
				applySpamUsersFilters();
				$('#users-toolkit-clear-search').hide();
			}
		});
		
		// Inicializar contador al cargar
		updateVisibleCount();

		// Ordenamiento por columnas mejorado
		var currentSort = {
			column: null,
			direction: 'asc' // 'asc' o 'desc'
		};

		$('#users-toolkit-spam-table th.sortable').on('click', function() {
			var $th = $(this);
			var column = $th.data('column');
			var type = $th.data('type');
			var $table = $('#users-toolkit-spam-table');
			var $tbody = $table.find('tbody');
			var $rows = $tbody.find('tr');
			
			// Determinar dirección de ordenamiento
			if (currentSort.column === column) {
				// Cambiar dirección si es la misma columna
				currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
			} else {
				// Nueva columna, empezar con ascendente
				currentSort.column = column;
				currentSort.direction = 'asc';
			}
			
			// Remover indicadores de todas las columnas
			$('#users-toolkit-spam-table th.sortable .sort-indicator').text('');
			$('#users-toolkit-spam-table th.sortable').removeClass('sort-asc sort-desc');
			
			// Agregar indicador a la columna actual
			$th.addClass('sort-' + currentSort.direction);
			var indicator = currentSort.direction === 'asc' ? ' ↑' : ' ↓';
			$th.find('.sort-indicator').text(indicator);
			
			// Obtener índice de columna
			var columnIndex = $th.index();
			
			// Ordenar filas
			$rows.sort(function(a, b) {
				var $a = $(a);
				var $b = $(b);
				var valA, valB;
				
				// Obtener valor de la celda correspondiente
				var $cellA = $a.find('td').eq(columnIndex);
				var $cellB = $b.find('td').eq(columnIndex);
				var textA = $cellA.text().trim();
				var textB = $cellB.text().trim();
				
				// Obtener valor según el tipo de dato
				if (type === 'number') {
					// Intentar obtener de data-attribute primero
					valA = parseFloat($a.data(column)) || parseFloat(textA.replace(/[^\d.-]/g, '')) || 0;
					valB = parseFloat($b.data(column)) || parseFloat(textB.replace(/[^\d.-]/g, '')) || 0;
				} else if (type === 'date') {
					// Para fechas, intentar parsear o usar timestamp
					valA = parseInt($a.data(column)) || new Date(textA).getTime() || 0;
					valB = parseInt($b.data(column)) || new Date(textB).getTime() || 0;
				} else {
					// Texto - usar data-attribute si existe, sino el texto de la celda
					valA = ($a.data(column) || textA || '').toString().toLowerCase();
					valB = ($b.data(column) || textB || '').toString().toLowerCase();
				}
				
				// Comparar
				var result = 0;
				if (valA < valB) {
					result = -1;
				} else if (valA > valB) {
					result = 1;
				}
				
				// Aplicar dirección
				return currentSort.direction === 'asc' ? result : -result;
			});
			
			// Reordenar filas en el DOM
			$tbody.empty().append($rows);
			
			// Reaplicar filtro y contador después de ordenar
			applySpamUsersFilters();
		});
		
		// Función para exportar usuarios
		function exportUsers(userIds) {
			if (!userIds || userIds.length === 0) {
				alert('No hay usuarios para exportar.');
				return;
			}
			
			var $results = $('#users-toolkit-delete-results');
			var $content = $('#users-toolkit-delete-content');
			
			// Verificar que los elementos existan
			if ($results.length === 0) {
				console.error('Users Toolkit: Contenedor de resultados no encontrado');
				alert('Error: No se encontró el contenedor de resultados. Por favor, recarga la página.');
				return;
			}
			
			// Mostrar contenedor y mensaje de carga
			$content.html('<div style="padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">');
			$content.append('<p style="margin: 0 0 10px 0;"><strong>⏳ Exportando ' + userIds.length + ' usuario(s)...</strong></p>');
			$content.append('<p style="margin: 0; font-size: 13px; color: #646970;">Esto puede tardar varios minutos si hay muchos usuarios. Por favor, espera...</p>');
			$content.append('</div>');
			$results.removeClass('error warning success').addClass('info').show();
			
			// Scroll hasta el contenedor de resultados
			setTimeout(function() {
				if ($results.length && $results.offset()) {
					$('html, body').animate({
						scrollTop: $results.offset().top - 100
					}, 500);
				}
			}, 100);
			
			$.ajax({
				url: usersToolkit.ajaxurl,
				type: 'POST',
				timeout: 600000, // 10 minutos para exportaciones grandes
				data: {
					action: 'users_toolkit_export_users',
					nonce: usersToolkit.nonce,
					user_ids: userIds
				},
				success: function(response) {
					if (response.success && response.data) {
						var html = '<div class="users-toolkit-message success" style="padding: 15px; margin: 10px 0; background: #d4edda; border-left: 4px solid #00a32a; border-radius: 4px;">';
						html += '<h4 style="margin-top: 0; color: #00a32a;">✅ Exportación Completada</h4>';
						html += '<p><strong>Se exportaron ' + (response.data.count || 0) + ' usuario(s) con todos sus metadatos.</strong></p>';
						
						if (response.data.file_name) {
							// Asegurar que el nombre del archivo tenga extensión .json
							var downloadFileName = response.data.file_name;
							if (!downloadFileName.endsWith('.json')) {
								downloadFileName = downloadFileName.replace(/\.[^/.]+$/, '') + '.json';
							}
							
							// SIEMPRE usar download_url (endpoint AJAX) - es la única forma confiable
							var downloadUrl = response.data.download_url;
							if (!downloadUrl) {
								// Si no hay download_url, crear uno usando el endpoint AJAX con el nonce
								var nonce = response.data.download_nonce || '';
								if (!nonce && response.data.file_name) {
									// Generar nonce desde el lado del cliente (no ideal, pero funcional)
									// En realidad, debería venir del servidor
									console.warn('Users Toolkit: No se recibió nonce para descarga, intentando sin nonce');
								}
								downloadUrl = usersToolkit.ajaxurl + '?action=users_toolkit_download_export&file=' + encodeURIComponent(response.data.file_name) + (nonce ? '&nonce=' + encodeURIComponent(nonce) : '');
							}
							
							html += '<div style="margin-top: 15px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
							html += '<p style="margin: 0 0 8px 0; font-weight: bold;">📦 Archivo de exportación:</p>';
							html += '<p style="margin: 0 0 10px 0;">';
							html += '<a href="' + downloadUrl + '" id="users-toolkit-download-link" download="' + downloadFileName + '" type="application/json" style="display: inline-block; padding: 10px 20px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px;">⬇️ Descargar: ' + downloadFileName + '</a>';
							html += '</p>';
							html += '<p style="margin: 8px 0 0 0; font-size: 12px; color: #646970;">';
							html += 'Ubicación: <code>wp-content/uploads/users-toolkit/' + response.data.file_name + '</code>';
							html += '</p>';
							html += '<p style="margin: 8px 0 0 0; font-size: 12px; color: #646970;">';
							html += 'Si la descarga no inicia automáticamente, haz clic en el botón de arriba.';
							html += '</p>';
							html += '</div>';
						} else {
							html += '<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 4px;">';
							html += '<p style="margin: 0;"><strong>⚠️ Nota:</strong> El archivo se generó pero no se pudo obtener la URL. Revisa la carpeta wp-content/uploads/users-toolkit/ para encontrar el archivo más reciente.</p>';
							html += '</div>';
						}
						
						html += '</div>';
						$content.html(html);
						$results.removeClass('error warning info').addClass('success').show();
						
						// Forzar visibilidad del contenedor
						$results.css('display', 'block');
						
						// Scroll hasta el contenedor de resultados
						setTimeout(function() {
							if ($results.length && $results.offset()) {
								$('html, body').animate({
									scrollTop: $results.offset().top - 100
								}, 500);
							}
						}, 100);
						
						// Intentar descarga automática después de un breve delay
						if (response.data.file_name) {
							// SIEMPRE usar download_url (endpoint AJAX)
							var downloadUrl = response.data.download_url;
							if (!downloadUrl) {
								// Si no hay download_url, crear uno usando el endpoint AJAX
								var nonce = response.data.download_nonce || '';
								downloadUrl = usersToolkit.ajaxurl + '?action=users_toolkit_download_export&file=' + encodeURIComponent(response.data.file_name) + (nonce ? '&nonce=' + encodeURIComponent(nonce) : '');
							}
							var fileName = response.data.file_name;
							
							// Asegurar que el nombre del archivo tenga extensión .json
							if (!fileName.endsWith('.json')) {
								fileName = fileName.replace(/\.[^/.]+$/, '') + '.json';
							}
							

							
							if (downloadUrl) {
								setTimeout(function() {
									try {
										// Crear iframe temporal para forzar descarga
										var iframe = document.createElement('iframe');
										iframe.style.display = 'none';
										iframe.src = downloadUrl;
										document.body.appendChild(iframe);
										
										// Remover iframe después de un tiempo
										setTimeout(function() {
											if (iframe.parentNode) {
												document.body.removeChild(iframe);
											}
										}, 5000);
										
										// También intentar con el enlace como fallback después de un momento
										setTimeout(function() {
											var $link = $('#users-toolkit-download-link');
											if ($link.length) {
												$link[0].click();
											}
										}, 500);
									} catch (e) {
										console.warn('Error en descarga automática:', e);
										// Mostrar mensaje y enlace manual
										var $link = $('#users-toolkit-download-link');
										if ($link.length) {
											$link[0].click();
										}
									}
								}, 1000);
							} else {
								console.warn('Users Toolkit: No hay URL de descarga disponible');
							}
						}
					} else {
						showError($content, response.data.message || 'Error al exportar usuarios.');
						$results.removeClass('success warning').addClass('error').show();
						$('html, body').animate({
							scrollTop: $results.offset().top - 100
						}, 500);
					}
				},
				error: function(xhr, status, error) {
					console.error('exportUsers error:', xhr, status, error);
					var errorMsg = 'Error de comunicación al exportar usuarios.';
					if (status === 'timeout') {
						errorMsg = '⏱️ La operación tardó demasiado (más de 10 minutos).';
						errorMsg += '<br><br><strong>El archivo puede haberse generado correctamente.</strong>';
						errorMsg += '<br>Por favor, revisa la carpeta <code>wp-content/uploads/users-toolkit/</code> para encontrar el archivo más reciente.';
						errorMsg += '<br>Busca archivos que empiecen con <code>exported-users-</code> y ordenados por fecha.';
					} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}
					
					var errorHtml = '<div class="users-toolkit-message error" style="padding: 15px; margin: 10px 0; background: #f8d7da; border-left: 4px solid #d63638; border-radius: 4px;">';
					errorHtml += '<h4 style="margin-top: 0; color: #d63638;">❌ Error en la Exportación</h4>';
					errorHtml += '<p>' + errorMsg + '</p>';
					errorHtml += '</div>';
					
					$content.html(errorHtml);
					$results.removeClass('success warning info').addClass('error').show();
					$results.css('display', 'block');
					
					setTimeout(function() {
						if ($results.length && $results.offset()) {
							$('html, body').animate({
								scrollTop: $results.offset().top - 100
							}, 500);
						}
					}, 100);
				}
			});
		}

	});

})(jQuery);
