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

		add_action( 'mss_admin_scripts', array( $this, 'footer_scripts' ) );
		add_action( 'wp_ajax_mss_verify_integrity', array( $this, 'verify_integrity' ) );
		add_action( 'wp_ajax_nopriv_mss_verify_integrity', '__return_false' );
		add_action( 'upgrader_process_complete', array( $this, 'delete_checksums' ), 9999, 2 );

		add_action( 'malCure_security_suite_add_meta_boxes', array( $this, 'add_meta_boxes' ) );

	}

	function add_meta_boxes() {

		global $mss_integrity_results;

		add_meta_box( 'integrity_missing', 'Integrity: Missing Files', array( $this, 'meta_box_missing_files' ), $GLOBALS['malCure_security_suite']['pagehook'], 'main' );
		add_meta_box( 'integrity_failed', 'Integrity: Failed Checksums', array( $this, 'meta_box_failed_checksums' ), $GLOBALS['malCure_security_suite']['pagehook'], 'main' );
		add_meta_box( 'integrity_extra', 'Integrity: Missing Checksums', array( $this, 'meta_box_extra_files' ), $GLOBALS['malCure_security_suite']['pagehook'], 'main' );

	}

	function meta_box_missing_files() {
		global $mss_integrity_results;

		if ( ! empty( $mss_integrity_results ) ) {
			if ( ! empty( $mss_integrity_results['missing_files'] ) ) {
				echo '<p class="mss_notice"><strong>The following files are missing and form a part of the WordPress distribution and / or the installed plugin(s). This could indicate a broken WordPress install or broken plugin(s).</strong></p>';
				echo '<ul>';
				foreach ( $mss_integrity_results['missing_files'] as $missing ) {
					echo '<li>' . $missing . '</li>';
				}
			} else {
				echo '<h2 id="mss_integrity_missing">All core WordPress files are present.</h2>';
			}
		} else {

			submit_button( 'Show Missing Files', 'primary', 'mss_integrity_missing_files', true );
			echo '<div class="integrity_response"></div>';
		}
	}

	function meta_box_failed_checksums() {
		global $mss_integrity_results;

		if ( ! empty( $mss_integrity_results['failed_checksums'] ) ) {
			echo '<p class="mss_notice"><strong>The following files failed checksum verification.</strong></p>';
			echo '<ul>';
			foreach ( $mss_integrity_results['failed_checksums'] as $failed ) {
				echo '<li>' . $failed . '</li>';
			}
		} else {
			submit_button( 'Show Failed Checksums', 'primary', 'mss_integrity_failed_checksums', true );
			echo '<div class="integrity_response"></div>';
		}

	}

	function meta_box_extra_files() {
		global $mss_integrity_results;
		if ( ! empty( $mss_integrity_results['extra_files'] ) ) {
			echo '<p class="mss_notice"><strong>The following files do not have a checksum. It\'s possible that these files may be from premium plugins, themes or may not strictly be required (could even have been injected malware). Please review if you really need them.</strong></p>';
			echo '<ul>';
			foreach ( $mss_integrity_results['extra_files'] as $extra ) {
				echo '<li>' . $extra . '</li>';
			}
		} else {
			submit_button( 'Show Extra Files', 'primary', 'mss_integrity_extra_files', true );
			echo '<div class="integrity_response"></div>';

		}
	}

	function footer_scripts() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#mss_integrity_missing_files').click(function(e){
				make_integrity_request($(this).attr('id'),$(this).parents('.postbox').find('.integrity_response') );

				});
			$('#mss_integrity_failed_checksums').click(function(e){
				make_integrity_request($(this).attr('id'),$(this).parents('.postbox').find('.integrity_response') );

			});
			$('#mss_integrity_extra_files').click(function(e){
				make_integrity_request( $(this).attr('id'), $(this).parents('.postbox').find('.integrity_response') );

			});
		});

		function make_integrity_request(req,container){
			$ = jQuery.noConflict();


			mss_verify_integrity = {
					mss_verify_integrity_nonce: '<?php echo wp_create_nonce( 'mss_verify_integrity' ); ?>',
						action: "mss_verify_integrity",
						cachebust: Math.floor((new Date()).getTime() / 1000),
						request: req
					};
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: mss_verify_integrity,
					complete: function(jqXHR, textStatus) {},
					success: function(data,textStatus,jqXHR) {
						if ((typeof data) != 'object') {
							console.log('invalid data');
							data = JSON.parse(data);
						}
						if (data.hasOwnProperty('success') && data.success) {
							files = data.data;

							files = Object.values(files);

							if(files.length){								
								files = '<ol reversed class="mss_verify_integrity"><li>'+files.join('</li><li>')+'</li></ol>';
								$(container).html('<div class="mss_success" style="display:flex;">'+files+'</div>');
							}
							else {
								$(container).html('<p class="mss_success">No matches.</p>');
							}
							console.log('WordPress successfully executed the requested action.');
						} else {
							$(container).html('<p class="mss_error">'+data.data+'</p>');
							console.log('WordPress failed to execute the requested action.');
						}
					}, // success
					error: function(jqXHR,textStatus,errorThrown) {
						if(errorThrown.length) {
							$(container).html('<p class="mss_error">'+ errorThrown + '</p>');
						}
						else {
							$(container).html('<p class="mss_error">Request failed.</p>');
						}
					}
				}); // ajax post
		}
		</script>
		<?php
	}

	function verify_integrity() {
		check_ajax_referer( 'mss_verify_integrity', 'mss_verify_integrity_nonce' );
		$req = $_REQUEST['request'];

		$result = $this->verify_checksums();

		switch ( $req ) {
			case 'mss_integrity_extra_files':
				wp_send_json_success( $result['extra_files'] );
			case 'mss_integrity_failed_checksums':
				wp_send_json_success( $result['failed_checksums'] );
			case 'mss_integrity_missing_files':
				wp_send_json_success( $result['missing_files'] );
		}
		$req = $_REQUEST['mss_integrity_extra_files'];
		wp_send_json_success( $this->verify_checksums() );
		wp_send_json_success( $this->get_checksums() );
		wp_send_json_success( $this->get_all_files() );
	}

	function meta_box_ad() {
		?>
		<div id="integrity_sb1_ad"><a href="https://malcure.com/?p=107&utm_source=mss-integgrity-sb-ad&utm_medium=web&utm_campaign=mss">WordPress Malware Removal Service</a></div>
		<?php
	}

	function delete_checksums() {
		delete_transient( 'malcure_repo_checksums' );
	}

	function get_checksums( $cached = true ) {
		$checksums = $cached ? get_transient( 'malcure_repo_checksums' ) : false;
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
				set_transient( 'malcure_repo_checksums', $checksums, 7 * DAY_IN_SECONDS );
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
				$plugin_file  = trailingslashit( dirname( MSS_DIR ) ) . $key;
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



	/**
	 * Verify checksums if we have a checksum for a file
	 *
	 * @return void
	 */
	function verify_checksums() {

		$wp_upload_dir = wp_upload_dir();
		$wp_upload_dir = $wp_upload_dir['basedir'];
		$mimes         = wp_get_mime_types();
		$mimes         = array_keys( $mimes );
		$local_files   = $this->get_all_files();
		$checksums     = $this->get_checksums();
		$install_path  = get_home_path();
		$failed_files  = array(
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
				if ( strpos( $local_file, $wp_upload_dir ) !== false ) { // This file is a part of uploads directory (then only push if it has unwanted extension)
					$allowed = false;
					foreach ( $mimes as $mime ) {
						if ( preg_match( '/\.(' . $mime . ')$/i', $local_file ) ) { // This file-type is not allowed in uploads.
							$allowed = true;
						}
					}
					if ( ! $allowed ) { // not an allowed file
						$failed_files['extra_files'][ $local_file ] = $local_file; // insert into unique key to avoid duplicate insertion due to multiple matches
					}
				} else { // The file is not in uploads, push it into extra files (or failed checksum?)
					$failed_files['extra_files'][ $local_file ] = $local_file;
				}
			}
		}

		foreach ( $checksums as $checksum => $value ) {
			$checksum = trailingslashit( $install_path ) . $checksum;
			if ( ! in_array( $checksum, $local_files ) ) {
				$failed_files['missing_files'][] = $checksum;
			}
		}

		$failed_files['missing_files']    = array_values( $failed_files['missing_files'] );
		$failed_files['extra_files']      = array_values( $failed_files['extra_files'] );
		$failed_files['failed_checksums'] = array_values( $failed_files['failed_checksums'] );
		return $failed_files;
	}

	function get_all_files( $path = false ) {
		$files = malCure_Utils::get_files();
		return $files['files'];
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
