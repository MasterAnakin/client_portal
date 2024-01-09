<?php
/**
 * Plugin Name: Everhour integration
 * Description: Get Everhour API - reatiners, projects, list of tasks
 * Author: Valet - Milos Milosevic
 * Author URI: https://valet.io
 * Version: 1.0
 */
function get_data_from_everhour() {

	$everhour_client_id = get_field( 'everhour_client_id' );

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, 'https://api.everhour.com/projects/' . $everhour_client_id );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_HEADER, false );

	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'X-Api-Key: c51c-b701-e2f6af-c0f838-3177af42',
		)
	);

	$get_valet_client = curl_exec( $ch );
	curl_close( $ch );

	$valet_client_clean = json_decode( $get_valet_client, true );

	//if ( get_the_title() === $valet_client_clean['name'] ) {
		echo '<style>h2, h3 {
              margin-bottom: 0;
              margin-top: 1.25em;
            }</style>';

		echo '<h2>Client: ' . esc_html( $valet_client_clean['name'] ) . '</h2> ';
		if ( null !== $valet_client_clean['budget'] ) {

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
				echo 'Care Plan: Basic';
				echo '<br>';
			}
			if ( $retainer_total_hours > 3 && $retainer_total_hours < 8 ) {
				echo 'Care Plan: Professional';
				echo '<br>';
			}
			if ( $retainer_total_hours > 8 && $retainer_total_hours < 26 ) {
				echo 'Care Plan: Elite';
				echo '<br>';
			}
			echo 'Hours Refresh: ' . esc_html( $retainer_refresh_date );
			echo '<h3>Current month: ' . esc_html( $used_time_hours ) . ' of ' . esc_html( $retainer_total_hours ) . ' hours used</h3> ';

		}
	//}
	// Get Client list of tasks
	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, 'https://api.everhour.com/projects/' . $everhour_client_id . '/tasks' );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_HEADER, false );

	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'X-Api-Key: c51c-b701-e2f6af-c0f838-3177af42',
		)
	);

	$client_get_tasks = curl_exec( $ch );
	curl_close( $ch );

	$client_tasks_clean = json_decode( $client_get_tasks, true );

	foreach ( $client_tasks_clean as $key => $part ) {
		$sort[ $key ] = strtotime( $part['createdAt'] );
	}
	array_multisort( $sort, SORT_DESC, $client_tasks_clean );

	$closed_tasks = array();
	$open_tasks   = array();
	foreach ( $client_tasks_clean as $item ) {
		if ( strpos( $item['status'], 'complete' ) !== false ) {
			$closed_tasks[] = $item;
		} else {
			$open_tasks[] = $item;
		}
	}

	if ( ! empty( $open_tasks ) ) {
		echo '<h3>Open Tasks:</h3>';?>
<style>
td {
border: 1px solid;
}
</style>

<table style="width:100%">
<tr>
  <th>Date</th>
  <th>Name of the task</th>
  <th>Hours spent</th>
</tr>
		<?php
		foreach ( $open_tasks as $single_open_task ) {
			$timestamp            = strtotime( $single_open_task['createdAt'] );
			$open_task_used_time  = $single_open_task['time']['total'];
			$open_task_hours_used = round( $open_task_used_time / 3600, 2 );
			$open_task_date       = date( 'M-d', $timestamp );
			if ( $open_task_hours_used > 0 ) {
				echo '<tr><td>' . esc_html( $open_task_date ) . '</td><td>' . esc_html( $single_open_task['name'] ) . '</td>' . '<td>' . esc_html( $open_task_hours_used ) . '</td></tr>';
			}
		}
		?>
</table>
		<?php
	} else {
		echo '<h3>There are no open tasks at the moment.</h3>';
	}

	if ( ! empty( $closed_tasks ) ) {
		echo '<h3>History:</h3>';
		?>
<style>
  td {
  border: 1px solid;
}
</style>
<table style="width:100%">
 <tr>
  <th>Date</th>
  <th>Name of the task</th>
  <th>Hours spent</th>
  </tr>

		<?php
		foreach ( $closed_tasks as $single_closed_task ) {
			$closed_task_timestamp   = strtotime( $single_closed_task['createdAt'] );
			$closed_task_date   = date( 'M-d', $closed_task_timestamp );
			$closed_task_used_time = $single_closed_task['time']['total'];
			$closed_task_used_hours     = round( $closed_task_used_time / 3600, 2 );

			if ( ! str_contains( $single_closed_task['name'], 'Balance Transfer' ) && $closed_task_used_hours > 0 ) {
				echo '<tr><td>' . esc_html( $closed_task_date ) . '</td><td>' . esc_html( $single_closed_task['name'] ) . '</td>' . '<td>' . esc_html( $closed_task_used_hours ) . '</td></tr>';
			}
		}
		?>

</table>

		<?php
		if ( $retainer_total_hours > 8 ) {
			echo '<br><b>If you have 911, please reach out here.</b>';
		}
	}
}

add_shortcode( 'get_data_from_everhour', 'get_data_from_everhour' );



// Register Custom Post Type
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

/*
function redirect_after_login() {
	$milos = get_field( 'everhour_client_id', 9 );

	return $milos;

		//return '/valet-client/';

}

add_filter( 'login_redirect', 'redirect_after_login' );

*/





