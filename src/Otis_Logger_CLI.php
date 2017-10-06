<?php

require_once 'Otis_Logger.php';

/**
 * Class Otis_Logger_CLI
 */
class Otis_Logger_CLI extends Otis_Logger {
	/**
	 * @param string $message
	 * @param string $type Values: success, warning, error. Default: success.
	 *
	 * @return string The complete message that was logged.
	 */
	public function log( $message, $type = 'success' ) {
		$full_message = parent::log( $message, $type );

		if ( is_callable( 'WP_CLI::' . $type ) ) {
			WP_CLI::$type( $message );
		} else {
			WP_CLI::log( $message );
		}

		return $full_message;
	}
}
