<?php
// Extensible class that handles malware scan processing
class MI_Scanner {

	public $filemaxsize = 1111111;
	
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

	function get_files( $path = false ) {
		return mss_utils::get_files();
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
			if ( empty( $contents ) ) {
				return;
			}
			// $s = microtime(1);
			$definitions = $this->definitions;// self::get_malware_file_definitions();
			foreach ( $definitions as $definition => $signature ) {
				if ( $signature['class'] == 'htaccess' && $ext != 'htaccess' ) {
					continue;
				}
				$matches  = @preg_match( mss_utils::decode( $signature['signature'] ), $contents, $found );
				$pcre_err = preg_last_error();
				if ( $pcre_err != 0 ) {
					continue;
				}
				if ( $matches >= 1 ) {
					if ( in_array( $signature['severity'], array( 'severe', 'high' ) ) ) {
					}
					return array(
						'id'       => $definition,
						'severity' => $signature['severity'],
						'info'     => $signature['severity'],
					);
				}
			}
			$checksums = mss_utils::get_option_checksums_generated();
			$md5       = @md5_file( $file );
			if ( $md5 ) {
				$checksums[ mss_utils::normalize_path( $file ) ] = $md5;
			}
			mss_utils::update_option_checksums_generated( $checksums );
			return array(
				'id'       => '',
				'severity' => '',
				'info'     => '',
			);
		} else {
		}
	}

	function is_valid_file( $file ) {
		if ( file_exists( $file ) && // Check if file or dir exists
			is_file( $file ) && // Check if is actually a file
			filesize( $file ) && // check if the file is not empty
			filesize( $file ) <= $this->filemaxsize // Check if file-size qualifies
			) {
			return true;
		}
		return false;
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

	function scan_contents( $arrContents ) {
	}

	function scan_content( $content ) {
	}

	function get_all_definitions() {
		$definitions = self::get_definitions_data();
		if ( $definitions ) {
			return $definitions;
		}
	}

	/**
	 * Gets all definitions excluding version
	 *
	 * @return void
	 */
	static function get_definitions_data() {
		$defs = mss_utils::get_option_definitions();
		if ( ! empty( $defs['malware'] ) ) {
			return $defs['malware'];
		}
	}

	static function get_definition_version() {
		$defs = self::get_all_definitions();
		if ( ! empty( $defs['v'] ) ) {
			return $defs['v'];
		}
	}

	/**
	 * Gets malware definitions for files only
	 */
	static function get_malware_file_definitions() {
		$defs = self::get_definitions_data();
		if ( ! empty( $defs['files'] ) ) {
			return $defs['files'];
		}
	}

	/**
	 * Gets malware definitions for database only
	 *
	 * @return void
	 */
	static function get_malware_db_definitions() {
		$defs = self::get_definitions_data();
		if ( ! empty( $defs['db'] ) ) {
			return $defs['db'];
		}
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
}
