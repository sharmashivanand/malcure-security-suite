<?php
require_once 'scanner_base.php';
require_once 'cli.php';
/**
 * Common utility functions
 */
final class nsmi_utils {
	static $opt_name = 'NSMI';
	static $cap      = 'activate_plugins';
	function __construct() {
		add_filter( 'nsmi_checksums', array( $this, 'generated_checksums' ) );
	}

	static function get_instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
			$instance->init();
		}
		return $instance;
	}

	function init() {
		add_filter( 'nsmi_checksums', array( $this, 'generated_checksums' ) );
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
	
	static function append_log( $how_when_where, $msg = '' ) {
		$errors = self::get_setting( 'log' );
		if ( ! $errors ) {
			$errors = array();
		}
		$errors[ time() ] = array(
			'how' => $how_when_where,
			'msg' => $msg,
		);
		asort( $errors );
		$errors = array_slice( $errors, 0, 100 ); // limit errors to recent 100
		return self::update_setting( 'log', $errors );
	}

	/**
	 * Log message to file
	 */
	static function flog( $str, $file = '', $timestamp = false ) {
		$date = date( 'Ymd-G:i:s' ); // 20171231-23:59:59
		$date = $date . '-' . microtime( true );
		if ( $file ) {
			$file = NSMI_DIR . $file;
		} else {
			$file = NSMI_DIR . 'log.log';
		}
		if ( $timestamp ) {
			file_put_contents( $file, PHP_EOL . $date, FILE_APPEND | LOCK_EX );
		}
		$str = print_r( $str, true );
		file_put_contents( $file, PHP_EOL . $str, FILE_APPEND | LOCK_EX );
	}

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

	static function is_registered() {
		return self::get_setting( 'api-credentials' );
	}

	static function encode( $str ) {
		return strtr( base64_encode( json_encode( $str ) ), '+/=', '-_,' );
	}

	static function decode( $str ) {
		return json_decode( base64_decode( strtr( $str, '-_,', '+/=' ) ), true );
	}

	static function get_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugin_data( NSMI_FILE, false, false );
	}

	/**
	 * Returns all files at the specified path
	 *
	 * @param boolean $path
	 * @return array, file-paths and file-count
	 */
	static function get_files( $path = false ) {
		if ( ! $path ) {
			$path = ABSPATH;
		}
		$allfiles = new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS );
		$files    = array();
		foreach ( new RecursiveIteratorIterator( $allfiles ) as $filename => $cur ) {
			$files[] = $filename;
		}
		sort( $files );
		return array(
			'total_files' => count( $files ),
			'files'       => $files,
		);
	}

	/**
	 * Register with the api endpoint and save credentials
	 *
	 * @return mixed data or wp_error
	 */
	static function do_nsmi_api_register( $user ) {
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
				add_query_arg( 'reg_details', $data, NSMI_API_EP )
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
	static function get_api_url( $action ) {
		return self::build_api_url( $action );
	}

	/**
	 * Builds full URL to API Endpoint for the requested action
	 */
	static function build_api_url( $action ) {
		$args          = array(
			'cachebust'   => time(),
			'wpmr_action' => $action,
		);
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
		$args['state'] = self::encode( $state );
		return trailingslashit( NSMI_API_EP ) . '?' . urldecode( http_build_query( $args ) );
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
		if ( ! $checksums ) {
			global $wp_version;
			$checksums = get_core_checksums( $wp_version, get_locale() );
			if ( ! $checksums ) { // get_core_checksums failed
				$checksums = get_core_checksums( $wp_version, 'en_US' ); // try en_US locale
				if ( ! $checksums ) {
					$checksums = array(); // fallback to empty array
				}
			}
			$plugin_checksums = self::fetch_plugin_checksums();
			if ( $plugin_checksums ) {
				$checksums = array_merge( $checksums, $plugin_checksums );
			}
			$theme_checksums  = self::fetch_theme_checksums();
			if ( $theme_checksums ) {
				$checksums = array_merge( $checksums, $theme_checksums );
			}
			if ( $checksums ) {
				self::update_option_checksums_core( $checksums );
				return apply_filters( 'nsmi_checksums', $checksums );
			}
			return apply_filters( 'nsmi_checksums', array() );
		} else {
			return apply_filters( 'nsmi_checksums', $checksums );
		}
	}

	static function fetch_plugin_checksums() {
		$missing          = array();
		$all_plugins      = get_plugins();
		$install_path     = get_home_path();
		$plugin_checksums = array();
		foreach ( $all_plugins as $key => $value ) {
			if ( false !== strpos( $key, '/' ) ) { // plugin has to be inside a directory. currently drop in plugins are not supported
				$plugin_file  = trailingslashit( dirname( NSMI_DIR ) ) . $key;
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
		$extras = self::get_pro_checksums( $missing );
		if ( $extras ) {
			$plugin_checksums = array_merge( $plugin_checksums, $extras );
		}
		return $plugin_checksums;
	}

	static function fetch_theme_checksums(){
		$all_themes      = wp_get_themes();
		$install_path    = get_home_path();
		$theme_checksums = array();
		$theme_root      = get_theme_root();
		//$state           = $this->get_setting( 'user' );
		//$state           = $this->encode( $state );
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

	static function generated_checksums( $checksums ) {
		$generated = self::get_option_checksums_generated();
		if ( $generated && is_array( $generated ) && ! empty( $checksums ) && is_array( $checksums ) ) {
			return array_merge( $generated, $checksums );
		} else {
		}
		return $checksums;
	}

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
				$plugin_file  = trailingslashit( dirname( NSMI_DIR ) ) . $key;
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
			$severe     = array();
			$high       = array();
			$suspicious = array();
			foreach ( $definitions['definitions']['files'] as $definition => $signature ) { // always return definitions in this sequence else suspicious matches are returned first without scanning for severe infections.
				if ( $signature['severity'] == 'severe' ) {
					$severe[ $definition ] = $definitions['definitions']['files'][ $definition ];
				}
				if ( $signature['severity'] == 'high' ) {
					$high[ $definition ] = $definitions['definitions']['files'][ $definition ];
				}
				if ( $signature['severity'] == 'suspicious' ) {
					$suspicious[ $definition ] = $definitions['definitions']['files'][ $definition ];
				}
			}
			$files = array_merge( $severe, $high, $suspicious ); // always return definitions in this sequence else suspicious matches are returned first without scanning for severe infections.
			$severe     = array();
			$high       = array();
			$suspicious = array();
			foreach ( $definitions['definitions']['db'] as $definition => $signature ) { // always return definitions in this sequence else suspicious matches are returned first without scanning for severe infections.
				if ( $signature['severity'] == 'severe' ) {
					$severe[ $definition ] = $definitions['definitions']['db'][ $definition ];
				}
				if ( $signature['severity'] == 'high' ) {
					$high[ $definition ] = $definitions['definitions']['db'][ $definition ];
				}
				if ( $signature['severity'] == 'suspicious' ) {
					$suspicious[ $definition ] = $definitions['definitions']['db'][ $definition ];
				}
			}
			$db = array_merge( $severe, $high, $suspicious );
			$definitions['definitions']['files'] = $files; // array_filter because for some reason we have an empty element too
			$definitions['definitions']['db']    = $db;
			self::update_option_definitions( $definitions );
			$time = date( 'U' );
			self::update_setting( 'definitions_update_time', $time );
			return true;
		}
	}

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

	static function update_option_checksums_core( $checksums ) {
		return self::update_option( 'checksums_core', $checksums );
	}

	static function update_option_checksums_generated( $checksums ) {
		return self::update_option( 'checksums_generated', $checksums );
	}

	static function update_option_definitions( $definitions ) {
		return self::update_option( 'definitions', $definitions );
	}
	
	static function get_option_checksums_core() {
		return self::get_option( 'checksums_core' );
	}

	static function get_option_checksums_generated() {
		$checksums = self::get_option( 'checksums_generated' );
		if ( ! $checksums ) {
			return array();
		}
		return $checksums;
	}

	static function get_option_definitions() {
		return self::get_option( 'definitions' );
	}

	static function delete_option_checksums_core() {
		return self::delete_option( 'checksums_core' );
	}

	static function delete_option_checksums_generated() {
		return self::delete_option( 'checksums_generated' );
	}

	static function delete_option_definitions() {
		return self::delete_option( 'definitions' );
	}

	static function await_unlock() {
		while ( self::get_option( 'NSMI_lock' ) == 'true' ) {
			usleep( rand( 2500, 7500 ) );
		}
		self::update_option( 'NSMI_lock', 'true' );
	}

	static function do_unlock() {
		self::update_option( 'NSMI_lock', 'false' );
	}

	static function get_setting( $setting ) {
		$settings = get_option( self::$opt_name );
		return isset( $settings[ $setting ] ) ? $settings[ $setting ] : false;
	}

	static function update_setting( $setting, $value ) {
		$settings = get_option( self::$opt_name );
		if ( ! $settings ) {
			$settings = array();
		}
		$settings[ $setting ] = $value;
		update_option( self::$opt_name, $settings );
	}

	static function delete_setting( $setting ) {
		$settings = get_option( self::$opt_name );
		if ( ! $settings ) {
			$settings = array();
		}
		unset( $settings[ $setting ] );
		update_option( self::$opt_name, $settings );
	}

	static function do_maintenance() {
		self::delete_setting( 'scan_id' );
		
		return;

		// self::delete_setting( 'scan_status' );
		$lock       = self::get_setting( 'scan_id' );
		$now        = time();
		$difference = ( $now - $lock );
		$is_expired = $difference > ( 3600 * 6 ) ? 1 : 0;if ( $is_expired ) {
			self::delete_setting( 'scan_id' );
		}
		$scans = self::get_option( 'scans' );
		if ( empty( $scans ) ) { // when no scans have been run till date
			$scans = array();
		}
		$retain  = 2;
		$retain -= 1; // purge one extra so that we can make space for new scan. This way we'll end up having the same number after completion.
		if ( count( $scans ) >= $retain ) {
			$scans = array_slice( $scans, count( $scans ) - $retain, $retain, true );
		}
		self::update_option( 'scans', $scans );
	}

	static function get_option( $option ) {
		return get_option( self::$opt_name . '_' . $option );
	}

	static function update_option( $option, $value ) {
		return update_option( self::$opt_name . '_' . $option, $value );
	}

	static function delete_option( $option ) {
		return delete_option( self::$opt_name . '_' . $option );
	}
}
// nsmi_utils::get_instance();
$nsmi_utils = new nsmi_utils();
