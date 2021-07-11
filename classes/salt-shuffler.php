<?php

class MI_Salt_Shuffler {

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
		add_action( 'wp_ajax_nsmi_shuffle_salts', array( $this, 'nsmi_shuffle_salts' ) );
		add_action( 'wp_ajax_nopriv_nsmi_shuffle_salts', '__return_false' );
		add_action( 'nsmi_admin_scripts', array( $this, 'footer_scripts' ) );
		add_action( 'MI_security_suite_add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	function add_meta_boxes() {
		add_meta_box( 'nsmi_salt_shuffler', 'Salt Shuffler', array( $this, 'nsmi_salt_shuffler_ui' ), $GLOBALS['MI_security_suite']['pagehook'], 'main' );
	}

	function nsmi_salt_shuffler_ui(){ ?>
		<p>WordPress salts make your passwords harder to crack. Shuffling WordPress salts will automatically log everyone out of your website, forcing them to relogin. Take it with a pinch of salt!</p>
		<input class="button-primary nsmi_action" value="Shuffle Salts" id="nsmi_shuffle_salts" type="submit" />
		<div id="nsmi_shuffle_salts_status" class="nsmi_status"></div>
		<?php
	}

	function footer_scripts() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#nsmi_shuffle_salts').click(function(e){
				e.preventDefault();
				nsmi_shuffle_salts = {
					nsmi_shuffle_salts_nonce: '<?php echo wp_create_nonce( 'nsmi_shuffle_salts' ); ?>',
						action: "nsmi_shuffle_salts",
						cachebust: Math.floor((new Date()).getTime() / 1000),
					};
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: nsmi_shuffle_salts,
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

							$('#nsmi_shuffle_salts_status').html('<p class="nsmi_success">'+data.data+'</p>');
							console.log('WordPress successfully executed the requested action.');
						} else {
							$('#nsmi_shuffle_salts_status').html('<p class="nsmi_error">'+data.data+'</p>');
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
							$('#nsmi_shuffle_salts_status').html('<p class="nsmi_error">'+ errorThrown + '</p>');
						}
						else {
							$('#nsmi_shuffle_salts_status').html('<p class="nsmi_error">Failed to execute the requested action.</p>');
						}
					}
				}); // ajax post
				return false;
			});

		});
		</script>
		<?php
	}

	function nsmi_shuffle_salts() {

		WP_Filesystem();
		global $wp_filesystem;
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
			wp_send_json_success( 'Salts shuffled. Please login again.' );
		} else {
			wp_send_json_error( 'Failed to write to wp-config.php.' );
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

MI_Salt_Shuffler::get_instance();
