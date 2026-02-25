<?php
/**
 * Departments CRUD class.
 *
 * Handles all database operations for the departments table.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Departments
 *
 * Provides create, read, update, and delete operations for departments
 * stored in the {prefix}mhd_departments table.
 *
 * @since 1.0.0
 */
class MHD_Departments {

	/**
	 * Full database table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table;

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

		$this->table        = $wpdb->prefix . MHD_TABLE_PREFIX . 'departments';
		$this->agents_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'agents';
	}

	/**
	 * Retrieve a list of all departments.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of department row objects.
	 */
	public function get_departments() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT * FROM {$this->table} ORDER BY id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get a single department by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Department ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_department( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Create a new department.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Department data.
	 *
	 *     @type string $name        Department name.
	 *     @type string $description Department description.
	 *     @type string $color       Department color hex code.
	 *     @type string $agents      JSON-encoded array of agent user IDs.
	 * }
	 * @return int|false The new department ID on success, false on failure.
	 */
	public function create_department( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'name'        => '',
			'description' => '',
			'color'       => '',
			'agents'      => '[]',
			'created_at'  => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamp.
		$data['created_at'] = $now;

		$format = array(
			'%s', // name
			'%s', // description
			'%s', // color
			'%s', // agents
			'%s', // created_at
		);

		$result = $wpdb->insert( $this->table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing department.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Department ID.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_department( $id, $data ) {
		global $wpdb;

		$format = array();
		foreach ( $data as $key => $value ) {
			$format[] = '%s';
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
	 * Delete a department.
	 *
	 * Also nullifies the department_id on any agents assigned to this department.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Department ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_department( $id ) {
		global $wpdb;

		// Nullify department_id on agents in this department.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->agents_table} SET department_id = NULL WHERE department_id = %d",
				(int) $id
			)
		);

		$result = $wpdb->delete(
			$this->table,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get agents assigned to a department.
	 *
	 * Joins the WordPress users table to include display_name and user_email.
	 *
	 * @since 1.0.0
	 *
	 * @param int $department_id Department ID.
	 * @return array Array of agent row objects with display_name and user_email.
	 */
	public function get_department_agents( $department_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name, u.user_email
				FROM {$this->agents_table} AS a
				INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
				WHERE a.department_id = %d
				ORDER BY a.id ASC",
				(int) $department_id
			)
		);
	}
}
