<?php

require_once 'Otis.php';

/**
 * Settings class for the OTIS API.
 */
class Otis_Settings {

  /**
   * @var Otis
   */
  private $otis;

  /**
   * @var Otis_Logger
   */
  private $logger;

  /**
   * Otis_Settings constructor.
   *
   * @param Otis $otis
   * @param Otis_Logger $logger
   */
  public function __construct( $otis, $logger ) {
    $this->otis   = $otis;
    $this->logger = $logger;
  }

  /**
   * Otis_Settings sync from ACF data.
   */
  public function sync() {

  }
}
