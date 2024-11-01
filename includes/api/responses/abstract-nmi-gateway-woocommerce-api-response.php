<?php
/**
 * NMI WooCommerce Woocommerce
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
 * @package   woofunnels-woocommerce-nmi-gateway/includes/api/responses
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Abstract Response Class
 *
 * Provides functionality common to all responses
 *
 * @since 1.0.0
 */
abstract class NMI_Gateway_Woocommerce_API_Response implements NMI_Gateway_Woocommerce_Framework\SV_WC_API_Response, NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_response, NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Authorization_Response, NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response, NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Customer_Response {


	/** @var mixed raw response from the NMI_Gateway_Woocommerce SDK */
	protected $response;


	/**
	 * NMI_Gateway_Woocommerce_API_Response constructor.
	 *
	 * @param $response
	 *
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function __construct( $response ) {

		$this->response = $response;

		if ( ! $this->transaction_approved() ) {
			$this->display_error();
		}
	}


	/**
	 * Checks if the transaction was successful. NMI's "success" attribute
	 * indicates _both_ that the request was successful *and* the transaction
	 * (if the request was a transaction) was successful. If a request/transaction
	 * isn't successful, it's due to one or more of the following 4 things:
	 *
	 * 1) Validation failure - invalid request data or the request itself was invalid
	 * 2) Gateway Rejection - the gateway rejected the transaction (duplicate check, AVS, CVV, fraud)
	 * 3) Processor Declined - the merchant processor declined the transaction (soft/hard decline, depends on error code)
	 *
	 * Note that exceptions are handled prior to response "parsing" so there's no
	 * handling for them here.
	 *
	 * @return bool true if approved, false otherwise
	 * @see SV_WC_Payment_Gateway_API_Response::transaction_approved()
	 * @since 1.0.0
	 */
	public function transaction_approved() {

		return ( isset( $this->response->approved ) ) ? $this->response->approved : false;
	}


	/**
	 * NMI_Gateway_Woocommerce does not support the concept of held requests/transactions, so this does not apply
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function transaction_held() {
		return false;
	}


	/**
	 * Gets the transaction status code
	 *
	 * @return string status code
	 * @see SV_WC_Payment_Gateway_API_Response::get_status_code()
	 * @since 1.0.0
	 */
	public function get_status_code() {

		return isset( $this->response->response_code ) ? $this->response->response_code : '';
	}


	/**
	 * Gets the transaction status message
	 *
	 * @return string status message
	 * @see SV_WC_Payment_Gateway_API_Response::get_status_message()
	 * @since 1.0.0
	 */
	public function get_status_message() {

		return isset( $this->response->responsetext ) ? $this->response->responsetext : '';
	}

	/**
	 * Get the failure status info for the given parameter, either code or message
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_failure_status_info() {
		return array(
			'code'    => $this->get_status_code(),
			'message' => $this->get_status_message()
		);
	}

	/**
	 * Returns the result of the AVS check
	 *
	 * @return string result of the AVS check, if any
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_avs_result()
	 * @since 1.0.0
	 */
	public function get_avs_result() {

		return isset( $this->response->avsresponse ) ? $this->response->avsresponse : '';

	}

	/**
	 * Returns true if the response contains validation errors (API call
	 * cannot be processed because the request was invalid)
	 *
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function has_validation_errors() {

		return ! isset( $this->response->approved );
	}

	/**
	 * Get the authorization code
	 *
	 * @return string 6 character credit card authorization code
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_authorization_code()
	 * @since 1.0.0
	 */
	public function get_authorization_code() {

		return ( isset( $this->response->authcode ) && ! empty( $this->response->authcode ) ) ? $this->response->authcode : null;
	}

	/**
	 * Returns the result of the CSC check
	 *
	 * @return string result of CSC check
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_csc_result()
	 * @since 1.0.0
	 */
	public function get_csc_result() {

		return ( isset( $this->response->cvvresponse ) && ! empty( $this->response->cvvresponse ) ) ? $this->response->cvvresponse : null;
	}

	/**
	 * Returns true if the CSC check was successful
	 *
	 * @return boolean true if the CSC check was successful
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::csc_match()
	 * @since 1.0.0
	 */
	public function csc_match() {

		return $this->get_csc_result() === self::CSC_MATCH;
	}

	/**
	 * Get the error message suitable for displaying to the customer. This should
	 * provide enough information to be helpful for correcting customer-solvable
	 * issues (e.g. invalid CVV) but not enough to help nefarious folks fishing
	 * for data
	 *
	 * @since 1.0.0
	 */
	public function get_user_message() {

		$helper = new NMI_Gateway_Woocommerce_API_Response_Message_Helper( $this );

		return $helper->get_message();
	}


	/**
	 * Return the string representation of the response
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function to_string() {
		// TODO: print this nicer and with less irrelevant information (e.g. subscription attributes, etc) @MR 2015-11-05
		return print_r( $this->response, true ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}


	/**
	 * Return the string representation of the response, stripped of any
	 * confidential info
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function to_string_safe() {
		// no idea yet
		return $this->to_string();
	}

	/**
	 * Return the response type, either `credit-card` or `paypal`
	 *
	 * @return string
	 * @since 1.0.0
	 */
	protected function get_response_type() {
		return 'credit-card';
	}


