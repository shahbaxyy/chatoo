<?php
/**
 * Fired during plugin deactivation.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Deactivator
 *
 * @since 1.0.0
 */
class MHD_Deactivator {

	/**
	 * Plugin deactivation routine.
	 *
	 * Clears scheduled WP Cron events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'mhd_email_piping_cron' );
		wp_clear_scheduled_hook( 'mhd_agent_away_check' );
		flush_rewrite_rules();
	}
}
