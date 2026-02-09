<?php
/**
 * Spam users page template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap users-toolkit-spam">
	<h1><?php esc_html_e( 'Usuarios Spam', 'users-toolkit' ); ?></h1>

	<div class="users-toolkit-spam-section">
		<h2><?php esc_html_e( 'Identificar Usuarios', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Selecciona los criterios para identificar usuarios. Puedes buscar usuarios SIN actividad o CON actividad específica.', 'users-toolkit' ); ?></p>
		
		<div class="users-toolkit-search-criteria" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Criterios de Búsqueda', 'users-toolkit' ); ?></h3>
			<p style="font-size: 13px; color: #646970; margin-bottom: 15px;">
				<?php esc_html_e( 'Selecciona qué actividades deben tener o NO tener los usuarios. Si no seleccionas ningún criterio, se buscarán usuarios sin ninguna actividad (sin cursos, pedidos, certificados ni comentarios).', 'users-toolkit' ); ?>
			</p>
			<div style="margin-bottom: 15px; padding: 10px; background: #e5f5fa; border-left: 4px solid #2271b1; border-radius: 4px; font-size: 12px;">
				<strong>💡 Tip:</strong> <?php esc_html_e( 'Para ver TODOS los usuarios de un rol específico (ej: todos los administradores o suscriptores), solo selecciona el rol y NO marques ningún otro criterio.', 'users-toolkit' ); ?>
			</div>
			
			<!-- Selector de Roles -->
			<div style="margin-bottom: 20px; padding: 12px; background: #e5f5fa; border-left: 4px solid #2271b1; border-radius: 4px;">
				<label style="display: block; margin-bottom: 8px; font-weight: bold;">
					<?php esc_html_e( 'Filtrar por Rol de Usuario:', 'users-toolkit' ); ?>
				</label>
				<select name="user_roles[]" id="users-toolkit-user-roles" multiple style="width: 100%; min-height: 120px; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 13px;">
					<?php
					global $wp_roles;
					$roles = $wp_roles->get_names();
					foreach ( $roles as $role_key => $role_name ) {
						echo '<option value="' . esc_attr( $role_key ) . '">' . esc_html( $role_name ) . ' (' . esc_html( $role_key ) . ')</option>';
					}
					?>
				</select>
				<p style="margin: 8px 0 0 0; font-size: 12px; color: #646970;">
					<?php esc_html_e( 'Mantén presionada la tecla Ctrl (Cmd en Mac) para seleccionar múltiples roles. Si no seleccionas ningún rol, se buscarán usuarios de todos los roles.', 'users-toolkit' ); ?>
				</p>
				<button type="button" id="users-toolkit-clear-roles" class="button button-small" style="margin-top: 8px;">
					<?php esc_html_e( 'Limpiar Selección', 'users-toolkit' ); ?>
				</button>
			</div>
			
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
				<div style="padding: 15px; background: #e7f5e7; border: 1px solid #00a32a; border-radius: 4px;">
					<strong style="display: block; margin-bottom: 10px; color: #00a32a;"><?php esc_html_e( '✓ Debe TENER:', 'users-toolkit' ); ?></strong>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_positive[]" value="courses">
						<?php esc_html_e( 'Cursos LearnDash', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_positive[]" value="certificates">
						<?php esc_html_e( 'Certificados LearnDash', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_positive[]" value="orders">
						<?php esc_html_e( 'Pedidos/Compras WooCommerce', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_positive[]" value="comments">
						<?php esc_html_e( 'Comentarios', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_positive[]" value="memberships">
						<?php esc_html_e( 'Membresías WooCommerce', 'users-toolkit' ); ?>
					</label>
					
					<!-- Checkboxes dinámicos para tipos de post -->
					<div id="users-toolkit-post-types-positive" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #00a32a;">
						<strong style="display: block; margin-bottom: 8px; color: #00a32a; font-size: 12px;"><?php esc_html_e( 'Contenido (autores de):', 'users-toolkit' ); ?></strong>
						<label style="display: block; margin: 8px 0; font-size: 13px; font-weight: 600;">
							<input type="checkbox" name="post_types_positive[]" value="any" class="users-toolkit-post-type-checkbox users-toolkit-post-type-any">
							<?php esc_html_e( '✓ Cualquier tipo de contenido', 'users-toolkit' ); ?>
						</label>
						<?php
						// Obtener todos los post types públicos (excepto los excluidos)
						$excluded_types = array( 'revision', 'nav_menu_item', 'attachment', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block' );
						$post_types = get_post_types( array( 'public' => true ), 'objects' );
						foreach ( $post_types as $post_type ) {
							if ( in_array( $post_type->name, $excluded_types, true ) ) {
								continue;
							}
							$label = $post_type->label ?: $post_type->name;
							echo '<label style="display: block; margin: 6px 0; font-size: 12px;">';
							echo '<input type="checkbox" name="post_types_positive[]" value="' . esc_attr( $post_type->name ) . '" class="users-toolkit-post-type-checkbox users-toolkit-post-type-specific">';
							echo esc_html( $label ) . ' (' . esc_html( $post_type->name ) . ')';
							echo '</label>';
						}
						?>
					</div>
				</div>
				<div style="padding: 15px; background: #fff3cd; border: 1px solid #ffb900; border-radius: 4px;">
					<strong style="display: block; margin-bottom: 10px; color: #d63638;"><?php esc_html_e( '✗ NO debe tener:', 'users-toolkit' ); ?></strong>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_negative[]" value="courses">
						<?php esc_html_e( 'Cursos LearnDash', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_negative[]" value="certificates">
						<?php esc_html_e( 'Certificados LearnDash', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_negative[]" value="orders">
						<?php esc_html_e( 'Pedidos/Compras WooCommerce', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_negative[]" value="comments">
						<?php esc_html_e( 'Comentarios', 'users-toolkit' ); ?>
					</label>
					<label style="display: block; margin: 8px 0;">
						<input type="checkbox" name="criteria_negative[]" value="memberships">
						<?php esc_html_e( 'Membresías WooCommerce', 'users-toolkit' ); ?>
					</label>
					
					<!-- Checkboxes dinámicos para tipos de post (negativos) -->
					<div id="users-toolkit-post-types-negative" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ffb900;">
						<strong style="display: block; margin-bottom: 8px; color: #d63638; font-size: 12px;"><?php esc_html_e( 'Contenido (NO debe ser autor de):', 'users-toolkit' ); ?></strong>
						<label style="display: block; margin: 8px 0; font-size: 13px; font-weight: 600;">
							<input type="checkbox" name="post_types_negative[]" value="any" class="users-toolkit-post-type-checkbox users-toolkit-post-type-any">
							<?php esc_html_e( '✗ NO debe tener ningún tipo de contenido', 'users-toolkit' ); ?>
						</label>
						<?php
						// Reutilizar los mismos post types
						foreach ( $post_types as $post_type ) {
							if ( in_array( $post_type->name, $excluded_types, true ) ) {
								continue;
							}
							$label = $post_type->label ?: $post_type->name;
							echo '<label style="display: block; margin: 6px 0; font-size: 12px;">';
							echo '<input type="checkbox" name="post_types_negative[]" value="' . esc_attr( $post_type->name ) . '" class="users-toolkit-post-type-checkbox users-toolkit-post-type-specific">';
							echo esc_html( $label ) . ' (' . esc_html( $post_type->name ) . ')';
							echo '</label>';
						}
						?>
					</div>
				</div>
			</div>

			<div style="margin-top: 15px; padding: 12px; background: #e5f5fa; border-left: 4px solid #2271b1; border-radius: 4px;">
				<label style="display: block; margin: 0;">
					<input type="checkbox" name="match_all" value="1" id="users-toolkit-match-all" style="margin-right: 8px;">
					<strong><?php esc_html_e( 'Coincidir con TODOS los criterios seleccionados', 'users-toolkit' ); ?></strong>
				</label>
				<p style="margin: 8px 0 0 0; font-size: 12px; color: #646970; padding-left: 24px;">
					<?php esc_html_e( '(Si no está marcado, coincidirá con CUALQUIERA de los criterios seleccionados en cada columna. Ejemplo: si marcas "Debe TENER: Cursos" y "NO debe tener: Certificados", encontrará usuarios que tienen cursos PERO NO tienen certificados)', 'users-toolkit' ); ?>
				</p>
			</div>
		</div>
		
		<button type="button" id="users-toolkit-identify-spam" class="button button-primary button-large">
			<?php esc_html_e( 'Identificar Usuarios', 'users-toolkit' ); ?>
		</button>

		<div id="users-toolkit-progress-container" style="display: none; margin: 20px 0;">
			<h3><?php esc_html_e( 'Progreso', 'users-toolkit' ); ?></h3>
			<div class="users-toolkit-progress-wrapper">
				<div class="users-toolkit-progress">
					<div id="users-toolkit-progress-bar" class="users-toolkit-progress-bar" style="width: 0%;">0%</div>
				</div>
				<p id="users-toolkit-progress-message" style="margin: 10px 0; color: #646970;"></p>
			</div>
			<div id="users-toolkit-progress-log" class="users-toolkit-progress-log" style="max-height: 200px; overflow-y: auto; background: #f0f0f1; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
			</div>
		</div>

		<div id="users-toolkit-identify-results" class="users-toolkit-results" style="display: none;">
			<h3><?php esc_html_e( 'Resultados', 'users-toolkit' ); ?></h3>
			<div id="users-toolkit-identify-content"></div>
		</div>
	</div>

	<?php if ( ! empty( $existing_json_files ) ) : ?>
	<div class="users-toolkit-spam-section">
		<h2><?php esc_html_e( 'Listas Anteriores', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Cargar una lista guardada anteriormente:', 'users-toolkit' ); ?></p>
		<ul class="users-toolkit-file-list">
			<?php foreach ( $existing_json_files as $ef ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=users-toolkit-spam&load_file=' . rawurlencode( $ef['name'] ) ) ); ?>">
					<?php echo esc_html( $ef['name'] ); ?>
				</a>
				<span class="description">(<?php echo esc_html( $ef['date'] ); ?>)</span>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $spam_users ) ) : ?>
	<div class="users-toolkit-spam-section">
		<h2><?php esc_html_e( 'Usuarios Spam Identificados', 'users-toolkit' ); ?></h2>
		<p>
			<?php echo sprintf( esc_html__( 'Total de usuarios en la lista: %d', 'users-toolkit' ), count( $spam_users ) ); ?>
			<span id="users-toolkit-visible-count" style="margin-left: 15px; font-weight: bold; color: #2271b1;"></span>
		</p>

		<!-- Panel de Acciones Mejorado -->
		<div class="users-toolkit-action-panel" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border: 2px solid #2271b1; border-radius: 8px;">
			<h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Acciones Disponibles', 'users-toolkit' ); ?></h3>
			
			<!-- Selector de Alcance -->
			<div style="margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
				<label style="display: flex; align-items: center; font-weight: bold; margin-bottom: 10px;">
					<input type="checkbox" id="users-toolkit-apply-to-all" style="margin-right: 8px; width: 18px; height: 18px;">
					<span><?php esc_html_e( 'Aplicar a TODOS los usuarios de la lista', 'users-toolkit' ); ?></span>
				</label>
				<p style="margin: 5px 0 0 25px; font-size: 12px; color: #646970;">
					<?php esc_html_e( 'Si está desmarcado, la acción se aplicará solo a los usuarios seleccionados con el checkbox.', 'users-toolkit' ); ?>
				</p>
			</div>
			
			<!-- Selector de Acción -->
			<div style="margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
				<label style="display: block; font-weight: bold; margin-bottom: 10px;">
					<?php esc_html_e( 'Selecciona una acción:', 'users-toolkit' ); ?>
				</label>
				<select id="users-toolkit-action-selector" style="width: 100%; padding: 8px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px;">
					<option value=""><?php esc_html_e( '-- Selecciona una acción --', 'users-toolkit' ); ?></option>
					<option value="simulate"><?php esc_html_e( '🔍 Simular (NO borra, solo muestra qué pasaría)', 'users-toolkit' ); ?></option>
					<option value="export"><?php esc_html_e( '📥 Exportar usuarios con metadatos', 'users-toolkit' ); ?></option>
					<option value="delete"><?php esc_html_e( '🗑️ Eliminar usuarios permanentemente', 'users-toolkit' ); ?></option>
				</select>
			</div>
			
			<!-- Botón de Ejecutar -->
			<div style="text-align: center;">
				<button type="button" id="users-toolkit-execute-action" class="button button-primary button-large" style="font-size: 16px; padding: 10px 30px;" disabled>
					<?php esc_html_e( 'Ejecutar Acción', 'users-toolkit' ); ?>
				</button>
			</div>
			
			<!-- Información de Seguridad -->
			<div id="users-toolkit-action-info" style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 4px; display: none;">
				<p style="margin: 0; font-size: 13px; color: #856404;">
					<strong>⚠️ Advertencia:</strong> <span id="users-toolkit-action-warning-text"></span>
				</p>
			</div>
		</div>

		<!-- Contenedor de resultados (mostrar antes de la lista) -->
		<div id="users-toolkit-delete-results" class="users-toolkit-results" style="display: none; margin-bottom: 20px;">
			<h3><?php esc_html_e( 'Resultados de Operación', 'users-toolkit' ); ?></h3>
			<div id="users-toolkit-delete-content"></div>
		</div>

		<div class="users-toolkit-spam-table-wrapper">
			<!-- Campo de búsqueda/filtrado -->
			<div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">
					<label for="users-toolkit-search-input" style="font-weight: bold; margin: 0;">
						<?php esc_html_e( 'Buscar/Filtrar usuarios:', 'users-toolkit' ); ?>
					</label>
					<strong id="users-toolkit-visible-count-header" style="color: #2271b1; font-size: 14px; white-space: nowrap;">
						<?php echo sprintf( esc_html__( 'Mostrando: %d de %d', 'users-toolkit' ), count( $spam_users ), count( $spam_users ) ); ?>
					</strong>
				</div>
				<div style="display: flex; gap: 8px; align-items: center;">
					<input type="text" id="users-toolkit-search-input" placeholder="<?php esc_attr_e( 'Buscar por ID, email, login, roles...', 'users-toolkit' ); ?>" style="flex: 1; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 13px;">
					<button type="button" id="users-toolkit-clear-search" class="button button-small" style="display: none; white-space: nowrap;">
						<?php esc_html_e( 'Limpiar búsqueda', 'users-toolkit' ); ?>
					</button>
				</div>
				<p style="margin: 8px 0 0 0; font-size: 12px; color: #646970;">
					<?php esc_html_e( 'Escribe para filtrar la lista en tiempo real. Puedes buscar por ID, email, login o roles. Haz clic en las columnas para ordenar.', 'users-toolkit' ); ?>
				</p>
			</div>
			
			<table class="wp-list-table widefat fixed striped" id="users-toolkit-spam-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column" style="width: 50px;">
							<input type="checkbox" id="users-toolkit-select-all" class="users-toolkit-select-all-checkbox">
						</td>
						<th class="sortable" data-column="id" data-type="number">
							<?php esc_html_e( 'ID', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="email" data-type="text">
							<?php esc_html_e( 'Email', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="login" data-type="text">
							<?php esc_html_e( 'Login', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="roles" data-type="text">
							<?php esc_html_e( 'Roles', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="courses" data-type="number">
							<?php esc_html_e( 'Cursos', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="orders" data-type="number">
							<?php esc_html_e( 'Pedidos', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="posts" data-type="number">
							<?php esc_html_e( 'Posts', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="memberships" data-type="number">
							<?php esc_html_e( 'Membresías', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="registered" data-type="date">
							<?php esc_html_e( 'Registrado', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
						<th class="sortable" data-column="days" data-type="number">
							<?php esc_html_e( 'Días', 'users-toolkit' ); ?>
							<span class="sort-indicator"></span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $spam_users as $user ) : 
						$user_id = isset( $user['ID'] ) ? (int) $user['ID'] : 0;
						$courses_count = isset( $user['courses'] ) ? (int) $user['courses'] : 0;
						$orders_count = isset( $user['orders'] ) ? (int) $user['orders'] : 0;
						$user_edit_url = admin_url( 'user-edit.php?user_id=' . $user_id );
						
						// URL para pedidos de WooCommerce (compatible con HPOS y legacy)
						$orders_url = '';
						if ( class_exists( 'WooCommerce' ) ) {
							// WooCommerce legacy: edit.php?post_type=shop_order&_customer_user=USER_ID
							// WooCommerce HPOS: admin.php?page=wc-orders&s=customer:USER_ID
							// Intentar HPOS primero, luego legacy
							if ( class_exists( '\Automattic\WooCommerce\Caches\OrderCache' ) ) {
								// WooCommerce con HPOS habilitado
								$orders_url = admin_url( 'admin.php?page=wc-orders&s=customer:' . $user_id );
							} else {
								// WooCommerce legacy
								$orders_url = admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $user_id );
							}
						} else {
							// Fallback si WooCommerce no está disponible
							$orders_url = admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $user_id );
						}
					?>
					<?php $memberships_count = isset( $user['memberships'] ) ? (int) $user['memberships'] : 0; ?>
					<tr data-id="<?php echo esc_attr( $user_id ); ?>" 
						data-email="<?php echo esc_attr( strtolower( $user['email'] ) ); ?>" 
						data-login="<?php echo esc_attr( strtolower( $user['login'] ) ); ?>" 
						data-roles="<?php echo esc_attr( strtolower( isset( $user['roles'] ) ? $user['roles'] : '' ) ); ?>" 
						data-courses="<?php echo esc_attr( $courses_count ); ?>" 
						data-orders="<?php echo esc_attr( $orders_count ); ?>" 
						data-posts="<?php echo esc_attr( isset( $user['posts'] ) ? (int) $user['posts'] : 0 ); ?>" 
						data-memberships="<?php echo esc_attr( $memberships_count ); ?>" 
						data-registered="<?php echo esc_attr( strtotime( $user['registered'] ) ); ?>" 
						data-days="<?php echo esc_attr( $user['days_old'] ); ?>">
						<th scope="row" class="check-column">
							<input type="checkbox" name="spam_users[]" value="<?php echo esc_attr( $user_id ); ?>" class="spam-user-checkbox">
						</th>
						<td>
							<a href="<?php echo esc_url( $user_edit_url ); ?>" target="_blank" title="<?php esc_attr_e( 'Abrir perfil del usuario en nueva pestaña', 'users-toolkit' ); ?>">
								<?php echo esc_html( $user_id ); ?>
							</a>
						</td>
						<td><?php echo esc_html( $user['email'] ); ?></td>
						<td><?php echo esc_html( $user['login'] ); ?></td>
						<td><?php echo esc_html( isset( $user['roles'] ) ? $user['roles'] : '' ); ?></td>
						<td><?php echo esc_html( $courses_count ); ?></td>
						<td>
							<?php if ( $orders_count > 0 ) : ?>
								<a href="<?php echo esc_url( $orders_url ); ?>" target="_blank" title="<?php esc_attr_e( 'Ver pedidos del usuario en nueva pestaña', 'users-toolkit' ); ?>">
									<?php echo esc_html( $orders_count ); ?>
								</a>
							<?php else : ?>
								<span style="color: #646970;"><?php echo esc_html( $orders_count ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php 
							$posts_count = isset( $user['posts'] ) ? (int) $user['posts'] : 0;
							if ( $posts_count > 0 ) : 
								$posts_url = admin_url( 'edit.php?author=' . $user_id );
							?>
								<a href="<?php echo esc_url( $posts_url ); ?>" target="_blank" title="<?php esc_attr_e( 'Ver posts del usuario en nueva pestaña', 'users-toolkit' ); ?>">
									<?php echo esc_html( $posts_count ); ?>
								</a>
							<?php else : ?>
								<span style="color: #646970;"><?php echo esc_html( $posts_count ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $memberships_count > 0 ) : 
								$memberships_url = admin_url( 'edit.php?post_type=wc_user_membership&author=' . $user_id );
							?>
								<a href="<?php echo esc_url( $memberships_url ); ?>" target="_blank" title="<?php esc_attr_e( 'Ver membresías del usuario en nueva pestaña', 'users-toolkit' ); ?>">
									<?php echo esc_html( $memberships_count ); ?>
								</a>
							<?php else : ?>
								<span style="color: #646970;"><?php echo esc_html( $memberships_count ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $user['registered'] ); ?></td>
						<td><?php echo esc_html( $user['days_old'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>
</div>
