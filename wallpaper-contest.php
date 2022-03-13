<?php
/*
 *  Plugin Name: Wallpaper Contest
 *  Description: A plugin that helps you organize a wallpaper contest from submissions to voting.
 *  Author: Pasi Lallinaho
 *  Version: 2018-feb
 *  Author URI: http://open.knome.fi/
 *  Plugin URI: http://wordpress.knome.fi/
 *
 *  License: GNU General Public License v2 or later
 *  License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 */

/*  Register the contest post type  */

add_action( 'init', 'wallpaper_contest_init' );

function wallpaper_contest_init( ) {
	register_post_type(
		'wallpaper_contest',
		array(
			'label' => __( 'Contests', 'wallpaper-contest' ),
			'description' => __( 'Wallpaper Contests', 'wallpaper-contest' ),
			'public' => true,
			'menu_icon' => 'dashicons-thumbs-up',
			'capability_type' => 'wallpaper_contest',
			// 'map_meta_cap' => false,
			// 'show_in_rest' => true
		)
	);
}

/*  Register admin pages for voting and vote result pages  */

add_action( 'admin_menu', 'wallpaper_contest_admin_menu' );

function wallpaper_contest_admin_menu( ) {
	add_submenu_page( null, 'Vote', 'Vote', 'wallpaper_contest_vote', 'wallpaper_contest_vote', 'wallpaper_contest_ui_vote' );
	add_submenu_page( null, 'Vote Results', 'Vote Results', 'wallpaper_contest_see_results', 'wallpaper_contest_vote_results', 'wallpaper_contest_ui_vote_results' );
}

/*  Create links to voting and vote results in the contest table  */

add_filter( 'post_row_actions', 'wallpaper_contest_admin_actions', 10, 2 );

function wallpaper_contest_admin_actions( $actions, $post ) {
	if( 'wallpaper_contest' == $post->post_type ) {
		if( current_user_can( 'wallpaper_contest_vote' ) ) {
			$actions['vote'] = '<a href="' . wp_nonce_url( admin_url( 'edit.php?post_type=wallpaper_contest&page=wallpaper_contest_vote&id=' . $post->ID ), 'wallpaper_contest_vote', '_wallpaper_contest_vote' ) . '">' . __( 'Vote', 'wallpaper-contest' ) . '</a>';
		}
		if( current_user_can( 'wallpaper_contest_see_results' ) ) {
			$actions['vote_results'] = '<a href="' . wp_nonce_url( admin_url( 'edit.php?post_type=wallpaper_contest&page=wallpaper_contest_vote_results&id=' . $post->ID ), 'wallpaper_contest_results', '_wallpaper_contest_results' ) . '">' . __( 'Vote Results', 'wallpaper-contest' ) . '</a>';
		}
	}
	return $actions;
}

/*  Enqueue scripts  */

add_action( 'wp_enqueue_scripts', 'wallpaper_contest_enqueue_scripts' );

function wallpaper_contest_enqueue_scripts( ) {
	wp_enqueue_style( 'wallpaper-contest', plugins_url( 'ui.css', __FILE__ ) );
}

add_action( 'admin_enqueue_scripts', 'wallpaper_contest_admin_enqueue_scripts' );

function wallpaper_contest_admin_enqueue_scripts( ) {
	global $current_screen;

	if( 'wallpaper_contest' == $current_screen->post_type ) {
		wp_enqueue_style( 'wallpaper-contest-admin', plugins_url( 'admin.css', __FILE__ ) );
		wp_enqueue_script( 'wallpaper-contest-admin', plugins_url( 'admin.js', __FILE__ ), array( 'jquery' ), '2' );

		wp_enqueue_script( 'wallpaper-contest-vote', plugins_url( 'vote.js', __FILE__ ), array( 'jquery' ), '2' );
		$strings = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'ajaxnonce' => wp_create_nonce( 'wallpaper-contest-vote' ),
			'user' => get_current_user_id( ),
		);
		wp_localize_script( 'wallpaper-contest-vote', 'wallpaper_contest', $strings );

		wp_enqueue_style( 'wallpaper-contest-featherlight-css', plugins_url( 'featherlight/featherlight.min.css', __FILE__ ) );
		wp_enqueue_script( 'wallpaper-contest-featherlight-js', plugins_url( 'featherlight/featherlight.min.js', __FILE__ ), array( 'jquery' ) );
	}
}

/*  Add AJAX request handler for voting  */

