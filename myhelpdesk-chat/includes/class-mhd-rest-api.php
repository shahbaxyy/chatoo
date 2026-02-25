<?php
/**
 * REST API class.
 *
 * Registers and handles all WordPress REST API endpoints for the plugin.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Rest_API
 *
 * Provides REST API endpoints under the mhd/v1 namespace for external
 * integrations. All endpoints require API key authentication via the
 * X-MHD-API-Key header.
 *
 * @since 1.0.0
 */
class MHD_Rest_API {

	/**
	 * REST API namespace.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $namespace = 'mhd/v1';

	/**
	 * Constructor.
	 *
	 * Hooks the route registration into the rest_api_init action.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// Conversations.
		register_rest_route( $this->namespace, '/conversations', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversations' ),
				'permission_callback' => array( $this, 'authenticate' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_conversation' ),
				'permission_callback' => array( $this, 'authenticate' ),
			),
		) );

		register_rest_route( $this->namespace, '/conversations/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_conversation' ),
			'permission_callback' => array( $this, 'authenticate' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		register_rest_route( $this->namespace, '/conversations/(?P<id>\d+)/messages', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'send_message' ),
			'permission_callback' => array( $this, 'authenticate' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		register_rest_route( $this->namespace, '/conversations/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_conversation_status' ),
			'permission_callback' => array( $this, 'authenticate' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		// Agents.
		register_rest_route( $this->namespace, '/agents', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_agents' ),
			'permission_callback' => array( $this, 'authenticate' ),
		) );

		// Departments.
		register_rest_route( $this->namespace, '/departments', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_departments' ),
			'permission_callback' => array( $this, 'authenticate' ),
		) );

		// Tickets.
		register_rest_route( $this->namespace, '/tickets', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_ticket' ),
			'permission_callback' => array( $this, 'authenticate' ),
		) );

		register_rest_route( $this->namespace, '/tickets/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_ticket' ),
			'permission_callback' => array( $this, 'authenticate' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		register_rest_route( $this->namespace, '/tickets/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_ticket_status' ),
			'permission_callback' => array( $this, 'authenticate' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		register_rest_route( $this->namespace, '/tickets/(?P<id>\d+)/replies', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'add_ticket_reply' ),
			'permission_callback' => array( $this, 'authenticate' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );

		// Knowledge Base.
		register_rest_route( $this->namespace, '/kb/articles', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_kb_articles' ),
			'permission_callback' => array( $this, 'authenticate' ),
		) );

		register_rest_route( $this->namespace, '/kb/articles/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_kb_article' ),
			'permission_callback' => array( $this, 'authenticate' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param ) {
						return is_numeric( $param );
					},
				),
			),
		) );
	}

	/**
	 * Permission callback that checks the X-MHD-API-Key header.
	 *
	 * Verifies the provided API key matches the stored plugin API key
	 * and that the REST API feature is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool|WP_Error True if authenticated, WP_Error otherwise.
	 */
	public function authenticate( $request ) {
		if ( '1' !== get_option( 'mhd_rest_api_enabled', '0' ) ) {
			return new WP_Error(
				'rest_disabled',
				__( 'The REST API is not enabled.', 'myhelpdesk-chat' ),
				array( 'status' => 403 )
			);
		}

		$api_key    = get_option( 'mhd_api_key', '' );
		$provided   = $request->get_header( 'X-MHD-API-Key' );

		if ( empty( $api_key ) || empty( $provided ) || ! hash_equals( $api_key, $provided ) ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Invalid or missing API key.', 'myhelpdesk-chat' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Get a list of conversations.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_conversations( $request ) {
		$conversations = new MHD_Conversations();

		$args = array(
			'status'   => sanitize_text_field( $request->get_param( 'status' ) ),
			'agent_id' => absint( $request->get_param( 'agent_id' ) ),
			'source'   => sanitize_text_field( $request->get_param( 'source' ) ),
			'search'   => sanitize_text_field( $request->get_param( 'search' ) ),
			'per_page' => absint( $request->get_param( 'per_page' ) ) ?: 20,
			'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
		);

		$result = $conversations->get_conversations( $args );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Create a new conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function create_conversation( $request ) {
		$conversations = new MHD_Conversations();

		$data = array(
			'subject'    => sanitize_text_field( $request->get_param( 'subject' ) ),
			'user_name'  => sanitize_text_field( $request->get_param( 'user_name' ) ),
			'user_email' => sanitize_email( $request->get_param( 'user_email' ) ),
			'source'     => sanitize_text_field( $request->get_param( 'source' ) ) ?: 'chat',
		);

		$department_id = absint( $request->get_param( 'department_id' ) );
		if ( $department_id ) {
			$data['department_id'] = $department_id;
		}

		$id = $conversations->create_conversation( $data );

		if ( false === $id ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to create conversation.', 'myhelpdesk-chat' ) ),
				500
			);
		}

		$conversation = $conversations->get_conversation( $id );

		return new WP_REST_Response( $conversation, 201 );
	}

	/**
	 * Get a single conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_conversation( $request ) {
		$conversations = new MHD_Conversations();
		$conversation  = $conversations->get_conversation( absint( $request['id'] ) );

		if ( ! $conversation ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Conversation not found.', 'myhelpdesk-chat' ) ),
				404
			);
		}

		// Include messages.
		$messages_handler     = new MHD_Messages();
		$messages             = $messages_handler->get_messages( (int) $conversation->id );
		$conversation->messages = $messages;

		return new WP_REST_Response( $conversation, 200 );
	}

	/**
	 * Send a message to a conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function send_message( $request ) {
		$conversation_id = absint( $request['id'] );

		$conversations = new MHD_Conversations();
		$conversation  = $conversations->get_conversation( $conversation_id );

		if ( ! $conversation ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Conversation not found.', 'myhelpdesk-chat' ) ),
				404
			);
		}

		$message_text = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( empty( $message_text ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Message content is required.', 'myhelpdesk-chat' ) ),
				400
			);
		}

		$messages = new MHD_Messages();

		$data = array(
			'conversation_id' => $conversation_id,
			'message'         => $message_text,
			'message_type'    => sanitize_text_field( $request->get_param( 'message_type' ) ) ?: 'text',
		);

		$agent_id = absint( $request->get_param( 'agent_id' ) );
		$user_id  = absint( $request->get_param( 'user_id' ) );

		if ( $agent_id ) {
			$data['agent_id'] = $agent_id;
		}
		if ( $user_id ) {
			$data['user_id'] = $user_id;
		}

		$message_id = $messages->create_message( $data );

		if ( false === $message_id ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to send message.', 'myhelpdesk-chat' ) ),
				500
			);
		}

		$msg = $messages->get_message( $message_id );

		return new WP_REST_Response( $msg, 201 );
	}

	/**
	 * Update the status of a conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function update_conversation_status( $request ) {
		$conversations = new MHD_Conversations();
		$conversation  = $conversations->get_conversation( absint( $request['id'] ) );

		if ( ! $conversation ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Conversation not found.', 'myhelpdesk-chat' ) ),
				404
			);
		}

		$status  = sanitize_text_field( $request->get_param( 'status' ) );
		$allowed = array( 'open', 'pending', 'resolved', 'archived' );

		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Invalid status value.', 'myhelpdesk-chat' ) ),
				400
			);
		}

		$conversations->update_conversation( (int) $conversation->id, array( 'status' => $status ) );

		$updated = $conversations->get_conversation( (int) $conversation->id );

		return new WP_REST_Response( $updated, 200 );
	}

	/**
	 * Get a list of agents.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_agents( $request ) {
		$agents_handler = new MHD_Agents();

		$args = array(
			'department_id' => absint( $request->get_param( 'department_id' ) ),
			'status'        => sanitize_text_field( $request->get_param( 'status' ) ),
		);

		$agents = $agents_handler->get_agents( $args );

		return new WP_REST_Response( $agents, 200 );
	}

	/**
	 * Get a list of departments.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_departments( $request ) {
		$departments_handler = new MHD_Departments();
		$departments         = $departments_handler->get_departments();

		return new WP_REST_Response( $departments, 200 );
	}

	/**
	 * Create a new ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function create_ticket( $request ) {
		$tickets = new MHD_Tickets();

		$subject = sanitize_text_field( $request->get_param( 'subject' ) );

		if ( empty( $subject ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Subject is required.', 'myhelpdesk-chat' ) ),
				400
			);
		}

		$data = array(
			'subject'    => $subject,
			'user_email' => sanitize_email( $request->get_param( 'user_email' ) ),
			'priority'   => sanitize_text_field( $request->get_param( 'priority' ) ) ?: 'medium',
			'status'     => 'open',
		);

		$user_id = absint( $request->get_param( 'user_id' ) );
		if ( $user_id ) {
			$data['user_id'] = $user_id;
		}

		$department_id = absint( $request->get_param( 'department_id' ) );
		if ( $department_id ) {
			$data['department_id'] = $department_id;
		}

		$assigned_agent_id = absint( $request->get_param( 'assigned_agent_id' ) );
		if ( $assigned_agent_id ) {
			$data['assigned_agent_id'] = $assigned_agent_id;
		}

		$id = $tickets->create_ticket( $data );

		if ( false === $id ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to create ticket.', 'myhelpdesk-chat' ) ),
				500
			);
		}

		$ticket = $tickets->get_ticket( $id );

		return new WP_REST_Response( $ticket, 201 );
	}

	/**
	 * Get a single ticket with its replies.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_ticket( $request ) {
		$tickets = new MHD_Tickets();
		$ticket  = $tickets->get_ticket( absint( $request['id'] ) );

		if ( ! $ticket ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Ticket not found.', 'myhelpdesk-chat' ) ),
				404
			);
		}

		$ticket->replies = $tickets->get_ticket_replies( (int) $ticket->id );

		return new WP_REST_Response( $ticket, 200 );
	}

	/**
	 * Update the status of a ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function update_ticket_status( $request ) {
		$tickets = new MHD_Tickets();
		$ticket  = $tickets->get_ticket( absint( $request['id'] ) );

		if ( ! $ticket ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Ticket not found.', 'myhelpdesk-chat' ) ),
				404
			);
		}

		$status  = sanitize_text_field( $request->get_param( 'status' ) );
		$allowed = array( 'open', 'in_progress', 'resolved', 'closed' );

		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Invalid status value.', 'myhelpdesk-chat' ) ),
				400
			);
		}

		$tickets->update_ticket( (int) $ticket->id, array( 'status' => $status ) );

		$updated = $tickets->get_ticket( (int) $ticket->id );

		return new WP_REST_Response( $updated, 200 );
	}

	/**
	 * Add a reply to a ticket.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function add_ticket_reply( $request ) {
		$tickets   = new MHD_Tickets();
		$ticket_id = absint( $request['id'] );
		$ticket    = $tickets->get_ticket( $ticket_id );

		if ( ! $ticket ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Ticket not found.', 'myhelpdesk-chat' ) ),
				404
			);
		}

		$message = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( empty( $message ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Message content is required.', 'myhelpdesk-chat' ) ),
				400
			);
		}

		$data = array(
			'ticket_id' => $ticket_id,
			'message'   => $message,
			'is_note'   => absint( $request->get_param( 'is_note' ) ) ? 1 : 0,
		);

		$agent_id = absint( $request->get_param( 'agent_id' ) );
		$user_id  = absint( $request->get_param( 'user_id' ) );

		if ( $agent_id ) {
			$data['agent_id'] = $agent_id;
		}
		if ( $user_id ) {
			$data['user_id'] = $user_id;
		}

		$reply_id = $tickets->add_ticket_reply( $data );

		if ( false === $reply_id ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to add reply.', 'myhelpdesk-chat' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'id'        => $reply_id,
				'ticket_id' => $ticket_id,
				'message'   => __( 'Reply added successfully.', 'myhelpdesk-chat' ),
			),
			201
		);
	}

	/**
	 * Get a list of KB articles.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_kb_articles( $request ) {
		$kb = new MHD_Knowledge_Base();

		$args = array(
			'category_id' => absint( $request->get_param( 'category_id' ) ),
			'status'      => sanitize_text_field( $request->get_param( 'status' ) ) ?: 'published',
			'search'      => sanitize_text_field( $request->get_param( 'search' ) ),
			'per_page'    => absint( $request->get_param( 'per_page' ) ) ?: 20,
			'page'        => absint( $request->get_param( 'page' ) ) ?: 1,
		);

		$result = $kb->get_articles( $args );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get a single KB article.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_kb_article( $request ) {
		$kb      = new MHD_Knowledge_Base();
		$article = $kb->get_article( absint( $request['id'] ) );

		if ( ! $article ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Article not found.', 'myhelpdesk-chat' ) ),
				404
			);
		}

		return new WP_REST_Response( $article, 200 );
	}
}
