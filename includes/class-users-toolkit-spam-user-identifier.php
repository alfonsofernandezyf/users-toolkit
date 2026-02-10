<?php

/**
 * Class to identify spam users
 */
class Users_Toolkit_Spam_User_Identifier {

	/**
	 * Identify spam users based on criteria
	 *
	 * @param string $operation_id         Optional operation ID for progress tracking
	 * @param array  $criteria_positive    Optional array of positive criteria (must have): 'courses', 'certificates', 'orders', 'comments', 'memberships', 'dlm_downloads', 'suspicious_email'
	 * @param array  $criteria_negative    Optional array of negative criteria (must NOT have): 'courses', 'certificates', 'orders', 'comments', 'memberships', 'dlm_downloads', 'suspicious_email'
	 * @param bool   $match_all            If true, user must match ALL criteria. If false, match ANY criterion.
	 * @param array  $post_types_positive  Optional array of post types user must be author of
	 * @param array  $post_types_negative  Optional array of post types user must NOT be author of
	 * @param array  $user_roles           Optional array of user roles to filter by
	 * @return array Array of user IDs and details that match the criteria
	 */
	public static function identify_spam_users( $operation_id = '', $criteria_positive = array(), $criteria_negative = array(), $match_all = false, $post_types_positive = array(), $post_types_negative = array(), $user_roles = array() ) {
		global $wpdb;

		// Aumentar tiempo y memoria para bases con muchos usuarios.
		set_time_limit( 1800 );
		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'memory_limit', '512M' );
			@ini_set( 'max_execution_time', 1800 );
		}

		$spam_users = array();
		$all_users = $wpdb->get_results( "SELECT ID, user_email, user_login, user_registered FROM {$wpdb->users} ORDER BY ID", ARRAY_A );
		$total = count( $all_users );
		$processed = 0;
		$has_positive_criteria = ! empty( $criteria_positive ) || ! empty( $post_types_positive );
		$has_negative_criteria = ! empty( $criteria_negative ) || ! empty( $post_types_negative );
		$has_role_filter = ! empty( $user_roles );
		$use_default_inactive_mode = ! $has_positive_criteria && ! $has_negative_criteria && ! $has_role_filter;
		$all_user_ids = array();
		$email_to_user_ids_map = array();
		$user_profile_map = array();

		foreach ( (array) $all_users as $user_row ) {
			$user_id = isset( $user_row['ID'] ) ? (int) $user_row['ID'] : 0;
			if ( $user_id <= 0 ) {
				continue;
			}

			$user_email = isset( $user_row['user_email'] ) ? strtolower( trim( (string) $user_row['user_email'] ) ) : '';

			$all_user_ids[] = $user_id;
			if ( ! empty( $user_email ) ) {
				if ( ! isset( $email_to_user_ids_map[ $user_email ] ) ) {
					$email_to_user_ids_map[ $user_email ] = array();
				}
				$email_to_user_ids_map[ $user_email ][] = $user_id;
			}

			$user_profile_map[ $user_id ] = array(
				'first_name' => '',
				'last_name'  => '',
				'city'       => '',
				'country'    => '',
			);
		}

		$profile_meta_keys = array( 'first_name', 'last_name', 'billing_city', 'city', 'billing_country', 'country' );
		if ( ! empty( $all_user_ids ) ) {
			foreach ( array_chunk( $all_user_ids, 400 ) as $user_ids_chunk ) {
				$id_placeholders = implode( ',', array_fill( 0, count( $user_ids_chunk ), '%d' ) );
				$key_placeholders = implode( ',', array_fill( 0, count( $profile_meta_keys ), '%s' ) );

				$sql_args = array_merge(
					array( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id IN ({$id_placeholders}) AND meta_key IN ({$key_placeholders})" ),
					$user_ids_chunk,
					$profile_meta_keys
				);
				$meta_sql = call_user_func_array( array( $wpdb, 'prepare' ), $sql_args );
				$meta_rows = $wpdb->get_results( $meta_sql, ARRAY_A );

				foreach ( (array) $meta_rows as $meta_row ) {
					$uid = isset( $meta_row['user_id'] ) ? (int) $meta_row['user_id'] : 0;
					if ( $uid <= 0 || ! isset( $user_profile_map[ $uid ] ) ) {
						continue;
					}

					$meta_key = isset( $meta_row['meta_key'] ) ? (string) $meta_row['meta_key'] : '';
					$meta_value_raw = isset( $meta_row['meta_value'] ) ? maybe_unserialize( $meta_row['meta_value'] ) : '';
					if ( is_scalar( $meta_value_raw ) ) {
						$meta_value = trim( wp_strip_all_tags( (string) $meta_value_raw ) );
					} else {
						$meta_value = '';
					}

					if ( '' === $meta_value ) {
						continue;
					}

					if ( 'first_name' === $meta_key ) {
						$user_profile_map[ $uid ]['first_name'] = $meta_value;
					} elseif ( 'last_name' === $meta_key ) {
						$user_profile_map[ $uid ]['last_name'] = $meta_value;
					} elseif ( 'billing_city' === $meta_key ) {
						$user_profile_map[ $uid ]['city'] = $meta_value;
					} elseif ( 'city' === $meta_key && '' === $user_profile_map[ $uid ]['city'] ) {
						$user_profile_map[ $uid ]['city'] = $meta_value;
					} elseif ( 'billing_country' === $meta_key ) {
						$user_profile_map[ $uid ]['country'] = $meta_value;
					} elseif ( 'country' === $meta_key && '' === $user_profile_map[ $uid ]['country'] ) {
						$user_profile_map[ $uid ]['country'] = $meta_value;
					}
				}
			}
		}

		/*
		 * Mapa de roles/capacidades sin instanciar WP_User por cada ID.
		 * Esto reduce uso de memoria en sitios con miles de usuarios.
		 */
		$capabilities_key = $wpdb->prefix . 'capabilities';
		$cap_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$capabilities_key
			),
			ARRAY_A
		);
		$user_roles_map = array();
		$user_caps_map = array();
		foreach ( (array) $cap_rows as $cap_row ) {
			$uid = isset( $cap_row['user_id'] ) ? (int) $cap_row['user_id'] : 0;
			if ( $uid <= 0 ) {
				continue;
			}
			$caps = maybe_unserialize( isset( $cap_row['meta_value'] ) ? $cap_row['meta_value'] : array() );
			if ( ! is_array( $caps ) ) {
				$caps = array();
			}
			$user_caps_map[ $uid ] = $caps;
			$roles = array();
			foreach ( $caps as $cap_key => $enabled ) {
				if ( ! $enabled || ! is_string( $cap_key ) ) {
					continue;
				}
				if ( strpos( $cap_key, 'level_' ) === 0 || 'manage_options' === $cap_key ) {
					continue;
				}
				$roles[] = $cap_key;
			}
			$user_roles_map[ $uid ] = $roles;
		}

		if ( ! empty( $operation_id ) ) {
			$criteria_desc = '';
			
			// Agregar filtro de roles si existe
			if ( ! empty( $user_roles ) ) {
				global $wp_roles;
				$role_names = array();
				foreach ( $user_roles as $role_key ) {
					$role_names[] = $wp_roles->get_names()[ $role_key ] ?? $role_key;
				}
				$criteria_desc .= __( 'roles: ', 'users-toolkit' ) . implode( ', ', $role_names ) . ' ';
			}
			
			if ( ! empty( $criteria_positive ) ) {
				$criteria_desc .= __( 'con: ', 'users-toolkit' ) . implode( ', ', $criteria_positive ) . ' ';
			}
			if ( ! empty( $post_types_positive ) ) {
				$post_types_labels = array();
				foreach ( $post_types_positive as $pt ) {
					if ( $pt === 'any' ) {
						$post_types_labels[] = __( 'cualquier contenido', 'users-toolkit' );
					} else {
						$post_type_obj = get_post_type_object( $pt );
						$post_types_labels[] = $post_type_obj ? $post_type_obj->label : $pt;
					}
				}
				$criteria_desc .= __( 'con contenido: ', 'users-toolkit' ) . implode( ', ', $post_types_labels ) . ' ';
			}
			if ( ! empty( $criteria_negative ) ) {
				$criteria_desc .= __( 'sin: ', 'users-toolkit' ) . implode( ', ', $criteria_negative ) . ' ';
			}
			if ( ! empty( $post_types_negative ) ) {
				$post_types_labels = array();
				foreach ( $post_types_negative as $pt ) {
					if ( $pt === 'any' ) {
						$post_types_labels[] = __( 'ningún contenido', 'users-toolkit' );
					} else {
						$post_type_obj = get_post_type_object( $pt );
						$post_types_labels[] = $post_type_obj ? $post_type_obj->label : $pt;
					}
				}
				$criteria_desc .= __( 'sin contenido: ', 'users-toolkit' ) . implode( ', ', $post_types_labels ) . ' ';
			}
			if ( empty( $criteria_desc ) ) {
				$criteria_desc = __( 'sin actividad', 'users-toolkit' );
			}
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 0, sprintf( __( 'Iniciando análisis de %d usuarios (%s)...', 'users-toolkit' ), $total, $criteria_desc ), false );
		}

		$course_count_cache = array();
		$orders_count_cache = array();
		$certificates_cache = array();
		$comments_cache = array();
		$memberships_count_cache = array();
		$posts_count_cache = array();
		$post_type_cache = array();
		$any_content_cache = array();
		$dlm_downloads_count_cache = array();
		$suspicious_email_reason_cache = array();

		$activity_table = $wpdb->prefix . 'learndash_user_activity';
		$activity_table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $activity_table ) ) === $activity_table );
		$orders_table = $wpdb->prefix . 'wc_orders';
		$orders_table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table );
		$orders_table_has_status = false;
		if ( $orders_table_exists ) {
			$orders_table_has_status = ! empty( $wpdb->get_results( "SHOW COLUMNS FROM {$orders_table} LIKE 'status'" ) );
		}

		$dlm_download_table = '';
		$dlm_download_user_id_column = '';
		$dlm_download_email_column = '';
		$needs_dlm_lookup = in_array( 'dlm_downloads', $criteria_positive, true ) || in_array( 'dlm_downloads', $criteria_negative, true ) || $use_default_inactive_mode;
		$dlm_candidate_tables = array(
			$wpdb->prefix . 'dlm_download_log',
			$wpdb->prefix . 'download_log',
		);
		foreach ( $dlm_candidate_tables as $candidate_table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $candidate_table ) ) === $candidate_table ) {
				$dlm_download_table = $candidate_table;
				break;
			}
		}

		if ( $needs_dlm_lookup && ! empty( $dlm_download_table ) ) {
			$table_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$dlm_download_table}`", 0 );
			$table_columns = is_array( $table_columns ) ? $table_columns : array();

			$id_column_candidates = array( 'user_id', 'userid', 'member_id', 'customer_id', 'user' );
			foreach ( $id_column_candidates as $column_name ) {
				if ( in_array( $column_name, $table_columns, true ) ) {
					$dlm_download_user_id_column = $column_name;
					break;
				}
			}

			$email_column_candidates = array( 'user_email', 'email', 'user_mail' );
			foreach ( $email_column_candidates as $column_name ) {
				if ( in_array( $column_name, $table_columns, true ) ) {
					$dlm_download_email_column = $column_name;
					break;
				}
			}

			if ( ! empty( $dlm_download_user_id_column ) && ! empty( $all_user_ids ) ) {
				foreach ( array_chunk( $all_user_ids, 500 ) as $user_ids_chunk ) {
					$id_placeholders = implode( ',', array_fill( 0, count( $user_ids_chunk ), '%d' ) );
					$sql_args = array_merge(
						array( "SELECT `{$dlm_download_user_id_column}` AS user_key, COUNT(*) AS downloads_count FROM `{$dlm_download_table}` WHERE `{$dlm_download_user_id_column}` IN ({$id_placeholders}) GROUP BY `{$dlm_download_user_id_column}`" ),
						$user_ids_chunk
					);
					$downloads_sql = call_user_func_array( array( $wpdb, 'prepare' ), $sql_args );
					$download_rows = $wpdb->get_results( $downloads_sql, ARRAY_A );

					foreach ( (array) $download_rows as $download_row ) {
						$uid = isset( $download_row['user_key'] ) ? (int) $download_row['user_key'] : 0;
						if ( $uid <= 0 ) {
							continue;
						}
						$dlm_downloads_count_cache[ $uid ] = isset( $download_row['downloads_count'] ) ? (int) $download_row['downloads_count'] : 0;
					}
				}
			}

			if ( ! empty( $dlm_download_email_column ) && ! empty( $email_to_user_ids_map ) ) {
				$all_emails = array_keys( $email_to_user_ids_map );
				foreach ( array_chunk( $all_emails, 300 ) as $emails_chunk ) {
					$normalized_emails = array_map( 'strtolower', $emails_chunk );
					$email_placeholders = implode( ',', array_fill( 0, count( $normalized_emails ), '%s' ) );
					$sql_args = array_merge(
						array( "SELECT LOWER(`{$dlm_download_email_column}`) AS email_key, COUNT(*) AS downloads_count FROM `{$dlm_download_table}` WHERE LOWER(`{$dlm_download_email_column}`) IN ({$email_placeholders}) GROUP BY LOWER(`{$dlm_download_email_column}`)" ),
						$normalized_emails
					);
					$email_sql = call_user_func_array( array( $wpdb, 'prepare' ), $sql_args );
					$email_rows = $wpdb->get_results( $email_sql, ARRAY_A );

					foreach ( (array) $email_rows as $email_row ) {
						$email_key = isset( $email_row['email_key'] ) ? strtolower( (string) $email_row['email_key'] ) : '';
						if ( empty( $email_key ) || ! isset( $email_to_user_ids_map[ $email_key ] ) ) {
							continue;
						}

						$email_count = isset( $email_row['downloads_count'] ) ? (int) $email_row['downloads_count'] : 0;
						foreach ( $email_to_user_ids_map[ $email_key ] as $uid ) {
							if ( ! empty( $dlm_download_user_id_column ) && isset( $dlm_downloads_count_cache[ $uid ] ) && $dlm_downloads_count_cache[ $uid ] > 0 ) {
								continue;
							}
							if ( ! isset( $dlm_downloads_count_cache[ $uid ] ) ) {
								$dlm_downloads_count_cache[ $uid ] = 0;
							}
							$dlm_downloads_count_cache[ $uid ] += $email_count;
						}
					}
				}
			}
		}

		foreach ( $all_user_ids as $uid ) {
			if ( ! isset( $dlm_downloads_count_cache[ $uid ] ) ) {
				$dlm_downloads_count_cache[ $uid ] = 0;
			}
		}

		$disposable_domains = (array) apply_filters(
			'users_toolkit_suspicious_email_disposable_domains',
			array(
				'mailinator.com', 'guerrillamail.com', 'sharklasers.com', 'temp-mail.org', 'tempmail.com', '10minutemail.com',
				'yopmail.com', 'trashmail.com', 'maildrop.cc', 'dispostable.com', 'getnada.com', 'moakt.com',
				'throwawaymail.com', 'fakeinbox.com', 'mintemail.com', 'spamgourmet.com', 'mytemp.email', 'emailondeck.com',
				'tempr.email', 'tempinbox.com',
			)
		);
		$suspicious_tlds = (array) apply_filters(
			'users_toolkit_suspicious_email_tlds',
			array( 'xyz', 'top', 'click', 'work', 'loan', 'gq', 'cf', 'ml', 'ga', 'tk', 'pw', 'zip', 'country', 'kim', 'ru', 'su' )
		);

		$disposable_domains_map = array_fill_keys( array_map( 'strtolower', array_filter( $disposable_domains ) ), true );
		$suspicious_tlds_map = array_fill_keys( array_map( 'strtolower', array_filter( $suspicious_tlds ) ), true );

		$get_suspicious_email_reason_func = function( $email ) use ( &$suspicious_email_reason_cache, $disposable_domains_map, $suspicious_tlds_map ) {
			$email = strtolower( trim( (string) $email ) );
			if ( isset( $suspicious_email_reason_cache[ $email ] ) ) {
				return $suspicious_email_reason_cache[ $email ];
			}

			if ( empty( $email ) || ! is_email( $email ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Formato inválido de correo', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			$parts = explode( '@', $email );
			if ( count( $parts ) !== 2 ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Estructura de correo inválida', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			$local_part = trim( $parts[0] );
			$domain = trim( $parts[1] );
			if ( empty( $local_part ) || empty( $domain ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Estructura de correo incompleta', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			if ( isset( $disposable_domains_map[ $domain ] ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Dominio temporal/desechable', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			if ( strpos( $domain, 'xn--' ) === 0 || strpos( $domain, '.xn--' ) !== false ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Dominio punycode sospechoso', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			$domain_parts = explode( '.', $domain );
			$tld = '';
			if ( ! empty( $domain_parts ) ) {
				$tld = strtolower( end( $domain_parts ) );
			}
			if ( ! empty( $tld ) && isset( $suspicious_tlds_map[ $tld ] ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'TLD de alto riesgo', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			if ( preg_match( '/(test|fake|spam|bot|dummy|asdf|qwerty|noreply|temp)/i', $local_part ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Alias típico de prueba/spam', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			$normalized_local = preg_replace( '/[^a-z0-9]/i', '', $local_part );
			$digits_only = preg_replace( '/[^0-9]/', '', $normalized_local );
			$letters_only = preg_replace( '/[^a-z]/i', '', $normalized_local );

			if ( strlen( $normalized_local ) >= 11 && strlen( $digits_only ) >= 4 && strlen( $letters_only ) >= 3 ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Patrón de alias aleatorio', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			if ( preg_match( '/(.)\1{4,}/', $local_part ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Patrón repetitivo inusual', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			if ( preg_match( '/\d{6,}/', $local_part ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Secuencia numérica extensa', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			if ( preg_match( '/\d{4,}/', $domain ) ) {
				$suspicious_email_reason_cache[ $email ] = __( 'Dominio con patrón numérico inusual', 'users-toolkit' );
				return $suspicious_email_reason_cache[ $email ];
			}

			$suspicious_email_reason_cache[ $email ] = '';
			return '';
		};

		// Función auxiliar para contar cursos únicos.
		$count_courses_func = function( $uid ) use ( $wpdb, &$course_count_cache, $activity_table_exists, $activity_table ) {
			if ( isset( $course_count_cache[ $uid ] ) ) {
				return $course_count_cache[ $uid ];
			}

			$course_ids = array();

			// 1. API oficial de LearnDash (inscripciones formales)
			if ( function_exists( 'learndash_user_get_enrolled_courses' ) ) {
				$enrolled_courses = learndash_user_get_enrolled_courses( $uid, array(), true );
				if ( is_array( $enrolled_courses ) ) {
					foreach ( $enrolled_courses as $course_id ) {
						$course_ids[] = (int) $course_id;
					}
				}
			}

			// 2. Meta course_XXX_access_from
			$access_from_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_key FROM {$wpdb->usermeta}
					WHERE user_id = %d
					AND meta_key LIKE 'course_%%_access_from'",
					$uid
				)
			);
			foreach ( (array) $access_from_keys as $key ) {
				if ( preg_match( '/^course_(\d+)_access_from$/', $key, $matches ) ) {
					$course_ids[] = (int) $matches[1];
				}
			}

			// 3. Meta _sfwd-course_progress
			$progress_meta = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->usermeta}
					WHERE user_id = %d
					AND meta_key = '_sfwd-course_progress'",
					$uid
				)
			);
			if ( $progress_meta && $progress_meta !== 'a:0:{}' ) {
				$progress_data = maybe_unserialize( $progress_meta );
				if ( is_array( $progress_data ) && ! empty( $progress_data ) ) {
					foreach ( array_keys( $progress_data ) as $course_id ) {
						if ( is_numeric( $course_id ) ) {
							$course_ids[] = (int) $course_id;
						}
					}
				}
			}

			// 4. Meta course_completed_XXX
			$completed_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_key FROM {$wpdb->usermeta}
					WHERE user_id = %d
					AND meta_key LIKE 'course_completed_%%'",
					$uid
				)
			);
			foreach ( (array) $completed_keys as $key ) {
				if ( preg_match( '/^course_completed_(\d+)$/', $key, $matches ) ) {
					$course_ids[] = (int) $matches[1];
				}
			}

			// 5. Metas de certificados (formatos comunes)
			$cert_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_key FROM {$wpdb->usermeta}
					WHERE user_id = %d
					AND (meta_key LIKE '%%course-cert%%' OR meta_key LIKE '_uo-course-cert-%%')",
					$uid
				)
			);
			foreach ( (array) $cert_keys as $key ) {
				if ( preg_match( '/cert[_-](\d+)/', $key, $matches ) ) {
					$course_ids[] = (int) $matches[1];
				}
			}

			// 6. Tabla learndash_user_activity
			if ( $activity_table_exists ) {
				$activity_courses = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT course_id FROM {$activity_table} WHERE user_id = %d AND course_id > 0",
						$uid
					)
				);
				foreach ( (array) $activity_courses as $course_id ) {
					$course_ids[] = (int) $course_id;
				}
			}

			// 7. Certificados en posts sfwd-certificates
			$certificate_posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'sfwd-certificates'
					AND post_author = %d
					AND post_status != 'trash'",
					$uid
				)
			);
			if ( ! empty( $certificate_posts ) ) {
				foreach ( $certificate_posts as $cert_id ) {
					$course_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT meta_value FROM {$wpdb->postmeta}
							WHERE post_id = %d
							AND meta_key = 'course_id'
							LIMIT 1",
							$cert_id
						)
					);
					if ( $course_id && is_numeric( $course_id ) ) {
						$course_ids[] = (int) $course_id;
					}
				}
			}

			$course_count_cache[ $uid ] = count( array_unique( array_filter( $course_ids ) ) );
			return $course_count_cache[ $uid ];
		};

		// Función auxiliar para contar pedidos únicos (HPOS + legacy).
		$count_orders_func = function( $uid ) use ( $wpdb, &$orders_count_cache, $orders_table_exists, $orders_table, $orders_table_has_status ) {
			if ( isset( $orders_count_cache[ $uid ] ) ) {
				return $orders_count_cache[ $uid ];
			}

			$order_ids = array();

			if ( $orders_table_exists ) {
				if ( $orders_table_has_status ) {
					$hpos_orders = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT id FROM {$orders_table} WHERE customer_id = %d AND status != 'trash'",
							$uid
						)
					);
				} else {
					$hpos_orders = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT id FROM {$orders_table} WHERE customer_id = %d",
							$uid
						)
					);
				}
				foreach ( (array) $hpos_orders as $order_id ) {
					$order_ids[] = (int) $order_id;
				}
			}

			$legacy_orders = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE pm.meta_key = '_customer_user'
					AND CAST(pm.meta_value AS UNSIGNED) = %d
					AND p.post_type = 'shop_order'
					AND p.post_status != 'trash'",
					$uid
				)
			);
			foreach ( (array) $legacy_orders as $order_id ) {
				$order_ids[] = (int) $order_id;
			}

			$orders_count_cache[ $uid ] = count( array_unique( $order_ids ) );
			return $orders_count_cache[ $uid ];
		};

		$count_memberships_func = function( $uid ) use ( $wpdb, &$memberships_count_cache ) {
			if ( isset( $memberships_count_cache[ $uid ] ) ) {
				return $memberships_count_cache[ $uid ];
			}

			if ( function_exists( 'wc_memberships_get_user_memberships' ) ) {
				$memberships = wc_memberships_get_user_memberships(
					$uid,
					array(
						// Contar membresías históricas como actividad para no marcar usuarios legítimos como spam.
						'status' => array( 'active', 'complimentary', 'free_trial', 'pending', 'paused', 'delayed', 'expired', 'cancelled' ),
					)
				);
				$memberships_count_cache[ $uid ] = is_array( $memberships ) ? count( $memberships ) : 0;
				return $memberships_count_cache[ $uid ];
			}

			$memberships_count_cache[ $uid ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_type = 'wc_user_membership'
					AND post_author = %d
					AND post_status IN ('wcm-active', 'wcm-complimentary', 'wcm-free_trial', 'wcm-pending', 'wcm-paused', 'wcm-delayed', 'wcm-expired', 'wcm-cancelled')",
					$uid
				)
			);
			return $memberships_count_cache[ $uid ];
		};

		$count_posts_func = function( $uid ) use ( $wpdb, &$posts_count_cache ) {
			if ( isset( $posts_count_cache[ $uid ] ) ) {
				return $posts_count_cache[ $uid ];
			}

			$posts_count_cache[ $uid ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_author = %d
					AND post_status IN ('publish', 'draft', 'pending', 'private')
					AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment', 'shop_order')",
					$uid
				)
			);
			return $posts_count_cache[ $uid ];
		};

		$has_post_type_func = function( $uid, $post_type ) use ( $wpdb, &$post_type_cache ) {
			$key = $uid . ':' . $post_type;
			if ( isset( $post_type_cache[ $key ] ) ) {
				return $post_type_cache[ $key ];
			}

			$post_type_cache[ $key ] = ( (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_author = %d
					AND post_type = %s
					AND post_status IN ('publish', 'draft', 'pending', 'private')",
					$uid,
					$post_type
				)
			) ) > 0;
			return $post_type_cache[ $key ];
		};

		$has_any_content_func = function( $uid ) use ( $wpdb, &$any_content_cache ) {
			if ( isset( $any_content_cache[ $uid ] ) ) {
				return $any_content_cache[ $uid ];
			}

			$any_content_cache[ $uid ] = ( (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_author = %d
					AND post_status IN ('publish', 'draft', 'pending', 'private')
					AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')",
					$uid
				)
			) ) > 0;
			return $any_content_cache[ $uid ];
		};

		$has_courses_func = function( $uid ) use ( $count_courses_func ) {
			return $count_courses_func( $uid ) > 0;
		};
		$has_orders_func = function( $uid ) use ( $count_orders_func ) {
			return $count_orders_func( $uid ) > 0;
		};
		$has_memberships_func = function( $uid ) use ( $count_memberships_func ) {
			return $count_memberships_func( $uid ) > 0;
		};
		$count_dlm_downloads_func = function( $uid ) use ( &$dlm_downloads_count_cache ) {
			return isset( $dlm_downloads_count_cache[ $uid ] ) ? (int) $dlm_downloads_count_cache[ $uid ] : 0;
		};
		$has_dlm_downloads_func = function( $uid ) use ( $count_dlm_downloads_func ) {
			return $count_dlm_downloads_func( $uid ) > 0;
		};
		$has_certificates_func = function( $uid ) use ( $wpdb, &$certificates_cache ) {
			if ( isset( $certificates_cache[ $uid ] ) ) {
				return $certificates_cache[ $uid ];
			}
			$certificates_cache[ $uid ] = ( (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_type = 'sfwd-certificates'
					AND post_author = %d",
					$uid
				)
			) ) > 0;
			return $certificates_cache[ $uid ];
		};
		$has_comments_func = function( $uid ) use ( $wpdb, &$comments_cache ) {
			if ( isset( $comments_cache[ $uid ] ) ) {
				return $comments_cache[ $uid ];
			}
			$comments_cache[ $uid ] = ( (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->comments}
					WHERE user_id = %d
					AND comment_approved != 'spam'",
					$uid
				)
			) ) > 0;
			return $comments_cache[ $uid ];
		};

		foreach ( $all_users as $user_row ) {
			$user_id = isset( $user_row['ID'] ) ? (int) $user_row['ID'] : 0;
			if ( $user_id <= 0 ) {
				continue;
			}
			$processed++;
			
			// Actualizar progreso cada 50 usuarios o cada 5% del total.
			if ( ! empty( $operation_id ) && ( $processed % 50 === 0 || $processed % max( 1, floor( $total / 20 ) ) === 0 ) ) {
				$percent = min( 95, floor( ( $processed / $total ) * 100 ) );
				Users_Toolkit_Progress_Tracker::set_progress( $operation_id, $percent, sprintf( __( 'Analizando usuarios: %d de %d (%d%%). Encontrados: %d', 'users-toolkit' ), $processed, $total, $percent, count( $spam_users ) ), false, array( 'found' => count( $spam_users ) ) );
			}
			
			$user_roles_array = isset( $user_roles_map[ $user_id ] ) && is_array( $user_roles_map[ $user_id ] ) ? $user_roles_map[ $user_id ] : array();
			$user_caps = isset( $user_caps_map[ $user_id ] ) && is_array( $user_caps_map[ $user_id ] ) ? $user_caps_map[ $user_id ] : array();

			// Filtrar por roles si se especificó
			if ( ! empty( $user_roles ) ) {
				$has_matching_role = false;
				foreach ( $user_roles as $role ) {
					if ( in_array( $role, $user_roles_array, true ) ) {
						$has_matching_role = true;
						break;
					}
				}
				if ( ! $has_matching_role ) {
					continue; // Saltar este usuario si no tiene ninguno de los roles seleccionados
				}
			} else {
				// Si NO se especificó ningún rol, excluir administradores por defecto
				// (comportamiento original para evitar mostrar admins accidentalmente)
				$is_admin = in_array( 'administrator', $user_roles_array, true ) || ! empty( $user_caps['manage_options'] );
				if ( $is_admin ) {
					continue;
				}
			}

			// Verificar criterios positivos (debe tener)
			$positive_matches = array();
			$user_email = isset( $user_row['user_email'] ) ? strtolower( trim( (string) $user_row['user_email'] ) ) : '';
			$email_suspicious_reason = $get_suspicious_email_reason_func( $user_email );
			$has_suspicious_email = ! empty( $email_suspicious_reason );
			if ( in_array( 'courses', $criteria_positive, true ) ) {
				$positive_matches['courses'] = $has_courses_func( $user_id );
			}
			if ( in_array( 'orders', $criteria_positive, true ) ) {
				$positive_matches['orders'] = $has_orders_func( $user_id );
			}
			if ( in_array( 'certificates', $criteria_positive, true ) ) {
				$positive_matches['certificates'] = $has_certificates_func( $user_id );
			}
			if ( in_array( 'comments', $criteria_positive, true ) ) {
				$positive_matches['comments'] = $has_comments_func( $user_id );
			}
			if ( in_array( 'memberships', $criteria_positive, true ) ) {
				$positive_matches['memberships'] = $has_memberships_func( $user_id );
			}
			if ( in_array( 'dlm_downloads', $criteria_positive, true ) ) {
				$positive_matches['dlm_downloads'] = $has_dlm_downloads_func( $user_id );
			}
			if ( in_array( 'suspicious_email', $criteria_positive, true ) ) {
				$positive_matches['suspicious_email'] = $has_suspicious_email;
			}
			
			// Verificar tipos de post positivos (debe ser autor de)
			if ( ! empty( $post_types_positive ) ) {
				// Si se seleccionó "any", verificar si tiene cualquier tipo de contenido
				if ( in_array( 'any', $post_types_positive, true ) ) {
					$positive_matches['post_type_any'] = $has_any_content_func( $user_id );
				} else {
					// Verificar tipos específicos
					foreach ( $post_types_positive as $post_type ) {
						if ( $post_type !== 'any' ) {
							$positive_matches[ 'post_type_' . $post_type ] = $has_post_type_func( $user_id, $post_type );
						}
					}
				}
			}

			// Verificar criterios negativos (no debe tener)
			$negative_matches = array();
			if ( in_array( 'courses', $criteria_negative, true ) ) {
				$course_count = $count_courses_func( $user_id );
				$negative_matches['courses'] = ( $course_count === 0 ); // true = NO tiene cursos (cumple)
			}
			if ( in_array( 'orders', $criteria_negative, true ) ) {
				$orders_count = $count_orders_func( $user_id );
				$negative_matches['orders'] = ( $orders_count === 0 ); // true = NO tiene pedidos (cumple)
			}
			if ( in_array( 'certificates', $criteria_negative, true ) ) {
				$negative_matches['certificates'] = ! $has_certificates_func( $user_id );
			}
			if ( in_array( 'comments', $criteria_negative, true ) ) {
				$negative_matches['comments'] = ! $has_comments_func( $user_id );
			}
			if ( in_array( 'memberships', $criteria_negative, true ) ) {
				$memberships_count = $count_memberships_func( $user_id );
				$negative_matches['memberships'] = ( $memberships_count === 0 ); // true = NO tiene membresías (cumple)
			}
			if ( in_array( 'dlm_downloads', $criteria_negative, true ) ) {
				$dlm_downloads_count = $count_dlm_downloads_func( $user_id );
				$negative_matches['dlm_downloads'] = ( $dlm_downloads_count === 0 ); // true = NO tiene descargas (cumple)
			}
			if ( in_array( 'suspicious_email', $criteria_negative, true ) ) {
				$negative_matches['suspicious_email'] = ! $has_suspicious_email;
			}
			
			// Verificar tipos de post negativos (NO debe ser autor de)
			if ( ! empty( $post_types_negative ) ) {
				// Si se seleccionó "any", verificar que NO tenga ningún tipo de contenido
				if ( in_array( 'any', $post_types_negative, true ) ) {
					$negative_matches['post_type_any'] = ! $has_any_content_func( $user_id ); // true = NO tiene contenido (cumple)
				} else {
					// Verificar tipos específicos
					foreach ( $post_types_negative as $post_type ) {
						if ( $post_type !== 'any' ) {
							$negative_matches[ 'post_type_' . $post_type ] = ! $has_post_type_func( $user_id, $post_type );
						}
					}
				}
			}

			// Determinar si el usuario coincide con los criterios
			$matches = false;

			// Si solo se seleccionó un rol sin otros criterios, mostrar TODOS los usuarios de ese rol
			if ( $has_role_filter && ! $has_positive_criteria && ! $has_negative_criteria ) {
				// Solo filtro por rol: incluir todos los usuarios de ese rol
				$matches = true;
			} elseif ( $use_default_inactive_mode ) {
				// Si no hay criterios específicos seleccionados, buscar usuarios sin actividad (comportamiento por defecto)
				// Por defecto: buscar usuarios sin ninguna actividad
				$has_courses = $has_courses_func( $user_id );
				$has_orders = $has_orders_func( $user_id );
				$has_certificates = $has_certificates_func( $user_id );
				$has_comments = $has_comments_func( $user_id );
				$has_memberships = $has_memberships_func( $user_id );
				$has_dlm_downloads = $has_dlm_downloads_func( $user_id );
				$has_any_activity = $has_courses || $has_orders || $has_certificates || $has_comments || $has_memberships || $has_dlm_downloads;
				// Usuarios sin actividad: no deben tener cursos, pedidos, certificados, comentarios, membresías ni descargas.
				$matches = ! $has_any_activity;
			} else {
				// Hay criterios específicos seleccionados
				
				// Verificar criterios positivos (debe tener)
				$positive_match = true; // Por defecto true (no requiere nada si no hay criterios positivos)
				if ( $has_positive_criteria ) {
					if ( empty( $positive_matches ) ) {
						// Si hay criterios positivos pero no se verificaron, no cumple
						$positive_match = false;
					} else {
						if ( $match_all ) {
							// Debe cumplir TODOS los criterios positivos
							$positive_match = count( array_filter( $positive_matches ) ) === count( $positive_matches );
						} else {
							// Debe cumplir AL MENOS UNO de los criterios positivos
							$positive_match = ! empty( array_filter( $positive_matches ) );
						}
					}
				}

				// Verificar criterios negativos (no debe tener)
				$negative_match = true; // Por defecto true (no requiere nada si no hay criterios negativos)
				if ( $has_negative_criteria ) {
					if ( empty( $negative_matches ) ) {
						// Si hay criterios negativos pero no se verificaron, no cumple
						$negative_match = false;
					} else {
						// Para criterios negativos: todos deben cumplirse (el usuario NO debe tener ninguno de los seleccionados)
						// $negative_matches contiene true/false: true = NO tiene (cumple), false = tiene (no cumple)
						// El usuario debe cumplir TODOS los criterios negativos (todos deben ser true)
						$all_negative_match = true;
						foreach ( $negative_matches as $match_value ) {
							if ( ! $match_value ) {
								$all_negative_match = false;
								break;
							}
						}
						$negative_match = $all_negative_match;
					}
				}

				// El usuario debe cumplir tanto los criterios positivos como los negativos
				$matches = $positive_match && $negative_match;
			}

			// Asegurar que $matches sea un booleano
			$matches = (bool) $matches;

			// Si coincide con los criterios, agregar a la lista
			if ( $matches ) {
				// Verificación adicional: asegurar que realmente cumple los criterios negativos
				// Esto es una doble verificación para evitar errores
				$passes_negative_check = true;
				if ( ! empty( $criteria_negative ) ) {
					if ( in_array( 'courses', $criteria_negative, true ) ) {
						$actual_course_count = $count_courses_func( $user_id );
						if ( $actual_course_count > 0 ) {
							$passes_negative_check = false;
						}
					}
					if ( in_array( 'orders', $criteria_negative, true ) ) {
						$actual_orders_count = $count_orders_func( $user_id );
						if ( $actual_orders_count > 0 ) {
							$passes_negative_check = false;
						}
					}
					if ( in_array( 'certificates', $criteria_negative, true ) ) {
						if ( $has_certificates_func( $user_id ) ) {
							$passes_negative_check = false;
						}
					}
					if ( in_array( 'comments', $criteria_negative, true ) ) {
						if ( $has_comments_func( $user_id ) ) {
							$passes_negative_check = false;
						}
					}
					if ( in_array( 'memberships', $criteria_negative, true ) ) {
						$actual_memberships_count = $count_memberships_func( $user_id );
						if ( $actual_memberships_count > 0 ) {
							$passes_negative_check = false;
						}
					}
					if ( in_array( 'dlm_downloads', $criteria_negative, true ) ) {
						$actual_dlm_downloads_count = $count_dlm_downloads_func( $user_id );
						if ( $actual_dlm_downloads_count > 0 ) {
							$passes_negative_check = false;
						}
					}
					if ( in_array( 'suspicious_email', $criteria_negative, true ) ) {
						if ( $has_suspicious_email ) {
							$passes_negative_check = false;
						}
					}
				}
				
				// Solo agregar si pasa la verificación adicional
				if ( ! $passes_negative_check ) {
					continue; // Saltar este usuario
				}
				
				$user_registered = isset( $user_row['user_registered'] ) ? (string) $user_row['user_registered'] : '';
				$registered_ts = ! empty( $user_registered ) ? strtotime( $user_registered ) : false;
				$days_old = $registered_ts ? floor( ( time() - $registered_ts ) / ( 60 * 60 * 24 ) ) : 0;

				// Contar cursos únicos (evita duplicados entre diferentes fuentes)
				$course_count = $count_courses_func( $user_id );

				// Contar pedidos únicos (evita duplicados entre HPOS y legacy)
				$orders_count = $count_orders_func( $user_id );

				// Contar posts/publicaciones
				$posts_count = $count_posts_func( $user_id );

				// Contar membresías WooCommerce
				$memberships_count = $count_memberships_func( $user_id );
				$dlm_downloads_count = $count_dlm_downloads_func( $user_id );
				$user_profile = isset( $user_profile_map[ $user_id ] ) && is_array( $user_profile_map[ $user_id ] ) ? $user_profile_map[ $user_id ] : array();

				$spam_users[] = array(
					'ID'         => $user_id,
					'email'      => isset( $user_row['user_email'] ) ? $user_row['user_email'] : '',
					'login'      => isset( $user_row['user_login'] ) ? $user_row['user_login'] : '',
					'first_name' => isset( $user_profile['first_name'] ) ? $user_profile['first_name'] : '',
					'last_name'  => isset( $user_profile['last_name'] ) ? $user_profile['last_name'] : '',
					'city'       => isset( $user_profile['city'] ) ? $user_profile['city'] : '',
					'country'    => isset( $user_profile['country'] ) ? $user_profile['country'] : '',
					'registered' => $user_registered,
					'days_old'   => $days_old,
					'roles'      => implode( ', ', $user_roles_array ),
					'courses'    => (int) $course_count,
					'orders'     => (int) $orders_count,
					'posts'      => (int) $posts_count,
					'memberships' => (int) $memberships_count,
					'dlm_downloads' => (int) $dlm_downloads_count,
					'email_suspicious' => $has_suspicious_email ? 1 : 0,
					'email_suspicious_reason' => $email_suspicious_reason,
				);
			}

			// Liberar memoria periódicamente en ejecuciones grandes.
			if ( $processed % 200 === 0 ) {
				if ( function_exists( 'wp_cache_flush_runtime' ) ) {
					wp_cache_flush_runtime();
				}
				if ( function_exists( 'gc_collect_cycles' ) ) {
					@gc_collect_cycles();
				}
			}
		}

		if ( ! empty( $operation_id ) ) {
			Users_Toolkit_Progress_Tracker::set_progress( $operation_id, 98, sprintf( __( 'Finalizando análisis: %d usuarios encontrados...', 'users-toolkit' ), count( $spam_users ) ), false, array( 'found' => count( $spam_users ) ) );
		}

		return $spam_users;
	}

	/**
	 * Save spam users list to file
	 *
	 * @param array $spam_users Array of spam users
	 * @return string|false File path or false on failure
	 */
	public static function save_spam_users_list( $spam_users ) {
		$upload_dir = wp_upload_dir();
		$reports_dir = $upload_dir['basedir'] . '/users-toolkit';

		if ( ! file_exists( $reports_dir ) ) {
			wp_mkdir_p( $reports_dir );
		}

		$date_suffix = date( 'Y-m-d-His' );
		$file_path = $reports_dir . '/spam-users-' . $date_suffix . '.txt';
		$fp = fopen( $file_path, 'w' );

		if ( ! $fp ) {
			return false;
		}

		fwrite( $fp, "LISTA DE USUARIOS SPAM - " . date( 'Y-m-d H:i:s' ) . "\n" );
		fwrite( $fp, str_repeat( '=', 80 ) . "\n\n" );

		foreach ( $spam_users as $spam_user ) {
			$first_name = isset( $spam_user['first_name'] ) ? $spam_user['first_name'] : '';
			$last_name = isset( $spam_user['last_name'] ) ? $spam_user['last_name'] : '';
			$city = isset( $spam_user['city'] ) ? $spam_user['city'] : '';
			$country = isset( $spam_user['country'] ) ? $spam_user['country'] : '';
			$courses = isset( $spam_user['courses'] ) ? $spam_user['courses'] : 0;
			$orders = isset( $spam_user['orders'] ) ? $spam_user['orders'] : 0;
			$posts = isset( $spam_user['posts'] ) ? $spam_user['posts'] : 0;
			$memberships = isset( $spam_user['memberships'] ) ? $spam_user['memberships'] : 0;
			$dlm_downloads = isset( $spam_user['dlm_downloads'] ) ? $spam_user['dlm_downloads'] : 0;
			$email_suspicious = ! empty( $spam_user['email_suspicious'] ) ? __( 'Sí', 'users-toolkit' ) : __( 'No', 'users-toolkit' );
			$email_suspicious_reason = isset( $spam_user['email_suspicious_reason'] ) ? $spam_user['email_suspicious_reason'] : '';
			fwrite( $fp, sprintf(
				"ID: %d | Nombre: %s %s | Email: %s | Correo sospechoso: %s | Motivo: %s | Login: %s | Roles: %s | Ciudad: %s | País: %s | Registrado: %s | Días: %d | Cursos: %d | Pedidos: %d | Posts: %d | Membresías: %d | Descargas DLM: %d\n",
				$spam_user['ID'],
				$first_name,
				$last_name,
				$spam_user['email'],
				$email_suspicious,
				$email_suspicious_reason,
				$spam_user['login'],
				$spam_user['roles'],
				$city,
				$country,
				$spam_user['registered'],
				$spam_user['days_old'],
				$courses,
				$orders,
				$posts,
				$memberships,
				$dlm_downloads
			) );
		}

		fclose( $fp );

		// Guardar JSON con el mismo nombre base (para cargar la tabla en la página)
		$json_path = $reports_dir . '/spam-users-' . $date_suffix . '.json';
		file_put_contents( $json_path, json_encode( $spam_users, JSON_PRETTY_PRINT ) );

		return $file_path;
	}
}
