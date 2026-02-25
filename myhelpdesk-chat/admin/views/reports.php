<?php
/**
 * Admin Reports view.
 *
 * Displays a date range filter, overview cards, four chart containers
 * (conversations per day, by source, tickets by status, agent activity),
 * an agent performance table, and a CSV export button.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$reports = new MHD_Reports();

// Default date range: last 30 days.
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$overview         = $reports->get_overview( $date_from, $date_to );
$agent_performance = $reports->get_agent_performance( $date_from, $date_to );
?>
<div class="wrap mhd-reports-page">

	<h1><?php esc_html_e( 'Reports', 'myhelpdesk-chat' ); ?></h1>

	<!-- ============================================================
		 Date Range Filter
	============================================================= -->
	<form method="get" action="" class="mhd-report-filter" style="display:flex;align-items:center;gap:10px;margin:15px 0;">
		<input type="hidden" name="page" value="myhelpdesk-reports" />
		<label for="mhd-date-from"><?php esc_html_e( 'From:', 'myhelpdesk-chat' ); ?></label>
		<input type="date" id="mhd-date-from" name="date_from"
			value="<?php echo esc_attr( $date_from ); ?>" />

		<label for="mhd-date-to"><?php esc_html_e( 'To:', 'myhelpdesk-chat' ); ?></label>
		<input type="date" id="mhd-date-to" name="date_to"
			value="<?php echo esc_attr( $date_to ); ?>" />

		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Apply', 'myhelpdesk-chat' ); ?>
		</button>
	</form>

	<!-- ============================================================
		 Overview Cards
	============================================================= -->
	<div class="mhd-report-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:15px;margin:20px 0;">
		<div class="mhd-card" style="background:#fff;padding:15px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3 style="margin:0;"><?php echo esc_html( number_format_i18n( $overview['total_conversations'] ) ); ?></h3>
			<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Total Conversations', 'myhelpdesk-chat' ); ?></p>
		</div>
		<div class="mhd-card" style="background:#fff;padding:15px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3 style="margin:0;"><?php echo esc_html( number_format_i18n( $overview['total_messages'] ) ); ?></h3>
			<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Total Messages', 'myhelpdesk-chat' ); ?></p>
		</div>
		<div class="mhd-card" style="background:#fff;padding:15px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3 style="margin:0;"><?php echo esc_html( number_format_i18n( $overview['open_tickets'] ) ); ?></h3>
			<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Open Tickets', 'myhelpdesk-chat' ); ?></p>
		</div>
		<div class="mhd-card" style="background:#fff;padding:15px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3 style="margin:0;"><?php echo esc_html( number_format_i18n( $overview['resolved_tickets'] ) ); ?></h3>
			<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Resolved Tickets', 'myhelpdesk-chat' ); ?></p>
		</div>
		<div class="mhd-card" style="background:#fff;padding:15px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3 style="margin:0;"><?php echo esc_html( number_format( $overview['avg_first_response'], 0 ) ); ?>s</h3>
			<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Avg First Response', 'myhelpdesk-chat' ); ?></p>
		</div>
		<div class="mhd-card" style="background:#fff;padding:15px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3 style="margin:0;"><?php echo esc_html( number_format( $overview['satisfaction_score'], 1 ) ); ?> / 5</h3>
			<p style="margin:4px 0 0;color:#666;"><?php esc_html_e( 'Satisfaction', 'myhelpdesk-chat' ); ?></p>
		</div>
	</div>

	<!-- ============================================================
		 Charts
	============================================================= -->
	<div class="mhd-report-charts" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:20px 0;">

		<!-- Conversations Per Day -->
		<div class="mhd-chart-container" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3><?php esc_html_e( 'Conversations Per Day', 'myhelpdesk-chat' ); ?></h3>
			<canvas id="mhd-chart-conversations-day" height="250"></canvas>
		</div>

		<!-- Conversations By Source -->
		<div class="mhd-chart-container" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3><?php esc_html_e( 'Conversations By Source', 'myhelpdesk-chat' ); ?></h3>
			<canvas id="mhd-chart-conversations-source" height="250"></canvas>
		</div>

		<!-- Tickets By Status -->
		<div class="mhd-chart-container" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3><?php esc_html_e( 'Tickets By Status', 'myhelpdesk-chat' ); ?></h3>
			<canvas id="mhd-chart-tickets-status" height="250"></canvas>
		</div>

		<!-- Agent Activity -->
		<div class="mhd-chart-container" style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<h3><?php esc_html_e( 'Agent Activity', 'myhelpdesk-chat' ); ?></h3>
			<canvas id="mhd-chart-agent-activity" height="250"></canvas>
		</div>

	</div><!-- .mhd-report-charts -->

	<!-- ============================================================
		 Agent Performance Table
	============================================================= -->
	<h2><?php esc_html_e( 'Agent Performance', 'myhelpdesk-chat' ); ?></h2>
	<table class="wp-list-table widefat fixed striped" id="mhd-agent-performance-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Agent', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Chats Handled', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Messages Sent', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Avg Response (s)', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Tickets Resolved', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Positive Ratings', 'myhelpdesk-chat' ); ?></th>
				<th><?php esc_html_e( 'Negative Ratings', 'myhelpdesk-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $agent_performance ) ) : ?>
				<tr>
					<td colspan="7" style="text-align:center;padding:20px;">
						<?php esc_html_e( 'No agent data available for the selected period.', 'myhelpdesk-chat' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $agent_performance as $perf ) : ?>
					<tr>
						<td><?php echo esc_html( $perf['display_name'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $perf['chats_handled'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $perf['messages_sent'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $perf['avg_response_time'], 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $perf['tickets_resolved'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $perf['positive_ratings'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $perf['negative_ratings'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- ============================================================
		 Export CSV
	============================================================= -->
	<div style="margin-top:20px;">
		<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=mhd_export_csv&date_from=' . rawurlencode( $date_from ) . '&date_to=' . rawurlencode( $date_to ) ), 'mhd_nonce', '_wpnonce' ) ); ?>"
			class="button button-secondary">
			<?php esc_html_e( 'Export CSV', 'myhelpdesk-chat' ); ?>
		</a>
	</div>

</div><!-- .wrap -->
