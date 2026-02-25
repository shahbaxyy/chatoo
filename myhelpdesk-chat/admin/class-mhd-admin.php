<?php
/**
 * Admin class for the MyHelpDesk Chat plugin.
 *
 * Registers the admin menu, enqueues assets, registers settings,
 * and renders all admin view pages.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Admin
 *
 * Handles every aspect of the WordPress admin integration for the
 * MyHelpDesk Chat plugin including menus, assets, settings, and views.
 *
 * @since 1.0.0
 */
class MHD_Admin {

	/**
	 * Register the top-level menu and all submenu pages.
	 *
	 * Creates the "MyHelpDesk" menu at position 30 with submenus for
	 * Dashboard, Conversations, Tickets, Agents, Departments,
	 * Knowledge Base, Saved Replies, Automations, Reports, and Settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'MyHelpDesk', 'myhelpdesk-chat' ),
			__( 'MyHelpDesk', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk',
			array( $this, 'render_dashboard' ),
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Dashboard', 'myhelpdesk-chat' ),
			__( 'Dashboard', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Conversations', 'myhelpdesk-chat' ),
			__( 'Conversations', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-conversations',
			array( $this, 'render_conversations' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Tickets', 'myhelpdesk-chat' ),
			__( 'Tickets', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-tickets',
			array( $this, 'render_tickets' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Agents', 'myhelpdesk-chat' ),
			__( 'Agents', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-agents',
			array( $this, 'render_agents' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Departments', 'myhelpdesk-chat' ),
			__( 'Departments', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-departments',
			array( $this, 'render_departments' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Knowledge Base', 'myhelpdesk-chat' ),
			__( 'Knowledge Base', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-knowledge-base',
			array( $this, 'render_knowledge_base' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Saved Replies', 'myhelpdesk-chat' ),
			__( 'Saved Replies', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-saved-replies',
			array( $this, 'render_saved_replies' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Automations', 'myhelpdesk-chat' ),
			__( 'Automations', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-automations',
			array( $this, 'render_automations' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Reports', 'myhelpdesk-chat' ),
			__( 'Reports', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-reports',
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			'myhelpdesk',
			__( 'Settings', 'myhelpdesk-chat' ),
			__( 'Settings', 'myhelpdesk-chat' ),
			'manage_options',
			'myhelpdesk-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin stylesheets.
	 *
	 * Loads admin-style.css only on plugin pages where the hook
	 * suffix contains "myhelpdesk".
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		if ( false === strpos( $hook, 'myhelpdesk' ) ) {
			return;
		}

		wp_enqueue_style(
			'mhd-admin-style',
			MHD_PLUGIN_URL . 'admin/css/admin-style.css',
			array(),
			MHD_VERSION
		);
	}

	/**
	 * Enqueue admin JavaScript files.
	 *
	 * Loads admin JS only on plugin pages and localizes the mhd_admin
	 * object with ajax_url, nonce, and plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( false === strpos( $hook, 'myhelpdesk' ) ) {
			return;
		}

		wp_enqueue_script(
			'mhd-admin-script',
			MHD_PLUGIN_URL . 'admin/js/admin-script.js',
			array( 'jquery' ),
			MHD_VERSION,
			true
		);

		wp_localize_script(
			'mhd-admin-script',
			'mhd_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mhd_nonce' ),
				'settings' => array(
					'chat_enabled'       => get_option( 'mhd_chat_enabled', '1' ),
					'auto_assign'        => get_option( 'mhd_auto_assign', '1' ),
					'assignment_method'  => get_option( 'mhd_assignment_method', 'round_robin' ),
					'sound_notification' => get_option( 'mhd_sound_notification', '1' ),
					'file_uploads'       => get_option( 'mhd_file_uploads', '1' ),
				),
			)
		);
	}

	/**
	 * Register all plugin settings with the WordPress Settings API.
	 *
	 * Registers settings groups and individual options for General,
	 * Chat Behavior, Notifications, Design, Agents, Tickets,
	 * Knowledge Base, Email Piping, WooCommerce, and Advanced tabs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings() {

		/* --------------------------------------------------------
		 * General settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_general_settings', 'mhd_chat_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_general_settings', 'mhd_company_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_general_settings', 'mhd_company_logo', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'mhd_general_settings', 'mhd_default_language', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_general_settings', 'mhd_timezone', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		/* --------------------------------------------------------
		 * Chat behavior settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_chat_settings', 'mhd_auto_assign', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_chat_settings', 'mhd_assignment_method', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_chat_settings', 'mhd_offline_message', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'mhd_chat_settings', 'mhd_welcome_message', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'mhd_chat_settings', 'mhd_pre_chat_form', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_chat_settings', 'mhd_file_uploads', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_chat_settings', 'mhd_max_file_size', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'mhd_chat_settings', 'mhd_chat_rating', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_chat_settings', 'mhd_inactivity_timeout', array( 'sanitize_callback' => 'absint' ) );

		/* --------------------------------------------------------
		 * Notification settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_notification_settings', 'mhd_sound_notification', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_notification_settings', 'mhd_email_notifications', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_notification_settings', 'mhd_notification_email', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'mhd_notification_settings', 'mhd_browser_push', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		/* --------------------------------------------------------
		 * Design settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_design_settings', 'mhd_primary_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'mhd_design_settings', 'mhd_widget_position', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_design_settings', 'mhd_widget_icon', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_design_settings', 'mhd_custom_css', array( 'sanitize_callback' => 'wp_strip_all_tags' ) );

		/* --------------------------------------------------------
		 * Agents & Departments settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_agents_settings', 'mhd_default_department', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'mhd_agents_settings', 'mhd_max_concurrent_chats', array( 'sanitize_callback' => 'absint' ) );

		/* --------------------------------------------------------
		 * Ticket settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_ticket_settings', 'mhd_ticket_prefix', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_ticket_settings', 'mhd_auto_close_days', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'mhd_ticket_settings', 'mhd_allow_guest_tickets', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		/* --------------------------------------------------------
		 * Knowledge Base settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_kb_settings', 'mhd_kb_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_kb_settings', 'mhd_kb_slug', array( 'sanitize_callback' => 'sanitize_title' ) );
		register_setting( 'mhd_kb_settings', 'mhd_kb_per_page', array( 'sanitize_callback' => 'absint' ) );

		/* --------------------------------------------------------
		 * Email Piping settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_email_settings', 'mhd_email_piping_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_email_settings', 'mhd_imap_host', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_email_settings', 'mhd_imap_port', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'mhd_email_settings', 'mhd_imap_username', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_email_settings', 'mhd_imap_password', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_email_settings', 'mhd_imap_encryption', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		/* --------------------------------------------------------
		 * WooCommerce settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_woo_settings', 'mhd_woo_integration', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_woo_settings', 'mhd_woo_order_info', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		/* --------------------------------------------------------
		 * Advanced settings
		 * ------------------------------------------------------ */
		register_setting( 'mhd_advanced_settings', 'mhd_delete_data_on_uninstall', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_advanced_settings', 'mhd_debug_mode', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mhd_advanced_settings', 'mhd_custom_js', array( 'sanitize_callback' => 'wp_strip_all_tags' ) );
	}

	/**
	 * Display an admin notice after plugin activation.
	 *
	 * Shows a dismissible success notice with a link to the settings page.
	 * The notice is removed once the user visits the settings page or
	 * dismisses it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activation_notice() {
		if ( ! get_transient( 'mhd_activation_notice' ) ) {
			return;
		}

		$settings_url = esc_url( admin_url( 'admin.php?page=myhelpdesk-settings' ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'MyHelpDesk Chat has been activated! Get started by configuring your', 'myhelpdesk-chat' ),
			$settings_url,
			esc_html__( 'settings.', 'myhelpdesk-chat' )
		);

		delete_transient( 'mhd_activation_notice' );
	}

	/**
	 * Render the Dashboard page.
	 *
	 * Includes the dashboard view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_dashboard() {
		require_once MHD_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the Conversations page.
	 *
	 * Includes the conversations view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_conversations() {
		require_once MHD_PLUGIN_DIR . 'admin/views/conversations.php';
	}

	/**
	 * Render the Tickets page.
	 *
	 * Includes the tickets view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_tickets() {
		require_once MHD_PLUGIN_DIR . 'admin/views/tickets.php';
	}

	/**
	 * Render the Agents page.
	 *
	 * Includes the agents view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_agents() {
		require_once MHD_PLUGIN_DIR . 'admin/views/agents.php';
	}

	/**
	 * Render the Departments page.
	 *
	 * Includes the departments view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_departments() {
		require_once MHD_PLUGIN_DIR . 'admin/views/departments.php';
	}

	/**
	 * Render the Knowledge Base page.
	 *
	 * Includes the knowledge base view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_knowledge_base() {
		require_once MHD_PLUGIN_DIR . 'admin/views/knowledge-base.php';
	}

	/**
	 * Render the Saved Replies page.
	 *
	 * Includes the saved replies view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_saved_replies() {
		require_once MHD_PLUGIN_DIR . 'admin/views/saved-replies.php';
	}

	/**
	 * Render the Automations page.
	 *
	 * Includes the automations view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_automations() {
		require_once MHD_PLUGIN_DIR . 'admin/views/automations.php';
	}

	/**
	 * Render the Reports page.
	 *
	 * Includes the reports view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_reports() {
		require_once MHD_PLUGIN_DIR . 'admin/views/reports.php';
	}

	/**
	 * Render the Settings page.
	 *
	 * Includes the settings view template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings() {
		require_once MHD_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
