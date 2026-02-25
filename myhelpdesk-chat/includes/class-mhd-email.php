<?php
/**
 * Email class.
 *
 * Handles email sending via wp_mail with optional custom SMTP configuration.
 *
 * @package MyHelpDesk_Chat
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class MHD_Email
 *
 * Sends email notifications to agents and users using wp_mail.
 * Supports custom SMTP configuration when enabled in plugin settings.
 *
 * @since 1.0.0
 */
class MHD_Email {

	/**
	 * Agents table name including WP prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $agents_table;

	/**
	 * Constructor.
	 *
	 * Sets up the table names and hooks the SMTP configuration filter
	 * when custom SMTP is enabled in plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->agents_table = $wpdb->prefix . MHD_TABLE_PREFIX . 'agents';

		if ( '1' === get_option( 'mhd_custom_smtp_enabled', '0' ) ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}
	}

	/**
	 * Send an email notification to an agent.
	 *
	 * Retrieves the agent's WordPress user email and sends the message
	 * using the plugin's configured from name and from email address.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $agent_id The agent table ID.
	 * @param string $subject  Email subject.
	 * @param string $message  Email body (HTML supported).
	 * @return bool True if the email was accepted for delivery, false otherwise.
	 */
	public function send_agent_notification( $agent_id, $subject, $message ) {
		global $wpdb;

		$agent = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, u.user_email, u.display_name
				FROM {$this->agents_table} AS a
				INNER JOIN {$wpdb->users} AS u ON a.user_id = u.ID
				WHERE a.id = %d",
				(int) $agent_id
			)
		);

		if ( ! $agent || empty( $agent->user_email ) ) {
			return false;
		}

		$from_name  = get_option( 'mhd_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'mhd_email_from_address', get_bloginfo( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', sanitize_text_field( $from_name ), sanitize_email( $from_email ) ),
		);

		return wp_mail( $agent->user_email, $subject, $message, $headers );
	}

	/**
	 * Send an email notification to a user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email   Recipient email address.
	 * @param string $subject Email subject.
	 * @param string $message Email body (HTML supported).
	 * @return bool True if the email was accepted for delivery, false otherwise.
	 */
	public function send_user_notification( $email, $subject, $message ) {
		$from_name  = get_option( 'mhd_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'mhd_email_from_address', get_bloginfo( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', sanitize_text_field( $from_name ), sanitize_email( $from_email ) ),
		);

		return wp_mail( sanitize_email( $email ), $subject, $message, $headers );
	}

	/**
	 * Get an HTML email template with variable replacement.
	 *
	 * Loads a template by name and replaces placeholders with the provided
	 * data values. Placeholders use the format {{key}}.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_name Template name (without extension), e.g. 'new-ticket'.
	 * @param array  $data          Associative array of placeholder => replacement value pairs.
	 * @return string Rendered HTML email content, or empty string if template not found.
	 */
	public function get_email_template( $template_name, $data = array() ) {
		$template_file = MHD_PLUGIN_DIR . 'templates/emails/' . sanitize_file_name( $template_name ) . '.html';

		if ( ! file_exists( $template_file ) ) {
			// Fallback: build a simple default template.
			$site_name = get_bloginfo( 'name' );
			$html      = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
			$html     .= '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">';
			$html     .= '<h2 style="color:#0084ff;">' . esc_html( $site_name ) . '</h2>';
			$html     .= '<div style="padding:20px 0;">{{content}}</div>';
			$html     .= '<p style="color:#999;font-size:12px;">' . esc_html( $site_name ) . '</p>';
			$html     .= '</div></body></html>';
		} else {
			$html = file_get_contents( $template_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		// Replace all {{key}} placeholders with corresponding data values.
		foreach ( $data as $key => $value ) {
			$html = str_replace( '{{' . $key . '}}', $value, $html );
		}

		return $html;
	}

	/**
	 * Configure custom SMTP settings on the PHPMailer instance.
	 *
	 * Hooked into the `phpmailer_init` action when custom SMTP is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer The PHPMailer instance.
	 * @return void
	 */
	public function configure_smtp( $phpmailer ) {
		$host       = get_option( 'mhd_smtp_host', '' );
		$port       = get_option( 'mhd_smtp_port', '587' );
		$username   = get_option( 'mhd_smtp_username', '' );
		$password   = get_option( 'mhd_smtp_password', '' );
		$encryption = get_option( 'mhd_smtp_encryption', 'tls' );

		if ( empty( $host ) ) {
			return;
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		$phpmailer->Host       = sanitize_text_field( $host );
		$phpmailer->Port       = (int) $port;
		$phpmailer->SMTPAuth   = ! empty( $username );
		$phpmailer->Username   = sanitize_text_field( $username );
		$phpmailer->Password   = $password;
		$phpmailer->SMTPSecure = in_array( $encryption, array( 'tls', 'ssl' ), true ) ? $encryption : 'tls';
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
