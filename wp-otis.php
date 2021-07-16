<?php
/**
 * Plugin Name:     WP OTIS
 * Plugin URI:      traveloregon.com
 * Description:     OTIS importer
 * Author:          ThinkShout
 * Author URI:      thinkshout.com
 * Text Domain:     wp-otis
 * Domain Path:     /languages
 * Version:         1.0.5
 *
 * @package         Otis
 */

define( 'WP_OTIS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_OTIS_FIELDS_PATH', plugin_dir_path( __FILE__ ) . 'acf-json/group_58250328ca2ce.json' );

define( 'WP_OTIS_TOKEN', 'wp_otis_token' );
define( 'WP_OTIS_LAST_IMPORT_DATE', 'wp_otis_last_import_date' );
define( 'WP_OTIS_BULK_IMPORT_ACTIVE', 'wp_otis_bulk_import_active' );
define( 'WP_OTIS_BULK_HISTORY_ACTIVE', 'wp_otis_bulk_history_active' );
define( 'WP_OTIS_BULK_DISABLE_CACHE', 0 );

require_once 'wp-otis-poi.php';
require_once 'src/Otis_Importer.php';
require_once 'src/Otis_Logger_Simple.php';
require_once 'src/Otis_Command.php';
require_once 'src/Otis_Dashboard.php';
require_once 'wp-logging/WP_Logging.php';
// require_once 'wp-otis-debug.php';

/**
 * Look up the wordpress post id for a given OTIS UUID value.
 *
 * @param string $uuid
 *
 * @return false|int
 */
function wp_otis_get_post_id_for_uuid( $uuid ) {
	$the_query = new WP_Query( [
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'posts_per_page'         => 1,
		'post_status'            => 'any',
		'post_type'              => 'poi',
		'meta_key'               => 'uuid',
		'meta_value'             => $uuid,
	] );

	$post_id = null;

	while ( $the_query->have_posts() ) {
		$the_query->the_post();

		$post_id = get_the_ID();
	}

	wp_reset_postdata();

	return $post_id;
}

/**
 * Add the acf-json path.
 *
 * @param $paths
 *
 * @return array
 */
function wp_otis_acf_json_load_point( $paths ) {
	$paths[] = WP_OTIS_PLUGIN_PATH . 'acf-json';

	return $paths;
}

add_filter( 'acf/settings/load_json', 'wp_otis_acf_json_load_point' );

// On initial plugin installation or restart after a bulk import, begin hourly update schedule one minute later
if ( ! wp_next_scheduled( 'wp_otis_cron' ) ) {
    $bulk = get_option( WP_OTIS_BULK_IMPORT_ACTIVE, '' );
		$bulk_history = get_option( WP_OTIS_BULK_HISTORY_ACTIVE, '' );
    if ( ! wp_next_scheduled( 'wp_otis_bulk_importer' ) && !($bulk) & ! wp_next_scheduled( 'wp_otis_bulk_history_importer' ) && !($bulk_history))  {
        wp_schedule_event(time() + 60 * 1, 'hourly', 'wp_otis_cron');
    }
}

add_action( 'wp_otis_cron', function () {

	if ( WP_OTIS_BULK_DISABLE_CACHE ) {
		wp_cache_add_non_persistent_groups( ['acf'] );
	}

    $bulk = get_option( WP_OTIS_BULK_IMPORT_ACTIVE, false );

    if ($bulk == false) {
        $current_date     = date( 'c' );
        $last_import_date = get_option( WP_OTIS_LAST_IMPORT_DATE, '' );

        if ( ! $last_import_date ) {
            // Start pulling in updates from the point after the plugin was installed.
            update_option( WP_OTIS_LAST_IMPORT_DATE, $current_date );
            return;
        }

        $otis     = new Otis();
        $logger   = new Otis_Logger_Simple();
        $importer = new Otis_Importer( $otis, $logger );

        try {
            $importer->import( 'pois', [
                'modified' => $last_import_date,
            ] );
            update_option( WP_OTIS_LAST_IMPORT_DATE, $current_date );

        } catch ( Exception $e ) {
            $logger->log( $e->getMessage(), 0, 'error' );
        }
    }

} );

