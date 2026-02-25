<?php
/**
 * Reports class.
 *
 * Generates report and analytics data from plugin database tables.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Reports
 *
 * Provides methods to generate overview stats, per-day counts, agent
 * performance data, and CSV exports from the conversations, messages,
 * tickets, and ratings tables.
 *
 * @since 1.0.0
 */
class MHD_Reports {

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
	 * Ratings table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $ratings_table;

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

		$this->conversations_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'conversations';
		$this->messages_table      = $wpdb->prefix . MHD_TABLE_PREFIX . 'messages';
		$this->tickets_table       = $wpdb->prefix . MHD_TABLE_PREFIX . 'tickets';
		$this->ratings_table       = $wpdb->prefix . MHD_TABLE_PREFIX . 'ratings';
		$this->agents_table        = $wpdb->prefix . MHD_TABLE_PREFIX . 'agents';
	}

	/**
	 * Get overview statistics for a date range.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date in Y-m-d format.
	 * @param string $date_to   End date in Y-m-d format.
	 * @return array {
	 *     Overview statistics.
	 *
	 *     @type int    $total_conversations Total conversations in period.
	 *     @type int    $total_messages      Total messages in period.
	 *     @type int    $open_tickets        Tickets with open/in_progress status.
	 *     @type int    $resolved_tickets    Tickets resolved/closed in period.
	 *     @type float  $avg_first_response  Average first response time in seconds.
	 *     @type float  $avg_resolution_time Average resolution time in seconds.
	 *     @type float  $satisfaction_score  Average rating score (1-5).
	 * }
	 */
	public function get_overview( $date_from, $date_to ) {
		global $wpdb;

		$date_from = sanitize_text_field( $date_from ) . ' 00:00:00';
		$date_to   = sanitize_text_field( $date_to ) . ' 23:59:59';

		// Total conversations in date range.
		$total_conversations = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->conversations_table} WHERE created_at BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		// Total messages in date range.
		$total_messages = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->messages_table} WHERE created_at BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		// Open tickets (current state, not date-filtered).
		$open_tickets = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->tickets_table} WHERE status IN ('open', 'in_progress')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Resolved tickets in date range.
		$resolved_tickets = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tickets_table} WHERE status IN ('resolved', 'closed') AND updated_at BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		// Average first response time: time between conversation creation and the
		// first agent message, in seconds.
		$avg_first_response = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(TIMESTAMPDIFF(SECOND, c.created_at, m.first_reply))
				FROM {$this->conversations_table} AS c
				INNER JOIN (
					SELECT conversation_id, MIN(created_at) AS first_reply
					FROM {$this->messages_table}
					WHERE agent_id IS NOT NULL
					GROUP BY conversation_id
				) AS m ON c.id = m.conversation_id
				WHERE c.created_at BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		// Average resolution time: time between creation and updated_at for
		// resolved/archived conversations.
		$avg_resolution_time = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at))
				FROM {$this->conversations_table}
				WHERE status IN ('resolved', 'archived')
					AND created_at BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		// Satisfaction score: average rating in date range.
		$satisfaction_score = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$this->ratings_table} WHERE created_at BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		return array(
			'total_conversations' => $total_conversations,
			'total_messages'      => $total_messages,
			'open_tickets'        => $open_tickets,
			'resolved_tickets'    => $resolved_tickets,
			'avg_first_response'  => round( $avg_first_response, 2 ),
			'avg_resolution_time' => round( $avg_resolution_time, 2 ),
			'satisfaction_score'  => round( $satisfaction_score, 2 ),
		);
	}

	/**
	 * Get daily conversation counts for a date range.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date in Y-m-d format.
	 * @param string $date_to   End date in Y-m-d format.
	 * @return array Array of objects with 'date' (Y-m-d) and 'count' properties.
	 */
	public function get_conversations_per_day( $date_from, $date_to ) {
		global $wpdb;

		$date_from = sanitize_text_field( $date_from ) . ' 00:00:00';
		$date_to   = sanitize_text_field( $date_to ) . ' 23:59:59';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS date, COUNT(*) AS count
				FROM {$this->conversations_table}
				WHERE created_at BETWEEN %s AND %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get conversation counts grouped by source.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date in Y-m-d format.
	 * @param string $date_to   End date in Y-m-d format.
	 * @return array Array of objects with 'source' and 'count' properties.
	 */
	public function get_conversations_by_source( $date_from, $date_to ) {
		global $wpdb;

		$date_from = sanitize_text_field( $date_from ) . ' 00:00:00';
		$date_to   = sanitize_text_field( $date_to ) . ' 23:59:59';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, COUNT(*) AS count
				FROM {$this->conversations_table}
				WHERE created_at BETWEEN %s AND %s
				GROUP BY source
				ORDER BY count DESC",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get ticket counts grouped by status.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of objects with 'status' and 'count' properties.
	 */
	public function get_tickets_by_status() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT status, COUNT(*) AS count FROM {$this->tickets_table} GROUP BY status ORDER BY count DESC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get per-agent performance statistics for a date range.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date in Y-m-d format.
	 * @param string $date_to   End date in Y-m-d format.
	 * @return array Array of associative arrays, one per agent, with keys:
	 *               agent_id, display_name, chats_handled, messages_sent,
	 *               avg_response_time, tickets_resolved, positive_ratings,
	 *               negative_ratings.
	 */
	public function get_agent_performance( $date_from, $date_to ) {
		global $wpdb;

		$date_from = sanitize_text_field( $date_from ) . ' 00:00:00';
		$date_to   = sanitize_text_field( $date_to ) . ' 23:59:59';

		// Get all agents.
		$agents = $wpdb->get_results(
			"SELECT a.id AS agent_id, u.display_name
			FROM {$this->agents_table} AS a
			INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
			ORDER BY a.id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$results = array();

		foreach ( $agents as $agent ) {
			$agent_id = (int) $agent->agent_id;

			// Chats handled: conversations assigned to this agent in date range.
			$chats_handled = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->conversations_table} WHERE agent_id = %d AND created_at BETWEEN %s AND %s",
					$agent_id,
					$date_from,
					$date_to
				)
			);

			// Messages sent by this agent in date range.
			$messages_sent = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->messages_table} WHERE agent_id = %d AND created_at BETWEEN %s AND %s",
					$agent_id,
					$date_from,
					$date_to
				)
			);

			// Average response time for this agent.
			$avg_response_time = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT AVG(TIMESTAMPDIFF(SECOND, c.created_at, m.first_reply))
					FROM {$this->conversations_table} AS c
					INNER JOIN (
						SELECT conversation_id, MIN(created_at) AS first_reply
						FROM {$this->messages_table}
						WHERE agent_id = %d
						GROUP BY conversation_id
					) AS m ON c.id = m.conversation_id
					WHERE c.created_at BETWEEN %s AND %s",
					$agent_id,
					$date_from,
					$date_to
				)
			);

			// Tickets resolved by this agent in date range.
			$tickets_resolved = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->tickets_table} WHERE assigned_agent_id = %d AND status IN ('resolved', 'closed') AND updated_at BETWEEN %s AND %s",
					$agent_id,
					$date_from,
					$date_to
				)
			);

			// Positive ratings (4 or 5).
			$positive_ratings = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->ratings_table} WHERE agent_id = %d AND rating >= 4 AND created_at BETWEEN %s AND %s",
					$agent_id,
					$date_from,
					$date_to
				)
			);

			// Negative ratings (1 or 2).
			$negative_ratings = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->ratings_table} WHERE agent_id = %d AND rating <= 2 AND created_at BETWEEN %s AND %s",
					$agent_id,
					$date_from,
					$date_to
				)
			);

			$results[] = array(
				'agent_id'          => $agent_id,
				'display_name'      => $agent->display_name,
				'chats_handled'     => $chats_handled,
				'messages_sent'     => $messages_sent,
				'avg_response_time' => round( $avg_response_time, 2 ),
				'tickets_resolved'  => $tickets_resolved,
				'positive_ratings'  => $positive_ratings,
				'negative_ratings'  => $negative_ratings,
			);
		}

		return $results;
	}

	/**
	 * Export conversations as a CSV string.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Arguments to filter conversations for export.
	 *
	 *     @type string $status    Filter by status.
	 *     @type string $date_from Start date (Y-m-d).
	 *     @type string $date_to   End date (Y-m-d).
	 *     @type int    $agent_id  Filter by agent ID.
	 * }
	 * @return string CSV-formatted string with headers and data rows.
	 */
	public function export_conversations_csv( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
			'agent_id'  => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array();
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'agent_id = %d';
			$values[] = (int) $args['agent_id'];
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		$sql = "SELECT * FROM {$this->conversations_table} {$where_clause} ORDER BY created_at DESC";

		if ( ! empty( $values ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( empty( $rows ) ) {
			return '';
		}

		$output = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// Write CSV header.
		fputcsv( $output, array_keys( $rows[0] ) );

		// Write data rows.
		foreach ( $rows as $row ) {
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $csv;
	}
}
