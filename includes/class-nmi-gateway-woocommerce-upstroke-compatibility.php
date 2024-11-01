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

/**
 * Class NMI_Gateway_Woocommerce_Upstroke_Compatibility
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_Upstroke_Compatibility extends WFOCU_Gateway {

	public $token = false;
	public $key;
	protected static $ins = null;
	public $order;
	public $wc_pre_30;

	/**
	 *
	 * Initialize the gateway
	 *
	 * @since 1.0.0
	 *
	 * NMI_Gateway_Woocommerce_Upstroke_Compatibility constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->wc_pre_30 = version_compare( WC_VERSION, '3.0.0', '<' );
		$this->key       = NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID;

		if ( class_exists( 'WFOCU_Core' ) && defined( 'WFOCU_VERSION' ) ) {
			if ( version_compare( WFOCU_VERSION, '1.99', '>' ) ) {
				$this->refund_supported = true;
			}
		}
		add_filter( 'wfocu_subscriptions_get_supported_gateways', array( $this, 'enable_subscription_upsell_support' ), 10, 1 );

		//Copying _wc_nmi_gateway_woocommerce_credit_card_payment_token in renewal offer for Subscriptions upsell
		add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_xl_nmi_payment_token_keys_to_copy' ), 10, 2 );

		add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_nmi_payment_token_to_subscription' ), 10, 3 );
	}

	/**
	 * @return NMI_Gateway_Woocommerce_Upstroke_Compatibility|null
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self;
		}

		return self::$ins;
	}

	/**
	 * Adding this gateway as Subscriptions upsell supported gateway
	 *
	 * @param $gateways
	 *
	 * @return array
	 */
	public function enable_subscription_upsell_support( $gateways ) {
		if ( is_array( $gateways ) ) {
			$gateways[] = NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID;
		}

		return $gateways;
	}

	/**
	 * Adding keys to copy to renewal orders
	 *
	 * @param $meta_keys
	 *
	 * @return mixed
	 */
	public function set_xl_nmi_payment_token_keys_to_copy( $meta_keys, $order = null ) {

		if ( $order instanceof WC_Order ) {
			$payment_method = $order->get_payment_method();
			if ( $payment_method === $this->key ) {
				$meta_keys[] = '_wc_nmi_gateway_woocommerce_credit_card_payment_token';
				$meta_keys[] = '_wc_nmi_gateway_woocommerce_credit_card_account_four';
				$meta_keys[] = '_wc_nmi_gateway_woocommerce_credit_card_expiry_date';
				$meta_keys[] = '_wc_nmi_gateway_woocommerce_credit_card_card_type';
				$meta_keys[] = '_wc_nmi_gateway_woocommerce_credit_card_card_environment';
			}
		}

		return $meta_keys;
	}

	/**
	 * @param WC_Subscription $subscription
	 * @param $key
	 * @param WC_Order $order
	 */
	public function save_nmi_payment_token_to_subscription( $subscription, $key, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( $order instanceof WC_Order && $this->get_key() === $order->get_payment_method() && did_action( 'woocommerce_created_customer' ) ) {
			/**
			 * Sometimes when upstroke creates subscription, it also creates user & because payment processes before user creation the token is not getting inserted into usermeta
			 * THis means that order ID is the only place where token is available making subscription renewals to fail.
			 */
			$this->add_transaction_data( $order );
		}

		$get_nmi_token = $order->get_meta( '_wc_nmi_gateway_woocommerce_credit_card_payment_token', true );

		if ( ! empty( $get_nmi_token ) ) {
			$subscription->update_meta_data( '_wc_nmi_gateway_woocommerce_credit_card_payment_token', $get_nmi_token );
			$subscription->update_meta_data( '_wc_nmi_gateway_woocommerce_credit_card_customer_id', $get_nmi_token );
			$subscription->save();
		}
	}

	/**
	 * @param $order
	 *
	 * @return false
	 */
	public function add_transaction_data( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$order_id        = WFOCU_WC_Compatibility::get_order_id( $order );
		$nmi_customer_id = $this->get_wc_gateway()->get_order_meta( $order, 'payment_token' );
		$expiry_date     = $this->get_wc_gateway()->get_order_meta( $order, 'card_expiry_date' );

		$exp_month = $exp_year = '00';
		if ( ! empty( $expiry_date ) ) {
			$exp_arr = explode( '-', $expiry_date );
			if ( count( $exp_arr ) > 1 ) {
				$exp_month = $exp_arr[1];
				$exp_year  = $exp_arr[0];
			}
		}

		$order_user_id = $order->get_customer_id();
		NMI_Gateway_Woocommerce_Logger::log( "XL NMI WFOCU Adding a token in token: $nmi_customer_id table for order id: $order_id, userid: $order_user_id, Expiry date: $expiry_date, Expiry Month: $exp_month, Exp year: $exp_year " );
		if ( $order_user_id > 0 ) {
			$request_data            = array();
			$request_data['payment'] = array(
				'id'        => (int) $nmi_customer_id,
				'last4'     => $this->get_wc_gateway()->get_order_meta( $order, 'account_four' ),
				'exp_month' => $exp_month,
				'exp_year'  => $exp_year,
				'brand'     => $this->get_wc_gateway()->get_order_meta( $order, 'card_type' ),
				'user_id'   => $order_user_id,
				'mode'      => $this->get_wc_gateway()->get_order_meta( $order, 'environment' ),
			);

			$card  = (object) $request_data['payment'];
			$token = new WC_Payment_Token_CC();
			$token->set_token( $card->id );
			$token->set_gateway_id( NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );
			$token->set_card_type( strtolower( $card->brand ) );
			$token->set_last4( $card->last4 );
			$token->set_expiry_month( $card->exp_month );
			$token->set_expiry_year( '20' . $card->exp_year );
			$token->set_user_id( $card->user_id );
			$token->add_meta_data( 'mode', $card->mode, true );
			$token->save();

			NMI_Gateway_Woocommerce_Logger::log( "XL NMI WFOCU Token added in token table for order id: $order_id, token id: {$token->get_id()}, Token user id: {$token->get_user_id()}" );
		}
	}

	/**
	 * Try and get the payment token from order->payment session
	 *
	 * @param WC_Order $order
	 *
	 * @return bool|true
	 */
	public function has_token( $order ) {
		$this->order = $order;
		$nmi_cc      = $this->get_wc_gateway();

		$this->token = $nmi_cc->get_order_meta( $order, 'payment_token' );

		WFOCU_Core()->log->log( "Token (NMI customer id) in " . __FUNCTION__ . ": {$this->token}" );

		if ( ! empty( $this->token ) && $this->is_enabled( $order ) && ( $this->get_key() === $order->get_payment_method() ) ) {
			return true;
		}

		WFOCU_Core()->log->log( "Token (NMI customer id and/or CVV) is missing: {$this->token}" );

		return false;
	}

	/**
	 *  Charging the card for which token is saved.
	 *
	 * @param WC_Order $order
	 *
	 * @return array|true
	 */
	public function process_charge( $order ) {
		$is_successful = false;
		$get_offer_id  = WFOCU_Core()->data->get( 'current_offer' );

		$response = $this->generate_nmi_offer_charge( $order );

		if ( ! $response->approved || $response->error || $response->declined ) {
			$this->handle_api_error( sprintf( __( 'XL NMI Payment failed for offer %s. Reason: %s', 'woofunnels-woocommerce-nmi-gateway' ), $get_offer_id, $response->responsetext ), sprintf( __( 'XL NMI Payment failed for offer %s, with error response: %s', 'woofunnels-woocommerce-nmi-gateway' ), $get_offer_id, print_r( $response, true ) ), $order ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		} else {
			$is_successful = true;
			WFOCU_Core()->data->set( '_transaction_id', $response->transactionid );
			WFOCU_Core()->log->log( "NMI payment is successful for offer: $get_offer_id" );
		}

		return $this->handle_result( $is_successful );
	}

	/**
	 * Creating nmi charge call
	 *
	 * @param $order
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function generate_nmi_offer_charge( $order ) {
		$response = array();
		try {
			$nmi_cc   = $this->get_wc_gateway();
			$username = $password = $private_key = '';

			if ( 'collect_js' === $nmi_cc->get_payment_api_method() ) {
				$private_key = $nmi_cc->get_private_key();
			}
			if ( 'direct_post' === $nmi_cc->get_payment_api_method() ) {
				$username = $nmi_cc->get_gateway_username();
				$password = $nmi_cc->get_gateway_password();
			}
			WFOCU_Core()->log->log( "NMI upsell offer: Username: $username, Pass: $password, Private key: $private_key" );

			$wp_nmi       = new NMI_Gateway_Woocommerce_Remote_Request( $username, $password, $private_key );
			$request_data = $this->set_wp_nmi_request_offer_data( $order );
			$response     = $wp_nmi->do_wp_remote_request( $request_data );
			WFOCU_Core()->log->log( 'Offer Charge response: ' . print_r( $response, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		} catch ( Exception $e ) {
			WFOCU_Core()->log->log( 'Offer Charge response error: ' . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		return $response;
	}

	/**
	 * Setting offer charge data
	 *
	 * @param $order
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function set_wp_nmi_request_offer_data( $order ) {
		$request_data = array();

		$get_package  = WFOCU_Core()->data->get( '_upsell_package' );
		$get_offer_id = WFOCU_Core()->data->get( 'current_offer' );

		$nmi_csc = WFOCU_Core()->data->get( 'nmi_csc' );

		/* translators: 1) offer 2) amount */
		$description = sprintf( __( 'Charging an offer: %1$s of amount %2$s', 'woofunnels-woocommerce-nmi-gateway' ), $get_offer_id, $get_package['total'] );

		//Primary data
		$request_data['orderid']           = $this->get_order_number( $order );
		$request_data['order_description'] = $description;
		$request_data['amount']            = $get_package['total'];
		$request_data['type']              = 'sale';

		//Customer
		$request_data['phone'] = ( $this->wc_pre_30 ) ? $order->billing_phone : $order->get_billing_phone();
		$request_data['email'] = ( $this->wc_pre_30 ) ? $order->billing_email : $order->get_billing_email();

		//Billing
		$request_data['first_name'] = WFOCU_WC_Compatibility::get_billing_first_name( $order );
		$request_data['last_name']  = WFOCU_WC_Compatibility::get_billing_last_name( $order );
		$request_data['address1']   = WFOCU_WC_Compatibility::get_order_billing_1( $order );
		$request_data['address2']   = WFOCU_WC_Compatibility::get_order_billing_2( $order );
		$request_data['company']    = ( $this->wc_pre_30 ) ? $order->billing_company : $order->get_billing_company();
		$request_data['city']       = ( $this->wc_pre_30 ) ? $order->billing_city : $order->get_billing_city();
		$request_data['state']      = ( $this->wc_pre_30 ) ? $order->billing_state : $order->get_billing_state();
		$request_data['zip']        = ( $this->wc_pre_30 ) ? $order->billing_postcode : $order->get_billing_postcode();
		$request_data['country']    = WFOCU_WC_Compatibility::get_billing_country_from_order( $order );

		//Payment
		$request_data['customer_vault_id'] = $this->token;
		$request_data['cvv']               = empty( $nmi_csc ) ? '' : $nmi_csc;
		$customer_ip                       = WFOCU_WC_Compatibility::get_customer_ip_address( $order );

		$request_data['currency']         = WFOCU_WC_Compatibility::get_order_currency( $order );
		$request_data['customer_receipt'] = ( 'yes' === $this->get_wc_gateway()->get_option( 'send_gateway_receipt' ) );
		$request_data['ipaddress']        = empty( $customer_ip ) ? WC_Geolocation::get_ip_address() : $customer_ip;

		$request_data = apply_filters( $this->get_wc_gateway()->get_id() . '_final_request_data', $request_data, $get_package );

		NMI_Gateway_Woocommerce_Logger::log( "XL NMI WFOCU Final request data sent to NMI for offer charge: " . print_r( $request_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		return $request_data;
	}

	/**
	 * Handling refund offer
	 *
	 * @param $order
	 *
	 * @return bool
	 */
	public function process_refund_offer( $order ) {
		$refund_data = $_POST;

		$txn_id        = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
		$amnt          = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
		$api           = $this->get_wc_gateway()->get_api();
		$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';

		// add refund info
		$order->refund         = new stdClass();
		$order->refund->amount = number_format( $amnt, 2, '.', '' );

		$order->refund->trans_id = $txn_id;

		$order->refund->reason = $refund_reason;

		$response = $api->refund( $order );

		$transaction_id = $response->get_transaction_id();

		WFOCU_Core()->log->log( "XL NMI Gateway Offer refund transaction ID: $transaction_id response: " . print_r( $response, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		if ( ! $transaction_id ) {
			$response                    = $api->void( $order );
			$transaction_id              = $response->get_transaction_id();
			$order->refund->wfocu_voided = true;
			WFOCU_Core()->log->log( "XL NMI Gateway Offer void transaction id: $transaction_id response: " . print_r( $response, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		return $transaction_id ? $transaction_id : false;

	}

	/**
	 * @param $order WC_Order
	 * @param $amnt
	 * @param $refund_id
	 * @param $offer_id
	 * @param $refund_reason
	 */
	public function wfocu_add_order_note( $order, $amnt, $refund_id, $offer_id, $refund_reason ) {
		if ( isset( $order->refund->wfocu_voided ) && true === $order->refund->wfocu_voided ) {
			/* translators: 1) dollar amount 2) transaction id 3) refund message */
			$refund_note = sprintf( __( 'Voided %1$s - Void Txn ID: %2$s <br/>Offer: %3$s(#%4$s) %5$s', 'woofunnels-upstroke-one-click-upsell' ), $amnt, $refund_id, get_the_title( $offer_id ), $offer_id, $refund_reason );
			$order->add_order_note( $refund_note );
		} else {
			parent::wfocu_add_order_note( $order, $amnt, $refund_id, $offer_id, $refund_reason );
		}
	}
}

NMI_Gateway_Woocommerce_Upstroke_Compatibility::get_instance();
