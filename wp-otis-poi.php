<?php

/**
 * Define custom post type. Initialize OTIS fields.
 */
function wp_otis_poi_init() {
	register_taxonomy( 'type', [ 'poi' ], [
		'label'             => __( 'Listing Types' ),
		'rewrite'           => [
			'slug'       => 'type',
			'with_front' => false,
		],
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_categories_meta_box',
		'hierarchical'      => true,
	] );

	register_taxonomy( 'glocats', [ 'poi' ], [
		'label'             => __( 'Global Categories' ),
		'rewrite'           => [
			'slug'       => 'glocat',
			'with_front' => false,
		],
		'show_in_nav_menus' => true,
		'meta_box_cb'       => 'post_categories_meta_box',
		'hierarchical'      => true,
	] );

	$labels = array(
		'name'                  => _x( 'Points of Interest', 'Post Type General Name', 'text_domain' ),
		'singular_name'         => _x( 'Point of Interest', 'Post Type Singular Name', 'text_domain' ),
		'menu_name'             => __( 'POI', 'text_domain' ),
		'name_admin_bar'        => __( 'POI', 'text_domain' ),
		'archives'              => __( 'POI Archives', 'text_domain' ),
		'parent_item_colon'     => __( 'Parent POI:', 'text_domain' ),
		'all_items'             => __( 'All POIs', 'text_domain' ),
		'add_new_item'          => __( 'Add New POI', 'text_domain' ),
		'add_new'               => __( 'Add New', 'text_domain' ),
		'new_item'              => __( 'New POI', 'text_domain' ),
		'edit_item'             => __( 'Edit POI', 'text_domain' ),
		'update_item'           => __( 'Update POI', 'text_domain' ),
		'view_item'             => __( 'View POI', 'text_domain' ),
		'search_items'          => __( 'Search POI', 'text_domain' ),
		'not_found'             => __( 'Not found', 'text_domain' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
		'featured_image'        => __( 'Featured Image', 'text_domain' ),
		'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
		'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
		'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
		'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'text_domain' ),
		'items_list'            => __( 'POIs list', 'text_domain' ),
		'items_list_navigation' => __( 'POIs list navigation', 'text_domain' ),
		'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
		'attributes'            => __( 'Order', 'text_domain' ),
	);
	$args = array(
		'label'               => __( 'Point of Interest', 'text_domain' ),
		'description'         => __( 'Point of Interest', 'text_domain' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor', 'revisions', 'thumbnail', 'schemify' ),
		'taxonomies'          => array( 'type' ),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 5,
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => false,
		'rewrite'             => array(
			'slug'       => 'poi',
			'with_front' => false,
		),
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'page-attributes'     => true,
		'capability_type'     => 'post',
	);
	register_post_type( 'poi', $args );

	$field_group = wp_otis_fields_load();

	foreach ( $field_group['fields'] as $field ) {
		if ( 'taxonomy' === $field['type'] && ! get_taxonomy( $field['taxonomy'] ) ) {
			register_taxonomy( $field['taxonomy'], array( 'poi' ), array(
				'label'   => $field['label'],
				'show_ui' => false,
			) );
		}
	}

}

add_action( 'init', 'wp_otis_poi_init', 0 );

/**
 * Add sorting support.
 */
function wp_otis_poi_posts_order() {
	add_post_type_support( 'poi', 'page-attributes' );
}

add_action( 'admin_init', 'wp_otis_poi_posts_order' );

//TODO: Put all of this code back in place. (We need the edit screen functional for now, for debugging)
//add_action( 'wp_enqueue_scripts', function () {
//	if ( is_admin() ) {
//		$src = plugins_url( '/js/poi-script.js', __FILE__ );
//		wp_enqueue_script( 'poi-script', $src, array( 'jquery' ), '20120208', true );
//	}
//} );
//
//add_filter( 'user_can_richedit', function ( $default ) {
//	// Disable the editor because editors can't edit these.
//	if ( 'poi' === get_post_type() ) {
//		return false;
//	}
//
//	return $default;
//} );
//
//add_filter( 'wp_editor_settings', function ( $settings ) {
//	// Hide HTML Edit.
//	$settings['quicktags'] = false;
//
//	return $settings;
//} );
//
//add_action( 'admin_head', function () {
//	// Hide Add Media.
//	if ( 'poi' === get_post_type() ) {
//		remove_action( 'media_buttons', 'media_buttons' );
//	}
//} );
