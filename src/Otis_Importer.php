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
	 * Determines array_chunk size of listings to process.
	 * @var int
	 */
	protected $processing_chunk_size;

	/**
	 * Otis_Importer constructor.
	 *
	 * @param Otis $otis
	 * @param Otis_Logger $logger
	 */
	public function __construct( $otis, $logger ) {
		$this->otis   = $otis;
		$this->logger = $logger;
		$this->processing_chunk_size = apply_filters( 'wp_otis_processing_chunk_size', 5 );
		$this->processing_chunk_size = intval( $this->processing_chunk_size, 10 ) > 0 ? intval( $this->processing_chunk_size, 10 ) : 5;
	}

	/** Cancel current Import and Process */
	public function cancel_import( $starting_log_message = 'Cancelling import...', $log_message = 'Import cancelled.' ) {
		$this->logger->log( $starting_log_message );
		do_action( 'wp_otis_cancel_import' );
		$this->_cancel_import( $log_message );
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
		// Check if the cancel flag is set.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		if ( ! $args ) {
			$args = [ 'pois' ];
		} elseif ( ! is_array( $args ) ) {
			$args = [ $args ];
		}

    $import_active = isset( $assoc_args['bulk'] ) ? $assoc_args['bulk'] : get_option( WP_OTIS_IMPORT_ACTIVE, false );
		if ( $import_active && ! get_option( WP_OTIS_IMPORT_ACTIVE, false ) ) {
			$this->logger->log( 'Import in progress, pausing cron based imports.' );
			$this->start_bulk();
		}

		// Apply otis_listings filter to $assoc_args.
		$assoc_args = apply_filters( 'wp_otis_listings', $assoc_args );

		switch ( $args[0] ) {
			case 'terms':
				$this->_import_terms( $assoc_args );

				$log[] = 'Terms import complete.';

				return $log;

			case 'regions':
				$assoc_args['type'] = 'Regions';
				$assoc_args['all']  = true;

				$this->_fetch_otis_listings( $assoc_args );

				$log[] = 'Regions import complete.';

				return $log;

			case 'cities':
				$assoc_args['type'] = 'Cities';
				$assoc_args['all']  = true;

				$this->_fetch_otis_listings( $assoc_args );

				$log[] = 'Cities import complete.';

				return $log;

			case 'pois':
					$this->import( 'terms', $assoc_args );
					$this->import( 'regions', $assoc_args );
					$this->import( 'cities', $assoc_args );
					$assoc_args['type'] = 'pois';

					$this->_fetch_otis_listings( $assoc_args );
					// $this->_import_history( $assoc_args );

					$log[] = 'Import continuing with scheduled actions.';

					return $log;

			case 'pois-only':
					$assoc_args['type'] = 'pois';
					$this->_fetch_otis_listings( $assoc_args );
					// $this->_import_history( $assoc_args );

					$log[] = 'Import continuing with scheduled actions.';

					return $log;

			case 'related-pois-only':
					$assoc_args['related_only'] = true;
					$this->_fetch_otis_listings( $assoc_args );

					$log[] = 'Import continuing with scheduled actions.';

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
			
			case 'all-listings':
				if ( empty( $assoc_args['sync_page'] ) ) {
					$assoc_args['sync_page'] = 1;
				}
				$this->_fetch_all_active_listings( $assoc_args );

				$log[] = 'All listings importing.';

				return $log;

		} // End switch().

		throw new Otis_Exception( 'Unknown command: ' . $args[0] );
	}

	/** Process Transient Data */
	public function process_listings( $assoc_args ) {
		$this->logger->log( 'Initialized processing of ' . $assoc_args['type'] . ' listing with UUID: ' . $assoc_args['listing_uuid'] );
		$this->_process_listings( $assoc_args );
	}

	/** Delete POIs */
	public function delete_removed_listings( $assoc_args ) {
		$this->logger->log( 'Deleting removed listings...' );
		$this->_delete_removed_listings( $assoc_args );
	}

	/** Set All POIs Transient for Sync All Actions */
	public function set_all_pois_transient() {
		$this->logger->log( 'Setting all POIs transient...' );
		$this->_set_all_pois_transient();
	}

	/** Process Sync All Listings Transient Data */
	public function remove_sync_all_inactive_listings( $assoc_args ) {
		$this->logger->log( 'Checking active POIs against OTIS...' );
		$this->_remove_all_inactive_listings( $assoc_args );
	}

	/** Process Sync All Listings Transient Data */
	public function import_sync_all_active_listings( $assoc_args ) {
		$this->logger->log( 'Checking for missing POIs...' );
		$this->_import_all_active_listings( $assoc_args );
	}

	public function sync_listing_type_libraries() {
		$this->logger->log( 'Syncing listing type libraries...' );
		$this->_sync_listing_type_libraries();
	}

    /**
     * Sets bulk importer flag to false
     *
     * @return array
     */
    function nobulk() {
        update_option( WP_OTIS_IMPORT_ACTIVE, false );
        $log[] = 'OTIS import active flag set to false';
        return $log;
    }

		/**
		 * Sets bulk importer flag to true
		 *
		 * @return array
		 */
		function start_bulk() {
			update_option( WP_OTIS_IMPORT_ACTIVE, true );
			$log[] = 'OTIS import active flag set to true';
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
		$post_id = $post_id ?: 0;

		try {
			$post_id = $this->_upsert_poi( $post_id, $result );
		} catch ( Exception $exception ) {
			$this->logger->log( $exception->getMessage(), $post_id, 'error' );
		}
	}

	
	/** Make Listings Transient Key */
	private function make_listings_transient_key( $listings_type ) {
		$listings_key_type = strtolower( $listings_type );
		$listings_key_type = str_replace( ' ', '_', $listings_key_type );
		return 'wp_otis_listings' . '_' . $listings_key_type;
	}
	
	/** Get Listings transient if it exists */
	private function get_listings_transient( $listings_type = 'pois' ) {
		$transient_key = $this->make_listings_transient_key( $listings_type );
		return get_transient( $transient_key );
	}
	
	/** Set Listings transient */
	private function set_listings_transient( $data = [], $listings_type = 'pois' ) {
		$transient_key = $this->make_listings_transient_key( $listings_type );
		return set_transient( $transient_key, $data, 21600 );
	}

	/** Delete Listings transient */
	private function delete_listings_transient( $listings_type = 'pois' ) {
		$transient_key = $this->make_listings_transient_key( $listings_type );
		return delete_transient( $transient_key );
	}

	/** Schedule action scheduler action */
	private function schedule_action($action, $args = []) {
		$timestamp = as_next_scheduled_action( $action, $args );
		if ( false === $timestamp ) {
			as_enqueue_async_action( $action, $args );
		}
	}

	/** Unschedule action scheduler action */
	private function unschedule_action($action, $args = []) {
		$timestamp = as_next_scheduled_action($action, $args);
		if ( false !== $timestamp ) {
			as_unschedule_all_actions($action, $args);
		}
	}

	/**
	 * Removes all scheduled actions, deletes transients, and resets flags for the OTIS import.
	 */
	private function _cancel_import( $log_message = 'Import canceled' ) {
		// Unschedule all actions.
		$this->unschedule_action( 'wp_otis_fetch_listings' );
		$this->unschedule_action( 'wp_otis_process_single_listing' );
		$this->unschedule_action( 'wp_otis_delete_removed_listings' );
		$this->unschedule_action( 'wp_otis_sync_all_listings' );
		$this->unschedule_action( 'wp_otis_sync_all_listings_fetch' );
		$this->unschedule_action( 'wp_otis_sync_all_listings_process' );
		$this->unschedule_action( 'wp_otis_sync_all_listings_import' );
		$this->unschedule_action( 'wp_otis_sync_all_listings_posts_transient' );
		// Delete all transients.
		$this->delete_listings_transient( 'pois' );
		$this->delete_listings_transient( 'regions' );
		$this->delete_listings_transient( 'cities' );
		$this->delete_listings_transient( 'activeIds' );
		$this->delete_listings_transient( 'allPoiPosts' );
		// Set OTIS import options to false.
		update_option( WP_OTIS_CANCEL_IMPORT, false );
		update_option( WP_OTIS_IMPORT_ACTIVE, false );
		// Enable caching.
		// $this->_toggle_caching( true );
		// Log the cancel.
		$this->logger->log( $log_message );
	}

	/**
	 * Toggle caching global
	 */
	private function _toggle_caching( $enabled = true ) {
		if ( $enabled && WP_OTIS_BULK_DISABLE_CACHE ) {
			// Enable caching.
			define( 'WP_OTIS_BULK_DISABLE_CACHE', false );
			$this->logger->log( 'Caching enabled.' );
		} else if ( ! $enabled && ! WP_OTIS_BULK_DISABLE_CACHE ) {
			// Disable caching.
			define( 'WP_OTIS_BULK_DISABLE_CACHE', true );
			$this->logger->log( 'Caching disabled.' );
		}
	}

	/**
	 * Reset Importer Active Flag
	 */
	private function _reset_importer_active_flag() {
		// Reset WP_OTIS_IMPORT_ACTIVE option to false.
		update_option( WP_OTIS_IMPORT_ACTIVE, false );
		// Log the reset.
		$this->logger->log( 'Automatic imports restarting.' );
	}

	/** Fetch listings from OTIS with passed args and store them in a transient for later use */
	private function _fetch_otis_listings( $assoc_args = [] ) {
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		// Set the WP_OTIS_IMPORT_ACTIVE option to true if it's not already set.
		if ( ! get_option( WP_OTIS_IMPORT_ACTIVE, false ) ) {
			update_option( WP_OTIS_IMPORT_ACTIVE, true );
			$this->logger->log( 'Pausing automatic imports.' );
		}
		// Run actions and filters to allow other plugins to modify the API params.
		do_action( 'wp_otis_before_fetch_listings', $assoc_args );
		$api_params = apply_filters( 'wp_otis_listings_api_params', $assoc_args );

		// Look for listing page in args and set it to the first one if it's not present.
		$listings_page = ! empty( $assoc_args['page'] ) ? $assoc_args['page'] : 1;
		// Look for listing type in args and set it to pois if it's not present.
		$listings_type = ! empty( $assoc_args['type'] ) ? $assoc_args['type'] : 'pois';
		// Create API params to pass to array.
		$api_params    = [
			'page'      => $listings_page,
		];
		// Check if we have a modified date and add it to the API params in MM/DD/YYYY format if we do.
		if ( $assoc_args['modified'] ) {
			$api_params['modified'] = date( 'm/d/Y', strtotime( $assoc_args['modified'] ) );
		}
		// Merge API params with passed args.
		$api_params    = array_merge( $assoc_args, $api_params );
		// Check if API params type is pois and unset it if so (OTIS listings are all POIs).
		if ( 'pois' === $api_params['type'] ) {
			unset( $api_params['type'] );
		}
		// Fetch listings from OTIS.
		$this->logger->log( 'Fetching page ' . $listings_page . ' of ' . $listings_type );
		$listings = $this->otis->call( 'listings', $api_params, $this->logger );
		// Check if we have any listings and if there are more pages.
		$listings_next = is_null( $listings['next'] ) ? $listings['next'] : trim( $listings['next'] );
		$has_next_page = empty( $listings_next ) || 'null' === $listings_next ? false : true;
		$listings_total = $listings['count'] ?? 0;
		$listings = $listings['results'] ?? [];

		// Loop through listings and add end date to each one.
		foreach ($listings as &$listing) {
			$end_date = '';
			if ( ! empty( $listing['attributes'] ) ) {
				foreach ( $listing['attributes'] as $attribute ) {
					if ( ! empty( $attribute['schema']['name'] ) && 'end_date' === $attribute['schema']['name'] ) {
						$end_date = $attribute['value'];
					}
				}
			}
			$listing['end_date'] = $end_date;
		}

		// If we have listings, store them in a transient.
		$listings_transient = $this->get_listings_transient( $listings_type );
		$listings_transient = $listings_transient ? $listings_transient : [];
		$listings_transient = array_merge( $listings_transient, $listings );
		$this->set_listings_transient( $listings_transient, $listings_type );
		// If we have more pages, schedule another action to fetch them.
		if ( $has_next_page ) {
			$api_params['page'] = intval( $listings_page ) + 1;
			// Importer import function expects import type and pois is the general call so we need to pass change the type to pois-only if it's pois.
			$api_params['type'] = $listings_type === 'pois' ? 'pois-only' : $listings_type;
			$this->schedule_action( 'wp_otis_fetch_listings', [ 'params' => $api_params ] );
			$this->logger->log( 'Scheduling fetch of next page of ' . $listings_type );
			return;
		}
		// Do post fetch actions.
		do_action( 'wp_otis_after_fetch_listings', $assoc_args );

		// If we don't have more pages, check if there are listings to process and schedule actions for each.
		if ( count( $listings_transient ) ) {
			// Log that we're scheduling processing actions.
			$this->logger->log( 'No more pages to fetch. Scheduling process actions...' );
			// Add listings_total to api args to pass forward to processing.
			$api_params['listings_total'] = $listings_total;
			foreach ( $listings_transient as $listing_key => $listing ) {
				$params = [
					'listing_uuid' => $listing['uuid'],
					'listing_number' => $listing_key + 1,
					'import_args' => $api_params,
				];
				$this->schedule_action( 'wp_otis_process_single_listing', [ 'params' => $params ] );
			}
			$this->logger->log( 'Process ' . $listings_type . ' actions scheduled.' );
		}
		// Schedule action to delete removed listings.
		if ( 'pois' === $listings_type ) {
			$this->schedule_action( 'wp_otis_delete_removed_listings', [ 'params' => [ 'modified' => $assoc_args['modified'] ] ] );
			$this->logger->log( 'Scheduling delete removed listings action.' );
		}
	}

	/** Process listings stored in transient */
	private function _process_listings( $assoc_args = [] ) {
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		} 
		// Disable Caching if it's enabled.
		// $this->_toggle_caching( false );

		// Run actions for before processing listings.
		do_action( 'wp_otis_before_process_listings', $assoc_args );
		$assoc_args = apply_filters( 'wp_otis_before_process_listings_args', $assoc_args );

		// Get listings type from args.
		$listings_type = $assoc_args['import_args']['type'] ?? 'pois';
		// Get listings from transient.
		$listings_transient = $this->get_listings_transient( $listings_type );
		// If we don't have any listings, return.
		if ( false === $listings_transient ) {
			$this->logger->log( "No $listings_type listings transient to process" );
			return;
		}

		// Get the relevant listing from the transient by UUID
		$transient_listing = array_filter( $listings_transient, function( $listing ) use ( $assoc_args ) {
			return $listing['uuid'] === $assoc_args['listing_uuid'];
		} );
		$transient_listing = array_shift( $transient_listing );
		// If we don't have a listing, return.
		if ( false === $transient_listing ) {
			$this->logger->log( "No $listings_type listing to process" );
			return;
		}
		// Apply filters to relevant listing.
		$transient_listing = apply_filters( 'wp_otis_listing_to_process', $transient_listing, $listings_type );

		// Process the listing.
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		// Get the existing listing ID from Wordpress if it exists.
		try {
			$found_poi_post_id = wp_otis_get_post_id_for_uuid( $transient_listing['uuid'] );
			$found_poi_post_id = $found_poi_post_id ?: 0;
			$upserted_post_id = $this->_upsert_poi( $found_poi_post_id, $transient_listing );
			if ( $upserted_post_id && ! is_wp_error($upserted_post_id) ) {
				$this->logger->log( 'Successfully processed listing: ' . $transient_listing['name'] . ' (' . $assoc_args['listing_number'] . ' of ' . $assoc_args['listings_total'] . ')' );
			} else {
				$this->logger->log( 'Error processing listing: ' . $transient_listing['name'] );
			}
		} catch (\Throwable $th) {
			$this->logger->log( 'Error processing listing: ' . $transient_listing['name'] );
		}

		// Run actions for after processing listings.
		do_action( 'wp_otis_after_process_listings', $assoc_args );

		return;
	}

	/** Delete listings that have been removed from OTIS */
	private function _delete_removed_listings( $assoc_args = [] ) {
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		// Run actions for before deleting listings.
		do_action( 'wp_otis_before_delete_removed_listings', $assoc_args );
		$assoc_args = apply_filters( 'wp_otis_before_delete_removed_listings_args', $assoc_args );

		// Get before and after dates from args.
		$before = $assoc_args['before'] ?? null;
		$after = $assoc_args['modified'] ?? null;
		// Reformat dates to be in Y-m-d we need.
		$before = $before ? date( 'Y-m-d', strtotime( $before ) ) : null;
		$after = $after ? date( 'Y-m-d', strtotime( $after ) ) : null;

		// Check if there's a page in args and set it to the first one if it's not present.
		$deletes_page = isset( $assoc_args['deletes_page'] ) ? intval( $assoc_args['deletes_page'] ) : 1;
		
		// Construct API params.
		$api_params = [];
		if ( ! is_null( $before ) ) {
			$api_params['before'] = $before;
		}
		if ( ! is_null( $after ) ) {
			$api_params['after'] = $after;
		}
		// Add page to API params if it is greater than 1.
		if ( 1 < $deletes_page ) {
			$api_params['page'] = $deletes_page;
		}
		// Fetch removed listings from OTIS.
		$this->logger->log( 'Fetching removed listings' );
		$removed_listings = $this->otis->call( 'listings/deleted', $api_params, $this->logger );
		$removed_listings_results = $removed_listings['results'] ?: [];
		// If we don't have any removed listings, log that and return.
		if ( ! count( $removed_listings_results ) ) {
			$this->logger->log( 'No removed listings to delete' );
			$this->delete_listings_transient();
			$this->_reset_importer_active_flag();
			return;
		}
		// Loop through removed listings and delete them.
		foreach ( $removed_listings_results as $removed_listing_uuid ) {
			// Get the existing listing ID from Wordpress if it exists.
			$found_poi_post_id = wp_otis_get_post_id_for_uuid( $removed_listing_uuid );
			// If the listing exists, trash it.
			if ( $found_poi_post_id ) {
				$this->logger->log( 'Deleting removed listing ' . $removed_listing_uuid );
				wp_trash_post( $found_poi_post_id );
			}
		}
		// Check if there are more pages.
		$deletes_next = is_null( $removed_listings['next'] ) ? $removed_listings['next'] : trim( $removed_listings['next'] );
		$has_next_page = empty( $deletes_next ) || 'null' === $deletes_next ? false : true;
		// If there are more pages, schedule another action to fetch them.
		if ( $has_next_page ) {
			$assoc_args['deletes_page'] = $deletes_page + 1;
			$assoc_args = array_merge( $assoc_args, $api_params );
			$this->schedule_action( 'wp_otis_delete_removed_listings', $assoc_args );
			$this->logger->log( 'Scheduling fetch of next page of removed listings' );
			return;
		}
		// Run actions for after deleting listings.
		do_action( 'wp_otis_after_delete_removed_listings', $assoc_args );
		// Log that we're done.
		$this->logger->log( 'Finished deleting removed listings. Wrapping up OTIS import.' );
		// Clean up transients.
		$this->delete_listings_transient();
		// Reset the WP_OTIS_IMPORT_ACTIVE option.
		$this->_reset_importer_active_flag();
	}

	/** Sync with all active POIs in OTIS */
	private function _fetch_all_active_listings( $assoc_args = [] ) {
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		// Check if the WP_OTIS_IMPORT_ACTIVE option is set to true and set it if it's not.
		if ( ! get_option( WP_OTIS_IMPORT_ACTIVE, false ) ) {
			update_option( WP_OTIS_IMPORT_ACTIVE, true );
			$this->logger->log( 'Pausing automatic imports.' );
		}
		// Run actions for before syncing all listings.
		do_action( 'wp_otis_before_sync_all_listings', $assoc_args );
		$assoc_args = apply_filters( 'wp_otis_before_sync_all_listings_args', $assoc_args );

		// Check that there's a page in args and set it to the first one if it's not present.
		$sync_page = $assoc_args['sync_page'] ? intval( $assoc_args['sync_page'] ) : 1;

		// Construct API params.
		$api_params = [];
		// Add page to API params if it is greater than 1.
		if ( 1 < $sync_page ) {
			$api_params['page'] = $sync_page;
		}
		// Merge API params with args.
		$api_params = array_merge( $assoc_args, $api_params );

		// Call the API to get all active listing UUIDs.
		$active_listings_results = $this->otis->call( 'listings/activeids', $api_params, $this->logger );

		// Merge retrieved IDs with the activeIds transient.
		$active_listings_transient = $this->get_listings_transient( 'activeIds' ) ? $this->get_listings_transient( 'activeIds' ) : [];
		$active_listing_ids = $active_listings_results['results'] ? $active_listings_results['results'] : [];
		$active_listing_ids = array_merge( $active_listings_transient , $active_listing_ids );

		// Set the activeIds transient.
		$this->set_listings_transient( $active_listing_ids, 'activeIds' );

		// Check if there are more pages.
		$sync_next = is_null( $active_listings_results['next'] ) ? $active_listings_results['next'] : trim( $active_listings_results['next'] );
		$has_next_page = empty( $sync_next ) || 'null' === $sync_next ? false : true;

		// If there are more pages, schedule another action to fetch them.
		if ( $has_next_page ) {
			$assoc_args['sync_page'] = $sync_page + 1;
			$this->schedule_action( 'wp_otis_sync_all_listings_fetch', [ 'params' => $assoc_args ] );
			$this->logger->log( 'Scheduling fetch of next page of active listings' );
			return;
		}

		// Run actions for after syncing all listings.
		do_action( 'wp_otis_after_sync_all_listings', $assoc_args );

		// Schedule the action to get create allPoiPosts transient.
		$this->schedule_action( 'wp_otis_sync_all_listings_posts_transient' );
	}

	/** Get all published POI Posts and set a transient */
	private function _set_all_pois_transient() {
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		// Check if the WP_OTIS_IMPORT_ACTIVE option is set to true and set it if it's not.
		if ( ! get_option( WP_OTIS_IMPORT_ACTIVE, false ) ) {
			update_option( WP_OTIS_IMPORT_ACTIVE, true );
			$this->logger->log( 'Pausing automatic imports.' );
		}
		// Get all published POI Post IDs.
		$active_poi_post_query = new WP_Query(
			[
				'post_type'      => 'poi',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		// Set the transient.
		$this->set_listings_transient( $active_poi_post_query->get_posts(), 'allPoiPosts' );

		// Schedule the action to sync the listings.
		$this->schedule_action( 'wp_otis_sync_all_listings_process', [ 'params' => [ 'process_page' => 1 ] ] );
	}
	

	/** Process activeIds Transient */
	private function _remove_all_inactive_listings( $assoc_args = [] ) {
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		// Check if the WP_OTIS_IMPORT_ACTIVE option is set to true and set it if it's not.
		if ( ! get_option( WP_OTIS_IMPORT_ACTIVE, false ) ) {
			update_option( WP_OTIS_IMPORT_ACTIVE, true );
			$this->logger->log( 'Pausing automatic imports.' );
		}
		// Run actions for before processing all listings.
		do_action( 'wp_otis_before_process_active_listings', $assoc_args );
		// Check if theres a process_page in args and set it to the first one if it's not present.
		$process_page = $assoc_args['process_page'] ? intval( $assoc_args['process_page'] ) : 1;
		// Get the activeIds transient.
		$active_listing_uuids = $this->get_listings_transient( 'activeIds' );
		// Get the allPois transient.
		$active_poi_post_ids = $this->get_listings_transient( 'allPoiPosts' );

		// Split the allPoiPosts transient into chunks.
		$active_poi_post_chunks = array_chunk( $active_poi_post_ids, $this->processing_chunk_size );

		// Loop through the poi chunk based on the process_page.
		foreach ( $active_poi_post_chunks[ $process_page - 1 ] as $active_poi_post_id ) {
			// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
			if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
				$this->cancel_import();
				return;
			}
			$active_poi_post_uuid = get_post_meta( $active_poi_post_id, 'uuid', true );
			if ( ! in_array( $active_poi_post_uuid, $active_listing_uuids, true ) ) {
				$this->logger->log( 'Trashing POI Post with UUID: ' . $active_poi_post_uuid, $active_poi_post_id );
				wp_trash_post( $active_poi_post_id );
			}
		}

		// Check if there are more pages.
		$has_next_page = $process_page < count( $active_poi_post_chunks ) ? true : false;
		// If there are more pages, schedule another action to fetch them.
		if ( $has_next_page ) {
			$next_page = $process_page + 1;
			$total_pages = count( $active_poi_post_chunks );
			$assoc_args['process_page'] = $next_page;
			$this->schedule_action( 'wp_otis_sync_all_listings_process', [ 'params' => $assoc_args ] );
			$this->logger->log( "Scheduling process of page $next_page of $total_pages of remove inactive listings" );
			return;
		}

		// Run actions for after processing all listings.
		do_action( 'wp_otis_after_process_active_listings', $assoc_args );

		// Schedule the action to import missing listings.
		$this->logger->log( 'Scheduling import of missing POIs' );
		$this->schedule_action( 'wp_otis_sync_all_listings_import', [ 'params' => [ 'import_page' => 1 ] ] );
	}

	/** Import missing listings */
	private function _import_all_active_listings( $assoc_args = [] ) {
		// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
		if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
			$this->cancel_import();
			return;
		}
		// Check if the WP_OTIS_IMPORT_ACTIVE option is set to true and set it if it's not.
		if ( ! get_option( WP_OTIS_IMPORT_ACTIVE, false ) ) {
			update_option( WP_OTIS_IMPORT_ACTIVE, true );
			$this->logger->log( 'Pausing automatic imports.' );
		}
		// Disable Caching if it's enabled.
		// $this->_toggle_caching( false );

		// Run actions for before importing all listings.
		do_action( 'wp_otis_before_import_active_listings', $assoc_args );

		// Check if theres a import_page in args and set it to the first one if it's not present.
		$import_page = $assoc_args['import_page'] ? intval( $assoc_args['import_page'] ) : 1;

		// Get the activeIds transient.
		$active_listing_uuids = $this->get_listings_transient( 'activeIds' );
		// Split the activeIds transient into chunks.
		$active_listing_uuid_chunks = array_chunk( $active_listing_uuids, $this->processing_chunk_size );

		// Loop through the uuid chunks.
		foreach ( $active_listing_uuid_chunks[ $import_page - 1 ] as $listing_uuid ) {
			// Check if the WP_OTIS_CANCEL_IMPORT option is set to true and if so, cancel the import.
			if ( get_option( WP_OTIS_CANCEL_IMPORT, false ) ) {
				$this->cancel_import();
				return;
			}
			$existing_post_id = wp_otis_get_post_id_for_uuid( $listing_uuid );
			if ( $existing_post_id ) {
				continue;
			}
			$this->import( 'poi', [ 'uuid' => $listing_uuid ] );
		}

		// Check if there are more pages.
		$has_next_page = $import_page < count( $active_listing_uuid_chunks ) ? true : false;
		// If there are more pages, schedule another action to fetch them.
		if ( $has_next_page ) {
			$next_page = $import_page + 1;
			$total_pages = count( $active_listing_uuid_chunks );
			$assoc_args['import_page'] = $next_page;
			$this->schedule_action( 'wp_otis_sync_all_listings_import', [ 'params' => $assoc_args ] );
			$this->logger->log( "Scheduling import page $next_page of $total_pages of active listings import" );
			return;
		}

		// Run actions for after importing all listings.
		do_action( 'wp_otis_after_import_active_listings', $assoc_args );

		// Enable Caching if it's disabled.
		// $this->_toggle_caching( true );

		// Update the WP_OTIS_IMPORT_ACTIVE option to false.
		$this->_reset_importer_active_flag();

		// Clean Up Transients.
		$this->delete_listings_transient( 'activeIds' );
		$this->delete_listings_transient( 'allPoiPosts' );

		// Log that the import is finished.
		$this->logger->log( 'Finished importing syncing active listings.' );
	}

	/** Sync Listing Type Libraries */
	private function _sync_listing_type_libraries() {
		// Get all listing type terms.
		$listing_type_terms = get_terms( [
			'taxonomy'   => 'type',
			'hide_empty' => false,
		] );

		// Loop through the listing type terms and pass the term to the _add_listing_type_library_meta function.
		foreach ( $listing_type_terms as $listing_type_term ) {
			$this->_add_listing_type_library_meta( $listing_type_term );
		}
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
				'post_type'     => 'poi',
				'post_status'   => $post_status,
				'ID'            => $post_id,
				'post_title'    => $post_title,
				'post_name'     => '',              // Empty = auto-generate.
				'post_content'  => $post_content,
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
	 * Calculate Listing Type Library meta value for Types taxonomy terms.
	 */
	private function _add_listing_type_library_meta( $type_term ) {
		// Remove existing meta.
		delete_term_meta( $type_term->term_id, 'otis_listing_type_library' );

		// Get the OTIS Path meta and parse it.
		$otis_path_meta = get_term_meta( $type_term->term_id, 'otis_path' );
		$otis_listing_type_libraries = array_map( function ( $path ) {
			$library = explode( '/', $path )[1];
			$library = str_replace( 'listings-', '', $library );
			return $library;
		}, $otis_path_meta );

		$otis_listing_type_libraries = array_unique( $otis_listing_type_libraries );

		// Loop through libraries and add the listing_type_library meta.
		foreach ( $otis_listing_type_libraries as $library ) {
			$libraries_term_meta = add_term_meta( $type_term->term_id, 'otis_listing_type_library', $library );
			if ( is_wp_error( $libraries_term_meta ) ) {
				throw new Otis_Exception( 'Error: taxonomy type, term id: ' . $type_term->term_id . ', ' . $libraries_term_meta->get_error_message() );
			}
		}
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