add_action( 'wp_otis_async_bulk_import', function( $args, $assoc_args ) {
	if ( WP_OTIS_BULK_DISABLE_CACHE ) {
		wp_cache_add_non_persistent_groups( ['acf'] );
	}

	$modified = $assoc_args['modified'];
	$all = $assoc_args['all'];
	$page = $assoc_args['page'];
	$page_size = $args['page_size'];
	$related_only = isset($assoc_args['related_only']);
	$otis     = new Otis();
	$logger   = new Otis_Logger_Simple();
	$importer = new Otis_Importer( $otis, $logger );
	$logger->log( "Bulk OTIS import continuing on page ".$page.". (".$modified.")");

	try {
		$importer->import( 'pois-only', [
			'modified' => $modified,
			'page' => $page,
			'page_size' => $page_size,
			'related_only' => $related_only,
			'all' => $all
		] );
	} catch ( Exception $e ) {
		$logger->log( $e->getMessage(), 0, 'error' );
	}
}, 10, 2 );

add_action( 'wp_otis_bulk_importer', function($modified, $all, $page, $page_size = 50, $related_only = false) {

	if ( WP_OTIS_BULK_DISABLE_CACHE ) {
		wp_cache_add_non_persistent_groups( ['acf'] );
	}

    $otis     = new Otis();
    $logger   = new Otis_Logger_Simple();
    $importer = new Otis_Importer( $otis, $logger );
    $logger->log( "Bulk OTIS import continuing on page ".$page.". (".$modified.")");

    try {
        $importer->import( 'pois-only', [
            'modified' => $modified,
            'page' => $page,
						'page_size' => $page_size,
            'related_only' => $related_only,
            'all' => $all
        ] );
    } catch ( Exception $e ) {
        $logger->log( $e->getMessage(), 0, 'error' );
    }

}, 10, 3 );

add_action( 'wp_otis_bulk_history_importer', function($modified, $all, $page, $related_only = false) {

	if ( WP_OTIS_BULK_DISABLE_CACHE ) {
		wp_cache_add_non_persistent_groups( ['acf'] );
	}

	$otis     = new Otis();
	$logger   = new Otis_Logger_Simple();
	$importer = new Otis_Importer( $otis, $logger );
	$logger->log( "Bulk OTIS history import continuing on page ".$page.". (".$modified.")");

	try {
		$importer->import( 'history-only', [
			'modified' => $modified,
			'bulk-history-page' => $page,
			'related_only' => $related_only,
			'all' => $all
		] );
	} catch ( Exception $e ) {
		$logger->log( $e->getMessage(), 0, 'error' );
	}

}, 10, 3 );

if ( ! wp_next_scheduled( 'wp_otis_expire_events' ) ) {
  wp_schedule_event( time(), 'daily', 'wp_otis_expire_events' );
}

add_action( 'wp_otis_expire_events', function () {

  if ( WP_OTIS_BULK_DISABLE_CACHE ) {
  	wp_cache_add_non_persistent_groups( ['acf'] );
  }

  $logger = new Otis_Logger_Simple();

  $logger->log( 'Checking for expired posts -----------------------');

  $query = new WP_Query( array(
    'posts_per_page' => -1,
    'post_type'      => 'poi',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'post_status'    => 'publish',
    'meta_query'     => array(
      array(
        'key'     => 'end_date',
        'type'    => 'DATE',
        'value'   => date( 'Y-m-d' ),
        'compare' => '<',
      ),
    ),
    'tax_query'      => array(
      'relation' => 'OR',
      array(
        'taxonomy' => 'type',
        'field'    => 'slug',
        'terms'    => 'events',
      ),
      array(
        'taxonomy' => 'type',
        'field'    => 'slug',
        'terms'    => 'deals',
      ),
    ),
  ) );

  while ( $query->have_posts() ) {
    $query->the_post();

    $this_post_type = 'unknown';
    if (has_term('deals','type')) { $this_post_type = 'deal'; }
    if (has_term('events','type')) { $this_post_type = 'event'; }

    wp_update_post( [
      'ID'          => get_the_ID(),
      'post_status' => 'draft',
    ] );

    $logger->log( 'Updated expired '.$this_post_type.' to draft | UUID: ' . get_field( 'uuid' ), get_the_ID() );
  }

  $logger->log( 'Expired post cleanup complete -----------------------');

  wp_reset_postdata();
} );

