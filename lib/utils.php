<?php

// Extensible class that handles malware scan processing
class malCure_Scanner {

	/**
	 * Initialize with api credentials. First / Last name, email are must
	 *
	 * @param [type] $arrCreds
	 */
	function __construct( $arrCreds = false ) {
		$this->set_api( $arrCreds ); // do we need this?
		$this->filemaxsize = 10800000;

		add_filter( 'mc_is_valid_file', array( $this, 'check_valid_file' ), 10, 2 );
	}

	/**
	 * Set up credentials for use later
	 *
	 * @param [type] $creds
	 * @return void
	 */
	function set_api( $creds = false ) {
		if ( ! $creds ) {
			return;
		}
		$this->creds = $creds;

	}

	function get_definitions() {
		$definitions = malCure_Utils::get_malware_definitions();
		if ( $definitions ) {
			return $definitions;
		}
	}

	function get_files( $path = false ) {
		return malCure_Utils::get_files();
	}



	/**
	 * Returns status of a scanned file
	 *
	 * @param [type] $file
	 * @return array
	 *  (
	 *      'severity' => clean || unknown || mismatch || suspicious || infected    // This can be used to identify the severity of the infection
	 *      'label' => 'unknown file found' || 'suspicious file contents' || 'severe infection found' // This can be used to present information on the UI
	 */
	function scan_file( $file ) {
		$ext = self::get_file_extension( $file );
		if ( $this->is_valid_file( $file ) ) {
			$status = array(
				'severity' => '',
				'label'    => '',
			);
			if ( $this->in_core_dir( $file ) ) { // since we are scanning this file

			}
			$definitions = self::get_malware_file_definitions();
			foreach ( $definitions as $definition => $signature ) {
				if ( $signature['class'] == 'htaccess' && $ext != 'htaccess' ) {
					continue;
				}
				$matches  = @preg_match( $this->decode( $signature['signature'] ), $GLOBALS['WPMR']['tmp']['file_contents'], $found );
				$pcre_err = preg_last_error();
				if ( $pcre_err != 0 ) {
					continue;
				}
				if ( $matches >= 1 ) {
					if ( in_array( $signature['severity'], array( 'severe', 'high' ) ) ) {
						// $this->update_setting( 'infected', true );
					}
					return array(
						'id'       => $definition,
						'severity' => $signature['severity'],
						'info'     => $signature['severity'],
					);
				}
			}
		}
	}

	function get_file_extension( $filename ) {
		$nameparts = explode( '.', ".$filename" );
		return strtolower( $nameparts[ ( count( $nameparts ) - 1 ) ] );
	}

	/**
	 * Checks if a file is inside WP core directories ( inside wp-admin or wp-includes)
	 *
	 * @param [type] $file
	 * @return true if file is inside one of core directories false otherwise
	 */
	function in_core_dir( $file ) {

		if ( strpos( $file, get_home_path() . 'wp-admin/' ) === false && strpos( $file, get_home_path() . 'wp-includes/' ) === false ) { // if the file is inside wp-admin
			return false;
		}
		return true;
	}

	function is_valid_file( $file ) {

		return apply_filters( 'mc_is_valid_file', false, $file );

	}

	/**
	 * Check if file is a valid file
	 * DO NOT CHECK is_readable here; if ! is_readable then we want to log as error somewhere else.
	 */
	function check_valid_file( $valid, $file ) {
		if ( file_exists( $file ) && // Check if file or dir exists
			is_file( $file ) && // Check if is actually a file
			filesize( $file ) <= $this->filemaxsize // Check if file-size qualifies
			) {
			return true;
		}
		return false;
	}

	function scan_contents( $arrContents ) {

	}

	function scan_content( $content ) {

	}

	function uencode( $data ) {
		return urlencode( base64_encode( json_encode( $data ) ) );
	}

	function udecode( $data ) {
		return json_decode( base64_decode( urldecode( $data ) ), 1 );
	}

}

/**
 * Common utility functions
 */
class malCure_Utils {
	static $opt_name = 'MSS';
	static $cap      = 'activate_plugins';

	function __construct() {
		// malCure_Utils::opt_name = 'MSS';
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
			return '<pre>' . print_r( $str, 1 ) . '</pre>';
		} else {
			echo '<pre>' . print_r( $str, 1 ) . '</pre>';
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
		self::append_err( $how_when_where, $msg );
		if ( $return ) {
			return '<pre>' . print_r( $str, 1 ) . '</pre>';
		} else {
			echo '<pre>' . print_r( $str, 1 ) . '</pre>';
		}
	}

