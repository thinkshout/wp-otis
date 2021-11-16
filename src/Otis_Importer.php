<?php

require_once 'Otis.php';

/**
 * Importer class for the OTIS API.
 */
class Otis_Importer {

	/**
	 * @var Otis
	 */
	private $otis;

	/**
	 * @var Otis_Logger
	 */
	private $logger;

	/**
	 * Duplicate OTIS attributes: keys = dupe fields, values = fields they map to.
	 *
	 * @var array
	 */
	private $attribute_map = [
		'last_updated'                          => null,
		'website'                               => null,
		'bed_and_breakfast_amenities'           => 'amenities',
		'boutique_hotels_amenities'             => 'amenities',
		'campgrounds_amenities'                 => 'amenities',
		'farm_and_ranch_stays_amenities'        => 'amenities',
		'glamping_and_tree_houses_amenities'    => 'amenities',
		'hostels_amenities'                     => 'amenities',
		'hotels_and_motels_amenities'           => 'amenities',
		'resorts_amenities'                     => 'amenities',
		'rv_parks_amenities'                    => 'amenities',
		'vacation_rentals_amenities'            => 'amenities',
		'venue_amenities'                       => 'amenities',
		'wine_amenities'                        => 'amenities',
		'lakes_and_reservoirs_category'         => 'otis_category',
		'other_outdoor_category'                => 'otis_category',
		'parks_and_recreational_areas_category' => 'otis_category',
		'restaurants_category'                  => 'otis_category',
		'rivers_and_streams_category'           => 'otis_category',
		'category_cannabis'                     => 'otis_category',
		'category_event_services'               => 'otis_category',
		'category_farms_ranches_u_pick'         => 'otis_category',
		'category_health_wellness'              => 'otis_category',
		'category_non_tourism_businesses'       => 'otis_category',
		'category_shopping'                     => 'otis_category',
		'venue_categories'                      => 'otis_category',
		'tag_list'                              => 'otis_tag',
		'activities'                            => 'type',
		'cycling_ride_type'                     => 'type',
		'event_type'                            => 'type',
		'additional_lodging_types'              => 'type',
		'primary_city'                          => 'city',
		'primary_region'                        => 'region',
	];

	/**
	 * Otis_Importer constructor.
	 *
	 * @param Otis $otis
	 * @param Otis_Logger $logger
	 */
	public function __construct( $otis, $logger ) {
		$this->otis   = $otis;
		$this->logger = $logger;
	}

	/**
	 * Import from OTIS.
	 *
	 * @param array|string $args
	 * @param array $assoc_args
	 *
	 * @throws \Otis_Exception
	 */
	function import( $args, $assoc_args ) {
		if ( ! $args ) {
			$args = [ 'pois' ];
		} elseif ( ! is_array( $args ) ) {
			$args = [ $args ];
		}

    $bulk = isset( $assoc_args['bulk'] ) ? $assoc_args['bulk'] : get_option( WP_OTIS_BULK_IMPORT_ACTIVE, false );

		switch ( $args[0] ) {
			case 'terms':
				$this->_import_terms( $assoc_args );

				$log[] = 'Terms import complete.';

				return $log;

			case 'regions':
				$assoc_args['type'] = 'Regions';
				$assoc_args['all']  = true;
				$assoc_args['page'] = 1;

				$this->_import_pois( $assoc_args );

				$log[] = 'Regions import complete.';

				return $log;

			case 'cities':
				$assoc_args['type'] = 'Cities';
				$assoc_args['all']  = true;
				$assoc_args['page'] = 1;

				$this->_import_pois( $assoc_args );

				$log[] = 'Cities import complete.';

				return $log;

			case 'pois':
					$this->import( 'terms', $assoc_args );
					$this->import( 'regions', $assoc_args );
					$this->import( 'cities', $assoc_args );

					$this->_import_pois( $assoc_args );
					$this->_import_history( $assoc_args );

					if (!$bulk) {
						$log[] = 'POI import complete.';
					} else {
						$log[] = 'Adding bulk import CRON.';
					}

					return $log;

			case 'pois-only':
					$this->_import_pois( $assoc_args );
					$assoc_args['history-page'] = 1;
					$this->_import_history( $assoc_args );

					if (!$bulk) {
							$log[] = 'POI import complete.';
					}

					return $log;

			case 'related-pois-only':
					$assoc_args['related_only'] = true;
					$this->_import_pois( $assoc_args );

					if (!$bulk) {
							$log[] = 'POI import complete.';
					}

					return $log;

			case 'poi':

				if ( empty( $assoc_args['uuid'] ) ) {
					if ( empty( $args[1] ) ) {
						throw new Otis_Exception( 'Missing argument: uuid' );
					}
					$assoc_args['uuid'] = $args[1];
				}

				$this->_import_poi( $assoc_args );

				$log[] = 'POI import complete.';

				return $log;

			case 'history-only':
				$this->_import_history( $assoc_args );

				$log[] = 'History import complete.';
				return $log;

		} // End switch().

		throw new Otis_Exception( 'Unknown command: ' . $args[0] );
	}

    /**
     * Sets bulk importer flag to false
     *
     * @return array
     */
    function nobulk() {
        update_option( WP_OTIS_BULK_IMPORT_ACTIVE, false );
        $log[] = 'OTIS bulk import flag set to false';
        return $log;
    }

		/**
		 * Set bulk history flag to false
		 *
		 * @return array
		 */
		function nohistory() {
				update_option( WP_OTIS_BULK_HISTORY_ACTIVE, false );
				delete_transient( WP_OTIS_BULK_IMPORT_TRANSIENT );
				$log[] = 'OTIS bulk history flag set to false';
				return $log;
		}

		/**
		 * Sets bulk importer flag to true
		 *
		 * @return array
		 */
		function start_bulk() {
			update_option( WP_OTIS_BULK_IMPORT_ACTIVE, true );
			$log[] = 'OTIS bulk import flag set to true';
			return $log;
		}

