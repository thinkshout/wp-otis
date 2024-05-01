<?php

require_once 'Otis_Importer.php';
class Otis_Dashboard 
{
  /**
	 * @var Otis_Importer
	 */
	protected $importer;

  public function otis_dashboard_scripts() {
    wp_enqueue_media();
    wp_register_script( 'otis-js', plugins_url( '../dist/otis.js', __FILE__ ), [], '2.1', true );
    wp_localize_script( 'otis-js', 'otisDash', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'admin_url' => admin_url() ) );

    wp_enqueue_script( 'otis-js' );
    wp_enqueue_style( 'otis-styles', plugins_url( '../dist/otis.css', __FILE__ ), [], '2.1' );
  }

  public function otis_dashboard_page() {
    $dashboard_menu_page = add_menu_page( 'OTIS Dashboard', 'OTIS Dashboard', 'manage_options', 'otis-dashboard', [ $this, 'otis_dashboard_setup' ], 'dashicons-oregon', 66 );
    add_action( 'admin_print_styles-' . $dashboard_menu_page, 'wp-otis-icon-css' );
  }

  public function otis_oregon_dashicon_css() {
    ?>
    <style>
      .dashicons-oregon {
        background: url('<?php echo plugins_url( '../assets/icons/oregon.svg', __FILE__ ); ?>') no-repeat;
        background-size: contain;
        background-repeat: no-repeat;
      }
    </style>
    <?php
  }

  public function otis_dashboard_ui() {
    ?>
    <div id="otis-dashboard-mount"></div>
    <?php
  }

  public function otis_dashboard_setup() {
    $this->otis_dashboard_ui();
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
    $assoc_args = apply_filters( 'wp_otis_listings', [] );
    if ($modified_start) {
      $assoc_args['modified'] = $modified_start;
    }
    // Check if the type filter is set and if it isn't, set it to 'pois'
    if ( ! isset( $assoc_args['type'] ) ) {
      $assoc_args['type'] = 'pois';
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

  public function otis_cancel_import() {
    try {
      update_option( WP_OTIS_CANCEL_IMPORT, true );
    } catch ( Exception $e ) {
      echo json_encode($e->getMessage());
    } finally {
      wp_die();
    }
  }

  public function otis_stop_all() {
    $this->importer->cancel_import( 'Resetting importer...', 'Importer reset.' );
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
    $otis_schedule = $this->otis_schedule();
    echo json_encode( $otis_schedule );
    wp_die();
  }


  public function otis_init_pois_sync() {
    // Set up the sync params
    $sync_params = [
      'bulk' => true,
    ];
    // Schedule the sync
    as_enqueue_async_action( 'wp_otis_sync_all_listings_fetch', [ 'params' => $sync_params ] );
  }

  protected function otis_schedule() {
    return [
      'importSchedule' => [
        'fetchListings'        => as_next_scheduled_action( 'wp_otis_fetch_listings' ),
        'processListings'      => as_next_scheduled_action( 'wp_otis_process_single_listing' ),
        'deleteListings'       => as_next_scheduled_action( 'wp_otis_delete_removed_listings' ),
        'syncAllPoisFetch'     => as_next_scheduled_action( 'wp_otis_sync_all_listings_fetch' ),
        'syncAllPoisProcess'   => as_next_scheduled_action( 'wp_otis_sync_all_listings_process' ),
        'syncAllPoisImport'    => as_next_scheduled_action( 'wp_otis_sync_all_listings_import' ),
        'syncAllPoisTransient' => as_next_scheduled_action( 'wp_otis_sync_all_listings_posts_transient' ),
        'cancelling'           => get_option( WP_OTIS_CANCEL_IMPORT, false ),
        'nextScheduledImport'  => wp_next_scheduled( 'wp_otis_cron' ),
      ],
      'importerActive' => get_option( WP_OTIS_IMPORT_ACTIVE, false ),
      'lastImportDate' => get_option( WP_OTIS_LAST_IMPORT_DATE ),
      'poiCount' => $this->otis_poi_counts(),
      'activeFilters' => apply_filters( 'wp_otis_listings', [] ),
    ];
  }
  
  function __construct( $importer ) {
    add_action( 'admin_menu', [ $this, 'otis_dashboard_page' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'otis_dashboard_scripts' ] );
    add_action('admin_head', [ $this, 'otis_oregon_dashicon_css' ]);

    // Ajax Handlers
    add_action( 'wp_ajax_otis_status', [ $this, 'otis_status' ] );
    add_action( 'wp_ajax_otis_import', [ $this, 'otis_init_import' ] );
    add_action( 'wp_ajax_otis_preview_log', [ $this, 'otis_log_preview' ] );
    add_action( 'wp_ajax_otis_cancel_importer', [ $this, 'otis_cancel_import' ] );
    add_action( 'wp_ajax_otis_sync_all_pois', [ $this, 'otis_init_pois_sync' ] );
    add_action( 'wp_ajax_otis_stop_all', [ $this, 'otis_stop_all' ] );

    $this->importer = $importer;
  }
}

?>