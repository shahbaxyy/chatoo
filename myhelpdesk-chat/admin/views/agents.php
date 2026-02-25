<?php
/**
 * Admin Agents view.
 *
 * Lists all registered agents and provides a modal form to add or
 * edit agent records. Each agent row shows avatar, name, email, role,
 * department, online status, and last-seen timestamp.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$agents_obj       = new MHD_Agents();
$agents_list      = $agents_obj->get_agents();
$departments_obj  = new MHD_Departments();
$departments_list = $departments_obj->get_departments();
?>
<div class="wrap mhd-agents-page">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Agents', 'myhelpdesk-chat' ); ?></h1>
	<button type="button" id="mhd-add-agent-btn" class="page-title-action">
		<?php esc_html_e( 'Add Agent', 'myhelpdesk-chat' ); ?>
	</button>
	<hr class="wp-header-end" />

	<!-- ============================================================
		 Agents List Table
	============================================================= -->
	<table class="wp-list-table widefat fixed striped" id="mhd-agents-table">
		<thead>
			<tr>
				<th style="width:50px;"><?php esc_html_e( 'Avatar', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Email', 'myhelpdesk-chat' ); ?></th>
				<th style="width:100px;"><?php esc_html_e( 'Role', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Department', 'myhelpdesk-chat' ); ?></th>
				<th style="width:90px;"><?php esc_html_e( 'Status', 'myhelpdesk-chat' ); ?></th>
				<th style="width:150px;"><?php esc_html_e( 'Last Seen', 'myhelpdesk-chat' ); ?></th>
				<th style="width:130px;"><?php esc_html_e( 'Actions', 'myhelpdesk-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $agents_list ) ) : ?>
				<tr>
					<td colspan="8" style="text-align:center;padding:20px;">
						<?php esc_html_e( 'No agents found. Click "Add Agent" to create one.', 'myhelpdesk-chat' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $agents_list as $agent ) : ?>
					<tr data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
						<td>
							<?php echo get_avatar( (int) $agent->user_id, 32 ); ?>
						</td>
						<td><?php echo esc_html( $agent->display_name ); ?></td>
						<td><?php echo esc_html( $agent->user_email ); ?></td>
						<td><?php echo esc_html( ucfirst( $agent->role ) ); ?></td>
						<td>
							<?php
							$dept_name = '—';
							foreach ( $departments_list as $dept ) {
								if ( (int) $dept->id === (int) $agent->department_id ) {
									$dept_name = $dept->name;
									break;
								}
							}
							echo esc_html( $dept_name );
							?>
						</td>
						<td>
							<span class="mhd-status-badge mhd-status-<?php echo esc_attr( $agent->status ); ?>">
								<?php echo esc_html( ucfirst( $agent->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $agent->last_seen ); ?></td>
						<td>
							<button type="button"
								class="button button-small mhd-edit-agent"
								data-agent-id="<?php echo esc_attr( $agent->id ); ?>"
								data-user-id="<?php echo esc_attr( $agent->user_id ); ?>"
								data-role="<?php echo esc_attr( $agent->role ); ?>"
								data-department="<?php echo esc_attr( $agent->department_id ); ?>"
								data-max-chats="<?php echo esc_attr( $agent->max_chats ); ?>">
								<?php esc_html_e( 'Edit', 'myhelpdesk-chat' ); ?>
							</button>
							<button type="button"
								class="button button-small button-link-delete mhd-remove-agent"
								data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
								<?php esc_html_e( 'Remove', 'myhelpdesk-chat' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- ============================================================
		 Add / Edit Agent Modal
	============================================================= -->
	<div id="mhd-agent-modal" class="mhd-modal" style="display:none;">
		<div class="mhd-modal-backdrop"></div>
		<div class="mhd-modal-content" style="background:#fff;max-width:500px;margin:80px auto;padding:25px;border-radius:4px;position:relative;z-index:100001;">
			<h2 id="mhd-agent-modal-title"><?php esc_html_e( 'Add Agent', 'myhelpdesk-chat' ); ?></h2>
			<form id="mhd-agent-form">
				<input type="hidden" id="mhd-agent-id" name="agent_id" value="" />

				<table class="form-table">
					<!-- WordPress User -->
					<tr>
						<th scope="row">
							<label for="mhd-agent-user-id"><?php esc_html_e( 'WordPress User', 'myhelpdesk-chat' ); ?></label>
						</th>
						<td>
							<select id="mhd-agent-user-id" name="user_id" required style="width:100%;">
								<option value=""><?php esc_html_e( '— Select User —', 'myhelpdesk-chat' ); ?></option>
								<?php
								$wp_users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
								foreach ( $wp_users as $wp_user ) :
									?>
									<option value="<?php echo esc_attr( $wp_user->ID ); ?>">
										<?php echo esc_html( $wp_user->display_name . ' (' . $wp_user->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<!-- Role -->
					<tr>
						<th scope="row">
							<label for="mhd-agent-role"><?php esc_html_e( 'Role', 'myhelpdesk-chat' ); ?></label>
						</th>
						<td>
							<select id="mhd-agent-role" name="role" style="width:100%;">
								<option value="agent"><?php esc_html_e( 'Agent', 'myhelpdesk-chat' ); ?></option>
								<option value="admin"><?php esc_html_e( 'Admin', 'myhelpdesk-chat' ); ?></option>
								<option value="supervisor"><?php esc_html_e( 'Supervisor', 'myhelpdesk-chat' ); ?></option>
							</select>
						</td>
					</tr>

					<!-- Department -->
					<tr>
						<th scope="row">
							<label for="mhd-agent-dept"><?php esc_html_e( 'Department', 'myhelpdesk-chat' ); ?></label>
						</th>
						<td>
							<select id="mhd-agent-dept" name="department_id" style="width:100%;">
								<option value=""><?php esc_html_e( 'None', 'myhelpdesk-chat' ); ?></option>
								<?php foreach ( $departments_list as $dept ) : ?>
									<option value="<?php echo esc_attr( $dept->id ); ?>">
										<?php echo esc_html( $dept->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<!-- Max Concurrent Chats -->
					<tr>
						<th scope="row">
							<label for="mhd-agent-max-chats"><?php esc_html_e( 'Max Chats', 'myhelpdesk-chat' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="mhd-agent-max-chats"
								name="max_chats"
								min="1"
								max="50"
								value="5"
								class="small-text" />
						</td>
					</tr>
				</table>

				<div style="text-align:right;margin-top:15px;">
					<button type="button" id="mhd-agent-modal-cancel" class="button">
						<?php esc_html_e( 'Cancel', 'myhelpdesk-chat' ); ?>
					</button>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Agent', 'myhelpdesk-chat' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>

</div><!-- .wrap -->
