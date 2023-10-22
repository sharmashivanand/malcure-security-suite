<?php
// require_once 'scanner_base.php';
require_once 'cli.php';

/**
 * Common utility functions
 */
final class mss_utils {
	static $opt_name = 'MSS';
	static $cap      = 'activate_plugins';

	function __construct() {
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

		add_action( 'wp_ajax_mss_update_checksums_plugin', array( $this, 'update_checksums_plugin' ) );
		add_action( 'wp_ajax_nopriv_mss_update_checksums_plugin', array( $this, 'update_checksums_plugin' ) );
	}

	/** DATABASE MANAGEMENT */

	/**
	 * Update all checksums from web into the database
	 *
	 * @return null
	 */
	static function update_checksums_web() {
		self::flog( 'MSS WARNING!!! COSTLY OPERATION' . __FUNCTION__ );
		global $wp_version;

		$checksums = self::update_checksums_core( $wp_version, get_locale() );
		if ( ! $checksums ) { // get_core_checksums failed
			$checksums = self::update_checksums_core( $wp_version, 'en_US' ); // try en_US locale
		}

		self::update_checksums_plugins();
		// if ( $plugin_checksums ) {
		// $checksums = array_merge( $checksums, $plugin_checksums );
		// }

		self::update_checksums_themes();
		// if ( $theme_checksums ) {
		// $checksums = array_merge( $checksums, $theme_checksums );
		// }
	}

