<?php
/**
 * Admin Automations view.
 *
 * Lists automation rules and provides a form to create or edit rules
 * with trigger selection, conditions builder, and action configuration.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$automations_obj = new MHD_Automations();
$automations     = $automations_obj->get_automations();
?>
<div class="wrap mhd-automations-page">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Automations', 'myhelpdesk-chat' ); ?></h1>
	<button type="button" id="mhd-add-automation-btn" class="page-title-action">
		<?php esc_html_e( 'Add Automation', 'myhelpdesk-chat' ); ?>
	</button>
	<hr class="wp-header-end" />

	<!-- ============================================================
		 Automations List Table
	============================================================= -->
	<table class="wp-list-table widefat fixed striped" id="mhd-automations-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Trigger', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Action', 'myhelpdesk-chat' ); ?></th>
				<th style="width:90px;"><?php esc_html_e( 'Active', 'myhelpdesk-chat' ); ?></th>
				<th style="width:130px;"><?php esc_html_e( 'Actions', 'myhelpdesk-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $automations ) ) : ?>
				<tr>
					<td colspan="5" style="text-align:center;padding:20px;">
						<?php esc_html_e( 'No automations found.', 'myhelpdesk-chat' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $automations as $auto ) : ?>
					<tr data-automation-id="<?php echo esc_attr( $auto->id ); ?>">
						<td><strong><?php echo esc_html( $auto->name ); ?></strong></td>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $auto->trigger_type ) ) ); ?></td>
						<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $auto->action_type ) ) ); ?></td>
						<td>
							<label class="mhd-toggle-switch">
								<input type="checkbox"
									class="mhd-automation-toggle"
									data-automation-id="<?php echo esc_attr( $auto->id ); ?>"
									<?php checked( $auto->is_active, 1 ); ?> />
								<span><?php echo esc_html( $auto->is_active ? __( 'On', 'myhelpdesk-chat' ) : __( 'Off', 'myhelpdesk-chat' ) ); ?></span>
							</label>
						</td>
						<td>
							<button type="button"
								class="button button-small mhd-edit-automation"
								data-automation-id="<?php echo esc_attr( $auto->id ); ?>">
								<?php esc_html_e( 'Edit', 'myhelpdesk-chat' ); ?>
							</button>
							<button type="button"
								class="button button-small button-link-delete mhd-delete-automation"
								data-automation-id="<?php echo esc_attr( $auto->id ); ?>">
								<?php esc_html_e( 'Delete', 'myhelpdesk-chat' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- ============================================================
		 Add / Edit Automation Form
	============================================================= -->
	<div id="mhd-automation-form-wrap" style="display:none;margin-top:20px;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:4px;max-width:700px;">
		<h2 id="mhd-automation-form-title"><?php esc_html_e( 'Add Automation', 'myhelpdesk-chat' ); ?></h2>
		<form id="mhd-automation-form">
			<input type="hidden" id="mhd-automation-id" name="automation_id" value="" />

			<table class="form-table">
				<!-- Name -->
				<tr>
					<th scope="row">
						<label for="mhd-auto-name"><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd-auto-name" name="name" class="regular-text" required />
					</td>
				</tr>

				<!-- Trigger -->
				<tr>
					<th scope="row">
						<label for="mhd-auto-trigger"><?php esc_html_e( 'Trigger', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd-auto-trigger" name="trigger_type" style="width:100%;">
							<option value=""><?php esc_html_e( '— Select Trigger —', 'myhelpdesk-chat' ); ?></option>
							<option value="new_conversation"><?php esc_html_e( 'New Conversation', 'myhelpdesk-chat' ); ?></option>
							<option value="new_ticket"><?php esc_html_e( 'New Ticket', 'myhelpdesk-chat' ); ?></option>
							<option value="conversation_assigned"><?php esc_html_e( 'Conversation Assigned', 'myhelpdesk-chat' ); ?></option>
							<option value="ticket_status_changed"><?php esc_html_e( 'Ticket Status Changed', 'myhelpdesk-chat' ); ?></option>
							<option value="agent_offline"><?php esc_html_e( 'All Agents Offline', 'myhelpdesk-chat' ); ?></option>
							<option value="inactivity_timeout"><?php esc_html_e( 'Inactivity Timeout', 'myhelpdesk-chat' ); ?></option>
						</select>
					</td>
				</tr>

				<!-- Conditions Builder -->
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Conditions', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<div id="mhd-auto-conditions" class="mhd-conditions-builder">
							<div class="mhd-condition-row" style="display:flex;gap:8px;margin-bottom:8px;">
								<select name="condition_field[]" style="flex:1;">
									<option value=""><?php esc_html_e( '— Field —', 'myhelpdesk-chat' ); ?></option>
									<option value="department"><?php esc_html_e( 'Department', 'myhelpdesk-chat' ); ?></option>
									<option value="status"><?php esc_html_e( 'Status', 'myhelpdesk-chat' ); ?></option>
									<option value="priority"><?php esc_html_e( 'Priority', 'myhelpdesk-chat' ); ?></option>
									<option value="source"><?php esc_html_e( 'Source', 'myhelpdesk-chat' ); ?></option>
									<option value="message_content"><?php esc_html_e( 'Message Content', 'myhelpdesk-chat' ); ?></option>
								</select>
								<select name="condition_operator[]" style="width:120px;">
									<option value="equals"><?php esc_html_e( 'Equals', 'myhelpdesk-chat' ); ?></option>
									<option value="not_equals"><?php esc_html_e( 'Not Equals', 'myhelpdesk-chat' ); ?></option>
									<option value="contains"><?php esc_html_e( 'Contains', 'myhelpdesk-chat' ); ?></option>
								</select>
								<input type="text" name="condition_value[]" class="regular-text" style="flex:1;"
									placeholder="<?php esc_attr_e( 'Value', 'myhelpdesk-chat' ); ?>" />
							</div>
						</div>
						<button type="button" id="mhd-add-condition-row" class="button button-small">
							<?php esc_html_e( '+ Add Condition', 'myhelpdesk-chat' ); ?>
						</button>
					</td>
				</tr>

				<!-- Action -->
				<tr>
					<th scope="row">
						<label for="mhd-auto-action"><?php esc_html_e( 'Action', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd-auto-action" name="action_type" style="width:100%;">
							<option value=""><?php esc_html_e( '— Select Action —', 'myhelpdesk-chat' ); ?></option>
							<option value="assign_agent"><?php esc_html_e( 'Assign Agent', 'myhelpdesk-chat' ); ?></option>
							<option value="assign_department"><?php esc_html_e( 'Assign Department', 'myhelpdesk-chat' ); ?></option>
							<option value="send_message"><?php esc_html_e( 'Send Auto-Reply', 'myhelpdesk-chat' ); ?></option>
							<option value="change_status"><?php esc_html_e( 'Change Status', 'myhelpdesk-chat' ); ?></option>
							<option value="send_email"><?php esc_html_e( 'Send Email Notification', 'myhelpdesk-chat' ); ?></option>
							<option value="set_priority"><?php esc_html_e( 'Set Priority', 'myhelpdesk-chat' ); ?></option>
							<option value="add_tag"><?php esc_html_e( 'Add Tag', 'myhelpdesk-chat' ); ?></option>
						</select>
					</td>
				</tr>

				<!-- Action Data -->
				<tr>
					<th scope="row">
						<label for="mhd-auto-action-data"><?php esc_html_e( 'Action Data', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<textarea id="mhd-auto-action-data" name="action_data" rows="3" class="large-text"
							placeholder="<?php esc_attr_e( 'JSON or value for the action', 'myhelpdesk-chat' ); ?>"></textarea>
						<p class="description">
							<?php esc_html_e( 'Enter the data associated with the selected action (e.g. agent ID, message text, department ID).', 'myhelpdesk-chat' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div style="margin-top:15px;">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Automation', 'myhelpdesk-chat' ); ?>
				</button>
				<button type="button" id="mhd-automation-form-cancel" class="button">
					<?php esc_html_e( 'Cancel', 'myhelpdesk-chat' ); ?>
				</button>
			</div>
		</form>
	</div>

</div><!-- .wrap -->
