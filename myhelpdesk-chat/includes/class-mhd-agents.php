<?php
/**
 * Agents CRUD class.
 *
 * Handles all database operations for the agents table.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Agents
 *
 * Provides create, read, update, delete, status management, and assignment
 * operations for agents stored in the {prefix}mhd_agents table.
 *
 * @since 1.0.0
 */
class MHD_Agents {

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

		$this->table               = $wpdb->prefix . MHD_TABLE_PREFIX . 'agents';
		$this->conversations_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'conversations';
	}

	/**
	 * Retrieve a list of agents with optional filters.
	 *
	 * Joins the WordPress users table to include display_name and user_email.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Arguments to filter agents.
	 *
	 *     @type int    $department_id Filter by department ID.
	 *     @type string $role          Filter by agent role.
	 *     @type string $status        Filter by online status (active, away, offline).
	 * }
	 * @return array Array of agent row objects with display_name and user_email.
	 */
	public function get_agents( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'department_id' => 0,
			'role'          => '',
			'status'        => '',
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array();
		$values = array();

		if ( ! empty( $args['department_id'] ) ) {
			$where[]  = 'a.department_id = %d';
			$values[] = (int) $args['department_id'];
		}

		if ( ! empty( $args['role'] ) ) {
			$where[]  = 'a.role = %s';
			$values[] = $args['role'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'a.status = %s';
			$values[] = $args['status'];
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		$sql = "SELECT a.*, u.display_name, u.user_email
			FROM {$this->table} AS a
			INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
			{$where_clause}
			ORDER BY a.id ASC";

		if ( ! empty( $values ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get a single agent by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Agent table ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_agent( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, u.display_name, u.user_email
				FROM {$this->table} AS a
				INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
				WHERE a.id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Get a single agent by WordPress user ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_agent_by_user_id( $user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, u.display_name, u.user_email
				FROM {$this->table} AS a
				INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
				WHERE a.user_id = %d",
				(int) $user_id
			)
		);
	}

	/**
	 * Create a new agent.
	 *
	 * Inserts the agent record and adds the 'mhd_agent' capability to
	 * the corresponding WordPress user.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Agent data.
	 *
	 *     @type int    $user_id        WordPress user ID.
	 *     @type int    $department_id  Department ID.
	 *     @type string $role           Agent role. Default 'agent'.
	 *     @type int    $max_chats      Maximum concurrent chats. Default 5.
	 *     @type string $profile_image  URL to the agent profile image.
	 * }
	 * @return int|false The new agent ID on success, false on failure.
	 */
	public function create_agent( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'user_id'       => 0,
			'department_id' => null,
			'role'          => 'agent',
			'max_chats'     => 5,
			'profile_image' => '',
			'status'        => 'offline',
			'is_online'     => 0,
			'last_seen'     => $now,
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamps.
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$format = array(
			'%d', // user_id
			'%d', // department_id
			'%s', // role
			'%d', // max_chats
			'%s', // profile_image
			'%s', // status
			'%d', // is_online
			'%s', // last_seen
			'%s', // created_at
			'%s', // updated_at
		);

		$result = $wpdb->insert( $this->table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		$agent_id = (int) $wpdb->insert_id;

		// Add the mhd_agent capability to the WordPress user.
		$user = get_userdata( (int) $data['user_id'] );
		if ( $user ) {
			$user->add_cap( 'mhd_agent' );
		}

		return $agent_id;
	}

	/**
	 * Update an existing agent.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Agent table ID.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_agent( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$format = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'user_id', 'department_id', 'max_chats', 'is_online' ), true ) ) {
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
	 * Delete an agent and remove the 'mhd_agent' capability from the WordPress user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Agent table ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_agent( $id ) {
		global $wpdb;

		// Retrieve the agent so we can remove the capability from the WP user.
		$agent = $this->get_agent( (int) $id );

		$result = $wpdb->delete(
			$this->table,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		if ( false !== $result && $agent ) {
			$user = get_userdata( (int) $agent->user_id );
			if ( $user ) {
				$user->remove_cap( 'mhd_agent' );
			}
		}

		return false !== $result;
	}

	/**
	 * Set an agent's online status.
	 *
	 * Updates the status column, is_online flag, and last_seen timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $status  Agent status: 'active', 'away', or 'offline'.
	 * @return bool True on success, false on failure.
	 */
	public function set_status( $user_id, $status ) {
		global $wpdb;

		$allowed = array( 'active', 'away', 'offline' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$is_online = ( 'offline' !== $status ) ? 1 : 0;
		$now       = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'is_online'  => $is_online,
				'last_seen'  => $now,
				'updated_at' => $now,
			),
			array( 'user_id' => (int) $user_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get agents that are currently online (active or away).
	 *
	 * Optionally filtered by department.
	 *
	 * @since 1.0.0
	 *
	 * @param int $department_id Optional. Department ID to filter by. Default 0 (all departments).
	 * @return array Array of online agent row objects.
	 */
	public function get_online_agents( $department_id = 0 ) {
		global $wpdb;

		$where  = array( 'a.is_online = 1' );
		$values = array();

		if ( ! empty( $department_id ) ) {
			$where[]  = 'a.department_id = %d';
			$values[] = (int) $department_id;
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		$sql = "SELECT a.*, u.display_name, u.user_email
			FROM {$this->table} AS a
			INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
			{$where_clause}
			ORDER BY a.id ASC";

		if ( ! empty( $values ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Find the online agent with the fewest open conversations in a department.
	 *
	 * Only considers agents whose current open conversation count is below
	 * their max_chats limit.
	 *
	 * @since 1.0.0
	 *
	 * @param int $department_id Department ID.
	 * @return object|null Agent row object on success, null if none available.
	 */
	public function get_least_busy_agent( $department_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, u.display_name, u.user_email,
					COALESCE(open_counts.open_count, 0) AS open_count
				FROM {$this->table} AS a
				INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
				LEFT JOIN (
					SELECT agent_id, COUNT(*) AS open_count
					FROM {$this->conversations_table}
					WHERE status = 'open'
					GROUP BY agent_id
				) AS open_counts ON a.id = open_counts.agent_id
				WHERE a.department_id = %d
					AND a.is_online = 1
					AND COALESCE(open_counts.open_count, 0) < a.max_chats
				ORDER BY open_count ASC
				LIMIT 1",
				(int) $department_id
			)
		);
	}

	/**
	 * Get the next agent in round-robin order for a department.
	 *
	 * Uses a WordPress transient to track the last assigned agent ID per
	 * department. Only considers agents that are currently online and have
	 * not exceeded their max_chats limit.
	 *
	 * @since 1.0.0
	 *
	 * @param int $department_id Department ID.
	 * @return object|null Agent row object on success, null if none available.
	 */
	public function get_next_round_robin_agent( $department_id ) {
		global $wpdb;

		$department_id = (int) $department_id;
		$transient_key = 'mhd_rr_last_agent_dept_' . $department_id;
		$last_agent_id = (int) get_transient( $transient_key );

		// Get all eligible online agents in this department under their chat limit.
		$agents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name, u.user_email,
					COALESCE(open_counts.open_count, 0) AS open_count
				FROM {$this->table} AS a
				INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
				LEFT JOIN (
					SELECT agent_id, COUNT(*) AS open_count
					FROM {$this->conversations_table}
					WHERE status = 'open'
					GROUP BY agent_id
				) AS open_counts ON a.id = open_counts.agent_id
				WHERE a.department_id = %d
					AND a.is_online = 1
					AND COALESCE(open_counts.open_count, 0) < a.max_chats
				ORDER BY a.id ASC",
				$department_id
			)
		);

		if ( empty( $agents ) ) {
			return null;
		}

		// Find the next agent after the last assigned one.
		$next_agent = null;
		foreach ( $agents as $agent ) {
			if ( (int) $agent->id > $last_agent_id ) {
				$next_agent = $agent;
				break;
			}
		}

		// Wrap around to the first agent if none found after last_agent_id.
		if ( null === $next_agent ) {
			$next_agent = $agents[0];
		}

		// Store the assigned agent ID for the next round-robin cycle.
		set_transient( $transient_key, (int) $next_agent->id, DAY_IN_SECONDS );

		return $next_agent;
	}
}
