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

final class malCure {

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
		// add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'site_status_tests', array( $this, 'malcure_security_tests' ) );
	}

	function malcure_security_tests( $tests ) {

		// Test if admin user exists
		$tests['direct']['admin_user_test'] = array(
			'label' => __( 'Admin user test' ),
			'test'  => array( $this, 'admin_user_test_callback' ),
		);

		$tests['direct']['abspath_perm_test'] = array(
			'label' => __( 'Permissions of wp-config.php' ),
			'test'  => array( $this, 'abspath_perm_test_callback' ),
		);

		$tests['direct']['wp_config_perm_test'] = array(
			'label' => __( 'Permissions of wp-config.php' ),
			'test'  => array( $this, 'wp_config_perm_test_callback' ),
		);

		$tests['direct']['htaccess_perm_test'] = array(
			'label' => __( 'Permissions of .htaccess' ),
			'test'  => array( $this, 'htaccess_perm_test_callback' ),
		);

		$tests['direct']['wp_admin_perm_test'] = array(
			'label' => __( 'Permissions of .htaccess' ),
			'test'  => array( $this, 'wp_admin_perm_test_callback' ),
		);

		$tests['direct']['wp_content_perm_test'] = array(
			'label' => __( 'Permissions of wp-content directory' ),
			'test'  => array( $this, 'wp_content_perm_test_callback' ),
		);

		$tests['direct']['wp_includes_perm_test'] = array(
			'label' => __( 'Permissions of wp-includes directory' ),
			'test'  => array( $this, 'wp_includes_perm_test_callback' ),
		);

		$tests['direct']['themes_perm_test'] = array(
			'label' => __( 'Permissions of themes directory' ),
			'test'  => array( $this, 'themes_perm_test_callback' ),
		);

		// /wp-includes/
		// /wp-content/themes/
		// /wp-content/plugins/

		return $tests;
	}

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
			$result['description']    = sprintf( '<p>%s</p>', __( 'A user named admin exists on your site. Many attacks target this user ID.' ) );
			$result['actions']       .= sprintf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'users.php' ) ), __( 'Remove Admin User' ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for WordPress installation directory
	 *
	 * @return array
	 */
	function abspath_perm_test_callback() {
		$abs_path = $this->get_permissions( ABSPATH );

		$result = array(
			'label'       => __( 'Permissions for WordPress installation directory' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions for WordPress installation directory are set to -755' ) ),
			'actions'     => '',
			'test'        => 'abspath_perm_test',
		);

		if ( ! ( ! $this->group_can_write( $abs_path ) &&
			 ! $this->world_can_write( $abs_path )
		) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Set permissions on the WordPress installation directory to 755' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s %s <a href="https://wordpress.org/support/article/hardening-wordpress/#file-permissions" target="_blank">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify WordPress installation directory to infect the website. Current permissions are' ), $abs_path_perms );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Your WordPress installation directory is located at:' ), esc_url( ABSPATH ) );
		}

		if ( preg_match( '/777$/', $abs_path_perms ) ) {
			$result['status']         = 'critical';
			$result['label']          = __( 'Set permissions on the WordPress installation directory to 0755' );
			$result['badge']['color'] = 'red';
			$result['description']    = sprintf( '<p>%s %s <a href="https://wordpress.org/support/article/hardening-wordpress/#file-permissions" target="_blank">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify WordPress installation directory to infect the website. Current permissions are' ), $abs_path_perms );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Your WordPress installation directory is located at:' ), esc_url( ABSPATH ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-config.php file
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

		if ( ! ( ! $this->user_can_write( $config_path ) && ! $this->user_can_execute( $config_path ) &&
			! $this->group_can_write( $config_path ) && ! $this->group_can_execute( $config_path ) &&
			! $this->world_can_read( $config_path ) && ! $this->world_can_write( $config_path ) && ! $this->world_can_execute( $config_path )
		) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on wp-config.php' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s" target="_blank">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify wp-config.php to infect the website. Current permissions are' ), $config_path_perms, esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your wp-config.php is:' ), esc_url( $config_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for root .htaccess file
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
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on .htaccess are set to 440' ) ),
			'actions'     => '',
			'test'        => 'htaccess_perm_test',
		);

		if ( $root_htaccess_path ) {
			if ( ! (
			! $this->group_can_write( $root_htaccess_path ) &&
			! $this->world_can_write( $root_htaccess_path )
			) ) {
				$result['status']         = 'recommended';
				$result['label']          = __( 'Insecure permissions on .htaccess' );
				$result['badge']['color'] = 'orange';
				$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s" target="_blank">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify .htaccess to infect the website. Current permissions are' ), $root_htaccess_path_perms, esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
				$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your .htaccess is:' ), esc_url( $root_htaccess_path ) );
			}
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-admin directory
	 *
	 * @return array
	 */
	function wp_admin_perm_test_callback() {

		$core_admin_path = ABSPATH . 'wp-admin';

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

		if ( ! ( ! $this->group_can_write( $core_admin_path ) &&
			! $this->world_can_write( $core_admin_path )
			) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on wp-admin directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify .htaccess to infect the website. Current permissions are' ), $root_htaccess_path_perms, esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your .htaccess is:' ), esc_url( $root_htaccess_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-content directory
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

		if ( ! ( ! $this->world_can_write( $core_content_path ) ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on wp-admin directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify .htaccess to infect the website. Current permissions are' ), $root_htaccess_path_perms, esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your .htaccess is:' ), esc_url( $root_htaccess_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-content directory
	 *
	 * @return array
	 */
	function wp_includes_perm_test_callback() {

		$core_inc_path = ABSPATH . WPINC;

		$result = array(
			'label'       => __( 'Permissions for wp-content' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'malCure Security Suite' ),
				'color' => 'blue',
			),
			'description' => sprintf( '<p>%s</p>', __( 'Permissions on wp-content directory are set to 755' ) ),
			'actions'     => '',
			'test'        => 'wp_includes_perm_test',
		);

		if ( ! ( ! $this->group_can_write( $core_inc_path ) &&
		! $this->world_can_write( $core_inc_path )
		) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on wp-admin directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify .htaccess to infect the website. Current permissions are' ), $root_htaccess_path_perms, esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your .htaccess is:' ), esc_url( $root_htaccess_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-content directory
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

		if ( ! ( ! $this->world_can_write( $theme_root_path ) ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on themes directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify .htaccess to infect the website. Current permissions are' ), $theme_root_path, esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your .htaccess is:' ), esc_url( $theme_root_path ) );
		}

		return $result;
	}

	/**
	 * File permission test callback for wp-content directory
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
			 ! $this->group_can_write( $plugin_root_path ) &&
			 ! $this->world_can_write( $plugin_root_path ) 
			 ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Insecure permissions on plugins directory' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf( '<p>%s <code>%s</code> <a href="%s">WordPress Codex</a>.</p>', __( 'Unauthorised users may modify .htaccess to infect the website. Current permissions are' ), $plugin_root_path, esc_url( 'https://wordpress.org/support/article/hardening-wordpress/#file-permissions' ) );
			$result['actions']       .= sprintf( '<p>%s <code>%s</code>.</p>', __( 'Path to your .htaccess is:' ), esc_url( $plugin_root_path ) );
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

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -3, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 0, 1 );
		return int_val( $user_perms );
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

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -3, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 1, 1 );
		return int_val( $user_perms );
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

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -3, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 2, 1 );
		return int_val( $user_perms );
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

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -2, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 0, 1 );
		return int_val( $user_perms );
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

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -2, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 1, 1 );
		return int_val( $user_perms );
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

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -2, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 2, 1 );
		return int_val( $user_perms );
	}

	/**
	 * Given a path, checks if world_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function world_can_read( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -1, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 0, 1 );
		return int_val( $user_perms );
	}

	/**
	 * Given a path, checks if world_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function world_can_write( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -1, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 1, 1 );
		return int_val( $user_perms );
	}

	/**
	 * Given a path, checks if world_can_[permission]
	 *
	 * @param [string] $path
	 * @return bool
	 */
	function world_can_execute( $path ) {
		if ( empty( $path ) || ! file_exists( $path ) ) {
			return;
		}

		$perms = substr( decoct( fileperms( $path ) ), 3 );
		if ( empty( $perms ) || strlen( $perms ) < 3 ) {
			return;
		}
		$user_perms = substr( $perms, -1, 1 );
		$user_perms = decbin( $user_perms );
		$user_perms = substr( $user_perms, 2, 1 );
		return int_val( $user_perms );
	}

	function get_hidden() {
		$files = $this->get_all_files();
		$files = array_filter(
			$files,
			function( $p ) {
				return ( preg_match( '/^\.+/', basename( $p ) ) );
			}
		);
		$this->llog( $files );
	}

	function get_all_files( $path = false ) {
		if ( ! $path ) {
			$path = ABSPATH;
			if ( empty( $path ) ) {
				return array();
			}
			$path = untrailingslashit( $path );
		}

		$children = @scandir( $path );
		if ( is_array( $children ) ) {
			$children = array_diff( $children, array( '..', '.' ) );
			$dirs     = array();
			$files    = array();
			foreach ( $children  as $child ) {
				$target = untrailingslashit( $path ) . DIRECTORY_SEPARATOR . $child;
				if ( is_dir( $target ) ) {
					$elements = $this->get_all_files( $target );
					if ( $elements ) { // check for read/write errors
						foreach ( $elements as $element ) {
							$files[] = $element;
						}
					}
				}
				if ( is_file( $target ) ) {
					$files[] = $target;
				}
			}
			return $files;
		}

	}

	function get_ssl_status() {
		$status   = '';
		$wp_url   = get_bloginfo( 'wpurl' );
		$site_url = get_bloginfo( 'url' );
		if ( preg_match( '/^https/', $wp_url ) && preg_match( '/^https/', $site_url ) ) {
			$status = 'Site is using SSL.';
		} else {
			$status = 'Parts of site are using SSL.';
		}
		if ( ! $this->test_ssl_redirect( $wp_url ) ) {
			$status .= ' non-SSL version of WordPress address is not redirected to SSL.';
		}
		if ( ! $this->test_ssl_redirect( $site_url ) ) {
			$status .= ' non-SSL version of Site address is not redirected to SSL.';
		}
		return $status;
	}

	function test_ssl_redirect( $url ) {

		$nonssl_url = $this->get_nonssl_url( $url );

		$redir_nonssl_url = wp_remote_request( $nonssl_url, array( 'redirection' => 0 ) );

		if ( ! is_wp_error( $redir_nonssl_url ) && preg_match( '/^https\:\/\//', wp_remote_retrieve_header( $redir_nonssl_url, 'location' ) ) ) { // $headers
			return true;
		}
	}

	function get_nonssl_url( $url ) {
		$parsed_url = parse_url( $url );
		$scheme     = 'http://';
		$host       = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port       = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user       = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass       = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass       = ( $user || $pass ) ? "$pass@" : '';
		$path       = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$query      = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment   = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	}


	function get_permissions( $path ) {
		return substr( sprintf( '%o', fileperms( $path ) ), -4 );
	}

	function get_critical_paths() {

		$paths = array();

		$paths[ str_replace( ABSPATH, '', $core_admin_path )  ] = $core_admin_path;

		$core_content_path                                        = WP_CONTENT_DIR;
		$paths[ str_replace( ABSPATH, '', $core_content_path )  ] = $core_content_path;

		$wp_upload_dir                                       = wp_upload_dir();
		$wp_upload_dir                                       = $wp_upload_dir['basedir'];
		$paths[ str_replace( ABSPATH, '', $wp_upload_dir ) ] = $wp_upload_dir;

		$core_config_path = $this->get_config_path();
		if ( $core_config_path ) {
			$paths[ str_replace( ABSPATH, '', $core_config_path ) ] = $core_config_path;
		}

		return $paths;

	}

	function get_htaccess_path() {
		if ( file_exists( ABSPATH . '.htaccess' ) ) {
			return ABSPATH . '.htaccess';
		}
	}

	function llog( $str ) {
		echo '<pre>' . print_r( $str, 1 ) . '</pre>';
	}

}

function malCure() {
	return malCure::get_instance();
}

malCure();
