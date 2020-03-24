<?php

class malCure_Integrity {

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
		add_action( 'mss_settings_menu', array( $this, 'submenu_page' ) );
		// add_action( 'mss_admin_scripts', array( $this, 'js' ) );
		add_action( 'wp_ajax_mss_verify_integrity', array( $this, 'verify_integrity' ) );
		add_action( 'wp_ajax_nopriv_mss_verify_integrity', '__return_false' );
		add_action( 'upgrader_process_complete', array( $this, 'delete_checksums' ), 9999, 2 );

	}

	function delete_checksums() {
		delete_transient( 'malcure_checksums' );
	}

	function submenu_page() {
		add_submenu_page(
			'_mss',  // parent_slug
			'malCure WordPress Integrity Verifier', // page_title
			'Integrity Verifier', // menu_title
			MSS_GOD, // capability
			'integrity_mss',
			array( $this, 'integrity_mss_page' )
		);
	}

	function llog( $str ) {
		echo '<pre>' . print_r( $str, 1 ) . '</pre>';
	}

	function integrity_mss_page() {
		?>
		<div class="wrap">
		<h1>malCure WordPress Integrity Checker</h1>
			<div class="container">
			<?php
				// 'failed_checksums'
				// 'extra_files'
				// 'missing_files

			$results = $this->verify_checksums();
			// var_dump( $results );
			if ( ! empty( $results ) ) {
				if ( ! empty( $results['failed_checksums'] ) ) {
					echo '<h2>The following files failed checksum verification:</h2>';
					echo '<ul>';
					foreach ( $results['failed_checksums'] as $failed ) {
						echo '<li>' . $failed . '</li>';
					}
					echo '</ul>';
				}
				if ( ! empty( $results['extra_files'] ) ) {
					echo '<h2>The following files do not have a checksum:</h2>';
					echo '<ul>';
					foreach ( $results['extra_files'] as $extra ) {
						echo '<li>' . $extra . '</li>';
					}
					echo '</ul>';
				}
				if ( ! empty( $results['missing_files'] ) ) {
					echo '<h2>The following files are missing:</h2>';
					echo '<ul>';
					foreach ( $results['missing_files'] as $missing ) {
						echo '<li>' . $missing . '</li>';
					}
					echo '</ul>';
				}
			} else {
				echo '<h2>All WordPress integrity checks pass!</h2>';
			}
			?>
			</div>
		</div>
		<?php
	}

	function js() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#mss_verify_integrity').click(function(e){
				e.preventDefault();
				mss_verify_integrity = {
					mss_verify_integrity_nonce: '<?php echo wp_create_nonce( 'mss_verify_integrity' ); ?>',
						action: "mss_verify_integrity",
						cachebust: Math.floor((new Date()).getTime() / 1000),
					};
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: mss_verify_integrity,
					complete: function(jqXHR, textStatus) {
						console.log('complete');
						console.log('jqXHR');
						console.log(jqXHR);
						console.log('textStatus');
						console.log(textStatus);
					},
					success: function(data,textStatus,jqXHR) {
						console.log('success');
						console.dir('data');
						console.dir(data);
						console.dir('textStatus');
						console.dir(textStatus);
						console.dir('jqXHR');
						console.dir(jqXHR);
						if ((typeof data) != 'object') {
							console.log('invalid data');
							data = JSON.parse(data);
						}
						if (data.hasOwnProperty('success') && data.success) {
							$('#mss_verify_integrity_status').html('<p class="mss_success">'+data.data+'</p>');
							console.log('WordPress successfully executed the requested action.');
						} else {
							$('#mss_verify_integrity_status').html('<p class="mss_error">'+data.data+'</p>');
							console.log('WordPress failed to execute the requested action.');
						}
					}, // success
					error: function(jqXHR,textStatus,errorThrown) {
						console.log('error');
						console.dir('jqXHR');
						console.dir(jqXHR);
						console.dir('textStatus');
						console.dir(textStatus);
						console.dir('errorThrown');
						console.dir(errorThrown);
						if(errorThrown.length) {
							$('#mss_verify_integrity_status').html('<p class="mss_error">'+ errorThrown + '</p>');
						}
						else {
							$('#mss_verify_integrity_status').html('<p class="mss_error">Failed to execute the requested action.</p>');
						}
					}
				}); // ajax post
				return false;
			});
		});
		<?php
	}

	function get_checksums( $cached = true ) {
		$checksums = $cached ? get_transient( 'malcure_checksums' ) : false;
		if ( ! $checksums ) {
			global $wp_version;
			$checksums = get_core_checksums( $wp_version, get_locale() );
			if ( ! $checksums ) { // get_core_checksums failed
				$checksums = array();
			}
			$plugin_checksums = $this->get_plugin_checksums();
			if ( $plugin_checksums ) {
				$checksums = array_merge( $checksums, $plugin_checksums );
			}
			if ( $checksums ) {
				set_transient( 'malcure_checksums', $checksums, 7 * DAY_IN_SECONDS );
				return $checksums;
			}
			return array();
		} else {
			return $checksums;
		}
	}

	function get_plugin_checksums() {
		$missing          = array();
		$all_plugins      = get_plugins();
		$install_path     = get_home_path();
		$plugin_checksums = array();
		foreach ( $all_plugins as $key => $value ) {
			if ( false !== strpos( $key, '/' ) ) { // plugin has to be inside a directory. currently drop in plugins are not supported
				$plugin_file  = trailingslashit( dirname( $this->dir ) ) . $key;
				$plugin_file  = str_replace( $install_path, '', $plugin_file );
				$checksum_url = 'https://downloads.wordpress.org/plugin-checksums/' . dirname( $key ) . '/' . $value['Version'] . '.json';
				$checksum     = wp_safe_remote_get( $checksum_url );
				if ( is_wp_error( $checksum ) ) {
					continue;
				}

				if ( '200' != wp_remote_retrieve_response_code( $checksum ) ) {
					if ( '404' == wp_remote_retrieve_response_code( $checksum ) ) {
						$missing[ $key ] = array( 'Version' => $value['Version'] );
					}
					continue;
				}
				$checksum = wp_remote_retrieve_body( $checksum );
				$checksum = json_decode( $checksum, true );
				if ( ! is_null( $checksum ) && ! empty( $checksum['files'] ) ) {
					$checksum = $checksum['files'];
					foreach ( $checksum as $file => $checksums ) {
						$plugin_checksums[ trailingslashit( dirname( $plugin_file ) ) . $file ] = $checksums['md5'];
					}
				}
			} else {
			}
		}

		return $plugin_checksums;
	}

	function verify_integrity() {
		// should return missing, extra and mismatches
		wp_send_json_success( $this->verify_checksums() );
		wp_send_json_success( $this->get_checksums() );
		wp_send_json_success( $this->get_all_files() );
	}

	/**
	 * Verify checksums if we have a checksum for a file
	 *
	 * @return void
	 */
	function verify_checksums() {
		$local_files  = $this->get_all_files();
		$checksums    = $this->get_checksums();
		$install_path = get_home_path();
		$failed_files = array(
			'failed_checksums' => array(),
			'extra_files'      => array(),
			'missing_files'    => array(),
		);

		foreach ( $local_files as $local_file ) {
			$match_path = str_replace( $install_path, '', $local_file );
			if ( array_key_exists( $match_path, $checksums ) ) {
				if ( is_array( $checksums[ $match_path ] ) ) { // plugin readmes can have multiple md5 hashes since a readme.txt can be updated on svn without bumping the plugin version
					if ( ! in_array( md5_file( $local_file ), $checksums[ $match_path ] ) ) {
						$failed_files['failed_checksums'][] = $local_file;
					}
				} else {
					if ( $checksums[ $match_path ] != md5_file( $local_file ) ) {
						$failed_files['failed_checksums'][] = $local_file;
					}
				}
			} else { // we don't have checksum for this file.
				$failed_files['extra_files'][] = $local_file;
			}
		}

		// $this->llog( $checksums );
		// $this->llog( $local_files );

		foreach ( $checksums as $checksum => $value ) {
			$checksum = trailingslashit( $install_path ) . $checksum;
			// $this->llog($checksum);
			if ( ! in_array( $checksum, $local_files ) ) {
				$failed_files['missing_files'][] = $checksum;
			}
		}
		return $failed_files;
	}

	function get_all_files( $path = false ) {
		if ( ! $path ) {
			$path = get_home_path();
			if ( empty( $path ) ) {
				return array();
			}
			$path = untrailingslashit( $path );
		}

		if ( is_dir( $path ) ) {
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
	}
}

malCure_Integrity::get_instance();
