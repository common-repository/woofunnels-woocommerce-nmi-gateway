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
 * @package   woofunnels-woocommerce-nmi-gateway/includes
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */


defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI Gateway WooCommerce Credit Card Gateway Class
 *
 * @since 1.0.0
 * NMI_Gateway_Woocommerce_Credit_Card
 */
class NMI_Gateway_Woocommerce_Credit_Card extends NMI_Gateway_Woocommerce_Base {

	/** @var string require CSC field */
	protected $require_csc;

	// Supported currencies
	public $currencies = array();

	//actions
	public $actions;

	/**
	 * Initialize the gateway
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct( NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID, nmi_gateway_woocommerce_cc(), array(
			'method_title'       => __( 'XL NMI Gateway for WooCommerce', 'woofunnels-woocommerce-nmi-gateway' ),
			'method_description' => __( 'Allow customers to securely pay using their credit cards via NMI Payment Gateway.', 'woofunnels-woocommerce-nmi-gateway' ),
			'supports'           => apply_filters( 'wc_' . NMI_Gateway_Woocommerce::PLUGIN_ID . '_gateway_supports', array(
				self::FEATURE_PRODUCTS,
				self::INTEGRATION_SUBSCRIPTIONS,
				self::FEATURE_REFUNDS,
				self::INTEGRATION_PRE_ORDERS,
				self::FEATURE_ADD_PAYMENT_METHOD,
				self::FEATURE_CARD_TYPES,
				self::FEATURE_PAYMENT_FORM,
				self::FEATURE_TOKENIZATION,
				self::FEATURE_CREDIT_CARD_CHARGE,
				self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
				self::FEATURE_CREDIT_CARD_AUTHORIZATION,
				self::FEATURE_CREDIT_CARD_CAPTURE,
				self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
				self::FEATURE_REFUNDS,
				self::FEATURE_VOIDS,
				//self::FEATURE_CUSTOMER_ID,
				//self::FEATURE_TOKEN_EDITOR,
			) ),

			'payment_type' => self::PAYMENT_TYPE_CREDIT_CARD,
			'environments' => $this->get_nmi_gateway_woocommerce_environments(),
			//'shared_settings'    => $this->shared_settings_names,
			'card_types'   => array(
				'visa'       => 'Visa',
				'mastercard' => 'MasterCard',
				'amex'       => 'American Express',
				'discover'   => 'Discover',
				'diners'     => 'Diners',
				'maestro'    => 'Maestro',
				'jcb'        => 'JCB',
			),
		) );

		//Removing subscriptions support when tokenization is disabled
		if ( ! $this->tokenization_enabled() ) {
			$this->supports = array(
				self::FEATURE_PRODUCTS,
				//self::INTEGRATION_SUBSCRIPTIONS,
				//self::INTEGRATION_PRE_ORDERS,
				self::FEATURE_REFUNDS,
				self::FEATURE_ADD_PAYMENT_METHOD,
				self::FEATURE_CARD_TYPES,
				self::FEATURE_PAYMENT_FORM,
				//self::FEATURE_TOKENIZATION,
				//self::FEATURE_CREDIT_CARD_CHARGE,
				self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
				self::FEATURE_CREDIT_CARD_AUTHORIZATION,
				self::FEATURE_CREDIT_CARD_CAPTURE,
				self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
				self::FEATURE_REFUNDS,
				self::FEATURE_VOIDS,
				//self::FEATURE_CUSTOMER_ID,
				//self::FEATURE_TOKEN_EDITOR,
			);
		}

		$this->live_url          = 'https://secure.networkmerchants.com/api/transact.php';
		$this->test_url          = '';
		$this->order_button_text = '';

		$this->currencies = array(
			'AED',
			'AMD',
			'ANG',
			'ARS',
			'AUD',
			'AWG',
			'AZN',
			'BBD',
			'BDT',
			'BGN',
			'BIF',
			'BMD',
			'BND',
			'BOB',
			'BRL',
			'BWP',
			'BYR',
			'BZD',
			'CAD',
			'CHF',
			'CLP',
			'CNY',
			'COP',
			'CRC',
			'CVE',
			'CYP',
			'CZK',
			'DJF',
			'DKK',
			'DOP',
			'DZD',
			'EEK',
			'EGP',
			'ETB',
			'EUR',
			'FJD',
			'FKP',
			'GBP',
			'GEL',
			'GHC',
			'GIP',
			'GMD',
			'GNF',
			'GTQ',
			'GWP',
			'GYD',
			'HKD',
			'HNL',
			'HTG',
			'HUF',
			'IDR',
			'ILS',
			'INR',
			'ISK',
			'JMD',
			'JPY',
			'KES',
			'KGS',
			'KHR',
			'KMF',
			'KRW',
			'KYD',
			'KZT',
			'LAK',
			'LBP',
			'LKR',
			'LTL',
			'LVL',
			'MAD',
			'MDL',
			'MGF',
			'MNT',
			'MOP',
			'MRO',
			'MTL',
			'MUR',
			'MVR',
			'MWK',
			'MYR',
			'MZN',
			'MXN',
			'NAD',
			'NGN',
			'NIO',
			'NOK',
			'NPR',
			'NZD',
			'PAB',
			'PEN',
			'PGK',
			'PHP',
			'PKR',
			'PLN',
			'PYG',
			'QAR',
			'RON',
			'RUB',
			'RWF',
			'SAR',
			'SBD',
			'SCR',
			'SEK',
			'SGD',
			'SHP',
			'SKK',
			'SLL',
			'SOS',
			'STD',
			'SVC',
			'SZL',
			'THB',
			'TOP',
			'TRY',
			'TTD',
			'TWD',
			'TZS',
			'UAH',
			'UGX',
			'USD',
			'UYU',
			'UZS',
			'VND',
			'VUV',
			'WST',
			'XAF',
			'XCD',
			'XOF',
			'XPF',
			'YER',
			'ZAR',
			'ZMK',
			'ZWD',
		);

		if ( $this->is_test_environment() ) {
			$this->description .= ' ' . sprintf( __( '<br /><p> In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date or check the documentation "<a href="%s">NMI Direct Post API</a>" for more card numbers.</p>', 'woofunnels-woocommerce-nmi-gateway' ), 'https://secure.networkmerchants.com/gw/merchants/resources/integration/download.php?document=directpost' );
			$this->description = trim( $this->description );
		}

		// Hooks
		add_action( 'admin_notices', array( $this, 'nmi_gateway_woocommerce_admin_notices' ) );

		//Removing edit button from payment methods
		add_filter( 'wc_' . NMI_Gateway_Woocommerce::PLUGIN_ID . '_my_payment_methods_table_actions_html', array( $this, 'nmi_actions_filter' ), 10, 2 );

		if ( $this->csc_enabled_for_tokens() ) {
			//Adding cvv field for saved payment method during checkout
			add_filter( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', array( $this, 'nmi_gateway_woocommerce_add_csc_with_saved_token_methods' ), 10, 2 );
		}

		//Removing duplicate payment methods table from my account payment methods created by skyverge
		add_filter( 'wc_' . NMI_Gateway_Woocommerce::PLUGIN_ID . '_my_payment_methods_table_html', array( $this, 'nmi_gateway_woocommerce_remove_payment_methods' ), 10, 1 );

		add_filter( 'sv_wc_payment_gateway_payment_form_js_localized_script_params', array( $this, 'xl_wc_nmi_localize_collect_js_params' ), 10, 1 );
	}

	/**
	 * Get the default payment method title, which is configurable within the
	 * admin and displayed on checkout
	 *
	 * @return string payment method title to show on checkout
	 * @since 1.0.0
	 */
	protected function get_default_title() {
		// defaults for credit card and echeck, override for others
		return esc_html__( 'Credit Card (XL NMI)', 'woofunnels-woocommerce-nmi-gateway' );

	}

