<?php
/**
 * Loader class.
 *
 * Collects all action and filter hooks and registers them with WordPress.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Loader
 *
 * Maintains lists of all hooks registered by the plugin and fires them
 * at the appropriate time via the run() method.
 *
 * @since 1.0.0
 */
class MHD_Loader {

	/**
	 * Array of actions registered with WordPress.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array $actions The actions registered with WordPress.
	 */
	protected $actions;

	/**
	 * Array of filters registered with WordPress.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array $filters The filters registered with WordPress.
	 */
	protected $filters;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook          The name of the WordPress action.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the method on the component.
	 * @param int    $priority      Optional. The priority. Default 10.
	 * @param int    $accepted_args Optional. The number of arguments. Default 1.
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook          The name of the WordPress filter.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the method on the component.
	 * @param int    $priority      Optional. The priority. Default 10.
	 * @param int    $accepted_args Optional. The number of arguments. Default 1.
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Utility to register the actions and hooks into a single collection.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param array  $hooks         The collection of hooks being registered.
	 * @param string $hook          The name of the WordPress hook.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the method.
	 * @param int    $priority      The priority at which the function should be fired.
	 * @param int    $accepted_args The number of arguments that should be passed.
	 * @return array The collection of actions and filters registered with WordPress.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * Instantiates all feature classes and registers their hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {

		// Admin hooks.
		$admin = new MHD_Admin();
		$this->add_action( 'admin_menu', $admin, 'register_menu' );
		$this->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->add_action( 'admin_init', $admin, 'register_settings' );
		$this->add_action( 'admin_notices', $admin, 'activation_notice' );

		// Public / Frontend hooks.
		$public = new MHD_Public();
		$this->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
		$this->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
		$this->add_action( 'wp_footer', $public, 'render_chat_widget' );

		// AJAX hooks.
		$ajax = new MHD_Ajax();
		$this->add_action( 'wp_ajax_mhd_start_conversation', $ajax, 'start_conversation' );
		$this->add_action( 'wp_ajax_nopriv_mhd_start_conversation', $ajax, 'start_conversation' );
		$this->add_action( 'wp_ajax_mhd_send_message', $ajax, 'send_message' );
		$this->add_action( 'wp_ajax_nopriv_mhd_send_message', $ajax, 'send_message' );
		$this->add_action( 'wp_ajax_mhd_get_messages', $ajax, 'get_messages' );
		$this->add_action( 'wp_ajax_nopriv_mhd_get_messages', $ajax, 'get_messages' );
		$this->add_action( 'wp_ajax_mhd_set_typing', $ajax, 'set_typing' );
		$this->add_action( 'wp_ajax_nopriv_mhd_set_typing', $ajax, 'set_typing' );
		$this->add_action( 'wp_ajax_mhd_get_typing', $ajax, 'get_typing' );
		$this->add_action( 'wp_ajax_nopriv_mhd_get_typing', $ajax, 'get_typing' );
		$this->add_action( 'wp_ajax_mhd_get_agent_status', $ajax, 'get_agent_status' );
		$this->add_action( 'wp_ajax_nopriv_mhd_get_agent_status', $ajax, 'get_agent_status' );
		$this->add_action( 'wp_ajax_mhd_get_conversations_updates', $ajax, 'get_conversations_updates' );
		$this->add_action( 'wp_ajax_mhd_update_conversation', $ajax, 'update_conversation' );
		$this->add_action( 'wp_ajax_mhd_get_notifications', $ajax, 'get_notifications' );
		$this->add_action( 'wp_ajax_mhd_mark_notifications_read', $ajax, 'mark_notifications_read' );
		$this->add_action( 'wp_ajax_mhd_upload_file', $ajax, 'upload_file' );
		$this->add_action( 'wp_ajax_nopriv_mhd_upload_file', $ajax, 'upload_file' );
		$this->add_action( 'wp_ajax_mhd_submit_rating', $ajax, 'submit_rating' );
		$this->add_action( 'wp_ajax_nopriv_mhd_submit_rating', $ajax, 'submit_rating' );
		$this->add_action( 'wp_ajax_mhd_submit_ticket', $ajax, 'submit_ticket' );
		$this->add_action( 'wp_ajax_nopriv_mhd_submit_ticket', $ajax, 'submit_ticket' );
		$this->add_action( 'wp_ajax_mhd_get_kb_articles', $ajax, 'get_kb_articles' );
		$this->add_action( 'wp_ajax_nopriv_mhd_get_kb_articles', $ajax, 'get_kb_articles' );
		$this->add_action( 'wp_ajax_mhd_save_agent', $ajax, 'save_agent' );
		$this->add_action( 'wp_ajax_mhd_delete_agent', $ajax, 'delete_agent' );
		$this->add_action( 'wp_ajax_mhd_save_department', $ajax, 'save_department' );
		$this->add_action( 'wp_ajax_mhd_delete_department', $ajax, 'delete_department' );
		$this->add_action( 'wp_ajax_mhd_save_reply', $ajax, 'save_reply' );
		$this->add_action( 'wp_ajax_mhd_delete_reply', $ajax, 'delete_reply' );
		$this->add_action( 'wp_ajax_mhd_get_saved_replies', $ajax, 'get_saved_replies' );
		$this->add_action( 'wp_ajax_mhd_save_automation', $ajax, 'save_automation' );
		$this->add_action( 'wp_ajax_mhd_delete_automation', $ajax, 'delete_automation' );
		$this->add_action( 'wp_ajax_mhd_save_kb_category', $ajax, 'save_kb_category' );
		$this->add_action( 'wp_ajax_mhd_delete_kb_category', $ajax, 'delete_kb_category' );
		$this->add_action( 'wp_ajax_mhd_save_kb_article', $ajax, 'save_kb_article' );
		$this->add_action( 'wp_ajax_mhd_delete_kb_article', $ajax, 'delete_kb_article' );
		$this->add_action( 'wp_ajax_mhd_get_report_data', $ajax, 'get_report_data' );
		$this->add_action( 'wp_ajax_mhd_export_csv', $ajax, 'export_csv' );
		$this->add_action( 'wp_ajax_mhd_set_agent_status', $ajax, 'set_agent_status' );
		$this->add_action( 'wp_ajax_mhd_send_direct_message', $ajax, 'send_direct_message' );
		$this->add_action( 'wp_ajax_mhd_delete_conversation', $ajax, 'delete_conversation' );
		$this->add_action( 'wp_ajax_mhd_offline_form', $ajax, 'offline_form' );
		$this->add_action( 'wp_ajax_nopriv_mhd_offline_form', $ajax, 'offline_form' );
		$this->add_action( 'wp_ajax_mhd_kb_helpful', $ajax, 'kb_helpful' );
		$this->add_action( 'wp_ajax_nopriv_mhd_kb_helpful', $ajax, 'kb_helpful' );

		// Notifications hooks.
		$notifications = new MHD_Notifications();
		$this->add_action( 'mhd_new_conversation', $notifications, 'on_new_conversation' );
		$this->add_action( 'mhd_new_message', $notifications, 'on_new_message' );
		$this->add_action( 'mhd_new_ticket', $notifications, 'on_new_ticket' );
		$this->add_action( 'mhd_ticket_reply', $notifications, 'on_ticket_reply' );

		// Automations hooks.
		$automations = new MHD_Automations();
		$this->add_action( 'mhd_new_conversation', $automations, 'trigger_new_conversation' );
		$this->add_action( 'mhd_new_message', $automations, 'trigger_new_message' );
		$this->add_action( 'mhd_new_ticket', $automations, 'trigger_new_ticket' );

		// Email hooks.
		$email = new MHD_Email();
		$this->add_action( 'mhd_send_agent_notification', $email, 'send_agent_notification', 10, 3 );
		$this->add_action( 'mhd_send_user_notification', $email, 'send_user_notification', 10, 3 );

		// REST API.
		$rest = new MHD_Rest_API();
		$this->add_action( 'rest_api_init', $rest, 'register_routes' );

		// Shortcodes.
		add_shortcode( 'mhd_chat_widget', array( $public, 'shortcode_chat_widget' ) );
		add_shortcode( 'mhd_ticket_form', array( $public, 'shortcode_ticket_form' ) );
		add_shortcode( 'mhd_my_tickets', array( $public, 'shortcode_my_tickets' ) );
		add_shortcode( 'mhd_knowledge_base', array( $public, 'shortcode_knowledge_base' ) );
		add_shortcode( 'mhd_kb_article', array( $public, 'shortcode_kb_article' ) );
		add_shortcode( 'mhd_kb_search', array( $public, 'shortcode_kb_search' ) );

		// Fire all registered hooks.
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
