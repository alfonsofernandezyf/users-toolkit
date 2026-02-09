<?php
/**
 * Backup page template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap users-toolkit-backup">
	<h1><?php esc_html_e( 'Backups de Base de Datos', 'users-toolkit' ); ?></h1>

	<div class="users-toolkit-backup-section">
		<h2><?php esc_html_e( 'Crear Backup', 'users-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Crea una copia de seguridad completa de tu base de datos. Se recomienda crear un backup antes de realizar cualquier operación de limpieza o eliminación.', 'users-toolkit' ); ?></p>
		
		<button type="button" id="users-toolkit-create-backup" class="button button-primary button-large">
			<?php esc_html_e( 'Crear Backup', 'users-toolkit' ); ?>
		</button>

		<div id="users-toolkit-backup-progress-container" style="display: none; margin: 20px 0;">
			<h3><?php esc_html_e( 'Progreso del Backup', 'users-toolkit' ); ?></h3>
			<div class="users-toolkit-progress-wrapper">
				<div class="users-toolkit-progress">
					<div id="users-toolkit-backup-progress-bar" class="users-toolkit-progress-bar" style="width: 0%;">0%</div>
				</div>
				<p id="users-toolkit-backup-progress-message" style="margin: 10px 0; color: #646970;"></p>
			</div>
			<div id="users-toolkit-backup-progress-log" class="users-toolkit-progress-log" style="max-height: 200px; overflow-y: auto; background: #f0f0f1; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
			</div>
		</div>

		<div id="users-toolkit-backup-results" class="users-toolkit-results" style="display: none;">
			<h3><?php esc_html_e( 'Resultados', 'users-toolkit' ); ?></h3>
			<div id="users-toolkit-backup-content"></div>
		</div>
	</div>

	<?php if ( ! empty( $backups ) ) : ?>
	<div class="users-toolkit-backup-section">
		<h2><?php esc_html_e( 'Backups Disponibles', 'users-toolkit' ); ?></h2>
		<p><?php echo sprintf( esc_html__( 'Se encontraron %d backups.', 'users-toolkit' ), count( $backups ) ); ?></p>

		<div class="users-toolkit-backup-table-wrapper">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Archivo', 'users-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Fecha', 'users-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Tamaño', 'users-toolkit' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'users-toolkit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $backups as $backup ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $backup['filename'] ); ?></strong></td>
						<td><?php echo esc_html( $backup['date_human'] ); ?></td>
						<td><?php echo esc_html( $backup['size_human'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=users-toolkit-backup&users_toolkit_download_backup=1&filename=' . urlencode( $backup['filename'] ) ), 'users_toolkit_download_backup', 'users_toolkit_backup_nonce' ) ); ?>" class="button button-small button-primary">
								<?php esc_html_e( 'Descargar', 'users-toolkit' ); ?>
							</a>
							<button type="button" class="button button-small button-link-delete users-toolkit-delete-backup-btn" data-filename="<?php echo esc_attr( $backup['filename'] ); ?>">
								<?php esc_html_e( 'Eliminar', 'users-toolkit' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php else : ?>
	<div class="users-toolkit-backup-section">
		<p class="users-toolkit-message info">
			<?php esc_html_e( 'No hay backups disponibles. Crea tu primer backup ahora.', 'users-toolkit' ); ?>
		</p>
	</div>
	<?php endif; ?>

	<div class="users-toolkit-info-box">
		<h3><?php esc_html_e( 'Información sobre Backups', 'users-toolkit' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Los backups se almacenan en el directorio de uploads y se comprimen automáticamente si es posible.', 'users-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Se recomienda crear un backup antes de eliminar usuarios spam o realizar optimizaciones de base de datos.', 'users-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Los backups incluyen todas las tablas de la base de datos.', 'users-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Los archivos con extensión .gz están comprimidos para ahorrar espacio.', 'users-toolkit' ); ?></li>
		</ul>
	</div>
</div>
