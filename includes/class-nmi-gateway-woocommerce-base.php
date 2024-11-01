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
 * @package   woofunnels-woocommerce-nmi-gateway
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;
use SkyVerge\WooCommerce\PluginFramework\v5_2_1\SV_WC_Helper;

/**
 * NMI_Gateway_Woocommerce Base Gateway Class
 *
 * Handles common functionality among the Credit Card gateways
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_Base extends NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Direct {

	/** sandbox environment ID */
	const ENVIRONMENT_SANDBOX = 'sandbox';

	/** production environment ID */
	const ENVIRONMENT_PRODUCTION = 'production';

	/** @var bool collect js enabled * */
	protected $payment_api_method;

	/** @var string production tokenization key * */
	protected $production_public_key;

	/** @var string production security key * */
	protected $production_private_key;

	/** @var string live username * */
	protected $live_username;

	/** @var string live password */
	protected $live_password;

	/** @var string sandbox tokenization key * */
	protected $sandbox_public_key;

	/** @var string sandbox security key * */
	protected $sandbox_private_key;

	/** @var string sandbox username * */
	protected $sandbox_username;

	/** @var string sandbox password */
	protected $sandbox_password;

	/** @var \NMI_Gateway_Woocommerce_API instance */
	protected $api;

	/** @var array shared settings names */
	protected $shared_settings_names = array( 'live_username', 'live_password', 'sandbox_username', 'sandbox_password' );

	/**
	 * Loads the plugin configuration settings
	 *
	 * @since 2.0.0
	 */
	protected function load_settings() {
		parent::load_settings();
	}

	/**
	 * Enqueue the nmi_gateway_woocommerce_frontend.js library prior to enqueueing gateway scripts
	 */
	public function enqueue_gateway_assets() {
		if ( $this->is_available() && ( $this->is_payment_form_page() ) ) {

			wp_enqueue_script( NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '-frontend', $this->get_plugin()->get_plugin_url() . '/assets/js/nmi_gateway_woocommerce_frontend.js', array(), NMI_GATEWAY_WOOCOMMERCE_VERSION );

			if ( 'collect_js' === $this->get_payment_api_method() && ! empty( $this->get_public_key() ) ) {
				add_filter( 'script_loader_tag', array( $this, 'add_public_key_to_js' ), 10, 2 );

				wp_enqueue_script( 'xl_nmi_wc_collect-js', 'https://secure.nmi.com/token/Collect.js', '', null, true );
				wp_enqueue_script( NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '-collect-js', $this->get_plugin()->get_plugin_url() . '/assets/js/xl_wc_nmi_collect.js', array( 'xl_nmi_wc_collect-js' ), NMI_GATEWAY_WOOCOMMERCE_VERSION );
			}

			parent::enqueue_gateway_assets();
		}
	}

	public function add_public_key_to_js( $tag, $handle ) {
		if ( 'xl_nmi_wc_collect-js' !== $handle ) {
			return $tag;
		}

		$public_key = $this->get_public_key();

		return str_replace( ' src', ' data-tokenization-key="' . $public_key . '" src', $tag );
	}

	/**
	 * Validate the payment nonce exists
	 *
	 * @param $is_valid
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public function validate_payment_nonce( $is_valid ) {

		return $is_valid;
	}


	/**
	 * Determines if the authorization for an order is valid for capture.
	 *
	 * Overridden to add the capture status to legacy orders since the v1 plugin
	 * may not have set it.
	 *
	 * @param \WC_Order $order order object
	 *
	 * @return bool
	 * @see \SkyVerge\Plugin_Framework\SV_WC_Payment_Gateway::has_authorization_expired()
	 * @since 1.0.0
	 *
	 */
	public function authorization_valid_for_capture( $order ) {

		// if v1 never set the capture status, assume it has been captured
		if ( ! in_array( $this->get_order_meta( $order, 'charge_captured' ), array( 'yes', 'no' ), true ) ) {
			$this->update_order_meta( $order, 'charge_captured', 'yes' );
		}

		return parent::authorization_valid_for_capture( $order );
	}


	/**
	 * Determines if the authorization for an order has expired.
	 *
	 * Overridden to add the transaction date to legacy orders since the v1.x
	 * plugin didn't set its own transaction date meta.
	 *
	 * @param \WC_Order $order the order object
	 *
	 * @return bool
	 * @see \SkyVerge\Plugin_Framework\SV_WC_Payment_Gateway::has_authorization_expired()
	 * @since 1.0.0
	 *
	 */
	public function has_authorization_expired( $order ) {

		if ( ! $this->get_order_meta( $order, 'trans_id' ) ) {
			$this->update_order_meta( $order, 'trans_id', NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'transaction_id' ) );
		}

		$date_created = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_date_created( $order );

		if ( ! $this->get_order_meta( $order, 'trans_date' ) && $date_created ) {
			$this->update_order_meta( $order, 'trans_date', $date_created->date( 'Y-m-d H:i:s' ) );
		}

		return parent::has_authorization_expired( $order );
	}


	/**
	 *
	 * Gets the order object with data added to process a refund.
	 *
	 * Overridden to add the transaction ID to legacy orders since the v1.x
	 * plugin didn't set its own transaction ID meta.
	 *
	 * @param int|NMI_Gateway_Woocommerce_Framework\WC_Order $order
	 * @param float $amount the refund amount
	 * @param string $reason the refund reason
	 *
	 * @return NMI_Gateway_Woocommerce_Framework\WC_Order
	 * @since 1.0.0
	 * @see \SV_WC_Payment_Gateway::get_order_for_refund()
	 */
	public function get_order_for_refund( $order, $amount, $reason ) {

		$order = parent::get_order_for_refund( $order, $amount, $reason );

		if ( empty( $order->refund->trans_id ) ) {

			$order->refund->trans_id = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'transaction_id' );
		}

		return $order;
	}


	/** Tokenization methods **************************************************/


	/**
	 * NMI Gateway Woocommerce tokenizes payment methods during the transaction (if successful)
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function tokenize_with_sale() {
		return false;
	}

	/**
	 * Return the custom NMI_Gateway_Woocommerce payment tokens handler class
	 *
	 * @return \NMI_Gateway_Woocommerce_Payment_Method_Handler
	 * @since 1.0.0
	 */
	protected function build_payment_tokens_handler() {

		return new NMI_Gateway_Woocommerce_Payment_Method_Handler( $this );
	}

	/** Admin settings methods ************************************************/

	/**
	 * Adds the gateway environment form fields
	 *
	 * @param array $form_fields gateway form fields
	 *
	 * @return array $form_fields gateway form fields
	 * @since 1.0.0
	 */
	protected function add_environment_form_fields( $form_fields ) {

		$form_fields['environment']        = array(
			/* translators: environment as in a software environment (test/production) */
			'title'    => esc_html__( 'Environment', 'woofunnels-woocommerce-nmi-gateway' ),
			'type'     => 'select',
			'default'  => key( $this->get_environments() ),  // default to first defined environment
			'desc_tip' => esc_html__( 'Select the gateway environment to use for transactions.', 'woofunnels-woocommerce-nmi-gateway' ),
			'options'  => $this->get_environments(),
		);
		$form_fields['payment_api_method'] = array(
			'title'   => esc_html__( 'Payment API Method', 'woofunnels-woocommerce-nmi-gateway' ),
			'type'    => 'radio',
			'id'      => 'payment_api_method',
			'default' => 'direct_post',
			'css'     => 'margin:0',
			'options' => array(
				'direct_post' => __( 'Direct Post', 'woocommerce' ),
				'collect_js'  => __( 'Collect.js (Recommended)', 'woocommerce' ),
			),
		);

		return $form_fields;
	}

	/**
	 * Returns an array of form fields common for all gateways (Credit card in our case)
	 *
	 * @return array of form fields
	 * @see SV_WC_Payment_Gateway::get_method_form_fields()
	 * @since 1.0.0
	 */
	protected function get_method_form_fields() {

		return array(
			// production
			'production_private_key' => array(
				'title'    => esc_html__( 'Live Private Key', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'     => 'password',
				'class'    => 'environment-field production-field',
				'desc_tip' => __( 'Enter your NMI API credentials to process transactions.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'production_public_key'  => array(
				'title'       => esc_html__( 'Live Public Key', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'        => 'text',
				'class'       => 'environment-field production-field',
				'description' => sprintf( __( '<a href="https://secure.networkmerchants.com/gw/merchants/resources/integration/integration_portal.php#cjs_methodology" target="_blank">Click here </a>to find out how to get API keys.', 'woofunnels-woocommerce-nmi-gateway' ) ),
				'desc_tip'    => __( 'Enter your NMI API credentials to process transactions.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'live_username'          => array(
				'title'    => __( 'Live Username', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'     => 'text',
				'class'    => 'environment-field production-field',
				'desc_tip' => __( 'The Live Gateway Username for your NMI account.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'live_password'          => array(
				'title'    => __( 'Live Password', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'     => 'password',
				'class'    => 'environment-field production-field',
				'desc_tip' => __( 'The Live Gateway Password for your NMI account', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			// sandbox
			'sandbox_private_key'    => array(
				'title'    => esc_html__( 'Sandbox Private Key', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'     => 'password',
				'class'    => 'environment-field sandbox-field',
				'desc_tip' => __( 'Enter your NMI API credentials to process transactions.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'sandbox_public_key'     => array(
				'title'       => esc_html__( 'Sandbox Public Key', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'        => 'text',
				'class'       => 'environment-field sandbox-field',
				'description' => sprintf( __( '<a href="https://secure.networkmerchants.com/gw/merchants/resources/integration/integration_portal.php#cjs_methodology" target="_blank">Click here </a>to find out how to get API keys.', 'woofunnels-woocommerce-nmi-gateway' ) ),
				'desc_tip'    => __( 'Enter your NMI API credentials to process transactions.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'sandbox_username'       => array(
				'title'    => __( 'Sandbox Username', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'     => 'text',
				'class'    => 'environment-field sandbox-field',
				'desc_tip' => __( 'The Gateway Username for your NMI sandbox account.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'sandbox_password'       => array(
				'title'    => __( 'Sandbox Password', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'     => 'password',
				'class'    => 'environment-field sandbox-field',
				'desc_tip' => __( 'The Gateway Password for your NMI sandbox account.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'validate_processor'     => array(
				'title'       => esc_html__( 'Payment Mechanism', 'woofunnels-woocommerce-nmi-gateway' ),
				'label'       => esc_html__( 'Check this box if you want to change Payment charging mechanism to Validate API.', 'woofunnels-woocommerce-nmi-gateway' ),
				'description' => esc_html__( 'Default it is set to Authorize API. Note that not all merchant processors of NMI provide Validate API.', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc_tip'    => __( 'The payment processor supported with your NMI account', 'woofunnels-woocommerce-nmi-gateway' ),
			),
			'send_gateway_receipt'   => array(
				'title'       => esc_html__( 'Gateway Receipt', 'woofunnels-woocommerce-nmi-gateway' ),
				'label'       => esc_html__( 'Enable sending a gateway receipt.', 'woofunnels-woocommerce-nmi-gateway' ),
				'description' => esc_html__( 'If enabled, customer will receive a payment recipt on every successful transaction.', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'        => 'checkbox',
				'default'     => false,
				'desc_tip'    => __( 'Enable this to send payment receipt to the customer from gateway.', 'woofunnels-woocommerce-nmi-gateway' ),
			),
		);
	}


	/**
	 * Returns the customer ID for the given user ID. NMI provides a customer
	 * ID after creation.
	 *
	 * This is overridden to account for merchants that switched to v1 from the
	 * SkyVerge plugin, then updated old subscriptions and/or processed new
	 * subscriptions while waiting for v2.
	 *
	 * @param int $user_id WP user ID
	 * @param array $args optional additional arguments which can include: environment_id, autocreate (true/false), and order
	 *
	 * @return string payment gateway customer id
	 * @see SV_WC_Payment_Gateway::get_customer_id()
	 *
	 * @since 1.0.0
	 */
	public function get_customer_id( $user_id, $args = array() ) {

		$defaults = array(
			'environment_id' => $this->get_environment(),
			'autocreate'     => false,
			'order'          => null,
		);

		$args = array_merge( $defaults, $args );

		$customer_ids = get_user_meta( $user_id, $this->get_customer_id_user_meta_name( $args['environment_id'] ) );

		// if there is more than one customer ID, grab the latest and use it
		if ( is_array( $customer_ids ) && count( $customer_ids ) > 1 ) {

			$customer_id = end( $customer_ids );

			if ( $customer_id ) {

				$this->remove_customer_id( $user_id, $args['environment_id'] );

				$this->update_customer_id( $user_id, $customer_id, $args['environment_id'] );
			}
		}

		return parent::get_customer_id( $user_id, $args );
	}


	/**
	 * Ensure a customer ID is created in NMI for guest customers
	 *
	 * A customer ID must exist in NMI before it can be used so a guest
	 * customer ID cannot be generated on the fly. This ensures a customer is
	 * created when a payment method is tokenized for transactions such as a
	 * pre-order guest purchase.
	 *
	 * @param WC_Order $order
	 *
	 * @return bool false
	 * @since 1.0.0
	 * @see SV_WC_Payment_Gateway::get_guest_customer_id()
	 *
	 */
	public function get_guest_customer_id( WC_Order $order ) {

		// is there a customer id already tied to this order?
		if ( $customer_id = $this->get_order_meta( $order, 'customer_id' ) ) {
			return $customer_id;
		}

		// default to false as a customer must be created first
		return false;
	}

	/**
	 * Returns true if the current page contains a payment form
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_payment_form_page() {

		return ( is_checkout() && ! is_order_received_page() ) || is_checkout_pay_page() || is_add_payment_method_page();
	}


	/**
	 * Get the API object
	 *
	 * @return \NMI_Gateway_Woocommerce_API instance
	 * @see SV_WC_Payment_Gateway::get_api()
	 * @since 1.0.0
	 */
	public function get_api() {

		if ( is_object( $this->api ) ) {
			return $this->api;
		}

		//inlucdes folder path
		$includes_path = $this->get_plugin()->get_plugin_path() . '/includes';

		// main NMI Gateway Woocommerce API class
		require_once( $includes_path . '/api/class-nmi-gateway-woocommerce-api.php' );

		// response message helper
		require_once( $includes_path . '/api/class-nmi-gateway-woocommerce-api-response-message-helper.php' );

		//Request
		require_once( $includes_path . '/api/requests/abstract-nmi-gateway-woocommerce-api-request.php' );
		require_once( $includes_path . '/api/requests/class-nmi-gateway-woocommerce-api-transaction-request.php' );
		require_once( $includes_path . '/api/requests/abstract-nmi-gateway-woocommerce-api-vault-request.php' );
		require_once( $includes_path . '/api/requests/class-nmi-gateway-woocommerce-api-customer-request.php' );
		require_once( $includes_path . '/api/requests/class-nmi-gateway-woocommerce-api-payment-method-request.php' );

		//Response
		require_once( $includes_path . '/api/responses/abstract-nmi-gateway-woocommerce-api-response.php' );
		require_once( $includes_path . '/api/responses/abstract-nmi-gateway-woocommerce-api-transaction-response.php' );
		require_once( $includes_path . '/api/responses/class-nmi-gateway-woocommerce-api-credit-card-transaction-response.php' );
		require_once( $includes_path . '/api/responses/abstract-nmi-gateway-woocommerce-api-vault-response.php' );
		require_once( $includes_path . '/api/responses/class-nmi-gateway-woocommerce-api-customer-response.php' );
		require_once( $includes_path . '/api/responses/class-nmi-gateway-woocommerce-api-payment-method-response.php' );

		return $this->api = new NMI_Gateway_Woocommerce_API( $this );
	}


	/**
	 * Returns true if the current gateway environment is configured to 'sandbox'
	 *
	 * @param string $environment_id optional environment id to check, otherwise defaults to the gateway current environment
	 *
	 * @return boolean true if $environment_id (if non-null) or otherwise the current environment is test
	 * @since 1.0.0
	 * @see SV_WC_Payment_Gateway::is_test_environment()
	 *
	 */
	public function is_test_environment( $environment_id = null ) {

		// if an environment is passed in, check that
		if ( ! is_null( $environment_id ) ) {
			return self::ENVIRONMENT_SANDBOX === $environment_id;
		}

		// otherwise default to checking the current environment
		return $this->is_environment( self::ENVIRONMENT_SANDBOX );

	}

	/**
	 * Determines if this is a gateway that supports charging virtual-only orders.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function supports_credit_card_charge_virtual() {
		return $this->supports( self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL );
	}

	/**
	 * Determines if this is a gateway that supports add payment methods on my-account screen
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function supports_feature_my_payment_methods() {
		return $this->supports( self::FEATURE_MY_PAYMENT_METHODS );
	}

	/**
	 * Returns the Gateway username based on the current environment
	 *
	 * @param string $environment_id optional one of 'sandbox' or 'production', defaults to current configured environment
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 */
	public function get_gateway_username( $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		return self::ENVIRONMENT_PRODUCTION === $environment_id ? $this->live_username : $this->sandbox_username;
	}


	/**
	 * Returns the gateway password based on the current environment
	 *
	 * @param string $environment_id optional one of 'sandbox' or 'production', defaults to current configured environment
	 *
	 * @return string gateway password
	 * @since 1.0.0
	 *
	 */
	public function get_gateway_password( $environment_id = null ) {

		if ( is_null( $environment_id ) ) {
			$environment_id = $this->get_environment();
		}

		return self::ENVIRONMENT_PRODUCTION === $environment_id ? $this->live_password : $this->sandbox_password;
	}

	/**
	 * Return an array of valid NMI_Gateway_Woocommerce environments
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function get_nmi_gateway_woocommerce_environments() {
		return array(
			self::ENVIRONMENT_PRODUCTION => __( 'Live', 'woofunnels-woocommerce-nmi-gateway' ),
			self::ENVIRONMENT_SANDBOX    => __( 'Sandbox', 'woofunnels-woocommerce-nmi-gateway' )
		);
	}


	/**
	 * Outputs fields for entering credit card information.
	 *
	 * @since 1.0.0
	 */
	public function form() {
		wp_enqueue_script( 'wc-credit-card-form' );

		$fields = array();

		$cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'csc' ) . ' style="width:100px" />
		</p>';

		$default_fields = array(
			'card-number-field' => '<p class="form-row form-row-wide xl-nmi-gateway-for-woocommerce">
				<label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'account-number' ) . ' />
			</p>',
			'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YY)', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'expiry' ) . ' />
			</p>',
		);

		if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			$default_fields['card-cvc-field'] = $cvc_field;
		}

		$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>

		<fieldset id="wc-<?php echo esc_attr( NMI_Gateway_Woocommerce::PLUGIN_ID ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
			<?php
			foreach ( $fields as $field ) {
				echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			}
			?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php

		if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
			echo '<fieldset>' . $cvc_field . '</fieldset>'; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
		}
	}

	public function xl_nmi_collect_js_form() {
		?>
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>

			<div class="form-row form-row-wide">
				<label for="xl-wc-nmi-card-number"><?php esc_html_e( 'Card Number', 'woocommerce' ); ?> <span class="required">*</span></label>
				<div class="xl-wc-nmi-card-group">
					<div id="xl-wc-nmi-card-number" class="xl-wc-nmi-card-number-field"></div>
					<i class="xl-wc-nmi-card-brand xl-wc-nmi-card-brand" alt="Card Number"></i>
				</div>
			</div>

			<div class="form-row form-row-first">
				<label for="xl-wc-nmi-card-expiry"><?php esc_html_e( 'Expiry (MM/YY)', 'woocommerce' ); ?> <span class="required">*</span></label>
				<div id="xl-wc-nmi-card-expiry" class="xl-wc-nmi-card-expiry-field"></div>
			</div>

			<div class="form-row form-row-last">
				<label for="xl-wc-nmi-card-cvv"><?php esc_html_e( 'Card Code (CVC)', 'woocommerce' ); ?> <span class="required">*</span></label>
				<div id="xl-wc-nmi-card-cvv" class="xl-wc-nmi-card-cvv-field"></div>
			</div>
			<div class="clear"></div>

			<div class="xl-wc-nmi-source-errors" role="alert"></div>
			<br/>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	/**
	 * Output field name HTML
	 *
	 * Gateways which support tokenization do not require names - we don't want the data to post to the server.
	 *
	 * @param string $name Field name.
	 *
	 * @return string
	 * @since  1.0.0
	 *
	 */
	public function field_name( $name ) {
		return ' name="wc-' . esc_attr( $this->get_id_dasherized() . '-' . $name ) . '" ';
	}

	/**
	 * Outputs a checkbox for saving a new payment method to the database.
	 *
	 * @since 1.0.0
	 */
	public function save_payment_method_checkbox() {
		$force_tokenization = $this->should_force_tokenize();
		$type               = $force_tokenization ? 'hidden' : 'checkbox';
		$desc               = $force_tokenization ? '' : esc_html__( 'Save to account', 'woocommerce' );

		printf( '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-tokenize-payment-method" type="%2$s" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%3$s</label>
			</p>', esc_attr( $this->get_id_dasherized() ), $type, $desc );
	}

	/**
	 * Check if need of force tokenization
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function should_force_tokenize() {

		if ( is_checkout() && isset( $_GET['change_payment_method'] ) && ! empty( $_GET['change_payment_method'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( class_exists( 'WFOCU_Gateway' ) && WFOCU_Core()->data->is_funnel_exists() ) {

			return true;

		}

		if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return true;
		}

		return apply_filters( $this->get_id() . '_should_force_tokenize', false );
	}

	/**
	 * Mark the given order as failed and set the order note
	 *
	 * @param WC_Order $order the order
	 * @param string $error_message a message to display inside the "Payment Failed" order note
	 * @param NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response optional $response the transaction response object
	 *
	 * @since 1.0.0
	 *
	 */
	public function mark_order_as_failed( $order, $error_message, $response = null ) {
		$order_id    = BWF_WC_Compatibility::get_order_id( $order );
		$max_retries = apply_filters( 'xl_wc_nmi_max_weekly_failed_retries', 0 );
		if ( $order_id > 0 && $max_retries > 0 ) {
			$current_week = 'week_' . date( 'W' );
			$retries      = get_post_meta( $order_id, 'wc_xl_nmi_retries', true );
			$retries      = is_array( $retries ) ? $retries : [];

			$retry_count = isset( $retries[ $current_week ] ) ? $retries[ $current_week ] : 0;
			$retry_count ++;
			$retries                  = [];
			$retries[ $current_week ] = $retry_count;
			NMI_Gateway_Woocommerce_Logger::log( "Recording payment failure in function: " . __FUNCTION__ . ". Order id: $order_id, Current week: $current_week, Max retries: $max_retries, Retries: " . print_r( $retries, true ) );

			update_post_meta( $order_id, 'wc_xl_nmi_retries', $retries );
		}

		$call_from = is_admin() ? __( 'Backend', 'woofunnels-woocommerce-nmi-gateway' ) : __( 'Frontend', 'woofunnels-woocommerce-nmi-gateway' );

		/* translators: Placeholders: %1$s - payment gateway title, %2$s - error message; e.g. Order Note: [Payment method] Payment failed [error] */
		$order_note = sprintf( esc_html__( '%1$s %2$s Payment Failed (%3$s)', 'woofunnels-woocommerce-nmi-gateway' ), $this->get_method_title(), $call_from, $error_message );

		// Mark order as failed if not already set, otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
		if ( ! $order->has_status( 'failed' ) ) {
			$order->update_status( 'failed', $order_note );
		} else {
			$order->add_order_note( $order_note );
		}

		NMI_Gateway_Woocommerce_Logger::log( $error_message, false, 'error' );

		// user message
		$user_message = '';
		if ( $response && $this->is_detailed_customer_decline_messages_enabled() ) {
			$user_message = $response->get_user_message();
		}
		$user_message = ( empty( $user_message ) && $this->is_detailed_customer_decline_messages_enabled() ) ? $error_message : $user_message;

		if ( empty( $user_message ) ) {
			$user_message = esc_html__( 'An error occurred, please try again or try an alternate form of payment.', 'woofunnels-woocommerce-nmi-gateway' );
		}
		$this->add_debug_message( $user_message, 'error' );
	}

	/**
	 * Adds debug messages to the page as a WC message/error, and/or to the WC Error log
	 *
	 * @param string $message message to add
	 * @param string $type how to add the message, options are:
	 *     'message' (styled as WC message), 'error' (styled as WC Error)
	 *
	 * @since 1.0.0
	 */
	public function add_debug_message( $message, $type = 'message' ) {
		// do nothing when debug mode is off or no message
		if ( $this->debug_off() || ! $message ) {
			return;
		}

		// avoid adding notices when performing refunds, these occur in the admin as an Ajax call, so checking the current filter
		// is the only reliably way to do so
		if ( in_array( 'wp_ajax_woocommerce_refund_line_items', $GLOBALS['wp_current_filter'] ) ) {
			return;
		}

		// add debug message to woocommerce->errors/messages if checkout or both is enabled, the admin/Ajax check ensures capture charge transactions aren't logged as notices to the front end
		if ( ( $this->debug_checkout() || ( 'error' === $type && $this->is_test_environment() ) ) && ( ! is_admin() ) ) {

			if ( 'message' === $type ) {

				SV_WC_Helper::wc_add_notice( str_replace( "\n", "<br/>", htmlspecialchars( $message ) ), 'notice' );

			} else {

				// defaults to error message
				SV_WC_Helper::wc_add_notice( str_replace( "\n", "<br/>", htmlspecialchars( $message ) ), 'error' );
			}
		}
	}

	/**
	 * Overriding this function to disable logging from inside Skyverge library which is revealing card info
	 *
	 * @param array $request
	 * @param array $response
	 */
	public function log_api_request( $request, $response ) {
		//overriding with blank body
	}

	public function get_payment_api_method() {
		return empty( $this->payment_api_method ) ? 'direct_post' : $this->payment_api_method;
	}

	public function get_public_key() {
		return ( $this->is_test_environment() ) ? $this->sandbox_public_key : $this->production_public_key;
	}

	public function get_private_key() {
		return ( $this->is_test_environment() ) ? $this->sandbox_private_key : $this->production_private_key;
	}

	/**
	 * @param $response
	 * @param $order
	 */
	public function maybe_add_avs_result_to_order_note( $response, $order ) {
		$order_id = $response->get_response_orderid();
		$avs_code = $response->get_avs_result();
		NMI_Gateway_Woocommerce_Logger::log( "Going to add avs result for order id: $order_id, avs result: $avs_code." );
		if ( $order_id > 0 && ! empty( $avs_code ) ) {
			$order_note = $this->get_decoded_avs_message( $avs_code );
			if ( ! empty( $order_note ) ) {
				$order->add_order_note( sprintf( __( 'AVS Result: %s', 'woofunnels-woocommerce-nmi-gateway' ), $order_note ) );
			}
		}
	}

	/**
	 * Decoding AVS Result from this at https://secure.networkmerchants.com/gw/merchants/resources/integration/integration_portal.php#dp_appendix_1
	 * @return array
	 */
	public function get_decoded_avs_message( $avs_code ) {
		$avs_result_messages = array(
			'A' => __( 'Address match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'B' => __( 'Address match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'C' => __( 'No address or ZIP match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'D' => __( 'Exact match, 5-character numeric ZIP', 'woofunnels-woocommerce-nmi-gateway' ),
			'E' => __( 'Not a mail/phone order', 'woofunnels-woocommerce-nmi-gateway' ),
			'G' => __( 'Non-U.S. issuer does not participate', 'woofunnels-woocommerce-nmi-gateway' ),
			'I' => __( 'Non-U.S. issuer does not participate', 'woofunnels-woocommerce-nmi-gateway' ),
			'L' => __( '5-character ZIP match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'M' => __( 'Exact match, 5-character numeric ZIP', 'woofunnels-woocommerce-nmi-gateway' ),
			'N' => __( 'No address or ZIP match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'O' => __( 'AVS not available', 'woofunnels-woocommerce-nmi-gateway' ),
			'P' => __( '5-character ZIP match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'R' => __( 'Issuer system unavailable', 'woofunnels-woocommerce-nmi-gateway' ),
			'S' => __( 'Service not supported', 'woofunnels-woocommerce-nmi-gateway' ),
			'U' => __( 'Address unavailable', 'woofunnels-woocommerce-nmi-gateway' ),
			'W' => __( '9-character numeric ZIP match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'X' => __( 'Exact match, 9-character numeric ZIP', 'woofunnels-woocommerce-nmi-gateway' ),
			'Y' => __( 'Exact match, 5-character numeric ZIP', 'woofunnels-woocommerce-nmi-gateway' ),
			'Z' => __( '5-character ZIP match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'0' => __( 'AVS not availabley', 'woofunnels-woocommerce-nmi-gateway' ),
			'1' => __( '5-character ZIP, customer name match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'2' => __( 'Exact match, 5-character numeric ZIP, customer name', 'woofunnels-woocommerce-nmi-gateway' ),
			'3' => __( 'Address, customer name match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'4' => __( 'No address or ZIP or customer name match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'5' => __( '5-character ZIP, customer name match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'6' => __( 'Exact match, 5-character numeric ZIP, customer name', 'woofunnels-woocommerce-nmi-gateway' ),
			'7' => __( 'Address, customer name match only', 'woofunnels-woocommerce-nmi-gateway' ),
			'8' => __( 'No address or ZIP or customer name match only', 'woofunnels-woocommerce-nmi-gateway' ),
		);

		return isset( $avs_result_messages[ $avs_code ] ) ? $avs_result_messages[ $avs_code ] : '';
	}

	/**
	 * Generate Checkbox HTML.
	 *
	 * @param string $key Field key.
	 * @param array $data Field data.
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function generate_radio_html( $key, $data ) {

		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'label'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( ! $data['label'] ) {
			$data['label'] = $data['title'];
		}
		$db_value = empty( $this->get_option( $key ) ) ? $data['default'] : $this->get_option( $key );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
						<input class="<?php echo esc_attr( $data['class'] ); ?>" type="radio" name="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $option_key ); ?>" <?php checked( $db_value, $option_key ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
						<label style="padding-right:3%;" for="<?php echo esc_attr( $option_value ) ?>"><?php echo wp_kses_post( $option_value ); ?></label>
					<?php endforeach; ?>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * @return false|true
	 */
	public function is_available() {
		$is_available = parent::is_available();
		if ( 'collect_js' === $this->get_payment_api_method() ) {
			if ( empty( $this->get_public_key() ) || empty( $this->get_private_key() ) ) {
				$is_available = false;
			}
		}
		if ( 'direct_post' === $this->get_payment_api_method() ) {
			if ( empty( $this->get_gateway_username() ) || empty( $this->get_gateway_password() ) ) {
				$is_available = false;
			}
		}

		return $is_available;
	}

	/**
	 * @param $response
	 * @param $request_data
	 */
	public function maybe_record_token_failure_in_usermata( $response, $request_data ) {
		$is_vaulting_request = isset( $request_data['customer_vault'] ) ? ( 'add_customer' === $request_data['customer_vault'] ) : false;
		$has_order_id        = isset( $request_data['orderid'] ) ? $request_data['orderid'] : false;
		$is_successful       = ( isset( $response->response ) && '1' === $response->response );
		$user_id             = get_current_user_id();
		$max_retries         = apply_filters( 'xl_wc_nmi_max_weekly_failed_retries', 0 );

		if ( $max_retries > 0 ) {
			NMI_Gateway_Woocommerce_Logger::log( "Recording token failure from add_payment_method page: Is vaulting request: $is_vaulting_request, Has order_id: $has_order_id, Is successful: $is_successful, User_id: $user_id, Max retries: $max_retries" );

			if ( $is_vaulting_request && ! $has_order_id && ! $is_successful && $user_id > 0 && $max_retries > 0 ) {

				$current_week = 'week_' . date( 'W' );
				$retries      = get_user_meta( $user_id, 'wc_xl_nmi_retries', true );
				$retries      = is_array( $retries ) ? $retries : [];

				$retry_count = isset( $retries[ $current_week ] ) ? $retries[ $current_week ] : 0;
				$retry_count ++;
				$retries                  = [];
				$retries[ $current_week ] = $retry_count;
				update_user_meta( $user_id, 'wc_xl_nmi_retries', $retries );
			}
		}
	}
}
