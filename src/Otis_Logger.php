<?php

/**
 * Class Otis_Logger
 */
class Otis_Logger {
	/**
	 * Otis_Logger Send Email.
	 *
	 * @param string $message The log entry message
	 *
	 * @return void
	 */
	public function send_email( $message ) {
		// Get the site admin email.
		$admin_email = get_option( 'admin_email' );
		// Set the email subject.
		$subject = 'WP OTIS Import Error';
		// Set the email message.
		$message = 'There was an error during the WP OTIS import: ' . $message;
		// Send the email.
		if ( ! is_email( $admin_email ) ) {
			return;
		}
		wp_mail( $admin_email, $subject, $message );
	}
	/**
	 * @param string $message The log entry message
	 * @param int $parent The post object ID that you want this log entry connected to, if any
	 * @param string $type The type classification to give this log entry.
	 * @param bool $log_errors_to_email Whether to send an email when an error is logged.
	 *
	 * @return string The complete message that was logged.
	 */
	public function log( $message, $parent = 0, $type = '', $log_errors_to_email = false ) {
		$date         = date( 'c' );
		$full_message = "$date\t$type\t$parent\t$message";

		// Grab upload dir for the log, since it'll be writable by the web user.
		$upload_dir = wp_upload_dir();
		$filename = 'wp-otis-' . date( 'mY' ) . '.log';

		error_log( $full_message . PHP_EOL, 3, trailingslashit( $upload_dir['basedir'] ) . $filename );

		do_action( 'wp_otis_log', $message, $parent, $type );

		// If this is an error type log, also send an email.
		if ( 'error' === $type  && $log_errors_to_email ) {
			$this->send_email( $full_message );
		}

		return $full_message;
	}
}