	/**
	 * Fetches the full attribute schema for an OTIS type.
	 *
	 * @param array|int $type
	 *
	 * @return array
	 */
	private function _get_type_schema( $type ) {
		static $type_schemas = [];

		$type_id = $type['id'] ?? $type;

		if ( ! isset( $type_schemas[ $type_id ] ) ) {
			$results = $this->otis->call( 'listings-types/' . $type_id );

			if ( isset( $results['schema'] ) && is_array( $results['schema'] ) ) {
				$type_schemas[ $type_id ] = $results['schema'];
			} else {
				$type_schemas[ $type_id ] = [];
			}
		}

		return $type_schemas[ $type_id ];
	}

	/**
	 * Import OTIS values into WordPress taxonomy terms.
	 *
	 * @param array $assoc_args
	 */
	private function _import_terms( $assoc_args = [] ) {
		// Import collections and types.
		$collections = $this->otis->call( 'listings-collections' );

		foreach ( $collections['results'] as $collection ) {
			$collection_id = $this->_identify_term( $collection, 'type' );

			foreach ( $collection['types'] as $type ) {
				$this->_identify_term( $type, 'type', [
					'parent' => $collection_id,
				] );
			}
		}

		// Import activities.
		$activities = $this->otis->call( 'listings-activities' );

		// Fake an activity parent term link so that this term can be renamed in the UI.
		$activity_value = [
			'name' => 'Activities',
			'uri'  => '/listings-activities/parent/',
		];

		$activity_id = $this->_identify_term( $activity_value, 'type' );

		foreach ( $activities['results'] as $activity ) {
			$this->_identify_term( $activity, 'type', [
				'parent' => $activity_id,
			] );
		}

		// Import cycling_ride_type.
		$cycling_ride_type = $this->otis->call( 'listings-attributes/30' );

		$cycling_ride_type_id = $this->_identify_term( $cycling_ride_type, 'type' );

		foreach ( $cycling_ride_type['choices'] as $cycling_ride_choice ) {
			$this->_identify_term( $cycling_ride_choice, 'type', [
				'parent' => $cycling_ride_type_id,
			] );
		}

		// Import event_type.
		$event_type = $this->otis->call( 'listings-attributes/77' );

		$event_type_id = $this->_identify_term( $event_type, 'type' );

		foreach ( $event_type['choices'] as $event_choice ) {
			$this->_identify_term( $event_choice, 'type', [
				'parent' => $event_type_id,
			] );
		}

		// Import global categories.
		$glocats = $this->otis->call( 'global-categories' );

		foreach ( $glocats['results'] as $glocat ) {
			$this->_identify_term( $glocat, 'glocats' );
		}
	}

	/**
	 * Filter Raw OTIS Results by Modified Date against given date range.
	 * 
	 * @param array $results
	 * @param array $args
	 * 
	 * @return array
	 */
		private function _filter_results_by_date( $results, $args ) {
			if ( ! isset( $args['start_date'] ) && ! isset( $args['end_date'] ) ) {
				return $results;
			}
			$filtered_results = [];
			$start_date = $args['start_date'];
			$end_date = $args['end_date'];

			foreach ( $results as $result ) {
				$modified_datetime = $result['modified'];
				$modified_datestamp = strtotime( $modified_datetime );
				$start_datestamp = strtotime( $start_date );
				$end_datestamp = strtotime( $end_date );

				if ( $modified_datestamp >= $start_datestamp && $modified_datestamp <= $end_datestamp ) {
					$filtered_results[] = $result;
				}
			}

			return $filtered_results;
		}

