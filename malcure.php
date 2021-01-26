<?php
/**
 * malCure Security Suite
 *
 * @package     malCure Security Suite
 * @author      malCure
 * @copyright   2020 malcure.com
 * @license     MIT
 *
 * @wordpress-plugin
 * Plugin Name: malCure Security Suite
 * Description: malCurity Security Suite helps you lock down and secure your WordPress site.
 * Version:     0.3
 * Author:      malCure
 * Author URI:  https://malcure.com
 * Text Domain: malcure-security-suite
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Plugin URI:  https://malcure.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'MSS_GOD' ) ) {
	define( 'MSS_GOD', 'activate_plugins' );
}

define( 'MSS_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'MSS_FILE', __FILE__ );
define( 'MSS_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'MSS_API_EP', 'https://wp-malware-removal.com/' );

final class malCure_security_suite {

	public $dir;
	public $url;


	static function get_instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
			$instance->init();
		}
		return $instance;
	}

	private function __construct() {
	}

	function init() {
		$this->dir = trailingslashit( plugin_dir_path( __FILE__ ) );
		$this->url = trailingslashit( plugin_dir_url( __FILE__ ) );

		include_once $this->dir . 'lib/utils.php';
		if ( malCure_Utils::is_registered() ) {
			include_once $this->dir . 'classes/integrity.php';
			include_once $this->dir . 'classes/malware_scanner.php';
			include_once $this->dir . 'classes/salt-shuffler.php';
		}

		add_filter( 'site_status_tests', array( $this, 'malcure_security_tests' ) );

		add_action( 'admin_menu', array( $this, 'settings_menu' ) );
		add_action( 'mss_settings_menu', array( $this, 'debug_menu' ) );

		add_action( 'admin_head', array( $this, 'admin_inline_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'plugin_res' ) );

		add_action( 'admin_footer', array( $this, 'footer_scripts' ) );

		add_action( 'wp_ajax_mss_api_register', array( $this, 'mss_api_register_handler' ) );
		add_action( 'wp_ajax_nopriv_mss_api_register', '__return_false' );

	}

	function mss_api_register_handler() {
		check_ajax_referer( 'mss_api_register', 'mss_api_register_nonce' );

		$user = $_REQUEST['user'];

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

	function admin_inline_style() {
		?>
		<style type="text/css">
		#toplevel_page__mss .wp-menu-image img {
			width: 24px;
			height: auto;
			opacity: 1;
			/*padding: 0 0 0 0;*/
			padding: 6px 0 0 0;
		}
		</style>
		<?php
	}

	function plugin_res( $hook ) {
		// $this->llog( $hook );
		if ( preg_match( '/_mss$/', $hook ) ) {
			wp_enqueue_style( 'mss-stylesheet', $this->url . 'assets/style.css', array(), filemtime( $this->dir . 'assets/style.css' ) );
			wp_enqueue_script( 'jquery' );
		}
	}

	function settings_menu() {
		add_menu_page(
			'malCure Security', // page_title
			'malCure Security', // menu_title
			MSS_GOD,   // capability
			'_mss',  // menu_slug
			array( $this, 'settings_page' ), // function
			$this->url . 'assets/icon-dark-trans.svg', // icon_url
			79
		);

		do_action( 'mss_settings_menu' );

	}

	function debug_menu() {
		add_submenu_page(
			'_mss',  // parent_slug
			'malCure Debug', // page_title
			'malCure Debug', // menu_title
			MSS_GOD, // capability
			'debug_mss',
			array( $this, 'debug_mss_page' )
		);
	}

	function settings_page() {
		?>
		<div class="wrap">
		<h1>malCure Security Suite</h1>
			<div class="container">
			<?php
			echo '<div id="mss_branding" class="mss_branding" >' . $this->render_branding() . '</div>';

			if ( ! malCure_Utils::is_registered() ) {
				$current_user = wp_get_current_user();
				?>
				<h3>You have successfully installed malCure Security Suite</h3>
				<p>Submit the following information to download free anti-virus definitions and malCure rules.</p>
				<p><label><strong>First Name:</strong><br />
				<input type="text" id="mss_user_fname" name="mss_user_fname" value="<?php $current_user->user_firstname; ?>" /></label></p>
				<p><label><strong>Last Name:</strong><br />
				<input type="text" id="mss_user_lname" name="mss_user_lname" value="<?php $current_user->user_lastname; ?>" /></label></p>
				<p><label><strong>Email:</strong><br />
				<input type="text" id="mss_user_email" name="mss_user_email" value="" /></label></p>
				<p><small>We do not use this email address for any other purpose unless you opt-in to receive other mailings. You can turn off alerts in the options.</small></p>
				<a href="#" class="button-primary" id="mss_api_register_btn" role="button">Next&nbsp;&rarr;</a>
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
							//cachebust: Date.now(), // 
						};
						//$("#mss_trigger_scan").fadeTo("slow",.1,);
						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: mss_api_register,
							success: function(response_data, textStatus, jqXHR) {
								console.dir(response_data);
								if ((typeof response_data) != 'object') { // is the server not sending us JSON?
									//response = JSON.parse( response );
								}
								if (response_data.hasOwnProperty('success') && response_data.success) { // ajax request has a success but we haven't tested if success is true or false
									location.reload();
								} else { // perhaps this is just JSON without a success object
									alert('Failed to register with API. Error: ' + response_data.data );
								}
							},
							error: function( jqXHR, textStatus, errorThrown){
								// console.dir('error Data Begins');
								// console.dir(jqXHR);
								// console.dir(textStatus);
								// console.dir(errorThrown);
								// console.dir('error Data Ends');
							},
							complete: function(jqXHR_data, textStatus) { // use this since we need to run and catch regardless of success and failure
								// console.dir('complete Data Begins');
								// console.dir(jqXHR_data);
								// console.dir(textStatus);
								// console.dir('complete Data Ends');
								// // a good JSON response may have status: 200, statusText: "success", responseJSON (object)
							},
						});
					});
				});
				</script>
				<?php
			} else {
				// var_dump( malCure_Utils::update_definitions() );
				// malCure_Utils::llog( malCure_Utils::check_definition_updates() );
				// malCure_Utils::llog( malCure_Utils::get_plugin_checksums() );
				submit_button( 'Init Scan', 'primary', 'mss_trigger_scan', true );
				// malCure_Utils::delete_setting( 'checksums' );
				// malCure_Utils::delete_setting( 'mc_scan_tracker' );
				// malCure_Utils::delete_setting( 'scan' );
				// malCure_Utils::update_definitions();

				?>
				<script type="text/javascript">
			jQuery(document).ready(function($){
				$("#mss_trigger_scan").click(function(){
					mss_trigger_scan = {
						mss_trigger_scan_nonce: '<?php echo wp_create_nonce( 'mss_trigger_scan' ); ?>',
						action: "mss_trigger_scan",
						cachebust: Date.now(),
						user: {
							id: <?php echo get_current_user_id(); ?>
						}
					};					
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: mss_trigger_scan,
						complete: function(jqXHR, textStatus) {
							console.dir(jqXHR);
						},
						ssuccess: function(response) {
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
				$mss_scanner = malCure_Malware_Scanner::get_instance();
				// $mss_scanner->get_checksums();

				// $start_time = microtime( true );
				// malCure_Utils::delete_setting( 'malware-signatures-clone');
				// $sigs = malCure_Utils::get_setting( 'malware-signatures' );
				// $res  = malCure_Utils::update_setting( 'malware-signatures-clone', $sigs );
				// $end_time       = microtime( true );
				// $execution_time = ( $end_time - $start_time );
				// echo 'Took ' . ($execution_time)  . 'ms or ' . human_time_diff( $start_time, $end_time );
				// var_dump( $res );

				// $mss_scanner->mss_scan_handler();

				// var_dump(  $mss_scanner->in_core_dir('/_extvol_data/html/dev/plugindev/wp-content/index.php') );
				// malCure_Utils::llog( $mss_scanner->get_files() );
				?>
				<h2>Notice</h2>
				<p><strong>This plugin is meant for security experts to interpret the results and implement necessary measures as required. Here's the system status. For other features and functions please make your selection from the plugin-sub-menu from the left.</strong></p>
				<h2>System Status</h2>
				<?php $this->mss_system_status(); ?>
				<?php
			}

			?>
				
			</div> <!-- / .container -->
		</div> <!-- / .wrap -->
		<?php
	}

	function render_branding() {
		return '<img src="' . MSS_URL . 'assets/logo-light-trans.svg" />';
	}

	function debug_mss_page() {
		?>
		<div class="wrap">
		<h1>malCure Debug</h1>
			<div class="container">
			<?php
			echo '<div id="mss_debug_branding" class="mss_branding" >' . $this->render_branding() . '</div>';
			malCure_Utils::llog( 'MSS' );
			malCure_Utils::llog( var_export( get_option( 'MSS' ), 1 ) );
			malCure_Utils::llog( 'MSS_scans' );
			$scans = get_option( 'MSS_scans' );
			krsort( $scans );
			malCure_Utils::llog( var_export( $scans, 1 ) );
			// malCure_Utils::llog( 'MSS_definitions' );
			// malCure_Utils::llog( var_export( get_option( 'MSS_definitions' ), 1 ) );
			// malCure_Utils::llog( 'MSS_checksums_core' );
			// malCure_Utils::llog( var_export( get_option( 'MSS_checksums_core' ), 1 ) );
			malCure_Utils::llog( 'MSS_checksums_generated' );
			malCure_Utils::llog( var_export( get_option( 'MSS_checksums_generated' ), 1 ) );
			?>
			</div>
		</div>
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
		<?php $this->malcure_user_sessions(); ?>
		
		</table>

		<?php

	}

	function destroy_sessions() {
		check_ajax_referer( 'malcure_destroy_sessions', 'malcure_destroy_sessions_nonce' );
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

	function malcure_user_sessions() {
		?>
		<tr><th>Logged-In Users</th><td>
			<?php
			submit_button( 'Logout All Users', 'primary', 'malcure_destroy_sessions' );
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
			</td></tr>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				$("#malcure_destroy_sessions").click(function(){
					malcure_destroy_sessions = {
						malcure_destroy_sessions_nonce: '<?php echo wp_create_nonce( 'malcure_destroy_sessions' ); ?>',
						action: "malcure_destroy_sessions",
						cachebust: Date.now(),
						user: {
							id: <?php echo get_current_user_id(); ?>
						}
					};
					$("#malcure_destroy_sessions").fadeTo("slow",.1,);
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: malcure_destroy_sessions,
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

	function footer_scripts() {
		$screen = get_current_screen();
		if ( preg_match( '/_mss$/', $screen->id ) ) {
			do_action( 'mss_admin_scripts' );
		}
	}

	function malcure_security_tests( $tests ) {

		$tests['direct']['abspath_perm_test'] = array(
			'label' => __( 'Permissions of WordPress installation directory' ),
			'test'  => array( $this, 'abspath_perm_test_callback' ),
		);

		$tests['direct']['wp_admin_perm_test'] = array(
			'label' => __( 'Permissions of wp-admin directory' ),
			'test'  => array( $this, 'wp_admin_perm_test_callback' ),
		);

		$tests['direct']['wp_includes_perm_test'] = array(
			'label' => __( 'Permissions of wp-includes directory' ),
			'test'  => array( $this, 'wp_includes_perm_test_callback' ),
		);

		$tests['direct']['wp_content_perm_test'] = array(
			'label' => __( 'Permissions of wp-content directory' ),
			'test'  => array( $this, 'wp_content_perm_test_callback' ),
		);

		$tests['direct']['themes_perm_test'] = array(
			'label' => __( 'Permissions of themes directory' ),
			'test'  => array( $this, 'themes_perm_test_callback' ),
		);

		$tests['direct']['plugins_perm_test'] = array(
			'label' => __( 'Permissions of plugins directory' ),
			'test'  => array( $this, 'plugins_perm_test_callback' ),
		);

		$tests['direct']['uploads_perm_test'] = array(
			'label' => __( 'Permissions of uploads directory' ),
			'test'  => array( $this, 'uploads_perm_test_callback' ),
		);

		$tests['direct']['wp_config_perm_test'] = array(
			'label' => __( 'Permissions of wp-config.php' ),
			'test'  => array( $this, 'wp_config_perm_test_callback' ),
		);

		$tests['direct']['htaccess_perm_test'] = array(
			'label' => __( 'Permissions of .htaccess' ),
			'test'  => array( $this, 'htaccess_perm_test_callback' ),
		);

		// Test if admin user exists
		$tests['direct']['admin_user_test'] = array(
			'label' => __( 'Does a user with user_login of "admin" exist?' ),
			'test'  => array( $this, 'admin_user_test_callback' ),
		);
		return $tests;
	}

	/**
	 * Check if a user with slug "admin" exists
	 *
	 * @return array
	 */
	function admin_user_test_callback() {
		$result = array(
			'label'       => __( 'No access for admin user account' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Admin user doesn\'t exist.' ) ),
			'actions'     => '',
			'test'        => 'admin_user_test',
		);

		if ( get_user_by( 'login', 'admin' ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Admin user exists' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <a href="https://wordpress.org/support/article/hardening-wordpress/#security-through-obscurity">%s</a></p>', __( 'A user named admin exists on your site. Many attacks target this user ID. Please change the username of this user.' ), 'WordPress Codex' );
			$result['actions']       .= sprintf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'users.php' ) ), __( 'Manage admin users' ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for WordPress installation directory permissions
	 *
	 * @return array
	 */
	function abspath_perm_test_callback() {
		$abs_path       = ABSPATH;
		$abs_path_perms = $this->get_permissions( $abs_path );
		$result         = array(
			'label'       => __( 'Permissions for WordPress installation directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions for WordPress installation directory are set to 755' ) ),
			'actions'     => '',
			'test'        => 'abspath_perm_test',
		);

		if ( ! (
			$this->user_can_read( $abs_path ) && $this->user_can_write( $abs_path ) && $this->user_can_execute( $abs_path ) && // 7
			$this->group_can_read( $abs_path ) && ! $this->group_can_write( $abs_path ) && $this->group_can_execute( $abs_path ) && // 5
			$this->other_can_read( $abs_path ) && ! $this->other_can_write( $abs_path ) && $this->other_can_execute( $abs_path )        // 5
		) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Set permissions on the WordPress installation directory to 755' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s %s <a href="%s" target="_blank">WordPress Codex</a>.</p>', __( 'All files should be writable only by your user account (755). Current permissions are' ), $this->get_permissions( $abs_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Your WordPress installation directory is located at:' ), esc_url( ABSPATH ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-admin directory permissions
	 *
	 * @return array
	 */
	function wp_admin_perm_test_callback() {

		$core_admin_path = trailingslashit( ABSPATH ) . 'wp-admin';

		$result = array(
			'label'       => __( 'Permissions for wp-admin' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on wp-admin directory are set to 755' ) ),
			'actions'     => '',
			'test'        => 'wp_admin_perm_test',
		);

		if ( ! (
			$this->user_can_read( $core_admin_path ) && $this->user_can_write( $core_admin_path ) && $this->user_can_execute( $core_admin_path ) && // 7
			$this->group_can_read( $core_admin_path ) && ! $this->group_can_write( $core_admin_path ) && $this->group_can_execute( $core_admin_path ) && // 5
			$this->other_can_read( $core_admin_path ) && ! $this->other_can_write( $core_admin_path ) && $this->other_can_execute( $core_admin_path )       // 5
			) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Review permissions on wp-admin directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'All files should be writable only by your user account (755). Current permissions are' ), $this->get_permissions( $core_admin_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your wp-admin is:' ), esc_url( $core_admin_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-includes directory permissions
	 *
	 * @return array
	 */
	function wp_includes_perm_test_callback() {

		$core_inc_path = trailingslashit( ABSPATH ) . WPINC;

		$result = array(
			'label'       => __( 'Permissions for wp-content' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on wp-includes directory are set to 755' ) ),
			'actions'     => '',
			'test'        => 'wp_includes_perm_test',
		);

		if ( ! (
			$this->user_can_read( $core_inc_path ) && $this->user_can_write( $core_inc_path ) && $this->user_can_execute( $core_inc_path ) && // 7
			$this->group_can_read( $core_inc_path ) && ! $this->group_can_write( $core_inc_path ) && $this->group_can_execute( $core_inc_path ) && // 5
			$this->other_can_read( $core_inc_path ) && ! $this->other_can_write( $core_inc_path ) && $this->other_can_execute( $core_inc_path )     // 5
		) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Review permissions on wp-includes directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'All files should be writable only by your user account (755). Current permissions are' ), $this->get_permissions( $core_inc_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your wp-includes is:' ), esc_url( $core_inc_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-content directory permissions
	 *
	 * @return array
	 */
	function wp_content_perm_test_callback() {

		$core_content_path = WP_CONTENT_DIR;

		$result = array(
			'label'       => __( 'Permissions for wp-content' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on wp-content directory are set to 775' ) ),
			'actions'     => '',
			'test'        => 'wp_content_perm_test',
		);

		if ( ! (
			$this->user_can_read( $core_content_path ) && $this->user_can_write( $core_content_path ) && $this->user_can_execute( $core_content_path ) && // 7
			$this->other_can_read( $core_content_path ) && $this->other_can_write( $core_content_path ) && $this->other_can_execute( $core_content_path ) && // 7
			$this->other_can_read( $core_content_path ) && ! $this->other_can_write( $core_content_path ) && $this->other_can_execute( $core_content_path )     // 5
			 ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Review permissions on wp-content directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'User-supplied content: intended to be writable by your user account and the web server process (775). Current permissions are' ), $this->get_permissions( $core_content_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to wp-content is:' ), esc_url( $core_content_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for themes directory permissions
	 *
	 * @return array
	 */
	function themes_perm_test_callback() {

		$theme_root_path = trailingslashit( get_stylesheet_directory() ) . 'style.css';

		$result = array(
			'label'       => __( 'Permissions for themes directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on themes files are set to 664' ) ),
			'actions'     => '',
			'test'        => 'themes_perm_test',
		);

		if ( ! (
			$this->user_can_read( $theme_root_path ) && $this->user_can_write( $theme_root_path ) && ! $this->user_can_execute( $theme_root_path ) && // 6
			$this->group_can_read( $theme_root_path ) && $this->group_can_write( $theme_root_path ) && ! $this->group_can_execute( $theme_root_path ) && // 6
			$this->other_can_read( $theme_root_path ) && ! $this->other_can_write( $theme_root_path ) && ! $this->other_can_execute( $theme_root_path )   // 4
		 ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Review permissions on themes files' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'If you want to use the built-in theme editor, all files need to be writable by the web server process (664). If you do not want to use the built-in theme editor, all files can be writable only by your user account (644). Current permissions are' ), $this->get_permissions( $theme_root_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Your current theme files are inside:' ), esc_url( get_stylesheet_directory() ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for plugins directory permissions
	 *
	 * @return array
	 */
	function plugins_perm_test_callback() {

		$plugin_root_path = __FILE__;

		$result = array(
			'label'       => __( 'Permissions for plugin files' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on plugin files are set to 644' ) ),
			'actions'     => '',
			'test'        => 'plugins_perm_test',
		);

		if ( ! (
			$this->user_can_read( $plugin_root_path ) && $this->user_can_write( $plugin_root_path ) && ! $this->user_can_execute( $plugin_root_path ) && // 6
			$this->group_can_read( $plugin_root_path ) && ! $this->group_can_write( $plugin_root_path ) && ! $this->group_can_execute( $plugin_root_path ) && // 4
			$this->other_can_read( $plugin_root_path ) && ! $this->other_can_write( $plugin_root_path ) && ! $this->other_can_execute( $plugin_root_path )    // 4
			 ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Review permissions on plugin files' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'Plugin files should be writable only by your user account (644). Current permissions are' ), $this->get_permissions( $plugin_root_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Plugins files are stored inside:' ), esc_url( $plugin_root_path ) );
		}

		return $result;
	}

	function get_config_path() {
		WP_Filesystem();
		global $wp_filesystem;
		$config_path = '';
		if ( $wp_filesystem->exists( ABSPATH . 'wp-config.php' ) && $wp_filesystem->is_file( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php'; // The config file resides in ABSPATH
		} elseif ( $wp_filesystem->exists( dirname( ABSPATH ) . '/wp-config.php' ) && $wp_filesystem->is_file( dirname( ABSPATH ) . '/wp-config.php' ) && ! $wp_filesystem->exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php'; // The config file resides one level above ABSPATH but is not part of another installation
		}
	}

	/**
	 * File permission test callback for wp-config.php file permissions
	 *
	 * @return array
	 */
	function wp_config_perm_test_callback() {

		$config_path = $this->get_config_path();

		$result = array(
			'label'       => __( 'Permissions for wp-config.php' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on wp-config.php are set to 400 or 440' ) ),
			'actions'     => '',
			'test'        => 'wp_config_perm_test',
		);

		if ( ! $config_path ) {
			return $result;
		}

		if ( ! ( $this->user_can_read( $config_path ) && ! $this->user_can_write( $config_path ) && ! $this->user_can_execute( $config_path ) && // 4
			$this->group_can_read( $config_path ) && ! $this->group_can_write( $config_path ) && ! $this->group_can_execute( $config_path ) && // 4
			! $this->other_can_read( $config_path ) && ! $this->other_can_write( $config_path ) && ! $this->other_can_execute( $config_path )       // 0
		) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Review permissions on wp-config.php' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s" target="_blank">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify wp-config.php to infect the website. 440 permissions are recommended. Current permissions are' ), $this->get_permissions( $config_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#securing-wp-config-php' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your wp-config.php is:' ), esc_url( $config_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for root .htaccess file permissions
	 *
	 * @return array
	 */
	function htaccess_perm_test_callback() {

		$root_htaccess_path = $this->get_htaccess_path();

		$result = array(
			'label'       => __( 'Permissions for .htaccess' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on .htaccess are set to 664' ) ),
			'actions'     => '',
			'test'        => 'htaccess_perm_test',
		);

		if ( $root_htaccess_path ) {
			if ( ! (
			$this->user_can_read( $root_htaccess_path ) && $this->user_can_write( $root_htaccess_path ) && ! $this->user_can_execute( $root_htaccess_path ) && // 6
			$this->group_can_read( $root_htaccess_path ) && $this->group_can_write( $root_htaccess_path ) && ! $this->group_can_execute( $root_htaccess_path ) && // 6
			$this->other_can_read( $root_htaccess_path ) && ! $this->other_can_write( $root_htaccess_path ) && ! $this->other_can_execute( $root_htaccess_path )      // 4
			) ) {
				$result['status']         = 'recommended';
				$result['label']          = __( 'Review permissions on .htaccess' );
				$result['badge']['color'] = 'orange';
				$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s" target="_blank">WordPress Codex</a>.</p>', __( '664 is normally required and recommended for .htaccess files. Current permissions are' ), $this->get_permissions( $root_htaccess_path ), esc_url( 'https://wordpress.org/support/article/changing-file-permissions/#htaccess-permissions' ) );
				$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your .htaccess is:' ), esc_url( $root_htaccess_path ) );
			}
		}

		return $result;
	}

	/**
	 * File permission test callback for uploads directory permissions
	 *
	 * @return array
	 */
	function uploads_perm_test_callback() {

		$wp_upload_dir = wp_upload_dir();
		$wp_upload_dir = $wp_upload_dir['basedir'];

		$result = array(
			'label'       => __( 'Permissions for uploads directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on uploads directory are set to 755' ) ),
			'actions'     => '',
			'test'        => 'uploads_perm_test',
		);

		if ( ! (
			$this->user_can_read( $wp_upload_dir ) && $this->user_can_write( $wp_upload_dir ) && $this->user_can_execute( $wp_upload_dir ) && // 7
			$this->group_can_read( $wp_upload_dir ) && ! $this->group_can_write( $wp_upload_dir ) && $this->group_can_execute( $wp_upload_dir ) && // 5
			$this->other_can_read( $wp_upload_dir ) && ! $this->other_can_write( $wp_upload_dir ) && $this->other_can_execute( $wp_upload_dir )     // 5
			 ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on uploads directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code>.</p>', __( '755 permissions are recomended. Current permissions are' ), $this->get_permissions( $wp_upload_dir ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your uploads directory is:' ), esc_url( $wp_upload_dir ) );
		}

		return $result;
	}

	/**
	 * Given a path, checks if user_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function user_can_read( $path ) {

		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}
		$perms = substr( decoct( fileperms( $path ) ), -3 );

		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -3, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 0, 1 );
		return intval( $user_perms );
	}

	/**
	 * Given a path, checks if user_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function user_can_write( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -3, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 1, 1 );
		return intval( $user_perms );
	}

	/**
	 * Given a path, checks if user_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function user_can_execute( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -3, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 2, 1 );
		return intval( $user_perms );
	}

	/**
	 * Given a path, checks if group_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function group_can_read( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$group_perms = substr( $perms, -2, 1 );
		$group_perms = decbin( $group_perms );
		$group_perms = substr( $group_perms, 0, 1 );
		return intval( $group_perms );
	}

	/**
	 * Given a path, checks if group_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function group_can_write( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$group_perms = substr( $perms, -2, 1 );
		$group_perms = decbin( $group_perms );
		$group_perms = substr( $group_perms, 1, 1 );
		return intval( $group_perms );
	}

	/**
	 * Given a path, checks if group_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function group_can_execute( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$group_perms = substr( $perms, -2, 1 );
		$group_perms = decbin( $group_perms );
		$group_perms = substr( $group_perms, 2, 1 );
		return intval( $group_perms );
	}

	/**
	 * Given a path, checks if other_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function other_can_read( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$other_perms = substr( $perms, -1, 1 );
		$other_perms = decbin( $other_perms );
		$other_perms = substr( $other_perms, 0, 1 );
		return intval( $other_perms );
	}

	/**
	 * Given a path, checks if other_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function other_can_write( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$other_perms = substr( $perms, -1, 1 );
		$other_perms = decbin( $other_perms );
		$other_perms = substr( $other_perms, 1, 1 );
		return intval( $other_perms );
	}

	/**
	 * Given a path, checks if other_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function other_can_execute( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), -3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$other_perms = substr( $perms, -1, 1 );
		$other_perms = decbin( $other_perms );
		$other_perms = substr( $other_perms, 2, 1 );
		return intval( $other_perms );
	}

	/**
	 * Get file permissions in octal notation
	 *
	 * @param [type] $path
	 * @return string permissions
	 */
	function get_permissions( $path ) {
		return substr( sprintf( '%o', fileperms( $path ) ), -3 );
	}

	/**
	 * Return the absolute path of root .htaccess if it exists
	 *
	 * @return string path
	 */
	function get_htaccess_path() {
		if ( file_exists( ABSPATH . '.htaccess' ) ) {
			return ABSPATH . '.htaccess';
		}
	}



}

function malCure_security_suite() {
	return malCure_security_suite::get_instance();
}

malCure_security_suite();
