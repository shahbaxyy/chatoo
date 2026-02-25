<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If uninstall not called from WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Check if user opted to delete all data.
if ( '1' !== get_option( 'mhd_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

$prefix = $wpdb->prefix . 'mhd_';

// Drop all plugin tables.
$tables = array(
	'conversations',
	'messages',
	'agents',
	'departments',
	'tickets',
	'ticket_replies',
	'saved_replies',
	'notifications',
	'kb_categories',
	'kb_articles',
	'automations',
	'ratings',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhd_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Remove custom capability.
$admin_role = get_role( 'administrator' );
if ( $admin_role ) {
	$admin_role->remove_cap( 'mhd_agent' );
}

// Clean up transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mhd_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mhd_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