	/**
	 * Log message to file
	 */
	static function flog( $str ) {
		$date = date( 'Ymd-G:i:s' ); // 20171231-23:59:59
		$date = $date . '-' . microtime( true );
		$file = MSS_DIR . 'log.log';
		file_put_contents( $file, PHP_EOL . $date, FILE_APPEND | LOCK_EX );
		// usleep( 1000 );
		$str = print_r( $str, true );
		file_put_contents( $file, PHP_EOL . $str, FILE_APPEND | LOCK_EX );
		// usleep( 1000 );
	}

	static function is_registered() {
		return self::get_setting( 'api-credentials' );
	}

	static function encode( $str ) {
		return urlencode( base64_encode( json_encode( $str ) ) );
	}

	static function decode( $str ) {
		return json_decode( base64_decode( urldecode( $str ) ), true );
	}

	static function get_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugin_data( MSS_FILE, false, false );
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
	 * Gets the definitions from the database including version
	 *
	 * @return void
	 */
	static function get_definitions() {
		return self::get_setting( 'definitions' );
	}

	static function get_definition_version() {
		return self::get_setting( 'definitions' )['v'];
	}

	/**
	 * Gets all definitions excluding version
	 *
	 * @return void
	 */
	static function get_malware_definitions() {
		return self::get_definitions()['definitions'];
	}

	/**
	 * Gets malware definitions for files only
	 */
	static function get_malware_file_definitions() {
		return self::get_malware_definitions()['files'];
		// return $definitions['files'];
	}

	/**
	 * Gets malware definitions for database only
	 *
	 * @return void
	 */
	static function get_malware_db_definitions() {
		return self::get_malware_definitions()['db'];
	}

	/**
	 * For future, match malware in user content like post content, urls etc.?
	 *
	 * @return array
	 */
	static function get_malware_content_definitions() {

	}

	/**
	 * Get firewall rules
	 *
	 * @return array
	 */
	static function get_firewall_definitions() {

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
		// return trailingslashit( MSS_API_EP ) . '?' . urldecode( http_build_query( $args ) );
		return trailingslashit( MSS_API_EP ) . '?' . urldecode( http_build_query( $args ) );
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
	static function get_checksums() {
		// $checksums = $cached ? get_transient( 'WPMR_checksums' ) : false;
		$checksums = self::get_setting( 'checksums' );
		if ( ! $checksums ) {
			global $wp_version;
			$checksums = get_core_checksums( $wp_version, get_locale() );
			if ( ! $checksums ) { // get_core_checksums failed
				$checksums = get_core_checksums( $wp_version, 'en_US' ); // try en_US locale
				if ( ! $checksums ) {
					$checksums = array(); // fallback to empty array
				}
			}
			$plugin_checksums = self::get_plugin_checksums();
			if ( $plugin_checksums ) {
				$checksums = array_merge( $checksums, $plugin_checksums );
			}
			if ( $checksums ) {
				self::update_setting( 'checksums', $checksums );
				return apply_filters( 'mss_checksums', $checksums );
			}
			return apply_filters( 'mss_checksums', array() );
		} else {
			return apply_filters( 'mss_checksums', $checksums );
		}
	}

	static function get_plugin_checksums() {
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
		$extras = self::get_pro_checksums( $missing );
		if ( $extras ) {
			$plugin_checksums = array_merge( $plugin_checksums, $extras );
		}
		return $plugin_checksums;
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

				$checksum = wp_safe_remote_get( $checksum_url );
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
			self::update_setting( 'definitions', $definitions );
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
		// $creds = self::$creds;
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
		return update_option( self::$opt_name, $settings );
	}

	static function delete_setting( $setting ) {
		$settings = get_option( self::$opt_name );
		if ( ! $settings ) {
			$settings = array();
		}
		unset( $settings[ $setting ] );
		update_option( self::$opt_name, $settings );
	}

	static function append_err( $how_when_where, $msg = '' ) {
		$errors = self::get_setting( 'errors' );
		if ( ! $errors ) {
			$errors = array();
		}
		$errors[ time() ] = array(
			'how' => $how_when_where,
			'msg' => $msg,
		);
		
		asort( $errors );

		$errors = array_slice( $errors, 0, 100 ); // limit errors to recent 100

		return update_setting( 'errors', $errors );
	}

}
