<?php

/**
 * Class Otis_Logger
 */
class Otis_Logger {
	/**
	 * @param string $message The log entry message
	 * @param int $parent The post object ID that you want this log entry connected to, if any
	 * @param string $type The type classification to give this log entry.
	 *
	 * @return string The complete message that was logged.
	 */
	public function log( $message, $parent = 0, $type = '' ) {
		$date         = date( 'c' );
		$full_message = "$date\t$type\t$parent\t$message";

		// Grab upload dir for the log, since it'll be writable by the web user.
		$upload_dir = wp_upload_dir();
		$filename = 'wp-otis-' . date( 'mY' ) . '.log';

		error_log( $full_message . PHP_EOL, 3, trailingslashit( $upload_dir['basedir'] ) . $filename );

		do_action( 'wp_otis_log', $message, $parent, $type );

		return $full_message;
	}
}
