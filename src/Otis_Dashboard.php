<?php

require_once 'Otis_Importer.php';
class Otis_Dashboard 
{
  /**
	 * @var Otis_Importer
	 */
	private $importer;

  public function otis_dashboard_scripts() {
    wp_enqueue_media();
    wp_register_script( 'otis-js', plugins_url( '../dist/otis.js', __FILE__ ), [], '2.0', true );
    wp_localize_script( 'otis-js', 'otisDash', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'admin_url' => admin_url() ) );

    wp_enqueue_script( 'otis-js' );
    wp_enqueue_style( 'otis-dashboard-styles', plugins_url( '../css/dashboard.css', __FILE__ ), [], '1.0' );
  }

  public function otis_dashboard_page() {
    add_management_page( 'OTIS Dashboard', 'OTIS Dashboard', 'manage_options', 'otis-dashboard', [ $this, 'otis_dashboard_setup' ] );
  }

  public function otis_dashboard_ui() {
    ?>
    <div id="otis-dashboard-mount"></div>
    <?php
  }

  public function otis_dashboard_setup() {
    $this->otis_dashboard_ui();
  }

  public function otis_start_import($args, $assoc_args) {
    $this->importer->import( $args, $assoc_args );
  }

  public function otis_bulk_delete_pois() {
    $this->importer->import('deleted-pois', [ 'deletes_page' => 1 ]);
  }

  // calculate page_size based on import date
  public function otis_calculate_page_size($modified_start_date, $modified_end_date) {
    $page_size = 25;
    if ( ! $modified_start_date ) {
      return $page_size;
    }
    $start_date = new DateTime( $modified_start_date );
    $end_date = new DateTime( $modified_end_date );
    $diff = $start_date->diff( $end_date );
    if ( $diff->days > 30 ) {
      $page_size = 10;
    }
    return $page_size;
  }

  public function otis_init_import() {
    $modified_start = isset($_POST['from_date']) ? _sanitize_text_fields($_POST['from_date']) : false;
    $initial = isset($_POST['initial_import']);
    $assoc_args = array();
    if ($initial) {
      $assoc_args['type'] = 'pois';
    } else {
      $assoc_args['type'] = 'pois-only';
    }
    if ($modified_start) {
      $assoc_args['modified'] = $modified_start;
    }
    try {
      as_enqueue_async_action( 'wp_otis_fetch_listings', ['params' => $assoc_args] );
      echo json_encode('scheduling import');
    } catch ( Exception $e ) {
      echo json_encode($e->getMessage());
    } finally {
      wp_die();
    }
  }

  public function otis_stop_bulk_importer() {
    try {
      $log = $this->importer->nobulk();
      as_unschedule_all_actions( 'wp_otis_dashboard_start_async_import' );
      as_unschedule_all_actions( 'wp_otis_async_bulk_import' );
      echo json_encode($log);
    } catch ( Exception $e ) {
      echo json_encode($e->getMessage());
    } finally {
      wp_die();
    }
  }

  public function otis_log_preview() {
    $args = [
			'numberposts' => 15,
			'post_type'      => 'wp_log',
		];
    $log = get_posts($args);
    echo json_encode( $log );
    wp_die();
  }

  public function otis_poi_counts() {
    return wp_count_posts('poi');
  }

  public function otis_status() {
    echo json_encode([
      'bulkImportScheduled' => 
        as_next_scheduled_action( 'wp_otis_dashboard_start_import' ) ||
        as_next_scheduled_action('wp_otis_dashboard_start_async_import') ||
        as_next_scheduled_action( 'wp_otis_dashboard_start_async_delete_pois' ),
      'bulkImportActive' => get_option( WP_OTIS_BULK_IMPORT_ACTIVE, false ),
      'lastImportDate' => get_option( WP_OTIS_LAST_IMPORT_DATE ),
      'poiCount' => $this->otis_poi_counts(),
    ]);
    wp_die();
  }


  public function otis_init_deleted_pois_sync() {
    as_enqueue_async_action( 'wp_otis_dashboard_start_async_delete_pois' );
  }
  
  function __construct( $importer ) {
    add_action( 'admin_menu', [ $this, 'otis_dashboard_page' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'otis_dashboard_scripts' ] );

    // Ajax Handlers
    add_action( 'wp_ajax_otis_status', [ $this, 'otis_status' ] );
    add_action( 'wp_ajax_otis_import', [ $this, 'otis_init_import' ] );
    add_action( 'wp_ajax_otis_preview_log', [ $this, 'otis_log_preview' ] );
    add_action( 'wp_ajax_otis_stop_bulk', [ $this, 'otis_stop_bulk_importer' ] );
    add_action( 'wp_ajax_otis_sync_deleted_pois', [ $this, 'otis_init_deleted_pois_sync' ] );

    add_action( 'wp_otis_dashboard_start_async_import', [ $this, 'otis_start_import' ], 10, 2 );

    add_action( 'wp_otis_dashboard_start_async_delete_pois', [ $this, 'otis_bulk_delete_pois' ] );

    $this->importer = $importer;
  }
}

?>