add_filter( 'manage_edit-type_columns', function ( $columns ) {
	$columns['otis_path'] = 'OTIS Path';

	return $columns;
} );

add_action( 'manage_type_custom_column', function ( $value, $column_name, $tax_id ) {
	if ( 'otis_path' === $column_name ) {
		$paths = get_term_meta( $tax_id, 'otis_path' );

		return implode( '<br>', array_map( function ( $path ) {
			return '<a href="' . Otis::API_ROOT . $path . '" target="_blank">' . $path . '</a>';
		}, $paths ) );
	}

	return '';
}, 10, 3 );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'wp-otis-meta-box', 'OTIS', 'wp_otis_meta_box_markup', 'poi', 'side', 'high' );
} );

/**
 * OTIS metabox markup.
 */
function wp_otis_meta_box_markup( $post, $box ) {
	$uuids = get_post_meta( $post->ID, 'uuid' );
	$uuid  = $uuids ? $uuids[0] : null;
	if ( $uuid ) :
		$view_url = 'https://otis.traveloregon.com/listing-view/' . $uuid . '/'; ?>
		<a class="button" href="<?php echo esc_url( $view_url ) ?>" target="_blank">View OTIS</a>

		<?php $import_url = add_query_arg( [
			'post'   => $post->ID,
			'action' => 'otis_import',
		], admin_url( 'post.php' ) ); ?>
		<p>
			<a class="button button-primary button-large" href="<?php echo esc_url( $import_url ) ?>">Reimport Fields</a>
		</p>
	<?php endif;
}

add_action( 'post_action_otis_import', function ( $post_id ) {
	$message = null;
	$uuids   = get_post_meta( $post_id, 'uuid' );

	if ( $uuids ) {
		$uuid     = $uuids[0];
		$otis     = new Otis();
		$logger   = new Otis_Logger_Simple();
		$importer = new Otis_Importer( $otis, $logger );

		try {
			$importer->import( [ 'poi', $uuid ], [] );

			//TODO: How to display import result?
			$message = 4;
		} catch ( Exception $e ) {
			$logger->log( $e->getMessage(), $post_id, 'error' );

			//TODO: How to show an error message?
			$message = 0;
		}
	}

	$url = add_query_arg( [
		'post'    => $post_id,
		'action'  => 'edit',
		'message' => $message,
	], admin_url( 'post.php' ) );

	wp_redirect( $url );
	exit();
} );

/**
 * Load OTIS fields.
 *
 * @return array
 */
function wp_otis_fields_load() {
	static $field_group = null;

	if ( ! $field_group ) {
		$field_group = json_decode( file_get_contents( WP_OTIS_FIELDS_PATH ), true );
	}

	return $field_group;
}

/**
 * Save OTIS fields.
 *
 * @param array $field_group
 */
function wp_otis_fields_save( $field_group ) {
	file_put_contents( WP_OTIS_FIELDS_PATH, wp_json_encode( $field_group, JSON_PRETTY_PRINT ) );
}

/**
 * Laravel helper
 */
