<?php

/**
 * Class Otis_Logger
 */
class Otis_Logger {
	/**
	 * @param string $message
	 * @param string $type Values: success, warning, error. Default: success.
	 *
	 * @return string The complete message that was logged.
	 */
	public function log( $message, $type = 'success' ) {
		$date         = date( 'c' );
		$full_message = "$date\t$type\t$message";

		// Grab upload dir for the log, since it'll be writable by the web user.
		$upload_dir = wp_upload_dir();
		error_log( $full_message . PHP_EOL, 3, trailingslashit( $upload_dir['basedir'] ) . 'otis.log' );

		return $full_message;
	}
}
