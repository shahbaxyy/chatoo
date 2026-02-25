<?php
/**
 * Admin Saved Replies view.
 *
 * Lists saved reply templates and provides an inline form
 * to create or edit replies with name, message, category,
 * and visibility (global or personal) options.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Saved replies are stored in a custom table; data is loaded via AJAX
// for full interactivity. The initial list is rendered server-side.
global $wpdb;
$table   = $wpdb->prefix . MHD_TABLE_PREFIX . 'saved_replies';
$replies = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
?>
<div class="wrap mhd-saved-replies-page">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Saved Replies', 'myhelpdesk-chat' ); ?></h1>
	<button type="button" id="mhd-add-reply-btn" class="page-title-action">
		<?php esc_html_e( 'Add Saved Reply', 'myhelpdesk-chat' ); ?>
	</button>
	<hr class="wp-header-end" />

	<!-- ============================================================
		 Saved Replies List Table
	============================================================= -->
	<table class="wp-list-table widefat fixed striped" id="mhd-saved-replies-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Category', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Message Preview', 'myhelpdesk-chat' ); ?></th>
				<th style="width:100px;"><?php esc_html_e( 'Visibility', 'myhelpdesk-chat' ); ?></th>
				<th style="width:130px;"><?php esc_html_e( 'Actions', 'myhelpdesk-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $replies ) ) : ?>
				<tr>
					<td colspan="5" style="text-align:center;padding:20px;">
						<?php esc_html_e( 'No saved replies found.', 'myhelpdesk-chat' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $replies as $reply ) : ?>
					<tr data-reply-id="<?php echo esc_attr( $reply->id ); ?>">
						<td><strong><?php echo esc_html( $reply->name ); ?></strong></td>
						<td><?php echo esc_html( ! empty( $reply->category ) ? $reply->category : '—' ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $reply->message, 15, '…' ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $reply->visibility ) ); ?></td>
						<td>
							<button type="button"
								class="button button-small mhd-edit-reply"
								data-reply-id="<?php echo esc_attr( $reply->id ); ?>"
								data-name="<?php echo esc_attr( $reply->name ); ?>"
								data-message="<?php echo esc_attr( $reply->message ); ?>"
								data-category="<?php echo esc_attr( $reply->category ); ?>"
								data-visibility="<?php echo esc_attr( $reply->visibility ); ?>">
								<?php esc_html_e( 'Edit', 'myhelpdesk-chat' ); ?>
							</button>
							<button type="button"
								class="button button-small button-link-delete mhd-delete-reply"
								data-reply-id="<?php echo esc_attr( $reply->id ); ?>">
								<?php esc_html_e( 'Delete', 'myhelpdesk-chat' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- ============================================================
		 Add / Edit Saved Reply Form
	============================================================= -->
	<div id="mhd-reply-form-wrap" style="display:none;margin-top:20px;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:4px;max-width:600px;">
		<h2 id="mhd-reply-form-title"><?php esc_html_e( 'Add Saved Reply', 'myhelpdesk-chat' ); ?></h2>
		<form id="mhd-reply-form">
			<input type="hidden" id="mhd-reply-id" name="reply_id" value="" />

			<table class="form-table">
				<!-- Name -->
				<tr>
					<th scope="row">
						<label for="mhd-reply-name"><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd-reply-name" name="name" class="regular-text" required />
					</td>
				</tr>

				<!-- Message -->
				<tr>
					<th scope="row">
						<label for="mhd-reply-message"><?php esc_html_e( 'Message', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<textarea id="mhd-reply-message" name="message" rows="6" class="large-text" required></textarea>
						<p class="description">
							<?php esc_html_e( 'You can use placeholders: {name}, {email}, {ticket_id}.', 'myhelpdesk-chat' ); ?>
						</p>
					</td>
				</tr>

				<!-- Category -->
				<tr>
					<th scope="row">
						<label for="mhd-reply-category"><?php esc_html_e( 'Category', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd-reply-category" name="category" class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. Billing, Support', 'myhelpdesk-chat' ); ?>" />
					</td>
				</tr>

				<!-- Visibility -->
				<tr>
					<th scope="row">
						<label for="mhd-reply-visibility"><?php esc_html_e( 'Visibility', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd-reply-visibility" name="visibility">
							<option value="global"><?php esc_html_e( 'Global', 'myhelpdesk-chat' ); ?></option>
							<option value="personal"><?php esc_html_e( 'Personal', 'myhelpdesk-chat' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<div style="margin-top:15px;">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Reply', 'myhelpdesk-chat' ); ?>
				</button>
				<button type="button" id="mhd-reply-form-cancel" class="button">
					<?php esc_html_e( 'Cancel', 'myhelpdesk-chat' ); ?>
				</button>
			</div>
		</form>
	</div>

</div><!-- .wrap -->