	static function update_checksums_core( $ver = false, $locale = 'en_US' ) {
		global $wp_version;
		if ( ! $ver ) {
			$ver = $wp_version;
		}
		$checksum_url = self::build_api_url(
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
				$core_checksums[ self::realpath( ABSPATH . $file ) ] = $checksums['sha256'];
			}
			self::insertChecksumsIntoDatabase( $core_checksums, 'core', $ver );
			return $core_checksums;
		}
	}

	/**
	 * Fetches checksums for premium plugins from the API server
	 *
	 * @return void
	 */
	static function update_checksums_plugins( $plugins = array() ) {
		$missing = array();
		if ( ! $plugins ) {
			$all_plugins = get_plugins();
		} else {
			$all_plugins = $plugins;
		}
		$install_path = self::get_home_dir();
		$plugin_dir   = trailingslashit( WP_PLUGIN_DIR );
		foreach ( $all_plugins as $key => $value ) {
			if ( false !== strpos( $key, '/' ) ) { // plugin has to be inside a directory. currently drop in plugins are not supported
				// self::flog( 'Plugin: ' . dirname( $key ) );
				$plugin_file  = trailingslashit( $key );
				$plugin_file  = str_replace( $install_path, '', $plugin_file );
				$checksum_url = 'https://downloads.wordpress.org/plugin-checksums/' . dirname( $key ) . '/' . $value['Version'] . '.json';

				$checksum = wp_safe_remote_get( $checksum_url );
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
					$checksum         = $checksum['files'];
					$plugin_checksums = array();
					foreach ( $checksum as $file => $checksums ) {
						if ( is_array( $checksums['sha256'] ) ) {
							// self::flog( $checksums );
							$checksums['sha256'] = array_pop( $checksums['sha256'] );
						}
						$plugin_checksums[ self::realpath( $plugin_dir . trailingslashit( dirname( $plugin_file ) ) . $file ) ] = $checksums['sha256'];
					}
					// self::flog( '$plugin_checksums' );
					// self::flog( $plugin_checksums );
					self::insertChecksumsIntoDatabase( $plugin_checksums, 'plugin', $value['Version'] );
				}
			}
		}
	}

	static function update_checksums_themes() {
		$all_themes      = wp_get_themes();
		$install_path    = ABSPATH;
		$theme_checksums = array();
		$theme_root      = trailingslashit( get_theme_root() );
		foreach ( $all_themes as $key => $value ) {
			$theme_dir = trailingslashit( $theme_root . $key );
			// $theme_file   = str_replace( $install_path, '', $theme_file );
			$checksum_url = self::build_api_url(
				array(
					'action'  => 'wpmr_checksum',
					'slug'    => $key,
					'version' => $value['Version'],
					'type'    => 'theme',
				)
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
				$checksum        = $checksum['files'];
				$theme_checksums = array();
				foreach ( $checksum as $file => $checksums ) {
					if ( is_array( $checksums['sha256'] ) ) {
						// self::flog( $checksums );
						$checksums['sha256'] = array_pop( $checksums['sha256'] );
					}
					$theme_checksums[ self::realpath( $theme_dir . trailingslashit( dirname( $theme_dir ) ) . $file ) ] = $checksums['sha256'];
				}
				self::insertChecksumsIntoDatabase( $theme_checksums, 'theme', $value['Version'] );
			}
		}
	}

	static function insertChecksumsIntoDatabase( $arrChecksums, $type, $version ) {
		global $wpdb;
		$tableName = $wpdb->prefix . MSS_ORIGIN_CS;

		$query = "INSERT INTO $tableName (path, checksum, type, ver) VALUES ";

		$valuePlaceholders = array();
		$params            = array();

		foreach ( $arrChecksums as $key => $value ) {
			$valuePlaceholders[] = '(%s, %s, %s, %s)';
			$params[]            = $key;
			$params[]            = $value;
			$params[]            = $type;
			$params[]            = $version;
		}

		if ( ! empty( $valuePlaceholders ) ) {
			$query .= implode( ', ', $valuePlaceholders );
			$query .= ' ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), type = VALUES(type), ver = VALUES(ver)';
			// self::flog( '$query' );
			// self::flog( $query );
			$preparedQuery = $wpdb->prepare( $query, $params );
			$wpdb->query( $preparedQuery );
		}
	}


	/** DATABASE MANAGEMENT ENDS */

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

		// $fl = debug_backtrace()[1]['file'] ;
		// $fn = debug_backtrace()[1]['function'] ;
		// $line = debug_backtrace()[1]['line'] ;

		$fl   = '';
		$fn   = '';
		$line = '';

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$date = date( 'Ymd-G:i:s' ); // 20171231-23:59:59
			$date = $date . '-' . microtime( true );
			if ( $file ) {
				$file = MSS_DIR . $file;
			} else {
				$file = MSS_DIR . 'log.log';
			}
			if ( $timestamp && ! self::is_cli() ) {
				file_put_contents( $file, $date . PHP_EOL, FILE_APPEND | LOCK_EX );
			}
			$str = print_r( $str, true );
			if ( ! self::is_cli() ) {
				file_put_contents( $file, $str . ' ' . $fl . $fn . $line . PHP_EOL, FILE_APPEND | LOCK_EX );
			} else {
				WP_CLI::log( $str . PHP_EOL );
			}
		}
	}

	static function clear_log() {
		file_put_contents( MSS_DIR . 'log.log', '', LOCK_EX );
	}

	static function human_readable_bytes($bytes, $decimals = 2) {
		$size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
	}

	static function human_readable_time_diff($start_timestamp, $end_timestamp) {
		$diff = abs($end_timestamp - $start_timestamp);
	
		$units = array(
			'year'   => 31556926,
			'month'  => 2629744,
			'week'   => 604800,
			'day'    => 86400,
			'hour'   => 3600,
			'minute' => 60,
			'second' => 1,
		);
	
		$parts = array();
	
		foreach ($units as $name => $divisor) {
			if ($diff < $divisor) continue;
	
			$time = floor($diff / $divisor);
			$diff %= $divisor;
	
			$parts[] = $time . ' ' . $name . ($time > 1 ? 's' : '');
		}
	
		$last = array_pop($parts);
	
		if (empty($parts)) {
			return $last;
		} else {
			return join(', ', $parts) . ' and ' . $last;
		}
	}
	

	/**
	 * Returns trus if running in CLI mode
	 *
	 * @return boolean
	 */
	static function is_cli() {
		return defined( 'WP_CLI' ) && WP_CLI;
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
		// self::flog( $path );
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
	 * Builds full URL to API Endpoint for the requested action
	 */
	static function build_api_url( $data ) {
		if ( empty( $data['action'] ) ) {
			self::flog( 'No action specified' );
			return;
		}
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data['cachebust']   = time();
		$data['wpmr_action'] = $data['action'];

		$compatibility = self::get_plugin_data();
		$state         = self::get_setting( 'api-credentials' );
		$lic           = self::get_setting( 'license_key' );
		$defver        = self::get_definition_version();
		if ( $state ) {
			$state = array_merge( $state, $compatibility );
		} else {
			$state = $compatibility;
		}
		if ( $lic ) {
			$state['lic'] = $lic;
		}
		if ( ! $defver ) {
			$defver = '1.0.0';
		}
		$state['defver'] = $defver;
		$data['state']   = self::encode( $state );
		return trailingslashit( MSS_API_EP ) . '?' . urldecode( http_build_query( $data ) );
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

	/**
	 * Largely redundant. Not called anywhere
	 *
	 * @return void
	 */
	static function get_checksums_db() {
		global $wpdb;
		$query     = "SELECT * FROM {$wpdb->prefix}" . MSS_ORIGIN_CS;
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

	static function insertFileIntoDatabase( $filePath ) {
		global $wpdb;
		$tableName = $wpdb->prefix . MSS_GEN_CS;
		self::flog( $filePath );

		// $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $tableName, $data ) );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $tableName (path) VALUES (%s)", $filePath ) );
	}

	static function update_checkasdfsums_themes() {
		$all_themes      = wp_get_themes();
		$install_path    = ABSPATH;
		$theme_checksums = array();
		$theme_root      = get_theme_root();
		$state           = self::get_setting( 'user' );
		$state           = self::encode( $state );
		foreach ( $all_themes as $key => $value ) {

			$theme_file = trailingslashit( $theme_root ) . $key;
			WP_CLI::log( $key );
			WP_CLI::log( $theme_file );

		}
		// return $theme_checksums;
	}

	function update_checksums_theme() {
		$theme_file       = str_replace( $install_path, '', $theme_file );
			$checksum_url = WPMR_SERVER . '?wpmr_action=wpmr_checksum&slug=' . $key . '&version=' . $value['Version'] . '&type=theme&state=' . $state;
			$checksum     = wp_safe_remote_get( $checksum_url );
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
				$theme_checksums[ trailingslashit( dirname( $theme_file ) ) . $file ] = $checksums['sha256'];
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
			$checksum_url = self::build_api_url(
				array(
					'action'  => 'wpmr_checksum',
					'slug'    => $key,
					'version' => $value['Version'],
					'type'    => 'theme',
				)
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
		if ( empty( $missing ) || ! self::is_registered() ) {
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
				$checksum_url = self::build_api_url(
					array(
						'action'  => 'wpmr_checksum',
						'slug'    => dirname( $key ),
						'version' => $value['Version'],
						'type'    => 'plugin',
					)
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
	 * Check for definition updates
	 *
	 * @return array of new and current defition versions | WP_Error
	 */
	static function definition_updates_available() {
		$current = self::get_definition_version();
		self::check_definition_updates();
		$new = self::get_setting( 'update-version' );
		// self::flog( 'new ' . $new );
		// self::flog( 'current ' . $current );
		return $current != $new;

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
		$url = self::build_api_url(
			array(
				'action' => 'check-definitions',
				'defvar' => self::get_definition_version(),
			)
		);
		// self::flog($url);
		$response    = wp_safe_remote_request(
			$url
		);
		$headers     = wp_remote_retrieve_headers( $response );
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 != $status_code ) {
			return new WP_Error( 'broke', 'Got HTTP error ' . $status_code . ' while checking definition updates.' );
		}
		if ( is_wp_error( $response ) ) {
			self::flog( $response );
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
	 * Update definitions from API server
	 */
	static function update_definitions() {
		if ( ! self::is_registered() ) {
			self::flog( 'No API credentials.' );
			return; // return new WP_Error( 'broke', 'Not registered' );
		}
		$definitions = self::fetch_definitions();

		if ( is_wp_error( $definitions ) ) {
			self::flog( $definitions );
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
	 * Fetch definitions from the api endpoint
	 *
	 * @return array definitions or wp error
	 */
	static function fetch_definitions() {
		$url = self::build_api_url( array( 'action' => 'update-definitions' ) );
		// self::flog( $url );
		$response    = wp_safe_remote_request( $url );
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
	 * Returns the version of the definitions
	 *
	 * @return void
	 */
	static function get_definition_version() {
		$definitions = self::get_option_definitions();
		if ( $definitions && ! empty( $definitions['v'] ) ) {
			return $definitions['v'];
		}
	}

	static function get_default_definitions() {
		return array(
			'definitions' =>
			array(
				'files' =>
				array(
					'qLPAE' =>
					array(
						'severity'  => 'suspicious',
						'signature' => 'IlwvW15hLXpcXFwvJ1wiXWV2YWxcXChbXlxcKV0rWydcIlxcc1xcKTtdK1wvaSI,',
						'class'     => 'scripting',
					),
					'Y9N1H' =>
					array(
						'severity'  => 'suspicious',
						'signature' => 'IlwvXFwkYXV0aF9wYXNzXFxzKj0uKztcL2ki',
						'class'     => 'scripting',
					),
					'2F58G' =>
					array(
						'severity'  => 'suspicious',
						'signature' => 'IlwvcHJlZ19yZXBsYWNlXFxzKlxcKC4rW1xcXC9cXCNcXHxdW2ldKmVbaV0qWydcIl0uK1xcKVwvaSI,',
						'class'     => 'scripting',
					),
					'wCM8D' =>
					array(
						'severity'  => 'suspicious',
						'signature' => 'IlwvXFxcL1xcKiBUaGlzIGZpbGUgaXMgcHJvdGVjdGVkIGJ5IGNvcHlyaWdodCBsYXcgYW5kIHByb3ZpZGVkIHVuZGVyIGxpY2Vuc2UuIFJldmVyc2UgZW5naW5lZXJpbmcgb2YgdGhpcyBmaWxlIGlzIHN0cmljdGx5IHByb2hpYml0ZWQuIFxcKlxcXC9cLyI,',
						'class'     => 'scripting',
					),
					'GML4E' =>
					array(
						'severity'  => 'suspicious',
						'signature' => 'IlwvXFwjKFxcdyspXFwjLis_XFwjXFxcL1xcMVxcI1wvaXMi',
						'class'     => 'scripting',
					),
					'CD69I' =>
					array(
						'severity'  => 'severe',
						'signature' => 'IlwvKFxccyooXFwkWzAtOV9hLXpdKylcXHMqPVxccyooW1wiJ10pKFthLXpcXFwvX1xcLTAtOVxcLl0qXFxcXHhbYS1mMC05XXsyfSkrW15cXDNdKj9cXDM7fFxccyooXFxcL1xcKihbXipdKlxcKig_IVxcXC8pKSpbXipdKlxcKlxcXC8pKSpbXFxAXFxzXSooaW5jbHVkZXxyZXF1aXJlKShfb25jZSk_W1xcc1xcKF0qKFxcXC9cXCpbXlxcKl0qKFxcKlteXFwqXFxcL10qKStcXFwvXFxzKikqW1xcKFxcc10qKChbXCInXSkoW2EtelxcXC9fXFwtMC05XFwuXSpcXFxcKHhbYS1mMC05XXsyfXxbMC05XXsyLDN9KSkrW15cXDEyXSo_XFwxMnxcXDJ8XFwkXyg_IVJFUVVFU1RcXFsndGFyZ2V0J1xcXTspKFBPU3xSRVFVRVN8R0UpVFxccyooXFxbW15cXF1dK1xcXVxccyp8XFx7W15cXH1dK1xcfVxccyopKylbXFxzXFwpXSo7KFxccypcXDUpKlwvaSI,',
						'class'     => 'scripting',
					),
					'xCQBJ' =>
					array(
						'severity'  => 'severe',
						'signature' => 'IlwvPFxcP1twaFxcc10rKFxcXC9cXFwvW15cXG5dKlxccyt8KGlmW1xcc1xcKF0raXNzZXRcXCh8ZnVuY3Rpb25cXHMrW2Etel8wLTldK1xcKFteXFx7XStbXFx7XFxzXSsuK3JldHVybilbXjtdKztbXFxzXFx9XSspKigoKFxcXC9cXCooW14qXSpcXCooPyFcXFwvKSkqW14qXSpcXCpcXFwvXFxzKnxpZlxccyopK1xcKGlzc2V0W1xcc1xcKF0rW1xcJFxceydcIl9dK1teXFx7XStbXFx7XFxzXSopPygoXFxcL1xcKihbXipdKlxcKig_IVxcXC8pKSpbXipdKlxcKlxcXC9cXHMqfFxcJFthLXpfMC05XSsoXFxzKlxcW1teXFxdXSpcXF0pKlxccyopKz1bXjtdKjtcXHMqfGZvcihlYWNoKT9cXHMqXFwoW15cXHtdK1xce1teXFx9XStcXH1cXHMqKSspKygoZnVuY3Rpb25cXHMrW2Etel8wLTldK1xcKFteXFx7XStbXFx7XFxzXSsuKj8pP1xcJFtcXCRcXHtdKlthLXpfMC05XStbXFx9XFxzXSooXFxcL1xcKihbXipdKlxcKig_IVxcXC8pKSpbXipdKlxcKlxcXC9cXHMqfFxcW1teXFxdXStcXF1cXHMqKSpcXCguKjtbXFx9XFxzXSopKygkfFxcPz4pXC9pIg,,',
						'class'     => 'scripting',
					),
					'v411F' =>
					array(
						'severity'  => 'suspicious',
						'signature' => 'IlwvanNvbjJcXC5taW5cXC5qc1wvaSI,',
						'class'     => 'scripting',
					),
				),
				'db'    =>
				array(
					'J53Gh' =>
					array(
						'severity'  => 'severe',
						'query'     => 'IiU8c2NyaXB0JSI,',
						'signature' => 'IlwvPHNjcmlwdC4rP2Zyb21DaGFyQ29kZS4rP2xvY2F0aW9uLis_bG9jYXRpb24uKz9sb2NhdGlvbi4rPzxcXFwvc2NyaXB0PlwvaXMi',
						'class'     => 'database',
					),
					'cHTCI' =>
					array(
						'severity'  => 'severe',
						'query'     => 'IiU8YSAldmlhZ3JhJSI,',
						'signature' => 'IlwvPGFbXj5dKj5bXjxdKihwdXJjaGFzZXxvcmRlcnxidXl8Y2hlYXB8dmlhZ3JhfGxpc2lub3ByaWx8amFudXZpYXxmbHVveGV0aW5lfHBpbGxzfGNhc2htZXJlW1xcc19cXC1dKnNjYXJmfG9ubGluZXxvdXRsZXQpW148XSo8XFxcL2E',
						'class'     => 'database',
					),
					'OCQ5J' =>
					array(
						'severity'  => 'severe',
						'query'     => 'IiVldmFsKFN0cmluZy5mcm9tQ2hhckNvZGUoJSI,',
						'signature' => 'IlwvZXZhbFxcKFtcXHNhLXpfMC05XFwuXFwoXSpmcm9tQ2hhckNvZGVcXChbMC05LFxcc10rW1xcKVxcc10rO1xccypcL2ki',
						'class'     => 'database',
					),
					'n711J' =>
					array(
						'severity'  => 'severe',
						'query'     => 'IiU8YSAlY2lhbGlzJSI,',
						'signature' => 'IlwvPGFbXj5dKig-fD5bXjxdKltcXHNfXFwtXFwuXSspY2lhbGlzKFtcXHNfXFwtXFwuXStbXjxdKjx8PClcXFwvYT5cL2lzIg,,',
						'class'     => 'database',
					),
				),
			),
			'v'           => 'G764J',
		);
	}



	/**
	 * Return definitions. Attempts up update tp default definitions if they are not present.
	 *
	 * @return void
	 */
	static function get_option_definitions() {
		$defs = self::get_option( 'definitions' );
		if ( ! $defs ) {
			self::update_option_definitions( self::get_default_definitions() );
			return self::get_option( 'definitions' );
		}
		return $defs;
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
	 * Delete definitions
	 *
	 * @return void
	 */
	static function delete_option_definitions() {
		return self::delete_option( 'definitions' );
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
		// self::flog( 'getting option: ' . self::$opt_name . '_' . $option );
		$result = get_option( self::$opt_name . '_' . $option );
		// self::flog( 'option value: ' );
		// self::flog( $result );
		return $result;
	}

	/**
	 * Update our option in the database
	 *
	 * @param [type] $option
	 * @param [type] $value
	 * @return void
	 */
	static function update_option( $option, $value ) {
		// self::flog( 'updating option: ' . self::$opt_name . '_' . $option );
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

mss_utils::get_instance();
// $mss_utils = new mss_utils();
