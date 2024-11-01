<?php
/**
 * Plugin Name: XL NMI Gateway for WooCommerce
 * Plugin URI: https://buildwoofunnels.com/woocommerce-nmi-payment-gateway/
 * Description: Receive credit card payments using NMI (Network Merchants) Gateway with subscription support. A valid SSL certificate (for security reasons) is required for this gateway to function. Requires PHP 7.0+
 * Author: XLPlugins
 * Author URI: https://xlplugins.com/
 * Version: 2.3.1
 * Text Domain: woofunnels-woocommerce-nmi-gateway
 * Domain Path: /i18n/languages/
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   woofunnels-woocommerce-nmi-gateway
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * Requires at least: 5.0
 * Tested up to: 6.3.1
 * WC requires at least: 4.0.0
 * WC tested up to: 8.1.0
 */

defined( 'ABSPATH' ) or exit;

// Required functions
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php' );
}

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

define( 'NMI_GATEWAY_WOOCOMMERCE_FILE', __FILE__ );

define( 'NMI_GATEWAY_WOOCOMMERCE_BASENAME', plugin_basename( __FILE__ ) );

define( 'NMI_GATEWAY_WOOCOMMERCE_VERSION', '2.3.1' );
define( 'XLNMI_BWF_VERSION', '1.10.11.12' );
define( 'NMI_GATEWAY_WOOCOMMERCE_FULL_NAME', 'XL NMI Gateway for WooCommerce' );

