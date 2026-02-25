<?php
/**
 * AJAX Handler for MyHelpDesk Chat.
 *
 * Handles all WordPress AJAX requests for the plugin.
 *
 * @package    MyHelpDesk_Chat
 * @subpackage MyHelpDesk_Chat/includes
 * @since      1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Ajax
 *
 * Processes all front-end and admin AJAX actions for live chat,
 * tickets, knowledge base, agents, departments, automations, and reports.
 *
 * @since 1.0.0
 */
class MHD_Ajax {

	/**
	 * Start a new chat conversation.
	 *
	 * Creates a conversation record and sets a guest cookie for
	 * non-authenticated visitors.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function start_conversation() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$user_name     = sanitize_text_field( wp_unslash( $_POST['user_name'] ?? '' ) );
		$user_email    = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
		$subject       = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$department_id = absint( $_POST['department_id'] ?? 0 );
		$current_page  = esc_url_raw( wp_unslash( $_POST['current_page'] ?? '' ) );

		if ( empty( $user_name ) || empty( $user_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Name and email are required.', 'myhelpdesk-chat' ) ) );
		}

		$conversations = new MHD_Conversations();

		$data = array(
			'user_name'     => $user_name,
			'user_email'    => $user_email,
			'subject'       => $subject,
			'department_id' => $department_id,
			'current_page'  => $current_page,
			'user_ip'       => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'user_browser'  => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
			'source'        => 'chat',
			'status'        => 'open',
		);

		if ( is_user_logged_in() ) {
			$data['user_id'] = get_current_user_id();
		}

		$conversation_id = $conversations->create_conversation( $data );

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create conversation.', 'myhelpdesk-chat' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			$duration = absint( get_option( 'mhd_cookie_duration', 30 ) ) * DAY_IN_SECONDS;
			setcookie( 'mhd_guest_' . $conversation_id, $user_email, time() + $duration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}

		$conversation = $conversations->get_conversation( $conversation_id );

		wp_send_json_success( array( 'conversation' => $conversation ) );
	}

	/**
	 * Send a message within a conversation.
	 *
	 * Determines sender type (agent vs. user) based on current user
	 * capabilities and creates the message record.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function send_message() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$message         = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
		$message_type    = sanitize_text_field( wp_unslash( $_POST['message_type'] ?? 'text' ) );

		if ( ! $conversation_id || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID and message are required.', 'myhelpdesk-chat' ) ) );
		}

		$is_agent = current_user_can( 'mhd_agent' );

		$data = array(
			'conversation_id' => $conversation_id,
			'message'         => $message,
			'message_type'    => $message_type,
		);

		if ( $is_agent ) {
			$data['agent_id'] = get_current_user_id();
		} else {
			$data['user_id'] = is_user_logged_in() ? get_current_user_id() : 0;
		}

		$messages   = new MHD_Messages();
		$message_id = $messages->create_message( $data );

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not send message.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success( array(
			'message_id' => $message_id,
			'message'    => $messages->get_message( $message_id ),
		) );
	}

	/**
	 * Retrieve messages for a conversation since a given message ID.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_messages() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$last_message_id = absint( $_POST['last_message_id'] ?? 0 );

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$messages_model = new MHD_Messages();
		$messages       = $messages_model->get_messages( $conversation_id, array(
			'since_id' => $last_message_id,
		) );

		wp_send_json_success( array( 'messages' => $messages ) );
	}

	/**
	 * Set the typing indicator for the current user in a conversation.
	 *
	 * Stores a short-lived transient so the other party can poll for it.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function set_typing() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$is_typing       = rest_sanitize_boolean( $_POST['is_typing'] ?? false );

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$user_type = current_user_can( 'mhd_agent' ) ? 'agent' : 'user';
		$key       = 'mhd_typing_' . $conversation_id . '_' . $user_type;

		if ( $is_typing ) {
			set_transient( $key, true, 5 );
		} else {
			delete_transient( $key );
		}

		wp_send_json_success();
	}

	/**
	 * Check whether the other party is currently typing.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_typing() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$check_type = current_user_can( 'mhd_agent' ) ? 'user' : 'agent';
		$is_typing  = (bool) get_transient( 'mhd_typing_' . $conversation_id . '_' . $check_type );

		wp_send_json_success( array( 'is_typing' => $is_typing ) );
	}

	/**
	 * Get the assigned agent's online status for a conversation.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_agent_status() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$conversations = new MHD_Conversations();
		$conversation  = $conversations->get_conversation( $conversation_id );

		if ( ! $conversation || empty( $conversation->agent_id ) ) {
			wp_send_json_success( array(
				'agent_name'   => '',
				'agent_avatar' => '',
				'status'       => 'offline',
			) );
			return;
		}

		$agents = new MHD_Agents();
		$agent  = $agents->get_agent_by_user_id( $conversation->agent_id );

		if ( ! $agent ) {
			wp_send_json_success( array(
				'agent_name'   => '',
				'agent_avatar' => '',
				'status'       => 'offline',
			) );
			return;
		}

		$user = get_userdata( $conversation->agent_id );

		wp_send_json_success( array(
			'agent_name'   => $user ? $user->display_name : '',
			'agent_avatar' => get_avatar_url( $conversation->agent_id ),
			'status'       => isset( $agent->status ) ? $agent->status : 'offline',
		) );
	}

	/**
	 * Get conversations updated since a given timestamp.
	 *
	 * Restricted to users with the mhd_agent capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_conversations_updates() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$last_update = sanitize_text_field( wp_unslash( $_POST['last_update'] ?? '' ) );

		$conversations = new MHD_Conversations();
		$results       = $conversations->get_conversations( array(
			'updated_since' => $last_update,
		) );

		wp_send_json_success( array( 'conversations' => $results ) );
	}

	/**
	 * Update a single field on a conversation.
	 *
	 * Restricted to users with the mhd_agent capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function update_conversation() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$field           = sanitize_text_field( wp_unslash( $_POST['field'] ?? '' ) );
		$value           = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

		if ( ! $conversation_id || empty( $field ) ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID and field are required.', 'myhelpdesk-chat' ) ) );
		}

		$allowed_fields = array( 'status', 'agent_id', 'department_id', 'subject', 'tags' );

		if ( ! in_array( $field, $allowed_fields, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'myhelpdesk-chat' ) ) );
		}

		$conversations = new MHD_Conversations();
		$updated       = $conversations->update_conversation( $conversation_id, array( $field => $value ) );

		if ( ! $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not update conversation.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success( array(
			'conversation' => $conversations->get_conversation( $conversation_id ),
		) );
	}

	/**
	 * Delete a conversation and its messages.
	 *
	 * Restricted to users with the mhd_agent capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function delete_conversation() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$messages = new MHD_Messages();
		$messages->delete_messages( $conversation_id );

		$conversations = new MHD_Conversations();
		$deleted       = $conversations->delete_conversation( $conversation_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete conversation.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// Notification handlers
	// ------------------------------------------------------------------

	/**
	 * Get notifications for the current agent.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_notifications() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$notifications = new MHD_Notifications();
		$items         = $notifications->get_notifications( get_current_user_id() );
		$unread        = $notifications->get_unread_count( get_current_user_id() );

		wp_send_json_success( array(
			'notifications' => $items,
			'unread_count'  => $unread,
		) );
	}

	/**
	 * Mark all notifications as read for the current agent.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function mark_notifications_read() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$notifications = new MHD_Notifications();
		$notifications->mark_as_read( get_current_user_id() );

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// File upload
	// ------------------------------------------------------------------

	/**
	 * Handle a file upload within a conversation.
	 *
	 * Validates the file against the allowed MIME types configured in
	 * plugin settings and delegates to wp_handle_upload().
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function upload_file() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'myhelpdesk-chat' ) ) );
		}

		$allowed_types = get_option( 'mhd_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,zip' );
		$allowed_array = array_map( 'trim', explode( ',', $allowed_types ) );

		$file_info = wp_check_filetype( sanitize_file_name( $_FILES['file']['name'] ) );

		if ( ! $file_info['ext'] || ! in_array( strtolower( $file_info['ext'] ), $allowed_array, true ) ) {
			wp_send_json_error( array( 'message' => __( 'File type not allowed.', 'myhelpdesk-chat' ) ) );
		}

		$overrides = array(
			'test_form' => false,
			'mimes'     => array( $file_info['ext'] => $file_info['type'] ),
		);

		$uploaded = wp_handle_upload( $_FILES['file'], $overrides );

		if ( isset( $uploaded['error'] ) ) {
			wp_send_json_error( array( 'message' => $uploaded['error'] ) );
		}

		wp_send_json_success( array(
			'url'  => esc_url( $uploaded['url'] ),
			'type' => $uploaded['type'],
		) );
	}

	// ------------------------------------------------------------------
	// Rating
	// ------------------------------------------------------------------

	/**
	 * Submit a rating for a conversation.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function submit_rating() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$conversation_id = absint( $_POST['conversation_id'] ?? 0 );
		$rating          = absint( $_POST['rating'] ?? 0 );
		$comment         = sanitize_text_field( wp_unslash( $_POST['comment'] ?? '' ) );

		if ( ! $conversation_id || ! $rating ) {
			wp_send_json_error( array( 'message' => __( 'Conversation ID and rating are required.', 'myhelpdesk-chat' ) ) );
		}

		$conversations = new MHD_Conversations();
		$conversation  = $conversations->get_conversation( $conversation_id );

		if ( ! $conversation ) {
			wp_send_json_error( array( 'message' => __( 'Conversation not found.', 'myhelpdesk-chat' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'mhd_ratings';

		$inserted = $wpdb->insert(
			$table,
			array(
				'conversation_id' => $conversation_id,
				'agent_id'        => $conversation->agent_id ? $conversation->agent_id : null,
				'rating'          => $rating,
				'comment'         => $comment,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Could not save rating.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
	}

	// ------------------------------------------------------------------
	// Ticket handlers
	// ------------------------------------------------------------------

	/**
	 * Submit a new support ticket.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function submit_ticket() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$subject  = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$message  = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$priority = sanitize_text_field( wp_unslash( $_POST['priority'] ?? 'normal' ) );

		if ( empty( $subject ) || empty( $message ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Subject, message, and email are required.', 'myhelpdesk-chat' ) ) );
		}

		$tickets   = new MHD_Tickets();
		$ticket_id = $tickets->create_ticket( array(
			'subject'  => $subject,
			'message'  => $message,
			'email'    => $email,
			'priority' => $priority,
			'user_id'  => is_user_logged_in() ? get_current_user_id() : 0,
		) );

		if ( ! $ticket_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create ticket.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success( array(
			'ticket_id' => $ticket_id,
			'ticket'    => $tickets->get_ticket( $ticket_id ),
		) );
	}

	// ------------------------------------------------------------------
	// Knowledge Base (public)
	// ------------------------------------------------------------------

	/**
	 * Search knowledge base articles.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_kb_articles() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );

		if ( empty( $query ) ) {
			wp_send_json_error( array( 'message' => __( 'Search query is required.', 'myhelpdesk-chat' ) ) );
		}

		$kb       = new MHD_Knowledge_Base();
		$articles = $kb->search_articles( $query );

		wp_send_json_success( array( 'articles' => $articles ) );
	}

	/**
	 * Record a helpful / not-helpful vote for a KB article.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function kb_helpful() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$article_id = absint( $_POST['article_id'] ?? 0 );
		$helpful    = rest_sanitize_boolean( $_POST['helpful'] ?? true );

		if ( ! $article_id ) {
			wp_send_json_error( array( 'message' => __( 'Article ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$kb     = new MHD_Knowledge_Base();
		$result = $kb->record_helpful( $article_id, $helpful );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not record vote.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// Agent management
	// ------------------------------------------------------------------

	/**
	 * Create or update an agent.
	 *
	 * Restricted to users with the manage_options capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function save_agent() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$agent_id      = absint( $_POST['agent_id'] ?? 0 );
		$user_id       = absint( $_POST['user_id'] ?? 0 );
		$department_id = absint( $_POST['department_id'] ?? 0 );
		$display_name  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );

		$data = array(
			'user_id'       => $user_id,
			'department_id' => $department_id,
			'display_name'  => $display_name,
		);

		$agents = new MHD_Agents();

		if ( $agent_id ) {
			$result = $agents->update_agent( $agent_id, $data );
		} else {
			$result = $agents->create_agent( $data );
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save agent.', 'myhelpdesk-chat' ) ) );
		}

		$id = $agent_id ? $agent_id : $result;

		wp_send_json_success( array( 'agent' => $agents->get_agent( $id ) ) );
	}

	/**
	 * Delete an agent.
	 *
	 * Restricted to users with the manage_options capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function delete_agent() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$agent_id = absint( $_POST['agent_id'] ?? 0 );

		if ( ! $agent_id ) {
			wp_send_json_error( array( 'message' => __( 'Agent ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$agents  = new MHD_Agents();
		$deleted = $agents->delete_agent( $agent_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete agent.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * Set the current agent's online/offline status.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function set_agent_status() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

		if ( ! in_array( $status, array( 'online', 'offline', 'away' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'myhelpdesk-chat' ) ) );
		}

		$agents = new MHD_Agents();
		$result = $agents->set_status( get_current_user_id(), $status );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not update status.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success( array( 'status' => $status ) );
	}

	// ------------------------------------------------------------------
	// Department management
	// ------------------------------------------------------------------

	/**
	 * Create or update a department.
	 *
	 * Restricted to users with the manage_options capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function save_department() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$department_id = absint( $_POST['department_id'] ?? 0 );
		$name          = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$description   = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Department name is required.', 'myhelpdesk-chat' ) ) );
		}

		$data = array(
			'name'        => $name,
			'description' => $description,
		);

		$departments = new MHD_Departments();

		if ( $department_id ) {
			$result = $departments->update_department( $department_id, $data );
		} else {
			$result = $departments->create_department( $data );
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save department.', 'myhelpdesk-chat' ) ) );
		}

		$id = $department_id ? $department_id : $result;

		wp_send_json_success( array( 'department' => $departments->get_department( $id ) ) );
	}

	/**
	 * Delete a department.
	 *
	 * Restricted to users with the manage_options capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function delete_department() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$department_id = absint( $_POST['department_id'] ?? 0 );

		if ( ! $department_id ) {
			wp_send_json_error( array( 'message' => __( 'Department ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$departments = new MHD_Departments();
		$deleted     = $departments->delete_department( $department_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete department.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// Saved replies
	// ------------------------------------------------------------------

	/**
	 * Create or update a saved reply.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function save_reply() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$reply_id = absint( $_POST['reply_id'] ?? 0 );
		$name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$message  = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );

		if ( empty( $name ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Name and message are required.', 'myhelpdesk-chat' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'mhd_saved_replies';

		if ( $reply_id ) {
			$result = $wpdb->update(
				$table,
				array(
					'name'     => $name,
					'message'  => $message,
					'category' => $category,
				),
				array( 'id' => $reply_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert(
				$table,
				array(
					'name'       => $name,
					'message'    => $message,
					'agent_id'   => get_current_user_id(),
					'category'   => $category,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s' )
			);
		}

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save reply.', 'myhelpdesk-chat' ) ) );
		}

		$id  = $reply_id ? $reply_id : $wpdb->insert_id;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		wp_send_json_success( array( 'reply' => $row ) );
	}

	/**
	 * Delete a saved reply.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function delete_reply() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$reply_id = absint( $_POST['reply_id'] ?? 0 );

		if ( ! $reply_id ) {
			wp_send_json_error( array( 'message' => __( 'Reply ID is required.', 'myhelpdesk-chat' ) ) );
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'mhd_saved_replies';
		$deleted = $wpdb->delete( $table, array( 'id' => $reply_id ), array( '%d' ) );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete reply.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * Get saved replies, optionally filtered by a search term.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_saved_replies() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

		global $wpdb;
		$table = $wpdb->prefix . 'mhd_saved_replies';

		if ( ! empty( $search ) ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$replies = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE name LIKE %s OR message LIKE %s ORDER BY name ASC",
					$like,
					$like
				)
			);
		} else {
			$replies = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
		}

		wp_send_json_success( array( 'replies' => $replies ) );
	}

	// ------------------------------------------------------------------
	// Automation management
	// ------------------------------------------------------------------

	/**
	 * Create or update an automation rule.
	 *
	 * Restricted to users with the manage_options capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function save_automation() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$automation_id = absint( $_POST['automation_id'] ?? 0 );
		$name          = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$trigger       = sanitize_text_field( wp_unslash( $_POST['trigger'] ?? '' ) );
		$conditions    = sanitize_text_field( wp_unslash( $_POST['conditions'] ?? '' ) );
		$actions       = sanitize_text_field( wp_unslash( $_POST['actions'] ?? '' ) );
		$is_active     = absint( $_POST['is_active'] ?? 1 );

		if ( empty( $name ) || empty( $trigger ) ) {
			wp_send_json_error( array( 'message' => __( 'Name and trigger are required.', 'myhelpdesk-chat' ) ) );
		}

		$data = array(
			'name'       => $name,
			'trigger'    => $trigger,
			'conditions' => $conditions,
			'actions'    => $actions,
			'is_active'  => $is_active,
		);

		$automations = new MHD_Automations();

		if ( $automation_id ) {
			$result = $automations->update_automation( $automation_id, $data );
		} else {
			$result = $automations->create_automation( $data );
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save automation.', 'myhelpdesk-chat' ) ) );
		}

		$id = $automation_id ? $automation_id : $result;

		wp_send_json_success( array( 'automation' => $automations->get_automation( $id ) ) );
	}

	/**
	 * Delete an automation rule.
	 *
	 * Restricted to users with the manage_options capability.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function delete_automation() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$automation_id = absint( $_POST['automation_id'] ?? 0 );

		if ( ! $automation_id ) {
			wp_send_json_error( array( 'message' => __( 'Automation ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$automations = new MHD_Automations();
		$deleted     = $automations->delete_automation( $automation_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete automation.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// Knowledge Base management
	// ------------------------------------------------------------------

	/**
	 * Create or update a knowledge base category.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function save_kb_category() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$category_id = absint( $_POST['category_id'] ?? 0 );
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );
		$parent_id   = absint( $_POST['parent_id'] ?? 0 );

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Category name is required.', 'myhelpdesk-chat' ) ) );
		}

		$data = array(
			'name'        => $name,
			'description' => $description,
			'parent_id'   => $parent_id,
		);

		$kb = new MHD_Knowledge_Base();

		if ( $category_id ) {
			$result = $kb->update_category( $category_id, $data );
		} else {
			$result = $kb->create_category( $data );
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save category.', 'myhelpdesk-chat' ) ) );
		}

		$id = $category_id ? $category_id : $result;

		wp_send_json_success( array( 'category' => $kb->get_category( $id ) ) );
	}

	/**
	 * Delete a knowledge base category.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function delete_kb_category() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$category_id = absint( $_POST['category_id'] ?? 0 );

		if ( ! $category_id ) {
			wp_send_json_error( array( 'message' => __( 'Category ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$kb      = new MHD_Knowledge_Base();
		$deleted = $kb->delete_category( $category_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete category.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * Create or update a knowledge base article.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function save_kb_article() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$article_id  = absint( $_POST['article_id'] ?? 0 );
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$content     = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
		$category_id = absint( $_POST['category_id'] ?? 0 );
		$status      = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) );

		if ( empty( $title ) || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Title and content are required.', 'myhelpdesk-chat' ) ) );
		}

		$data = array(
			'title'       => $title,
			'content'     => $content,
			'category_id' => $category_id,
			'status'      => $status,
			'author_id'   => get_current_user_id(),
		);

		$kb = new MHD_Knowledge_Base();

		if ( $article_id ) {
			$result = $kb->update_article( $article_id, $data );
		} else {
			$result = $kb->create_article( $data );
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save article.', 'myhelpdesk-chat' ) ) );
		}

		$id = $article_id ? $article_id : $result;

		wp_send_json_success( array( 'article' => $kb->get_article( $id ) ) );
	}

	/**
	 * Delete a knowledge base article.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function delete_kb_article() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$article_id = absint( $_POST['article_id'] ?? 0 );

		if ( ! $article_id ) {
			wp_send_json_error( array( 'message' => __( 'Article ID is required.', 'myhelpdesk-chat' ) ) );
		}

		$kb      = new MHD_Knowledge_Base();
		$deleted = $kb->delete_article( $article_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete article.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// Reports
	// ------------------------------------------------------------------

	/**
	 * Return report data based on the requested report type and date range.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function get_report_data() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$report_type = sanitize_text_field( wp_unslash( $_POST['report_type'] ?? 'overview' ) );
		$date_from   = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
		$date_to     = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );

		if ( empty( $date_from ) || empty( $date_to ) ) {
			$date_to   = current_time( 'Y-m-d' );
			$date_from = gmdate( 'Y-m-d', strtotime( '-30 days', strtotime( $date_to ) ) );
		}

		$reports = new MHD_Reports();

		switch ( $report_type ) {
			case 'conversations_per_day':
				$data = $reports->get_conversations_per_day( $date_from, $date_to );
				break;

			case 'conversations_by_source':
				$data = $reports->get_conversations_by_source( $date_from, $date_to );
				break;

			case 'tickets_by_status':
				$data = $reports->get_tickets_by_status();
				break;

			case 'agent_performance':
				$data = $reports->get_agent_performance( $date_from, $date_to );
				break;

			default:
				$data = $reports->get_overview( $date_from, $date_to );
				break;
		}

		wp_send_json_success( array( 'report' => $data ) );
	}

	/**
	 * Generate and return a CSV download of conversation data.
	 *
	 * @since 1.0.0
	 * @return void Outputs CSV headers and content, then dies.
	 */
	public function export_csv() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
		$date_to   = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );
		$status    = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

		$args = array();
		if ( ! empty( $date_from ) ) {
			$args['date_from'] = $date_from;
		}
		if ( ! empty( $date_to ) ) {
			$args['date_to'] = $date_to;
		}
		if ( ! empty( $status ) ) {
			$args['status'] = $status;
		}

		$reports = new MHD_Reports();
		$csv     = $reports->export_conversations_csv( $args );

		$filename = 'mhd-conversations-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV binary output.
		wp_die();
	}

	// ------------------------------------------------------------------
	// Direct message
	// ------------------------------------------------------------------

	/**
	 * Send a direct message by creating a new conversation with source 'direct'.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function send_direct_message() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		if ( ! current_user_can( 'mhd_agent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'myhelpdesk-chat' ) ) );
		}

		$user_email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
		$user_name  = sanitize_text_field( wp_unslash( $_POST['user_name'] ?? '' ) );
		$subject    = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$message    = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );

		if ( empty( $user_email ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Email and message are required.', 'myhelpdesk-chat' ) ) );
		}

		$conversations   = new MHD_Conversations();
		$conversation_id = $conversations->create_conversation( array(
			'user_email' => $user_email,
			'user_name'  => $user_name,
			'subject'    => $subject,
			'agent_id'   => get_current_user_id(),
			'source'     => 'direct',
			'status'     => 'open',
		) );

		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create conversation.', 'myhelpdesk-chat' ) ) );
		}

		$messages_model = new MHD_Messages();
		$message_id     = $messages_model->create_message( array(
			'conversation_id' => $conversation_id,
			'agent_id'        => get_current_user_id(),
			'message'         => $message,
			'message_type'    => 'text',
		) );

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not send message.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success( array(
			'conversation_id' => $conversation_id,
			'message_id'      => $message_id,
		) );
	}

	// ------------------------------------------------------------------
	// Offline form
	// ------------------------------------------------------------------

	/**
	 * Handle an offline contact form submission.
	 *
	 * Creates a support ticket from the submitted data when no agents
	 * are available online.
	 *
	 * @since 1.0.0
	 * @return void Sends JSON response and dies.
	 */
	public function offline_form() {
		check_ajax_referer( 'mhd_nonce', 'nonce' );

		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$message = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );

		if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'myhelpdesk-chat' ) ) );
		}

		$tickets   = new MHD_Tickets();
		$ticket_id = $tickets->create_ticket( array(
			'subject'  => sprintf(
				/* translators: %s: visitor name */
				__( 'Offline message from %s', 'myhelpdesk-chat' ),
				$name
			),
			'message'  => $message,
			'email'    => $email,
			'priority' => 'normal',
		) );

		if ( ! $ticket_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not submit form.', 'myhelpdesk-chat' ) ) );
		}

		wp_send_json_success( array(
			'message'   => __( 'Your message has been received. We will get back to you soon.', 'myhelpdesk-chat' ),
			'ticket_id' => $ticket_id,
		) );
	}
}