add_action( 'wp_ajax_wallpaper_contest_vote', 'wallpaper_contest_ajax_vote' );

function wallpaper_contest_ajax_vote( ) {
	check_ajax_referer( 'wallpaper-contest-vote', 'security' );

	$option = get_post_meta( $_POST['id'], 'wallpaper_contest_votes', true );
	if( !is_array( $option ) ) {
		$option = array( );
	}
	$option[$_POST['user']] = $_POST['value'];

	$r = update_post_meta( $_POST['id'], 'wallpaper_contest_votes', $option );

	if( $r !== false ) {
		echo '1';
		update_post_meta( $_POST['id'], 'wallpaper_contest_vote_total', array_sum( $option ) );
	} else {
		echo '0';
	}

	wp_die( );
}

/*  Register capabilities for user roles  */
/*  TODO: (Un)Register on plugin (de)activation */
/*  TODO: Allow user to decide which user roles can vote?  */

add_action( 'init', 'wallpaper_contest_caps' );

function wallpaper_contest_caps( ) {
	$admin = get_role( 'administrator' );
	$admin->add_cap( 'wallpaper_contest_vote' );
	$admin->add_cap( 'wallpaper_contest_see_results' );
	$admin->add_cap( 'publish_wallpaper_contest' );
	$admin->add_cap( 'edit_wallpaper_contests' );
	$admin->add_cap( 'edit_others_wallpaper_contests' );

	$editor = get_role( 'editor' );
	$editor->add_cap( 'wallpaper_contest_vote' );
	$editor->add_cap( 'edit_wallpaper_contests' );

	$roles = wp_roles( );
	foreach( $roles->role_objects as $slug => $role ) {
		$role->add_cap( 'read_wallpaper_contest' );
	}
}

/*  Register a custom image size  */

add_image_size( 'wallpaper_contest', '300', '300', false );

/*  Register a meta box for contest status  */

add_action( 'add_meta_boxes', 'wallpaper_contest_metaboxes' );

function wallpaper_contest_metaboxes( ) {
	add_meta_box( 'wallpaper_contest_status', __( 'Status', 'wallpaper-contest' ), 'wallpaper_contest_metabox_status', 'wallpaper_contest', 'side' );
}

function wallpaper_contest_metabox_status( ) {
	$status_opts = array(
		'closed' => __( 'Closed', 'wallpaper-contest' ),
		'open' => __( 'Open', 'wallpaper-contest' ),
	);

	echo '<select name="wallpaper_contest_status" class="widefat">';
	foreach( $status_opts as $value => $label ) {
		echo '<option value="' . $value . '" ' . selected( $value, get_post_meta( get_the_ID( ), '_wallpaper_contest_status', true ), false ) . '>' . $label . '</option>';
	}
	echo '</select>';

	$privacy_opts = array(
		'public' => __( 'Public submissions', 'wallpaper-contest' ),
		'private' => __( 'Private submissions', 'wallpaper-contest' ),
	);

	echo '<select name="wallpaper_contest_submission_privacy" class="widefat">';
	foreach( $privacy_opts as $value => $label ) {
		echo '<option value="' . $value . '" ' . selected( $value, get_post_meta( get_the_ID( ), '_wallpaper_contest_submission_privacy', true ), false ) . '>' . $label . '</option>';
	}
	echo '</select>';
	wp_nonce_field( 'wallpaper_contest_status_metabox', '_wallpaper_contest_status_nonce' );
}

/*  Handle saving meta box data  */

add_action( 'save_post_wallpaper_contest', 'wallpaper_contest_metaboxes_save' );

function wallpaper_contest_metaboxes_save( $post_id ) {
	// Verify nonce
	if( !wp_verify_nonce( $_POST['_wallpaper_contest_status_nonce'], 'wallpaper_contest_status_metabox' ) ) {
		return $post_id;
	}

	// Do not save on autosaves
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return $post_id;
	}

	// Check permissions
	if( !current_user_can( 'wallpaper_contest_see_results' ) ) {
		exit( 'Oops' );
	}

	// Save the data
	update_post_meta( $post_id, '_wallpaper_contest_status', sanitize_text_field( $_POST['wallpaper_contest_status'] ) );
	update_post_meta( $post_id, '_wallpaper_contest_submission_privacy', sanitize_text_field( $_POST['wallpaper_contest_submission_privacy'] ) );
}

/*  Add a form after the contest page content  */
/*  Handle user submitted data  */

