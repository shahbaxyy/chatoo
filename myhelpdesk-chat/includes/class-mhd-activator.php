<?php
/**
 * Fired during plugin activation.
 *
 * Creates all database tables, sets default options, and registers custom capabilities.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Activator
 *
 * @since 1.0.0
 */
class MHD_Activator {

	/**
	 * Plugin activation routine.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::add_capabilities();
		set_transient( 'mhd_activation_notice', true, 60 );
		flush_rewrite_rules();
	}

	/**
	 * Create all 12 plugin database tables using dbDelta.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . MHD_TABLE_PREFIX;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// TABLE 1: Conversations.
		$sql = "CREATE TABLE {$prefix}conversations (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			agent_id bigint(20) UNSIGNED DEFAULT NULL,
			department_id int(10) UNSIGNED DEFAULT NULL,
			status enum('open','pending','resolved','archived') DEFAULT 'open',
			subject varchar(255) DEFAULT '',
			source enum('chat','email','ticket','whatsapp','facebook','telegram','direct') DEFAULT 'chat',
			user_email varchar(255) DEFAULT '',
			user_name varchar(100) DEFAULT '',
			user_ip varchar(45) DEFAULT '',
			user_browser varchar(255) DEFAULT '',
			user_location varchar(255) DEFAULT '',
			current_page text,
			tags text,
			extra_data longtext,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_user_id (user_id),
			KEY idx_agent_id (agent_id),
			KEY idx_status (status),
			KEY idx_department_id (department_id),
			KEY idx_updated_at (updated_at)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 2: Messages.
		$sql = "CREATE TABLE {$prefix}messages (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			agent_id bigint(20) UNSIGNED DEFAULT NULL,
			message longtext NOT NULL,
			attachments text DEFAULT NULL,
			message_type enum('text','image','file','system','note','rich','email') DEFAULT 'text',
			is_read tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 3: Agents.
		$sql = "CREATE TABLE {$prefix}agents (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			department_id int(10) UNSIGNED DEFAULT NULL,
			role enum('agent','supervisor','admin') DEFAULT 'agent',
			is_online tinyint(1) DEFAULT 0,
			status enum('active','away','offline') DEFAULT 'offline',
			max_chats int(11) DEFAULT 5,
			last_seen datetime DEFAULT NULL,
			profile_image varchar(255) DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY idx_user_id (user_id),
			KEY idx_department_id (department_id),
			KEY idx_status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 4: Departments.
		$sql = "CREATE TABLE {$prefix}departments (
			id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			description text DEFAULT '',
			color varchar(10) DEFAULT '#0084ff',
			agents text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 5: Tickets.
		$sql = "CREATE TABLE {$prefix}tickets (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) UNSIGNED DEFAULT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			user_email varchar(255) DEFAULT '',
			subject varchar(255) NOT NULL,
			status enum('open','in_progress','resolved','closed') DEFAULT 'open',
			priority enum('low','medium','high','urgent') DEFAULT 'medium',
			assigned_agent_id bigint(20) UNSIGNED DEFAULT NULL,
			department_id int(10) UNSIGNED DEFAULT NULL,
			tags text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_user_id (user_id),
			KEY idx_status (status),
			KEY idx_assigned_agent_id (assigned_agent_id)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 6: Ticket Replies.
		$sql = "CREATE TABLE {$prefix}ticket_replies (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ticket_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			agent_id bigint(20) UNSIGNED DEFAULT NULL,
			message longtext NOT NULL,
			attachments text DEFAULT NULL,
			is_note tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_ticket_id (ticket_id)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 7: Saved Replies.
		$sql = "CREATE TABLE {$prefix}saved_replies (
			id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			message text NOT NULL,
			agent_id bigint(20) UNSIGNED DEFAULT NULL,
			category varchar(100) DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 8: Notifications.
		$sql = "CREATE TABLE {$prefix}notifications (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			agent_id bigint(20) UNSIGNED NOT NULL,
			conversation_id bigint(20) UNSIGNED DEFAULT NULL,
			ticket_id bigint(20) UNSIGNED DEFAULT NULL,
			type enum('new_chat','new_message','new_ticket','ticket_reply','assigned') DEFAULT 'new_message',
			message varchar(255) NOT NULL,
			is_read tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_agent_id (agent_id),
			KEY idx_is_read (is_read)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 9: KB Categories.
		$sql = "CREATE TABLE {$prefix}kb_categories (
			id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			icon varchar(50) DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_slug (slug)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 10: KB Articles.
		$sql = "CREATE TABLE {$prefix}kb_articles (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id int(10) UNSIGNED DEFAULT NULL,
			title varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			content longtext NOT NULL,
			excerpt text DEFAULT '',
			views bigint(20) DEFAULT 0,
			helpful_yes int(11) DEFAULT 0,
			helpful_no int(11) DEFAULT 0,
			status enum('published','draft') DEFAULT 'published',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_slug (slug),
			KEY idx_category_id (category_id),
			KEY idx_status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 11: Automations.
		$sql = "CREATE TABLE {$prefix}automations (
			id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			trigger_type varchar(100) NOT NULL,
			conditions text DEFAULT NULL,
			action_type varchar(100) NOT NULL,
			action_data text DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// TABLE 12: Ratings.
		$sql = "CREATE TABLE {$prefix}ratings (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) UNSIGNED NOT NULL,
			agent_id bigint(20) UNSIGNED DEFAULT NULL,
			rating tinyint(4) NOT NULL,
			comment text DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_agent_id (agent_id)
		) $charset_collate;";
		dbDelta( $sql );

		// Store DB version.
		update_option( 'mhd_db_version', MHD_VERSION );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			// General.
			'mhd_chat_enabled'          => '1',
			'mhd_chat_visibility'       => 'everyone',
			'mhd_excluded_pages'        => '',
			'mhd_cookie_duration'       => '30',
			'mhd_date_format'           => 'relative',

			// Chat Behavior.
			'mhd_welcome_message'       => __( 'Hello! How can we help you today?', 'myhelpdesk-chat' ),
			'mhd_offline_message'       => __( 'We are currently offline. Please leave a message and we will get back to you.', 'myhelpdesk-chat' ),
			'mhd_prechat_enabled'       => '1',
			'mhd_prechat_name'          => '1',
			'mhd_prechat_email'         => '1',
			'mhd_prechat_department'    => '0',
			'mhd_gdpr_enabled'          => '0',
			'mhd_gdpr_text'             => __( 'I agree to the privacy policy.', 'myhelpdesk-chat' ),
			'mhd_auto_open_delay'       => '0',
			'mhd_proactive_message'     => '',
			'mhd_proactive_delay'       => '30',
			'mhd_popup_message'         => '',
			'mhd_popup_delay'           => '10',
			'mhd_subscribe_enabled'     => '0',
			'mhd_followup_enabled'      => '0',
			'mhd_followup_message'      => __( 'Welcome back! Do you have any questions?', 'myhelpdesk-chat' ),
			'mhd_followup_delay'        => '24',
			'mhd_rating_enabled'        => '1',
			'mhd_rating_prompt'         => __( 'How was your experience?', 'myhelpdesk-chat' ),
			'mhd_file_upload_enabled'   => '1',
			'mhd_max_file_size'         => '10',
			'mhd_allowed_file_types'    => 'jpg,jpeg,png,gif,pdf,doc,docx,zip',

			// Notifications.
			'mhd_email_notifications'   => '1',
			'mhd_email_from_name'       => get_bloginfo( 'name' ),
			'mhd_email_from_address'    => get_bloginfo( 'admin_email' ),
			'mhd_custom_smtp_enabled'   => '0',
			'mhd_smtp_host'             => '',
			'mhd_smtp_port'             => '587',
			'mhd_smtp_username'         => '',
			'mhd_smtp_password'         => '',
			'mhd_smtp_encryption'       => 'tls',
			'mhd_desktop_notifications' => '1',
			'mhd_sound_notifications'   => '1',

			// Design.
			'mhd_primary_color'         => '#0084ff',
			'mhd_secondary_color'       => '#f0f0f0',
			'mhd_chat_header_text'      => __( 'Chat with us', 'myhelpdesk-chat' ),
			'mhd_bubble_icon'           => 'default',
			'mhd_widget_width'          => '370',
			'mhd_widget_height'         => '520',
			'mhd_font_size'             => 'medium',
			'mhd_border_radius'         => '12',
			'mhd_show_agent_avatar'     => '1',
			'mhd_custom_css'            => '',
			'mhd_powered_by'            => '1',

			// Agents & Departments.
			'mhd_default_department'    => '',
			'mhd_auto_assign_method'    => 'round-robin',
			'mhd_agent_away_timeout'    => '10',
			'mhd_max_queue_size'        => '50',
			'mhd_show_agent_name'       => '1',
			'mhd_show_agents_online'    => '1',

			// Tickets.
			'mhd_tickets_enabled'       => '1',
			'mhd_ticket_require_login'  => '0',
			'mhd_default_priority'      => 'medium',
			'mhd_ticket_from_offline'   => '1',
			'mhd_ticket_subjects'       => '',

			// Knowledge Base.
			'mhd_kb_enabled'            => '1',
			'mhd_kb_slug'               => 'knowledge-base',
			'mhd_kb_in_widget'          => '1',
			'mhd_kb_suggestions_count'  => '5',

			// Email Piping.
			'mhd_email_piping_enabled'  => '0',
			'mhd_imap_host'             => '',
			'mhd_imap_port'             => '993',
			'mhd_imap_username'         => '',
			'mhd_imap_password'         => '',
			'mhd_imap_encryption'       => 'ssl',
			'mhd_imap_frequency'        => '5',
			'mhd_imap_delete_after'     => '0',

			// WooCommerce.
			'mhd_woo_enabled'           => '1',
			'mhd_woo_show_orders'       => '1',
			'mhd_woo_regular_threshold' => '3',
			'mhd_woo_vip_threshold'     => '10',

			// Advanced.
			'mhd_delete_data_on_uninstall' => '0',
			'mhd_rest_api_enabled'         => '0',
			'mhd_api_key'                  => '',
			'mhd_debug_mode'               => '0',
			'mhd_polling_interval'         => '3',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}

	/**
	 * Add the custom mhd_agent capability to administrator role.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private static function add_capabilities() {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'mhd_agent' );
		}
	}
}
