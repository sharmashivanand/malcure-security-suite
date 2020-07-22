<?php

class malCure_Scanner {

	/**
	 * Initialize with api credentials. First / Last name, email are must
	 *
	 * @param [type] $arrCreds
	 */
	function __construct( $arrCreds ) {
		$this->set_api( $arrCreds );
	}

	/**
	 * Set up credentials for use later
	 *
	 * @param [type] $creds
	 * @return void
	 */
	function set_api( $creds ) {

		$this->creds = $creds;

	}

	/**
	 * Fetch definitions from the api endpoint
	 *
	 * @return array definitions
	 */
	function get_definitions() {
		$creds = $this->creds;
		return wp_remote_get( 'https://wp-malware-removal.com', $creds );
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

	function __construct() {

	}

	static function get_files( $path = false ) {

		if(! $path ) {
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

	function get_setting( $setting ) {
		$settings = get_option( 'MSS' );
		return isset( $settings[ $setting ] ) ? $settings[ $setting ] : false;
	}

	function update_setting( $setting, $value ) {
		$settings = get_option( 'MSS' );
		if ( ! $settings ) {
			$settings = array();
		}
		$settings[ $setting ] = $value;
		
		return update_option( 'MSS', $settings );
	}

	function delete_setting( $setting ) {
		$settings = get_option( 'MSS' );
		if ( ! $settings ) {
			$settings = array();
		}
		unset( $settings[ $setting ] );
		update_option( 'MSS', $settings );
	}

}
