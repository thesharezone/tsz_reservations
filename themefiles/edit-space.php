<?php
/*
Template Name: Manage Space
*/

$key_details = tsz_key_details();

$listing = get_post($wp->query_vars['listing-id'], ARRAY_A);
$menu_order = $wp->query_vars['menu-order'];


global $current_user, $wpdb;
get_currentuserinfo();


if($listing['post_author'] == $current_user->ID || $current_user->ID == 1) {
	if($space = getTszSpace($listing['ID'], $menu_order)) {
		// do nothing
	} else {
		$error_msg = "We can't find that space.";
	}

	$spaces_limit = get_option( "spaces_limit" );


	//// FORM POSTED
	if(isset($_POST['reservation'])) {
		$data = $_POST['reservation'];

		// keep formatted dates
		$start_format = $data['start'];
		$end_format = $data['end'];

		// convert date to time for compare and save
		$data['start'] = strtotime($data['start']);
		$data['end'] = strtotime($data['end']);

		// check email encryption
		if($data['guest_email']) {
			$encrypted_email = $data['guest_email'];
			$decrypted_guest_email = tsz_decrypt_email($encrypted_email);
			$recrypted = tsz_encrypt_email($decrypted_guest_email);
			$data['guest_email'] = $recrypted;

		}


		// check does not overlap existing reservation, and not to distant or past
		$overlapping = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM tsz_reservations WHERE ((start <= %d AND end >= %d) OR (start <= %d AND end >= %d)) AND space_id = $space->ID",
				$data['start'], $data['start'], $data['end'], $data['end']));

		foreach($overlapping as $res) {
			if($res and $res->id != $data['id'])
				$error_msg = "The dates you entered conflict with another reservation.";
		}

		// check reservation not too distant future or past
		$future_months_limit = get_option( "future_months_limit" );

		if($data['start'] > time() + ($future_months_limit * 30 * 24 * 60 * 60))
			$error_msg = "You can only enter reservations up to $future_months_limit months in advance.";
		elseif($data['end'] < time())
			$error_msg = "You can only enter reservations for future dates.";

		if(!isset($error_msg)) {

			if($data['id'] && $reservation = $wpdb->get_row($wpdb->prepare(
					"SELECT * FROM tsz_reservations WHERE id = %d",
					$data['id']))) 
			{
				//die(print_r($data));
				$wpdb->show_errors();
				$wpdb->query($wpdb->prepare(
					"UPDATE tsz_reservations
						SET start = %d, end = %d, guest_email = %s, guest_name = %s, details = %s
						WHERE id =  %d", 
					$data
				) );
			} else {
				unset($data['id']);
				$data["space_id"] = $space->ID;
				$wpdb->query( $wpdb->prepare(
						"INSERT INTO tsz_reservations
							( start, end, guest_email, guest_name, details, space_id )
							VALUES ( %d, %d, %s, %s, %s, %d )", 
						$data
				) );
				//die($wpdb->print_error());

			}

			if($data['guest_email']) { //// confirmation email, optional!
				$decrypted_host_email = tsz_decrypt_email($current_user->user_email);

				$headers = 'From: theShare.zone Host <' . $decrypted_host_email  . '>' . "\r\n" .
						'Reply-To: ' . $decrypted_host_email  . "\r\n";
					
				$message = "Your reservation of $space->post_title has been confirmed by the host!" . "\n";
				$message .= "Your reservation starts $start_format and ends $end_format. " . "\n";
				$message .= "Please refer to the listing for more details: " . get_permalink($listing['ID']) . "\n";
				if($btc_address = get_post_meta( $listing['ID'], "tsz_listing_btc_address", true )) {
					$message .= "This host can receive bitcoin payments at this address: $btc_address" . "\n";
					$message .= "You'll be able to leave a review for them at if you pay with bitcoin: http://hash.reviews" . "\n";
				}
					
				$message .= "\n";
				$message .= "Thanks for using " . get_bloginfo("name") . "!" . "\n";

				//die("$headers $decrypted_guest_email $message ");

				if(wp_mail( $decrypted_guest_email, 'Reservation Confirmation', $message, $headers ))
					$success_msg = "Confirmation Sent!";
				else
					$error_msg = "Your confirmation could not be sent, you can re-save the reservation to try again.";
			}

		}

	}

	// get all including the lates insert
	$reservations =  getTszReservations($space->ID);


} else {
	$listing = array();

	$error_msg = "You Can't Edit This Space.";
}



get_header();