    /**
     * Import OTIS POIs.
     *
     * @param array $assoc_args
     */
    private function _import_pois( $assoc_args = [] ) {

		$params = [
            'geo_data' => 'true',
			'reverse_relations' => 'true'
        ];

        if ( isset( $assoc_args['modified_start'] ) || isset( $assoc_args['modified'] ) ) {
            // Only pull expired listings relative to a recent import.
            $params['showexpired'] = 'true';
        }

        $params = apply_filters( 'wp_otis_listings', $params );

        $params['page_size'] = $assoc_args['page_size'] ?? 50;  // POIs per page
        $params['page']      = $assoc_args['page'] ?? 1;
        $chapter_size        = 5; // pages per chapter
        $next_chapter        = $params['page'] % $chapter_size == 0 ? true : false;

        if ( isset( $assoc_args['type'] ) ) {
            $params['type'] = $assoc_args['type'];
        }

        if ( isset( $assoc_args['modified'] ) ) {
            $assoc_args['all']  = true;
            $params['modified'] = date( 'm/d/Y', strtotime( $assoc_args['modified'] ) );
				// Check if we're doing a modified_start date without an end date. If so fallback to basic modified date param.
        } else if ( isset( $assoc_args['modified_start'] ) && ! isset( $assoc_args['modified_end'] ) ) {
						$assoc_args['all']  = true;
						$params['modified'] = date( 'm/d/Y', strtotime( $assoc_args['modified_start'] ) );
				// Check if we're both modified start and end dates are present.
				} else if ( isset( $assoc_args['modified_start'] ) && isset( $assoc_args['modified_end'] ) ) {
						// If both modified dates are present check if they're the equal. If so fallback to basic modified date param.
						if ( $assoc_args['modified_start'] === $assoc_args['modified_end'] ) {
								$assoc_args['all']   = true;
								$params['modified'] = date( 'm/d/Y', strtotime( $assoc_args['modified_start'] ) );
						// If they're different use the after & before parameters
						} else {
								$assoc_args['all']   = true;
								$params['modified']  = date( 'm/d/Y', strtotime( $assoc_args['modified_start'] ) );
								$params['start_date'] = $assoc_args['modified_start'];
								$params['end_date']   = $assoc_args['modified_end'];
						}
				}

        $this->logger->log('Calling to OTIS w/ params: ' . print_r( $params, true ) );
				$listings = $this->otis->call( 'listings', $params, $this->logger );

				if ( isset( $params['start_date']) && isset( $params['end_date'] ) ) {
					// filter listings by modified date
					$this->logger->log('Filtering this page of OTIS results by modified param using dates: ' . $params['start_date'] . ' - ' . $params['end_date'] );
					$filtered_results = $this->_filter_results_by_date( $listings['results'], $params );
					$listings['results'] = $filtered_results;
					$this->logger->log(count($listings['results']) . ' relevant results');
				}

        if ( empty( $listings['results'] ) && $listings['count'] > 0 ) {
						// The filter didn't return any results but there were results, so we need to go to the next page.
						$this->logger->log('No relevant results for this page continuing...');
            $total = ceil( $listings['count'] / $params['page_size'] );

						if ( isset( $assoc_args['all'] ) && $params['page'] < $total ) {
								$this->logger->log($params['page'] * $params['page_size'] ." out of ".$listings['count']. " records evaluated.");

								$assoc_args['page'] = $params['page'] + 1;
								$assoc_args['page_size'] = $params['page_size'];

								if ($next_chapter) {
										as_enqueue_async_action( 'wp_otis_async_bulk_import', ['params' => $assoc_args] );
								} else {
										$this->_import_pois( $assoc_args );
								}
						}

						if (isset( $assoc_args['all'] ) && ($params['page'] == $total) && $total > $chapter_size ) {
								$this->logger->log($listings['count'] ." out of ".$listings['count']. " records evaluated.");

								update_option( WP_OTIS_BULK_IMPORT_ACTIVE, false );

								as_unschedule_all_actions( 'wp_otis_async_bulk_import' );

								$this->logger->log("OTIS bulk import complete.");
						}
        } else if ( ! empty( $listings['results'] ) ) {
					// The filter returned results, so we can import them.
					$this->logger->log("Processing " . count($listings['results']) . " relevant listings...");
					$import_related_only = false;
					if ( isset($assoc_args['related_only']) ) {
						$import_related_only = $assoc_args['related_only'];
					}

					if ( $import_related_only && $params['page'] == 1) {
						$params[] = ['reverse_relations' => 'true'];
						$this->logger->log("Importing POIs with relationships only");
					}

					// $uuids = array_pluck( $listings['results'], 'uuid' );

					/* First page of an import that will require multiple chapters */
					if ((($listings['count'] / $params['page_size']) > $chapter_size) && ($params['page'] == 1)) {
							update_option( WP_OTIS_BULK_IMPORT_ACTIVE, true );
							$logger_date = wp_otis_get_logger_modified_date_string($assoc_args);
							$this->logger->log("OTIS bulk import detected: ".$listings['count']." updates. ".$logger_date);
					}

					foreach ( $listings['results'] as $result ) {
						$uuid = $result['uuid'];
						$poi_post = get_posts([
							'post_type' => 'poi',
							'post_status' => 'any',
							'meta_key' => 'uuid',
							'meta_value' => $uuid,
							'posts_per_page' => 1,
						]);
						$post_id = 0;
						if (count($poi_post) > 0) {
							$post_id = $poi_post[0]->ID;
						}
						$type = strtolower( $result['type']['name'] );
						if ( 'regions' === $type || 'cities' === $type ) {
								if ( ! isset( $assoc_args['type'] ) ) {
										// Regions and cities have already been populated...
										// Skip those when populating the rest of the listing results.
										continue;
								}
						}

						try {
							$post_id = $this->_upsert_poi( $post_id, $result, $import_related_only );
						} catch ( Exception $exception ) {
							$this->logger->log( $exception->getMessage(), $post_id, 'error' );
						}
					}

					// $the_query = new WP_Query( [
					// 		'no_found_rows'          => true,
					// 		'update_post_meta_cache' => false,
					// 		'update_post_term_cache' => false,
					// 		'posts_per_page'         => - 1,
					// 		'post_status'            => 'any',
					// 		'post_type'              => 'poi',
					// 		'meta_key'               => 'uuid',
					// 		'meta_value'             => $uuids,
					// ] );

					// $uuid_map = [];

					// while ( $the_query->have_posts() ) {
					// 		$the_query->the_post();

					// 		$uuid_map[ get_field( 'uuid' ) ] = get_the_ID();
					// }

					// wp_reset_postdata();

					// foreach ( $listings['results'] as $result ) {
					// 		$uuid    = $result['uuid'];
					// 		$post_id = $uuid_map[ $uuid ] ?? 0;

					// 		$type = strtolower( $result['type']['name'] );
					// 		if ( 'regions' === $type || 'cities' === $type ) {
					// 				if ( ! isset( $assoc_args['type'] ) ) {
					// 						// Regions and cities have already been populated...
					// 						// Skip those when populating the rest of the listing results.
					// 						continue;
					// 				}
					// 		}

					// 		try {
					// 			$post_id = $this->_upsert_poi( $post_id, $result, $import_related_only );
					// 		} catch ( Exception $exception ) {
					// 			$this->logger->log( $exception->getMessage(), $post_id, 'error' );
					// 		}

					// }

					$total = ceil( $listings['count'] / $params['page_size'] );

					if ( isset( $assoc_args['all'] ) && $params['page'] < $total ) {
							$this->logger->log($params['page'] * $params['page_size'] ." out of ".$listings['count']. " records evaluated.");

							$assoc_args['page'] = $params['page'] + 1;
							$assoc_args['page_size'] = $params['page_size'];

							if ($next_chapter) {
									as_enqueue_async_action( 'wp_otis_async_bulk_import', ['params' => $assoc_args] );
							} else {
									$this->_import_pois( $assoc_args );
							}
					}

					if (isset( $assoc_args['all'] ) && ($params['page'] == $total) && $total > $chapter_size ) {
							$this->logger->log($listings['count'] ." out of ".$listings['count']. " records evaluated.");

							update_option( WP_OTIS_BULK_IMPORT_ACTIVE, false );

							as_unschedule_all_actions( 'wp_otis_async_bulk_import' );

							$this->logger->log("OTIS bulk import complete.");
					}
				} else {
					$this->logger->log("No POIs to import.");
				}
    }

	/**
	 * Import a single OTIS POI.
	 *
	 * @param array $assoc_args
	 */
	private function _import_poi( $assoc_args = [] ) {
		$params = [
			'showexpired' => 'true',
		];
		$result = $this->otis->call( 'listings/' . $assoc_args['uuid'], $params );

		if ( empty( $result['uuid'] ) ) {
			throw new Otis_Exception( 'WP Error: POI not found for uuid ' . $assoc_args['uuid'] );
		}

		$post_id = wp_otis_get_post_id_for_uuid( $result['uuid'] );

		try {
			$post_id = $this->_upsert_poi( $post_id, $result );
		} catch ( Exception $exception ) {
			$this->logger->log( $exception->getMessage(), $post_id, 'error' );
		}
	}

