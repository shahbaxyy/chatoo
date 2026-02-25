<?php
/**
 * Admin Departments view.
 *
 * Displays the list of departments with colour badges and agent counts,
 * and provides an inline form to add or edit departments.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$departments_obj  = new MHD_Departments();
$departments_list = $departments_obj->get_departments();
$agents_obj       = new MHD_Agents();
$agents_list      = $agents_obj->get_agents();
?>
<div class="wrap mhd-departments-page">

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Departments', 'myhelpdesk-chat' ); ?></h1>
	<button type="button" id="mhd-add-dept-btn" class="page-title-action">
		<?php esc_html_e( 'Add Department', 'myhelpdesk-chat' ); ?>
	</button>
	<hr class="wp-header-end" />

	<!-- ============================================================
		 Departments List Table
	============================================================= -->
	<table class="wp-list-table widefat fixed striped" id="mhd-departments-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Description', 'myhelpdesk-chat' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Color', 'myhelpdesk-chat' ); ?></th>
				<th style="width:100px;"><?php esc_html_e( 'Agents', 'myhelpdesk-chat' ); ?></th>
				<th style="width:130px;"><?php esc_html_e( 'Actions', 'myhelpdesk-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $departments_list ) ) : ?>
				<tr>
					<td colspan="5" style="text-align:center;padding:20px;">
						<?php esc_html_e( 'No departments found. Click "Add Department" to create one.', 'myhelpdesk-chat' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $departments_list as $dept ) : ?>
					<?php
					// Count agents belonging to this department.
					$dept_agent_count = 0;
					foreach ( $agents_list as $agent ) {
						if ( (int) $agent->department_id === (int) $dept->id ) {
							++$dept_agent_count;
						}
					}
					$dept_color = ! empty( $dept->color ) ? $dept->color : '#0073aa';
					?>
					<tr data-dept-id="<?php echo esc_attr( $dept->id ); ?>">
						<td>
							<strong><?php echo esc_html( $dept->name ); ?></strong>
						</td>
						<td><?php echo esc_html( $dept->description ); ?></td>
						<td>
							<span class="mhd-color-badge" style="display:inline-block;width:24px;height:24px;border-radius:4px;background:<?php echo esc_attr( $dept_color ); ?>;"></span>
						</td>
						<td><?php echo esc_html( $dept_agent_count ); ?></td>
						<td>
							<button type="button"
								class="button button-small mhd-edit-dept"
								data-dept-id="<?php echo esc_attr( $dept->id ); ?>"
								data-name="<?php echo esc_attr( $dept->name ); ?>"
								data-description="<?php echo esc_attr( $dept->description ); ?>"
								data-color="<?php echo esc_attr( $dept_color ); ?>">
								<?php esc_html_e( 'Edit', 'myhelpdesk-chat' ); ?>
							</button>
							<button type="button"
								class="button button-small button-link-delete mhd-delete-dept"
								data-dept-id="<?php echo esc_attr( $dept->id ); ?>">
								<?php esc_html_e( 'Delete', 'myhelpdesk-chat' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- ============================================================
		 Add / Edit Department Form
	============================================================= -->
	<div id="mhd-dept-form-wrap" style="display:none;margin-top:20px;background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px;max-width:600px;">
		<h2 id="mhd-dept-form-title"><?php esc_html_e( 'Add Department', 'myhelpdesk-chat' ); ?></h2>
		<form id="mhd-dept-form">
			<input type="hidden" id="mhd-dept-id" name="dept_id" value="" />

			<table class="form-table">
				<!-- Name -->
				<tr>
					<th scope="row">
						<label for="mhd-dept-name"><?php esc_html_e( 'Name', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="mhd-dept-name" name="name" class="regular-text" required />
					</td>
				</tr>

				<!-- Description -->
				<tr>
					<th scope="row">
						<label for="mhd-dept-desc"><?php esc_html_e( 'Description', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<textarea id="mhd-dept-desc" name="description" rows="3" class="large-text"></textarea>
					</td>
				</tr>

				<!-- Color Picker -->
				<tr>
					<th scope="row">
						<label for="mhd-dept-color"><?php esc_html_e( 'Color', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<input type="color" id="mhd-dept-color" name="color" value="#0073aa" />
					</td>
				</tr>

				<!-- Agent Multi-select -->
				<tr>
					<th scope="row">
						<label for="mhd-dept-agents"><?php esc_html_e( 'Agents', 'myhelpdesk-chat' ); ?></label>
					</th>
					<td>
						<select id="mhd-dept-agents" name="agent_ids[]" multiple style="width:100%;min-height:120px;">
							<?php foreach ( $agents_list as $agent ) : ?>
								<option value="<?php echo esc_attr( $agent->id ); ?>">
									<?php echo esc_html( $agent->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Hold Ctrl/Cmd to select multiple agents.', 'myhelpdesk-chat' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div style="margin-top:15px;">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Department', 'myhelpdesk-chat' ); ?>
				</button>
				<button type="button" id="mhd-dept-form-cancel" class="button">
					<?php esc_html_e( 'Cancel', 'myhelpdesk-chat' ); ?>
				</button>
			</div>
		</form>
	</div>

</div><!-- .wrap -->