if ( ! function_exists('array_pluck'))
{
	/**
	 * Pluck an array of values from an array.
	 *
	 * @param  array   $array
	 * @param  string  $value
	 * @param  string  $key
	 * @return array
	 */
	function array_pluck($array, $value, $key = null)
	{
		$results = array();

		foreach ($array as $item)
		{
			$itemValue = data_get($item, $value);

			// If the key is "null", we will just append the value to the array and keep
			// looping. Otherwise we will key the array using the value of the key we
			// received from the developer. Then we'll return the final array form.
			if (is_null($key))
			{
				$results[] = $itemValue;
			}
			else
			{
				$itemKey = data_get($item, $key);

				$results[$itemKey] = $itemValue;
			}
		}

		return $results;
	}
}

/**
 * Laravel helper
 */
if ( ! function_exists('array_flatten'))
{
	/**
	 * Flatten a multi-dimensional array into a single level.
	 *
	 * @param  array  $array
	 * @return array
	 */
	function array_flatten($array)
	{
		$return = array();

		array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });

		return $return;
	}
}

/**
 * Laravel helper
 */
if (!function_exists('data_get')) {
	/**
	 * Get an item from an array or object using "dot" notation.
	 *
	 * @param  mixed $target
	 * @param  string $key
	 * @param  mixed $default
	 * @return mixed
	 */
	function data_get($target, $key, $default = null)
	{
		if (is_null($key)) {
			return $target;
		}

		foreach (explode('.', $key) as $segment) {
			if (is_array($target)) {
				if (!array_key_exists($segment, $target)) {
					return value($default);
				}

				$target = $target[$segment];
			} elseif ($target instanceof ArrayAccess) {
				if (!isset($target[$segment])) {
					return value($default);
				}

				$target = $target[$segment];
			} elseif (is_object($target)) {
				if (!isset($target->{$segment})) {
					return value($default);
				}

				$target = $target->{$segment};
			} else {
				return value($default);
			}
		}

		return $target;
	}
}


/**
 * Log OTIS results.
 */
add_action( 'wp_otis_log', function ( $message, $parent, $type ) {
	WP_Logging::add( '', $message, $parent, $type );
}, 10, 3 );

/**
 * TROR POI Menu items.
 */
add_action( 'admin_menu', function ( $context ) {
	add_submenu_page( 'edit.php?post_type=poi', 'Import Log', 'Import Log', 'edit_posts', 'tror_poi_otis_log', 'tror_poi_otis_log' );
} );

add_action( 'do_meta_boxes', function ( $post_type, $priority, $post ) {
	global $wp_meta_boxes;

	$wp_meta_boxes['poi']['side']['high']['wp-otis-meta-box']['callback'] = 'tror_poi_otis_meta_box_markup';

	return $wp_meta_boxes;
}, 10, 3 );

/**
 * Customize OTIS meta box
 */
function tror_poi_otis_meta_box_markup( $post, $box ) {
	$uuids = get_post_meta( $post->ID, 'uuid' );
	$uuid  = $uuids ? $uuids[0] : null;
	if ( $uuid ) :
		$log_url = add_query_arg( [
			'post_type'   => 'poi',
			'page'        => 'tror_poi_otis_log',
			'post_parent' => $post->ID,
		], admin_url( 'edit.php' ) ); ?>
		<a class="button" href="<?php echo esc_url( $log_url ) ?>" target="_blank">Import
			Log</a>
	<?php endif;

	wp_otis_meta_box_markup( $post, $box );
}

/**
 * Page callback for OTIS Log.
 */