add_filter( 'the_content', 'wallpaper_contest_output' );

function wallpaper_contest_output( $content ) {
	// Is the post a wallpaper contest
	if( is_singular( 'wallpaper_contest' ) ) {
		$status = get_post_meta( get_the_ID( ), '_wallpaper_contest_status', true );
		$privacy = get_post_meta( get_the_ID( ), '_wallpaper_contest_submission_privacy', true );

		ob_start( );

		if( $status == 'open' ) {
			echo '<h2>' . __( 'Submit', 'wallpaper-contest' ) . '</h2>';
			if( is_user_logged_in( ) ) {
				echo wallpaper_contest_submit( );
				if( !isset( $_GET['action'] ) || 'view' != $_GET['action'] ) {
					wallpaper_contest_form( );
				} else {
					echo '<p><a class="button primary" href="' . get_permalink( ) . '">' . __( 'Go to submission form', 'wallpaper-contest' ) . '</a></p>';
				}
			} else {
				echo '<p>' . sprintf( __( '<a class="button" href="%s">Log in to submit</a>', 'wallpaper-contest' ), wp_login_url( get_permalink( ) ) ) . '<br /><span class="small">When you log in, please allow your email address to be shared so we can contact you in case you win.</span></p>';
			}
		}

		if( $privacy == 'public' ) {
			echo '<h2>' . __( 'Submissions', 'wallpaper-contest' ) . '</h2>';
			if( isset( $_GET['action'] ) && 'view' == $_GET['action'] ) {
				wallpaper_contest_submissions( );
			} else {
				echo '<p><a class="button" href="' . get_permalink( ) . '?action=view">' . __( 'View submissions', 'wallpaper-contest' ) . '</a></p>';
			}
		}

		$content .= ob_get_contents( );
		ob_end_clean( );
	}

	return $content;
}

function wallpaper_contest_form( ) {
	?>
	<form class="wallpaper_contest" enctype="multipart/form-data" action="<?php the_permalink( ); ?>" method="post">
		<div>
			<label for="wallpaper-contest-attribution"><?php _e( 'Attribution', 'wallpaper-contest' ); ?></label>
			<input id="wallpaper-contest-attribution" name="wallpaper-contest-attribution" value="" type="text" class="regular-text ltr" />
			<span class="description"><?php _e( 'Specify the attribution name you would like to be used with your submission, eg. "John Doe".', 'wallpaper-contest' ); ?></span>
		</div>

		<div>
			<label for="wallpaper-contest-name"><?php _e( 'Name of work (optional)', 'wallpaper-contest' ); ?></label>
			<input id="wallpaper-contest-attribution" name="wallpaper-contest-name" type="text" class="regular-text ltr" />
			<span class="description"><?php _e( 'If you want to name your work, you can do it here.', 'wallpaper-contest' ); ?></span>
		</div>

		<div class="license">
			<strong><?php _e( 'License', 'wallpaper-contest' ); ?></strong>
			<div>
				<label for="wallpaper-contest-license-ccby">
					<input id="wallpaper-contest-license-ccby" name="wallpaper-contest-license" type="radio" value="cc-by" />
					Creative Commons, CC-BY 4.0
				</label>
				<span class="description"><strong><?php _e( 'Recommended.', 'wallpaper-contest' ); ?></strong> <?php _e( 'An open source license that allows sharing the wallpaper freely while always attributing the work to you.', 'wallpaper-contest' ); ?> <a href="https://creativecommons.org/licenses/by/4.0/"><?php _e( 'Read more...', 'wallpaper-contest' ); ?></a></span>
			</div>
			<div>
				<label for="wallpaper-contest-license-other">
					<input id="wallpaper-contest-license-other" name="wallpaper-contest-license" type="radio" value="other" />
					<?php _ex( 'Other, please specify details below', 'other license', 'wallpaper-contest' ); ?>
				</label>
				<textarea name="wallpaper-contest-license-other-details" class="large-text" cols="50" rows="2" style="margin: 0.5em 0;"></textarea>
				<span class="description"><?php _e( 'Please note that making sure the license is eligible is the submitters responsibility. Any submissions with an ineligible license will be removed at any time (including after the submission deadline) without notice to the submitter.', 'wallpaper-contest' ); ?></span>
			</div>
		</div>

		<div>
			<label for="wallpaper-contest-submission"><?php _e( 'Select an image to upload', 'wallpaper-contest' ); ?></label>
			<input id="wallpaper-contest-submission" name="wallpaper-contest-submission" type="file" />
		</div>

		<div class="terms">
			<label for="wallpaper-contest-acceptterms">
				<input id="wallpaper-contest-acceptterms" name="wallpaper-contest-acceptterms" type="checkbox" />
				<?php _e( 'I have read and accept the Terms and Guidelines', 'wallpaper-contest' ); ?>
			</label>
			<span class="description"><?php _e( 'All submissions must adhere to the Terms and Guidelines for the competition or they will not considered eligible to win.', 'wallpaper-contest' ); ?></span>
		</div>

		<p>
			<input type="submit" name="wallpaper_contest_submit_new" value="<?php _e( 'Submit', 'wallpaper-contest' ); ?>" />
		</p>

		<input type="hidden" name="action" value="add_wallpaper_contest_submission" />
		<input type="hidden" name="contest_id" value="<?php the_ID( ); ?>" />
		<?php wp_nonce_field( 'add_wallpaper_contest_submission', '_add_wallpaper_contest_submission' ); ?>
	</form>
	<?php
}