function my_login_redirect( $redirect_to, $request, $user ) {

$valet_client_id = get_user_meta($user->ID, 'valet_client');


$post_id = $valet_client_id[0];
$post = get_post($post_id); 
$slug = $post->post_name;

return '/valet-client/' . $slug;

}

add_filter( 'login_redirect', 'my_login_redirect', 10, 3 );


add_role(
	'basic_contributor',
	'Valet Client',
	array(
		'read'         => true, // True allows that capability
		'edit_posts'   => false,
		'delete_posts' => false, // Use false to explicitly deny
	)
);














function get_data_from_everhour2() {

	$everhour_client_id = get_field( 'everhour_client_id' );

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, 'https://api.everhour.com/projects/' . $everhour_client_id );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_HEADER, false );

	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'X-Api-Key: c51c-b701-e2f6af-c0f838-3177af42',
		)
	);

	$get_valet_client = curl_exec( $ch );
	curl_close( $ch );

	$valet_client_clean = json_decode( $get_valet_client, true );

	//if ( get_the_title() === $valet_client_clean['name'] ) {


		//echo '<h2>Client: ' . esc_html( $valet_client_clean['name'] ) . '</h2> ';
		if ( null !== $valet_client_clean['budget'] ) {

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
				$plan = 'Care Plan: Basic';

			}
			if ( $retainer_total_hours > 3 && $retainer_total_hours < 8 ) {
				$plan = 'Care Plan: Professional';

			}
			if ( $retainer_total_hours > 8 && $retainer_total_hours < 26 ) {
				$plan = 'Care Plan: Elite';

			}
			//echo 'Hours Refresh: ' . esc_html( $retainer_refresh_date );
			//echo '<h3>Current month: ' . esc_html( $used_time_hours ) . ' of ' . esc_html( $retainer_total_hours ) . ' hours used</h3> ';



		}
	//}
	// Get Client list of tasks
	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, 'https://api.everhour.com/projects/' . $everhour_client_id . '/tasks' );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_HEADER, false );

	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'X-Api-Key: c51c-b701-e2f6af-c0f838-3177af42',
		)
	);

	$client_get_tasks = curl_exec( $ch );
	curl_close( $ch );

	$client_tasks_clean = json_decode( $client_get_tasks, true );

	foreach ( $client_tasks_clean as $key => $part ) {
		$sort[ $key ] = strtotime( $part['createdAt'] );
	}
	array_multisort( $sort, SORT_DESC, $client_tasks_clean );

	$closed_tasks = array();
	$open_tasks   = array();
	foreach ( $client_tasks_clean as $item ) {
		if ( strpos( $item['status'], 'complete' ) !== false ) {
			$closed_tasks[] = $item;
		} else {
			$open_tasks[] = $item;
		}
	}

	if ( ! empty( $open_tasks ) ) {
		//echo '<h3>Open Tasks:</h3>';?>

		<?php
		global $open;
		foreach ( $open_tasks as $single_open_task ) {
			$timestamp            = strtotime( $single_open_task['createdAt'] );
			$open_task_used_time  = $single_open_task['time']['total'];
			$open_task_hours_used = round( $open_task_used_time / 3600, 2 );
			$open_task_date       = date( 'M-d', $timestamp );
			if ( $open_task_hours_used > 0 ) {
				//echo '<tr><td>' . esc_html( $open_task_date ) . '</td><td>' . esc_html( $single_open_task['name'] ) . '</td>' . '<td>' . esc_html( $open_task_hours_used ) . '</td></tr>';
			$open[] = array ("open_task_name" => $single_open_task['name'], "open_task_date" => $open_task_date, "open_task_used_hours" => $open_task_hours_used);
		
			}
		}

		?>
</table>
		<?php
	} else {
		//echo '<h3>There are no open tasks at the moment.</h3>';
	}

	if ( ! empty( $closed_tasks ) ) {
		//echo '<h3>History:</h3>';
		?>


		<?php
		global $closed;
		foreach ( $closed_tasks as $single_closed_task ) {
			$closed_task_timestamp   = strtotime( $single_closed_task['createdAt'] );
			$closed_task_date   = date( 'M-d', $closed_task_timestamp );
			$closed_task_used_time = $single_closed_task['time']['total'];
			$closed_task_used_hours     = round( $closed_task_used_time / 3600, 2 );

			if ( ! str_contains( $single_closed_task['name'], 'Balance Transfer' ) && $closed_task_used_hours > 0 ) {
				//echo '<tr><td>' . esc_html( $closed_task_date ) . '</td><td>' . esc_html( $single_closed_task['name'] ) . '</td>' . '<td>' . esc_html( $closed_task_used_hours ) . '</td></tr>';
			$closed[] = array ("closed_task_name" => $single_closed_task['name'], "closed_task_date" => $closed_task_date, "closed_task_used_hours" => $closed_task_used_hours);		
			}
		}
		?>

</table>

		<?php
		if ( $retainer_total_hours > 8 ) {
			$valet_emergency = true;
		}
			global $all;
			$all = array("plan" => $plan, "retainer_refresh_date" => $retainer_refresh_date, "used_time_hours" => $used_time_hours, "retainer_total_hours" => $retainer_total_hours, "911" =>$valet_emergency);		
	}
}

add_shortcode( 'get_data_from_everhour2', 'get_data_from_everhour2' );