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
		add_filter( 'site_status_tests', array( $this, 'admin_user_test' ) );
	}

	function admin_user_test( $tests ) {
		$tests['direct']['admin_test'] = array(
			'label' => __( 'Admin user test' ),
			'test'  => array( $this, 'admin_user_test_callback' ),
		);
		return $tests;
	}

	function admin_user_test_callback() {
		$result = array(
			'label'       => __( 'No access for admin user account' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				__( 'Admin user doesn\'t exist.' )
			),
			'actions'     => '',
			'test'        => 'admin_test',
		);

		if ( get_user_by( 'login', 'admin' ) ) {
			$result['status']         = 'recommended';
			$result['label']          = __( 'Admin user exists' );
			$result['badge']['color'] = 'orange';
			$result['description']    = sprintf(
				'<p>%s</p>',
				__( 'A user named admin exists on your site. Many attacks target this user ID.' )
			);
			$result['actions']       .= sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'users.php' ) ),
				__( 'Remove Admin User' )
			);
		}

		return $result;
	}

	function admin_menu() {
		add_options_page( 'malCure Security', 'malCure', 'manage_options', 'malcure', array( $this, 'page' ) );
	}

	function page() {
		?>
		<div class="wrap">
		<h1>malCure Security</h1>
		<h2>SSL Support</h2>
		<p><strong>SSL:</strong> <?php echo $this->get_ssl_status(); ?></p>
		<h2>File-System Permissions</h2>
		<?php
		$this->show_file_permissions();
		?>
		<h2>Hidden Files & Folders</h2>
		<?php
		$this->get_hidden();
		?>
		</div>
		<?php
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

	function show_file_permissions() {
		$perms = $this->get_file_permissions();
		if ( is_array( $perms ) ) {
			echo '<table class="malcure_file_permissions">';
			echo '<tr><th>Path</th><th>Permissions</th></tr>';
			foreach ( $perms as $label => $perm ) {
				echo '<tr><td>' . $label . '</td><td>' . $perm . '</td></tr>';
			}
			echo '</table>';
		}
	}

	function get_file_permissions() {
		$paths = $this->get_critical_paths();

		$perms = array();

		foreach ( $paths as $label => $path ) {
			$perms[ $label ] = substr( sprintf( '%o', fileperms( $path ) ), -4 );
		}
		return $perms;
	}

	function get_critical_paths() {

		$paths = array();

		$paths['/'] = ABSPATH;

		$core_admin_path                                        = ABSPATH . 'wp-admin';
		$paths[ str_replace( ABSPATH, '', $core_admin_path )  ] = $core_admin_path;

		$core_content_path                                        = WP_CONTENT_DIR;
		$paths[ str_replace( ABSPATH, '', $core_content_path )  ] = $core_content_path;

		$core_inc_path  = ABSPATH . WPINC;
		$paths[ WPINC ] = $core_inc_path;

		$wp_upload_dir                                       = wp_upload_dir();
		$wp_upload_dir                                       = $wp_upload_dir['basedir'];
		$paths[ str_replace( ABSPATH, '', $wp_upload_dir ) ] = $wp_upload_dir;

		$core_config_path = $this->get_config_path();
		if ( $core_config_path ) {
			$paths[ str_replace( ABSPATH, '', $core_config_path ) ] = $core_config_path;
		}

		$core_htaccess_path = $this->get_htaccess_path();
		if ( $core_htaccess_path ) {
			$paths[ str_replace( ABSPATH, '', $core_htaccess_path ) ] = $core_htaccess_path;
		}

		return $paths;

	}

	function get_config_path() {

		$config_path = '';
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {

			/** The config file resides in ABSPATH */
			$config_path = ABSPATH . 'wp-config.php';

		} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {

			/** The config file resides one level above ABSPATH but is not part of another installation */
			$config_path = dirname( ABSPATH ) . '/wp-config.php';

		}

		return $config_path;

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
