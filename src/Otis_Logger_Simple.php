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
	 * @inheritdoc
	 */
	public function log( $message, $parent = 0, $type = '', $log_errors_to_email = true ) {
		$full_message = parent::log( $message, $parent, $type, $log_errors_to_email );

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
