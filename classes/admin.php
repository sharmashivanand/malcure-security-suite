<?php

abstract class MSS_Admin {

	public $menu_ops;
	public $page_id;
	public $pagehook;

	public function init() {

		add_action( 'admin_menu', array( $this, 'maybe_add_main_menu' ), 5 );
		add_action( 'admin_menu', array( $this, 'maybe_add_first_submenu' ), 5 );
		add_action( 'admin_menu', array( $this, 'maybe_add_submenu' ) );
		add_action( 'admin_init', array( $this, 'load_resources' ) );

		add_action( 'admin_init', array( $this, 'hook_meta_boxes' ) );
		// add_action( 'load-' . $this->pagehook, array( $this, 'metaboxes' ) );
	}

	function hook_meta_boxes() {
		add_action( 'load-' . $this->pagehook, array( $this, 'add_meta_boxes' ) );
		add_action( 'load-' . $this->pagehook, array( $this, 'do_meta_boxes' ) );
		// echo 'load-' . $this->pagehook . '-add_action-' . PHP_EOL;
	}

	function add_meta_boxes() {
		malCure_Utils::llog( __FUNCTION__ );
		add_action( 'add_meta_boxes', array( $this, 'inject_metaboxes' ) );
	}

	function do_meta_boxes() {
		malCure_Utils::llog( __FUNCTION__ );
		do_action( 'add_meta_boxes', $this->pagehook, '' );
	}

	function inject_metaboxes() {
		echo $this->pagehook;
		add_meta_box( 'my_meta_slug_handle', 'my_meta_title', array( $this, 'do_meta_box_callback' ), $this->pagehook, 'main'  );
	}

	function do_meta_box_callback() {
		echo 'We\ll do something here!';
	}

	public function maybe_add_main_menu() {
		if ( isset( $this->menu_ops['main_menu'] ) && is_array( $this->menu_ops['main_menu'] ) ) {
			$menu = wp_parse_args(
				$this->menu_ops['main_menu'],
				array(
					'page_title' => '',
					'menu_title' => '',
					'capability' => 'activate_plugins',
					'icon_url'   => '',
					'position'   => '',
				)
			);

			$this->pagehook = add_menu_page( $menu['page_title'], $menu['menu_title'], $menu['capability'], $this->page_id, array( $this, 'settings_page' ), $menu['icon_url'], $menu['position'] );
			// var_dump( 'load-' . $this->pagehook );
		}
	}

	function maybe_add_first_submenu() {
		// add_submenu_page
		if ( isset( $this->menu_ops['first_submenu'] ) && is_array( $this->menu_ops['first_submenu'] ) ) {
			$menu = wp_parse_args(
				$this->menu_ops['first_submenu'],
				array(
					'page_title' => '',
					'menu_title' => '',
					'capability' => 'activate_plugins',
				)
			);

			$this->pagehook = add_submenu_page( $this->page_id, $menu['page_title'], $menu['menu_title'], $menu['capability'], $this->page_id, array( $this, 'settings_page' ) );
			// var_dump( 'load-' . $this->pagehook );
		}
	}

	function maybe_add_submenu() {
		// add_submenu_page
		if ( isset( $this->menu_ops['submenu'] ) && is_array( $this->menu_ops['submenu'] ) ) {
			$menu = wp_parse_args(
				$this->menu_ops['submenu'],
				array(
					'parent_slug' => '',
					'page_title'  => '',
					'menu_title'  => '',
					'capability'  => 'activate_plugins',
				)
			);

			$this->pagehook = add_submenu_page( $menu['parent_slug'], $menu['page_title'], $menu['menu_title'], $menu['capability'], $this->page_id, array( $this, 'settings_page' ) );
			// var_dump( 'load-' . $this->pagehook );
		}
	}

	function load_resources() {

		// Hook scripts method.
		if ( method_exists( $this, 'scripts' ) ) {
			add_action( "load-{$this->pagehook}", array( $this, 'scripts' ) );
		}

		// Hook styles method.
		if ( method_exists( $this, 'styles' ) ) {
			add_action( "load-{$this->pagehook}", array( $this, 'styles' ) );
		}

	}

	public function scripts() {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
	}

	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="container">
			</div>
			<div id="poststuff">
				<div class="metabox-holder columns-2" id="post-body">
					<div class="postbox-container" id="post-body-content">
						<?php do_meta_boxes( $this->pagehook, 'main', null ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

}

class mss_test_meta extends MSS_Admin {
	public $page_id = 'mss_tester_';
	// public $pagehook = 'mss_tester_';
	public $menu_ops = array(
		'main_menu'     => array(
			'sep'        => array(
				'sep_position'   => '58.995',
				'sep_capability' => 'activate_plugins',
			),
			'page_title' => 'Theme Settings',
			'menu_title' => 'Genesis',
			'capability' => 'activate_plugins',
			// 'icon_url'   => GENESIS_ADMIN_IMAGES_URL . '/genesis-menu.png',
			'position'   => '58.996',
		),
		'first_submenu' => array( // Do not use without 'main_menu'.
			'page_title' => 'Theme Settings',
			'menu_title' => 'Theme Settings',
			'capability' => 'activate_plugins',
		),
		/*
		'submenu'       => array(
			'parent_slug' => 'edit.php?post_type=boo',
			'page_title'  => 'Archive Settings',
			'menu_title'  => 'Archive Settings',
			'capability'  => 'manage_options',
		),
		*/
	);
	function __construct() {
		$this->type = 7;
		add_action( $this->pagehook, array( $this, 'do_metaboxes' ) );
		$this->init();
	}

	function do_metaboxes() {
		echo 1234;
	}

}

new mss_test_meta();
