<?php
/**
 * WooCommerce NMI (Network Merchants) Gateway
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 *
 * @package   woofunnels-woocommerce-nmi-gateway
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;


use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * # WooCommerce NMI (Network Merchants) Gateway Main Plugin Class
 *
 * ## Plugin Overview
 *
 * This plugin adds NMI as a payment gateway. Logged in customers' credit cards are saved to the NMI vault by default.
 *
 * ## Admin Considerations
 *
 * A user view/edit field is added for the NMI customer ID so it can easily be changed by the admin.
 *
 * ## Frontend Considerations
 *
 * Both the payment fields on checkout (and checkout->pay) and the My cards section on the My Account page are template
 * files for easy customization.
 *
 * ## Database
 *
 * ### Global Settings
 *
 * + `nmi_gateway_woocommerce_settings` - the serialized woofunnels-woocommerce-nmi-gateway settings array
 *
 * ### Options table
 *
 * ### Order Meta
 *
 */
class NMI_Gateway_Woocommerce extends NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Plugin {

	/** plugin version number */
	const VERSION = '1.8.5';

	/** @var NMI_Gateway_Woocommerce single instance of this plugin */
	protected static $instance;

	/** plugin id*/
	const PLUGIN_ID = 'nmi_gateway_woocommerce';

	/** plugin slug*/
	const PLUGIN_SLUG = 'woofunnels-woocommerce-nmi-gateway';

	/** credit card gateway class name */
	const CREDIT_CARD_GATEWAY_CLASS_NAME = 'NMI_Gateway_Woocommerce_Credit_Card';

	/** credit card gateway ID */
	const CREDIT_CARD_GATEWAY_ID = 'nmi_gateway_woocommerce_credit_card';

	/** @var \NMI_GATEWAY_WOOCOMMERCE the frontend instance */
	protected $frontend;

	/**
	 * Initializes the plugin
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct( self::PLUGIN_ID, self::VERSION, array(
			'text_domain'  => self::PLUGIN_SLUG,
			'gateways'     => array(
				self::CREDIT_CARD_GATEWAY_ID => self::CREDIT_CARD_GATEWAY_CLASS_NAME,
			),
			'require_ssl'  => false,
			'supports'     => array(
				self::FEATURE_CAPTURE_CHARGE,
				self::FEATURE_MY_PAYMENT_METHODS,
				self::FEATURE_CUSTOMER_ID,

			),
			'dependencies' => apply_filters( self::CREDIT_CARD_GATEWAY_ID . '_dependencies', array( 'curl', 'dom', 'hash', 'openssl' ) ),
		) );

		// include required files
		$this->includes();

	}

	/**
	 * Include required files
	 *
	 * @since 1.0.0
	 */
	public function includes() {

		//vendor path folder
		$vendor_path = $this->get_plugin_path() . '/vendor';

		//nmi wp remote request class
		require_once( $vendor_path . '/nmi/nmi_gateway_woocommerce_remote_request.php' );

		//nmi wp remote response class
		require_once( $vendor_path . '/nmi/nmi_gateway_woocommerce_remote_response.php' );

		// gateways
		require_once( $this->get_plugin_path() . '/class-nmi-gateway-woocommerce-woofunnels-support.php' );
		require_once( $this->get_plugin_path() . '/includes/class-nmi-gateway-woocommerce-base.php' );
		require_once( $this->get_plugin_path() . '/includes/class-nmi-gateway-woocommerce-credit-card.php' );

		if ( class_exists( 'WFOCU_Gateway' ) ) {
			require_once( $this->get_plugin_path() . '/includes/class-nmi-gateway-woocommerce-upstroke-compatibility.php' );

			add_action( 'init', array( $this, 'init_upstroke_compatibility' ) );
		}

		//NMI logger file
		require_once( $this->get_plugin_path() . '/includes/class-nmi-gateway-woocommerce-logger.php' );

		// payment method
		require_once( $this->get_plugin_path() . '/includes/class-nmi-gateway-woocommerce-payment-method-handler.php' );
		require_once( $this->get_plugin_path() . '/includes/class-nmi-gateway-woocommerce-payment-method.php' );

	}

	public function init_upstroke_compatibility() {
		//Adding this gateway on global settings on upstroke admin page
		add_filter( 'wfocu_wc_get_supported_gateways', array( $this, 'wfocu_nmi_gateway_woocommerce_integration' ), 10, 1 );

	}

	/**
	 * Adding gateways name for choosing on UpStroke global settings page
	 */
	public function wfocu_nmi_gateway_woocommerce_integration( $gateways ) {

		$gateways[ NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID ] = 'NMI_Gateway_Woocommerce_Upstroke_Compatibility';

		return $gateways;
	}

	/**
	 * Main NMI_Gateway_Woocommerce Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see nmi_gateway_woocommerce_cc()
	 * @return NMI_Gateway_Woocommerce
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Checks if a supported integration is activated (Subscriptions or Pre-Orders)
	 * and adds a notice if a gateway supports the integration *and* tokenization,
	 * but tokenization is not enabled
	 *
	 * @since 4.0.0
	 */
	protected function add_integration_requires_tokenization_notices() {

		// either integration requires tokenization
		if ( $this->is_subscriptions_active() || $this->is_pre_orders_active() ) {

			foreach ( $this->get_gateways() as $gateway ) {

				$tokenization_supported_but_not_enabled = $gateway->supports_tokenization() && ! $gateway->tokenization_enabled();

				// subscriptions
				if ( $this->is_subscriptions_active() && $gateway->is_enabled() && $tokenization_supported_but_not_enabled ) {

					/* translators: Placeholders: %1$s - payment gateway title (such as Authorize.net, Braintree, etc), %2$s - <a> tag, %3$s - </a> tag */
					$message = sprintf( esc_html__( '%1$s is inactive for subscription transactions. Please %2$senable tokenization%3$s to activate %1$s for Subscriptions.', 'woocommerce-plugin-framework' ), $gateway->get_method_title(), '<a href="' . $this->get_payment_gateway_configuration_url( $gateway->get_id() ) . '">', '</a>' );

					// add notice -- allow it to be dismissed even on the settings page as the admin may not want to use subscriptions with a particular gateway
					$this->get_admin_notice_handler()->add_admin_notice( $message, 'subscriptions-tokenization-' . $gateway->get_id(), array(
						'always_show_on_settings' => false,
						'notice_class'            => 'error',
						'dismissible'             => false,
					) );
				}
			}
		}
	}


	/**
	 * Gets the frontend class instance.
	 *
	 * @return NMI_Gateway_Woocommerce
	 */
	public function get_frontend_instance() {
		return $this->frontend;
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'XL NMI Gateway for WooCommerce', 'woofunnels-woocommerce-nmi-gateway' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return NMI_GATEWAY_WOOCOMMERCE_FILE;
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'https://buildwoofunnels.com/documentation/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {
		return 'https://buildwoofunnels.com/support/';
	}


	/**
	 * Determines if WooCommerce is active.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_woocommerce_active() {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}


} // end \NMI_Gateway_Woocommerce


/**
 * Returns the One True Instance of NMI_Gateway_Woocommerce
 *
 * @since 2.2.0
 * @return NMI_Gateway_Woocommerce
 */
function nmi_gateway_woocommerce_cc() {

	return NMI_Gateway_Woocommerce::instance();
}
