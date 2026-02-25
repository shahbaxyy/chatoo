<?php
/**
 * Admin Conversations view.
 *
 * Split-view layout with a filterable conversation list on the left,
 * a message thread with reply composer in the centre, and a context
 * sidebar on the right. All interactions are AJAX-driven.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$agents_obj  = new MHD_Agents();
$agents_list = $agents_obj->get_agents();

$departments_obj  = new MHD_Departments();
$departments_list = $departments_obj->get_departments();
?>
<div class="wrap mhd-conversations-page">

	<h1><?php esc_html_e( 'Conversations', 'myhelpdesk-chat' ); ?></h1>

	<div class="mhd-conversations-layout" style="display:flex;gap:0;border:1px solid #ccd0d4;border-radius:4px;background:#fff;min-height:600px;margin-top:15px;">

		<!-- ==========================================================
			 Left Panel – Conversation List
		========================================================== -->
		<div class="mhd-conv-list-panel" style="width:320px;border-right:1px solid #ccd0d4;overflow-y:auto;">

			<!-- Status Tabs -->
			<div class="mhd-conv-tabs" style="display:flex;border-bottom:1px solid #e2e4e7;padding:0;">
				<a href="#" class="mhd-conv-tab active" data-status="open" style="flex:1;text-align:center;padding:10px;text-decoration:none;font-weight:600;">
					<?php esc_html_e( 'Open', 'myhelpdesk-chat' ); ?>
				</a>
				<a href="#" class="mhd-conv-tab" data-status="pending" style="flex:1;text-align:center;padding:10px;text-decoration:none;">
					<?php esc_html_e( 'Pending', 'myhelpdesk-chat' ); ?>
				</a>
				<a href="#" class="mhd-conv-tab" data-status="resolved" style="flex:1;text-align:center;padding:10px;text-decoration:none;">
					<?php esc_html_e( 'Resolved', 'myhelpdesk-chat' ); ?>
				</a>
			</div>

			<!-- Search & Department Filter -->
			<div class="mhd-conv-filters" style="padding:10px;">
				<input type="text"
					id="mhd-conv-search"
					class="regular-text"
					placeholder="<?php esc_attr_e( 'Search conversations…', 'myhelpdesk-chat' ); ?>"
					style="width:100%;margin-bottom:8px;" />
				<select id="mhd-conv-dept-filter" style="width:100%;">
					<option value=""><?php esc_html_e( 'All Departments', 'myhelpdesk-chat' ); ?></option>
					<?php foreach ( $departments_list as $dept ) : ?>
						<option value="<?php echo esc_attr( $dept->id ); ?>">
							<?php echo esc_html( $dept->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Conversation Items (populated via AJAX) -->
			<div id="mhd-conv-items" class="mhd-conv-items">
				<p style="padding:20px;color:#999;text-align:center;">
					<?php esc_html_e( 'Loading conversations…', 'myhelpdesk-chat' ); ?>
				</p>
			</div>
		</div><!-- .mhd-conv-list-panel -->

		<!-- ==========================================================
			 Centre Panel – Thread & Reply Composer
		========================================================== -->
		<div class="mhd-conv-thread-panel" style="flex:1;display:flex;flex-direction:column;">

			<!-- Thread Header -->
			<div id="mhd-thread-header" class="mhd-thread-header" style="padding:12px 16px;border-bottom:1px solid #e2e4e7;font-weight:600;">
				<?php esc_html_e( 'Select a conversation', 'myhelpdesk-chat' ); ?>
			</div>

			<!-- Messages Area -->
			<div id="mhd-thread-messages" class="mhd-thread-messages" style="flex:1;overflow-y:auto;padding:16px;">
				<p style="color:#999;"><?php esc_html_e( 'No conversation selected.', 'myhelpdesk-chat' ); ?></p>
			</div>

			<!-- Reply Composer -->
			<div id="mhd-reply-composer" class="mhd-reply-composer" style="border-top:1px solid #e2e4e7;padding:12px 16px;display:none;">
				<textarea id="mhd-reply-message"
					rows="3"
					style="width:100%;margin-bottom:8px;"
					placeholder="<?php esc_attr_e( 'Type your reply…', 'myhelpdesk-chat' ); ?>"></textarea>
				<div style="display:flex;gap:8px;align-items:center;">
					<button type="button" id="mhd-send-reply" class="button button-primary">
						<?php esc_html_e( 'Send Reply', 'myhelpdesk-chat' ); ?>
					</button>
					<button type="button" id="mhd-add-note" class="button">
						<?php esc_html_e( 'Add Note', 'myhelpdesk-chat' ); ?>
					</button>
					<input type="hidden" id="mhd-current-conv-id" value="" />
				</div>
			</div>
		</div><!-- .mhd-conv-thread-panel -->

		<!-- ==========================================================
			 Right Sidebar – Context & Actions
		========================================================== -->
		<div class="mhd-conv-sidebar" style="width:280px;border-left:1px solid #ccd0d4;overflow-y:auto;padding:16px;display:none;" id="mhd-conv-sidebar">

			<!-- User Info -->
			<div class="mhd-sidebar-section" style="margin-bottom:20px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'User Info', 'myhelpdesk-chat' ); ?></h3>
				<div id="mhd-sidebar-user-info">
					<p style="color:#999;"><?php esc_html_e( '—', 'myhelpdesk-chat' ); ?></p>
				</div>
			</div>

			<!-- Assign Agent -->
			<div class="mhd-sidebar-section" style="margin-bottom:20px;">
				<h3><?php esc_html_e( 'Assign Agent', 'myhelpdesk-chat' ); ?></h3>
				<select id="mhd-assign-agent" style="width:100%;">
					<option value=""><?php esc_html_e( 'Unassigned', 'myhelpdesk-chat' ); ?></option>
					<?php foreach ( $agents_list as $agent ) : ?>
						<option value="<?php echo esc_attr( $agent->id ); ?>">
							<?php echo esc_html( $agent->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Assign Department -->
			<div class="mhd-sidebar-section" style="margin-bottom:20px;">
				<h3><?php esc_html_e( 'Department', 'myhelpdesk-chat' ); ?></h3>
				<select id="mhd-assign-dept" style="width:100%;">
					<option value=""><?php esc_html_e( 'No Department', 'myhelpdesk-chat' ); ?></option>
					<?php foreach ( $departments_list as $dept ) : ?>
						<option value="<?php echo esc_attr( $dept->id ); ?>">
							<?php echo esc_html( $dept->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Change Status -->
			<div class="mhd-sidebar-section" style="margin-bottom:20px;">
				<h3><?php esc_html_e( 'Status', 'myhelpdesk-chat' ); ?></h3>
				<select id="mhd-change-status" style="width:100%;">
					<option value="open"><?php esc_html_e( 'Open', 'myhelpdesk-chat' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'myhelpdesk-chat' ); ?></option>
					<option value="resolved"><?php esc_html_e( 'Resolved', 'myhelpdesk-chat' ); ?></option>
					<option value="archived"><?php esc_html_e( 'Archived', 'myhelpdesk-chat' ); ?></option>
				</select>
			</div>

			<!-- Tags -->
			<div class="mhd-sidebar-section">
				<h3><?php esc_html_e( 'Tags', 'myhelpdesk-chat' ); ?></h3>
				<input type="text"
					id="mhd-conv-tags"
					class="regular-text"
					placeholder="<?php esc_attr_e( 'Add tags (comma separated)', 'myhelpdesk-chat' ); ?>"
					style="width:100%;" />
				<button type="button" id="mhd-save-tags" class="button" style="margin-top:6px;">
					<?php esc_html_e( 'Save Tags', 'myhelpdesk-chat' ); ?>
				</button>
			</div>

		</div><!-- .mhd-conv-sidebar -->

	</div><!-- .mhd-conversations-layout -->

</div><!-- .wrap -->
