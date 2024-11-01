<?php
/**
 * WooCommerce NMI Gateway
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
 * @package   NMI_Gateway_Woocommerce/Gateway/API
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * WOO XL NMI Gateway API Class
 *
 * This is a pseudo-wrapper around the NMI PHP SDK
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_API extends NMI_Gateway_Woocommerce_Framework\SV_WC_API_Base implements NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API {

	/** @var \NMI_Gateway_Woocommerce_API class instance */
	protected $gateway;

	/** @var \WC_Order order associated with the request, if any */
	protected $order;

	/**
	 * Constructor - setup request object and set endpoint
	 *
	 * NMI_Gateway_Woocommerce_API constructor.
	 *
	 * @param $gateway
	 */
	public function __construct( $gateway ) {

		$this->gateway = $gateway;
	}


	/** API Methods ***********************************************************/

	/**
	 * Create a new credit card charge transaction
	 *
	 * @param WC_Order $order
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function credit_card_charge( WC_Order $order ) {

		// pre-verify CSC
		if ( $this->get_gateway()->is_credit_card_gateway() && $this->get_gateway()->is_csc_required() ) {
			$this->verify_csc( $order );
		}

		$request = $this->get_new_request( array(
			'type'  => 'transaction',
			'order' => $order,
		) );

		$request->create_credit_card_charge();

		return $this->perform_request( $request );
	}

	/** API Tokenization methods **********************************************/


	/**
	 * Tokenize the payment method associated with the order
	 *
	 * @param WC_Order $order
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 *
	 */
	public function tokenize_payment_method( WC_Order $order ) {

		if ( $order->get_id() ) {
			// create card vault id for an order
			$request = $this->get_new_request( array(
				'type'  => 'customer',
				'order' => $order,
			) );
			$request->create_customer( $order );

		} else {
			$max_retries = apply_filters( 'xl_wc_nmi_max_weekly_failed_retries', 0 );
			$user_id     = get_current_user_id();
			if ( $max_retries > 0 && $user_id > 0 ) {
				$current_week = 'week_' . date( 'W' );
				$retries      = get_user_meta( $user_id, 'wc_xl_nmi_retries', true );
				$retries      = is_array( $retries ) ? $retries : [];
				$retry_count  = isset( $retries[ $current_week ] ) ? $retries[ $current_week ] : 0;

				NMI_Gateway_Woocommerce_Logger::log( "NMI checking to block API request in function: " . __FUNCTION__ . ": User_id: $user_id, Current week: $current_week, Max retries: $max_retries, Retry count: $retry_count, Retries: " . print_r( $retries, true ) );

				if ( $retry_count >= $max_retries ) {
					throw new NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception( "Maximum attempt limit reached. Please contact us for further help." );
				}
			}

			// create customer vault via my-account screen
			$request = $this->get_new_request( array(
				'type'  => 'payment-method',
				'order' => $order,
			) );
			$request->create_payment_method( $order );
		}

		return $this->perform_request( $request );
	}


	/**
	 * Create a new credit card auth transaction
	 *
	 * @param WC_Order $order
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 *
	 */
	public function credit_card_authorization( WC_Order $order ) {

		// pre-verify CSC
		if ( $this->get_gateway()->is_credit_card_gateway() && $this->get_gateway()->is_csc_required() ) {
			$this->verify_csc( $order );
		}

		$request = $this->get_new_request( array(
			'type'  => 'transaction',
			'order' => $order,
		) );

		$request->create_credit_card_auth();

		return $this->perform_request( $request );
	}


	/**
	 * Verify the CSC for a transaction when using a saved payment toke and CSC is required. This must be done prior to processing the actual transaction.
	 *
	 * @param WC_Order $order
	 *
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	public function verify_csc( WC_Order $order ) {

		if ( ! empty( $order->payment->nonce ) && ! empty( $order->payment->token ) ) {

			$request = $this->get_new_request( array(
				'type'  => 'payment-method',
				'order' => $order,
			) );

			$request->verify_csc( $order->payment->token, $order->payment->nonce );

			$result = $this->perform_request( $request );

			if ( ! $result->transaction_approved() ) {

				if ( $result->has_avs_rejection() ) {

					$message = __( 'The billing address for this transaction does not match the cardholders.', 'woofunnels-woocommerce-nmi-gateway' );

				} elseif ( $result->has_cvv_rejection() ) {

					$message = __( 'The CSC for the transaction was invalid or incorrect.', 'woofunnels-woocommerce-nmi-gateway' );

				} else {

					$message = $result->get_user_message();
				}

				throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
			}
		}
	}


	/**
	 * Capture funds for a authorized credit card
	 *
	 * @param WC_Order $order
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @since 1.0.0
	 *
	 */
	public function credit_card_capture( WC_Order $order ) {

		$request = $this->get_new_request( array(
			'type'  => 'transaction',
			'order' => $order,
		) );

		$request->create_credit_card_capture();

		return $this->perform_request( $request );
	}


	/**
	 * Check Debit - no-op
	 *
	 * @param WC_Order $order
	 *
	 * @return NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response|void
	 */
	public function check_debit( WC_Order $order ) {
	}


	/**
	 * Perform a refund for the order
	 *
	 * @param WC_Order $order
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @since 1.0.0
	 *
	 */
	public function refund( WC_Order $order ) {

		$request = $this->get_new_request( array(
			'type'  => 'transaction',
			'order' => $order,
		) );

		$request->create_refund();

		return $this->perform_request( $request );
	}


	/**
	 * Perform a void for the order
	 *
	 * @param WC_Order $order
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @since 1.0.0
	 *
	 */
	public function void( WC_Order $order ) {

		$request = $this->get_new_request( array(
			'type'  => 'transaction',
			'order' => $order,
		) );

		$request->create_void();

		return $this->perform_request( $request );
	}

	/**
	 * Get the tokenized payment methods for the customer
	 * @param string $customer_id
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_API_Get_Tokenized_Payment_Methods_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 */
	public function get_tokenized_payment_methods( $customer_id ) {
		$request = $this->get_new_request( array( 'type' => 'customer' ) );

		$request->get_payment_methods( $customer_id );

		return $this->perform_request( $request );
	}


	/**
	 * Update the tokenized payment method for given customer
	 *
	 * @param WC_Order $order
	 *
	 * @since 1.0.0
	 *
	 */
	public function update_tokenized_payment_method( WC_Order $order ) {
		// update payment method
	}


	/**
	 * @param string $token
	 * @param string $customer_id
	 *
	 * @return object|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 */
	public function remove_tokenized_payment_method( $token, $customer_id ) {

		$request = $this->get_new_request( array( 'type' => 'payment-method' ) );

		$request->delete_payment_method( $token );

		return $this->perform_request( $request );
	}


	/**
	 * NMI_Gateway_Woocommerce supports retrieving tokenized payment methods
	 *
	 * @return boolean true
	 * @see SV_WC_Payment_Gateway_API::supports_get_tokenized_payment_methods()
	 * @since 1.0.0
	 */
	public function supports_get_tokenized_payment_methods() {
		return false;
	}


	/**
	 * NMI supports removing tokenized payment methods
	 *
	 * @return boolean true
	 * @see SV_WC_Payment_Gateway_API::supports_remove_tokenized_payment_method()
	 * @since 1.0.0
	 */
	public function supports_remove_tokenized_payment_method() {
		return false;
	}


	/**
	 * @param $nonce
	 *
	 * @return object
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 */
	public function get_payment_method_from_nonce( $nonce ) {

		$request = $this->get_new_request( array( 'type' => 'payment-method-nonce' ) );

		$request->get_payment_method( $nonce );

		return $this->perform_request( $request );
	}

	/**
	 * @param $token
	 *
	 * @return object
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 */
	public function get_nonce_from_payment_token( $token ) {

		$request = $this->get_new_request( array( 'type' => 'payment-method-nonce' ) );

		$request->create_nonce( $token );

		return $this->perform_request( $request );
	}


	/** Request/Response Methods **********************************************/


	/**
	 *  Perform a remote request using the NMI SDK. Overrides the standard wp_remote_request() as the SDK already provides a cURL implementation
	 *
	 * @param string $callback
	 * @param string $callback_params
	 *
	 * @return array|NMI_Gateway_Woocommerce_Remote_Response|NMI_Gateway_Woocommerce_Framework\WP_Error
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 */
	protected function do_remote_request( $callback, $callback_params ) {
		$username = $password = $private_key = '';

		if ( 'collect_js' === $this->gateway->get_payment_api_method() ) {
			$private_key = $this->gateway->get_private_key();
		}
		if ( 'direct_post' === $this->gateway->get_payment_api_method() ) {
			$username = $this->gateway->get_gateway_username();
			$password = $this->gateway->get_gateway_password();
		}
		NMI_Gateway_Woocommerce_Logger::log( "NMI primary order: Usename: $username, Pass: $password, Private key: $private_key" );
		$wp_nmi       = new NMI_Gateway_Woocommerce_Remote_Request( $username, $password, $private_key );
		$resource     = $this->get_request()->get_resource();
		$request_data = $this->set_wp_nmi_request_data( $callback_params, $resource );

		try {
			$response = $wp_nmi->do_wp_remote_request( $request_data );

			if ( ! empty( $response->customer_vault_id ) ) {
				$this->get_order()->payment->token    = isset( $response->customer_vault_id ) ? $response->customer_vault_id : '';
				$this->get_order()->payment->tokenize = $this->get_gateway()->tokenization_enabled();
				$this->get_order()->payment->mode     = $this->gateway->get_environment();
			}

			if ( isset( $this->get_order()->payment->account_number ) && ! empty( $this->get_order()->payment->account_number ) ) {
				$this->get_order()->payment->account_number = $this->get_masked_number( $this->get_order()->payment->account_number );
			}
			$response->order = $this->get_order()->payment;

			NMI_Gateway_Woocommerce_Logger::log( 'Final response from NMI: ' . print_r( $response, true ) );  //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			$this->get_gateway()->maybe_record_token_failure_in_usermata( $response, $request_data );

			return $response;

		} catch ( Exception $e ) {
			NMI_Gateway_Woocommerce_Logger::log( 'Exception in do_remote_request: ' . print_r( $e->getMessage(), true ) );  //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			throw new NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception( $this->get_NMI_Gateway_Woocommerce_exception_message( $e ), $e->getCode(), $e );
		}
	}


	/**
	 * Handle and parse the response
	 *
	 * @param array|NMI_Gateway_Woocommerce_Framework\WP_Error $response
	 *
	 * @return object
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 * @since 1.0.0
	 *
	 */
	protected function handle_response( $response ) {
		// check if NMI_Gateway_Woocommerce response contains exception and convert to framework exception
		if ( $response instanceof Exception ) {
			throw new NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception( $this->get_NMI_Gateway_Woocommerce_exception_message( $response ), $response->getCode(), $response );
		}

		$handler_class = $this->get_response_handler();

		// parse the response body and tie it to the request
		$this->response = new $handler_class( $response, 'credit-card' );

		// broadcast request
		$this->broadcast_request();

		return $this->response;
	}


	/**
	 * Get a human-friendly message from the NMI exception object
	 *
	 * @param \Exception $e
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 */
	protected function get_nmi_gateway_woocommerce_exception_message( $e ) {

		$message = $e->getMessage();

		return $message;
	}


	/**
	 * Override the standard request URI with the static callback instead, since
	 * the NMI SDK handles the actual remote request
	 *
	 * @return string
	 * @see SV_WC_API_Base::get_request_uri()
	 * @since 1.0.0
	 */
	protected function get_request_uri() {
		return $this->get_request()->get_callback();
	}


	/**
	 * Override the standard request args with the static callback params instead,
	 * since the NMI handles the actual remote request
	 *
	 * @return array
	 * @see SV_WC_API_Base::get_request_args()
	 * @since 1.0.0
	 */
	protected function get_request_args() {
		return $this->get_request()->get_callback_params();
	}


	/**
	 * Alert other actors that a request has been performed, primarily for
	 * request/response logging.
	 *
	 * @see SV_WC_API_Base::broadcast_request()
	 * @since 1.0.0
	 */
	protected function broadcast_request() {

		$request_data = array(
			'environment' => $this->get_gateway()->get_environment(),
			'uri'         => $this->get_request_uri(),
			'data'        => $this->get_request()->to_string_safe(),
			'duration'    => $this->get_request_duration() . 's', // seconds
		);

		$response_data = array(
			'data' => is_callable( array( $this->get_response(), 'to_string_safe' ) ) ? $this->get_response()->to_string_safe() : print_r( $this->get_response(), true ),
		);

		do_action( 'wc_' . $this->get_api_id() . '_api_request_performed', $request_data, $response_data, $this );
	}


	/**
	 * Builds and returns a new API request object
	 *
	 * @param array $args
	 *
	 * @return NMI_Gateway_Woocommerce_API_Customer_Request|NMI_Gateway_Woocommerce_API_Payment_Method_Request|NMI_Gateway_Woocommerce_API_Transaction_Request|SV_WC_API_Request
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception
	 */
	protected function get_new_request( $args = array() ) {

		$this->order = isset( $args['order'] ) && $args['order'] instanceof WC_Order ? $args['order'] : null;

		switch ( $args['type'] ) {

			case 'transaction':
				$this->set_response_handler( 'NMI_Gateway_Woocommerce_API_Credit_Card_Transaction_Response' );

				return new NMI_Gateway_Woocommerce_API_Transaction_Request( $this->order, $this->get_gateway()->csc_enabled_for_tokens() );

				break;

			case 'customer':
				$this->set_response_handler( 'NMI_Gateway_Woocommerce_API_Customer_Response' );

				return new NMI_Gateway_Woocommerce_API_Customer_Request( $this->order, $this->get_gateway()->get_payment_processor(), $this->gateway->get_environment() );

				break;

			case 'payment-method':
				$this->set_response_handler( 'NMI_Gateway_Woocommerce_API_Payment_Method_Response' );

				return new NMI_Gateway_Woocommerce_API_Payment_Method_Request( $this->order, $this->get_gateway()->get_payment_processor(), $this->gateway->get_environment() );

				break;

			default:
				throw new NMI_Gateway_Woocommerce_Framework\SV_WC_API_Exception( 'Invalid request type' );
		}
	}


	/**
	 * Return the order associated with the request, if any
	 *
	 * @return \WC_Order
	 * @since 1.0.0
	 */
	public function get_order() {

		return $this->order;
	}


	/**
	 * Get the ID for the API, used primarily to namespace the action name
	 * for broadcasting requests
	 *
	 * @return string
	 * @since 1.0.0
	 */
	protected function get_api_id() {
		return $this->get_gateway()->get_id();
	}


	/**
	 * Return the gateway plugin
	 *
	 * @return NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Plugin
	 * @since 1.0.0
	 */
	public function get_plugin() {

		return $this->get_gateway()->get_plugin();
	}


	/**
	 * Returns the gateway class associated with the request
	 *
	 * @return \NMI_Gateway_Woocommerce_API class instance
	 * @since 1.0.0
	 */
	public function get_gateway() {

		return $this->gateway;
	}


	/**
	 * Setting up request data for wp request post request
	 *
	 * @param $args
	 *
	 * @return array
	 */
	public function set_wp_nmi_request_data( $args, $resource ) {
		$request_data = array();
		$log_args     = $args;

		if ( isset( $log_args['payment'] ) && isset( $log_args['payment']['ccnumber'] ) ) {
			$log_args['payment']['ccnumber'] = substr( $log_args['payment']['ccnumber'], 0, 6 ) . '******' . substr( $log_args['payment']['ccnumber'], - 4 );
		}

		if ( isset( $log_args['payment'] ) && isset( $log_args['payment']['cvv'] ) ) {
			$log_args['payment']['cvv'] = ( strlen( $log_args['payment']['cvv'] ) === 4 ) ? '****' : '***';
		}

		NMI_Gateway_Woocommerce_Logger::log( 'Final request args for setting request data to NMI: ' . print_r( $log_args, true ) );  //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		if ( isset( $args['orderid'] ) ) {
			$request_data['orderid'] = $args['orderid'];
		}
		if ( isset( $args['order_description'] ) ) {
			$request_data['order_description'] = substr( $args['order_description'], 0, 99 );
		}
		if ( isset( $args['amount'] ) ) {
			$request_data['amount'] = $args['amount'];
		}
		if ( isset( $args['transactionid'] ) && ! empty( $args['transactionid'] ) ) {
			$request_data['transactionid'] = $args['transactionid'];
		}

		//Transaction type setup
		if ( ! empty( $resource ) ) {
			$request_data['type'] = $resource;
		}

		//Customer
		if ( isset( $args['customer']['phone'] ) ) {
			$request_data['phone'] = $args['customer']['phone'];
		}
		if ( isset( $args['customer']['email'] ) ) {
			$request_data['email'] = $args['customer']['email'];
		}

		//Billing
		$first_name = ( isset( $args['billing'] ) && isset( $args['billing']['firstName'] ) && ! empty( $args['billing']['firstName'] ) ) ? $args['billing']['firstName'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['firstName'] ) ) ? $args['shipping']['firstName'] : '' );
		$last_name  = ( isset( $args['billing'] ) && isset( $args['billing']['lastName'] ) && ! empty( $args['billing']['lastName'] ) ) ? $args['billing']['lastName'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['lastName'] ) ) ? $args['shipping']['lastName'] : '' );
		$company    = ( isset( $args['billing'] ) && isset( $args['billing']['company'] ) && ! empty( $args['billing']['company'] ) ) ? $args['billing']['company'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['company'] ) ) ? $args['shipping']['company'] : '' );
		$address1   = ( isset( $args['billing'] ) && isset( $args['billing']['address1'] ) && ! empty( $args['billing']['address1'] ) ) ? $args['billing']['address1'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['streetAddress'] ) ) ? $args['shipping']['streetAddress'] : '' );
		$address2   = ( isset( $args['billing'] ) && isset( $args['billing']['address2'] ) && ! empty( $args['billing']['address2'] ) ) ? $args['billing']['address2'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['extendedAddress'] ) ) ? $args['shipping']['extendedAddress'] : '' );
		$city       = ( isset( $args['billing'] ) && isset( $args['billing']['city'] ) && ! empty( $args['billing']['city'] ) ) ? $args['billing']['city'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['locality'] ) ) ? $args['shipping']['locality'] : '' );
		$state      = ( isset( $args['billing'] ) && isset( $args['billing']['state'] ) && ! empty( $args['billing']['state'] ) ) ? $args['billing']['state'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['region'] ) ) ? $args['shipping']['region'] : '' );
		$country    = ( isset( $args['billing'] ) && isset( $args['billing']['country'] ) && ! empty( $args['billing']['country'] ) ) ? $args['billing']['country'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['countryCodeAlpha2'] ) ) ? $args['shipping']['countryCodeAlpha2'] : '' );
		$zip        = ( isset( $args['billing'] ) && isset( $args['billing']['zip'] ) && ! empty( $args['billing']['zip'] ) ) ? $args['billing']['zip'] : ( ( isset( $args['shipping'] ) && isset( $args['shipping']['postalCode'] ) ) ? $args['shipping']['postalCode'] : '' );

		if ( ! empty( $first_name ) ) {
			$request_data['first_name'] = $first_name;
		}
		if ( ! empty( $last_name ) ) {
			$request_data['last_name'] = $last_name;
		}
		if ( ! empty( $company ) ) {
			$request_data['company'] = $company;
		}
		if ( ! empty( $address1 ) ) {
			$request_data['address1'] = $address1;
		}
		if ( ! empty( $address2 ) ) {
			$request_data['address2'] = $address2;
		}
		if ( ! empty( $city ) ) {
			$request_data['city'] = $city;
		}
		if ( ! empty( $state ) ) {
			$request_data['state'] = $state;
		}
		if ( ! empty( $country ) ) {
			$request_data['country'] = $country;
		}
		if ( ! empty( $zip ) ) {
			$request_data['zip'] = $zip;
		}

		//Payment
		if ( isset( $args['payment']['ccnumber'] ) ) {
			$request_data['ccnumber'] = $args['payment']['ccnumber'];
		}
		if ( isset( $args['payment']['ccexp'] ) ) {
			$request_data['ccexp'] = $args['payment']['ccexp'];
		}
		if ( isset( $args['payment']['cvv'] ) ) {
			$request_data['cvv'] = $args['payment']['cvv'];
		}
		if ( isset( $args['payment']['customer_vault'] ) ) {
			$request_data['customer_vault'] = $args['payment']['customer_vault']; //add_customer or update_customer
		}
		if ( isset( $args['payment']['customer_vault_id'] ) && ! empty( $args['payment']['customer_vault_id'] ) ) {
			$request_data['customer_vault_id'] = $args['payment']['customer_vault_id']; //payment_token order meta
		}
		if ( isset( $args['payment']['payment_token'] ) && ! empty( $args['payment']['payment_token'] ) ) {
			$request_data['payment_token'] = $args['payment']['payment_token']; //Collect.js payment token
		}

		$request_data['currency']         = isset( $args['payment']['currency'] ) ? $args['payment']['currency'] : get_woocommerce_currency();
		$request_data['customer_receipt'] = ( 'yes' === $this->get_gateway()->get_option( 'send_gateway_receipt' ) );
		$request_data['ipaddress']        = isset( $args['ipaddress'] ) ? $args['ipaddress'] : WC_Geolocation::get_ip_address();

		$request_data = apply_filters( $this->get_api_id() . '_final_request_data', $request_data, $args );

		$log_request_data = $request_data;

		if ( isset( $log_request_data['cvv'] ) ) {
			$log_request_data['cvv'] = ( strlen( $log_request_data['cvv'] ) === 4 ) ? '****' : '***';
		}

		if ( isset( $log_request_data['ccnumber'] ) ) {
			$log_request_data['ccnumber'] = substr( $log_request_data['ccnumber'], 0, 6 ) . '******' . substr( $log_request_data['ccnumber'], - 4 );
		}

		NMI_Gateway_Woocommerce_Logger::log( 'Final request data sent to NMI: ' . print_r( $log_request_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		return $request_data;
	}

	/**
	 * Get the masked card number, which is the first 6 digits followed by
	 * 6 asterisks then the last 4 digits. This complies with PCI security standards.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_masked_number( $card_no ) {
		$masked_number = '';
		if ( ! empty( $card_no ) ) {
			$masked_number = substr( $card_no, 0, 6 ) . '******' . substr( $card_no, - 4 );
		}

		return $masked_number;
	}
}
