<?php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class MSS_CLI {
		function dump( $args, $assoc_args ) {
			$opt = mss_utils::get_option( 'scan' );
			krsort( $opt );
			WP_CLI::log( print_r( $opt, 1 ) );
		}
	}
	WP_CLI::add_command( 'mss', 'MSS_CLI' );
}
