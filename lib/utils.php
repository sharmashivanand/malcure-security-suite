<?php

class malCure_Scanner {

	/**
	 * Initialize with api credentials. First / Last name, email are must
	 *
	 * @param [type] $arrCreds
	 */
	function __construct( $arrCreds = false ) {
		$this->set_api( $arrCreds ); // do we need this?
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
		$definitions = malCure_Utils::get_setting( 'malware-signatures' );
		if ( ! $definitions ) {

		}
	}

	function get_files( $path ) {
		$allfiles = new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS );

		$nbfiles = 0;
		foreach ( new RecursiveIteratorIterator( $allfiles ) as $filename => $cur ) {

			$nbfiles++;
			$files[] = $filename;
		}
		return array(
			'total_files' => $nbfiles,
			'files'       => $files,
		);
	}

	function scan_files( $arrFiles ) {

	}

	function scan_file( $file ) {

	}

	function scan_contents( $arrContents ) {

	}

	function scan_content( $content ) {

	}

	function llog( $str, $return = false ) {
		if ( $return ) {
			return '<pre>' . print_r( $str, 1 ) . '</pre>';
		} else {
			echo '<pre>' . print_r( $str, 1 ) . '</pre>';
		}
	}

	function uencode( $data ) {
		return urlencode( base64_encode( json_encode( $data ) ) );
	}

	function udecode( $data ) {
		return urldecode( base64_decode( urldecode( $data ) ) );
	}

}

class malCure_Utils {
	static $opt_name = 'MSS';

	function __construct() {
		// malCure_Utils::opt_name = 'MSS';
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
		return get_plugin_data( MSS_FILE, false, false );
	}

	static function get_files( $path = false ) {

		if ( ! $path ) {
			$path = ABSPATH;
		}

		$allfiles = new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS );
		$nbfiles  = 0;
		$files    = array();
		foreach ( new RecursiveIteratorIterator( $allfiles ) as $filename => $cur ) {
			$nbfiles++;
			$files[] = $filename;
		}
		return array(
			'total_files' => $nbfiles,
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
		$data = self::encode(
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

		$url = add_query_arg(
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
	 * Fetch definitions from the api endpoint
	 *
	 * @return array definitions
	 */
	static function fetch_definitions() {
		//$creds = self::$creds;

		$args          = array(
			'cachebust'   => time(),
			'wpmr_action' => 'update-definitions',
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
		return trailingslashit( MSS_API_EP ) . '?' . urldecode( http_build_query( $args ) );

		return wp_remote_get( MSS_API_EP, $creds );
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

}
