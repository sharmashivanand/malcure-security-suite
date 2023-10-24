<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class mss_gen_features {
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
		add_action( 'Malcure_security_suite_plugin_res', array( $this, 'resources' ) );
		add_action( 'Malcure_security_suite_add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'mss_admin_scripts', array( $this, 'footer_scripts' ) );
		add_action( 'wp_ajax_mss_api_register', array( $this, 'api_register_handler' ) );
		add_action( 'wp_ajax_nopriv_mss_api_register', '__return_false' );
		add_action( 'wp_ajax_mss_destroy_sessions', array( $this, 'destroy_sessions' ) );
		add_action( 'wp_ajax_nopriv_mss_destroy_sessions', '__return_false' );
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
		add_meta_box( 'mss_config', 'Configuration', array( $this, 'configuration' ), $GLOBALS['Malcure_security_suite']['pagehook'], 'side' );
		if ( mss_utils::is_registered() ) {
			add_meta_box( 'mss_connection_details', 'Connection Details', array( $this, 'registration_details' ), $GLOBALS['Malcure_security_suite']['pagehook'], 'side' );
			add_meta_box( 'mss_site_status', 'Site Status', array( $this, 'system_status' ), $GLOBALS['Malcure_security_suite']['pagehook'], 'main' );
			add_meta_box( 'mss_session_management', 'Session Management', array( $this, 'session_management' ), $GLOBALS['Malcure_security_suite']['pagehook'], 'main' );
			add_meta_box( 'mss_logs', 'Logs &amp; Disgnostics', array( $this, 'diags' ), $GLOBALS['Malcure_security_suite']['pagehook'], 'side' );
		} else {
			add_meta_box( 'mss_connection_ui', 'Setup', array( $this, 'connection_ui' ), $GLOBALS['Malcure_security_suite']['pagehook'], 'main' );
		}
	}

	function connection_ui() {
		$current_user = wp_get_current_user();
		?>
		<h3>Quick connection with the Malware Intercept API</h3>
		<p>This plugin is a SaaS solution and allows you to integrate your website with Malware Intercept Security Suite and uptime-monitoring services. A connection to the API endpoint is required for Malcure Security Suite to protect your site. API access is free for fair use and as our user base and traffic load grows, we continue to refine access limits. <a href="https://malwareintercept.com/?p=3&utm_source=adminnotice&utm_medium=web&utm_campaign=mintercept" target="_blank">Privacy Policy.</a></p>
		<p><label><strong>First Name:</strong><br />
		<input type="text" id="mss_user_fname" name="mss_user_fname" value="<?php $current_user->user_firstname; ?>" /></label></p>
		<p><label><strong>Last Name:</strong><br />
		<input type="text" id="mss_user_lname" name="mss_user_lname" value="<?php $current_user->user_lastname; ?>" /></label></p>
		<p><label><strong>Email:</strong><br />
		<input type="text" id="mss_user_email" name="mss_user_email" value="" /></label></p>
		<p><small>We do not use this email address for any other purpose unless you opt-in to receive other mailings. You can turn off alerts in the options.</small></p>
		<a href="#" class="mss_action" id="mss_api_register_btn" role="button">Complete Setup&nbsp;&rarr;</a>
		<script type="text/javascript">
		jQuery(document).ready(function($){
			$("#mss_api_register_btn").click(function(event){
				event.preventDefault();
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

	/**
	 * For now, allows selection of color scheme
	 */
	function configuration() {
		$color_schemes = array( 'Default', 'Darko' );
		$options       = array();
		foreach ( $color_schemes as $color_scheme ) {
			$options[ strtolower( sanitize_text_field( $color_scheme ) ) ] = $color_scheme;
		}
		// print_r($options);
		$current = mss_utils::get_setting( 'color_scheme' );
		?>
		<p><label foe="mss_color_scheme"><strong>Color Scheme:</strong></label></p>
		<select name="mss_color_scheme" id="mss_color_scheme">
		<?php
		foreach ( $options as $v => $k ) {
			echo '<option value="' . $v . '"' . selected( $current, $v, 0 ) . '>' . $k . '</option>';
		}
		?>
		</select>
		<?php
	}

	/**
	 * Show mss error log
	 */
	function diags() {
		mss_utils::llog( mss_utils::get_setting( 'log' ) );
		// var_dump(mss_utils::get_setting( 'log' ));
	}

	/**
	 * Show user registration details
	 */
	function registration_details() {
		$user = mss_utils::is_registered();
		// var_dump(mss_utils::$opt_name);
		// $user = $user['api-credentials'];
		?>
		<table id="mss_user_details">
		<tr><th>Name</th><td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td></tr>
		<tr><th>Email</th><td><?php echo $user['user_email']; ?></td></tr>
		<tr><th>API Connector ID</th><td><?php echo $user['ID']; ?></td></tr>
		</table>
		<?php
	}

	/**
	 * Show verbose system information
	 *
	 * @return void
	 */
	function system_status() {
		global $wpdb;
		?>
		<table id="system_status">
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
				echo '<span class="mss_bricks">' . $role . '</span>';}
			?>
			</td>
		</tr>
		<tr>
			<th>Must-Use Plugins</th>
			<td>
			<?php
			$mu = get_mu_plugins();
			foreach ( $mu as $key => $value ) {
				echo '<span class="mss_bricks">' . $key . '</span>';}
			?>
			</td>
		</tr>
		<tr>
			<th>Drop-ins</th>
			<td>
			<?php
			$dropins = get_dropins();
			foreach ( $dropins as $key => $value ) {
				echo '<span class="mss_bricks">' . $key . '</span>';}
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
		
		</table>
		<?php
	}

	/**
	 * Show logged in usres
	 *
	 * @return void
	 */
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
			echo '<input class="mss_action" value="Logout All Users&nbsp;&rarr;" id="mss_destroy_sessions" type="submit" />';
		?>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				$("#mss_destroy_sessions").click(function(){
					mss_destroy_sessions = {
						mss_destroy_sessions_nonce: '<?php echo wp_create_nonce( 'mss_destroy_sessions' ); ?>',
						action: "mss_destroy_sessions",
						cachebust: Date.now(),
						user: {
							id: <?php echo get_current_user_id(); ?>
						}
					};
					$("#mss_destroy_sessions").fadeTo("slow",.1,);
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
								$("#mss_destroy_sessions").fadeTo("slow",1,);
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

	/**
	 * Logout all other users
	 */
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

	/**
	 * Returns all logged-in users
	 *
	 * @return void
	 */
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
	function api_register_handler() {
		mss_utils::flog($_REQUEST);
		check_ajax_referer( 'mss_api_register', 'mss_api_register_nonce' );
		$user       = $_REQUEST['user'];
		$user['fn'] = preg_replace( '/[^A-Za-z ]/', '', $user['fn'] );
		$user['ln'] = preg_replace( '/[^A-Za-z ]/', '', $user['ln'] );
		mss_utils::flog($user);
		if ( empty( $user['fn'] ) ) {
			wp_send_json_error( 'Invalid firstname.' );
		}
		if ( empty( $user['fn'] ) ) {
			wp_send_json_error( 'Invalid lastname.' );
		}
		if ( ! filter_var( $user['email'], FILTER_VALIDATE_EMAIL ) ) {
			wp_send_json_error( 'Invalid email.' );
		}
		$registration = mss_utils::do_mss_api_register( $user );
		if ( is_wp_error( $registration ) ) {
			wp_send_json_error( $registration->get_error_message() );
		}
		wp_send_json_success( $registration );
		wp_send_json_success( mss_utils::encode( mss_utils::get_plugin_data() ) );
	}

}
function mss_gen_features() {
	return mss_gen_features::get_instance();
}
mss_gen_features();
