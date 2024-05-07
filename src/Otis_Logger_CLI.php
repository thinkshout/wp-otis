<?php

require_once 'Otis_Logger.php';

/**
 * Class Otis_Logger_CLI
 */
class Otis_Logger_CLI extends Otis_Logger {
	/**
	 * @inheritdoc
	 */
	public function log( $message, $parent = 0, $type = '', $log_errors_to_email = false ) {
		$full_message = parent::log( $message, $parent, $type, $log_errors_to_email );

		if ( is_callable( 'WP_CLI::' . $type ) ) {
			WP_CLI::$type( $parent . "\t" . $message );
		} else {
			WP_CLI::log( $parent . "\t" . $message );
		}

		return $full_message;
	}
}
