<?php
class Otis_Dashboard 
{
  public function otis_dashboard_scripts() {
    wp_enqueue_media();
    wp_register_script( 'axios', 'https://cdnjs.cloudflare.com/ajax/libs/axios/0.21.1/axios.min.js', [], '0.21.1' );
    wp_register_script( 'vue', 'https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js', [], '2.6.14' );
    wp_enqueue_script( 'axios' );
    wp_enqueue_script( 'vue' );

    wp_enqueue_style( 'otis-dashboard-styles', plugins_url( '../css/dashboard.css', __FILE__ ), [], '1.0' );
    wp_enqueue_script( 'otis-dashboard', plugins_url( '../js/dashboard.js', __FILE__ ), [], '1.0', true );
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
  
  function __construct() {
    add_action( 'admin_menu', [ $this, 'otis_dashboard_page' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'otis_dashboard_scripts' ] );
  }
}

?>