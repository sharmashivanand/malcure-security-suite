<?php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class MSS_CLI {
		function dump( $args, $assoc_args ) {
			// $scans = malCure_Utils::get_setting( 'scan' );
			$opt = get_option( 'MSS_scans' );
			krsort( $opt );
			WP_CLI::log( print_r( $opt, 1 ) );
		}
	}
	WP_CLI::add_command( 'mss', 'MSS_CLI' );
}