function tror_poi_otis_log() {
	$last_import_date = tror_poi_get_otis_date( get_option( WP_OTIS_LAST_IMPORT_DATE ) );
	$next_import_date = tror_poi_get_otis_date( date( 'c', wp_next_scheduled( 'wp_otis_cron' ) ) );
	$next_expire_date = tror_poi_get_otis_date( date( 'c', wp_next_scheduled( 'wp_otis_expire_events' ) ) );
	$bulk_import_running = get_option( WP_OTIS_BULK_IMPORT_ACTIVE, false );
	$bulk_history_running = get_option( WP_OTIS_BULK_HISTORY_ACTIVE, false );

	$list_table = new Otis_Log_List_Table();
	$list_table->prepare_items();

	?>
	<div class="wrap">
		<h1>OTIS Import Log (past 30 days)</h1>
		<table style="width: 100%;">
			<tr style="width: 50%;">
				<td width="200"><strong>Last successful import:</strong></td>
				<td><strong><?php echo esc_html( $last_import_date ) ?></strong></td>
				<td width="200">Next scheduled import:</td>
				<td><?php echo esc_html( $next_import_date ) ?></td>
			</tr>
			<tr>
				<td>Next expired event update:</td>
				<td><?php echo esc_html( $next_expire_date ) ?></td>
				<td>Bulk importer active:</td>
				<td><?php echo esc_html( $bulk_import_running ) ? '<strong>Yes</strong>' : 'No'; ?></td>
			</tr>
			<tr>
				<td></td>
				<td></td>
				<td>Bulk history importer active:</td>
				<td><?php echo esc_html( $bulk_history_running ) ? '<strong>Yes</strong>' : 'No'; ?></td>
			</tr>
		</table>
		<?php $list_table->display(); ?>
	</div>
	<?php
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Otis_Log_List_Table extends WP_List_Table {

	public function prepare_items() {
		global $post;

		$posts_per_page = 100;

		$args = [
			'posts_per_page' => $posts_per_page,
			'paged'          => $this->get_pagenum(),
			'post_type'      => 'wp_log',
		];

		if ( ! empty( $_REQUEST['post_parent'] ) ) {
			$args['post_parent'] = $_REQUEST['post_parent'];
		}

		$the_query = new WP_Query( $args );

		$this->set_pagination_args( [
			'total_items' => $the_query->found_posts,
			'per_page'    => $posts_per_page,
		] );

		$columns = $this->get_columns();
		$data    = [];

		while ( $the_query->have_posts() ) {
			$the_query->the_post();

			$post_date = tror_poi_get_otis_date( $post->post_date_gmt );
			$log_type  = has_term( 'error', 'wp_log_type' ) ? 'Error' : '';
			$post_link = '';
			if ( $post->post_parent ) {
				$post_link = '<a href="' . get_permalink( $post->post_parent ) . '">' . get_the_title( $post->post_parent ) . '</a>';
			}

			$data[] = [
				'date'    => $post_date,
				'message' => get_the_content(),
				'parent'  => $post_link,
				'type'    => $log_type,
			];
		}

		wp_reset_postdata();

		$this->_column_headers = [ $columns, [], [] ];
		$this->items           = $data;
	}

	public function get_columns() {
		$columns = [
			'date'    => 'Date',
			'message' => 'Message',
			'parent'  => 'Post',
			'type'    => 'Type',
		];

		return $columns;
	}

	function column_default( $item, $column_name ) {
		return $item[ $column_name ] ?? '';
	}
}

/**
 * Utility for displaying a formatted date, for OTIS logs.
 */
function tror_poi_get_otis_date( $timestamp, $timezone = 'UTC' ) {
	$date = new DateTime( $timestamp, new DateTimeZone( $timezone ) );
	$date->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) );

	return $date->format( 'n-j-Y, g:i a' );
}

/**
 * WP_Logging prune logs after 1 month.
 */
add_filter( 'wp_logging_should_we_prune', '__return_true' );

add_filter( 'wp_logging_prune_when', function ( $time ) {
	return '1 month ago';
} );

if ( ! wp_next_scheduled( 'wp_logging_prune_routine' ) ) {
	wp_schedule_event( time(), 'hourly', 'wp_logging_prune_routine' );
}

function as_increase_time_limit( $time_limit ) {
	if ( isset( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
		return 120;
	}
	return $time_limit;
}
add_filter( 'action_scheduler_queue_runner_time_limit', 'as_increase_time_limit' );


/**
 * Init the dashboard
 */
$otis          = new Otis();
$otis_logger   = new Otis_Logger_Simple();
$otis_importer = new Otis_Importer( $otis, $otis_logger );
new Otis_Dashboard( $otis_importer );