function wallpaper_contest_submit( ) {
	global $_POST, $_FILES;

	if( !isset( $_POST['_add_wallpaper_contest_submission'] ) ) {
		return;
	}

	// Verify the user is logged in
	if( !is_user_logged_in( ) ) {
		return '<p class="error hb red">' . __( 'You are not logged in.', 'wallpaper-contest' ) . '</p>';
	}

	// Verify nonce
	if( isset( $_POST['_add_wallpaper_contest_submission'] )
		&& !wp_verify_nonce( $_POST['_add_wallpaper_contest_submission'], 'add_wallpaper_contest_submission' ) ) {
		return '<p class="error hb red">' . __( 'Can not verify nonce.', 'wallpaper-contest' ) . '</p>';
	}

	// Verify there is a file to upload
	if( !isset( $_FILES['wallpaper-contest-submission'] ) ) {
		return '<p class="error hb red">' . __( 'Please select a file to upload.', 'wallpaper-contest' ) . '</p>';
	}

	// Verify the Terms and Guidelines are accepted
	if( !isset( $_POST['wallpaper-contest-acceptterms'] ) || $_POST['wallpaper-contest-acceptterms'] != "on" ) {
		return '<p class="error hb red">' . __( 'You need to accept the Terms and Guidelines.', 'wallpaper-contest' ) . '</p>';
	}

	// Verify an attribution name is given
	if( !isset( $_POST['wallpaper-contest-attribution'] ) || strlen( $_POST['wallpaper-contest-attribution'] ) < 1 ) {
		return '<p class="error hb red">' . __( 'You need to specify the attribution name.', 'wallpaper-contest' ) . '</p>';
	}

	// Upload the file
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	$upload = wp_handle_upload( $_FILES['wallpaper-contest-submission'], array( 'action' => $_POST['action'] ) );

	// Verify the upload succeeded
	if( isset( $upload['error'] ) ) {
		return '<p class="error hb red">' . __( 'Error handling the upload:', 'wallpaper-contest' ) . ' ' . $upload['error'] . '</p>';
	}

	// Attach the uploaded file to the contest and create metadata
	$submission_title = $_POST['wallpaper-contest-attribution'];
	if( isset( $_POST['wallpaper-contest-name'] ) && strlen( $_POST['wallpaper-contest-name'] ) > 0 ) {
		$submission_title .= ': "'. $_POST['wallpaper-contest-name'] . '"';
	}

	$attachment = array(
		'post_mime_type' => $upload['type'],
		'post_title' => $submission_title,
		'post_content' => '',
		'post_status' => 'inherit'
	);

	$attach_id = wp_insert_attachment( $attachment, $upload['file'], $_POST['contest_id'] );
	require_once( ABSPATH . 'wp-admin' . '/includes/image.php' );
	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );

	// Add license
	if( $_POST['wallpaper-contest-license'] == 'cc-by' ) {
		$license = 'CC-BY 4.0';
	} else {
		$license = $_POST['wallpaper-contest-licence-other-details'];
	}

	add_post_meta( $attach_id, 'wallpaper_contest_license', $license, true );
	add_post_meta( $attach_id, 'wallpaper_contest_vote_total', 0, true );

	return '<p class="success hb green">' . __( 'Your submission is registered. Thank you for participating!', 'wallpaper-contest' ) . '</p>';
}