	/**
	 * Import POI history, triggering publish, unpublish, and deletes on POIs.
	 *
	 * @param array $assoc_args
	 */
	private function _import_history( $assoc_args = [] ) {

		$bulk = get_option( WP_OTIS_BULK_IMPORT_ACTIVE, false );

		if (!$bulk) {
			$transient_history = get_transient(WP_OTIS_BULK_IMPORT_TRANSIENT);

			if ( empty( $transient_history ) ) {
				// If there is no data to process, go get it.
				$this->_fetch_history( $assoc_args );
			} elseif ( isset( $transient_history["history-complete"] )
				&& isset( $transient_history["history-data"] )
				&& !$transient_history["history-complete"] ) {
				// If a retrieval process is ongoing, continue it.
				$assoc_args['history-page'] = $transient_history['history-page'];
				$this->_fetch_history($assoc_args, $transient_history['history-data']);
			}

			// This may have changed during the _fetch_history call, so retrieve it again.
			$transient_history = get_transient(WP_OTIS_BULK_IMPORT_TRANSIENT);

			// Did the most recent fetch job finish filling out the retrieved history data?
			if ( !$transient_history["history-complete"] ) {
				// If not, enqueue a follow-up.
				$bulk_history_params = [
					'params' => [
						'all' => $assoc_args['all'],
						'page' => 1,
						'related_only' => $assoc_args['related_only']
					]
				];
				$bulk_history_params['params'] = wp_otis_make_modified_date_param($assoc_args, $bulk_history_params['params']);
				as_enqueue_async_action('wp_otis_async_bulk_history_import', $bulk_history_params);
			} else {
				// If retrieval is complete, get the data from the transient.
				$history 			      = $transient_history["history-data"];
				$history_page_size  = 500;
				$history_bulk       = get_option( WP_OTIS_BULK_HISTORY_ACTIVE, false );
				$history_total      = count($history);
				$history_page_count = ceil( $history_total / $history_page_size );
				$history_deletes    = [];

				if ( $history_page_count > 1 ) {
					if ( !$history_bulk ) {
						update_option(WP_OTIS_BULK_HISTORY_ACTIVE, true);
						$assoc_args['bulk-history-page'] = 1;
						$history_bulk = true;
						$logger_date = wp_otis_get_logger_modified_date_string($assoc_args);
						$this->logger->log("OTIS bulk history import detected: " . $history_total . " updates. " . $logger_date);
					}
				} else {
					$logger_date = wp_otis_get_logger_modified_date_string($assoc_args);
					$this->logger->log("OTIS nonbulk history import detected: " . $history_total . " updates. " . $logger_date);
				}

				$uuids = array_keys($history);

				if ($history_bulk) {
					// Trim any history updates that have already been processed:
					$history_trim = $history_page_size * ($assoc_args['bulk-history-page'] - 1);
					$uuids = array_slice($uuids, $history_trim, $history_page_size);
				}

				// Run the big query against up to 500 UUIDs from the set of listing keys.
				$the_query = new WP_Query([
					'no_found_rows' => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'posts_per_page' => -1,
					'post_status' => 'any',
					'post_type' => 'poi',
					'meta_key' => 'uuid',
					'meta_value' => $uuids,
				]);

				while ($the_query->have_posts()) {
					$the_query->the_post();

					$uuid = get_field('uuid');
					$poi_history = $history[$uuid];

					switch ($poi_history['verb']) {
						case 'updated':
							$post_status = $this->_get_post_status($poi_history);
							if (get_post_status() !== $post_status && ! in_array( get_the_ID(), $history_deletes, true )) {
								wp_update_post([
									'ID' => get_the_ID(),
									'post_status' => $post_status,
								]);
							}
							break;

						case 'deleted':
							$history_deletes[] = get_the_ID();
							break;
					}
				}

				// Run through the post ids in history_deletes and trash the posts.
				foreach ($history_deletes as $post_id) {
					$this->logger->log('Trashing post ' . $post_id);
					wp_trash_post($post_id);
				}

				// Wrap up this job: either enqueue a follow-up and leave the transient and flags intact,
				// or clear them and log the completed result.
				if ($history_bulk) {
					if ($assoc_args['bulk-history-page'] < $history_page_count) {
						$assoc_args['bulk-history-page'] = $assoc_args['bulk-history-page'] + 1;
						$bulk_history_params = [
							'params' => [
								'all' => $assoc_args['all'],
								'page' => $assoc_args['bulk-history-page'],
								'related_only' => $assoc_args['related_only']
							]
						];
						$bulk_history_params['params'] = wp_otis_make_modified_date_param($assoc_args, $bulk_history_params['params']);
						as_enqueue_async_action('wp_otis_async_bulk_history_import', $bulk_history_params);
					} elseif ($assoc_args['bulk-history-page'] == $history_page_count) {
						update_option(WP_OTIS_BULK_HISTORY_ACTIVE, false);
						delete_transient(WP_OTIS_BULK_IMPORT_TRANSIENT);
						as_unschedule_all_actions('wp_otis_async_bulk_history_import');
						$this->logger->log("OTIS bulk history import complete.");
					}
				} else {
					update_option(WP_OTIS_BULK_HISTORY_ACTIVE, false);
					delete_transient(WP_OTIS_BULK_IMPORT_TRANSIENT);
					as_unschedule_all_actions('wp_otis_async_bulk_history_import');
					$this->logger->log("OTIS nonbulk history import complete.");
				}
			}

			wp_reset_postdata();
		}
	}