	/**
	 * Get the default payment method description, which is configurable
	 * within the admin and displayed on checkout
	 *
	 * @return string payment method description to show on checkout
	 * @since 1.0.0
	 */
	protected function get_default_description() {
		// defaults for credit card
		return esc_html__( 'Pay securely using credit cards.', 'woofunnels-woocommerce-nmi-gateway' );
	}


	/**
	 * Override the standard CSC setting to instead indicate that it's a combined
	 * Display & Require CSC setting. NMI doesn't allow the CSC field to be
	 * present without also requiring it to be populated.
	 *
	 * @param array $form_fields gateway form fields
	 *
	 * @return array $form_fields gateway form fields
	 * @since 1.0.0
	 *
	 */
	protected function add_csc_form_fields( $form_fields ) {
		if ( $this->supports_tokenization() ) {
			$form_fields['enable_token_csc'] = array(
				'title'   => esc_html__( 'Saved Card CVV/CSC Verification', 'woofunnels-woocommerce-nmi-gateway' ),
				'label'   => esc_html__( 'Display the CVV/CSC field when paying with a saved card', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'    => 'checkbox',
				'default' => 'no',
			);
		}

		return $form_fields;
	}

	/**
	 * Adds any tokenization form fields for the settings page
	 *
	 * @param array $form_fields gateway form fields
	 *
	 * @return array $form_fields gateway form fields
	 * @since 1.0.0
	 *
	 */
	protected function add_tokenization_form_fields( $form_fields ) {
		assert( $this->supports_tokenization() );

		$form_fields['tokenization'] = array(
			'title'   => esc_html__( 'Tokenization', 'woofunnels-woocommerce-nmi-gateway' ),
			'label'   => esc_html__( 'Allow customers to securely save their payment details for future checkout.', 'woofunnels-woocommerce-nmi-gateway' ),
			'type'    => 'checkbox',
			'default' => 'no',
		);

		//Shifting show csc field for saved payment methods after tokenization enable
		if ( isset( $form_fields['enable_token_csc'] ) ) {
			$enable_token_csc = $form_fields['enable_token_csc'];
			unset( $form_fields['enable_token_csc'] );
			$form_fields['enable_token_csc'] = $enable_token_csc;
		}

		//Changing enable/disable label
		$form_fields['enabled']['label'] = esc_html__( 'Enable XL NMI Gateway for WooCommerce', 'woofunnels-woocommerce-nmi-gateway' );

		if ( isset( $this->form_fields['transaction_type'] ) ) {
			$form_fields['transaction_type'] = array(
				'title'    => esc_html__( 'Transaction Type', 'woofunnels-woocommerce-nmi-gateway' ),
				'type'     => 'select',
				'desc_tip' => esc_html__( 'Select how transactions should be processed. Charge submits all transactions for settlement, Authorization simply authorizes the order total for capture later.', 'woofunnels-woocommerce-nmi-gateway' ),
				'default'  => self::TRANSACTION_TYPE_CHARGE,
				'options'  => array(
					self::TRANSACTION_TYPE_CHARGE        => esc_html_x( 'Authorize and Capture', 'noun, credit card transaction type', 'woofunnels-woocommerce-nmi-gateway' ),
					self::TRANSACTION_TYPE_AUTHORIZATION => esc_html_x( 'Authorize Only', 'credit card transaction type', 'woofunnels-woocommerce-nmi-gateway' ),
				),
			);
		}

		return $form_fields;
	}


	/**
	 * Display settings page with some additional javascript for hiding conditional fields
	 *
	 * @since 1.0.0
	 * @see WC_Settings_API::admin_options()
	 */
	public function admin_options() {

		parent::admin_options();

		if ( isset( $this->form_fields['tokenization'] ) ) {

			// add inline javascript to show/hide any shared settings fields as needed
			ob_start();
			?>
			$( '#woocommerce_<?php echo $this->get_id(); ?>_tokenization' ).change( function() {
			var enabled = $( this ).is( ':checked' );
			if ( enabled ) {
			$( '#woocommerce_<?php echo $this->get_id(); ?>_enable_token_csc' ).closest( 'tr' ).show();
			} else {
			$( '#woocommerce_<?php echo $this->get_id(); ?>_enable_token_csc' ).closest( 'tr' ).hide();
			}
			} ).change();

			var api_method_el = $( 'input[name=woocommerce_<?php echo $this->get_id(); ?>_payment_api_method]');
			var api_method = $( 'input[name=woocommerce_<?php echo $this->get_id(); ?>_payment_api_method]:checked').val();
			process_settings(api_method_el,api_method);

			$( api_method_el ).click( function() {
			api_method = $(this).val();
			process_settings(api_method_el,api_method);
			} );
			function process_settings(APIMethod_el,APImethod){
			var envnmt = $('#woocommerce_<?php echo $this->get_id(); ?>_environment').val();
			var envnmt1 = ('production'===envnmt)?'live':envnmt;

			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_username' ).closest( 'tr' ).hide();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_password' ).closest( 'tr' ).hide();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_live_username' ).closest( 'tr' ).hide();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_live_password' ).closest( 'tr' ).hide();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_production_public_key' ).closest( 'tr' ).hide();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_production_private_key' ).closest( 'tr' ).hide();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_public_key' ).closest( 'tr' ).hide();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_private_key' ).closest( 'tr' ).hide();