/**
 * The plugin loader class.
 * Class NMI_Gateway_Woocommerce_Loader
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_Loader {

	/** minimum PHP version required by this plugin */
	const MINIMUM_PHP_VERSION = '7.0';

	/** minimum WordPress version required by this plugin */
	const MINIMUM_WP_VERSION = '5.0';

	/** minimum WooCommerce version required by this plugin */
	const MINIMUM_WC_VERSION = '4.0.0';

	/** SkyVerge plugin framework version used by this plugin */
	const FRAMEWORK_VERSION = '5.2.1';

	/** the plugin name, for displaying notices */
	const PLUGIN_NAME = 'XL NMI Gateway for WooCommerce';

	/** @var NMI_Gateway_Woocommerce_Loader single instance of this class */
	protected static $instance;

	/** @var array the admin notices to add */
	protected $notices = array();

	private static $_registered_entity = array(
		'active'   => array(),
		'inactive' => array(),
	);

	/**
	 * Constructs the class.
	 *
	 * @since 1.0.0
	 *
	 * NMI_Gateway_Woocommerce_Loader constructor.
	 */
	protected function __construct() {

		register_activation_hook( __FILE__, array( $this, 'activation_check' ) );

		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		// if the environment check fails, initialize the plugin
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );

			$this->load_woofunnels_core_classes();

		}

		/** Redirecting plugin to the settings page after activation */
		add_action( 'activated_plugin', array( $this, 'nmi_gateway_woocommerce_settings_redirect' ) );

		/**
		 * Loading WooFunnels core
		 */
		add_action( 'plugins_loaded', function () {
			WooFunnel_Loader::include_core();
		}, - 1 );
		if ( is_admin() ) {
			require_once( plugin_dir_path( __FILE__ ) . 'admin/upsell/class-xlnmi-upsell.php' ); // TODO: main plugin class file

		}
	}

	/**
	 * Loading woofunnels core files
	 */
	public function load_woofunnels_core_classes() {

		/** Setting Up WooFunnels Core */

		require_once( plugin_dir_path( __FILE__ ) . 'start.php' );

	}

	/**
	 * @return mixed
	 */
	public static function get_registered_class() {
		return self::$_registered_entity['active'];
	}

	/**
	 * @param $short_name
	 * @param $class
	 * @param null $overrides
	 */
	public static function register( $short_name, $class, $overrides = null ) {
		//Ignore classes that have been marked as inactive
		if ( in_array( $class, self::$_registered_entity['inactive'], true ) ) {
			return;
		}
		//Mark classes as active. Override existing active classes if they are supposed to be overridden
		$index = array_search( $overrides, self::$_registered_entity['active'], true );
		if ( false !== $index ) {
			self::$_registered_entity['active'][ $index ] = $class;
		} else {
			self::$_registered_entity['active'][ $short_name ] = $class;
		}

		//Mark overridden classes as inactive.
		if ( ! empty( $overrides ) ) {
			self::$_registered_entity['inactive'][] = $overrides;
		}
	}

	public function log( $message, $log_id = null ) {
		NMI_Gateway_Woocommerce_Logger::log( $message, false, 'info' );
	}

	/**
	 * Added redirection on plugin activation
	 *
	 * @param $plugin
	 */
	public function nmi_gateway_woocommerce_settings_redirect( $plugin ) {
		if ( is_woocommerce_active() && class_exists( 'WooCommerce' ) ) {
			if ( $plugin === plugin_basename( __FILE__ ) && 'blank' === get_option( 'bwf_is_opted', 'blank' ) ) {
				wp_redirect( add_query_arg( array(
					'page' => 'woofunnels-woocommerce-nmi-gateway',
				), admin_url( 'admin.php' ) ) );
				exit;
			} elseif ( $plugin === plugin_basename( __FILE__ ) ) {
				wp_redirect( add_query_arg( array(
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'nmi_gateway_woocommerce_credit_card',
				), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
	}

	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '1.0.0' );
	}

	/**
	 * Un-serializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '1.0.0' );
	}


	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init_plugin() {

		if ( ! $this->plugins_compatible() ) {
			return;
		}

		$this->load_framework();

		// load the main plugin class
		require_once( plugin_dir_path( __FILE__ ) . 'class-nmi-gateway-woocommerce.php' ); // TODO: main plugin class file


		// fire it up!
		nmi_gateway_woocommerce_cc();
	}


	/**
	 * Loads the base framework classes.
	 *
	 * @since 1.0.0
	 */
	protected function load_framework() {

		if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Plugin' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/class-sv-wc-plugin.php' );
		}

		// TODO: remove this if not a payment gateway
		if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Payment_Gateway_Plugin' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/payment-gateway/class-sv-wc-payment-gateway-plugin.php' );
		}
	}


	/**
	 * Gets the framework version in namespace form.
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 */
	protected function get_framework_version_namespace() {

		return 'v' . str_replace( '.', '_', $this->get_framework_version() );
	}


	/**
	 * Gets the framework version used by this plugin.
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 */
	protected function get_framework_version() {

		return self::FRAMEWORK_VERSION;
	}


	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 *
	 * @since 1.0.0
	 */
	public function activation_check() {

		if ( ! $this->is_environment_compatible() ) {

			$this->deactivate_plugin();

			wp_die( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() );
		}
	}

	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @since 1.0.0
	 */
	public function check_environment() {

		if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

			$this->deactivate_plugin();

			$this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
		}
	}


	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_notices() {

		if ( ! $this->is_wp_compatible() ) {

			$this->add_admin_notice( 'update_wordpress', 'error', sprintf( '%s requires WordPress version %s or higher. Please %s update WordPress &raquo;%s', '<strong>' . self::PLUGIN_NAME . '</strong>', self::MINIMUM_WP_VERSION, '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>' ) );
		}

		if ( ! $this->is_wc_compatible() ) {

			$this->add_admin_notice( 'update_woocommerce', 'error', sprintf( '%s requires WooCommerce version %s or higher. Please %s update WooCommerce &raquo;%s', '<strong>' . self::PLUGIN_NAME . '</strong>', self::MINIMUM_WC_VERSION, '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>' ) );
		}
	}


	/**
	 * Determines if the required plugins are compatible.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	protected function plugins_compatible() {

		return $this->is_wp_compatible() && $this->is_wc_compatible();
	}


	/**
	 * Determines if the WordPress compatible.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	protected function is_wp_compatible() {

		if ( ! self::MINIMUM_WP_VERSION ) {
			return true;
		}

		return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
	}


	/**
	 * Determines if the WooCommerce compatible.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	protected function is_wc_compatible() {

		if ( ! self::MINIMUM_WC_VERSION ) {
			return true;
		}

		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MINIMUM_WC_VERSION, '>=' );
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @since 1.0.0
	 */
	protected function deactivate_plugin() {

		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['activate'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_notice( $slug, $class, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message
		);
	}


	/**
	 * Displays any admin notices added with \SV_WC_Framework_Plugin_Loader::add_admin_notice()
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {

		foreach ( (array) $this->notices as $notice ) {

			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo "</p></div>";
		}
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * Override this method to add checks for more than just the PHP version.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	protected function is_environment_compatible() {

		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 */
	protected function get_environment_message() {

		$message = sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );

		return $message;
	}


	/**
	 * Gets the main \NMI_Gateway_Woocommerce_Loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \NMI_Gateway_Woocommerce_Loader
	 * @since 1.0.0
	 *
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

// fire it up!
NMI_Gateway_Woocommerce_Loader::instance();
