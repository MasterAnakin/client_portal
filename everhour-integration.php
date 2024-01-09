<?php
/**
 * Plugin Name: Everhour integration
 * Description: Get Everhour API - reatiners, projects, list of tasks
 * Author: Valet - Milos Milosevic
 * Author URI: https://mmilosevic.com
 * Version: 1.0
 */

/**
 * Register Custom Post Type
 */
function valet_portal_clients() {

	$labels = array(
		'name'                  => _x( 'Clients', 'Post Type General Name', 'clientportal' ),
		'singular_name'         => _x( 'Client', 'Post Type Singular Name', 'clientportal' ),
		'menu_name'             => __( 'Clients', 'clientportal' ),
		'name_admin_bar'        => __( 'Post Type', 'clientportal' ),
		'archives'              => __( 'Clients Archives', 'clientportal' ),
		'attributes'            => __( 'Clients Attributes', 'clientportal' ),
		'parent_item_colon'     => __( 'Client Item:', 'clientportal' ),
		'all_items'             => __( 'All Clients', 'clientportal' ),
		'add_new_item'          => __( 'Add New Client', 'clientportal' ),
		'add_new'               => __( 'Add New', 'clientportal' ),
		'new_item'              => __( 'New Client', 'clientportal' ),
		'edit_item'             => __( 'Edit Client', 'clientportal' ),
		'update_item'           => __( 'Update Client', 'clientportal' ),
		'view_item'             => __( 'View Client', 'clientportal' ),
		'view_items'            => __( 'View Clients', 'clientportal' ),
		'search_items'          => __( 'Search Clients', 'clientportal' ),
		'not_found'             => __( 'Not found', 'clientportal' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'clientportal' ),
		'featured_image'        => __( 'Featured Image', 'clientportal' ),
		'set_featured_image'    => __( 'Set featured image', 'clientportal' ),
		'remove_featured_image' => __( 'Remove featured image', 'clientportal' ),
		'use_featured_image'    => __( 'Use as featured image', 'clientportal' ),
		'insert_into_item'      => __( 'Insert into item', 'clientportal' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'clientportal' ),
		'items_list'            => __( 'Items list', 'clientportal' ),
		'items_list_navigation' => __( 'Items list navigation', 'clientportal' ),
		'filter_items_list'     => __( 'Filter items list', 'clientportal' ),
	);
	$args   = array(
		'label'               => __( 'Post Type', 'clientportal' ),
		'description'         => __( 'Post Type Description', 'clientportal' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor' ),
		'taxonomies'          => array( 'category', 'post_tag' ),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 5,
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'capability_type'     => 'page',
	);
	register_post_type( 'valet-client', $args );
}
add_action( 'init', 'valet_portal_clients', 0 );

/**
 * Add Custom Portal Valet role
 */
add_role(
	'basic_contributor',
	'Valet Client',
	array(
		'read'         => true, // True allows that capability.
		'edit_posts'   => false,
		'delete_posts' => false, // Use false to explicitly deny.
	)
);

/**
 * Redirect upon login
 *
 * @param redirect_to $redirect_to get from login_redirect.
 * @param request     $request get from login_redirect.
 * @param user        $user get from login_redirect.
 */
function valet_client_redirect( $redirect_to, $request, $user ) {

	$valet_client_id = get_user_meta( $user->ID, 'valet_client' );
	if ( ! is_admin() ) {

		$post_id = $valet_client_id[0];
		$post    = get_post( $post_id );
		$slug    = $post->post_name;

		return '/valet-client/' . $slug;
	}
}

add_filter( 'login_redirect', 'valet_client_redirect', 10, 3 );


function custom_login_form_shortcode() {
	if ( ! is_user_logged_in() ) {
		ob_start();
		wp_login_form();
		$forgot_password_link = wp_lostpassword_url();
		echo '<a href="' . esc_html( $forgot_password_link ) . '">' . __( 'Forgot Password?' ) . '</a>';
		return ob_get_clean();
	}
}
add_shortcode( 'login_form', 'custom_login_form_shortcode' );


add_action( 'wp_login_failed', 'custom_login_failed' );

function custom_login_failed( $username ) {
	$referrer = wp_get_referer();

	if ( $referrer && ! strstr( $referrer, 'wp-login' ) && ! strstr( $referrer, 'wp-admin' ) ) {
		wp_redirect( add_query_arg( 'login', 'failed', $referrer ) );
		exit;
	}
}
/**
 * Get data from EH and form arrays
 */
function valet_get_data_from_everhour() {

	$plan                  = '';
	$retainer_refresh_date = '';
	$used_time_hours       = '';
	$retainer_total_hours  = '';
	$everhour_client_id    = get_field( 'everhour_client_id' );

	if ( null !== $everhour_client_id ) {

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, 'https://api.everhour.com/projects/' . $everhour_client_id );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'X-Api-Key: add_key',
			)
		);

		$get_valet_client = curl_exec( $ch );
		curl_close( $ch );

		$valet_client_clean = json_decode( $get_valet_client, true );

		if ( isset( $valet_client_clean['budget'] ) ) {

			$retainer_total = $valet_client_clean['budget']['budget'];
			$used_time      = $valet_client_clean['budget']['timeProgress'];

			$retainer_total_hours = $retainer_total / 3600;
			$used_time_hours      = round( $used_time / 3600, 2 );
			// Check when plan refreshes.
			if ( 1 === $valet_client_clean['budget']['monthStartDate'] ) {
				$retainer_refresh_date = 'every 1st of month.';
			}
			if ( 2 === $valet_client_clean['budget']['monthStartDate'] ) {
				$retainer_refresh_date = 'every 2nd of month.';
			}
			if ( 3 === $valet_client_clean['budget']['monthStartDate'] ) {
				$retainer_refresh_date = 'every 3rd of month.';
			}
			if ( 3 < $valet_client_clean['budget']['monthStartDate'] ) {
				$retainer_refresh_date = 'every ' . $valet_client_clean['budget']['monthStartDate'] . 'th of month.';
			}
			// todo - annual retainers.

			if ( $retainer_total_hours < 3 ) {
				$plan = 'Basic';

			}
			if ( $retainer_total_hours > 3 && $retainer_total_hours < 8 ) {
				$plan = 'Professional';

			}
			if ( $retainer_total_hours > 8 && $retainer_total_hours < 26 ) {
				$plan = 'Elite';

			}
		}

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, 'https://api.everhour.com/projects/' . $everhour_client_id . '/tasks' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Content-Type: application/json',
				'X-Api-Key: api_key',
			)
		);

		$client_get_tasks = curl_exec( $ch );
		curl_close( $ch );

		$client_tasks_clean = json_decode( $client_get_tasks, true );

		foreach ( $client_tasks_clean as $key => $part ) {
			$sort[ $key ] = strtotime( $part['createdAt'] );
		}
		array_multisort( $sort, SORT_DESC, $client_tasks_clean );

		$valet_single_client_closed_tasks = array();
		$valet_single_client_open_tasks   = array();
		foreach ( $client_tasks_clean as $item ) {
			if ( strpos( $item['status'], 'complete' ) !== false ) {
				$closed_tasks[] = $item;
			} else {
				$open_tasks[] = $item;
			}
		}

		if ( ! empty( $open_tasks ) ) {
			global $valet_single_client_open_tasks;
			foreach ( $open_tasks as $single_open_task ) {
				$timestamp = strtotime( $single_open_task['createdAt'] );
				if ( isset( $single_open_task['time'] ) ) {
					$open_task_used_time  = $single_open_task['time']['total'];
					$open_task_hours_used = round( $open_task_used_time / 3600, 2 );
				} else {
					$open_task_hours_used = 0;
				}
					$open_task_date = gmdate( 'M-d', $timestamp );
				if ( $open_task_hours_used > 0 ) {
					$valet_single_client_open_tasks[] = array(
						'open_task_name'       => $single_open_task['name'],
						'open_task_date'       => $open_task_date,
						'open_task_used_hours' => $open_task_hours_used,
					);

				}
			}
		} else {
			global $valet_single_client_open_tasks;  $valet_single_client_open_tasks[] = array(
				'open_task_name'       => 'No open tasks',
				'open_task_date'       => '',
				'open_task_used_hours' => '',
			); }
		if ( ! empty( $closed_tasks ) ) {
			global $valet_single_client_closed_tasks;
			foreach ( $closed_tasks as $single_closed_task ) {
				$closed_task_timestamp = strtotime( $single_closed_task['createdAt'] );
				if ( isset( $single_closed_task['time'] ) ) {
					$closed_task_date       = gmdate( 'M-d', $closed_task_timestamp );
					$closed_task_used_time  = $single_closed_task['time']['total'];
					$closed_task_used_hours = round( $closed_task_used_time / 3600, 2 );
				} else {
					$closed_task_used_hours = 0;
				}
				if ( ! str_contains( $single_closed_task['name'], 'Balance Transfer' ) && $closed_task_used_hours > 0 ) {
					$valet_single_client_closed_tasks[] = array(
						'closed_task_name'       => $single_closed_task['name'],
						'closed_task_date'       => $closed_task_date,
						'closed_task_used_hours' => $closed_task_used_hours,
					);
				}
			}
		} else {
			global $valet_single_client_closed_tasks;    $valet_single_client_closed_tasks[] = array(
				'closed_task_name'       => 'No closed tasks',
				'closed_task_date'       => '',
				'closed_task_used_hours' => '',
			); }

		global $valet_get_client_data;

		$valet_get_client_data = array(
			'plan'                  => $plan,
			'retainer_refresh_date' => $retainer_refresh_date,
			'used_time_hours'       => $used_time_hours,
			'retainer_total_hours'  => $retainer_total_hours,
		);
	}
}

		add_shortcode( 'valet_get_data_from_everhour', 'valet_get_data_from_everhour' );