?>
<div id="main-content" class="main-content">
	<div id="primary" class="content-area">
		<div id="content" class="site-content" role="main">
			<header class="entry-header">
				<h1>Manage Reservations</h1>
				<h3 style="margin-top: 5px">"<?php echo $space->post_title ?>"</h3>
			</header>
			<div class="entry-content">
				<?php include WP_PLUGIN_DIR . "/tsz_listings/themefiles/messages.php" ?>

				<div id="calendar" class="calendar">
				</div>

				<form name="reservationform" id="reservationform" method="post" action="/edit-space/<?php echo $listing['ID'] ?>/<?php echo $menu_order ?>" >
					
					<h3 id="form_label" class="success">New Reservation</h3>
					<p>
						<input id="start" type="text" name="reservation[start]" placeholder="begins" > to 
						<input id="end" type="text" name="reservation[end]" placeholder="ends" ><br>
						(all times are local)
					</p>
					<p>
						<label for="guest_email">Guest Email (for confirmation, your email address will be sent to them) <br />
						<input type="text" name="reservation[guest_email]" id="guest_email" class="input" size="55" value="<?php echo stripslashes($guest_email) ?>" /></label>
					</p>
					<p>
						<label for="guest_name">Guest Name (shown in your calendar)<br />
						<input type="text" name="reservation[guest_name]" id="guest_name" class="input" size="55" value="<?php echo stripslashes($guest_name) ?>" /></label>
					</p>
					<p>
						<label for="details">Details<br />
						<textarea name="reservation[details]" id="details" class="input"><?php echo stripslashes($details) ?></textarea>
					</p>
					<input type="hidden" name="reservation[id]" id="id" />

					<br class="clear" style="clear: both; padding-top: 20px;" />
					<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Save" /> &nbsp;&nbsp;&nbsp;&nbsp; <a href="/edit-listing/<?php echo $listing['ID'] ?>"><b>Edit Listing</b></a></p>

						
				</form>
				
				<div style="margin-bottom: 30px">

				</div>
         

				
				<?php
				// Start the Loop.
				while ( have_posts() ) : the_post();
					the_content();
				endwhile;
			?>
			</div>

		</div><!-- #content -->
	</div><!-- #primary -->
	<?php get_sidebar( 'content' ); ?>
</div><!-- #main-content -->


<script type="text/javascript">
	jQuery("#reservationform").submit(function( event ) {
		cryptEmail(event, "guest_email", '<?php echo to_hex($key_details['rsa']['n']) ?>', '<?php echo to_hex($key_details['rsa']['e']) ?>');
	});



	jQuery('#calendar').fullCalendar({
        header: {
				left: 'title',
				right: 'prev,next today'
			},
		editable: false,
		allDaySlot: false,
		events: [
			<?php foreach($reservations as $reservation): ?>
				{
					id: 	'<?php echo $reservation->id ?>',
					title: '<?php echo $reservation->guest_name ?>',
					start: '<?php echo date("c", $reservation->start) ?>',
					end: '<?php echo date("c", $reservation->end) ?>',
				},
			<?php endforeach; ?>
				],
		eventClick: function(calEvent, jsEvent, view) {
			var data = eval("reservations.event" + calEvent.id);
			jQuery("#id").val(calEvent.id);
			jQuery("#start").val(data.start);
			jQuery("#end").val(data.end);
			jQuery("#guest_email").val(data.guest_email);
			jQuery("#guest_name").val(data.guest_name);
			jQuery("#details").val(data.details);
			jQuery("#form_label").html("Edit Reservation <input type=\"button\" value=\"new\" id=\"new_button\" style=\"padding: 4px\" />");
			jQuery("#form_label").attr('class', 'error');

			jQuery("#new_button").click(function(){
					jQuery("#id").val(null);
					jQuery("#start").val(null);
					jQuery("#end").val(null);
					jQuery("#guest_email").val(null);
					jQuery("#guest_name").val(null);
					jQuery("#details").val(null);
					jQuery("#form_label").text("New Reservation");
					jQuery("#form_label").attr('class', 'success');
		    });
	    }
    })




    jQuery( "#reservationform" ).submit(function( event ) {
	  if(jQuery("#start").val() == "" || jQuery("#end").val() == "") {
	  	alert("You must enter 'begins' and 'ends' dates.");
	  	event.preventDefault();
	  } 
	  else if(Date.parse(jQuery("#start").val()) > Date.parse(jQuery("#end").val())) {
	  	alert("The 'ends' date must follow 'begins' date.");
	  	event.preventDefault();
	  }
	  
	});


	var options = {
		id: 999,
		format:'n/j/Y g:i a',
		defaultTime:'3:00 pm',
		formatTime: 'g:i a'
	};
	jQuery('#start').datetimepicker(options);
	options.defaultTime = '11:00 am';
	jQuery('#end').datetimepicker(options);


	var reservations = {
	<?php foreach($reservations as $reservation): ?>
		"event<?php echo $reservation->id ?>" : {
				"start" : "<?php echo date('n/j/Y g:i a', $reservation->start) ?>",
				"end" : "<?php echo date('n/j/Y g:i a', $reservation->end) ?>",
				"guest_email" : "<?php echo $reservation->guest_email ?>",
				"guest_name" : "<?php echo $reservation->guest_name ?>",
				"details" : "<?php echo $reservation->details ?>",


		},
	<?php endforeach; ?>
	}
	console.log(reservations);

</script>

<?php
get_sidebar();
get_footer();