	/**
	 * Return the payment type for the response, either `credit-card` or `paypal`
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_payment_type() {
		return 'credit-card';
	}


	/**
	 * Return true always as we are implementing only credit card
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	protected function is_credit_card_response() {

		return true;
	}

	/**
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function display_error() {
		$message = $this->get_user_message();

		throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
	}

	/**
	 * @return bool|string|WC_Order|
	 */
	public function get_order() {
		if ( isset( $this->response->orderid ) ) {
			return wc_get_order( $this->response->orderid );
		} else {
			return $this->get_user_message();
		}
	}

	/**
	 * @return int
	 */
	public function get_response_orderid() {
		return isset( $this->response->orderid ) ? $this->response->orderid : 0;
	}

	/**
	 * Updates order meta data.
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return false
	 */
	public function update_order_meta( $key, $value ) {

		$order = $this->get_order();
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::update_meta_data( $order, $this->get_order_meta_prefix() . $key, $value );
	}

	/**
	 * Gets the order meta prefixed used for the *_order_meta() methods
	 *
	 * Defaults to `_wc_{gateway_id}_`
	 *
	 * @return string
	 * @since 2.2.0
	 */
	public function get_order_meta_prefix() {
		return '_wc_' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '_';
	}

	/**
	 * * Get the credit card payment token object created during this transaction
	 * @return WC_Payment_Token|null
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 */
	public function get_payment_token_object() {

		if ( empty( $this->response->customer_vault_id ) ) {
			throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( __( 'Required credit card token is missing or empty!', 'woofunnels-woocommerce-nmi-gateway' ) );
		}

		$args = array(
			'token_id'   => '',
			'user_id'    => $this->get_customer_id(),
			'gateway_id' => NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID,
			'type'       => '',
		);

		$tokens = WC_Payment_Tokens::get_tokens( $args );
		foreach ( $tokens as $token ) {
			if ( $token->get_token() === $this->response->customer_vault_id ) {
				return $token;
			}
		}

		return __return_null();
	}

	/**
	 * Get the masked card number, which is the first 6 digits followed by 6 asterisks then the last 4 digits. This complies with PCI security standards.
	 * @return string
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_masked_number() {
		$masked_number = isset( $this->response->cc_number ) ? $this->response->cc_number : '';
		if ( empty( $masked_number ) && isset( $this->response->order->account_number ) && ! empty( $this->response->order->account_number ) ) {
			$masked_number = $this->response->order->account_number;
			$masked_number = substr( $masked_number, 0, 6 ) . '******' . substr( $masked_number, - 4 );
		} elseif ( empty( $masked_number ) && isset( $this->response->customer_vault_id ) && ! empty( $this->response->customer_vault_id ) ) {
			$token = $this->get_payment_token_object();
			if ( ! empty( $token ) ) {
				$last4         = $token->get_last4();
				$masked_number = '************' . $last4;
			}
		}

		return $masked_number;
	}

	/**
	 * Get the last four digits of the card number used for this transaction
	 * @return string
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_last_four() {
		$last4 = '';
		if ( isset( $this->response->order->last_four ) && ! empty( $this->response->order->last_four ) ) {
			$last4 = $this->response->order->last_four;
		} elseif ( isset( $this->response->customer_vault_id ) && ! empty( $this->response->customer_vault_id ) ) {
			$token = $this->get_payment_token_object();
			if ( ! empty( $token ) ) {
				$last4 = $token->get_last4();
			}
		}

		return $last4;
	}

	/**
	 * Get the last four digits of the card number used for this transaction
	 * @return string
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_card_type() {
		$card_type = '';
		if ( isset( $this->response->order->card_type ) && ! empty( $this->response->order->card_type ) ) {
			$card_type = $this->response->order->card_type;
		} elseif ( isset( $this->response->customer_vault_id ) && ! empty( $this->response->customer_vault_id ) ) {
			$token = $this->get_payment_token_object();
			if ( ! empty( $token ) ) {
				$card_type = $token->get_card_type();
			}
		}

		return $card_type;
	}

	/**
	 * Get the expiration month (MM) of the card number used for this transaction
	 * @return string
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_exp_month() {
		$exp_month = '';
		if ( isset( $this->response->order->exp_month ) && ! empty( $this->response->order->exp_month ) ) {
			$exp_month = $this->response->order->exp_month;
		} elseif ( isset( $this->response->customer_vault_id ) && ! empty( $this->response->customer_vault_id ) ) {
			$token = $this->get_payment_token_object();
			if ( ! empty( $token ) ) {
				$exp_month = $token->get_expiry_month();
			}
		}

		return $exp_month;
	}

	/**
	 * Get the expiration year (YYYY) of the card number used for this transaction
	 * @return string
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function get_exp_year() {
		$exp_year = '';
		if ( isset( $this->response->order->exp_year ) && ! empty( $this->response->order->exp_year ) ) {
			$exp_year = $this->response->order->exp_year;
		} elseif ( isset( $this->response->customer_vault_id ) && ! empty( $this->response->customer_vault_id ) ) {
			$token = $this->get_payment_token_object();
			if ( ! empty( $token ) ) {
				$exp_year = $token->get_expiry_year();
			}
		}

		return $exp_year;
	}

	public function get_csc() {
		return isset( $this->response->order->csc ) ? $this->response->order->csc : '';
	}

}
