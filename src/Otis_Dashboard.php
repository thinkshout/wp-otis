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
    wp_register_script( 'axios', 'https://cdnjs.cloudflare.com/ajax/libs/axios/0.21.1/axios.min.js', [], '0.21.1' );
    wp_register_script( 'vue-date-range', 'https://cdn.jsdelivr.net/npm/vue2-daterange-picker@0.6.7/dist/vue2-daterange-picker.umd.min.js', [], '0.6.7' );
    wp_register_script( 'vue', 'https://cdn.jsdelivr.net/npm/vue@2.6.14', [], '2.6.14' );
    wp_enqueue_script( 'axios' );
    wp_enqueue_script( 'vue-date-range' );
    wp_enqueue_script( 'vue' );
    wp_register_script( 'otis-dashboard', plugins_url( '../js/dashboard.js', __FILE__ ), [], '1.0', true );
    wp_localize_script( 'otis-dashboard', 'otisDash', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'admin_url' => admin_url() ) );

    wp_enqueue_script( 'otis-dashboard' );
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

  // calculate page_size based on import date
  public function otis_calculate_page_size($modified_date) {
    $page_size = 25;
    if ( ! $modified_date ) {
      return $page_size;
    }
    $date = new DateTime( $modified_date );
    $today = new DateTime( 'now' );
    $diff = $date->diff( $today );
    if ( $diff->days > 30 ) {
      $page_size = 10;
    }
    return $page_size;
  }

  public function otis_init_import() {
    $modified = isset($_POST['modified_date']) ? _sanitize_text_fields($_POST['modified_date']) : false;
    $initial = isset($_POST['initial_import']);
    $args = array();
    $assoc_args = array(
      'page_size' => $this->otis_calculate_page_size($modified),
    );
    if ($initial) {
      $args[0] = 'pois';
    } else {
      $args[0] = 'pois-only';
    }
    if ($modified) {
      $assoc_args['modified'] = $modified;
    }
    try {
      as_enqueue_async_action( 'wp_otis_dashboard_start_async_import', ['args' => $args, 'assoc_args' => $assoc_args] );
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

  public function otis_stop_bulk_history_importer() {
    try {
      $log = $this->importer->nohistory();
      as_unschedule_all_actions('wp_otis_async_bulk_history_import');
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
      'bulkImportScheduled' => as_next_scheduled_action( 'wp_otis_dashboard_start_import' ) || as_next_scheduled_action('wp_otis_dashboard_start_async_import'),
      'bulkHistoryImportScheduled' => as_next_scheduled_action('wp_otis_async_bulk_history_import'),
      'bulkImportActive' => get_option( WP_OTIS_BULK_IMPORT_ACTIVE ),
      'bulkHistoryImportActive' => get_option( WP_OTIS_BULK_HISTORY_ACTIVE ),
      'lastImportDate' => get_option( WP_OTIS_LAST_IMPORT_DATE ),
      'poiCount' => $this->otis_poi_counts(),
    ]);
    wp_die();
  }
  
  function __construct( $importer ) {
    add_action( 'admin_menu', [ $this, 'otis_dashboard_page' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'otis_dashboard_scripts' ] );

    // Ajax Handlers
    add_action( 'wp_ajax_otis_status', [ $this, 'otis_status' ] );
    add_action( 'wp_ajax_otis_import', [ $this, 'otis_init_import' ] );
    add_action( 'wp_ajax_otis_preview_log', [ $this, 'otis_log_preview' ] );
    add_action( 'wp_ajax_otis_stop_bulk', [ $this, 'otis_stop_bulk_importer' ] );
    add_action( 'wp_ajax_otis_stop_bulk_history', [ $this, 'otis_stop_bulk_history_importer' ] );

    add_action( 'wp_otis_dashboard_start_async_import', [ $this, 'otis_start_import' ], 10, 2 );

    $this->importer = $importer;
  }
}

?>