	/**
	 * Fetch OTIS history (updates/deletes) since last import date from the API.
	 * All data is fetched in its entirety from OTIS for each bulk history import call, then WP determines which "page"
	 * of that response to process in order to prevent memory timeout issues for large datasets
	 *
	 * @param array $assoc_args
	 *
	 * @return array
	 */
    private function _fetch_history( $assoc_args = [], $transient_history = null ) {

        $params = [
            'page_size' => 500,
            'page'      => $assoc_args['history-page'] ?? 1,
        ];

        if ( isset( $assoc_args['modified'] ) ) {
            $params['after']   = date( 'Y-m-d', strtotime( $assoc_args['startdate'] ) );
            $assoc_args['all'] = true;
				// Check if we're doing a modified_start date without an end date. If so fallback to basic modified date param.
				} else if ( isset( $assoc_args['modified_start'] ) && ! isset( $assoc_args['modified_end'] ) ) {
						$params['after'] = date( 'Y-m-d', strtotime( $assoc_args['modified_start'] ) );
						$assoc_args['all'] = true;
						// Check if we're both modified start and end dates are present.
				} else if ( isset( $assoc_args['modified_start'] ) && isset( $assoc_args['modified_end'] ) ) {
						// If both modified dates are present check if they're the equal. If so fallback to basic modified date param.
						if ( $assoc_args['modified_start'] === $assoc_args['modified_end'] ) {
								$params['after'] = date( 'Y-m-d', strtotime( $assoc_args['modified_start'] ) );
								$assoc_args['all'] = true;
						// If they're different use the after & before parameters
						} else {
								$params['after']  = date( 'Y-m-d', strtotime( $assoc_args['modified_start'] ) );
								$params['before'] = date( 'Y-m-d', strtotime( $assoc_args['modified_end'] ) );
								$assoc_args['all'] = true;
						}
        } else {
            // Only import history relative to a recent import.
            return [];
        }

        $listings = $this->otis->call( 'listings/history', $params );
				$total = ceil( $listings['count'] / $params['page_size'] );

				$this->logger->log('Pre-fetching page ' . $params['page'] . ' of ' . $total . ' of ' . $listings['count'] . ' history items');

        $history = !empty($transient_history) ? $transient_history : [];

        if ( ! empty( $listings['results'] ) ) {
            foreach ( $listings['results'] as $result ) {
                $uuid = $result['uuid'];
                $verb = $result['verb'];

                if ( !array_key_exists( $uuid, $history ) && ( 'updated' === $verb || 'deleted' === $verb ) ) {

                    // Results are ordered by modified - only store the most recent update or delete for each uuid
                    $isapproved = $result['data']['isapproved'] ?? '';

                    $end_date = '';
                    if ( ! empty( $result['data']['attributes'] ) ) {
                        foreach ( $result['data']['attributes'] as $attribute ) {
                            if ( ! empty( $attribute['schema']['name'] ) && 'end_date' === $attribute['schema']['name'] ) {
                                $end_date = $attribute['value'];
                            }
                        }
                    }

                    $history[$uuid] = [
                        'verb' => $verb,
                        'isapproved' => $isapproved,
                        'end_date' => $end_date,
                    ];

                }
            }

            unset( $listings );

            if ( $params['page'] < $total ) {
                set_transient(WP_OTIS_BULK_IMPORT_TRANSIENT,
					[
						"history-page" => $params['page'] + 1,
						"history-data" => $history,
						"history-complete" => false,
					],
					HOUR_IN_SECONDS);
                return false;
            }

        }

		set_transient(WP_OTIS_BULK_IMPORT_TRANSIENT,
			[
				"history-complete" => true,
				"history-data" => $history,
			],
			HOUR_IN_SECONDS);
        return true;
    }

