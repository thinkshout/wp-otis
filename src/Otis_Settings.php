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

  private $filteredTypes;
  private $filteredCities;
  private $filteredRegions;

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
   * Otis_Settings apply settings from ACF data to filters.
   */
  public function applyFilterValues() {

    $bytype = get_field('filter_listings_by_type', 'option');
    $byregion = get_field('filter_listings_by_region', 'option');
    $bycity = get_field('filter_listings_by_city', 'option');

    if ( !empty( $bytype ) ) {
      $filterTypesArray = [];
      foreach ( $bytype as $filtertype ) {

        $filterTypesArray []= $filtertype->name;
      }
      $this->filteredTypes = implode( "|", $filterTypesArray );

      // undefined function add_filter?
      add_filter( 'wp_otis_listings', $this->_typeFilterValues);
    }
  }

  private function _typeFilterValues( $params ) {

    $params['types'] .= $this->filteredTypes;
    return $params;
  }
}
