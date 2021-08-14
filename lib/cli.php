<?php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class NSMI_CLI {
		function dump( $args, $assoc_args ) {
			$opt = nsmi_utils::get_option( 'scans' );
			krsort( $opt );
			WP_CLI::log( print_r( $opt, 1 ) );
		}
	}
	WP_CLI::add_command( 'mss', 'NSMI_CLI' );
}