	/**
	 * Create/update a WordPress POI based on OTIS result data. If post_id is
	 * empty, a new POI will be created. Otherwise the specified POI will be
	 * updated.
	 *
	 * @param int $post_id
	 * @param array $result
	 *
	 * @throws \Otis_Exception
	 */
    private function _upsert_poi( $post_id, $result, $related_only = false ) {
		$field_group = wp_otis_fields_load();
		$field_map   = [];
		foreach ( $field_group['fields'] as $field ) {
			$field_map[ $field['name'] ] = $field;
		}

		$type = strtolower( $result['type']['name'] );

		$type_schema = $this->_get_type_schema( $result['type'] );

		// Pre-populate all attribute fields with empty values.
		foreach ( $type_schema as $attribute_schema ) {
			// Careful not to clobber any existing values
			if ( ! isset( $result[ $attribute_schema['name'] ] ) ) {
				$result[ $attribute_schema['name'] ] = '';
			}
		}

	    if ( ! empty( $result['listing_credit'] ) ) {
		    $listing_credit = $result['listing_credit'];
		    unset($result['listing_credit']);
	    }

		// Prep attribute data for field lookup.
		foreach ( $result['attributes'] as $attribute ) {
			$result[ $attribute['schema']['name'] ] = $attribute['value'];
		}

        $has_related_pois = false;

		// Prep media data for field lookup.
		foreach ( $result['media'] as $media_type => $items ) {
			// Stable sort, obeying 'ordering' value if it's set.
			$orderings = [];
			$indexes   = [];
			foreach ( $items as $index => $item ) {
				$orderings[ $index ] = $item['ordering'] ?? 0;
				$indexes[ $index ]   = $index;
			}
			array_multisort( $orderings, SORT_NUMERIC, $indexes, SORT_NUMERIC, $items );

			$result[ $media_type ] = $items;
		}

		// Prep relation data for field lookup.
		foreach ( $result['relations'] as $relation ) {
			$relationship_type = $relation['relationship_type']['name'];
			switch ( $relationship_type ) {
				case 'Primary Region':
					if ( 'regions' !== $type ) {
						// Exclude primary region relations for region objects.
						$result[ $relationship_type ] = $relation;
					}
					break;

				case 'Primary City':
					if ( 'regions' !== $type && 'cities' !== $type ) {
						// Exclude city relations for region and city objects.
						$result[ $relationship_type ] = $relation;
					}
					break;

				case 'Additional City':
				case 'Additional Region':
				case 'Nearby Towns & Cities':
                case 'Another Listing':
                    $has_related_pois = true;
                    $other_id = wp_otis_get_post_id_for_uuid($relation['uuid']);
                    $relation['post_id'] = $other_id;
                    $result[ $relationship_type ][] = $relation;
                    break;

				default:
					$result[ $relationship_type ][] = $relation;
					break;
			}
		}

        // Prep reverse relations data for field lookup.

        if (isset($result['reverse_relations'])) {
            // Prep reverse relations data for field lookup.
            foreach ( $result['reverse_relations'] as $relation ) {
                $relationship_type = $relation['relationship_type']['name'];
                switch ( $relationship_type ) {
	                case 'Additional City':
	                case 'Additional Region':
	                case 'Nearby Towns & Cities':
                    case 'Another Listing':
                        $has_related_pois = true;
                        $other_id = wp_otis_get_post_id_for_uuid($relation['uuid']);
                        $relation['post_id'] = $other_id;
                        $result[ $relationship_type ][] = $relation;
                        break;
                }
            }
        }
        if (!$related_only || ($related_only && $has_related_pois)) {

            // Prep geo data for field lookup.
            if (isset($result['geo_data'])) {
                $result['geo_data'] = json_encode($result['geo_data']);
            }

            // Normalize and translate OTIS result data into WordPress field data.
            $data = [];
            foreach ($result as $key => $value) {
                $name = $this->_translate_field_name($key);
                $is_term = null;

                switch ($name) {
                    case 'type':
                    case 'glocats':
                        $value = $this->_translate_taxonomy_value($name, $value);
                        $is_term = true;
                        break;

                    default:
                        $field = $field_map[$name] ?? null;
                        $value = $this->_translate_field_value($field, $value);
                        $is_term = ('taxonomy' === $field['type']);
                        break;
                }

                if (!empty($data[$name]) && $is_term) {
                    $data[$name] = array_merge($data[$name], $value);
                } else {
                    $data[$name] = $value;
                }
            }

            $upsert_status = $post_id ? 'updated' : 'created';

	        // Since we'll re-save related POIs anyway, emptying the listings now ensures removal of any deleted relations in OTIS.
	        if ( 'updated' === $upsert_status ) {
		        delete_post_meta($post_id, 'another_listing');
	        }

            $post_status = $this->_get_post_status($result);
            $post_title = $result['name'];
            $post_content = empty($result['description']) ? '' : $this->_sanitize_content($result['description']);
            $post_date = empty($result['modified']) ? '' : date('Y-m-d H:i:s', strtotime($result['modified']));

            $post_result = wp_insert_post([
                'post_type' => 'poi',
                'post_status' => $post_status,
                'ID' => $post_id,
                'post_title' => $post_title,
                'post_name' => '', // Empty = auto-generate.
                'post_content' => $post_content,
                'post_date_gmt' => $post_date,
            ], true);

            if (!$post_result) {
								$this->logger->log('Error: POI not ' . $upsert_status . ', uuid ' . $result['uuid']);
                throw new Otis_Exception('Error: POI not ' . $upsert_status . ', uuid ' . $result['uuid']);
            } elseif (is_wp_error($post_result)) {
								$this->logger->log('Error: POI not ' . $upsert_status . ', uuid ' . $result['uuid']);
                throw new Otis_Exception('Error: POI not ' . $upsert_status . ', uuid ' . $result['uuid'] . ', ' . $post_result->get_error_message());
            } else {
                $post_id = $post_result;
            }

            foreach ($field_map as $name => $field) {
                if (isset($data[$name])) {
                    $return = update_field($name, $data[$name], $post_id);

                    if (is_wp_error($return)) {
												$this->logger->log('Error: field ' . $name . ', post id ' . $post_id . ', ' . $return->get_error_message());
                        throw new Otis_Exception('Error: field ' . $name . ', post id ' . $post_id . ', ' . $return->get_error_message());
                    }
                }
            }

	        if ( isset( $listing_credit ) ) {
		        update_field('listing_credit_id', $listing_credit['id'], $post_id);
		        update_field('listing_credit_name', $listing_credit['name'], $post_id);
		        update_field('listing_credit_caption', $listing_credit['caption'], $post_id);
		        update_field('listing_credit_url', $listing_credit['url'], $post_id);
	        }

            // Save collection, type, and activities.
            $return = wp_set_object_terms($post_id, $data['type'], 'type');

            if (is_wp_error($return)) {
								$this->logger->log('Error: taxonomy type, post id ' . $post_id . ', ' . $return->get_error_message());
                throw new Otis_Exception('Error: taxonomy type, post id ' . $post_id . ', ' . $return->get_error_message());
            }

            // Save global categories.
            $return = wp_set_object_terms($post_id, $data['glocats'], 'glocats');

            if (is_wp_error($return)) {
								$this->logger->log('Error: taxonomy glocats, post id ' . $post_id . ', ' . $return->get_error_message());
                throw new Otis_Exception('Error: taxonomy glocats, post id ' . $post_id . ', ' . $return->get_error_message());
            }

            $this->logger->log(ucfirst($upsert_status) . ' POI with UUID: ' . $result['uuid'], $post_id);

            return $post_id;
        }
	}

