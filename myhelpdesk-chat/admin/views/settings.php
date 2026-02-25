<?php
/**
 * Admin Settings view.
 *
 * Tabbed settings page with 10 tabs: General, Chat Behavior,
 * Notifications, Design, Agents & Departments, Tickets, Knowledge Base,
 * Email Piping, WooCommerce, and Advanced. Each tab renders the
 * corresponding settings fields using the WordPress options API.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Determine active tab.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tabs = array(
	'general'       => __( 'General', 'myhelpdesk-chat' ),
	'chat'          => __( 'Chat Behavior', 'myhelpdesk-chat' ),
	'notifications' => __( 'Notifications', 'myhelpdesk-chat' ),
	'design'        => __( 'Design', 'myhelpdesk-chat' ),
	'agents'        => __( 'Agents & Departments', 'myhelpdesk-chat' ),
	'tickets'       => __( 'Tickets', 'myhelpdesk-chat' ),
	'kb'            => __( 'Knowledge Base', 'myhelpdesk-chat' ),
	'email'         => __( 'Email Piping', 'myhelpdesk-chat' ),
	'woocommerce'   => __( 'WooCommerce', 'myhelpdesk-chat' ),
	'advanced'      => __( 'Advanced', 'myhelpdesk-chat' ),
);
?>
<div class="wrap mhd-settings-page">

	<h1><?php esc_html_e( 'MyHelpDesk Settings', 'myhelpdesk-chat' ); ?></h1>

	<!-- ============================================================
		 Tabs Navigation
	============================================================= -->
	<h2 class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'myhelpdesk-settings', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>"
				class="nav-tab <?php echo ( $active_tab === $tab_key ) ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<!-- ============================================================
		 Tab Content
	============================================================= -->
	<form method="post" action="options.php">

		<?php
		/* --------------------------------------------------------
		 * General Tab
		 * ------------------------------------------------------ */
		if ( 'general' === $active_tab ) :
			settings_fields( 'mhd_general_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_chat_enabled"><?php esc_html_e( 'Enable Chat', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_chat_enabled" name="mhd_chat_enabled" value="1"
							<?php checked( get_option( 'mhd_chat_enabled', '1' ), '1' ); ?> />
						<p class="description"><?php esc_html_e( 'Enable the live chat widget on the front-end.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_company_name"><?php esc_html_e( 'Company Name', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd_company_name" name="mhd_company_name" class="regular-text"
							value="<?php echo esc_attr( get_option( 'mhd_company_name', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_company_logo"><?php esc_html_e( 'Company Logo URL', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="url" id="mhd_company_logo" name="mhd_company_logo" class="regular-text"
							value="<?php echo esc_attr( get_option( 'mhd_company_logo', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_default_language"><?php esc_html_e( 'Default Language', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd_default_language" name="mhd_default_language" class="small-text"
							value="<?php echo esc_attr( get_option( 'mhd_default_language', 'en' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_timezone"><?php esc_html_e( 'Timezone', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd_timezone" name="mhd_timezone">
							<?php
							$current_tz = get_option( 'mhd_timezone', 'UTC' );
							$timezones  = timezone_identifiers_list();
							foreach ( $timezones as $tz ) :
								?>
								<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $current_tz, $tz ); ?>>
									<?php echo esc_html( $tz ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Chat Behavior Tab
		 * ------------------------------------------------------ */
		elseif ( 'chat' === $active_tab ) :
			settings_fields( 'mhd_chat_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_auto_assign"><?php esc_html_e( 'Auto-Assign Conversations', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_auto_assign" name="mhd_auto_assign" value="1"
							<?php checked( get_option( 'mhd_auto_assign', '1' ), '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_assignment_method"><?php esc_html_e( 'Assignment Method', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd_assignment_method" name="mhd_assignment_method">
							<option value="round_robin" <?php selected( get_option( 'mhd_assignment_method', 'round_robin' ), 'round_robin' ); ?>>
								<?php esc_html_e( 'Round Robin', 'myhelpdesk-chat' ); ?>
							</option>
							<option value="least_busy" <?php selected( get_option( 'mhd_assignment_method' ), 'least_busy' ); ?>>
								<?php esc_html_e( 'Least Busy', 'myhelpdesk-chat' ); ?>
							</option>
							<option value="manual" <?php selected( get_option( 'mhd_assignment_method' ), 'manual' ); ?>>
								<?php esc_html_e( 'Manual', 'myhelpdesk-chat' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_welcome_message"><?php esc_html_e( 'Welcome Message', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<textarea id="mhd_welcome_message" name="mhd_welcome_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'mhd_welcome_message', '' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_offline_message"><?php esc_html_e( 'Offline Message', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<textarea id="mhd_offline_message" name="mhd_offline_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'mhd_offline_message', '' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_pre_chat_form"><?php esc_html_e( 'Pre-Chat Form', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_pre_chat_form" name="mhd_pre_chat_form" value="1"
							<?php checked( get_option( 'mhd_pre_chat_form', '' ), '1' ); ?> />
						<p class="description"><?php esc_html_e( 'Require visitors to fill in name and email before starting a chat.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_file_uploads"><?php esc_html_e( 'File Uploads', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_file_uploads" name="mhd_file_uploads" value="1"
							<?php checked( get_option( 'mhd_file_uploads', '1' ), '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_max_file_size"><?php esc_html_e( 'Max File Size (MB)', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="number" id="mhd_max_file_size" name="mhd_max_file_size" class="small-text" min="1"
							value="<?php echo esc_attr( get_option( 'mhd_max_file_size', 5 ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_chat_rating"><?php esc_html_e( 'Chat Rating', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_chat_rating" name="mhd_chat_rating" value="1"
							<?php checked( get_option( 'mhd_chat_rating', '1' ), '1' ); ?> />
						<p class="description"><?php esc_html_e( 'Allow visitors to rate the chat after it ends.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_inactivity_timeout"><?php esc_html_e( 'Inactivity Timeout (min)', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="number" id="mhd_inactivity_timeout" name="mhd_inactivity_timeout" class="small-text" min="0"
							value="<?php echo esc_attr( get_option( 'mhd_inactivity_timeout', 10 ) ); ?>" />
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Notifications Tab
		 * ------------------------------------------------------ */
		elseif ( 'notifications' === $active_tab ) :
			settings_fields( 'mhd_notification_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_sound_notification"><?php esc_html_e( 'Sound Notifications', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_sound_notification" name="mhd_sound_notification" value="1"
							<?php checked( get_option( 'mhd_sound_notification', '1' ), '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_email_notifications"><?php esc_html_e( 'Email Notifications', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_email_notifications" name="mhd_email_notifications" value="1"
							<?php checked( get_option( 'mhd_email_notifications', '1' ), '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_notification_email"><?php esc_html_e( 'Notification Email', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="email" id="mhd_notification_email" name="mhd_notification_email" class="regular-text"
							value="<?php echo esc_attr( get_option( 'mhd_notification_email', get_option( 'admin_email' ) ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_browser_push"><?php esc_html_e( 'Browser Push Notifications', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_browser_push" name="mhd_browser_push" value="1"
							<?php checked( get_option( 'mhd_browser_push', '' ), '1' ); ?> />
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Design Tab
		 * ------------------------------------------------------ */
		elseif ( 'design' === $active_tab ) :
			settings_fields( 'mhd_design_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_primary_color"><?php esc_html_e( 'Primary Color', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="color" id="mhd_primary_color" name="mhd_primary_color"
							value="<?php echo esc_attr( get_option( 'mhd_primary_color', '#0073aa' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_widget_position"><?php esc_html_e( 'Widget Position', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd_widget_position" name="mhd_widget_position">
							<option value="bottom-right" <?php selected( get_option( 'mhd_widget_position', 'bottom-right' ), 'bottom-right' ); ?>>
								<?php esc_html_e( 'Bottom Right', 'myhelpdesk-chat' ); ?>
							</option>
							<option value="bottom-left" <?php selected( get_option( 'mhd_widget_position' ), 'bottom-left' ); ?>>
								<?php esc_html_e( 'Bottom Left', 'myhelpdesk-chat' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_widget_icon"><?php esc_html_e( 'Widget Icon', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd_widget_icon" name="mhd_widget_icon">
							<option value="chat" <?php selected( get_option( 'mhd_widget_icon', 'chat' ), 'chat' ); ?>>
								<?php esc_html_e( 'Chat Bubble', 'myhelpdesk-chat' ); ?>
							</option>
							<option value="headset" <?php selected( get_option( 'mhd_widget_icon' ), 'headset' ); ?>>
								<?php esc_html_e( 'Headset', 'myhelpdesk-chat' ); ?>
							</option>
							<option value="help" <?php selected( get_option( 'mhd_widget_icon' ), 'help' ); ?>>
								<?php esc_html_e( 'Help', 'myhelpdesk-chat' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_custom_css"><?php esc_html_e( 'Custom CSS', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<textarea id="mhd_custom_css" name="mhd_custom_css" rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'mhd_custom_css', '' ) ); ?></textarea>
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Agents & Departments Tab
		 * ------------------------------------------------------ */
		elseif ( 'agents' === $active_tab ) :
			settings_fields( 'mhd_agents_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_default_department"><?php esc_html_e( 'Default Department ID', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="number" id="mhd_default_department" name="mhd_default_department" class="small-text" min="0"
							value="<?php echo esc_attr( get_option( 'mhd_default_department', 0 ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_max_concurrent_chats"><?php esc_html_e( 'Default Max Concurrent Chats', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="number" id="mhd_max_concurrent_chats" name="mhd_max_concurrent_chats" class="small-text" min="1"
							value="<?php echo esc_attr( get_option( 'mhd_max_concurrent_chats', 5 ) ); ?>" />
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Tickets Tab
		 * ------------------------------------------------------ */
		elseif ( 'tickets' === $active_tab ) :
			settings_fields( 'mhd_ticket_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_ticket_prefix"><?php esc_html_e( 'Ticket ID Prefix', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd_ticket_prefix" name="mhd_ticket_prefix" class="small-text"
							value="<?php echo esc_attr( get_option( 'mhd_ticket_prefix', 'TKT-' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_auto_close_days"><?php esc_html_e( 'Auto-Close After (days)', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="number" id="mhd_auto_close_days" name="mhd_auto_close_days" class="small-text" min="0"
							value="<?php echo esc_attr( get_option( 'mhd_auto_close_days', 7 ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Set to 0 to disable auto-close.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_allow_guest_tickets"><?php esc_html_e( 'Allow Guest Tickets', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_allow_guest_tickets" name="mhd_allow_guest_tickets" value="1"
							<?php checked( get_option( 'mhd_allow_guest_tickets', '' ), '1' ); ?> />
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Knowledge Base Tab
		 * ------------------------------------------------------ */
		elseif ( 'kb' === $active_tab ) :
			settings_fields( 'mhd_kb_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_kb_enabled"><?php esc_html_e( 'Enable Knowledge Base', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_kb_enabled" name="mhd_kb_enabled" value="1"
							<?php checked( get_option( 'mhd_kb_enabled', '1' ), '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_kb_slug"><?php esc_html_e( 'KB URL Slug', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd_kb_slug" name="mhd_kb_slug" class="regular-text"
							value="<?php echo esc_attr( get_option( 'mhd_kb_slug', 'knowledge-base' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_kb_per_page"><?php esc_html_e( 'Articles Per Page', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="number" id="mhd_kb_per_page" name="mhd_kb_per_page" class="small-text" min="1"
							value="<?php echo esc_attr( get_option( 'mhd_kb_per_page', 10 ) ); ?>" />
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Email Piping Tab
		 * ------------------------------------------------------ */
		elseif ( 'email' === $active_tab ) :
			settings_fields( 'mhd_email_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_email_piping_enabled"><?php esc_html_e( 'Enable Email Piping', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_email_piping_enabled" name="mhd_email_piping_enabled" value="1"
							<?php checked( get_option( 'mhd_email_piping_enabled', '' ), '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_imap_host"><?php esc_html_e( 'IMAP Host', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd_imap_host" name="mhd_imap_host" class="regular-text"
							value="<?php echo esc_attr( get_option( 'mhd_imap_host', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_imap_port"><?php esc_html_e( 'IMAP Port', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="number" id="mhd_imap_port" name="mhd_imap_port" class="small-text"
							value="<?php echo esc_attr( get_option( 'mhd_imap_port', 993 ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_imap_username"><?php esc_html_e( 'IMAP Username', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd_imap_username" name="mhd_imap_username" class="regular-text"
							value="<?php echo esc_attr( get_option( 'mhd_imap_username', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_imap_password"><?php esc_html_e( 'IMAP Password', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="password" id="mhd_imap_password" name="mhd_imap_password" class="regular-text"
							value="<?php echo esc_attr( get_option( 'mhd_imap_password', '' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_imap_encryption"><?php esc_html_e( 'Encryption', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd_imap_encryption" name="mhd_imap_encryption">
							<option value="ssl" <?php selected( get_option( 'mhd_imap_encryption', 'ssl' ), 'ssl' ); ?>>
								<?php esc_html_e( 'SSL', 'myhelpdesk-chat' ); ?>
							</option>
							<option value="tls" <?php selected( get_option( 'mhd_imap_encryption' ), 'tls' ); ?>>
								<?php esc_html_e( 'TLS', 'myhelpdesk-chat' ); ?>
							</option>
							<option value="none" <?php selected( get_option( 'mhd_imap_encryption' ), 'none' ); ?>>
								<?php esc_html_e( 'None', 'myhelpdesk-chat' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * WooCommerce Tab
		 * ------------------------------------------------------ */
		elseif ( 'woocommerce' === $active_tab ) :
			settings_fields( 'mhd_woo_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_woo_integration"><?php esc_html_e( 'Enable WooCommerce Integration', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_woo_integration" name="mhd_woo_integration" value="1"
							<?php checked( get_option( 'mhd_woo_integration', '' ), '1' ); ?> />
						<p class="description"><?php esc_html_e( 'Show customer order information in conversation sidebar.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_woo_order_info"><?php esc_html_e( 'Show Order Info', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_woo_order_info" name="mhd_woo_order_info" value="1"
							<?php checked( get_option( 'mhd_woo_order_info', '' ), '1' ); ?> />
						<p class="description"><?php esc_html_e( 'Display recent orders, total spent, and order status for the customer.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
			</table>

		<?php
		/* --------------------------------------------------------
		 * Advanced Tab
		 * ------------------------------------------------------ */
		elseif ( 'advanced' === $active_tab ) :
			settings_fields( 'mhd_advanced_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mhd_delete_data_on_uninstall"><?php esc_html_e( 'Delete Data on Uninstall', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_delete_data_on_uninstall" name="mhd_delete_data_on_uninstall" value="1"
							<?php checked( get_option( 'mhd_delete_data_on_uninstall', '' ), '1' ); ?> />
						<p class="description"><?php esc_html_e( 'Remove all plugin data and tables when the plugin is deleted.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_debug_mode"><?php esc_html_e( 'Debug Mode', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="mhd_debug_mode" name="mhd_debug_mode" value="1"
							<?php checked( get_option( 'mhd_debug_mode', '' ), '1' ); ?> />
						<p class="description"><?php esc_html_e( 'Enable verbose logging to the debug.log file.', 'myhelpdesk-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mhd_custom_js"><?php esc_html_e( 'Custom JavaScript', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<textarea id="mhd_custom_js" name="mhd_custom_js" rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'mhd_custom_js', '' ) ); ?></textarea>
					</td>
				</tr>
			</table>

		<?php endif; ?>

		<?php submit_button( __( 'Save Settings', 'myhelpdesk-chat' ) ); ?>

	</form>

</div><!-- .wrap -->
