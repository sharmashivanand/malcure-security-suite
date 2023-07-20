<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class MSS_CLI {
		function dump( $args, $assoc_args ) {
			global $wpdb;
			$table  = $wpdb->prefix . 'mss_checksums';
			$delete = $wpdb->query( "TRUNCATE TABLE $table" );
			$table  = $wpdb->prefix . 'mss_files';
			$delete = $wpdb->query( "TRUNCATE TABLE $table" );

			$starttime = new DateTime( 'now' );
			// $opt       = mss_utils::update_checksums_themes();
			// $opt = mss_utils::update_checksums_plugins();
			// krsort( $opt );
			WP_CLI::log( print_r( $opt, 1 ) );
			$endtime        = new DateTime( 'now' );
			$interval       = $endtime->diff( $starttime );
			$elapsedHours   = $interval->h; // Elapsed hours
			$elapsedMinutes = $interval->i; // Elapsed minutes
			$elapsedSeconds = $interval->s; // Elapsed seconds

			$elapsedTime = sprintf( '%02d:%02d:%02d', $elapsedHours, $elapsedMinutes, $elapsedSeconds );

			echo "Elapsed time: $elapsedTime";
		}
	}
	WP_CLI::add_command( 'mss', 'MSS_CLI' );
}
