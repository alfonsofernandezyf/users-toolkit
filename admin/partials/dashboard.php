<?php
/**
 * Dashboard page template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap users-toolkit-dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="users-toolkit-stats-grid">
		<div class="users-toolkit-stat-card">
			<h3><?php esc_html_e( 'Total Usuarios', 'users-toolkit' ); ?></h3>
			<p class="users-toolkit-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_users'] ) ); ?></p>
		</div>

		<div class="users-toolkit-stat-card">
			<h3><?php esc_html_e( 'Total Posts', 'users-toolkit' ); ?></h3>
			<p class="users-toolkit-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_posts'] ) ); ?></p>
		</div>

		<div class="users-toolkit-stat-card">
			<h3><?php esc_html_e( 'Comentarios Spam', 'users-toolkit' ); ?></h3>
			<p class="users-toolkit-stat-number users-toolkit-stat-warning"><?php echo esc_html( number_format_i18n( $stats['spam_comments'] ) ); ?></p>
		</div>

		<div class="users-toolkit-stat-card">
			<h3><?php esc_html_e( 'Comentarios Papelera', 'users-toolkit' ); ?></h3>
			<p class="users-toolkit-stat-number users-toolkit-stat-warning"><?php echo esc_html( number_format_i18n( $stats['trash_comments'] ) ); ?></p>
		</div>

		<div class="users-toolkit-stat-card">
			<h3><?php esc_html_e( 'Transients', 'users-toolkit' ); ?></h3>
			<p class="users-toolkit-stat-number"><?php echo esc_html( number_format_i18n( $stats['transients_count'] ) ); ?></p>
		</div>

		<div class="users-toolkit-stat-card">
			<h3><?php esc_html_e( 'Total Opciones', 'users-toolkit' ); ?></h3>
			<p class="users-toolkit-stat-number"><?php echo esc_html( number_format_i18n( $stats['options_count'] ) ); ?></p>
		</div>
	</div>

	<div class="users-toolkit-quick-actions">
		<h2><?php esc_html_e( 'Acciones Rápidas', 'users-toolkit' ); ?></h2>
		
		<div class="users-toolkit-action-buttons">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=users-toolkit-backup' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Crear Backup', 'users-toolkit' ); ?>
			</a>
			
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=users-toolkit-spam' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Identificar Usuarios Spam', 'users-toolkit' ); ?>
			</a>
			
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=users-toolkit-db' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Optimizar Base de Datos', 'users-toolkit' ); ?>
			</a>
		</div>
	</div>

	<div class="users-toolkit-info-box">
		<h3><?php esc_html_e( 'Información', 'users-toolkit' ); ?></h3>
		<p><?php esc_html_e( 'Este plugin te permite identificar y eliminar usuarios spam que no tienen cursos, pedidos ni constancias. También incluye herramientas para optimizar la base de datos, limpiar eventos cron obsoletos y crear backups de la base de datos.', 'users-toolkit' ); ?></p>
		<p><strong><?php esc_html_e( 'Importante:', 'users-toolkit' ); ?></strong> <?php esc_html_e( 'Siempre crea un backup completo de tu base de datos antes de eliminar usuarios o realizar optimizaciones. Puedes crear backups desde el menú "Backups".', 'users-toolkit' ); ?></p>
	</div>
</div>
