<?php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class MSS_CLI {
		function dump( $args, $assoc_args ) {

			$opt = malCure_Utils::get_option( 'MSS_scans' );
			krsort( $opt );
			WP_CLI::log( print_r( $opt, 1 ) );
		}
	}
	WP_CLI::add_command( 'mss', 'MSS_CLI' );
}
