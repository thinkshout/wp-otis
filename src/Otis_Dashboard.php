<?php
class Otis_Dashboard 
{
  public function otis_dashboard_scripts() {
    wp_enqueue_media();
    wp_register_script( 'axios', 'https://cdnjs.cloudflare.com/ajax/libs/axios/0.21.1/axios.min.js', [], '0.21.1' );
    wp_register_script( 'vue', 'https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js', [], '2.6.14' );
    wp_enqueue_script( 'axios' );
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

  public function otis_start_import() {

  }

  public function otis_stop_bulk_importer() {

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

  public function otis_status() {
    $poi_count = wp_count_posts('poi');
    echo json_encode([
      'bulkImportActive' => get_option( WP_OTIS_BULK_IMPORT_ACTIVE ),
      'bulkHistoryImportActive' => get_option( WP_OTIS_BULK_HISTORY_ACTIVE ),
      'lastImportDate' => get_option( WP_OTIS_LAST_IMPORT_DATE ),
      'poiCount' => $poi_count,
    ]);
    wp_die();
  }
  
  function __construct() {
    add_action( 'admin_menu', [ $this, 'otis_dashboard_page' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'otis_dashboard_scripts' ] );

    // Ajax Handlers
    add_action( 'wp_ajax_otis_status', [ $this, 'otis_status' ] );
    add_action( 'wp_ajax_otis_import', [ $this, 'otis_start_import' ] );
    add_action( 'wp_ajax_otis_preview_log', [ $this, 'otis_log_preview' ] );
  }
}

?>