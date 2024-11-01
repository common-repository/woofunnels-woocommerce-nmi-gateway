<?php
/**
 * NMI_Gateway_Woocommerce
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
 * @package   NMI_Gateway_Woocommerce/Gateway/API/Requests/Transaction
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Transaction Request Class
 *
 * Handles transaction requests (charges, auths, captures, refunds, voids)
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_API_Transaction_Request extends NMI_Gateway_Woocommerce_API_Request {

	/** auth and capture transaction type */
	const AUTHORIZE_AND_CAPTURE = true;

	/** authorize-only transaction type */
	const AUTHORIZE_ONLY = false;

	/** Order object **/
	public $order;

	public $csc_enabled;

	/**
	 * NMI_Gateway_Woocommerce_API_Transaction_Request constructor.
	 *
	 * @param WC_Order $order
	 * @param $csc_enabled
	 */
	public function __construct( $order = null, $csc_enabled = null ) {

		parent::__construct( $order );

		$this->order       = $order;
		$this->csc_enabled = $csc_enabled;
	}

	/**
	 * Creates a credit card charge request for the payment method / customer
	 *
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 */
	public function create_credit_card_charge() {

		$this->set_resource( 'sale' );
		$this->set_callback( 'sale' );

		return $this->create_transaction( self::AUTHORIZE_AND_CAPTURE );
	}


	/**
	 * Creates a credit card auth request for the payment method / customer
	 *
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 */
	public function create_credit_card_auth() {
		$this->set_resource( 'auth' );
		$this->set_callback( 'auth' );

		$this->create_transaction( self::AUTHORIZE_ONLY );
	}


	/**
	 * Capture funds for a previous credit card authorization
	 *
	 * @since 1.0.0
	 */
	public function create_credit_card_capture() {

		$this->set_resource( 'capture' );
		$this->set_callback( 'capture' );

		$transaction_id = empty( $this->get_order()->get_transaction_id() ) ? NMI_Gateway_Woocommerce_Framework\SV_WC_Data_Compatibility::get_meta( wc_get_order( $this->get_order()->get_id() ), '_wc_nmi_gateway_woocommerce_credit_card_transaction_id', true ) : $this->get_order()->get_transaction_id();
		$payment_total  = isset( $this->get_order()->payment_total ) ? $this->get_order()->payment_total : '';

		if ( empty( $total ) ) {
			$payment_total = $this->get_order()->get_total();
		}

		$reason = sprintf( __( 'Capturing amount %s for order %s and transaction ID: %s', 'woofunnels-woocommerce-nmi-gateway' ), $payment_total, $this->get_order()->get_order_number(), $transaction_id );

		$this->request_data = array(
			'orderid'           => $this->get_order()->get_id(),
			'order_description' => $reason,
			'amount'            => $payment_total,
			'transactionid'     => $transaction_id,
			'type'              => 'capture',
			'email'             => $this->get_order_prop( 'billing_email' ),
			'currency'          => $this->get_order()->get_currency(),
		);
	}

	/**
	 * Refund funds from a previous transaction
	 *
	 * @since 1.0.0
	 */
	public function create_refund() {
		$this->set_resource( 'refund' );
		$this->set_callback( 'refund' );

		$reason = sprintf( __( 'Refunding amount %s for order %s', 'woofunnels-woocommerce-nmi-gateway' ), $this->get_order()->refund->amount, $this->get_order()->get_order_number() );

		$this->request_data = array(
			'amount'            => $this->get_order()->refund->amount,
			'transactionid'     => $this->get_order()->refund->trans_id,
			'email'             => $this->get_order_prop( 'billing_email' ),
			'type'              => 'refund',
			'order_description' => $reason,
			'currency'          => $this->get_order()->get_currency(),
		);
	}


	/**
	 * Void a previous transaction
	 *
	 * @since 1.0.0
	 */
	public function create_void() {
		$this->set_resource( 'void' );
		$this->set_callback( 'void' );

		$reason = sprintf( __( 'Cancel an order of amount %s for order %s', 'woofunnels-woocommerce-nmi-gateway' ), $this->get_order()->refund->amount, $this->get_order()->get_order_number() );

		$this->request_data = array(
			'amount'            => $this->get_order()->refund->amount,
			'transactionid'     => $this->get_order()->get_transaction_id(),
			'email'             => $this->get_order_prop( 'billing_email' ),
			'type'              => 'cancel',
			'order_description' => $reason,
			'currency'          => $this->get_order()->get_currency(),
		);
	}


	/**
	 * Create a sale transaction with the given settlement type
	 *
	 * @param $transaction_type
	 *
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	protected function create_transaction( $transaction_type ) {
		$type = ( $transaction_type === self::AUTHORIZE_AND_CAPTURE ) ? 'sale' : 'auth';

		$description        = sprintf( __( '%s - Order %s', 'woofunnels-woocommerce-nmi-gateway' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $this->get_order()->get_order_number() );
		$this->request_data = array(
			'orderid'           => $this->get_order()->get_id(),
			'order_description' => $description,
			'amount'            => $this->get_order()->payment_total,
			'transactionid'     => $this->get_order()->get_transaction_id(),
			'type'              => $type,
		);

		// set customer data
		$this->set_customer();

		// set billing data
		$this->set_billing();

		//set shipping data
		$this->set_shipping();

		// set payment method, either existing token or nonce
		$this->set_payment_method();

		/**
		 * Filters the request data for new transactions.
		 *
		 * @param array $data The transaction/sale data
		 * @param \NMI_Gateway_Woocommerce_API_Transaction_Request $request the request object
		 *
		 * @since 1.0.0
		 *
		 */
		$this->request_data = apply_filters( NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '_transaction_data', $this->request_data, $this );
	}

	protected function set_payment_method() {
		parent::set_payment_method(); // TODO: Change the autogenerated stub
		$nmi_csc = isset( $this->get_order()->payment->csc ) ? $this->get_order()->payment->csc : '';
		$nmi_csc = ( empty( $nmi_csc ) && isset( $_POST[ 'wc-' . $this->get_id_dasherized() . '-csc' ] ) ) ? $_POST[ 'wc-' . $this->get_id_dasherized() . '-csc' ] : $nmi_csc;

		$customer_vault_id = isset( $this->get_order()->payment->token ) ? $this->get_order()->payment->token : '';
		$customer_vault_id = empty( $customer_vault_id ) ? NMI_Gateway_Woocommerce_Framework\SV_WC_Data_Compatibility::get_meta( $this->get_order(), '_wc_' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '_payment_token', true ) : $customer_vault_id;

		$xl_nmi_js_token = isset( $_POST['xl_wc_nmi_js_token'] ) ? $_POST['xl_wc_nmi_js_token'] : '';
		$token_id        = isset( $_POST[ 'wc-' . $this->get_id_dasherized() . '-payment-token' ] ) ? wc_clean( $_POST[ 'wc-' . $this->get_id_dasherized() . '-payment-token' ] ) : '';
		$token_id        = ( empty( $token_id ) && isset( $_POST[ 'wc-' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '-payment-token' ] ) ) ? wc_clean( $_POST[ 'wc-' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '-payment-token' ] ) : '';

		NMI_Gateway_Woocommerce_Logger::log( "Set payment method: $customer_vault_id, NMI CSC: $nmi_csc, XL nmi js token: $xl_nmi_js_token, token id: $token_id" );

		if ( ! empty( $nmi_csc ) ) {
			$this->get_order()->payment->csc      = $nmi_csc;
			$this->request_data['payment']['cvv'] = $nmi_csc;
		}

		if ( ! empty( $customer_vault_id ) ) {
			$this->request_data['payment']['customer_vault_id'] = $customer_vault_id;
			$this->get_order()->payment->token                  = $customer_vault_id;
			NMI_Gateway_Woocommerce_Logger::log( "Case 1" );

		} else {
			if ( ! empty( $xl_nmi_js_token ) ) {
				if ( empty( $token_id ) ) {
					$this->request_data['payment']['payment_token'] = $xl_nmi_js_token;

					$js_response = isset( $_POST['xl_wc_nmi_js_response'] ) ? json_decode( stripslashes( $_POST['xl_wc_nmi_js_response'] ), true ) : [];
					$card_data   = ( isset( $js_response['card'] ) && is_array( $js_response['card'] ) ) ? $js_response['card'] : [];

					$account_number = isset( $card_data['number'] ) ? $card_data['number'] : '';
					$last_four      = isset( $card_data['number'] ) ? substr( $card_data['number'], '-4' ) : '';
					$card_type      = isset( $card_data['type'] ) ? $card_data['type'] : '';
					$exp_month      = isset( $card_data['exp'] ) ? substr( $card_data['exp'], 0, 2 ) : '00';
					$exp_year       = isset( $card_data['exp'] ) ? substr( $card_data['exp'], 2, 2 ) : '00';

					$this->get_order()->payment->account_number = ( isset( $this->get_order()->payment->account_number ) && ! empty( $this->get_order()->payment->account_number ) ) ? $this->get_order()->payment->account_number : $account_number;
					$this->get_order()->payment->last_four      = ( isset( $this->get_order()->payment->last_four ) && ! empty( $this->get_order()->payment->last_four ) ) ? $this->get_order()->payment->last_four : $last_four;
					$this->get_order()->payment->card_type      = ( isset( $this->get_order()->payment->card_type ) && ! empty( $this->get_order()->payment->card_type ) ) ? $this->get_order()->payment->card_type : $card_type;
					$this->get_order()->payment->exp_month      = ( isset( $this->get_order()->payment->exp_month ) && ! empty( $this->get_order()->payment->exp_month ) ) ? $this->get_order()->payment->exp_month : $exp_month;
					$this->get_order()->payment->exp_year       = ( isset( $this->get_order()->payment->exp_year ) && ! empty( $this->get_order()->payment->exp_year ) ) ? $this->get_order()->payment->exp_year : $exp_year;
					NMI_Gateway_Woocommerce_Logger::log( "Case 2" );

				} else {
					$this->request_data['payment']['customer_vault_id'] = $customer_vault_id;
					$this->get_order()->payment->token                  = $customer_vault_id;
					NMI_Gateway_Woocommerce_Logger::log( "Case 3" );

				}
			} else {
				if ( empty( $token_id ) ) {
					$expiry    = isset( $_POST[ 'wc-' . $this->get_id_dasherized() . '-expiry' ] ) ? explode( ' / ', $_POST[ 'wc-' . $this->get_id_dasherized() . '-expiry' ] ) : '';
					$exp_month = isset( $this->get_order()->payment->exp_month ) ? $this->get_order()->payment->exp_month : '';
					$exp_month = ( ! empty( $exp_month ) && isset( $expiry[0] ) ) ? $expiry[0] : $exp_month;
					if ( empty( $exp_month ) || '00' === $exp_month ) {
						$message = __( 'The card expiration month is invalid, please re-enter and try again. Error in function: ' . __FUNCTION__ . ' on line: ' . __LINE__, 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}

					$exp_year = isset( $this->get_order()->payment->exp_year ) ? $this->get_order()->payment->exp_year : '';
					$exp_year = ( ! empty( $exp_year ) && isset( $expiry[1] ) ) ? $expiry[1] : $exp_year;

					if ( empty( $exp_year ) || ! $exp_year || ( strlen( $exp_year ) !== 2 && strlen( $exp_year ) !== 4 ) ) {
						$message = __( 'Please enter a valid card expiry year to proceed', 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}

					if ( empty( $nmi_csc ) || strlen( $nmi_csc ) < 2 ) {
						$message = __( 'Please enter your 3 or 4 digit card code to proceed.', 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}

					$ex_year = date_create_from_format( 'Y', $exp_year )->format( 'y' );
					if ( gmdate( 'y' ) > $ex_year ) {
						$message = __( 'The card expiration year is invalid, please re-enter and try again.', 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}
					$acc_no = isset( $this->get_order()->payment->account_number ) ? $this->get_order()->payment->account_number : '';
					$acc_no = ( empty( $acc_no ) && isset( $_POST[ 'wc-' . $this->get_id_dasherized() . '-account-number' ] ) ) ? $_POST[ 'wc-' . $this->get_id_dasherized() . '-account-number' ] : $acc_no;

					$this->request_data['payment']['ccnumber'] = $acc_no;
					$this->request_data['payment']['ccexp']    = $exp_month . $ex_year;
					NMI_Gateway_Woocommerce_Logger::log( "Case 4" );

				} else {
					$this->request_data['payment']['customer_vault_id'] = $customer_vault_id;
					$this->get_order()->payment->token                  = $customer_vault_id;
					NMI_Gateway_Woocommerce_Logger::log( "Case 5" );

				}
			}
		}


	}

	protected function set_shipping() {
		$this->request_data['shipping'] = $this->get_shipping_address();
	}


	/**
	 * Get the shipping address for the transaction
	 * @return array
	 * @since 1.0.0
	 */
	protected function get_shipping_address() {
		return array(
			'firstName'         => $this->get_order_prop( 'shipping_first_name' ),
			'lastName'          => $this->get_order_prop( 'shipping_last_name' ),
			'company'           => $this->get_order_prop( 'shipping_company' ),
			'streetAddress'     => $this->get_order_prop( 'shipping_address_1' ),
			'extendedAddress'   => $this->get_order_prop( 'shipping_address_2' ),
			'locality'          => $this->get_order_prop( 'shipping_city' ),
			'region'            => $this->get_order_prop( 'shipping_state' ),
			'postalCode'        => $this->get_order_prop( 'shipping_postcode' ),
			'countryCodeAlpha2' => $this->get_order_prop( 'shipping_country' ),
		);

	}
}