function wallpaper_contest_submissions( ) {
	// $status = get_post_meta( get_the_ID( ), '_wallpaper_contest_status', true );
	$submissions = get_children( array(
		'post_parent' => get_the_ID( ),
		'post_type' => 'attachment',
		'post_mime_type' => 'image',
		'posts_per_page' => -1,
		'orderby' => 'date',
		'order' => 'DESC'
	) );

	if( is_array( $submissions ) && count( $submissions ) > 0 ) {
		echo '<div style="display: grid; grid-template-columns: repeat( 4, 1fr ); grid-gap: 1em; align-items: center;">';
		foreach( $submissions as $sub ) {
			$src = wp_get_attachment_image_src( $sub->ID, 'wallpaper_contest' );
			echo '<div>';
			echo '<a style="border-bottom: none !important;" href="' . wp_get_attachment_url( $sub->ID ). '"><img src="' . $src[0] . '" /></a><br />';
			// echo $sub->post_title;
			echo '</div>';
		}
		echo '</div>';
	} else {
		echo '<p>' . __( 'No submissions found.', 'wallpaper-contest' ) .'</p>';
	}
}

/*  Voting UI  */

function wallpaper_contest_ui_vote( ) {
	// Check permissions
	if( !current_user_can( 'wallpaper_contest_vote' ) ) {
		exit( 'Oops' );
	}

	// Verify nonce
	if( !isset( $_GET['_wallpaper_contest_vote'] ) || !wp_verify_nonce( $_GET['_wallpaper_contest_vote'], 'wallpaper_contest_vote' ) ) {
		echo '<p class="error hb red">' . __( 'Can not verify nonce.', 'wallpaper-contest' ) . '</p>';
		exit;
	}

	$user = get_current_user_id( );
	?>
	<div class="wrap wallpaper_contest_vote">
		<h1><?php _e( 'Voting', 'wallpaper-contest' ); ?></h1>
		<p><?php _e( 'To vote, click on the plus/minus buttons. Your vote is registered once the buttons turn gray. You can change your vote by pressing the other button.', 'wallpaper-contest' ); ?></p>

		<?php
			// $submissions = get_attached_media( 'image', absint( $_GET['id'] ) );
			$submissions = get_children( array(
				'post_parent' => absint( $_GET['id'] ),
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'posts_per_page' => -1,
				'orderby' => 'date',
				'order' => 'DESC'
			) );

			echo '<p class="enable-js"><strong>You need to enable JavaScript to vote.</strong></p>';

			if( is_array( $submissions ) && count( $submissions ) > 0 ) {
				echo '<div class="submissions compact">';
				foreach( $submissions as $sub ) {
					$votes = get_post_meta( $sub->ID, 'wallpaper_contest_votes', true );
					echo '<div class="item" value="' . $sub->ID . '" data-user-vote="' . $votes[$user] . '">';
					$attachment_lg = wp_get_attachment_image_src( $sub->ID, 'large' );
					$attachment_orig = wp_get_attachment_image_src( $sub->ID, 'full' );
					if( $sub->post_mime_type == 'image/svg' ) {
						echo '<div class="image"><a data-featherlight="image" href="' . $attachment_lg[0] . '"><img src="' . $attachment_lg[0] . '" /</a></div>';
					} else {
						echo '<div class="image"><a data-featherlight="image" href="' . $attachment_lg[0] . '">' . wp_get_attachment_image( $sub->ID, 'medium' ) . '</a></div>';
					}
					echo '<div class="vote show-on-js">';
						echo '<a class="up" value="1" href="#" title="' . __( 'Vote up', 'wallpaper-contest' ) . '">+</a> ';
						echo '<a class="down" value="-1" href="#" title="' . __( 'Vote down (not preferred or ineligible)', 'wallpaper-contest' ) . '">&ndash;</a>';
					echo '</div>';
					echo '<div class="information">';
						if( current_user_can( 'wallpaper_contest_see_results' ) ) {
							$meta = get_post_meta( $sub->ID );
							echo $sub->post_title;
							echo ' (' . $meta['wallpaper_contest_license'][0] . ')';
							echo '<br />';
						}
						echo '<a class="original" data-featherlight="image" href="' . $attachment_orig[0] . '">View or download original</a>';
					echo '</div>';
					echo '</div>';
				}
				echo '</div>';
			}
		?>
	</div>
	<?php
}

/*  Vote results UI  */

