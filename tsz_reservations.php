<?php
/*
Plugin Name: The Share Zone Reservations
Description: Requires TSZ Listings plugin
Version: 1.0
Author: The Share Zone
*/

defined('ABSPATH') or die;

add_action('init', 'tsz_space_register');
 
function tsz_space_register() {
 
  $labels = array(
    'name' => _x('Spaces', 'post type general name'),
    'singular_name' => _x('Space', 'post type singular name'),
    'add_new' => _x('Add New', 'Space'),
    'add_new_item' => __('Add New Space'),
    'edit_item' => __('Edit Space'),
    'new_item' => __('New Space'),
    'view_item' => __('View Space'),
    'search_items' => __('Search Spaces'),
    'not_found' =>  __('No listings found'),
    'not_found_in_trash' => __('No spaces found in Trash'),
    'parent_item_colon' => ''
  );
 
  $args = array(
    'labels' => $labels,
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true,
    'query_var' => true,
    'menu_icon' => plugins_url( 'img/icon.png' , __FILE__ ),
    'rewrite' => true,
    'capability_type' => 'post',
    'hierarchical' => false,
    'menu_position' => 21,
    'supports' => array('title','editor','thumbnail'),
    'has_archive' => true,
    'taxonomies' => array('post_tag')
    ); 
 
  register_post_type( 'space' , $args );
  
  //If there is permalink wonkiness enable this:
  //flush_rewrite_rules();
}


add_action( 'admin_init', 'tsz_reservation_page_init' );

/**
 * Register and add settings
 */
function tsz_reservation_page_init()
{       

  $settings = array(
      'spaces_limit' => 'Spaces per Listing',
      'past_months_limit' => 'Limit reservations to ? months in the past',
      'future_months_limit' => 'Limit reservations to ? months in the future'
    );

  foreach($settings as $id => $label) {
      register_setting(
          'tsz_listing_options', // Option group
          $id
      );

      add_settings_field(
          $id, // ID
          $label, // Title 
          'render_input_field', // Callback
          'tsz_listing_options_page', // Page
          'setting_section_id', // Section    
          array("id" => $id)    
      ); 
  }

}

function render_input_field(array $args)
{
    printf(
        '<input type="text" name="%s" value="%s"  />',
        $args['id'],
        get_site_option($args['id'])
    );
}

function getTszSpace($listing_id, $menu_order) {
  $spaces = get_posts(array(
          'post_type' => "space",
          'post_status' => "publish",
          'post_parent' => $listing_id,
          'menu_order'  => $menu_order 

    ));


  return reset($spaces);
}

function getTszSpaces($listing_id) {
  $spaces = get_posts(array(
          'post_type' => "space",
          'post_status' => "publish",
          'post_parent' => $listing_id,
          'orderby' => 'menu_order',
          'order' => 'ASC'
    ));

  $return = array();
  foreach($spaces as $space) {
    $return[$space->menu_order] = $space;
  }


  return $return;

}

function getTszReservations($space_id) {
  global $wpdb;
   return $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM tsz_reservations WHERE space_id = %d",
      $space_id));
}

add_action('tsz_edit_listing_load','tsz_delete_space');

function tsz_delete_space($listing)
{
  global $wp, $wpdb, $success_msg;
  if(isset($wp->query_vars['delete-space'])) {
    $space = getTszSpace($listing["ID"], $wp->query_vars['delete-space']);
    wp_delete_post($space->ID, 1);
    $wpdb->query($wpdb->prepare("DELETE FROM tsz_reservations WHERE space_id = %d", $space->id) );
    $success_msg = "Space Deleted.";
  }

}



add_action('tsz_edit_listing_post','tsz_edit_spaces_post');

