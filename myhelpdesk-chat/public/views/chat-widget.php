<?php
/**
 * Chat widget template.
 *
 * Renders the floating chat widget HTML including the bubble button,
 * chat window, pre-chat form, KB search area, message composer,
 * rating widget, offline form, and powered-by branding.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$mhd_company_name    = get_option( 'mhd_company_name', 'Support Chat' );
$mhd_primary_color   = get_option( 'mhd_primary_color', '#0073aa' );
$mhd_widget_position = get_option( 'mhd_widget_position', 'bottom-right' );
$mhd_widget_icon     = get_option( 'mhd_widget_icon', 'chat' );
$mhd_powered_by      = get_option( 'mhd_powered_by', '1' );
$mhd_prechat_form    = get_option( 'mhd_pre_chat_form', '1' );
$mhd_prechat_dept    = get_option( 'mhd_prechat_department', '0' );
$mhd_gdpr_enabled    = get_option( 'mhd_gdpr_enabled', '0' );
$mhd_gdpr_message    = get_option( 'mhd_gdpr_message', '' );
$mhd_file_uploads    = get_option( 'mhd_file_uploads', '1' );
$mhd_kb_in_widget    = get_option( 'mhd_kb_in_widget', '0' );
$mhd_rating_enabled  = get_option( 'mhd_chat_rating', '1' );

$mhd_departments = array();
if ( '1' === $mhd_prechat_dept ) {
	$departments_obj = new MHD_Departments();
	$mhd_departments = $departments_obj->get_departments();
}
?>

<!-- MyHelpDesk Chat Widget -->
<div id="mhd-chat-widget" class="mhd-widget mhd-position-<?php echo esc_attr( $mhd_widget_position ); ?>" data-position="<?php echo esc_attr( $mhd_widget_position ); ?>">

	<!-- Chat Bubble / Toggle Button -->
	<button type="button" id="mhd-chat-bubble" class="mhd-chat-bubble" aria-label="<?php esc_attr_e( 'Open chat', 'myhelpdesk-chat' ); ?>">
		<span class="mhd-bubble-icon mhd-icon-<?php echo esc_attr( $mhd_widget_icon ); ?>"></span>
		<span class="mhd-bubble-close-icon"></span>
		<span id="mhd-unread-badge" class="mhd-unread-badge mhd-hidden" aria-live="polite">0</span>
	</button>

	<!-- Chat Window -->
	<div id="mhd-chat-window" class="mhd-chat-window mhd-hidden" role="dialog" aria-label="<?php esc_attr_e( 'Chat window', 'myhelpdesk-chat' ); ?>">

		<!-- Header -->
		<div class="mhd-chat-header">
			<div class="mhd-header-agent">
				<div class="mhd-agent-avatar">
					<span class="mhd-avatar-placeholder"></span>
					<span id="mhd-agent-status-dot" class="mhd-status-dot mhd-status-offline"></span>
				</div>
				<div class="mhd-header-info">
					<span class="mhd-header-title"><?php echo esc_html( $mhd_company_name ); ?></span>
					<span id="mhd-agent-status-text" class="mhd-header-status"><?php esc_html_e( 'We reply as soon as we can', 'myhelpdesk-chat' ); ?></span>
				</div>
			</div>
			<div class="mhd-header-actions">
				<button type="button" id="mhd-minimize-btn" class="mhd-header-btn" aria-label="<?php esc_attr_e( 'Minimize chat', 'myhelpdesk-chat' ); ?>">
					<span class="mhd-icon-minimize"></span>
				</button>
				<button type="button" id="mhd-close-btn" class="mhd-header-btn" aria-label="<?php esc_attr_e( 'Close chat', 'myhelpdesk-chat' ); ?>">
					<span class="mhd-icon-close"></span>
				</button>
			</div>
		</div>

		<!-- Body -->
		<div class="mhd-chat-body">

			<!-- Pre-chat Form -->
			<?php if ( '1' === $mhd_prechat_form && ! is_user_logged_in() ) : ?>
				<div id="mhd-prechat-form" class="mhd-prechat-form">
					<p class="mhd-prechat-intro"><?php esc_html_e( 'Please fill in the form below to start a chat.', 'myhelpdesk-chat' ); ?></p>
					<div class="mhd-form-field">
						<label for="mhd-prechat-name"><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></label>
						<input type="text" id="mhd-prechat-name" class="mhd-input" placeholder="<?php esc_attr_e( 'Your name', 'myhelpdesk-chat' ); ?>" required />
					</div>
					<div class="mhd-form-field">
						<label for="mhd-prechat-email"><?php esc_html_e( 'Email', 'myhelpdesk-chat' ); ?></label>
						<input type="email" id="mhd-prechat-email" class="mhd-input" placeholder="<?php esc_attr_e( 'Your email', 'myhelpdesk-chat' ); ?>" required />
					</div>

					<?php if ( '1' === $mhd_prechat_dept && ! empty( $mhd_departments ) ) : ?>
						<div class="mhd-form-field">
							<label for="mhd-prechat-department"><?php esc_html_e( 'Department', 'myhelpdesk-chat' ); ?></label>
							<select id="mhd-prechat-department" class="mhd-select">
								<option value=""><?php esc_html_e( 'Select a department', 'myhelpdesk-chat' ); ?></option>
								<?php foreach ( $mhd_departments as $dept ) : ?>
									<option value="<?php echo esc_attr( $dept->id ); ?>">
										<?php echo esc_html( $dept->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ( '1' === $mhd_gdpr_enabled ) : ?>
						<div class="mhd-form-field mhd-gdpr-field">
							<label class="mhd-checkbox-label">
								<input type="checkbox" id="mhd-prechat-gdpr" class="mhd-checkbox" required />
								<span class="mhd-gdpr-text">
									<?php
									if ( ! empty( $mhd_gdpr_message ) ) {
										echo esc_html( $mhd_gdpr_message );
									} else {
										esc_html_e( 'I agree to the processing of my personal data.', 'myhelpdesk-chat' );
									}
									?>
								</span>
							</label>
						</div>
					<?php endif; ?>

					<button type="button" id="mhd-prechat-submit" class="mhd-btn mhd-btn-primary">
						<?php esc_html_e( 'Start Chat', 'myhelpdesk-chat' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<!-- Knowledge Base Search Area -->
			<?php if ( '1' === $mhd_kb_in_widget ) : ?>
				<div id="mhd-kb-area" class="mhd-kb-area mhd-hidden">
					<div class="mhd-kb-search-bar">
						<input
							type="text"
							id="mhd-kb-widget-search"
							class="mhd-input"
							placeholder="<?php esc_attr_e( 'Search for help…', 'myhelpdesk-chat' ); ?>"
						/>
					</div>
					<ul id="mhd-kb-results" class="mhd-kb-results-list"></ul>
					<div id="mhd-kb-article-view" class="mhd-kb-article-view mhd-hidden"></div>
					<button type="button" id="mhd-kb-need-help" class="mhd-btn mhd-btn-link mhd-hidden">
						<?php esc_html_e( 'Still need help? Chat with us', 'myhelpdesk-chat' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<!-- Messages Container -->
			<div id="mhd-messages" class="mhd-messages" role="log" aria-live="polite"></div>

			<!-- Typing Indicator -->
			<div id="mhd-typing-indicator" class="mhd-typing-indicator mhd-hidden" aria-live="polite">
				<span class="mhd-typing-dot"></span>
				<span class="mhd-typing-dot"></span>
				<span class="mhd-typing-dot"></span>
			</div>

			<!-- Rating Widget -->
			<?php if ( '1' === $mhd_rating_enabled ) : ?>
				<div id="mhd-rating-widget" class="mhd-rating-widget mhd-hidden">
					<p class="mhd-rating-prompt"><?php esc_html_e( 'How was your experience?', 'myhelpdesk-chat' ); ?></p>
					<div class="mhd-rating-buttons">
						<button type="button" class="mhd-rating-btn mhd-rating-up" data-rating="positive" aria-label="<?php esc_attr_e( 'Thumbs up', 'myhelpdesk-chat' ); ?>">
							<span class="mhd-icon-thumbs-up"></span>
						</button>
						<button type="button" class="mhd-rating-btn mhd-rating-down" data-rating="negative" aria-label="<?php esc_attr_e( 'Thumbs down', 'myhelpdesk-chat' ); ?>">
							<span class="mhd-icon-thumbs-down"></span>
						</button>
					</div>
					<div id="mhd-rating-comment" class="mhd-rating-comment mhd-hidden">
						<textarea
							id="mhd-rating-comment-input"
							class="mhd-textarea"
							rows="3"
							placeholder="<?php esc_attr_e( 'Any additional comments?', 'myhelpdesk-chat' ); ?>"
						></textarea>
						<button type="button" id="mhd-rating-submit" class="mhd-btn mhd-btn-primary">
							<?php esc_html_e( 'Submit', 'myhelpdesk-chat' ); ?>
						</button>
					</div>
					<div id="mhd-rating-thanks" class="mhd-rating-thanks mhd-hidden">
						<p><?php esc_html_e( 'Thank you for your feedback!', 'myhelpdesk-chat' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<!-- Offline Form -->
			<div id="mhd-offline-form" class="mhd-offline-form mhd-hidden">
				<p class="mhd-offline-intro"><?php esc_html_e( 'We are currently offline. Leave us a message and we will get back to you.', 'myhelpdesk-chat' ); ?></p>
				<div class="mhd-form-field">
					<label for="mhd-offline-name"><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></label>
					<input type="text" id="mhd-offline-name" class="mhd-input" placeholder="<?php esc_attr_e( 'Your name', 'myhelpdesk-chat' ); ?>" required />
				</div>
				<div class="mhd-form-field">
					<label for="mhd-offline-email"><?php esc_html_e( 'Email', 'myhelpdesk-chat' ); ?></label>
					<input type="email" id="mhd-offline-email" class="mhd-input" placeholder="<?php esc_attr_e( 'Your email', 'myhelpdesk-chat' ); ?>" required />
				</div>
				<div class="mhd-form-field">
					<label for="mhd-offline-message"><?php esc_html_e( 'Message', 'myhelpdesk-chat' ); ?></label>
					<textarea id="mhd-offline-message" class="mhd-textarea" rows="4" placeholder="<?php esc_attr_e( 'Type your message…', 'myhelpdesk-chat' ); ?>" required></textarea>
				</div>
				<button type="button" id="mhd-offline-submit" class="mhd-btn mhd-btn-primary">
					<?php esc_html_e( 'Send Message', 'myhelpdesk-chat' ); ?>
				</button>
				<div id="mhd-offline-status" class="mhd-offline-status"></div>
			</div>

		</div><!-- .mhd-chat-body -->

		<!-- Message Composer -->
		<div id="mhd-composer" class="mhd-composer mhd-hidden">
			<div class="mhd-composer-inner">
				<textarea
					id="mhd-message-input"
					class="mhd-message-input"
					rows="1"
					placeholder="<?php esc_attr_e( 'Type a message…', 'myhelpdesk-chat' ); ?>"
					aria-label="<?php esc_attr_e( 'Type a message', 'myhelpdesk-chat' ); ?>"
				></textarea>
				<div class="mhd-composer-actions">
					<button type="button" id="mhd-emoji-btn" class="mhd-composer-btn" aria-label="<?php esc_attr_e( 'Insert emoji', 'myhelpdesk-chat' ); ?>">
						<span class="mhd-icon-emoji"></span>
					</button>
					<?php if ( '1' === $mhd_file_uploads ) : ?>
						<button type="button" id="mhd-file-btn" class="mhd-composer-btn" aria-label="<?php esc_attr_e( 'Attach file', 'myhelpdesk-chat' ); ?>">
							<span class="mhd-icon-attachment"></span>
						</button>
						<input type="file" id="mhd-file-input" class="mhd-hidden" />
					<?php endif; ?>
					<button type="button" id="mhd-send-btn" class="mhd-composer-btn mhd-send-btn" aria-label="<?php esc_attr_e( 'Send message', 'myhelpdesk-chat' ); ?>">
						<span class="mhd-icon-send"></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Powered By -->
		<?php if ( '1' === $mhd_powered_by ) : ?>
			<div class="mhd-powered-by">
				<a href="https://myhelpdesk.chat" target="_blank" rel="noopener noreferrer">
					<?php
					printf(
						/* translators: %s: brand name */
						esc_html__( 'Powered by %s', 'myhelpdesk-chat' ),
						'MyHelpDesk'
					);
					?>
				</a>
			</div>
		<?php endif; ?>

	</div><!-- #mhd-chat-window -->

</div><!-- #mhd-chat-widget -->
