<?php

final class mss_Utils {
	private function __construct(){}
	static function get_instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
			$instance->init();
		}
		return $instance;
	}

	function init() {
		add_action( 'malCure_security_suite_plugin_res', array( $this, 'resources' ) );
		add_action( 'malCure_security_suite_add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'mss_admin_scripts', array( $this, 'footer_scripts' ) );

		add_action( 'wp_ajax_mss_api_register', array( $this, 'mss_api_register_handler' ) );
		add_action( 'wp_ajax_nopriv_mss_api_register', '__return_false' );
		add_action( 'wp_ajax_mss_destroy_sessions', array( $this, 'destroy_sessions' ) );
		add_action( 'wp_ajax_nopriv_mss_api_register', '__return_false' );
	}

	function footer_scripts() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($){
			console.log('MSS Ready!');
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			$('.postbox').each(function() {
				$(this).addClass('closed');
				
			});

			$('#mss_connection_ui').removeClass('closed');

			$('#mss_connection_ui').keypress(function (e) {
				var key = e.which;
				if(key == 13)  // the enter key code
				{
				$('#mss_api_register_btn').click();
				return false;  
				}
			}); 
			
		});
		</script>
		<?php
	}

	function add_meta_boxes() {
		if ( malCure_Utils::is_registered() ) {
			add_meta_box( 'mss_connection_details', 'Connection Details', array( $this, 'registration_details' ), $GLOBALS['malCure_security_suite']['pagehook'], 'side' );
			add_meta_box( 'mss_site_status', 'Site Status', array( $this, 'mss_system_status' ), $GLOBALS['malCure_security_suite']['pagehook'], 'main' );
			add_meta_box( 'mss_session_management', 'Session Management', array( $this, 'session_management' ), $GLOBALS['malCure_security_suite']['pagehook'], 'main' );
		} else {
			add_meta_box( 'mss_connection_ui', 'Setup', array( $this, 'connection_ui' ), $GLOBALS['malCure_security_suite']['pagehook'], 'main' );
		}
	}

	function connection_ui() {
		
			$current_user = wp_get_current_user();
			?>
			<h3>Quick connection with the Malcure API</h3>
			<p>A connection to the API endpoint is required for Malcure to protect your site.</p>
			<p><label><strong>First Name:</strong><br />
			<input type="text" id="mss_user_fname" name="mss_user_fname" value="<?php $current_user->user_firstname; ?>" /></label></p>
			<p><label><strong>Last Name:</strong><br />
			<input type="text" id="mss_user_lname" name="mss_user_lname" value="<?php $current_user->user_lastname; ?>" /></label></p>
			<p><label><strong>Email:</strong><br />
			<input type="text" id="mss_user_email" name="mss_user_email" value="" /></label></p>
			<p><small>We do not use this email address for any other purpose unless you opt-in to receive other mailings. You can turn off alerts in the options.</small></p>
			<a href="#" class="button-primary" id="mss_api_register_btn" role="button">Complete Setup&nbsp;&rarr;</a>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				$("#mss_api_register_btn").click(function(){
					mss_api_register = {
						mss_api_register_nonce: '<?php echo wp_create_nonce( 'mss_api_register' ); ?>',
						action: "mss_api_register",
						user: {
							fn: $('#mss_user_fname').val(),
							ln: $('#mss_user_lname').val(),
							email: $('#mss_user_email').val(),
						}
					};
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: mss_api_register,
						success: function(response_data, textStatus, jqXHR) {
							console.dir(response_data);
							if ((typeof response_data) != 'object') { // is the server not sending us JSON?

							}
							if (response_data.hasOwnProperty('success') && response_data.success) { // ajax request has a success but we haven't tested if success is true or false
								location.reload();
							} else { // perhaps this is just JSON without a success object
								alert('Failed to register with API. Error: ' + response_data.data );
							}
						},
						error: function( jqXHR, textStatus, errorThrown){},
						complete: function(jqXHR_data, textStatus) { // use this since we need to run and catch regardless of success and failure
						},
					});
				});
			});
			</script>
			<?php
		
	}

	function registration_details() {
		
		$user = malCure_Utils::is_registered();
		//var_dump(malCure_Utils::$opt_name);
		//$user = $user['api-credentials'];
		?>
		<table id="mss_user_details">
		<tr><th>Name</th><td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td></tr>
		<tr><th>Email</th><td><?php echo $user['user_email']; ?></td></tr>
		<tr><th>API Connector ID</th><td><?php echo $user['ID']; ?></td></tr>
		</table>
		<?php
	
	}

	function mss_system_status() {
		global $wpdb;
		?>
		<table id="mss_system_status">
		<tr>
			<th>Website URL</th>
			<td><?php echo get_bloginfo( 'url' ); ?></td>
		</tr>
		<tr>
			<th>WP URL</th>
			<td><?php echo get_bloginfo( 'wpurl' ); ?></td>
		</tr>
		<tr>
			<th>WP Installation DIR</th>
			<td><?php echo ABSPATH; ?></td>
		</tr>
		<tr>
			<th>WP Version</th>
			<td><?php echo get_bloginfo( 'version' ); ?></td>
		</tr>
		<tr>
			<th>WP Language</th>
			<td><?php echo get_bloginfo( 'language' ); ?></td>
		</tr>
		<tr>
			<th>WP Multisite</th>
			<td><?php echo is_multisite() ? 'Yes' : 'No'; ?></td>
		</tr>
		<tr>
			<th>Active Theme</th>
			<td><?php echo get_bloginfo( 'stylesheet_directory' ); ?></td>
		</tr>
		<tr>
			<th>Parent Theme</th>
			<td><?php echo get_bloginfo( 'template_directory' ); ?></td>
		</tr>
		<tr>
			<th>User Roles</th>
			<td>
			<?php
			global $wp_roles;
			foreach ( $wp_roles->roles as $role => $capabilities ) {
				echo '<span class="wpmr_bricks">' . $role . '</span>';}
			?>
			</td>
		</tr>
		<tr>
			<th>Must-Use Plugins</th>
			<td>
			<?php
			$mu = get_mu_plugins();
			foreach ( $mu as $key => $value ) {
				echo '<span class="wpmr_bricks">' . $key . '</span>';}
			?>
			</td>
		</tr>
		<tr>
			<th>Drop-ins</th>
			<td>
			<?php
			$dropins = get_dropins();
			foreach ( $dropins as $key => $value ) {
				echo '<span class="wpmr_bricks">' . $key . '</span>';}
			?>
			</td>
		</tr>
		<tr>
			<th>PHP</th>
			<td><?php echo phpversion(); ?></td>
		</tr>
		<tr>
			<th>Web-Server</th>
			<td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
		</tr>
		<tr>
			<th>Server</th>
			<td><?php echo php_uname(); ?></td>
		</tr>
		<tr>
			<th>Server Address</th>
			<td><?php echo $_SERVER['SERVER_ADDR']; ?></td>
		</tr>
		<tr>
			<th>Server Port</th>
			<td><?php echo $_SERVER['SERVER_PORT']; ?></td>
		</tr>
		<tr>
		<?php $allfilescount = malCure_Utils::get_files( get_home_path() ); ?>
			<th>Total Files</th>
			<td>
			<?php echo $allfilescount['total_files']; ?>
			</td>
		</tr>
		
		<tr><th>File Count (Recursive)</th><td>
		<?php
		$dirs = glob( trailingslashit( get_home_path() ) . '*', GLOB_ONLYDIR );
		$dirs = array_merge( glob( trailingslashit( get_home_path() ) . 'wp-content/*', GLOB_ONLYDIR ), $dirs );
		if ( $dirs ) {
			asort( $dirs );
			echo '<table>';
			echo '<tr><th>Directory</th><th></th></tr>';
			foreach ( $dirs as $dir ) {
				echo '<tr><td class="dir_container">' . str_replace( get_home_path(), '', $dir ) . '</td><td class="dir_count">' . malCure_Utils::get_files( $dir )['total_files'] . '</td></tr>';
			}
			echo '</table>';
		}
		?>
		</td></tr>
		<tr><th>Hidden Files &amp; Folders</th>
		<td id="hidden_files">
		<?php
		$hidden  = array_filter(
			malCure_Utils::get_files( get_home_path() )['files'],
			function( $v ) {
				return ( empty( explode( '.', basename( $v ) )[0] ) || empty( explode( '.', basename( dirname( $v ) ) )[0] ) ) ? true : false;
			}
		);
		$hidden  = array_values( $hidden );
		$newlist = array();
		foreach ( $hidden as $k => $v ) {
			$parts = explode( '.', basename( dirname( $v ) ) );
			if ( empty( $parts [0] ) ) {
				$newlist[ dirname( $v ) ] = '<strong>[*DIR] ' . dirname( $v ) . '</strong>';
			}
			$newlist[ $v ] = '[FILE] ' . $v;
		}
		echo implode( '<br />', $newlist );
		?>
		</td></tr>
		</table>
		<?php
	}

	function session_management() {
		?>
		<h3>Logged-In Users</h3>
			<?php
			$users = $this->get_users_loggedin();
			foreach ( $users as $user ) {
				echo '<table class="user_details" id="user_details_"' . $user->ID . '>';
				echo '<tr><th class="user_details_id">User ID</th><td>' . $user->ID . '</td></tr>';
				echo '<tr><th class="user_details_roles">User Roles</th><td>' . implode( ',', $user->roles ) . '</td></tr>';
				echo '<tr><th class="user_details_user_login">User Login</th><td>' . $user->user_login . '</td></tr>';
				echo '<tr><th class="user_details_user_email">User Email</th><td>' . $user->user_email . '</td></tr>';
				echo '<tr><th class="user_details_display_name">Display Name</th><td>' . $user->display_name . '</td></tr>';
				echo '<tr><th class="user_details_user_registered">Date Registered</th><td>' . $user->user_registered . '</td></tr>';
				$s_details = '';
				$s_details = get_user_meta( $user->ID, 'session_tokens', true );
				echo '<tr><th  class="user_details_session_ip">Sessions</th><td>';
				foreach ( $s_details as $s_detail ) {
					echo '<table class="user_details_session">';
					echo '<tr><th  class="user_details_session_ip">IP Address</th><td>' . $s_detail['ip'] . '</td></tr>';
					echo '<tr><th  class="user_details_session_ua">User-Agent</th><td>' . $s_detail['ua'] . '</td></tr>';
					echo '<tr><th  class="user_details_session_login">Login Date</th><td>' . date( 'Y M d', $s_detail['login'] ) . '</td></tr>';
					echo '<tr><th  class="user_details_session_expiration">Login Expiration Date</th><td>' . date( 'Y M d', $s_detail['expiration'] ) . '</td></tr>';
					echo '</table>';
				}
				echo '</td></tr>';
				echo '</table>';
			}
			?>
		<?php
			submit_button( 'Logout All Users', 'primary', 'malcure_destroy_sessions' );
		?>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				$("#malcure_destroy_sessions").click(function(){
					mss_destroy_sessions = {
						mss_destroy_sessions_nonce: '<?php echo wp_create_nonce( 'mss_destroy_sessions' ); ?>',
						action: "mss_destroy_sessions",
						cachebust: Date.now(),
						user: {
							id: <?php echo get_current_user_id(); ?>
						}
					};
					$("#malcure_destroy_sessions").fadeTo("slow",.1,);
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: mss_destroy_sessions,
						complete: function(jqXHR, textStatus) {},
						success: function(response) {
							if ((typeof response) != 'object') {
								response = JSON.parse( response );
							}
							if (response.hasOwnProperty('success')) {
								$("#malcure_destroy_sessions").fadeTo("slow",1,);
								if(confirm('All users have been logged out (except you). Reload the page now?')) {
									location.reload();
								}
							} else {
								alert('Failed to logout other users.');
							}
						}
					});
				})
			});
			</script>
			<?php
	}

	function destroy_sessions() {
		check_ajax_referer( 'mss_destroy_sessions', 'mss_destroy_sessions_nonce' );
		$users = $this->get_users_loggedin();
		$id    = $_REQUEST['user']['id'];
		foreach ( $users as $user ) {
			if ( $user->ID != $id ) {
				$sessions = WP_Session_Tokens::get_instance( $user->ID );
				$sessions->destroy_all();
			}
		}
		wp_send_json_success();
	}

	function get_users_loggedin() {
		return get_users(
			array(
				'meta_key'     => 'session_tokens',
				'meta_compare' => 'EXISTS',
			)
		);
	}

	function resources() {

	}

	/**
	 * Trigger User registration Server-Side / Ajax
	 *
	 * @return void
	 */
	function mss_api_register_handler() {
		check_ajax_referer( 'mss_api_register', 'mss_api_register_nonce' );
		$user       = $_REQUEST['user'];
		$user['fn'] = preg_replace( '/[^A-Za-z ]/', '', $user['fn'] );
		$user['ln'] = preg_replace( '/[^A-Za-z ]/', '', $user['ln'] );
		if ( empty( $user['fn'] ) ) {
			wp_send_json_error( 'Invalid firstname.' );
		}
		if ( empty( $user['fn'] ) ) {
			wp_send_json_error( 'Invalid lastname.' );
		}
		if ( ! filter_var( $user['email'], FILTER_VALIDATE_EMAIL ) ) {
			wp_send_json_error( 'Invalid email.' );
		}
		$registration = malCure_Utils::do_mss_api_register( $user );
		if ( is_wp_error( $registration ) ) {
			wp_send_json_error( $registration->get_error_message() );
		}
		wp_send_json_success( $registration );
		wp_send_json_success( malCure_Utils::encode( malCure_Utils::get_plugin_data() ) );
	}
}

function mss_Utils() {
	return mss_Utils::get_instance();
}

mss_Utils();
