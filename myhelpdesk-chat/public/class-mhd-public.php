<?php
/**
 * Public-facing functionality for the MyHelpDesk Chat plugin.
 *
 * Enqueues front-end assets, renders the floating chat widget,
 * and registers all public-facing shortcodes.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Public
 *
 * Handles all public-facing output for the MyHelpDesk Chat plugin
 * including the chat widget, knowledge-base shortcodes, ticket
 * shortcodes, and the required CSS / JavaScript assets.
 *
 * @since 1.0.0
 */
class MHD_Public {

	/**
	 * Enqueue front-end stylesheets.
	 *
	 * Loads chat-style.css on every non-excluded front-end page when
	 * the chat widget is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! $this->is_chat_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'mhd-chat-style',
			MHD_PLUGIN_URL . 'public/css/chat-style.css',
			array(),
			MHD_VERSION
		);
	}

	/**
	 * Enqueue front-end JavaScript files.
	 *
	 * Loads chat-widget.js and chat-notifications.js, then localizes
	 * the `mhd_chat` object containing AJAX settings, nonce, design
	 * options, pre-chat fields, GDPR, file-upload, knowledge-base,
	 * and current-user data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_chat_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'mhd-chat-widget',
			MHD_PLUGIN_URL . 'public/js/chat-widget.js',
			array( 'jquery' ),
			MHD_VERSION,
			true
		);

		wp_enqueue_script(
			'mhd-chat-notifications',
			MHD_PLUGIN_URL . 'public/js/chat-notifications.js',
			array( 'jquery', 'mhd-chat-widget' ),
			MHD_VERSION,
			true
		);

		$user_data = array();
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_data    = array(
				'id'    => $current_user->ID,
				'name'  => $current_user->display_name,
				'email' => $current_user->user_email,
			);
		}

		wp_localize_script(
			'mhd-chat-widget',
			'mhd_chat',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mhd_nonce' ),
				'settings' => array(
					'welcome_message'    => get_option( 'mhd_welcome_message', '' ),
					'offline_message'    => get_option( 'mhd_offline_message', '' ),
					'primary_color'      => get_option( 'mhd_primary_color', '#0073aa' ),
					'chat_header_text'   => get_option( 'mhd_company_name', 'Support Chat' ),
					'widget_width'       => get_option( 'mhd_widget_width', '370' ),
					'widget_height'      => get_option( 'mhd_widget_height', '520' ),
					'border_radius'      => get_option( 'mhd_border_radius', '12' ),
					'show_agent_avatar'  => get_option( 'mhd_show_agent_avatar', '1' ),
					'powered_by'         => get_option( 'mhd_powered_by', '1' ),
					'prechat_form'       => get_option( 'mhd_pre_chat_form', '1' ),
					'prechat_name'       => get_option( 'mhd_prechat_name', '1' ),
					'prechat_email'      => get_option( 'mhd_prechat_email', '1' ),
					'prechat_department' => get_option( 'mhd_prechat_department', '0' ),
					'gdpr_enabled'       => get_option( 'mhd_gdpr_enabled', '0' ),
					'gdpr_message'       => get_option( 'mhd_gdpr_message', '' ),
					'file_uploads'       => get_option( 'mhd_file_uploads', '1' ),
					'max_file_size'      => get_option( 'mhd_max_file_size', '5' ),
					'allowed_file_types' => get_option( 'mhd_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,zip' ),
					'kb_enabled'         => get_option( 'mhd_kb_enabled', '0' ),
					'kb_in_widget'       => get_option( 'mhd_kb_in_widget', '0' ),
					'polling_interval'   => get_option( 'mhd_polling_interval', '5' ),
					'sound_enabled'      => get_option( 'mhd_sound_notification', '1' ),
					'rating_enabled'     => get_option( 'mhd_chat_rating', '1' ),
					'user'               => $user_data,
				),
			)
		);
	}

	/**
	 * Render the floating chat widget in the site footer.
	 *
	 * Includes the chat-widget.php view template when the chat is
	 * enabled and the current page is not excluded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_chat_widget() {
		if ( ! $this->is_chat_enabled() ) {
			return;
		}

		include MHD_PLUGIN_DIR . 'public/views/chat-widget.php';
	}

	/**
	 * Shortcode callback to embed the chat widget inline.
	 *
	 * Usage: [mhd_chat_widget]
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string Chat widget HTML.
	 */
	public function shortcode_chat_widget( $atts ) {
		$atts = shortcode_atts( array(), $atts, 'mhd_chat_widget' );

		ob_start();
		include MHD_PLUGIN_DIR . 'public/views/chat-widget.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode callback to display a ticket submission form.
	 *
	 * Usage: [mhd_ticket_form]
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Ticket form HTML.
	 */
	public function shortcode_ticket_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'department' => '',
			),
			$atts,
			'mhd_ticket_form'
		);

		$departments_obj = new MHD_Departments();
		$departments     = $departments_obj->get_departments();
		$current_user    = wp_get_current_user();

		ob_start();
		?>
		<div class="mhd-ticket-form-wrap">
			<form class="mhd-ticket-form" method="post">
				<?php wp_nonce_field( 'mhd_submit_ticket', 'mhd_ticket_nonce' ); ?>

				<?php if ( ! is_user_logged_in() ) : ?>
					<div class="mhd-form-field">
						<label for="mhd-ticket-name"><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></label>
						<input type="text" id="mhd-ticket-name" name="name" required />
					</div>
					<div class="mhd-form-field">
						<label for="mhd-ticket-email"><?php esc_html_e( 'Email', 'myhelpdesk-chat' ); ?></label>
						<input type="email" id="mhd-ticket-email" name="email" required />
					</div>
				<?php else : ?>
					<input type="hidden" name="name" value="<?php echo esc_attr( $current_user->display_name ); ?>" />
					<input type="hidden" name="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" />
				<?php endif; ?>

				<div class="mhd-form-field">
					<label for="mhd-ticket-subject"><?php esc_html_e( 'Subject', 'myhelpdesk-chat' ); ?></label>
					<input type="text" id="mhd-ticket-subject" name="subject" required />
				</div>

				<?php if ( ! empty( $departments ) && empty( $atts['department'] ) ) : ?>
					<div class="mhd-form-field">
						<label for="mhd-ticket-department"><?php esc_html_e( 'Department', 'myhelpdesk-chat' ); ?></label>
						<select id="mhd-ticket-department" name="department_id">
							<option value=""><?php esc_html_e( 'Select a department', 'myhelpdesk-chat' ); ?></option>
							<?php foreach ( $departments as $dept ) : ?>
								<option value="<?php echo esc_attr( $dept->id ); ?>">
									<?php echo esc_html( $dept->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php elseif ( ! empty( $atts['department'] ) ) : ?>
					<input type="hidden" name="department_id" value="<?php echo esc_attr( $atts['department'] ); ?>" />
				<?php endif; ?>

				<div class="mhd-form-field">
					<label for="mhd-ticket-priority"><?php esc_html_e( 'Priority', 'myhelpdesk-chat' ); ?></label>
					<select id="mhd-ticket-priority" name="priority">
						<option value="low"><?php esc_html_e( 'Low', 'myhelpdesk-chat' ); ?></option>
						<option value="medium" selected><?php esc_html_e( 'Medium', 'myhelpdesk-chat' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'myhelpdesk-chat' ); ?></option>
					</select>
				</div>

				<div class="mhd-form-field">
					<label for="mhd-ticket-message"><?php esc_html_e( 'Message', 'myhelpdesk-chat' ); ?></label>
					<textarea id="mhd-ticket-message" name="message" rows="6" required></textarea>
				</div>

				<div class="mhd-form-field">
					<button type="submit" class="mhd-btn mhd-btn-primary">
						<?php esc_html_e( 'Submit Ticket', 'myhelpdesk-chat' ); ?>
					</button>
				</div>

				<div class="mhd-ticket-form-status"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode callback to display the current user's tickets.
	 *
	 * Requires the visitor to be logged in. Usage: [mhd_my_tickets]
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Tickets list HTML or login prompt.
	 */
	public function shortcode_my_tickets( $atts ) {
		$atts = shortcode_atts(
			array(
				'status' => '',
			),
			$atts,
			'mhd_my_tickets'
		);

		if ( ! is_user_logged_in() ) {
			return '<div class="mhd-notice mhd-notice-info">' .
				esc_html__( 'Please log in to view your tickets.', 'myhelpdesk-chat' ) .
				'</div>';
		}

		$tickets_obj = new MHD_Tickets();
		$tickets     = $tickets_obj->get_user_tickets( get_current_user_id() );

		if ( ! empty( $atts['status'] ) ) {
			$filter_status = sanitize_text_field( $atts['status'] );
			$tickets       = array_filter(
				$tickets,
				function ( $ticket ) use ( $filter_status ) {
					return $ticket->status === $filter_status;
				}
			);
		}

		ob_start();
		?>
		<div class="mhd-my-tickets-wrap">
			<?php if ( empty( $tickets ) ) : ?>
				<p class="mhd-no-tickets"><?php esc_html_e( 'You have no tickets.', 'myhelpdesk-chat' ); ?></p>
			<?php else : ?>
				<table class="mhd-tickets-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'myhelpdesk-chat' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'myhelpdesk-chat' ); ?></th>
							<th><?php esc_html_e( 'Status', 'myhelpdesk-chat' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'myhelpdesk-chat' ); ?></th>
							<th><?php esc_html_e( 'Date', 'myhelpdesk-chat' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tickets as $ticket ) : ?>
							<tr class="mhd-ticket-row" data-ticket-id="<?php echo esc_attr( $ticket->id ); ?>">
								<td><?php echo esc_html( $ticket->id ); ?></td>
								<td><?php echo esc_html( $ticket->subject ); ?></td>
								<td>
									<span class="mhd-ticket-status mhd-status-<?php echo esc_attr( $ticket->status ); ?>">
										<?php echo esc_html( ucfirst( $ticket->status ) ); ?>
									</span>
								</td>
								<td>
									<span class="mhd-ticket-priority mhd-priority-<?php echo esc_attr( $ticket->priority ); ?>">
										<?php echo esc_html( ucfirst( $ticket->priority ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $ticket->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode callback to display the full knowledge base.
	 *
	 * Renders a search bar, categories, and article listings.
	 * Usage: [mhd_knowledge_base]
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Knowledge base HTML.
	 */
	public function shortcode_knowledge_base( $atts ) {
		$atts = shortcode_atts(
			array(
				'per_page' => get_option( 'mhd_kb_per_page', '10' ),
			),
			$atts,
			'mhd_knowledge_base'
		);

		$kb         = new MHD_Knowledge_Base();
		$categories = $kb->get_categories();
		$articles   = $kb->get_articles(
			array(
				'status'   => 'published',
				'per_page' => absint( $atts['per_page'] ),
			)
		);

		ob_start();
		?>
		<div class="mhd-kb-wrap">
			<div class="mhd-kb-search">
				<form class="mhd-kb-search-form" method="get">
					<input
						type="text"
						class="mhd-kb-search-input"
						name="mhd_kb_q"
						placeholder="<?php esc_attr_e( 'Search the knowledge base…', 'myhelpdesk-chat' ); ?>"
						value="<?php echo esc_attr( isset( $_GET['mhd_kb_q'] ) ? sanitize_text_field( wp_unslash( $_GET['mhd_kb_q'] ) ) : '' ); ?>"
					/>
					<button type="submit" class="mhd-btn mhd-btn-primary">
						<?php esc_html_e( 'Search', 'myhelpdesk-chat' ); ?>
					</button>
				</form>
			</div>

			<?php
			// If a search query is present, show search results.
			if ( ! empty( $_GET['mhd_kb_q'] ) ) :
				$query          = sanitize_text_field( wp_unslash( $_GET['mhd_kb_q'] ) );
				$search_results = $kb->search_articles( $query );
				?>
				<div class="mhd-kb-search-results">
					<h3>
						<?php
						printf(
							/* translators: %s: search query */
							esc_html__( 'Search results for "%s"', 'myhelpdesk-chat' ),
							esc_html( $query )
						);
						?>
					</h3>
					<?php if ( empty( $search_results ) ) : ?>
						<p class="mhd-no-results"><?php esc_html_e( 'No articles found.', 'myhelpdesk-chat' ); ?></p>
					<?php else : ?>
						<ul class="mhd-kb-article-list">
							<?php foreach ( $search_results as $article ) : ?>
								<li class="mhd-kb-article-item" data-article-id="<?php echo esc_attr( $article->id ); ?>">
									<a href="#" class="mhd-kb-article-link" data-id="<?php echo esc_attr( $article->id ); ?>">
										<?php echo esc_html( $article->title ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $categories ) ) : ?>
				<div class="mhd-kb-categories">
					<?php foreach ( $categories as $category ) : ?>
						<div class="mhd-kb-category" data-category-id="<?php echo esc_attr( $category->id ); ?>">
							<?php if ( ! empty( $category->icon ) ) : ?>
								<span class="mhd-kb-category-icon"><?php echo esc_html( $category->icon ); ?></span>
							<?php endif; ?>
							<h3 class="mhd-kb-category-title"><?php echo esc_html( $category->name ); ?></h3>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $articles ) ) : ?>
				<div class="mhd-kb-articles">
					<ul class="mhd-kb-article-list">
						<?php foreach ( $articles as $article ) : ?>
							<li class="mhd-kb-article-item" data-article-id="<?php echo esc_attr( $article->id ); ?>">
								<a href="#" class="mhd-kb-article-link" data-id="<?php echo esc_attr( $article->id ); ?>">
									<?php echo esc_html( $article->title ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode callback to display a single knowledge-base article.
	 *
	 * Usage: [mhd_kb_article id="42"]
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes. Requires 'id'.
	 * @return string Article HTML or error notice.
	 */
	public function shortcode_kb_article( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'mhd_kb_article'
		);

		$article_id = absint( $atts['id'] );
		if ( ! $article_id ) {
			return '<div class="mhd-notice mhd-notice-error">' .
				esc_html__( 'No article ID specified.', 'myhelpdesk-chat' ) .
				'</div>';
		}

		$kb      = new MHD_Knowledge_Base();
		$article = $kb->get_article( $article_id );

		if ( ! $article ) {
			return '<div class="mhd-notice mhd-notice-error">' .
				esc_html__( 'Article not found.', 'myhelpdesk-chat' ) .
				'</div>';
		}

		$kb->increment_views( $article_id );

		ob_start();
		?>
		<div class="mhd-kb-article-wrap" data-article-id="<?php echo esc_attr( $article->id ); ?>">
			<h2 class="mhd-kb-article-title"><?php echo esc_html( $article->title ); ?></h2>
			<div class="mhd-kb-article-content">
				<?php echo wp_kses_post( $article->content ); ?>
			</div>
			<div class="mhd-kb-article-footer">
				<span class="mhd-kb-article-views">
					<?php
					printf(
						/* translators: %s: number of views */
						esc_html__( '%s views', 'myhelpdesk-chat' ),
						esc_html( number_format_i18n( $article->views ) )
					);
					?>
				</span>
				<div class="mhd-kb-helpful">
					<span class="mhd-kb-helpful-label"><?php esc_html_e( 'Was this article helpful?', 'myhelpdesk-chat' ); ?></span>
					<button type="button" class="mhd-btn mhd-kb-helpful-yes" data-article-id="<?php echo esc_attr( $article->id ); ?>">
						<?php esc_html_e( 'Yes', 'myhelpdesk-chat' ); ?>
					</button>
					<button type="button" class="mhd-btn mhd-kb-helpful-no" data-article-id="<?php echo esc_attr( $article->id ); ?>">
						<?php esc_html_e( 'No', 'myhelpdesk-chat' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode callback to display a knowledge-base search bar.
	 *
	 * Usage: [mhd_kb_search]
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Search bar HTML.
	 */
	public function shortcode_kb_search( $atts ) {
		$atts = shortcode_atts(
			array(
				'placeholder' => __( 'Search articles…', 'myhelpdesk-chat' ),
			),
			$atts,
			'mhd_kb_search'
		);

		ob_start();
		?>
		<div class="mhd-kb-search-wrap">
			<form class="mhd-kb-search-form" method="get">
				<input
					type="text"
					class="mhd-kb-search-input"
					name="mhd_kb_q"
					placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
					value="<?php echo esc_attr( isset( $_GET['mhd_kb_q'] ) ? sanitize_text_field( wp_unslash( $_GET['mhd_kb_q'] ) ) : '' ); ?>"
				/>
				<button type="submit" class="mhd-btn mhd-btn-primary">
					<?php esc_html_e( 'Search', 'myhelpdesk-chat' ); ?>
				</button>
			</form>
			<div class="mhd-kb-search-results-inline"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Determine whether the chat widget should display on the current page.
	 *
	 * Returns false when:
	 * - The `mhd_chat_enabled` option is not '1'.
	 * - The current page/post ID appears in the `mhd_excluded_pages` option.
	 * - The request is for an admin page, a REST request, or an AJAX request.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the chat should be rendered, false otherwise.
	 */
	public function is_chat_enabled() {
		if ( is_admin() ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		$enabled = get_option( 'mhd_chat_enabled', '1' );
		if ( '1' !== $enabled ) {
			return false;
		}

		$excluded_pages = get_option( 'mhd_excluded_pages', array() );
		if ( ! is_array( $excluded_pages ) ) {
			$excluded_pages = array_map( 'trim', explode( ',', $excluded_pages ) );
		}

		if ( ! empty( $excluded_pages ) ) {
			$current_page_id = get_queried_object_id();
			if ( $current_page_id && in_array( (string) $current_page_id, array_map( 'strval', $excluded_pages ), true ) ) {
				return false;
			}
		}

		return true;
	}
}
