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
 * Description: malCurity Security Suite shows you security issues on your WordPress installation.
 * Version:     0.1
 * Author:      malCure
 * Author URI:  https://malcure.com
 * Text Domain: malcure
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Plugin URI:  https://malcure.com/
 */

final class malCure_security_suite {

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
		add_filter( 'site_status_tests', array( $this, 'malcure_security_tests' ) );
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
		$abs_path = ABSPATH;
		$abs_path_perms = $this->get_permissions($abs_path);
		$result = array(
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
			$result['label']          = __( 'Insecure permissions on wp-admin directory' );
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
			$result['label']          = __( 'Insecure permissions on wp-includes directory' );
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
			$result['label']          = __( 'Insecure permissions on wp-content directory' );
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

		$theme_root_path = get_theme_root();

		$result = array(
			'label'       => __( 'Permissions for themes directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on themes directory are set to 775' ) ),
			'actions'     => '',
			'test'        => 'themes_perm_test',
		);

		if ( ! (
			$this->user_can_read( $theme_root_path ) && $this->user_can_write( $theme_root_path ) && $this->user_can_execute( $theme_root_path ) && // 7
			$this->group_can_read( $theme_root_path ) && $this->group_can_write( $theme_root_path ) && $this->group_can_execute( $theme_root_path ) && // 7
			$this->other_can_read( $theme_root_path ) && ! $this->other_can_write( $theme_root_path ) && $this->other_can_execute( $theme_root_path )   // 5
		 ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on themes directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'If you want to use the built-in theme editor, all files need to be writable by the web server process (775). If you do not want to use the built-in theme editor, all files can be writable only by your user account (755). Current permissions are' ), $this->get_permissions( $theme_root_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your themes directory is:' ), esc_url( $theme_root_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for plugins directory permissions
	 *
	 * @return array
	 */
	function plugins_perm_test_callback() {

		$plugin_root_path = WP_PLUGIN_DIR;

		$result = array(
			'label'       => __( 'Permissions for plugins directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on plugins directory are set to 755' ) ),
			'actions'     => '',
			'test'        => 'themes_perm_test',
		);

		if ( ! (
			$this->user_can_read( $plugin_root_path ) && $this->user_can_write( $plugin_root_path ) && $this->user_can_execute( $plugin_root_path ) && // 7
			$this->group_can_read( $plugin_root_path ) && ! $this->group_can_write( $plugin_root_path ) && $this->group_can_execute( $plugin_root_path ) && // 5
			$this->other_can_read( $plugin_root_path ) && ! $this->other_can_write( $plugin_root_path ) && $this->other_can_execute( $plugin_root_path )    // 5
			 ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on plugins directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'All files should be writable only by your user account (755). Current permissions are' ), $this->get_permissions( $plugin_root_path ), esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your plugins directory is:' ), esc_url( $plugin_root_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-config.php file permissions
	 *
	 * @return array
	 */
	function wp_config_perm_test_callback() {

		$config_path = '';
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			$config_path = ABSPATH . 'wp-config.php'; // The config file resides in ABSPATH
		} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			$config_path = dirname( ABSPATH ) . '/wp-config.php'; // The config file resides one level above ABSPATH but is not part of another installation
		}

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
			$result['label']          = __( 'Insecure permissions on wp-config.php' );
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
				$result['label']          = __( 'Insecure permissions on .htaccess' );
				$result['badge']['color'] = 'orange';
				$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s" target="_blank">WordPress Codex</a>.</p>', __( '644 is normally required and recommended for .htaccess files. Current permissions are' ), $this->get_permissions( $root_htaccess_path ), esc_url( 'https://wordpress.org/support/article/changing-file-permissions/#htaccess-permissions' ) );
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

	/**
	 * Debug function used for testing
	 *
	 * @param [type] $str
	 * @return void
	 */
	function llog( $str ) {
		echo '<pre>' . print_r( $str, 1 ) . '</pre>';
	}

}

function malCure_security_suite() {
	return malCure_security_suite::get_instance();
}

malCure_security_suite();
