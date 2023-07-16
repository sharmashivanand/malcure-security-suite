<?php
require_once 'scanner_base.php';
require_once 'cli.php';

/**
 * Common utility functions
 */
final class mss_utils {
	static $opt_name = 'MSS';
	static $cap      = 'activate_plugins';

	function __construct() {
		add_filter( 'mss_checksums', array( $this, 'generated_checksums' ) );
	}

	/**
	 * Updates the color scheme of the UI
	 *
	 * @param [type] $scheme
	 * @return void
	 */
	static function set_color_scheme( $scheme ) {
		return self::update_setting( 'color_scheme', $scheme );
	}

	/**
	 * Ensure singleton
	 *
	 * @return object / instance
	 */
	static function get_instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
			$instance->init();
		}
		return $instance;
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	function init() {
		add_filter( 'mss_checksums', array( $this, 'generated_checksums' ) );
	}

	/**
	 * Debug function used for testing
	 *
	 * @param [type] $str
	 * @return void
	 */
	static function llog( $str, $log = false, $return = false ) {
		if ( $log ) {
			return self::elog( $str, '', $return );
		}
		if ( $return ) {
			return '<pre style="font-weight: normal;font-style: normal; font-family: monospace;">' . print_r( $str, 1 ) . '</pre>';
		} else {
			echo '<pre style="font-weight: normal;font-style: normal; font-family: monospace;">' . print_r( $str, 1 ) . '</pre>';
		}
	}

	/**
	 * Log error
	 *
	 * @param [type]  $err | Whatever error message
	 * @param [type]  $description: Where did this occur, how, when
	 * @param boolean $return
	 * @return void
	 */
	static function elog( $how_when_where, $msg, $return = false ) {
		self::append_log( $how_when_where, $msg );
		if ( $return ) {
			return '<pre>' . print_r( $str, 1 ) . '</pre>';
		} else {
			echo '<pre>' . print_r( $str, 1 ) . '</pre>';
		}
	}

	/**
	 * Add message to database log
	 *
	 * @param [type] $how_when_where
	 * @param string $msg
	 * @return void
	 */
	static function append_log( $how_when_where, $msg = '' ) {
		$errors = self::get_setting( 'log' );
		if ( ! $errors ) {
			$errors = array();
		}
		$errors[ time() ] = array(
			'how' => $how_when_where,
			'msg' => $msg,
		);
		ksort( $errors );
		$errors = array_slice( $errors, -10, 10, true ); // limit errors to recent 100
		return self::update_setting( 'log', $errors );
	}

	/**
	 * Log message to file
	 */
	static function flog( $str, $file = '', $timestamp = false ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$date = date( 'Ymd-G:i:s' ); // 20171231-23:59:59
			$date = $date . '-' . microtime( true );
			if ( $file ) {
				$file = MSS_DIR . $file;
			} else {
				$file = MSS_DIR . 'log.log';
			}
			if ( $timestamp ) {
				file_put_contents( $file, $date . PHP_EOL, FILE_APPEND | LOCK_EX );
			}
			$str = print_r( $str, true );
			file_put_contents( $file, $str . PHP_EOL, FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * get number of CPUs
	 *
	 * @return void
	 */
	static function num_cpus() {
		$numCpus = false;
		if ( is_file( '/proc/cpuinfo' ) ) {
			$cpuinfo = file_get_contents( '/proc/cpuinfo' );
			preg_match_all( '/^processor/m', $cpuinfo, $matches );
			$numCpus = count( $matches[0] );
		} elseif ( 'WIN' == strtoupper( substr( PHP_OS, 0, 3 ) ) ) {
			$process = @popen( 'wmic cpu get NumberOfCores', 'rb' );
			if ( false !== $process ) {
				fgets( $process );
				$numCpus = intval( fgets( $process ) );
				pclose( $process );
			}
		} else {
			$process = @popen( 'sysctl -a', 'rb' );
			if ( false !== $process ) {
				$output = stream_get_contents( $process );
				preg_match( '/hw.ncpu: (\d+)/', $output, $matches );
				if ( $matches ) {
					  $numCpus = intval( $matches[1][0] );
				}
				pclose( $process );
			}
		}
		return $numCpus;
	}

	/**
	 * If the plugin is registered with the API
	 *
	 * @return boolean
	 */
	static function is_registered() {
		return self::get_setting( 'api-credentials' );
	}

	/**
	 * String encode to keep it URL safe and not break JSON and not flagged by other scanners
	 *
	 * @param [type] $str
	 * @return void
	 */
	static function encode( $str ) {
		return strtr( base64_encode( json_encode( $str ) ), '+/=', '-_,' );
	}

	/**
	 * Companion function to the encode fn above. Decodes the string and make it usable again
	 *
	 * @param [type] $str
	 * @return void
	 */
	static function decode( $str ) {
		return json_decode( base64_decode( strtr( $str, '-_,', '+/=' ) ), true );
	}

	/**
	 * Plugin data to ensure the API returns the correct data for the respective configuration
	 *
	 * @return void
	 */
	static function get_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugin_data( MSS_FILE, false, false );
	}

	static function get_home_dir() {
		$home    = set_url_scheme( get_option( 'home' ), 'http' );
		$siteurl = set_url_scheme( get_option( 'siteurl' ), 'http' );
		if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) &&
			! ( defined( 'WP_CLI' ) && WP_CLI ) // Don't detect when using WP CLI
		) {
			$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl );
			$pos                 = strripos( str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ), trailingslashit( $wp_path_rel_to_home ) );
			$home_path           = substr( $_SERVER['SCRIPT_FILENAME'], 0, $pos );
			$home_path           = trailingslashit( $home_path );
		} else {
			$home_path = ABSPATH;
		}
		$home_path = str_replace( '\\', '/', $home_path );
		return trailingslashit( self::realpath( $home_path ) );
	}

	static function raise_limits_conditionally() {
		if ( strpos( ini_get( 'disable_functions' ), 'ini_set' ) === false ) {
			if ( function_exists( 'memory_get_usage' ) && ( (int) @ini_get( 'memory_limit' ) < 256 ) ) {
				@ini_set( 'memory_limit', 256 . 'M' );
			}
		}
		@ini_set( 'max_execution_time', max( (int) @ini_get( 'max_execution_time' ), 90 ) );
	}

	static function get_files( $path = false ) {
		self::raise_limits_conditionally();
		// self::flog( debug_backtrace()[1] );
		// die();
		// if ( defined( 'WP_CLI' ) && WP_CLI ) {
		//
		// } else {
		// @ini_set( 'max_execution_time', 90 ); // Don't kill if using WP CLI
		// @set_time_limit( 0 );
		// }
		if ( ! $path ) {
			$path = self::get_home_dir();
			if ( empty( $path ) ) {
				self::flog( 'Dang!' );
				return array();
			}
		}
		if ( is_link( $path ) ) {
			return array();
		}
		$path     = untrailingslashit( $path );
		$children = @scandir( $path );
		// self::flog($children);
		if ( is_array( $children ) ) {
			$children = array_diff( $children, array( '..', '.' ) );
			$dirs     = array();
			$files    = array();
			foreach ( $children as $child ) {
				$target = untrailingslashit( $path ) . DIRECTORY_SEPARATOR . $child;
				if ( is_dir( $target ) && ! is_link( $target ) ) {
					$elements = self::get_files( $target );
					if ( $elements ) {
						foreach ( $elements as $element ) {
							if ( is_file( $element ) && ! is_link( $element ) ) {
								// $files[] = self::realpath( $element );
								self::insertFileIntoDatabase( self::realpath( $element ) );
							}
						}
					}
				}
				if ( is_file( $target ) ) {
					// $files[] = self::realpath( $target );
					self::insertFileIntoDatabase( self::realpath( $target ) );
				}
			}
			return $files;
		}

	}




	/**
	 * Returns all files at the specified path
	 *
	 * @param boolean $path
	 * @return array, file-paths and file-count
	 */
	static function get_all_files_o( $directory = false ) {

		if ( ! $directory ) {
			$directory = ABSPATH;
		}
		$files = array();

		if ( is_readable( $directory ) ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD // Catch exceptions for unreadable directories/files
			);

			foreach ( $iterator as $file ) {
				try {
					if ( $file->isFile() ) {
						$files[] = $file->getPathname();
					}
				} catch ( UnexpectedValueException $e ) {
					// Handle exception for unreadable files/directories
					continue;
				}
			}
		}

		sort( $files );
		if ( $directory == ABSPATH ) {
			// self::flog( $files );
		}
		return array(
			'total_files' => count( $files ),
			'files'       => $files,
		);
	}

	static function realpath( $path ) {
		self::flog( $path );
		$realpath = realpath( $path );
		if ( $realpath ) {
			return $realpath;
		}
		return $path;
	}

	/**
	 * Register with the api endpoint and save credentials
	 *
	 * @return mixed data or wp_error
	 */
	static function do_mss_api_register( $user ) {
		$user['fn'] = preg_replace( '/[^A-Za-z ]/', '', $user['fn'] );
		$user['ln'] = preg_replace( '/[^A-Za-z ]/', '', $user['ln'] );
		if ( empty( $user['fn'] ) || empty( $user['ln'] ) || ! filter_var( $user['email'], FILTER_VALIDATE_EMAIL ) ) {
			return;
		}
		global $wp_version;
		$data     = self::encode(
			array(
				'user' => $user,
				'diag' => array(
					'site_url'       => trailingslashit( site_url() ),
					'php'            => phpversion(),
					'web_server'     => empty( $_SERVER['SERVER_SOFTWARE'] ) ? 'none' : $_SERVER['SERVER_SOFTWARE'],
					'wp'             => $wp_version,
					'plugin_version' => self::get_plugin_data(),
					'cachebust'      => microtime( 1 ),
				),
			)
		);
		$url      = add_query_arg(
			'wpmr_action',
			'wpmr_register',
			add_query_arg(
				'p',
				'495',
				add_query_arg( 'reg_details', $data, MSS_API_EP )
			)
		);
		$response = wp_safe_remote_request(
			$url,
			array(
				'blocking' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 != $status_code ) {
			return new WP_Error( 'broke', 'Got HTTP error ' . $status_code . '.' );
		}
		$response = wp_remote_retrieve_body( $response );
		if ( empty( $response ) || is_null( $response ) ) {
			return new WP_Error( 'broke', 'Empty response.' );
		}
		$data = json_decode( $response, true );
		if ( ! isset( $data['error'] ) ) {
			self::update_setting( 'api-credentials', $data );
			return $data;
		} else {
			return new WP_Error( 'broke', 'API server didn\'t register. Please try again later.' );
		}
		return new WP_Error( 'broke', 'Uncaught error in ' . __FUNCTION__ . '.' );
	}

	/**
	 * Returns full URL to API Endpoint for the requested action
	 */
	static function get_api_url( $data ) {
		return self::build_api_url( $data );
	}

	/**
	 * Builds full URL to API Endpoint for the requested action
	 */
	static function build_api_url( $data ) {

		$data['cachebust']   = time();
		$data['wpmr_action'] = $data['action'];

		$compatibility = self::get_plugin_data();
		$state         = self::get_setting( 'api-credentials' );
		$lic           = self::get_setting( 'license_key' );
		if ( $state ) {
			$state = array_merge( $state, $compatibility );
		} else {
			$state = $compatibility;
		}
		if ( $lic ) {
			$state['lic'] = $lic;
		}
		$data['state'] = self::encode( $state );
		return trailingslashit( MSS_API_EP ) . '?' . urldecode( http_build_query( $data ) );
	}

	/**
	 * Check for definition updates
	 *
	 * @return array of new and current defition versions | WP_Error
	 */
	static function definition_updates_available() {
		$current = self::get_definition_version();
		$new     = self::get_setting( 'update-version' );
		return $new;
		if ( empty( $new ) ) {
			$new = self::check_definition_updates();
			if ( is_wp_error( $new ) ) {
				return $new;
			}
		}
		if ( $current != $new ) {
			return array(
				'new'     => $new,
				'current' => $current,
			);
		}
		return false;
	}

	/**
	 * Meant to be run daily or on-demand. Checks for definition update from API server.
	 *
	 * @return void
	 */
	static function check_definition_updates() {
		$response    = wp_safe_remote_request( self::get_api_url( 'check-definitions' ) );
		$headers     = wp_remote_retrieve_headers( $response );
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 != $status_code ) {
			return new WP_Error( 'broke', 'Got HTTP error ' . $status_code . ' while checking definition updates.' );
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body    = wp_remote_retrieve_body( $response );
		$version = json_decode( $body, true );
		if ( is_null( $version ) ) {
			return new WP_Error( 'broke', 'Unparsable response during definition update-check.' );
		}
		if ( $version['success'] != true ) {
			return new WP_Error( 'broke', sanitize_text_field( $version['data'] ) );
		}
		if ( ! empty( $version['success'] ) && $version['success'] == true ) {
			$version = $version['data'];
			$time    = date( 'U' );
			self::update_setting( 'update-version', $version );
			return $version;
		}
	}

	/**
	 * Gets WordPress Core and plugin checksums
	 *
	 * @return array
	 */
	static function fetch_checksums() {
		$checksums = self::get_option_checksums_core();

		return apply_filters( 'mss_checksums', $checksums );

	}

	static function get_checksums_db() {
		global $wpdb;
		$query     = "SELECT * FROM {$wpdb->prefix}mss_checksums";
		$checksums = $wpdb->get_results( $query, ARRAY_A );
		if ( empty( $checksums ) ) {
			self::update_checksums_web();
			$checksums = self::get_checksums_db();
			if ( empty( $checksums ) ) {
				return array();
			}
		}
		return $checksums;
	}


	static function update_checksums_web() {
		global $wp_version;
		$checksums = self::update_checksums_core( $wp_version, get_locale() );
		if ( ! $checksums ) { // get_core_checksums failed
			$checksums = self::update_checksums_core( $wp_version, 'en_US' ); // try en_US locale
			if ( ! $checksums ) {
				$checksums = array(); // fallback to empty array
			}
		}
		$plugin_checksums = self::update_checksums_plugins();
		if ( $plugin_checksums ) {
			$checksums = array_merge( $checksums, $plugin_checksums );
		}
		// $theme_checksums = self::fetch_theme_checksums();
		// if ( $theme_checksums ) {
		// $checksums = array_merge( $checksums, $theme_checksums );
		// }
		// if ( $checksums ) {
		// self::update_option_checksums_core( $checksums );
		// return apply_filters( 'mss_checksums', $checksums );
		// }
		// return apply_filters( 'mss_checksums', array() );
	}

	static function update_checksums_core( $ver = false, $locale = 'en_US' ) {
		$state = self::get_setting( 'user' );
		$state = self::encode( $state );
		global $wp_version;
		if ( ! $ver ) {
			$ver = $wp_version;
		}
		$checksum_url = self::get_api_url(
			array(
				'action'  => 'wpmr_checksum',
				'slug'    => 'wordpress',
				'version' => $ver,
				'locale'  => $locale,
				'type'    => 'core',

			)
		);// WPMR_SERVER . '?wpmr_action=wpmr_checksum&slug=wordpress&version=' . $ver . '&locale=' . $locale . '&type=core&state=' . $state;
		// $checksum_url = http_build_query();
		// return $checksum_url;
		$core_checksums = array();
		$checksum       = wp_safe_remote_get( $checksum_url );
		if ( is_wp_error( $checksum ) ) {
			return;
		}
		if ( '200' != wp_remote_retrieve_response_code( $checksum ) ) {
			return;
		}
		$checksum = wp_remote_retrieve_body( $checksum );
		$checksum = json_decode( $checksum, true );
		if ( ! is_null( $checksum ) && ! empty( $checksum['files'] ) ) {
			$checksum = $checksum['files'];
			foreach ( $checksum as $file => $checksums ) {
				$core_checksums[ $file ] = $checksums['sha256'];
			}
		}
		self::insertChecksumIntoDatabase( $core_checksums, 'core', $ver );
	}

	static function insertFileIntoDatabase( $filePath ) {
		global $wpdb;
		$tableName = $wpdb->prefix . 'mss_files';
		self::flog( $filePath );

		// $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $tableName, $data ) );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $tableName (path) VALUES (%s)", $filePath ) );
	}

	static function insertChecksumIntoDatabase( $arrChecksums, $type, $version ) {
		global $wpdb;
		$tableName = $wpdb->prefix . 'mss_checksums';
		foreach ( $arrChecksums as $key => $value ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $tableName (path, checksum, type, ver) VALUES (%s, %s, %s, %s) 
        			ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), type = VALUES(type), ver = VALUES(ver)",
					$key,
					$value,
					$type,
					$version
				)
			);
		}
	}

	/**
	 * Fetches checksums for premium plugins from the API server
	 *
	 * @return void
	 */
	static function update_checksums_plugins() {
		$missing          = array();
		$all_plugins      = get_plugins();
		$install_path     = self::get_home_dir();
		$plugin_checksums = array();
		foreach ( $all_plugins as $key => $value ) {
			if ( false !== strpos( $key, '/' ) ) { // plugin has to be inside a directory. currently drop in plugins are not supported
				$plugin_file  = trailingslashit( dirname( MSS_DIR ) ) . $key;
				$plugin_file  = str_replace( $install_path, '', $plugin_file );
				$t1           = time();
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
					$k        = trailingslashit( $install_path . dirname( $plugin_file ) );
					$t0e      = time();
					echo "fetched web\t" . ( $t0e - $t1 ) . PHP_EOL;
					foreach ( $checksum as $file => $checksums ) {
						if ( is_array( $checksums['sha256'] ) ) {
							$checksums['sha256'] = $checksums['sha256'][0];
						}
						$plugin_checksums[ $k . $file ] = $checksums['sha256'];
					}
				}

				$t2s = time();
				echo "Built array\t" . ( $t2s - $t0e ) . PHP_EOL;
				self::flog( 'Updating checksums for: ' . trailingslashit( $install_path . dirname( $plugin_file ) ) );
				self::insertChecksumIntoDatabase( $plugin_checksums, 'plugin', $value['Version'] );
				$t2 = time();
				echo "Updated db\t" . ( time() - $t2 ) . PHP_EOL;
			}
		}
	}

	/**
	 * Gets checksums for themes from the API server
	 *
	 * @return void
	 */
	static function fetch_theme_checksums() {
		$all_themes      = wp_get_themes();
		$install_path    = get_home_path();
		$theme_checksums = array();
		$theme_root      = get_theme_root();
		// $state           = $this->get_setting( 'user' );
		// $state           = $this->encode( $state );
		foreach ( $all_themes as $key => $value ) {
			$theme_file   = trailingslashit( $theme_root ) . $key;
			$theme_file   = str_replace( $install_path, '', $theme_file );
			$checksum_url = self::get_api_url( 'wpmr_checksum' ) . '&slug=' . $key . '&version=' . $value['Version'] . '&type=theme';
			$checksum     = wp_safe_remote_get( $checksum_url );
			if ( is_wp_error( $checksum ) ) {
				continue;
			}
			if ( '200' != wp_remote_retrieve_response_code( $checksum ) ) {
				continue;
			}
			$checksum = wp_remote_retrieve_body( $checksum );
			$checksum = json_decode( $checksum, true );
			if ( ! is_null( $checksum ) && ! empty( $checksum['files'] ) ) {
				$checksum = $checksum['files'];
				foreach ( $checksum as $file => $checksums ) {
					$theme_checksums[ trailingslashit( dirname( $theme_file ) ) . $file ] = $checksums['md5'];
				}
			}
		}
		return $theme_checksums;
	}

	/**
	 * Add locally generated checksums to the checksums array
	 *
	 * @param [type] $checksums
	 * @return void
	 */
	static function generated_checksums( $checksums ) {
		$generated = self::get_option_checksums_generated();
		if ( $generated && is_array( $generated ) && ! empty( $checksums ) && is_array( $checksums ) ) {
			return array_merge( $generated, $checksums );
		} else {
		}
		return $checksums;
	}

	/**
	 * Replace absolute path of the file with the WordPress relative path
	 */
	static function normalize_path( $file_path ) {
		return str_replace( get_home_path(), '', $file_path );
	}

	/**
	 * Gets checksums of premium versions from API server
	 */
	static function get_pro_checksums( $missing ) {
		if ( empty( $missing ) ) {
			return;
		}
		if ( ! self::is_registered() ) {
			return;
		}
		$state            = self::get_setting( 'user' );
		$state            = self::encode( $state );
		$all_plugins      = $missing;
		$install_path     = get_home_path();
		$plugin_checksums = array();
		foreach ( $all_plugins as $key => $value ) {
			if ( false !== strpos( $key, '/' ) ) { // plugin has to be inside a directory. currently drop in plugins are not supported
				$plugin_file  = trailingslashit( dirname( MSS_DIR ) ) . $key;
				$plugin_file  = str_replace( $install_path, '', $plugin_file );
				$checksum_url = self::get_api_url( 'wpmr_checksum' );
				$checksum_url = add_query_arg(
					array(
						'slug'    => dirname( $key ),
						'version' => $value['Version'],
						'type'    => 'plugin',
					),
					'http://example.com'
				);
				$checksum     = wp_safe_remote_get( $checksum_url );
				if ( is_wp_error( $checksum ) ) {
					continue;
				}
				if ( '200' != wp_remote_retrieve_response_code( $checksum ) ) {
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
	 * Update definitions from API server
	 */
	static function update_definitions() {
		$definitions = self::fetch_definitions();
		if ( is_wp_error( $definitions ) ) {
			return $definitions;
		} else {
			if ( $definitions['v'] != self::get_definition_version() ) {
				self::delete_option( 'checksums_generated' );
			}
			self::update_option_definitions( $definitions );
			$time = date( 'U' );
			self::update_setting( 'definitions_update_time', $time );
			return true;
		}
	}

	/**
	 * Returns the version of the definitions
	 *
	 * @return void
	 */
	static function get_definition_version() {
		$definitions = self::get_option( 'definitions' );
		if ( $definitions && ! empty( $definitions['v'] ) ) {
			return $definitions['v'];
		}
	}

	/**
	 * Fetch definitions from the api endpoint
	 *
	 * @return array definitions or wp error
	 */
	static function fetch_definitions() {
		$response    = wp_safe_remote_request( self::get_api_url( 'update-definitions' ) );
		$headers     = wp_remote_retrieve_headers( $response );
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 != $status_code ) {
			return new WP_Error( 'broke', 'Got HTTP error ' . $status_code . ' while fetching Update.' );
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body        = wp_remote_retrieve_body( $response );
		$definitions = json_decode( $body, true );
		if ( is_null( $definitions ) ) {
			return new WP_Error( 'broke', 'Unparsable response in definitions.' );
		}
		if ( $definitions['success'] != true ) {
			return new WP_Error( 'broke', sanitize_text_field( $definitions['data'] ) );
		}
		if ( ! empty( $definitions['success'] ) && $definitions['success'] == true ) {
			$definitions = $definitions['data'];
			return $definitions;
		}
	}

	/**
	 * Update core checksums
	 *
	 * @param [type] $checksums
	 * @return void
	 */
	static function update_option_checksums_core( $checksums ) {
		return self::update_option( 'checksums_core', $checksums );
	}

	/**
	 * Update locally generate checksums
	 *
	 * @param [type] $checksums
	 * @return void
	 */
	static function update_option_checksums_generated( $checksums ) {
		return self::update_option( 'checksums_generated', $checksums );
	}

	/**
	 * Save definitions to the database
	 *
	 * @param [type] $definitions
	 * @return void
	 */
	static function update_option_definitions( $definitions ) {
		return self::update_option( 'definitions', $definitions );
	}

	/**
	 * Return core WordPress checksums
	 *
	 * @return void
	 */
	static function get_option_checksums_core() {
		return self::get_option( 'checksums_core' );
	}

	/**
	 * Return locally generated checksums
	 */
	static function get_option_checksums_generated() {
		$checksums = self::get_option( 'checksums_generated' );
		if ( ! $checksums ) {
			return array();
		}
		return $checksums;
	}

	/**
	 * Return definitions
	 *
	 * @return void
	 */
	static function get_option_definitions() {
		return self::get_option( 'definitions' );
	}

	/**
	 * Delete core WordPress checksums
	 *
	 * @return void
	 */
	static function delete_option_checksums_core() {
		return self::delete_option( 'checksums_core' );
	}

	/**
	 * Delete locally generated checksums
	 *
	 * @return void
	 */
	static function delete_option_checksums_generated() {
		return self::delete_option( 'checksums_generated' );
	}

	/**
	 * Delete definitions
	 *
	 * @return void
	 */
	static function delete_option_definitions() {
		return self::delete_option( 'definitions' );
	}

	/**
	 * Wait for the lock to clear before getting lock
	 *
	 * @return void
	 */
	static function await_unlock() {
		while ( self::get_option( 'MSS_lock' ) == 'true' ) {
			usleep( rand( 2500, 7500 ) );
		}
		self::update_option( 'MSS_lock', 'true' );
	}

	/**
	 * Clear the lock
	 *
	 * @return void
	 */
	static function do_unlock() {
		self::update_option( 'MSS_lock', 'false' );
	}

	/**
	 * Get a specific setting from our settings array in the database option
	 *
	 * @param [type] $setting
	 * @return void
	 */
	static function get_setting( $setting ) {
		$settings = get_option( self::$opt_name );
		return isset( $settings[ $setting ] ) ? $settings[ $setting ] : false;
	}

	/**
	 * Update a specific setting in our settings array in the database option
	 *
	 * @param [type] $setting
	 * @param [type] $value
	 * @return void
	 */
	static function update_setting( $setting, $value ) {
		$settings = get_option( self::$opt_name );
		if ( ! $settings ) {
			$settings = array();
		}
		$settings[ $setting ] = $value;
		update_option( self::$opt_name, $settings );
	}

	/**
	 * Delete a specific setting in our settings array in the database option
	 *
	 * @param [type] $setting
	 * @return void
	 */
	static function delete_setting( $setting ) {
		self::flog( 'deleting setting: ' . $setting );
		$settings = get_option( self::$opt_name );
		if ( ! $settings ) {
			$settings = array();
		}
		self::flog( 'deleting setting before: ' );
		self::flog( $settings );
		unset( $settings[ $setting ] );
		self::flog( 'deleting setting after: ' );
		self::flog( $settings );
		update_option( self::$opt_name, $settings );
	}

	/**
	 * Run periodic maintenance tasks
	 *
	 * @return void
	 */
	static function do_maintenance() {

		$started = self::get_setting( 'scan_id' );
		if ( ! empty( $started ) && time() - $started >= 10800 ) { // Delete only if the scan strted 3 hrs back or earlier
			self::delete_setting( 'scan_id' );
		}

		$logs = self::get_setting( 'log' );
		if ( ! empty( $logs ) && is_array( $logs ) && count( $logs ) >= 11 ) {
			ksort( $logs );
			$logs = array_slice( $logs, -10, 10, true ); // limit errors to recent 100
			self::update_setting( 'log', $logs );
		}

		return;

		// self::delete_setting( 'scan_status' );
		$lock       = self::get_setting( 'scan_id' );
		$now        = time();
		$difference = ( $now - $lock );
		$is_expired = $difference > ( 3600 * 6 ) ? 1 : 0;if ( $is_expired ) {
			self::delete_setting( 'scan_id' );
		}
		// $scans = self::get_option( 'scans' );
		// if ( empty( $scans ) ) { // when no scans have been run till date
		// $scans = array();
		// }
		// $retain  = 2;
		// $retain -= 1; // purge one extra so that we can make space for new scan. This way we'll end up having the same number after completion.
		// if ( count( $scans ) >= $retain ) {
		// $scans = array_slice( $scans, count( $scans ) - $retain, $retain, true );
		// }
		// self::update_option( 'scans', $scans );
	}

	/**
	 * Return our option from the database
	 *
	 * @param [type] $option
	 * @return void
	 */
	static function get_option( $option ) {
		return get_option( self::$opt_name . '_' . $option );
	}

	/**
	 * Update our option in the database
	 *
	 * @param [type] $option
	 * @param [type] $value
	 * @return void
	 */
	static function update_option( $option, $value ) {
		return update_option( self::$opt_name . '_' . $option, $value );
	}

	/**
	 * Delete our option from the database
	 *
	 * @param [type] $option
	 * @return void
	 */
	static function delete_option( $option ) {
		return delete_option( self::$opt_name . '_' . $option );
	}
}
// mss_utils::get_instance();
$mss_utils = new mss_utils();
