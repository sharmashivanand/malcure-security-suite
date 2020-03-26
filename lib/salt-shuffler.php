<?php

class malCure_Salt_Shuffler {

	static function get_instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
			$instance->init();
		}
		return $instance;
	}

	private function __construct() {
	}

	function init() {
		add_action( 'mss_settings_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_ajax_mss_shuffle_salts', array( $this, 'mss_shuffle_salts' ) );
		add_action( 'wp_ajax_nopriv_mss_shuffle_salts', '__return_false' );
		add_action( 'mss_admin_scripts', array( $this, 'js' ) );

	}

	function admin_menu() {
		add_submenu_page(
			'_mss',  // parent_slug
			'malCure Salt Shuffler', // page_title
			'Salt Shuffler', // menu_title
			MSS_GOD, // capability
			'salt_shuffler_mss',
			array( $this, 'salt_shuffler_mss_page' )
		);
    }

	function salt_shuffler_mss_page() {
		?>
		<div class="wrap">
		<h1>malCure Salts Shuffler</h1>
			<div class="container">
			<table id="mss_utils">
				<tr><td><input class="button-primary mss_action" value="Shuffle Salts" id="mss_shuffle_salts" type="submit" /></td><td><p>WordPress salts make your passwords harder to crack. Shuffling WordPress salts will automatically log everyone out of your website, forcing them to relogin. Take it with a pinch of salt!</p><div id="mss_shuffle_salts_status" class="mss_status"></div></td></tr>
			</table>
			</div>
		</div>
		<?php
    }

	function js() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#mss_shuffle_salts').click(function(e){
				e.preventDefault();
				mss_shuffle_salts = {
					mss_shuffle_salts_nonce: '<?php echo wp_create_nonce( 'mss_shuffle_salts' ); ?>',
						action: "mss_shuffle_salts",
						cachebust: Math.floor((new Date()).getTime() / 1000),
					};
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: mss_shuffle_salts,
					complete: function(jqXHR, textStatus) {
						console.log('complete');
						console.log('jqXHR');
						console.log(jqXHR);
						console.log('textStatus');
						console.log(textStatus);
					},
					success: function(data,textStatus,jqXHR) {
						console.log('success');
						console.dir('data');
						console.dir(data);
						console.dir('textStatus');
						console.dir(textStatus);
						console.dir('jqXHR');
						console.dir(jqXHR);
						if ((typeof data) != 'object') {
							console.log('invalid data');
							data = JSON.parse(data);
						}
						if (data.hasOwnProperty('success') && data.success) {
							//location.reload(true);
							$('#mss_shuffle_salts_status').html('<p class="mss_success">'+data.data+'</p>');
							console.log('WordPress successfully executed the requested action.');
						} else {
							$('#mss_shuffle_salts_status').html('<p class="mss_error">'+data.data+'</p>');
							console.log('WordPress failed to execute the requested action.');
						}
					}, // success
					error: function(jqXHR,textStatus,errorThrown) {
						console.log('error');
						console.dir('jqXHR');
						console.dir(jqXHR);
						console.dir('textStatus');
						console.dir(textStatus);
						console.dir('errorThrown');
						console.dir(errorThrown);
						if(errorThrown.length) {
							$('#mss_shuffle_salts_status').html('<p class="mss_error">'+ errorThrown + '</p>');
						}
						else {
							$('#mss_shuffle_salts_status').html('<p class="mss_error">Failed to execute the requested action.</p>');
						}
					}
				}); // ajax post
				return false;
			});
			
			//$('.mss_action').each(function(){
			//	console.log($( this ).attr( "id" ));
			//	this.click()
			//});
			//console.log('ready');
		});
		</script>
		<?php
	}
    
	function mss_shuffle_salts() {

		WP_Filesystem();
		global $wp_filesystem;
		// $config_path = $this->get_config_path();
		// wp_send_json_error(  is_writable($config_path)   );
		// $config_path = $this->get_config_path();
		// $w = $wp_filesystem->is_writable( $config_path );
		// wp_send_json('config_path:' . $config_path . '~~is_writable:' .$w);
		$config_path = $this->get_config_path();
		if ( ! $config_path ) {
			wp_send_json_error( 'Failed to get location of wp-config.php' );
		}
		$is_writable = $wp_filesystem->is_writable( $config_path );
		if ( ! $is_writable ) {
			wp_send_json_error( 'wp-config.php is not writable.' );
		}

		$config = $wp_filesystem->get_contents( $config_path );

		$defines = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		foreach ( $defines as $define ) {
			if ( empty( $salts ) ) {
				$salts = $this->generate_salt();
			}

			$salt = array_pop( $salts );

			if ( empty( $salt ) ) {
				$salt = wp_generate_password( 64, true, true );
			}

			$salt   = str_replace( '$', '\\$', $salt );
			$regex  = "/(define\s*\(\s*(['\"])$define\\2\s*,\s*)(['\"]).+?\\3(\s*\)\s*;)/";
			$config = preg_replace( $regex, "\${1}'$salt'\${4}", $config );
		}

		if ( $wp_filesystem->put_contents( $config_path, $config ) ) {
			wp_send_json_success( 'Successfully updated wp-config.php.' );
		} else {
			wp_send_json_error( 'Failed to write to cwp-config.php.' );
		}

		wp_send_json_error( 'Failed to get wp-config.php location or it is not writable.' );
	}

	function generate_salt() {
		try {
			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
			$max   = strlen( $chars ) - 1;
			for ( $i = 0; $i < 8; $i++ ) {
				$key = '';
				for ( $j = 0; $j < 64; $j++ ) {
					$key .= substr( $chars, random_int( 0, $max ), 1 );
				}
				$secret_keys[] = $key;
			}
		} catch ( Exception $ex ) {
			$secret_keys = wp_remote_get( 'https://api.wordpress.org/secret-key/1.1/salt/' );

			if ( is_wp_error( $secret_keys ) ) {
				$secret_keys = array();
				for ( $i = 0; $i < 8; $i++ ) {
					$secret_keys[] = wp_generate_password( 64, true, true );
				}
			} else {
				$secret_keys = explode( "\n", wp_remote_retrieve_body( $secret_keys ) );
				foreach ( $secret_keys as $k => $v ) {
					$secret_keys[ $k ] = substr( $v, 28, 64 );
				}
			}
		}
		return $secret_keys;
    }
    
    function get_config_path() {
		WP_Filesystem();
		global $wp_filesystem;
		$config_path = '';
		if ( $wp_filesystem->exists( ABSPATH . 'wp-config.php' ) && $wp_filesystem->is_file( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php'; // The config file resides in ABSPATH
		} elseif ( $wp_filesystem->exists( dirname( ABSPATH ) . '/wp-config.php' ) && $wp_filesystem->is_file( dirname( ABSPATH ) . '/wp-config.php' ) && ! $wp_filesystem->exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php'; // The config file resides one level above ABSPATH but is not part of another installation
		}
	}

}

malCure_Salt_Shuffler::get_instance();