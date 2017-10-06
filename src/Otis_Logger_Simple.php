<?php

require_once 'Otis_Logger.php';

/**
 * Class Otis_Logger_Simple
 */
class Otis_Logger_Simple extends Otis_Logger {
	/**
	 * @var array
	 */
	private $logs = array();

	/**
	 * @param string $message
	 * @param string $type Values: success, warning, error. Default: success.
	 *
	 * @return string The complete message that was logged.
	 */
	public function log( $message, $type = 'success' ) {
		$full_message = parent::log( $message, $type );

		$this->logs[] = $full_message;

		return $full_message;
	}

	/**
	 * @return array
	 */
	public function get_logs() {
		return $this->logs;
	}
}