	/**
	 * Sanitize Otis content, for WordPress post content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	private function _sanitize_content( $content ) {
		$allowed_tags = [
			'a'      => [
				'href'   => true,
				'target' => true,
			],
			'b'      => [],
			'br'     => [],
			'div'    => [],
			'em'     => [],
			'h1'     => [],
			'h2'     => [],
			'h3'     => [],
			'h4'     => [],
			'h5'     => [],
			'h6'     => [],
			'hr'     => [],
			'i'      => [],
			'li'     => [],
			'p'      => [],
			'span'   => [],
			'strong' => [],
			'ul'     => [],
			'ol'     => [],
		];

		// Remove scripts/styles completely, before kses. Pulled from wp_strip_all_tags()
		$content = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $content );

		return wp_kses( $content, $allowed_tags );
	}

	/**
	 * Convert an OTIS field name into a WordPress field name.
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	private function _translate_field_name( $name ) {
		$name = strtolower( preg_replace( '/[^\w]/', '_', $name ) );

		if ( array_key_exists( $name, $this->attribute_map ) ) {
			return $this->attribute_map[ $name ];
		}

		return $name;
	}

	/**
	 * Convert an OTIS field value into a WordPress field value.
	 *
	 * @param array $field
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private function _translate_field_value( $field, $value ) {
		if ( $field && $value ) {
			if ( is_array( $value ) && isset( $value[0] ) ) {
				return array_map( function ( $item ) use ( $field ) {
					return $this->_translate_field_value( $field, $item );
				}, $value );
			}

			if ( 'taxonomy' === $field['type'] ) {
				$value = $this->_translate_taxonomy_value( $field['taxonomy'], $value );
			}

			switch ( $field['name'] ) {
				case 'region':
				case 'city':
					$value = wp_otis_get_post_id_for_uuid( $value['uuid'] );
					break;

				case 'photos':
					$value = [
						'image_url'     => $value['image'],
						'image_name'    => $value['name'],
						'image_caption' => $value['caption'],
						'image_credit'  => $value['photo_credit'],
						'image_alt'     => $value['alt_text'],
					];
					break;
			}
		}

		return $value;
	}

	/**
	 * Calculate the WordPress post status value for an OTIS result array.
	 *
	 * @param string $otis_result
	 *
	 * @return string
	 */
	private function _get_post_status( $otis_result ) {

		$toonly = false;

		$assoc_args = apply_filters( 'wp_otis_listings', array() );
		if ( ! empty( $assoc_args['set'] ) && 'toonly' === $assoc_args['set'] ) {
			$toonly = true;
		}

		if ( ! empty( $otis_result['end_date'] ) ) {
			$end_timestamp = strtotime( $otis_result['end_date'] );
			if ( time() - $end_timestamp > DAY_IN_SECONDS ) {
				return 'draft';
			}
		}

		$approval = strtolower( $otis_result['isapproved'] );

		if ( ! empty( $approval ) ) {
			if ( 'app' == $approval ) {
				return 'publish';
			}
			if ( $toonly == false && ( 'gen' == $approval || 'pen' == $approval ) ) {
				return 'publish';
			}
		}

		return 'draft';
	}

	/**
	 * Convert an OTIS field value into a WordPress taxonomy value.
	 *
	 * @param string $taxonomy
	 * @param mixed $value
	 *
	 * @return array
	 */
	function _translate_taxonomy_value( $taxonomy, $value ) {
		if ( $value ) {
			if ( is_array( $value ) && isset( $value[0] ) ) {
				$tax_array = array_map( function ( $item ) use ( $taxonomy ) {
					return $this->_translate_taxonomy_value( $taxonomy, $item );
				}, $value );
				return array_flatten( $tax_array, PHP_INT_MAX );
			}

			$terms = null;

            if ( is_array( $value ) ) {
                $terms = [ $value ];
            } else {
                if ((strpos($value, '| ') !== false)  || (strpos($value, ', ') === false)) {
                    $terms = array_filter( explode( '| ', $value ) );
                } else {
                    $terms = array_filter( explode( ', ', $value ) );
                }
            }

			$value = array_filter( array_map( function ( $term_name ) use ( $taxonomy ) {
				return $this->_identify_term( $term_name, $taxonomy );
			}, $terms ) );
		} else {
			$value = [];
		}

		return $value;
	}

	/**
	 * Fetch WordPress term_id and term_taxonomy_id details for an OTIS field
	 * value. This method will add a new term if it does not exist.
	 *
	 * @param array|string $value The term to identify - a string, or OTIS value
	 *   structure.
	 * @param string $taxonomy The taxonomy to which to add the term.
	 * @param array|string $args {
	 *     Optional. Array or string of arguments for inserting a term.
	 *
	 * @type string $alias_of Slug of the term to make this term an alias of.
	 *                               Default empty string. Accepts a term slug.
	 * @type string $description The term description. Default empty string.
	 * @type int $parent The id of the parent term. Default 0.
	 * @type string $slug The term slug to use. Default empty string.
	 *
	 * @return int|\WP_Error A term_id, or WP_Error object.
	 * @throws \Otis_Exception
	 * @internal param int $parent The id of the parent term. Default 0.
	 */
	private function _identify_term( $value, $taxonomy, $args = [] ) {
		$result    = null;
		$otis_path = null;
		$name      = null;

		$term_args = [
			'hide_empty' => false,
			'number'     => 1,
			'fields'     => 'ids',
			'taxonomy'   => $taxonomy,
		];

		if ( is_array( $value ) ) {
			$url = wp_parse_url( str_replace( Otis::API_ROOT, '', $value['uri'] ) );

			$otis_path = $url['path'];
			$name      = trim( $value['title'] ?? $value['name'] );

			$term_args['meta_key']   = 'otis_path';
			$term_args['meta_value'] = $otis_path;
		} else {
			$name = trim( $value );

			$term_args['name'] = $name;
		}

		$terms   = get_terms( $term_args );
		$term_id = $terms ? $terms[0] : null;

		if ( ! $term_id ) {
			// Check for an unmapped Type term.
			$term_exists = term_exists( $name, $taxonomy );
			if ( $term_exists ) {
				$term_id = intval( $term_exists['term_id'] );
			} else {
				$result = wp_insert_term( $name, $taxonomy, $args );

				if ( is_wp_error( $result ) ) {
					throw new Otis_Exception( 'Error: taxonomy ' . $taxonomy . ', ' . $result->get_error_message() );
				}

				$term_id = $result['term_id'];
			}

			if ( $otis_path ) {
				$meta_result = add_term_meta( $term_id, 'otis_path', $otis_path );

				if ( is_wp_error( $meta_result ) ) {
					throw new Otis_Exception( 'Error: taxonomy ' . $taxonomy . ', term id: ' . $term_id . ', ' . $meta_result->get_error_message() );
				}
			}
		}

		return $term_id ? intval( $term_id ) : null;
	}

