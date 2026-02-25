<?php
/**
 * Tickets CRUD class.
 *
 * Handles all database operations for the tickets and ticket replies tables.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Tickets
 *
 * Provides create, read, update, and delete operations for tickets
 * stored in the {prefix}mhd_tickets table and their replies stored
 * in the {prefix}mhd_ticket_replies table.
 *
 * @since 1.0.0
 */
class MHD_Tickets {

	/**
	 * Full database table name for tickets including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table;

	/**
	 * Full database table name for ticket replies including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $replies_table;

	/**
	 * Constructor.
	 *
	 * Sets up the table names used by this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table         = $wpdb->prefix . MHD_TABLE_PREFIX . 'tickets';
		$this->replies_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'ticket_replies';
	}

	/**
	 * Retrieve a list of tickets with optional filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Arguments to filter, sort, and paginate tickets.
	 *
	 *     @type string $status            Filter by status.
	 *     @type string $priority          Filter by priority.
	 *     @type int    $assigned_agent_id Filter by assigned agent ID.
	 *     @type int    $department_id     Filter by department ID.
	 *     @type string $search            Search term matched against subject and user_email.
	 *     @type string $orderby           Column to sort by. Default 'updated_at'.
	 *     @type string $order             Sort direction, ASC or DESC. Default 'DESC'.
	 *     @type int    $per_page          Number of results per page. Default 20.
	 *     @type int    $page              Page number (1-based). Default 1.
	 * }
	 * @return array {
	 *     @type array $tickets Array of ticket row objects.
	 *     @type int   $total   Total number of matching tickets.
	 * }
	 */
	public function get_tickets( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'            => '',
			'priority'          => '',
			'assigned_agent_id' => 0,
			'department_id'     => 0,
			'search'            => '',
			'orderby'           => 'updated_at',
			'order'             => 'DESC',
			'per_page'          => 20,
			'page'              => 1,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array();
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['priority'] ) ) {
			$where[]  = 'priority = %s';
			$values[] = $args['priority'];
		}

		if ( ! empty( $args['assigned_agent_id'] ) ) {
			$where[]  = 'assigned_agent_id = %d';
			$values[] = (int) $args['assigned_agent_id'];
		}

		if ( ! empty( $args['department_id'] ) ) {
			$where[]  = 'department_id = %d';
			$values[] = (int) $args['department_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(subject LIKE %s OR user_email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		$allowed_orderby = array( 'id', 'status', 'priority', 'created_at', 'updated_at' );
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
		$sql       = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[]  = $per_page;
		$values[]  = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'tickets' => $results,
			'total'   => $total,
		);
	}

	/**
	 * Get a single ticket by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Ticket ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_ticket( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Create a new ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Ticket data.
	 *
	 *     @type int    $conversation_id   Associated conversation ID.
	 *     @type int    $user_id           WordPress user ID.
	 *     @type string $user_email        User email address.
	 *     @type string $subject           Ticket subject.
	 *     @type string $status            Ticket status. Default 'open'.
	 *     @type string $priority          Ticket priority. Default 'normal'.
	 *     @type int    $assigned_agent_id Assigned agent ID.
	 *     @type int    $department_id     Department ID.
	 *     @type string $tags              Comma-separated tags.
	 * }
	 * @return int|false The new ticket ID on success, false on failure.
	 */
	public function create_ticket( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'conversation_id'   => null,
			'user_id'           => null,
			'user_email'        => '',
			'subject'           => '',
			'status'            => 'open',
			'priority'          => 'normal',
			'assigned_agent_id' => null,
			'department_id'     => null,
			'tags'              => '',
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamps.
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$format = array(
			'%d', // conversation_id
			'%d', // user_id
			'%s', // user_email
			'%s', // subject
			'%s', // status
			'%s', // priority
			'%d', // assigned_agent_id
			'%d', // department_id
			'%s', // tags
			'%s', // created_at
			'%s', // updated_at
		);

		$result = $wpdb->insert( $this->table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		$ticket_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a new ticket is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int $ticket_id The newly created ticket ID.
		 */
		do_action( 'mhd_new_ticket', $ticket_id );

		return $ticket_id;
	}

	/**
	 * Update an existing ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Ticket ID.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_ticket( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$format = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'conversation_id', 'user_id', 'assigned_agent_id', 'department_id' ), true ) ) {
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
	 * Delete a ticket and all of its associated replies.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Ticket ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_ticket( $id ) {
		global $wpdb;

		// Delete associated replies first.
		$wpdb->delete(
			$this->replies_table,
			array( 'ticket_id' => (int) $id ),
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
	 * Get all replies for a specific ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return array Array of reply row objects ordered by created_at ascending.
	 */
	public function get_ticket_replies( $ticket_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->replies_table} WHERE ticket_id = %d ORDER BY created_at ASC",
				(int) $ticket_id
			)
		);
	}

	/**
	 * Add a reply to a ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Reply data.
	 *
	 *     @type int    $ticket_id   Ticket ID the reply belongs to.
	 *     @type int    $user_id     WordPress user ID of the reply author.
	 *     @type int    $agent_id    Agent ID if the reply is from an agent.
	 *     @type string $message     Reply message content.
	 *     @type string $attachments JSON-encoded attachments data.
	 *     @type int    $is_note     Whether the reply is an internal note. Default 0.
	 * }
	 * @return int|false The new reply ID on success, false on failure.
	 */
	public function add_ticket_reply( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'ticket_id'   => 0,
			'user_id'     => null,
			'agent_id'    => null,
			'message'     => '',
			'attachments' => '',
			'is_note'     => 0,
			'created_at'  => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamp.
		$data['created_at'] = $now;

		$format = array(
			'%d', // ticket_id
			'%d', // user_id
			'%d', // agent_id
			'%s', // message
			'%s', // attachments
			'%d', // is_note
			'%s', // created_at
		);

		$result = $wpdb->insert( $this->replies_table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		$reply_id  = (int) $wpdb->insert_id;
		$ticket_id = (int) $data['ticket_id'];

		// Update the parent ticket's updated_at timestamp.
		$this->update_ticket( $ticket_id, array() );

		/**
		 * Fires after a new ticket reply is added.
		 *
		 * @since 1.0.0
		 *
		 * @param int $reply_id  The newly created reply ID.
		 * @param int $ticket_id The ticket ID the reply belongs to.
		 */
		do_action( 'mhd_ticket_reply', $reply_id, $ticket_id );

		return $reply_id;
	}

	/**
	 * Get all tickets for a specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Array of ticket row objects ordered by updated_at descending.
	 */
	public function get_user_tickets( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY updated_at DESC",
				(int) $user_id
			)
		);
	}

	/**
	 * Count tickets, optionally filtered by status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Optional. Filter by ticket status. Default empty (all statuses).
	 * @return int Number of matching tickets.
	 */
	public function get_tickets_count( $status = '' ) {
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
}
