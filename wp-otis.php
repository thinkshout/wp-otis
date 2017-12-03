<?php
/**
 * Plugin Name:     WP OTIS
 * Plugin URI:      traveloregon.com
 * Description:     OTIS importer
 * Author:          ThinkShout
 * Author URI:      thinkshout.com
 * Text Domain:     wp-otis
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Otis
 */

define( 'WP_OTIS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_OTIS_FIELDS_PATH', plugin_dir_path( __FILE__ ) . 'acf-json/group_58250328ca2ce.json' );

define( 'WP_OTIS_TOKEN', 'wp_otis_token' );
define( 'WP_OTIS_LAST_IMPORT_DATE', 'wp_otis_last_import_date' );

require_once 'wp-otis-poi.php';
require_once 'src/Otis_Importer.php';
require_once 'src/Otis_Logger_Simple.php';
require_once 'src/Otis_Command.php';

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

if ( ! wp_next_scheduled( 'wp_otis_cron' ) ) {
	wp_schedule_event( time(), 'hourly', 'wp_otis_cron' );
}

add_action( 'wp_otis_cron', function () {
	$last_import_date = get_option( WP_OTIS_LAST_IMPORT_DATE, '' );
	if ( ! $last_import_date ) {
		// Only run incremental imports after at least one full import has completed.
		return;
	}

	$otis     = new Otis();
	$logger   = new Otis_Logger_Simple();
	$importer = new Otis_Importer( $otis, $logger );

	try {
		$importer->import( 'pois', [
			'modified' => $last_import_date,
		] );
	} catch ( Exception $e ) {
		$logger->log( $e->getMessage(), 0, 'error' );
	}
} );

if ( ! wp_next_scheduled( 'wp_otis_expire_events' ) ) {
	wp_schedule_event( time(), 'daily', 'wp_otis_expire_events' );
}

add_action( 'wp_otis_expire_events', function () {
	$logger = new Otis_Logger_Simple();

	$query = new WP_Query( array(
		'posts_per_page' => -1,
		'post_type'      => 'poi',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'end_date',
				'type'    => 'DATE',
				'value'   => date( 'Y-m-d' ),
				'compare' => '<',
			),
		),
		'tax_query'      => array(
			array(
				'taxonomy' => 'type',
				'field'    => 'slug',
				'terms'    => TROR_SLUG_EVENTS,
			),
		),
	) );

	while ( $query->have_posts() ) {
		$query->the_post();

		wp_update_post( [
			'ID'          => get_the_ID(),
			'post_status' => 'draft',
		] );

		$logger->log( 'Updated expired event (set status draft) with UUID: ' . get_field( 'uuid' ), get_the_ID() );
	}

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
	if ( $uuid ) {
		$import_url = add_query_arg( [
			'post'   => $post->ID,
			'action' => 'otis_import',
		], admin_url( 'post.php' ) );

		echo '<a class="button button-primary button-large" href="' . esc_url( $import_url ) . '">Reimport Fields</a> ';

		$view_url = Otis::API_ROOT . '/listings/' . $uuid;

		echo '<a class="button" href="' . esc_url( $view_url ) . '" target="_blank">View OTIS</a> ';
	}
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
