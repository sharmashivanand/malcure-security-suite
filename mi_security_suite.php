<?php
/**
 * MI Security Suite
 *
 * @package     MI Security Suite
 * @author      Malware Intercept
 * @copyright   2021 malwareintercept.com
 * @license     MIT
 *
 * @wordpress-plugin
 * Plugin Name: MI Security Suite
 * Description: MI Security Suite helps you lock down and secure your WordPress site.
 * Version:     0.5
 * Author:      Malware Intercept
 * Author URI:  https://malwareintercept.com
 * Text Domain: malcure-security-suite
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Plugin URI:  https://malwareintercept.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NSMI_GOD' ) ) {
	define( 'NSMI_GOD', 'activate_plugins' );
}

define( 'NSMI_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'NSMI_FILE', __FILE__ );
define( 'NSMI_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'NSMI_API_EP', 'https://wp-malware-removal.com/' );
define( 'NSMI_ID', 134 );

final class MI_security_suite {
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
		$GLOBALS[ get_class( $this ) ] = array();
		$this->dir                     = trailingslashit( plugin_dir_path( __FILE__ ) );
		$this->url                     = trailingslashit( plugin_dir_url( __FILE__ ) );
		include_once $this->dir . 'lib/utils.php';
		include_once $this->dir . 'classes/general_features.php';
		if ( nsmi_utils::is_registered() ) {
			include_once $this->dir . 'classes/integrity.php';
			include_once $this->dir . 'classes/malware_scanner.php';
			include_once $this->dir . 'classes/salt-shuffler.php';
		}
		add_filter( 'site_status_tests', array( $this, 'security_tests' ) );
		add_action( 'admin_init', array( $this, 'hook_meta_boxes' ) );
		add_action( 'admin_menu', array( $this, 'settings_menu' ) );
		add_action( 'admin_head', array( $this, 'admin_inline_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'plugin_res' ) );
		add_action( 'admin_footer', array( $this, 'footer_scripts' ) );
		do_action( get_class( $this ) . '_' . __FUNCTION__ );

		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		add_action( 'wp_ajax_nsmi_ajax', array( $this, 'nsmi_ajax' ) );
		add_action( 'wp_ajax_nopriv_nsmi_ajax', '__return_false' );
	}

	function hook_meta_boxes() {
		if ( ! empty( $this->pagehook ) ) {
			add_action( 'load-' . $this->pagehook, array( $this, 'add_meta_boxes' ) );
			add_action( 'load-' . $this->pagehook, array( $this, 'add_admin_scripts' ) );
		}
	}

	function add_admin_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
	}

	function add_meta_boxes() {
		do_action( get_class( $this ) . '_' . __FUNCTION__ );
	}

	function do_meta_box_callback() {
		echo 'do something here';
	}

	function admin_body_class( $classes ) {
		$color_scheme = nsmi_utils::get_setting( 'color_scheme' );
		if ( ! empty( $color_scheme ) ) {
			$classes .= ' ' . 'nsmi_' . $color_scheme;
		}
		return $classes;
	}

	function settings_menu() {
		$this->pagehook                            = add_menu_page(
			'MI Security Suite', // page_title
			'MI Security Suite', // menu_title
			NSMI_GOD,   // capability
			'_nsmi',  // menu_slug
			array( $this, 'settings_page' ), // function
			$this->url . 'assets/icon-dark-trans.svg', // icon_url
			79
		);
		$GLOBALS[ get_class( $this ) ]['pagehook'] = $this->pagehook;
		do_action( 'nsmi_settings_menu' );
	}

	function settings_page() {
		$title = 'MI Security Suite';
		?>
		<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="container">
			<?php
			echo '<div id="nsmi_branding" class="nsmi_branding" >' . $this->render_branding() . '</div>';
			?>
			</div>
			<div id="poststuff">
				<div class="metabox-holder columns-2" id="post-body">
					<div class="postbox-container" id="post-body-content">
						<?php do_meta_boxes( $this->pagehook, 'main', null ); ?>
					</div>
					<div id="postbox-container-1" class="postbox-container">
						<?php do_meta_boxes( $this->pagehook, 'side', null ); ?>
					</div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
			$('#nsmi_color_scheme').on('change',function(){
				$classes = $('body').attr('class');
				$classes = $classes.split(" ");
				$classes = $classes.filter(function(value) {
    				return ((value.replaceAll(/\s+/ig,'')).length != 0 ) && ( ! value.match(/^nsmi_/ig) );
				});
				$classes.push('nsmi_' + this.value);
				$classes = $classes.join(' ');
				$classes = $('body').attr('class',$classes);
				ajax_request( 'set_color_scheme', this.value, 'color_scheme_cb' ); 
			});
		});

		function color_scheme_cb($response){
			console.dir($response);
		}

		function ajax_request(request, data, callback){
			nsmi_ajax_payload = {
				nsmi_ajax_nonce: '<?php echo wp_create_nonce( 'nsmi_ajax' ); ?>',
				action: "nsmi_ajax",
				payload: {
					data: data,
					request: request
				} 
			};
			$ = jQuery.noConflict();
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: nsmi_ajax_payload,
				success: function(response_data, textStatus, jqXHR) {
					console.dir(response_data);
					if ((typeof response_data) != 'object') { // is the server not sending us JSON?
					}
					if (response_data.hasOwnProperty('success') && response_data.success) { // ajax request has a success but we haven't tested if success is true or false
					} else { // perhaps this is just JSON without a success object
					}
				},
				error: function( jqXHR, textStatus, errorThrown){},
				complete: function(jqXHR_data, textStatus) { // use this since we need to run and catch regardless of success and failure
					window[callback](jqXHR_data);
				},
			});
		}
		</script>
		<?php
	}

	function nsmi_ajax() {
		check_ajax_referer( 'nsmi_ajax', 'nsmi_ajax_nonce' );
		if ( ! empty( $_REQUEST['payload'] ) && ! empty( $_REQUEST['payload']['request'] ) && method_exists( 'nsmi_utils', $_REQUEST['payload']['request'] ) ) {
			if ( ! empty( $_REQUEST['payload']['data'] ) ) {
				// $result = nsmi_utils::$_REQUEST['payload']['request']($_REQUEST['payload']['data']);
				$result = forward_static_call( array( nsmi_utils, $_REQUEST['payload']['request'] ), $_REQUEST['payload']['data'] );
			} else {
				$result = forward_static_call( array( nsmi_utils, $_REQUEST['payload']['request'] ) );
			}
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( 'Something went wrong.' );
		}

	}

	function settings_page_old() {
		?>
		<div class="wrap">
		<h1>MI Security Suite</h1>
			<div class="container">
			<?php
			echo '<div id="nsmi_branding" class="nsmi_branding" >' . $this->render_branding() . '</div>';
			if ( ! nsmi_utils::is_registered() ) {
				$current_user = wp_get_current_user();
				?>
				<h3>You have successfully installed MI Security Suite</h3>
				<p>Submit the following information to download free anti-virus definitions and Malcure rules.</p>
				<p><label><strong>First Name:</strong><br />
				<input type="text" id="nsmi_user_fname" name="nsmi_user_fname" value="<?php $current_user->user_firstname; ?>" /></label></p>
				<p><label><strong>Last Name:</strong><br />
				<input type="text" id="nsmi_user_lname" name="nsmi_user_lname" value="<?php $current_user->user_lastname; ?>" /></label></p>
				<p><label><strong>Email:</strong><br />
				<input type="text" id="nsmi_user_email" name="nsmi_user_email" value="" /></label></p>
				<p><small>We do not use this email address for any other purpose unless you opt-in to receive other mailings. You can turn off alerts in the options.</small></p>
				<a href="#" class="nsmi_action" id="nsmi_api_register_btn" role="button">Next&nbsp;&rarr;</a>
				<script type="text/javascript">
				jQuery(document).ready(function($){
					$("#nsmi_api_register_btn").click(function(){
						nsmi_api_register = {
							nsmi_api_register_nonce: '<?php echo wp_create_nonce( 'nsmi_api_register' ); ?>',
							action: "nsmi_api_register",
							user: {
								fn: $('#nsmi_user_fname').val(),
								ln: $('#nsmi_user_lname').val(),
								email: $('#nsmi_user_email').val(),
							}
						};
						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: nsmi_api_register,
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
			} else {
				echo '<input class="nsmi_action" value="Init Scan &rarr;" id="nsmi_trigger_scan" type="submit" />';
				?>
				<script type="text/javascript">
			jQuery(document).ready(function($){
				$("#nsmi_trigger_scan").click(function(){
					nsmi_trigger_scan = {
						nsmi_trigger_scan_nonce: '<?php echo wp_create_nonce( 'nsmi_trigger_scan' ); ?>',
						action: "nsmi_trigger_scan",
						cachebust: Date.now(),
						user: {
							id: <?php echo get_current_user_id(); ?>
						}
					};
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: nsmi_trigger_scan,
						complete: function(jqXHR, textStatus) {
							console.dir(jqXHR);
						},
						ssuccess: function(response) {
							if ((typeof response) != 'object') {
								response = JSON.parse( response );
							}
							if (response.hasOwnProperty('success')) {
								$("#nsmi_destroy_sessions").fadeTo("slow",1,);
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
				$nsmi_scanner = MI_Malware_Scanner::get_instance();
				?>
				<h2>Notice</h2>
				<p><strong>This plugin is meant for security experts to interpret the results and implement necessary measures as required. Here's the system status. For other features and functions please make your selection from the plugin-sub-menu from the left.</strong></p>
				<h2>System Status</h2>
				<?php $this->system_status(); ?>
				<?php
			}
			?>
			</div> <!-- / .container -->
		</div> <!-- / .wrap -->
		<?php
	}

	function admin_inline_style() {
		?>
		<style type="text/css">
		#toplevel_page__nsmi .wp-menu-image img {
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
		do_action( get_class( $this ) . '_' . __FUNCTION__ );
		if ( preg_match( '/_nsmi$/', $hook ) ) {
			wp_enqueue_style( 'mss-stylesheet', $this->url . 'assets/style.css', array(), filemtime( $this->dir . 'assets/style.css' ) );
			wp_enqueue_script( 'jquery' );
		}
	}

	function debug_menu() {
		add_submenu_page(
			'_nsmi',  // parent_slug
			'MI Debug', // page_title
			'MI Debug', // menu_title
			NSMI_GOD, // capability
			'debug_nsmi',
			array( $this, 'debug_nsmi_page' )
		);
	}

	function render_branding() {
		return '<img src="' . NSMI_URL . 'assets/logo-light-trans.svg" />';
	}

	function footer_scripts() {
		$screen = get_current_screen();
		if ( $screen->id == $this->pagehook ) {
			do_action( 'nsmi_admin_scripts' );
		}
	}
	function security_tests( $tests ) {
		$tests['direct']['abspath_perm_test']     = array(
			'label' => __( 'Permissions of WordPress installation directory' ),
			'test'  => array( $this, 'abspath_perm_test_callback' ),
		);
		$tests['direct']['wp_admin_perm_test']    = array(
			'label' => __( 'Permissions of wp-admin directory' ),
			'test'  => array( $this, 'wp_admin_perm_test_callback' ),
		);
		$tests['direct']['wp_includes_perm_test'] = array(
			'label' => __( 'Permissions of wp-includes directory' ),
			'test'  => array( $this, 'wp_includes_perm_test_callback' ),
		);
		$tests['direct']['wp_content_perm_test']  = array(
			'label' => __( 'Permissions of wp-content directory' ),
			'test'  => array( $this, 'wp_content_perm_test_callback' ),
		);
		$tests['direct']['themes_perm_test']      = array(
			'label' => __( 'Permissions of themes directory' ),
			'test'  => array( $this, 'themes_perm_test_callback' ),
		);
		$tests['direct']['plugins_perm_test']     = array(
			'label' => __( 'Permissions of plugins directory' ),
			'test'  => array( $this, 'plugins_perm_test_callback' ),
		);
		$tests['direct']['uploads_perm_test']     = array(
			'label' => __( 'Permissions of uploads directory' ),
			'test'  => array( $this, 'uploads_perm_test_callback' ),
		);
		$tests['direct']['wp_config_perm_test']   = array(
			'label' => __( 'Permissions of wp-config.php' ),
			'test'  => array( $this, 'wp_config_perm_test_callback' ),
		);
		$tests['direct']['htaccess_perm_test']    = array(
			'label' => __( 'Permissions of .htaccess' ),
			'test'  => array( $this, 'htaccess_perm_test_callback' ),
		);
		$tests['direct']['admin_user_test']       = array(
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
				'label' => __( 'MI Security Suite' ),
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
				'label' => __( 'MI Security Suite' ),
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
		$result          = array(
			'label'       => __( 'Permissions for wp-admin' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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
		$result        = array(
			'label'       => __( 'Permissions for wp-content' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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
		$result            = array(
			'label'       => __( 'Permissions for wp-content' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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
		$result          = array(
			'label'       => __( 'Permissions for themes directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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
		$result           = array(
			'label'       => __( 'Permissions for plugin files' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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
		$result      = array(
			'label'       => __( 'Permissions for wp-config.php' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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
		$result             = array(
			'label'       => __( 'Permissions for .htaccess' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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
		$result        = array(
			'label'       => __( 'Permissions for uploads directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'MI Security Suite' ),
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

function MI_security_suite() {
	return MI_security_suite::get_instance();
}

MI_security_suite();
