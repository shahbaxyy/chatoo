<?php
/**
 * Messages CRUD class.
 *
 * Handles all database operations for the messages table.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Messages
 *
 * Provides create, read, update, and delete operations for messages
 * stored in the {prefix}mhd_messages table.
 *
 * @since 1.0.0
 */
class MHD_Messages {

	/**
	 * Full database table name including WP prefix.
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
	 * Constructor.
	 *
	 * Sets up the table names used by this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table               = $wpdb->prefix . MHD_TABLE_PREFIX . 'messages';
		$this->conversations_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'conversations';
	}

	/**
	 * Retrieve messages for a given conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $conversation_id The conversation ID to fetch messages for.
	 * @param array $args {
	 *     Optional. Arguments to filter and paginate messages.
	 *
	 *     @type int $since_id Return only messages with an ID greater than this value.
	 *     @type int $limit    Maximum number of messages to return. Default 50.
	 *     @type int $offset   Number of messages to skip. Default 0.
	 * }
	 * @return array Array of message row objects.
	 */
	public function get_messages( $conversation_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'since_id' => 0,
			'limit'    => 50,
			'offset'   => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( 'conversation_id = %d' );
		$values = array( (int) $conversation_id );

		if ( ! empty( $args['since_id'] ) ) {
			$where[]  = 'id > %d';
			$values[] = (int) $args['since_id'];
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		$limit  = max( 1, (int) $args['limit'] );
		$offset = max( 0, (int) $args['offset'] );

		$sql      = "SELECT * FROM {$this->table} {$where_clause} ORDER BY id ASC LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get a single message by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Message ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_message( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Create a new message and update the parent conversation timestamp.
	 *
	 * Fires the `mhd_new_message` action after successful insertion.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Message data.
	 *
	 *     @type int    $conversation_id Parent conversation ID.
	 *     @type int    $user_id         WordPress user ID of the sender (visitor).
	 *     @type int    $agent_id        Agent ID if the sender is an agent.
	 *     @type string $message         Message body content.
	 *     @type string $attachments     JSON-encoded string of attachment data.
	 *     @type string $message_type    Type of message: text, image, file, system, note, rich, or email. Default 'text'.
	 *     @type int    $is_read         Whether the message has been read. Default 0.
	 * }
	 * @return int|false The new message ID on success, false on failure.
	 */
	public function create_message( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'conversation_id' => 0,
			'user_id'         => null,
			'agent_id'        => null,
			'message'         => '',
			'attachments'     => '',
			'message_type'    => 'text',
			'is_read'         => 0,
			'created_at'      => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamp.
		$data['created_at'] = $now;

		$format = array(
			'%d', // conversation_id
			'%d', // user_id
			'%d', // agent_id
			'%s', // message
			'%s', // attachments
			'%s', // message_type
			'%d', // is_read
			'%s', // created_at
		);

		$result = $wpdb->insert( $this->table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		$message_id      = (int) $wpdb->insert_id;
		$conversation_id = (int) $data['conversation_id'];

		// Update the parent conversation's updated_at timestamp.
		$wpdb->update(
			$this->conversations_table,
			array( 'updated_at' => $now ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a new message is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int $message_id      The newly created message ID.
		 * @param int $conversation_id The parent conversation ID.
		 */
		do_action( 'mhd_new_message', $message_id, $conversation_id );

		return $message_id;
	}

	/**
	 * Mark all messages in a conversation as read for a given reader type.
	 *
	 * When the reader is an agent, all messages sent by the user (user_id IS NOT NULL
	 * and agent_id IS NULL) are marked as read. When the reader is a user, all messages
	 * sent by an agent (agent_id IS NOT NULL and user_id IS NULL) are marked as read.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $conversation_id The conversation ID.
	 * @param string $reader_type     Who is reading: 'agent' or 'user'.
	 * @return int|false The number of rows updated, or false on failure.
	 */
	public function mark_as_read( $conversation_id, $reader_type ) {
		global $wpdb;

		if ( 'agent' === $reader_type ) {
			// Agent is reading — mark user messages as read.
			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->table} SET is_read = 1 WHERE conversation_id = %d AND user_id IS NOT NULL AND agent_id IS NULL AND is_read = 0",
					(int) $conversation_id
				)
			);
		}

		if ( 'user' === $reader_type ) {
			// User is reading — mark agent messages as read.
			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->table} SET is_read = 1 WHERE conversation_id = %d AND agent_id IS NOT NULL AND user_id IS NULL AND is_read = 0",
					(int) $conversation_id
				)
			);
		}

		return false;
	}

	/**
	 * Count unread messages in a conversation for a given reader type.
	 *
	 * When the reader is an agent, counts unread messages sent by the user.
	 * When the reader is a user, counts unread messages sent by an agent.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $conversation_id The conversation ID.
	 * @param string $reader_type     Who is reading: 'agent' or 'user'.
	 * @return int Number of unread messages, or 0 if reader_type is invalid.
	 */
	public function get_unread_count( $conversation_id, $reader_type ) {
		global $wpdb;

		if ( 'agent' === $reader_type ) {
			// Count unread user messages.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE conversation_id = %d AND user_id IS NOT NULL AND agent_id IS NULL AND is_read = 0",
					(int) $conversation_id
				)
			);
		}

		if ( 'user' === $reader_type ) {
			// Count unread agent messages.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE conversation_id = %d AND agent_id IS NOT NULL AND user_id IS NULL AND is_read = 0",
					(int) $conversation_id
				)
			);
		}

		return 0;
	}

	/**
	 * Delete all messages belonging to a conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $conversation_id The conversation ID whose messages should be deleted.
	 * @return int|false The number of rows deleted, or false on failure.
	 */
	public function delete_messages( $conversation_id ) {
		global $wpdb;

		return $wpdb->delete(
			$this->table,
			array( 'conversation_id' => (int) $conversation_id ),
			array( '%d' )
		);
	}
}