	/**
	 * Generate ACF from OTIS.
	 */
	function generate_acf() {
		$field_group = wp_otis_fields_load();
		$field_map   = [];
		foreach ( $field_group['fields'] as $field ) {
			$field_map[ $field['name'] ] = $field;
		}

		$attributes = $this->otis->call( 'listings-attributes' );

		$fields_count = 0;

		foreach ( $attributes['results'] as $attribute ) {
			$key  = 'field_otis_' . $attribute['id'];
			$name = $attribute['name'];
			if ( isset( $field_map[ $name ] ) || array_key_exists( $name, $this->attribute_map ) ) {
				// If the field has already been created, don't overwrite.
				// If the field will be mapped to a different field, don't create.
				continue;
			}

			$field = [
				'key'   => $key,
				'label' => str_replace( '_', ' ', $attribute['title'] ),
				'name'  => $name,
			];

			switch ( $attribute['datatype'] ) {
				case 'float':
					$field['type'] = 'number';
					break;
				case 'one':
					$field['type']       = 'taxonomy';
					$field['taxonomy']   = $name;
					$field['field_type'] = 'select';
					break;
				case 'many':
					$field['type']       = 'taxonomy';
					$field['taxonomy']   = $name;
					$field['field_type'] = 'checkbox';
					break;
				case 'bool':
					$field['type'] = 'true_false';
					break;
				case 'date':
					$field['type'] = 'date_picker';
					break;
				default:
					$field['type'] = 'text';
					break;
			}

			$field_map[ $name ] = $field;

			$fields_count ++;
		} // End foreach().

		$field_group['fields'] = array_values( $field_map );

		wp_otis_fields_save( $field_group );

		$log[] = 'Processed ' . $attributes['count'] . ' attributes, generated ' . $fields_count . ' fields.';

		return $log;
	}

	/**
	 * Report on UUID discrepancies between WordPress and OTIS.
	 */
	function report() {
		global $wpdb;

		$log[] = 'Current time: ' . date( 'c' );

		$log[] = 'OTIS last import time: ' . get_option( WP_OTIS_LAST_IMPORT_DATE, 'none' );

		$results = $wpdb->get_results( '
			SELECT wp_posts.ID,
			  wp_posts.post_title,
			  wp_postmeta.meta_value AS \'uuid\',
			  GROUP_CONCAT(wp_terms.slug SEPARATOR \', \') as \'terms\'
			FROM wp_posts
			LEFT JOIN wp_postmeta ON wp_postmeta.meta_key = \'uuid\'
			  AND wp_postmeta.post_id = wp_posts.ID
			LEFT JOIN wp_term_relationships ON wp_posts.ID = wp_term_relationships.object_id
			LEFT JOIN wp_term_taxonomy ON wp_term_taxonomy.taxonomy IN (\'type\', \'glocats\')
			  AND wp_term_taxonomy.term_taxonomy_id = wp_term_relationships.term_taxonomy_id
			LEFT JOIN wp_terms ON wp_terms.term_id = wp_term_taxonomy.term_id
			WHERE wp_posts.post_type = \'poi\'
			  AND wp_posts.post_status = \'publish\'
		  	GROUP BY wp_posts.ID
			ORDER BY wp_posts.post_title ASC
		', ARRAY_A );

		$uuid_map        = [];
		$duplicate_uuids = [];
		$count           = count( $results );

		foreach ( $results as $result ) {
			if ( isset( $uuid_map[ $result['uuid'] ] ) ) {
				$duplicate_uuids[ $result['uuid'] ] = $result;
			} else {
				$uuid_map[ $result['uuid'] ] = $result;
			}
		}

		unset( $results );

		$otis_uuids           = $this->_fetch_otis_uuids();
		$otis_uuid_map        = [];
		$duplicate_otis_uuids = [];
		$otis_count           = count( $otis_uuids );

		foreach ( $otis_uuids as $otis_uuid ) {
			if ( isset( $otis_uuid_map[ $otis_uuid ] ) ) {
				$duplicate_otis_uuids[ $otis_uuid ] = true;
			} else {
				$otis_uuid_map[ $otis_uuid ] = true;
			}
		}

		$log[] = 'Count of UUIDs found in OTIS: ' . $otis_count;
		$log[] = 'Count of UUIDs found in WordPress: ' . $count;

		$log[] = PHP_EOL . 'Duplicates found in OTIS:';

		foreach ( $duplicate_otis_uuids as $otis_uuid => $value ) {
			if ( empty( $uuid_map[ $otis_uuid ] ) ) {
				$log[] = $otis_uuid . '	' . Otis::API_ROOT . '/listings/' . $otis_uuid;
			} else {
				unset( $uuid_map[ $otis_uuid ] );
			}
		}

		$log[] = PHP_EOL . 'Duplicates found in WordPress:';

		foreach ( $duplicate_uuids as $uuid => $result ) {
			$edit_url = add_query_arg( [
				'post'   => $result['ID'],
				'action' => 'edit',
			], admin_url( 'post.php' ) );

			$log[] = $uuid . '	' . $edit_url . '	' . $result['post_title'] . '	' . $result['terms'];
		}

		$log[] = PHP_EOL . 'UUIDs from OTIS not found in WordPress:';

		foreach ( $otis_uuid_map as $otis_uuid => $value ) {
			if ( empty( $uuid_map[ $otis_uuid ] ) ) {
				$log[] = $otis_uuid . '	' . Otis::API_ROOT . '/listings/' . $otis_uuid;
			} else {
				unset( $uuid_map[ $otis_uuid ] );
			}
		}

		$log[] = PHP_EOL . 'UUIDs from WordPress not found in OTIS:';

		foreach ( $uuid_map as $uuid => $result ) {
			$edit_url = add_query_arg( [
				'post'   => $result['ID'],
				'action' => 'edit',
			], admin_url( 'post.php' ) );

			$log[] = $uuid . '	' . $edit_url . '	' . $result['post_title'] . '	' . $result['terms'];
		}

		return $log;
	}

	/**
	 * Fetch all active uuids from OTIS.
	 */
	function _fetch_otis_uuids( $params = [] ) {
		if ( ! $params ) {
			$params = [
				'showexpired' => 'false',
			];
			$params = apply_filters( 'wp_otis_listings', $params );

			$params['page_size'] = 200;
			$params['page']      = 1;
		}

		$listings = $this->otis->call( 'listings', $params );

		if ( empty( $listings['results'] ) ) {
			throw new Otis_Exception( 'Unable to fetch uuids from OTIS.' );
		}

		$uuids = array_pluck( $listings['results'], 'uuid' );
		$total = ceil( $listings['count'] / $params['page_size'] );

		unset( $listings );

		if ( $params['page'] < $total ) {
			$params['page'] = $params['page'] + 1;

			return array_merge( $uuids, $this->_fetch_otis_uuids( $params ) );
		}

		return $uuids;
	}

}
