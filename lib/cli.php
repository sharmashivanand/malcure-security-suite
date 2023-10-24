<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class MSS_CLI {
		function test( $args, $assoc_args ) {
			WP_CLI::log( 'todo: coming soon…' );
		}
	}
	WP_CLI::add_command( 'mss', 'MSS_CLI' );
}