			if ( 'direct_post' === APImethod ) {
			$( '#woocommerce_<?php echo $this->get_id(); ?>_'+envnmt1+'_username' ).closest( 'tr' ).show();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_'+envnmt1+'_password' ).closest( 'tr' ).show();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_live_username' ).addClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_live_password' ).addClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_username' ).addClass('sandbox-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_password' ).addClass('sandbox-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_production_public_key' ).removeClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_production_private_key' ).removeClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_public_key' ).removeClass('sandbox-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_private_key' ).removeClass('sandbox-field');
			}else{
			$( '#woocommerce_<?php echo $this->get_id(); ?>_'+envnmt+'_public_key' ).closest( 'tr' ).show();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_'+envnmt+'_private_key' ).closest( 'tr' ).show();
			$( '#woocommerce_<?php echo $this->get_id(); ?>_live_username' ).removeClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_live_password' ).removeClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_username' ).removeClass('sandbox-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_password' ).removeClass('sandbox-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_production_public_key' ).addClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_production_private_key' ).addClass('production-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_public_key' ).addClass('sandbox-field');
			$( '#woocommerce_<?php echo $this->get_id(); ?>_sandbox_private_key' ).addClass('sandbox-field');
			}
			}
			<?php

			wc_enqueue_js( ob_get_clean() );

		}

	}

	/**
	 * Returns true if the CSC field should be displayed and required at checkout
	 *
	 * @since 1.0.0
	 */
	public function is_csc_required() {
		return true;
	}

	/**
	 * Override the standard CSC enabled method to return the value of the csc_required()
	 * check since enabled/required is the same for NMI
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function csc_enabled() {
		return $this->is_csc_required();
	}

	/**
	 * @return mixed
	 */
	public function get_payment_processor() {
		return isset( $this->settings['validate_processor'] ) && ( 'yes' === $this->settings['validate_processor'] ) ? 'validate' : 'auth';
	}

	/**
	 * Returns true if the payment nonce is provided when not using a saved
	 * payment token. Note this can't be moved to the parent class because
	 * validation is payment-type specific.
	 *
	 * @param boolean $is_valid true if the fields are valid, false otherwise
	 *
	 * @return boolean true if the fields are valid, false otherwise
	 * @since 1.0.0
	 *
	 */
	protected function validate_credit_card_fields( $is_valid ) {

		return $this->validate_payment_nonce( $is_valid );
	}


	/**
	 * Returns true if the payment nonce is provided when using a saved payment method
	 * and CSC is required.
	 *
	 * @param string $csc
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	protected function validate_csc( $csc ) {

		return $this->validate_payment_nonce( true );
	}

	/**
	 * @param WC_Order $order
	 * @param null $response
	 *
	 * @return NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response|null
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Plugin_Exception
	 */
	public function do_credit_card_transaction( $order, $response = null ) {
		$order_id = BWF_WC_Compatibility::get_order_id( $order );

		NMI_Gateway_Woocommerce_Logger::log( "Starting the NMI function: " . __FUNCTION__ . ": For Order_id: $order_id" );

		if ( is_null( $response ) ) {

			/** To block further api request if max retries are set from filter **/
			$max_retries = apply_filters( 'xl_wc_nmi_max_weekly_failed_retries', 0 );

			if ( $order_id > 0 && $max_retries > 0 ) {
				$current_week = 'week_' . date( 'W' );
				$retries      = get_post_meta( $order_id, 'wc_xl_nmi_retries', true );
				$retries      = is_array( $retries ) ? $retries : [];
				$retry_count  = isset( $retries[ $current_week ] ) ? $retries[ $current_week ] : 0;

				NMI_Gateway_Woocommerce_Logger::log( "NMI checking to block API request in function: " . __FUNCTION__ . ": Order_id: $order_id, Current week: $current_week, Max retries: $max_retries, Retry count: $retry_count, Retries: " . print_r( $retries, true ) );

				if ( $retry_count >= $max_retries ) {
					throw new NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception( "Maximum attempt limit reached. Please contact us for further help." );
				}
			}
			/** Blocking further API request **/

			//Handling token creation and addition on order->payment object
			$order                    = $this->get_payment_tokens_handler()->create_token( $order, $response );
			$authorized_primary_order = false;

			if ( $order instanceof WC_Order ) {
				$authorized_primary_order = ( isset( $order->payment ) && isset( $order->payment->token_added ) );
			}

			NMI_Gateway_Woocommerce_Logger::log( "XL NMI Primary Order authorized: $authorized_primary_order order payment object for order id: $order_id:" . print_r( $order->payment, true ) );
			if ( $authorized_primary_order && $this->perform_credit_card_charge( $order ) ) {
				if ( 'validate' === $this->get_payment_processor() ) {
					$response = $this->get_api()->credit_card_charge( $order );
				} else {
					$response = $this->get_api()->credit_card_capture( $order );
				}
			} else {
				$response = $this->perform_credit_card_charge( $order ) ? $this->get_api()->credit_card_charge( $order ) : $this->get_api()->credit_card_authorization( $order );
			}

			if ( ! is_null( $response ) && $response->transaction_approved() ) {
				$account_number = ! empty( $response->get_masked_number() ) ? $response->get_masked_number() : ( isset( $card_data['number'] ) ? $card_data['number'] : '' );
				$last_four      = ! empty( $response->get_last_four() ) ? $response->get_last_four() : ( isset( $card_data['number'] ) ? substr( $card_data['number'], '-4' ) : '' );
				$card_type      = ! empty( $response->get_card_type() ) ? $response->get_card_type() : ( isset( $card_data['type'] ) ? $card_data['type'] : '' );
				$exp_month      = ! empty( $response->get_exp_month() ) ? $response->get_exp_month() : ( isset( $card_data['exp'] ) ? substr( $card_data['exp'], 0, 2 ) : '00' );
				$exp_year       = ! empty( $response->get_exp_year() ) ? $response->get_exp_year() : ( isset( $card_data['exp'] ) ? substr( $card_data['exp'], 2, 2 ) : '00' );
				$exp_csc        = ! empty( $response->get_csc() ) ? $response->get_csc() : ( isset( $card_data['bin'] ) ? substr( $card_data['bin'], 0, 3 ) : '000' );

				$order->payment->account_number = $account_number;
				$order->payment->last_four      = $last_four;
				$order->payment->card_type      = $card_type;
				$order->payment->exp_month      = $exp_month;
				$order->payment->exp_year       = $exp_year;
				$order->payment->csc            = $exp_csc;
				$order->payment->tokenize       = $this->supports_tokenization() ? $this->tokenization_enabled() : false;

				if ( ! empty( $response->customer_vault_id ) ) {
					$order->payment->token = $response->customer_vault_id;
				}

				$this->maybe_add_avs_result_to_order_note( $response, $order );
			}
		}

		return parent::do_credit_card_transaction( $order, $response );
	}

	/** Capture feature *******************************************************/


	/**
	 * Perform a credit card capture for an order.
	 *
	 * @param WC_Order $order
	 * @param null $amount
	 *
	 * @return array|/NMI_Gateway_Woocommerce_Framework/SV_WC_Payment_Gateway_API_Response|null
	 */
	public function do_credit_card_capture( $order, $amount = null ) {

		$order = $this->get_order_for_capture( $order, $amount );

		try {

			$response = $this->get_api()->credit_card_capture( $order );

			if ( $response->transaction_approved() ) {

				$message = sprintf( /* translators: Placeholders: %1$s - XL NMI Gateway, %2$s - transaction amount. Definitions: Capture, as in capture funds from a credit card. */ __( '%1$s Capture Approved', 'woofunnels-woocommerce-nmi-gateway' ), $this->get_method_title(), wc_price( $order->capture->amount, array( 'currency' => NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'currency', 'view' ) ) ) );

				// adds the transaction id (if any) to the order note
				if ( $response->get_transaction_id() ) {
					$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woofunnels-woocommerce-nmi-gateway' ), $response->get_transaction_id() );
				}

				$order->add_order_note( $message );

				// add the standard capture data to the order
				$this->add_capture_data( $order, $response );

				// let payment gateway implementations add their own data
				$this->add_payment_gateway_capture_data( $order, $response );

				// if the original auth amount has been captured, complete payment
				if ( $this->get_order_meta( $order, 'capture_total' ) >= NMI_Gateway_Woocommerce_Framework\SV_WC_Helper::number_format( $this->get_order_authorization_amount( $order ) ) ) {

					// prevent stock from being reduced when payment is completed as this is done when the charge was authorized
					add_filter( 'woocommerce_payment_complete_reduce_order_stock', '__return_false', 100 );

					// complete the order
					$order->payment_complete();
				}

				return array(
					'result'  => 'success',
					'message' => $message,
				);

			} else {

				$this->do_credit_card_capture_failed( $order, $response );

				$message = sprintf( /* translators: Placeholders: %1$s - XL NMI Gateway, %2$s - transaction amount, %3$s - transaction status message. Definitions: Capture, as in capture funds from a credit card. */ __( '%1$s Capture Failed: %2$s - %3$s', 'woofunnels-woocommerce-nmi-gateway' ), $this->get_method_title(), $response->get_status_code(), $response->get_status_message() );

				$order->add_order_note( $message );

				return array(
					'result'  => 'failure',
					'message' => $message,
				);
			}

		} catch ( NMI_Gateway_Woocommerce_Framework\SV_WC_Plugin_Exception $e ) {

			$message = sprintf( /* translators: Placeholders: %1$s - XL NMI Gateways, %2$s - failure message. Definitions: "capture" as in capturing funds from a credit card. */ __( '%1$s Capture Failed: %2$s', 'woofunnels-woocommerce-nmi-gateway' ), $this->get_method_title(), $e->getMessage() );

			$order->add_order_note( $message );

			return array(
				'result'  => 'failure',
				'message' => $message,
			);
		}
	}


	/**
	 * Adds any gateway-specific transaction data to the order, for credit cards this is:
	 *
	 * @param \WC_Order $order the order object
	 * @param \NMI_Gateway_Woocommerce_API_Credit_Card_Transaction_Response $response transaction response
	 *
	 * @since 1.0.0
	 * @see SV_WC_Payment_Gateway_Direct::add_transaction_data()
	 *
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {
		//Add token data
		if ( $this->supports_tokenization() && $this->tokenization_enabled() && isset( $response->customer_vault_id ) ) {
			$this->update_order_meta( $order, 'payment_token', $response->customer_vault_id );
			$this->update_order_meta( $order, 'customer_id', $response->customer_vault_id );
		}
	}


	/** Refund/Void feature ***************************************************/


	/**
	 * Void a transaction instead of refunding when it has a submitted for settlement
	 * status. Note that only credit card transactions are eligible for this,
	 *
	 * @param \WC_Order $order order
	 * @param \NMI_Gateway_Woocommerce_API_Response $response refund response
	 *
	 * @return bool true if the transaction should be transaction
	 * @since 1.0.0
	 *
	 */
	protected function maybe_void_instead_of_refund( $order, $response ) {
		// NMI_Gateway_Woocommerce conveniently returns a validation error code that indicates a void can be performed instead of refund
		return $response->has_validation_errors();
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function nmi_gateway_woocommerce_admin_notices() {
		if ( $this->enabled === 'no' ) {
			return;
		}

		if ( 'direct_post' === $this->get_payment_api_method() ) {
			if ( empty( $this->get_gateway_username() ) || empty( $this->get_gateway_password() ) ) {
				echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your Username and Password <a href="%s">here</a>', 'woofunnels-woocommerce-nmi-gateway' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID ) ) . '</p></div>';

				return;
			}
			// Simple check for duplicate keys
			if ( $this->get_gateway_username() === $this->get_gateway_password() ) {
				echo '<div class="error"><p>' . sprintf( __( 'NMI error: Your Username and Password matched. Please check and re-enter.', 'woofunnels-woocommerce-nmi-gateway' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID ) ) . '</p></div>';

				return;
			}
		} else {
			if ( empty( $this->get_public_key() ) || empty( $this->get_private_key() ) ) {
				echo '<div class="error"><p>' . sprintf( __( 'NMI error: Please enter your public key and private keys <a href="%s">here</a>', 'woofunnels-woocommerce-nmi-gateway' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID ) ) . '</p></div>';

				return;
			}

			// Simple check for duplicate keys
			if ( $this->get_public_key() === $this->get_private_key() ) {
				echo '<div class="error"><p>' . sprintf( __( 'NMI error: Your public and private keys matched. Please check and re-enter.', 'woofunnels-woocommerce-nmi-gateway' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID ) ) . '</p></div>';

				return;
			}
		}

		if ( ! $this->currency_is_accepted() ) {
			echo '<div class="error"><p>' . sprintf( __( 'NMI Gateway does not support %s currency.', 'woofunnels-woocommerce-nmi-gateway' ), $this->get_payment_currency() ) . '</p></div>';

			return;
		}
	}


	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {

		$display_tokenization = is_checkout() && $this->supports_tokenization() && $this->tokenization_enabled() && is_user_logged_in();
		$total                = WC()->cart->total;
		$order                = null;

		if ( ! $display_tokenization && '0.00' === $total && class_exists( 'WC_Subscriptions_Cart' ) ) {
			$display_tokenization = WC_Subscriptions_Cart::cart_contains_free_trial();
		}

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total = $order->get_total();
		}
		$desc = $this->get_description();

		if ( ! empty( $desc ) ) {
			echo '<div class="description"><p class="desc-text">' . $desc . '</p></div>';
		}

		echo '<div class="' . NMI_Gateway_Woocommerce::PLUGIN_ID . '_new_card"
			id="' . NMI_Gateway_Woocommerce::PLUGIN_ID . '-payment-data"
			data-description=""
			data-amount="' . esc_attr( $total ) . '"
			data-name="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}
		if ( 'collect_js' === $this->get_payment_api_method() && ! empty( $this->get_public_key() ) ) {
			$this->xl_nmi_collect_js_form();
		} else {
			$this->form();
		}

		if ( ( $display_tokenization ) ) {
			$this->save_payment_method_checkbox();
		}

		echo '</div>';
		//parent::payment_fields();

		do_action( $this->get_id() . '_after_cc_fields_on_wc_checkout', $total, $desc, $display_tokenization, $order );
	}

	/**
	 * Returns a users saved tokens for this gateway.
	 * @return array
	 * @since 1.1.0
	 */
	public function get_tokens() {

		if ( count( $this->tokens ) > 0 ) {
			return $this->tokens;
		}

		if ( is_user_logged_in() ) {
			$this->tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );
		}
		/**  Filtering production and sandbox tokens based on current environment **/
		foreach ( is_array( $this->tokens ) ? $this->tokens : array() as $token_id => $token ) {
			if ( $this->get_environment() !== $token->get_meta( 'mode' ) ) {
				unset( $this->tokens[ $token_id ] );
			}
		}

		return $this->tokens;
	}

	/**
	 * Set to charge or authenticate the transaction
	 *
	 * @param WC_Order|null $order
	 *
	 * @return bool
	 */
	public function perform_credit_card_charge( \WC_Order $order = null ) {
		return ( 'charge' === $this->get_option( 'transaction_type' ) );
	}

	/**
	 * Adding card id to order for selected card to make token payment
	 *
	 * @param $token_id
	 * @param $nmi_csc
	 * @param $order
	 *
	 * @return mixed
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function add_card_id_in_order_from_token( $token_id, $nmi_csc, $order ) {
		$token = WC_Payment_Tokens::get( $token_id );
		NMI_Gateway_Woocommerce_Logger::log( "Adding a token $token_id in class-nmi-woocommerce-credit_card to order: " . $order->get_id() );

		if ( ! $token || $token->get_user_id() !== $order->get_customer_id() ) {
			WC()->session->set( 'refresh_totals', true );
			throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( __( 'It seems this card is no more valid or token has expired. Please enter a new card number.', 'woofunnels-woocommerce-nmi-gateway' ) );
		}

		$card_id               = $token->get_token();
		$order->payment->token = $card_id;
		$order->payment->csc   = $nmi_csc;

		$this->update_order_meta( $order, 'payment_token', $card_id );
		$this->update_order_meta( $order, 'customer_id', $card_id );

		return $order;
	}


	/**
	 * @param $html
	 * @param $token
	 *
	 * @return string|string[]
	 */
	public function nmi_actions_filter( $html, $token ) {

		$html = str_replace( '<a href="#" class="edit-payment-method button">Edit</a>', '', $html );

		return $html;
	}

	/**
	 * @param $html
	 * @param $token
	 *
	 * @return string
	 */
	public function nmi_gateway_woocommerce_add_csc_with_saved_token_methods( $html, $token ) {
		$cvc_field = '<p class="form-row form-row-last" style="float:left;">
			<label for="' . esc_attr( $this->get_id_dasherized() ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( $this->get_id_dasherized() ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'csc-' . $token->get_id() ) . ' style="width:100px" /></p>';

		$html = $html . "<fieldset class='nmi_csc_" . $token->get_id() . "' style='display:none;'>" . $cvc_field . "</fieldset>";

		return $html;
	}

	/**
	 * //Removing duplicate payment methods table from my account payment methods created by skyverge
	 *
	 * @param $html
	 */
	public function nmi_gateway_woocommerce_remove_payment_methods( $html ) {
		__return_empty_string();
	}

	/**
	 * @param $xl_wc_nmi_params
	 *
	 * @return array
	 */
	public function xl_wc_nmi_localize_collect_js_params( $xl_wc_nmi_params ) {
		$xl_wc_nmi_params                       = is_array( $xl_wc_nmi_params ) ? $xl_wc_nmi_params : array();
		$xl_wc_nmi_params['allowed_card_types'] = $this->get_card_types();
		$xl_wc_nmi_params['timeout_error']      = __( 'The tokenization didn\'t respond in the expected timeframe.  This could be due to an invalid or incomplete field or poor connectivity. Please try again!', 'woofunnels-woocommerce-nmi-gateway' );
		$xl_wc_nmi_params['is_checkout']        = ( is_checkout() && empty( $_GET['pay_for_order'] ) ) ? 'yes' : 'no';

		return $xl_wc_nmi_params;
	}
}
