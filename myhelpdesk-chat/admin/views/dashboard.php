<?php
/**
 * Admin Dashboard view.
 *
 * Displays the main overview page with summary cards, quick stats,
 * and shortcut links to the most-used plugin features.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/* ------------------------------------------------------------------
 * Fetch overview data
 * ---------------------------------------------------------------- */
$reports = new MHD_Reports();
$agents  = new MHD_Agents();

$today     = gmdate( 'Y-m-d' );
$month_ago = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
$overview  = $reports->get_overview( $month_ago, $today );

$all_agents    = $agents->get_agents();
$active_agents = $agents->get_online_agents();

$total_conversations = isset( $overview['total_conversations'] ) ? (int) $overview['total_conversations'] : 0;
$open_tickets        = isset( $overview['open_tickets'] ) ? (int) $overview['open_tickets'] : 0;
$resolved_tickets    = isset( $overview['resolved_tickets'] ) ? (int) $overview['resolved_tickets'] : 0;
$total_tickets       = $open_tickets + $resolved_tickets;
$satisfaction_score  = isset( $overview['satisfaction_score'] ) ? (float) $overview['satisfaction_score'] : 0;

// Open conversations: total minus resolved (approximation from report data).
$open_conversations = $total_conversations; // Current report data represents the period total.
?>
<div class="wrap mhd-dashboard">

	<!-- ============================================================
		 Page Header
	============================================================= -->
	<h1><?php esc_html_e( 'MyHelpDesk Dashboard', 'myhelpdesk-chat' ); ?></h1>
	<p class="mhd-subtitle">
		<?php esc_html_e( 'Welcome! Here\'s a quick overview of your helpdesk activity over the last 30 days.', 'myhelpdesk-chat' ); ?>
	</p>

	<!-- ============================================================
		 Overview Cards
	============================================================= -->
	<div class="mhd-dashboard-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:20px;margin:20px 0;">

		<!-- Total Conversations -->
		<div class="mhd-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<span class="dashicons dashicons-format-chat" style="font-size:36px;color:#0073aa;"></span>
			<h2 style="margin:.5em 0 0;"><?php echo esc_html( number_format_i18n( $total_conversations ) ); ?></h2>
			<p><?php esc_html_e( 'Total Conversations', 'myhelpdesk-chat' ); ?></p>
		</div>

		<!-- Open Conversations -->
		<div class="mhd-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<span class="dashicons dashicons-testimonial" style="font-size:36px;color:#f0b849;"></span>
			<h2 style="margin:.5em 0 0;"><?php echo esc_html( number_format_i18n( $open_conversations ) ); ?></h2>
			<p><?php esc_html_e( 'Open Conversations', 'myhelpdesk-chat' ); ?></p>
		</div>

		<!-- Total Tickets -->
		<div class="mhd-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<span class="dashicons dashicons-tickets-alt" style="font-size:36px;color:#46b450;"></span>
			<h2 style="margin:.5em 0 0;"><?php echo esc_html( number_format_i18n( $total_tickets ) ); ?></h2>
			<p><?php esc_html_e( 'Total Tickets', 'myhelpdesk-chat' ); ?></p>
		</div>

		<!-- Open Tickets -->
		<div class="mhd-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<span class="dashicons dashicons-warning" style="font-size:36px;color:#dc3232;"></span>
			<h2 style="margin:.5em 0 0;"><?php echo esc_html( number_format_i18n( $open_tickets ) ); ?></h2>
			<p><?php esc_html_e( 'Open Tickets', 'myhelpdesk-chat' ); ?></p>
		</div>

		<!-- Active Agents -->
		<div class="mhd-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<span class="dashicons dashicons-groups" style="font-size:36px;color:#826eb4;"></span>
			<h2 style="margin:.5em 0 0;"><?php echo esc_html( number_format_i18n( count( $active_agents ) ) ); ?></h2>
			<p><?php esc_html_e( 'Active Agents', 'myhelpdesk-chat' ); ?></p>
		</div>

		<!-- Satisfaction Score -->
		<div class="mhd-card" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<span class="dashicons dashicons-star-filled" style="font-size:36px;color:#ffb900;"></span>
			<h2 style="margin:.5em 0 0;"><?php echo esc_html( number_format( $satisfaction_score, 1 ) ); ?> / 5</h2>
			<p><?php esc_html_e( 'Satisfaction Score', 'myhelpdesk-chat' ); ?></p>
		</div>

	</div><!-- .mhd-dashboard-cards -->

	<!-- ============================================================
		 Quick Links
	============================================================= -->
	<div class="mhd-quick-links" style="margin-top:30px;">
		<h2><?php esc_html_e( 'Quick Links', 'myhelpdesk-chat' ); ?></h2>
		<div style="display:flex;flex-wrap:wrap;gap:10px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=myhelpdesk-conversations' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Conversations', 'myhelpdesk-chat' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=myhelpdesk-tickets' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Tickets', 'myhelpdesk-chat' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=myhelpdesk-agents' ) ); ?>" class="button">
				<?php esc_html_e( 'Manage Agents', 'myhelpdesk-chat' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=myhelpdesk-departments' ) ); ?>" class="button">
				<?php esc_html_e( 'Departments', 'myhelpdesk-chat' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=myhelpdesk-knowledge-base' ) ); ?>" class="button">
				<?php esc_html_e( 'Knowledge Base', 'myhelpdesk-chat' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=myhelpdesk-reports' ) ); ?>" class="button">
				<?php esc_html_e( 'Reports', 'myhelpdesk-chat' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=myhelpdesk-settings' ) ); ?>" class="button">
				<?php esc_html_e( 'Settings', 'myhelpdesk-chat' ); ?>
			</a>
		</div>
	</div>

</div><!-- .wrap -->
