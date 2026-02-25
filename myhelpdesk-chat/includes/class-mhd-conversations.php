<?php
/**
 * Conversations CRUD class.
 *
 * Handles all database operations for the conversations table.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Conversations
 *
 * Provides create, read, update, and delete operations for conversations
 * stored in the {prefix}mhd_conversations table.
 *
 * @since 1.0.0
 */
class MHD_Conversations {

	/**
	 * Full database table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table;

	/**
	 * Messages table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $messages_table;

	/**
	 * Constructor.
	 *
	 * Sets up the table names used by this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table          = $wpdb->prefix . MHD_TABLE_PREFIX . 'conversations';
		$this->messages_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'messages';
	}

	/**
	 * Retrieve a list of conversations with optional filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Arguments to filter, sort, and paginate conversations.
	 *
	 *     @type string $status        Filter by status (open, pending, resolved, archived).
	 *     @type int    $department_id Filter by department ID.
	 *     @type int    $agent_id      Filter by agent ID.
	 *     @type string $source        Filter by source (chat, email, ticket, etc.).
	 *     @type string $date_from     Filter conversations created on or after this date (Y-m-d H:i:s).
	 *     @type string $date_to       Filter conversations created on or before this date (Y-m-d H:i:s).
	 *     @type string $search        Search term matched against subject, user_name, and user_email.
	 *     @type string $orderby       Column to sort by. Default 'updated_at'.
	 *     @type string $order         Sort direction, ASC or DESC. Default 'DESC'.
	 *     @type int    $per_page      Number of results per page. Default 20.
	 *     @type int    $page          Page number (1-based). Default 1.
	 * }
	 * @return array {
	 *     @type array $conversations Array of conversation row objects.
	 *     @type int   $total         Total number of matching conversations.
	 * }
	 */
	public function get_conversations( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'        => '',
			'department_id' => 0,
			'agent_id'      => 0,
			'source'        => '',
			'date_from'     => '',
			'date_to'       => '',
			'search'        => '',
			'orderby'       => 'updated_at',
			'order'         => 'DESC',
			'per_page'      => 20,
			'page'          => 1,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array();
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['department_id'] ) ) {
			$where[]  = 'department_id = %d';
			$values[] = (int) $args['department_id'];
		}

		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'agent_id = %d';
			$values[] = (int) $args['agent_id'];
		}

		if ( ! empty( $args['source'] ) ) {
			$where[]  = 'source = %s';
			$values[] = $args['source'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(subject LIKE %s OR user_name LIKE %s OR user_email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		$allowed_orderby = array( 'id', 'status', 'source', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		// Count total matching rows.
		$count_sql = "SELECT COUNT(*) FROM {$this->table} {$where_clause}";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Fetch paginated rows.
		$sql     = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[] = $per_page;
		$values[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'conversations' => $results,
			'total'         => $total,
		);
	}

	/**
	 * Get a single conversation by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Conversation ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_conversation( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Create a new conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Conversation data.
	 *
	 *     @type int    $user_id       WordPress user ID of the visitor.
	 *     @type int    $agent_id      Assigned agent ID.
	 *     @type int    $department_id Department ID.
	 *     @type string $status        Conversation status. Default 'open'.
	 *     @type string $subject       Conversation subject.
	 *     @type string $source        Conversation source. Default 'chat'.
	 *     @type string $user_email    Visitor email address.
	 *     @type string $user_name     Visitor display name.
	 *     @type string $user_ip       Visitor IP address.
	 *     @type string $user_browser  Visitor browser user-agent string.
	 *     @type string $user_location Visitor geographic location.
	 *     @type string $current_page  URL the visitor was on when starting the conversation.
	 *     @type string $tags          Comma-separated tags.
	 *     @type string $extra_data    JSON-encoded extra metadata.
	 * }
	 * @return int|false The new conversation ID on success, false on failure.
	 */
	public function create_conversation( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'user_id'       => null,
			'agent_id'      => null,
			'department_id' => null,
			'status'        => 'open',
			'subject'       => '',
			'source'        => 'chat',
			'user_email'    => '',
			'user_name'     => '',
			'user_ip'       => '',
			'user_browser'  => '',
			'user_location' => '',
			'current_page'  => '',
			'tags'          => '',
			'extra_data'    => '',
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamps.
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$format = array(
			'%d', // user_id
			'%d', // agent_id
			'%d', // department_id
			'%s', // status
			'%s', // subject
			'%s', // source
			'%s', // user_email
			'%s', // user_name
			'%s', // user_ip
			'%s', // user_browser
			'%s', // user_location
			'%s', // current_page
			'%s', // tags
			'%s', // extra_data
			'%s', // created_at
			'%s', // updated_at
		);

		$result = $wpdb->insert( $this->table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Conversation ID.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_conversation( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$format = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'user_id', 'agent_id', 'department_id' ), true ) ) {
				$format[] = '%d';
			} else {
				$format[] = '%s';
			}
		}

		$result = $wpdb->update(
			$this->table,
			$data,
			array( 'id' => (int) $id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a conversation and all of its associated messages.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Conversation ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_conversation( $id ) {
		global $wpdb;

		// Delete associated messages first.
		$wpdb->delete(
			$this->messages_table,
			array( 'conversation_id' => (int) $id ),
			array( '%d' )
		);

		$result = $wpdb->delete(
			$this->table,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Count conversations, optionally filtered by status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Optional. Filter by conversation status. Default empty (all statuses).
	 * @return int Number of matching conversations.
	 */
	public function get_conversations_count( $status = '' ) {
		global $wpdb;

		if ( ! empty( $status ) ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
					$status
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get all conversations for a specific user by email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_email The user email address to search for.
	 * @return array Array of conversation row objects.
	 */
	public function get_user_conversations( $user_email ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_email = %s ORDER BY updated_at DESC",
				$user_email
			)
		);
	}

	/**
	 * Assign a conversation to an agent.
	 *
	 * Updates the agent_id and sets the updated_at timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id       Conversation ID.
	 * @param int $agent_id Agent ID to assign.
	 * @return bool True on success, false on failure.
	 */
	public function assign_conversation( $id, $agent_id ) {
		return $this->update_conversation( (int) $id, array( 'agent_id' => (int) $agent_id ) );
	}
}
