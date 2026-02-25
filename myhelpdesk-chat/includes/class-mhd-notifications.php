<?php
/**
 * Notifications class.
 *
 * Handles all database operations for the notifications table.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Notifications
 *
 * Manages notification records stored in the {prefix}mhd_notifications table.
 * Creates notifications for agents when conversations, messages, tickets, or
 * ticket replies occur.
 *
 * @since 1.0.0
 */
class MHD_Notifications {

	/**
	 * Full database table name for notifications including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table;

	/**
	 * Conversations table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $conversations_table;

	/**
	 * Messages table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $messages_table;

	/**
	 * Tickets table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $tickets_table;

	/**
	 * Agents table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $agents_table;

	/**
	 * Constructor.
	 *
	 * Sets up the table names used by this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table               = $wpdb->prefix . MHD_TABLE_PREFIX . 'notifications';
		$this->conversations_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'conversations';
		$this->messages_table      = $wpdb->prefix . MHD_TABLE_PREFIX . 'messages';
		$this->tickets_table       = $wpdb->prefix . MHD_TABLE_PREFIX . 'tickets';
		$this->agents_table        = $wpdb->prefix . MHD_TABLE_PREFIX . 'agents';
	}

	/**
	 * Create notifications for relevant agents when a new conversation starts.
	 *
	 * If the conversation is assigned to an agent, only that agent is notified.
	 * If the conversation belongs to a department, all online agents in that
	 * department are notified. Otherwise all online agents are notified.
	 *
	 * @since 1.0.0
	 *
	 * @param int $conversation_id The new conversation ID.
	 * @return void
	 */
	public function on_new_conversation( $conversation_id ) {
		global $wpdb;

		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->conversations_table} WHERE id = %d",
				(int) $conversation_id
			)
		);

		if ( ! $conversation ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: visitor name or email */
			__( 'New conversation from %s', 'myhelpdesk-chat' ),
			! empty( $conversation->user_name ) ? $conversation->user_name : $conversation->user_email
		);

		if ( ! empty( $conversation->agent_id ) ) {
			$this->create_notification( array(
				'agent_id'        => (int) $conversation->agent_id,
				'conversation_id' => (int) $conversation_id,
				'type'            => 'new_chat',
				'message'         => $message,
			) );
			return;
		}

		$where  = array( 'a.is_online = 1' );
		$values = array();

		if ( ! empty( $conversation->department_id ) ) {
			$where[]  = 'a.department_id = %d';
			$values[] = (int) $conversation->department_id;
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		$sql = "SELECT a.id FROM {$this->agents_table} AS a {$where_clause}";

		if ( ! empty( $values ) ) {
			$agents = $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$agents = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( $agents ) {
			foreach ( $agents as $agent ) {
				$this->create_notification( array(
					'agent_id'        => (int) $agent->id,
					'conversation_id' => (int) $conversation_id,
					'type'            => 'new_chat',
					'message'         => $message,
				) );
			}
		}
	}

	/**
	 * Create a notification for the assigned agent when a new message arrives.
	 *
	 * Only creates a notification when the message is from a user (not an agent).
	 *
	 * @since 1.0.0
	 *
	 * @param int $message_id      The new message ID.
	 * @param int $conversation_id The parent conversation ID.
	 * @return void
	 */
	public function on_new_message( $message_id, $conversation_id ) {
		global $wpdb;

		$msg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE id = %d",
				(int) $message_id
			)
		);

		// Only notify when the message is from a user, not from an agent.
		if ( ! $msg || ! empty( $msg->agent_id ) ) {
			return;
		}

		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->conversations_table} WHERE id = %d",
				(int) $conversation_id
			)
		);

		if ( ! $conversation || empty( $conversation->agent_id ) ) {
			return;
		}

		$preview = wp_trim_words( $msg->message, 10, '...' );

		$this->create_notification( array(
			'agent_id'        => (int) $conversation->agent_id,
			'conversation_id' => (int) $conversation_id,
			'type'            => 'new_message',
			'message'         => sprintf(
				/* translators: %s: message preview */
				__( 'New message: %s', 'myhelpdesk-chat' ),
				$preview
			),
		) );
	}

	/**
	 * Create a notification for the assigned agent when a new ticket is created.
	 *
	 * @since 1.0.0
	 *
	 * @param int $ticket_id The new ticket ID.
	 * @return void
	 */
	public function on_new_ticket( $ticket_id ) {
		global $wpdb;

		$ticket = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tickets_table} WHERE id = %d",
				(int) $ticket_id
			)
		);

		if ( ! $ticket || empty( $ticket->assigned_agent_id ) ) {
			return;
		}

		$this->create_notification( array(
			'agent_id'  => (int) $ticket->assigned_agent_id,
			'ticket_id' => (int) $ticket_id,
			'type'      => 'new_ticket',
			'message'   => sprintf(
				/* translators: %s: ticket subject */
				__( 'New ticket: %s', 'myhelpdesk-chat' ),
				$ticket->subject
			),
		) );
	}

	/**
	 * Create a notification for the assigned agent when a ticket reply is added.
	 *
	 * Only creates a notification when the reply is from a user (not an agent).
	 *
	 * @since 1.0.0
	 *
	 * @param int $reply_id  The new reply ID.
	 * @param int $ticket_id The parent ticket ID.
	 * @return void
	 */
	public function on_ticket_reply( $reply_id, $ticket_id ) {
		global $wpdb;

		$ticket_replies_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'ticket_replies';

		$reply = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$ticket_replies_table} WHERE id = %d",
				(int) $reply_id
			)
		);

		// Only notify when the reply is from a user, not from an agent.
		if ( ! $reply || ! empty( $reply->agent_id ) ) {
			return;
		}

		$ticket = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tickets_table} WHERE id = %d",
				(int) $ticket_id
			)
		);

		if ( ! $ticket || empty( $ticket->assigned_agent_id ) ) {
			return;
		}

		$this->create_notification( array(
			'agent_id'  => (int) $ticket->assigned_agent_id,
			'ticket_id' => (int) $ticket_id,
			'type'      => 'ticket_reply',
			'message'   => sprintf(
				/* translators: %s: ticket subject */
				__( 'New reply on ticket: %s', 'myhelpdesk-chat' ),
				$ticket->subject
			),
		) );
	}

	/**
	 * Retrieve notifications for a specific agent.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_id The agent ID.
	 * @param int $limit    Maximum number of notifications to return. Default 20.
	 * @return array Array of notification row objects ordered by created_at descending.
	 */
	public function get_notifications( $agent_id, $limit = 20 ) {
		global $wpdb;

		$limit = max( 1, (int) $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE agent_id = %d ORDER BY created_at DESC LIMIT %d",
				(int) $agent_id,
				$limit
			)
		);
	}

	/**
	 * Count unread notifications for an agent.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_id The agent ID.
	 * @return int Number of unread notifications.
	 */
	public function get_unread_count( $agent_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE agent_id = %d AND is_read = 0",
				(int) $agent_id
			)
		);
	}

	/**
	 * Mark all notifications as read for an agent.
	 *
	 * @since 1.0.0
	 *
	 * @param int $agent_id The agent ID.
	 * @return int|false The number of rows updated, or false on failure.
	 */
	public function mark_as_read( $agent_id ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET is_read = 1 WHERE agent_id = %d AND is_read = 0",
				(int) $agent_id
			)
		);
	}

	/**
	 * Insert a notification record into the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Notification data.
	 *
	 *     @type int    $agent_id        The agent to notify.
	 *     @type int    $conversation_id Associated conversation ID. Optional.
	 *     @type int    $ticket_id       Associated ticket ID. Optional.
	 *     @type string $type            Notification type: new_chat, new_message, new_ticket, ticket_reply, assigned.
	 *     @type string $message         Human-readable notification message.
	 * }
	 * @return int|false The new notification ID on success, false on failure.
	 */
	public function create_notification( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'agent_id'        => 0,
			'conversation_id' => null,
			'ticket_id'       => null,
			'type'            => 'new_message',
			'message'         => '',
			'is_read'         => 0,
			'created_at'      => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamp.
		$data['created_at'] = $now;

		$format = array(
			'%d', // agent_id
			'%d', // conversation_id
			'%d', // ticket_id
			'%s', // type
			'%s', // message
			'%d', // is_read
			'%s', // created_at
		);

		$result = $wpdb->insert( $this->table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}
}
