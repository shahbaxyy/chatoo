<?php
/**
 * Automations class.
 *
 * Handles all database operations for the automations table and
 * provides trigger/action execution logic.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Automations
 *
 * Manages automation rules stored in the {prefix}mhd_automations table.
 * Evaluates trigger conditions and executes actions such as assigning agents,
 * changing statuses, or sending messages.
 *
 * @since 1.0.0
 */
class MHD_Automations {

	/**
	 * Full database table name for automations including WP prefix.
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
	 * Constructor.
	 *
	 * Sets up the table names used by this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table               = $wpdb->prefix . MHD_TABLE_PREFIX . 'automations';
		$this->conversations_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'conversations';
		$this->messages_table      = $wpdb->prefix . MHD_TABLE_PREFIX . 'messages';
		$this->tickets_table       = $wpdb->prefix . MHD_TABLE_PREFIX . 'tickets';
	}

	/**
	 * Retrieve all automations.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of automation row objects ordered by id ascending.
	 */
	public function get_automations() {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get a single automation by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Automation ID.
	 * @return object|null Database row object on success, null if not found.
	 */
	public function get_automation( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				(int) $id
			)
		);
	}

	/**
	 * Create a new automation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Automation data.
	 *
	 *     @type string $name         Human-readable automation name.
	 *     @type string $trigger_type Trigger type: new_conversation, message_received, ticket_created.
	 *     @type string $conditions   JSON-encoded array of condition objects.
	 *     @type string $action_type  Action type: assign_agent, assign_dept, send_message, change_status, add_tag.
	 *     @type string $action_data  JSON-encoded action parameters.
	 *     @type int    $is_active    Whether the automation is active. Default 1.
	 * }
	 * @return int|false The new automation ID on success, false on failure.
	 */
	public function create_automation( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$defaults = array(
			'name'         => '',
			'trigger_type' => '',
			'conditions'   => '[]',
			'action_type'  => '',
			'action_data'  => '{}',
			'is_active'    => 1,
			'created_at'   => $now,
		);

		$data = wp_parse_args( $data, $defaults );

		// Force timestamp.
		$data['created_at'] = $now;

		$format = array(
			'%s', // name
			'%s', // trigger_type
			'%s', // conditions
			'%s', // action_type
			'%s', // action_data
			'%d', // is_active
			'%s', // created_at
		);

		$result = $wpdb->insert( $this->table, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing automation.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Automation ID.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_automation( $id, $data ) {
		global $wpdb;

		$format = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'is_active' ), true ) ) {
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
	 * Delete an automation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Automation ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_automation( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check and execute automations triggered by a new conversation.
	 *
	 * Loads all active automations with trigger_type 'new_conversation',
	 * evaluates their conditions, and executes matching actions.
	 *
	 * @since 1.0.0
	 *
	 * @param int $conversation_id The new conversation ID.
	 * @return void
	 */
	public function trigger_new_conversation( $conversation_id ) {
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

		$automations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE trigger_type = %s AND is_active = 1 ORDER BY id ASC",
				'new_conversation'
			)
		);

		$context = array(
			'type'            => 'conversation',
			'conversation_id' => (int) $conversation_id,
			'conversation'    => $conversation,
		);

		foreach ( $automations as $automation ) {
			$conditions = json_decode( $automation->conditions, true );
			if ( $this->check_conditions( $conditions, $context ) ) {
				$this->execute_action( $automation, $context );
			}
		}
	}

	/**
	 * Check and execute automations triggered by a new message.
	 *
	 * @since 1.0.0
	 *
	 * @param int $message_id      The new message ID.
	 * @param int $conversation_id The parent conversation ID.
	 * @return void
	 */
	public function trigger_new_message( $message_id, $conversation_id ) {
		global $wpdb;

		$message = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE id = %d",
				(int) $message_id
			)
		);

		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->conversations_table} WHERE id = %d",
				(int) $conversation_id
			)
		);

		if ( ! $message || ! $conversation ) {
			return;
		}

		$automations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE trigger_type = %s AND is_active = 1 ORDER BY id ASC",
				'message_received'
			)
		);

		$context = array(
			'type'            => 'message',
			'message_id'      => (int) $message_id,
			'message'         => $message,
			'conversation_id' => (int) $conversation_id,
			'conversation'    => $conversation,
		);

		foreach ( $automations as $automation ) {
			$conditions = json_decode( $automation->conditions, true );
			if ( $this->check_conditions( $conditions, $context ) ) {
				$this->execute_action( $automation, $context );
			}
		}
	}

	/**
	 * Check and execute automations triggered by a new ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param int $ticket_id The new ticket ID.
	 * @return void
	 */
	public function trigger_new_ticket( $ticket_id ) {
		global $wpdb;

		$ticket = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tickets_table} WHERE id = %d",
				(int) $ticket_id
			)
		);

		if ( ! $ticket ) {
			return;
		}

		$automations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE trigger_type = %s AND is_active = 1 ORDER BY id ASC",
				'ticket_created'
			)
		);

		$context = array(
			'type'      => 'ticket',
			'ticket_id' => (int) $ticket_id,
			'ticket'    => $ticket,
		);

		foreach ( $automations as $automation ) {
			$conditions = json_decode( $automation->conditions, true );
			if ( $this->check_conditions( $conditions, $context ) ) {
				$this->execute_action( $automation, $context );
			}
		}
	}

	/**
	 * Execute the action defined by an automation rule.
	 *
	 * Supports the following action types:
	 * - assign_agent: Assign a specific agent to the conversation or ticket.
	 * - assign_dept: Assign a department to the conversation or ticket.
	 * - send_message: Send an automated system message to the conversation.
	 * - change_status: Change the status of the conversation or ticket.
	 * - add_tag: Append a tag to the conversation or ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param object $automation The automation row object.
	 * @param array  $context    Contextual data including type, IDs, and row objects.
	 * @return void
	 */
	public function execute_action( $automation, $context ) {
		global $wpdb;

		$action_data = json_decode( $automation->action_data, true );
		if ( ! is_array( $action_data ) ) {
			$action_data = array();
		}

		switch ( $automation->action_type ) {

			case 'assign_agent':
				if ( empty( $action_data['agent_id'] ) ) {
					break;
				}
				$agent_id = (int) $action_data['agent_id'];

				if ( 'ticket' === $context['type'] ) {
					$wpdb->update(
						$this->tickets_table,
						array(
							'assigned_agent_id' => $agent_id,
							'updated_at'        => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['ticket_id'] ),
						array( '%d', '%s' ),
						array( '%d' )
					);
				} else {
					$wpdb->update(
						$this->conversations_table,
						array(
							'agent_id'   => $agent_id,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['conversation_id'] ),
						array( '%d', '%s' ),
						array( '%d' )
					);
				}
				break;

			case 'assign_dept':
				if ( empty( $action_data['department_id'] ) ) {
					break;
				}
				$department_id = (int) $action_data['department_id'];

				if ( 'ticket' === $context['type'] ) {
					$wpdb->update(
						$this->tickets_table,
						array(
							'department_id' => $department_id,
							'updated_at'    => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['ticket_id'] ),
						array( '%d', '%s' ),
						array( '%d' )
					);
				} else {
					$wpdb->update(
						$this->conversations_table,
						array(
							'department_id' => $department_id,
							'updated_at'    => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['conversation_id'] ),
						array( '%d', '%s' ),
						array( '%d' )
					);
				}
				break;

			case 'send_message':
				if ( empty( $action_data['message'] ) || empty( $context['conversation_id'] ) ) {
					break;
				}
				$wpdb->insert(
					$this->messages_table,
					array(
						'conversation_id' => (int) $context['conversation_id'],
						'user_id'         => null,
						'agent_id'        => null,
						'message'         => sanitize_textarea_field( $action_data['message'] ),
						'attachments'     => '',
						'message_type'    => 'system',
						'is_read'         => 0,
						'created_at'      => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
				);
				break;

			case 'change_status':
				if ( empty( $action_data['status'] ) ) {
					break;
				}
				$status = sanitize_text_field( $action_data['status'] );

				if ( 'ticket' === $context['type'] ) {
					$wpdb->update(
						$this->tickets_table,
						array(
							'status'     => $status,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['ticket_id'] ),
						array( '%s', '%s' ),
						array( '%d' )
					);
				} else {
					$wpdb->update(
						$this->conversations_table,
						array(
							'status'     => $status,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['conversation_id'] ),
						array( '%s', '%s' ),
						array( '%d' )
					);
				}
				break;

			case 'add_tag':
				if ( empty( $action_data['tag'] ) ) {
					break;
				}
				$tag = sanitize_text_field( $action_data['tag'] );

				if ( 'ticket' === $context['type'] ) {
					$existing = isset( $context['ticket']->tags ) ? $context['ticket']->tags : '';
					$tags     = ! empty( $existing ) ? $existing . ',' . $tag : $tag;
					$wpdb->update(
						$this->tickets_table,
						array(
							'tags'       => $tags,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['ticket_id'] ),
						array( '%s', '%s' ),
						array( '%d' )
					);
				} elseif ( ! empty( $context['conversation_id'] ) ) {
					$existing = isset( $context['conversation']->tags ) ? $context['conversation']->tags : '';
					$tags     = ! empty( $existing ) ? $existing . ',' . $tag : $tag;
					$wpdb->update(
						$this->conversations_table,
						array(
							'tags'       => $tags,
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => (int) $context['conversation_id'] ),
						array( '%s', '%s' ),
						array( '%d' )
					);
				}
				break;
		}
	}

	/**
	 * Evaluate automation conditions against the given context.
	 *
	 * Each condition is an associative array with keys: field, operator, value.
	 * Supported operators: equals, not_equals, contains, not_contains.
	 * All conditions must pass (AND logic) for the method to return true.
	 * An empty conditions array always returns true.
	 *
	 * @since 1.0.0
	 *
	 * @param array $conditions Array of condition arrays, each with field, operator, value.
	 * @param array $context    Contextual data including conversation/ticket/message objects.
	 * @return bool True if all conditions match, false otherwise.
	 */
	public function check_conditions( $conditions, $context ) {
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return true;
		}

		foreach ( $conditions as $condition ) {
			if ( ! isset( $condition['field'], $condition['operator'], $condition['value'] ) ) {
				continue;
			}

			$field    = $condition['field'];
			$operator = $condition['operator'];
			$expected = $condition['value'];

			// Resolve the actual value from context objects.
			$actual = $this->resolve_field_value( $field, $context );

			switch ( $operator ) {
				case 'equals':
					if ( (string) $actual !== (string) $expected ) {
						return false;
					}
					break;

				case 'not_equals':
					if ( (string) $actual === (string) $expected ) {
						return false;
					}
					break;

				case 'contains':
					if ( false === stripos( (string) $actual, (string) $expected ) ) {
						return false;
					}
					break;

				case 'not_contains':
					if ( false !== stripos( (string) $actual, (string) $expected ) ) {
						return false;
					}
					break;

				default:
					// Unknown operator; skip this condition.
					break;
			}
		}

		return true;
	}

	/**
	 * Resolve a field value from the automation context.
	 *
	 * Looks for the field on the conversation, message, or ticket object
	 * depending on what is available in the context.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param string $field   The field name to resolve.
	 * @param array  $context Contextual data with row objects.
	 * @return string The resolved field value, or empty string if not found.
	 */
	private function resolve_field_value( $field, $context ) {
		// Check conversation object first.
		if ( ! empty( $context['conversation'] ) && isset( $context['conversation']->{$field} ) ) {
			return $context['conversation']->{$field};
		}

		// Check message object.
		if ( ! empty( $context['message'] ) && isset( $context['message']->{$field} ) ) {
			return $context['message']->{$field};
		}

		// Check ticket object.
		if ( ! empty( $context['ticket'] ) && isset( $context['ticket']->{$field} ) ) {
			return $context['ticket']->{$field};
		}

		return '';
	}
}
