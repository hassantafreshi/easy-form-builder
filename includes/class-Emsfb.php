<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class Emsfb {
	/**
	 * Emsfb constructor.
	 */
	public function __construct() {
		$this->set_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define Constants.
	 */
	private function set_constants() {
		define( 'Emsfb_ABSPATH', plugin_dir_path( EMSFB_PLUGIN_FILE ) );
		define( 'Emsfb_URL', plugin_dir_url( dirname( __FILE__ ) ) );
	}

	/**
	 * Initial plugin setup.
	 */
	private function init_hooks() {
		register_activation_hook( EMSFB_PLUGIN_FILE, array( '\Emsfb\Install', 'install' ) );

		// Load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );
		
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
	//	echo "------------------------------------->" .__('Define','Emsfb');
	
		load_plugin_textdomain( 'Emsfb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Includes classes and functions.
	 */
	public function includes() {
		require_once Emsfb_ABSPATH . 'includes/class-Emsfb-install.php';
		if ( is_admin() ) {
			require_once Emsfb_ABSPATH . 'includes/admin/class-Emsfb-admin.php';
			require_once Emsfb_ABSPATH . 'includes/admin/class-Emsfb-create.php';
			//require_once Emsfb_ABSPATH . 'includes/functions.php';		
		} else {
			require_once Emsfb_ABSPATH . 'includes/class-Emsfb-public.php';
		}

		require_once Emsfb_ABSPATH . 'includes/class-Emsfb-public.php';



		// Template functions.
	}
}