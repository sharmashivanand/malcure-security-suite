<?php
// Extensible class that handles malware scan processing
class malCure_Scanner {

	public $filemaxsize = 10800000;

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

		if ( self::is_valid_file( $file ) ) {
			$status = array(
				'severity' => '',
				'label'    => '',
			);
			if ( $this->in_core_dir( $file ) ) { // since we are scanning this file

            }
            $contents = @file_get_contents( $file );
            if(empty($contents)){
                die();
            }
            $definitions = malCure_Utils::get_malware_file_definitions();            
			foreach ( $definitions as $definition => $signature ) {
				if ( $signature['class'] == 'htaccess' && $ext != 'htaccess' ) {
					continue;
				}				
				$matches  = @preg_match( malCure_Utils::decode( $signature['signature'] ), $contents, $found );
				$pcre_err = preg_last_error();
				if ( $pcre_err != 0 ) {
					continue;
				}
				if ( $matches >= 1 ) {
					if ( in_array( $signature['severity'], array( 'severe', 'high' ) ) ) {
						// $this->update_setting( 'infected', true );
					}
					malCure_Utils::flog( 'INFECTED!!! ' . $file );
					return array(
						'id'       => $definition,
						'severity' => $signature['severity'],
						'info'     => $signature['severity'],
					);
				}
			}

			// file is clean
			$checksums = malCure_Utils::fetch_checksums();
			$md5       = @md5_file( $file );
			if ( $md5 ) {
				$checksums[ malCure_Utils::normalize_path( $file ) ] = $md5;
			}
			// malCure_Utils::flog( 'GENERATING CHECKSUM FOR FILE: ' . $file );
			malCure_Utils::update_setting( 'checksums', $checksums );
			return array(
				'id'       => '',
				'severity' => '',
				'info'     => '',
			);
		} else {
			malCure_Utils::flog( 'scan_file ! is_valid_file: ' . $file );
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

		// return $this->check_valid_file(false, $file);
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
