<?php
defined( 'ABSPATH' ) || exit;

class NMI_Gateway_Woocommerce_WooFunnels_Support {

	public static $_instance = null;
	protected $slug = 'woofunnels-woocommerce-nmi-gateway';

	/** Can't be change this further, as is used for license activation */
	public $full_name = 'XL NMI Gateway for WooCommerce';

	protected $encoded_basename = '';

	public function __construct() {

		$this->encoded_basename = sha1( NMI_GATEWAY_WOOCOMMERCE_BASENAME );

		add_action( NMI_Gateway_Woocommerce::PLUGIN_ID . '_page_right_content', array( $this, 'nmi_gateway_woocommerce_page_right_content' ), 10 );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 80 );

		add_action( 'admin_init', array( $this, 'redirect_to_optin_when_not_opted' ) );
		add_filter( 'woofunnels_default_reason_' . NMI_GATEWAY_WOOCOMMERCE_BASENAME, function () {
			return 1;
		} );
		add_filter( 'woofunnels_default_reason_default', function () {
			return 1;
		} );

		add_filter( 'plugin_action_links_' . NMI_GATEWAY_WOOCOMMERCE_BASENAME, array( $this, 'plugin_actions' ) );

		add_filter( 'woofunnels_optin_url', function () {
			return admin_url( 'admin.php?page=' . NMI_Gateway_Woocommerce::PLUGIN_SLUG );
		} );
	}

	/**
	 * @return NMI_Gateway_Woocommerce_WooFunnels_Support|null
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}


	public function register_admin_menu() {
		$get_opted_state = get_option( 'bwf_is_opted', '' );
		if ( '' === $get_opted_state ) {
			add_menu_page( __( 'XL NMI Gateway for WooCommerce', 'woofunnels-woocommerce-nmi-gateway' ), __( 'XL NMI Gateway for WooCommerce', 'woofunnels-woocommerce-nmi-gateway' ), 'manage_woocommerce', 'woofunnels-woocommerce-nmi-gateway', array(
				$this,
				'admin_page',
			) );
		}

	}

	/**
	 * Woofunnels submenu NMI Gateway Woocommerce callback function
	 */
	public function admin_page() {
		if ( is_woocommerce_active() && class_exists( 'WooCommerce' ) ) {
			if ( 'blank' !== get_option( 'bwf_is_opted', 'blank' ) ) {
				wp_redirect( add_query_arg( array(
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID,
				), admin_url( 'admin.php' ) ) );
				exit;

			} else {
				$admin_path = plugin_dir_path( __FILE__ ) . '/admin';
				require_once( $admin_path . '/views/optin-temp.php' );
			}
		}
	}

	public function redirect_to_optin_when_not_opted() {
		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] && isset( $_GET['section'] ) && NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID === $_GET['section'] && 'blank' === get_option( 'bwf_is_opted', 'blank' ) ) {
			wp_redirect( add_query_arg( array(
				'page' => NMI_Gateway_Woocommerce::PLUGIN_SLUG,
			), admin_url( 'admin.php' ) ) );
			exit;
		}

	}

	/**
	 * Hooked over 'plugin_action_links_{PLUGIN_BASENAME}' WordPress hook to add deactivate popup support
	 *
	 * @param array $links array of existing links
	 *
	 * @return array modified array
	 */
	public function plugin_actions( $links ) {
		if ( isset( $links['deactivate'] ) ) {
			$links['deactivate'] .= '<i class="woofunnels-slug" data-slug="' . NMI_GATEWAY_WOOCOMMERCE_BASENAME . '"></i>';
		}
		return $links;
	}

}


if ( class_exists( 'NMI_Gateway_Woocommerce_WooFunnels_Support' ) ) {
	NMI_Gateway_Woocommerce_WooFunnels_Support::get_instance();
}
