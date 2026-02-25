<?php
/**
 * Admin Tickets view.
 *
 * Displays a filterable ticket list table with status, priority, agent,
 * and department filters, a search box, and a detail / reply panel.
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
<div class="wrap mhd-tickets-page">

	<h1><?php esc_html_e( 'Tickets', 'myhelpdesk-chat' ); ?></h1>

	<!-- ============================================================
		 Filter Bar
	============================================================= -->
	<div class="mhd-ticket-filters" style="display:flex;flex-wrap:wrap;gap:10px;margin:15px 0;align-items:center;">

		<!-- Status Filter -->
		<select id="mhd-ticket-status-filter">
			<option value=""><?php esc_html_e( 'All Statuses', 'myhelpdesk-chat' ); ?></option>
			<option value="open"><?php esc_html_e( 'Open', 'myhelpdesk-chat' ); ?></option>
			<option value="in_progress"><?php esc_html_e( 'In Progress', 'myhelpdesk-chat' ); ?></option>
			<option value="waiting"><?php esc_html_e( 'Waiting', 'myhelpdesk-chat' ); ?></option>
			<option value="resolved"><?php esc_html_e( 'Resolved', 'myhelpdesk-chat' ); ?></option>
			<option value="closed"><?php esc_html_e( 'Closed', 'myhelpdesk-chat' ); ?></option>
		</select>

		<!-- Priority Filter -->
		<select id="mhd-ticket-priority-filter">
			<option value=""><?php esc_html_e( 'All Priorities', 'myhelpdesk-chat' ); ?></option>
			<option value="low"><?php esc_html_e( 'Low', 'myhelpdesk-chat' ); ?></option>
			<option value="medium"><?php esc_html_e( 'Medium', 'myhelpdesk-chat' ); ?></option>
			<option value="high"><?php esc_html_e( 'High', 'myhelpdesk-chat' ); ?></option>
			<option value="urgent"><?php esc_html_e( 'Urgent', 'myhelpdesk-chat' ); ?></option>
		</select>

		<!-- Agent Filter -->
		<select id="mhd-ticket-agent-filter">
			<option value=""><?php esc_html_e( 'All Agents', 'myhelpdesk-chat' ); ?></option>
			<?php foreach ( $agents_list as $agent ) : ?>
				<option value="<?php echo esc_attr( $agent->id ); ?>">
					<?php echo esc_html( $agent->display_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<!-- Department Filter -->
		<select id="mhd-ticket-dept-filter">
			<option value=""><?php esc_html_e( 'All Departments', 'myhelpdesk-chat' ); ?></option>
			<?php foreach ( $departments_list as $dept ) : ?>
				<option value="<?php echo esc_attr( $dept->id ); ?>">
					<?php echo esc_html( $dept->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<!-- Search Box -->
		<input type="text"
			id="mhd-ticket-search"
			class="regular-text"
			placeholder="<?php esc_attr_e( 'Search tickets…', 'myhelpdesk-chat' ); ?>" />

		<button type="button" id="mhd-ticket-filter-btn" class="button button-primary">
			<?php esc_html_e( 'Filter', 'myhelpdesk-chat' ); ?>
		</button>
	</div>

	<!-- ============================================================
		 Tickets List Table
	============================================================= -->
	<table class="wp-list-table widefat fixed striped" id="mhd-tickets-table">
		<thead>
			<tr>
				<th style="width:60px;"><?php esc_html_e( 'ID', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'User', 'myhelpdesk-chat' ); ?></th>
				<th style="width:100px;"><?php esc_html_e( 'Status', 'myhelpdesk-chat' ); ?></th>
				<th style="width:90px;"><?php esc_html_e( 'Priority', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Agent', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Department', 'myhelpdesk-chat' ); ?></th>
				<th style="width:140px;"><?php esc_html_e( 'Date', 'myhelpdesk-chat' ); ?></th>
			</tr>
		</thead>
		<tbody id="mhd-tickets-tbody">
			<tr>
				<td colspan="8" style="text-align:center;padding:30px;">
					<?php esc_html_e( 'Loading tickets…', 'myhelpdesk-chat' ); ?>
				</td>
			</tr>
		</tbody>
	</table>

	<!-- ============================================================
		 Ticket Thread View (shown when a ticket row is clicked)
	============================================================= -->
	<div id="mhd-ticket-thread" style="display:none;margin-top:20px;background:#fff;border:1px solid #ccd0d4;border-radius:4px;">

		<!-- Thread Header -->
		<div id="mhd-ticket-thread-header" style="padding:15px 20px;border-bottom:1px solid #e2e4e7;">
			<h2 id="mhd-ticket-subject" style="margin:0;"></h2>
			<p id="mhd-ticket-meta" style="color:#666;margin:4px 0 0;"></p>
		</div>

		<!-- Thread Messages -->
		<div id="mhd-ticket-messages" style="padding:20px;max-height:400px;overflow-y:auto;">
			<p style="color:#999;"><?php esc_html_e( 'No messages yet.', 'myhelpdesk-chat' ); ?></p>
		</div>

		<!-- Reply Form -->
		<div class="mhd-ticket-reply-form" style="border-top:1px solid #e2e4e7;padding:15px 20px;">
			<textarea id="mhd-ticket-reply-message"
				rows="4"
				style="width:100%;margin-bottom:10px;"
				placeholder="<?php esc_attr_e( 'Write your reply…', 'myhelpdesk-chat' ); ?>"></textarea>
			<div style="display:flex;gap:8px;">
				<button type="button" id="mhd-ticket-send-reply" class="button button-primary">
					<?php esc_html_e( 'Send Reply', 'myhelpdesk-chat' ); ?>
				</button>
				<button type="button" id="mhd-ticket-add-note" class="button">
					<?php esc_html_e( 'Add Internal Note', 'myhelpdesk-chat' ); ?>
				</button>
				<button type="button" id="mhd-ticket-close-thread" class="button" style="margin-left:auto;">
					<?php esc_html_e( 'Close', 'myhelpdesk-chat' ); ?>
				</button>
			</div>
			<input type="hidden" id="mhd-current-ticket-id" value="" />
		</div>
	</div>

</div><!-- .wrap -->
