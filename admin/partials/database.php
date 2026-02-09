<?php
/**
 * Database optimization page template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap users-toolkit-database">
	<h1><?php esc_html_e( 'Optimización de Base de Datos', 'users-toolkit' ); ?></h1>

	<div class="users-toolkit-db-controls" style="margin: 20px 0; padding: 15px; background: #f0f6fc; border: 1px solid #2271b1; border-radius: 4px;">
		<label style="display: flex; align-items: center; gap: 10px;">
			<input type="checkbox" id="users-toolkit-auto-load-stats" <?php echo $auto_load ? 'checked' : ''; ?>>
			<strong><?php esc_html_e( 'Cargar estadísticas automáticamente al abrir esta página', 'users-toolkit' ); ?></strong>
		</label>
		<p style="margin: 10px 0 0 0; font-size: 12px; color: #646970; padding-left: 24px;">
			<?php esc_html_e( '(Si está desactivado, las estadísticas solo se cargarán cuando uses "Previsualizar" o ejecutes una acción)', 'users-toolkit' ); ?>
		</p>
	</div>

	<?php if ( $auto_load && ! empty( $stats ) ) : ?>
		<div class="users-toolkit-db-stats-container" id="users-toolkit-db-stats-container">
			<button type="button" id="users-toolkit-load-stats" class="button button-secondary" style="margin-bottom: 20px;">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Recargar Estadísticas', 'users-toolkit' ); ?>
			</button>
			<div id="users-toolkit-stats-content">
				<div id="users-toolkit-transients-stats" style="display: block; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
					<p><strong><?php esc_html_e( 'Transients encontrados:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-transients-count"><?php echo esc_html( number_format_i18n( $stats['transients_count'] ) ); ?></span></p>
				</div>
				<div id="users-toolkit-comments-stats" style="display: block; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
					<p>
						<strong><?php esc_html_e( 'Comentarios spam:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-spam-comments-count"><?php echo esc_html( number_format_i18n( $stats['spam_comments'] ) ); ?></span><br>
						<strong><?php esc_html_e( 'Comentarios en papelera:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-trash-comments-count"><?php echo esc_html( number_format_i18n( $stats['trash_comments'] ) ); ?></span>
					</p>
				</div>
			</div>
		</div>
	<?php else : ?>
		<div class="users-toolkit-db-stats-container" id="users-toolkit-db-stats-container" style="display: none;">
			<button type="button" id="users-toolkit-load-stats" class="button button-secondary" style="margin-bottom: 20px;">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Cargar Estadísticas', 'users-toolkit' ); ?>
			</button>
			<div id="users-toolkit-stats-content"></div>
		</div>
	<?php endif; ?>

	<div class="users-toolkit-db-section" id="users-toolkit-section-transients">
		<h2><?php esc_html_e( 'Limpieza de Transients', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Elimina transients expirados que ocupan espacio innecesario en la base de datos.', 'users-toolkit' ); ?></p>
		<div id="users-toolkit-transients-stats" style="display: none; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
			<p><strong><?php esc_html_e( 'Transients encontrados:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-transients-count">-</span></p>
		</div>
		
		<div class="users-toolkit-action-buttons" style="margin-top: 15px;">
			<button type="button" class="users-toolkit-preview-btn button button-secondary" data-action="transients">
				<?php esc_html_e( 'Previsualizar', 'users-toolkit' ); ?>
			</button>
			<button type="button" id="users-toolkit-clean-transients" class="button button-secondary" data-action="transients">
				<?php esc_html_e( 'Limpiar Transients', 'users-toolkit' ); ?>
			</button>
		</div>
	</div>

	<div class="users-toolkit-db-section" id="users-toolkit-section-comments">
		<h2><?php esc_html_e( 'Limpieza de Comentarios', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Elimina comentarios marcados como spam o en la papelera.', 'users-toolkit' ); ?></p>
		<div id="users-toolkit-comments-stats" style="display: none; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
			<p>
				<strong><?php esc_html_e( 'Comentarios spam:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-spam-comments-count">-</span><br>
				<strong><?php esc_html_e( 'Comentarios en papelera:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-trash-comments-count">-</span>
			</p>
		</div>
		
		<div class="users-toolkit-action-buttons" style="margin-top: 15px;">
			<button type="button" class="users-toolkit-preview-btn button button-secondary" data-action="comments">
				<?php esc_html_e( 'Previsualizar', 'users-toolkit' ); ?>
			</button>
			<button type="button" id="users-toolkit-clean-comments" class="button button-secondary" data-action="comments">
				<?php esc_html_e( 'Limpiar Comentarios', 'users-toolkit' ); ?>
			</button>
		</div>
	</div>

	<div class="users-toolkit-db-section" id="users-toolkit-section-cron">
		<h2><?php esc_html_e( 'Limpieza de Cron', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Elimina eventos de cron obsoletos que pueden causar errores y afectar el rendimiento.', 'users-toolkit' ); ?></p>
		<div id="users-toolkit-cron-stats" style="display: none; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">
			<p><strong><?php esc_html_e( 'Eventos cron obsoletos encontrados:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-cron-count">-</span></p>
		</div>
		
		<div class="users-toolkit-action-buttons" style="margin-top: 15px;">
			<button type="button" class="users-toolkit-preview-btn button button-secondary" data-action="cron">
				<?php esc_html_e( 'Previsualizar', 'users-toolkit' ); ?>
			</button>
			<button type="button" id="users-toolkit-clean-cron" class="button button-secondary">
				<?php esc_html_e( 'Limpiar Cron', 'users-toolkit' ); ?>
			</button>
		</div>
	</div>

	<div class="users-toolkit-db-section" id="users-toolkit-section-autoload">
		<h2><?php esc_html_e( 'Optimización de Opciones Autoloaded', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Analiza y optimiza las opciones que se cargan automáticamente en cada petición. Desactivar autoload en opciones grandes que no se usan frecuentemente puede mejorar significativamente el rendimiento.', 'users-toolkit' ); ?></p>
		
		<div id="users-toolkit-autoload-stats" style="display: none; margin: 10px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
			<h4 style="margin-top: 0;"><?php esc_html_e( 'Estadísticas de Opciones Autoloaded', 'users-toolkit' ); ?></h4>
			<p>
				<strong><?php esc_html_e( 'Total de opciones autoloaded:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-autoload-total">-</span><br>
				<strong><?php esc_html_e( 'Tamaño total:', 'users-toolkit' ); ?></strong> <span id="users-toolkit-autoload-size-mb">-</span> MB
			</p>
		</div>
		
		<div style="margin: 15px 0; padding: 12px; background: #fff3cd; border-left: 4px solid #ffb900; border-radius: 4px;">
			<label>
				<strong><?php esc_html_e( 'Umbral mínimo (KB):', 'users-toolkit' ); ?></strong>
				<input type="number" id="users-toolkit-autoload-threshold" value="1" min="1" max="1000" style="width: 80px; margin-left: 10px;">
				<span style="font-size: 12px; color: #646970;"><?php esc_html_e( '(Solo se mostrarán opciones mayores a este tamaño. Recomendado: 1-5 KB)', 'users-toolkit' ); ?></span>
			</label>
			<p style="margin: 8px 0 0 0; font-size: 12px; color: #646970;">
				<?php esc_html_e( 'Nota: Las opciones pequeñas normalmente son menores a 1 KB. Opciones medianas: 1-10 KB. Opciones grandes: >10 KB. El umbral por defecto de 1 KB encontrará la mayoría de opciones optimizables.', 'users-toolkit' ); ?>
			</p>
		</div>
		
		<div class="users-toolkit-action-buttons" style="margin-top: 15px;">
			<button type="button" id="users-toolkit-load-autoload-stats" class="button button-secondary">
				<span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Ver Estadísticas', 'users-toolkit' ); ?>
			</button>
			<button type="button" id="users-toolkit-preview-autoload" class="button button-secondary">
				<?php esc_html_e( 'Previsualizar Opciones Grandes', 'users-toolkit' ); ?>
			</button>
		</div>
		
		<div id="users-toolkit-autoload-preview" style="display: none; margin-top: 20px; padding: 15px; background: #e5f5fa; border-left: 4px solid #2271b1; border-radius: 4px;">
			<h4><?php esc_html_e( 'Opciones Sugeridas para Desactivar Autoload', 'users-toolkit' ); ?></h4>
			<div id="users-toolkit-autoload-preview-content"></div>
		</div>
	</div>

	<div class="users-toolkit-db-section" id="users-toolkit-section-optimize">
		<h2><?php esc_html_e( 'Optimizar Tablas', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Optimiza las tablas principales de WordPress para mejorar el rendimiento de la base de datos.', 'users-toolkit' ); ?></p>
		<p style="color: #646970; font-size: 13px;">
			<?php esc_html_e( 'Nota: La optimización de tablas no tiene previsualización, ya que no elimina datos sino que reorganiza el almacenamiento.', 'users-toolkit' ); ?>
		</p>
		
		<button type="button" id="users-toolkit-optimize-tables" class="button button-primary" data-action="optimize" style="margin-top: 15px;">
			<?php esc_html_e( 'Optimizar Tablas', 'users-toolkit' ); ?>
		</button>
	</div>

	<div class="users-toolkit-db-section" id="users-toolkit-section-all">
		<h2><?php esc_html_e( 'Optimización Completa', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Ejecuta todas las optimizaciones de una vez: limpia transients, comentarios y optimiza tablas.', 'users-toolkit' ); ?></p>
		
		<div class="users-toolkit-action-buttons" style="margin-top: 15px;">
			<button type="button" class="users-toolkit-preview-btn button button-secondary button-large" data-action="all">
				<?php esc_html_e( 'Previsualizar Todo', 'users-toolkit' ); ?>
			</button>
			<button type="button" id="users-toolkit-optimize-all" class="button button-primary button-large" data-action="all">
				<?php esc_html_e( 'Optimización Completa', 'users-toolkit' ); ?>
			</button>
		</div>
	</div>

	<div id="users-toolkit-db-results" class="users-toolkit-results" style="display: none;">
		<h3><?php esc_html_e( 'Resultados', 'users-toolkit' ); ?></h3>
		<div id="users-toolkit-db-content"></div>
	</div>

	<div class="users-toolkit-warning-box">
		<p><strong><?php esc_html_e( 'Advertencia:', 'users-toolkit' ); ?></strong> <?php esc_html_e( 'Estas operaciones pueden tomar tiempo dependiendo del tamaño de tu base de datos. Se recomienda realizar un backup antes de ejecutar optimizaciones.', 'users-toolkit' ); ?></p>
	</div>
</div>