function tsz_edit_spaces_post($listing)
{
  global $wpdb;
  $spaces_limit = get_option( "spaces_limit" );

  if(isset($_GET['delete_space'])) {

    deleteTszSpace($_GET['listing_id'], $_GET['space']);
  }

  if(isset($_POST['space_names'])) {
    $wpdb->suppress_errors = false;
    $space_names = $_POST['space_names'];
    //die(print_r($spaces));


    foreach($space_names as $menu_order => $title) {
      if($space = getTszSpace($listing["ID"], $menu_order)) {
        $space = array(
          'ID' => $space->ID,
          'post_type' => "space",
          'post_title' => $title
        );
        wp_update_post( $space );

      } elseif($title != "") {
        $space = array(
          'post_type' => "space",
          'post_status' => "publish",
          'post_title' => $title,
          'post_author' => $listing["post_author"],
          'post_parent' => $listing["ID"],
          'menu_order'  => $menu_order
        );
        $space_id = wp_insert_post( $space );

      }

      if($ord >= $spaces_limit)
        break; // strict enforcment
      
    }
  }
}


add_action('tsz_edit_listing_form','tsz_edit_spaces_form');

function tsz_edit_spaces_form($listing_id)
{
  $spaces_limit = get_option( "spaces_limit" );
  $spaces = getTszSpaces($listing_id); ?>

  <p><h3>Reservations:</h3>You can keep track of reservations for up to <?php echo $spaces_limit ?> different rooms/spaces.  Each space needs a short, specific name ("The Blue Room", etc...) </p>
  <ul style="list-style-type: none; margin: 0; padding: 0;">
    <?php for($i = 1; $i <= $spaces_limit; $i++): ?>
      <li>
      <label for="space_name[<?php echo $i?>]">Space <?php echo $i ?> 
        <?php if(isset($spaces[$i])): ?>: 
        <a href="/edit-space/<?php echo $listing_id ?>/<?php echo $i ?>"> Manage Reservations</a>
         or <a href="/edit-listing/<?php echo $listing_id ?>/delete-space/<?php echo $i ?>">Delete</a>

        <?php endif; ?><br />
      <input type="text" name="space_names[<?php echo $i ?>]" size="40" value="<?php if(isset($spaces[$i])) echo $spaces[$i]->post_title ?>" placeholder="name"></label>
    </li>

    <?php endfor; ?>

  </ul>
  <?php
}

add_action('tsz_single_listing_display','tsz_show_spaces');

function tsz_show_spaces($listing_id)
{
  $spaces = getTszSpaces($listing_id); ?>

    <?php foreach($spaces as $menu_order => $space): ?>
      <?php $reservations =  getTszReservations($space->ID); ?>

      <h4><?php echo $space->post_title ?></h4>
      <div id="calendar<?php echo $menu_order?>" class="calendar">
          </div>

      <script type="text/javascript">

          jQuery('#calendar<?php echo $menu_order?>').fullCalendar({
                header: {
                left: 'title',
                right: 'prev,next today'
              },
            editable: false,
            allDaySlot: false,
            events: [
              <?php foreach($reservations as $reservation): ?>
                {
                  title: 'reserved',
                  start: '<?php echo date("c", $reservation->start) ?>',
                  end: '<?php echo date("c", $reservation->end) ?>',
                },
              <?php endforeach; ?>
                ]
            })


      </script>

    <?php endforeach; ?>

  <?php
}



add_filter("tsz_supported_pages", "space_pages");

function space_pages($supported_pages) {
  $supported_pages["edit-space"] = "tsz_reservations";
  return $supported_pages;
}



/// custom rewrite rules:

add_filter( 'rewrite_rules_array','tsz_reservations_rewrite_rules' );
add_filter( 'query_vars','tsz_reservations_insert_query_vars' );


function tsz_reservations_rewrite_rules( $rules )
{
  $newrules = array();
  $newrules['edit-listing/(\d*)/delete-space/(\d*)$'] = 'index.php?pagename=edit-listing&listing-id=$matches[1]&delete-space=$matches[2]';
  $newrules['edit-space/(\d*)/(\d*)$'] = 'index.php?pagename=edit-space&listing-id=$matches[1]&menu-order=$matches[2]';
  return $newrules + $rules;
}

function tsz_reservations_insert_query_vars( $vars )
{
    array_push($vars, 'menu-order');
    array_push($vars, 'delete-space');
    return $vars;
}