function wallpaper_contest_ui_vote_results( ) {
	// Check permissions
	if( !current_user_can( 'wallpaper_contest_see_results' ) ) {
		exit( 'Oops' );
	}

	// Verify nonce
	if( !isset( $_GET['_wallpaper_contest_results'] ) || !wp_verify_nonce( $_GET['_wallpaper_contest_results'], 'wallpaper_contest_results' ) ) {
		echo '<p class="error hb red">' . __( 'Can not verify nonce.', 'wallpaper-contest' ) . '</p>';
		exit;
	}

	// Get list of eligible voters
	$voters_raw = get_users( array( 'role' => 'editor' ) );
	foreach( $voters_raw as $voter ) {
		$voters[$voter->ID] = $voter->user_login;
		$voters_count[$voter->ID] = 0;
	}

	?>
	<div class="wrap">
		<h1><?php _e( 'Vote Results', 'wallpaper-contest' ); ?></h1>

		<?php
			$submissions = get_children( array(
				'post_parent' => absint( $_GET['id'] ),
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'posts_per_page' => -1,
				'meta_key' => 'wallpaper_contest_vote_total',
				'orderby' => 'meta_value_num',
				'order' => 'DESC'
			) );

			foreach( $submissions as $sub ) {
				$author_submissions[$sub->post_author] += 1;
			}

			if( !is_array( $submissions ) || count( $submissions ) < 1 ) {
				return;
			}

			echo '<div class="submissions results">';

			foreach( $submissions as $sub ) {
				$meta = get_post_meta( $sub->ID );
				if( $meta['wallpaper_contest_vote_total']['0'] > 0 ) { $class = 'pos'; } else { $class = 'neg'; }
				echo '<div class="item ' . $class . '" value="' . $sub->ID . '" data-vote="' . count( $meta['wallpaper_contest_votes'] ) . '">';
				echo '<div class="result" style="padding-top: 7px;"><span class="total">' . $meta['wallpaper_contest_vote_total']['0'] . '</span><br /><span class="voters">(' . count( unserialize( $meta['wallpaper_contest_votes'][0] ) ) . ')</span></div>';
				if( $sub->post_mime_type == 'image/svg' ) {
					echo '<div class="image"><a data-featherlight="image" href="' . wp_get_attachment_image_src( $sub->ID, 'full' )[0] . '"><img src="' . wp_get_attachment_image_src( $sub->ID, 'large' )[0] . '" /</a></div>';
				} else {
					echo '<div class="image"><a data-featherlight="image" href="' . wp_get_attachment_image_src( $sub->ID, 'full' )[0] . '">' . wp_get_attachment_image( $sub->ID, 'medium' ) . '</a></div>';
				}
				echo '<div class="info">';
				echo '<h3 style="margin: 5px 0 10px 0;">' . $sub->post_title . '</h3>';
				$author_email = get_the_author_meta( 'user_email', $sub->post_author );
				$author_user = get_the_author_meta( 'user_login', $sub->post_author );
				# translators: count of submissions
				echo '<p class="sub"><strong>' . __( 'Submitted by:', 'wallpaper-contest' ) . '</strong> <a href="mailto:' . $author_email . '">' . $author_email . '</a> (' . sprintf( _n( '%d submission', '%d submissions', $author_submissions[$sub->post_author], 'wallpaper-contest' ), $author_submissions[$sub->post_author] ) . ')</p>';
				echo '<p class="sub"><strong>' . __( 'Launchpad ID:', 'wallpaper-contest' ) . '</strong> <a href="https://launchpad.net/~' . $author_user . '">' . $author_user . '</a></p>';
				echo '<p class="sub"><strong>' . __( 'License:', 'wallpaper-contest' ) . '</strong> ' . $meta['wallpaper_contest_license'][0] . '</p>';
				echo '</div>';
				echo '</div>';

				// Vote counts...
				foreach( unserialize( $meta['wallpaper_contest_votes']['0'] ) as $voter => $vote ) {
					$voters_count[$voter]++;
				}
			}

			echo '</div>';
		?>

		<h2><?php _e( 'Voters', 'wallpaper-contest' ); ?></h2>
		<?php
			echo '<p>Submissions total: ' . count( $submissions ) . '</p>';
			echo '<ul>';
			foreach( $voters as $voter => $name ) {
				if( $voters_count[$voter] > 0 ) {
					print '<li><strong>' . $name . '</strong>: ' . $voters_count[$voter] . '</li>';
				}
			}
			echo '</ul>';
		?>
	</div>
	<?php
}

?>
