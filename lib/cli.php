<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class MSS_CLI {
		function test( $args, $assoc_args ) {
			WP_CLI::log( $_SERVER['REQUEST_TIME'] );
		}

		function dump( $args, $assoc_args ) {
			global $wpdb;
			// $table  = $wpdb->prefix . 'mss_checksums';
			// $delete = $wpdb->query( "TRUNCATE TABLE $table" );
			// $table  = $wpdb->prefix . 'mss_files';
			// $delete = $wpdb->query( "TRUNCATE TABLE $table" );

			$starttime = new DateTime( 'now' );
			$opt = wp_remote_get(
				admin_url( 'admin-ajax.php?action=mss_start_scan' ),
				array(
					'timeout'  => 0.01,
					'blocking' => false,
				)
			);

			// krsort( $opt );
			// WP_CLI::log( print_r( $opt, 1 ) );
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
