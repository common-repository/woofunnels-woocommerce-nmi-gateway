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
 * DISCLAIMER
 *
 * @package   woofunnels-woocommerce-nmi-gateway/includes
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce Payment Method Handler Class
 *
 * Extends the framework payment tokens handler class to provide NMI-specific
 * functionality
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_Payment_Method_Handler extends NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Payment_Tokens_Handler {

	/**
	 * Return a custom payment token class instance
	 *
	 * @param string $token_id token ID
	 * @param array $data token data
	 *
	 * @return \NMI_Gateway_Woocommerce_Payment_Method
	 * @see SV_WC_Payment_Gateway_Payment_Tokens_Handler::build_token()
	 *
	 * @since 1.0.0
	 */
	public function build_token( $token_id, $data ) {
		return new NMI_Gateway_Woocommerce_Payment_Method( $token_id, $data );
	}

	/**
	 * Checking if current token is belong to current user
	 *
	 * @param int $user_id
	 * @param NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Payment_Token|string $token
	 * @param null|string $environment_id
	 *
	 * @return bool
	 */
	public function user_has_token( $user_id, $token, $environment_id = null ) {
		if ( ! $environment_id ) {
			$environment_id = $this->gateway->get_environment();
		}

		$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );
		NMI_Gateway_Woocommerce_Logger::log( "User has token environment: $environment_id, Token: $token, user id: $user_id, customer tokens: " . print_r( array_keys( $customer_tokens ), true ) );

		foreach ( is_array( $customer_tokens ) ? $customer_tokens : array() as $customer_token ) {
			if ( $token === $customer_token->get_token() ) {
				return true;
			}
		}

		return false;

	}

	/**
	 *  Return token if it belong to current user
	 *
	 * @param int $user_id
	 * @param string $token
	 * @param null $environment_id
	 *
	 * @return null|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Payment_Token|string
	 */
	public function get_token( $user_id, $token, $environment_id = null ) {
		if ( empty( $environment_id ) ) {
			$environment_id = $this->gateway->get_environment();
		}

		$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );

		foreach ( is_array( $customer_tokens ) ? $customer_tokens : array() as $customer_token ) {
			if ( $token === $customer_token->get_token() ) {
				return $this->build_token( $customer_token->get_token(), $this->get_token_data( $customer_token ) );
			}
		}

		return null;
	}

	/** Handle all tokens *************************************************************************/


	/**
	 * Get the available payment tokens for a user as an associative array of
	 * payment token to SV_WC_Payment_Gateway_Payment_Token
	 *
	 * @param int $user_id WordPress user identifier, or 0 for guest
	 * @param array $args optional arguments, can include
	 *    `customer_id` - if not provided, this will be looked up based on $user_id
	 *    `environment_id` - defaults to plugin current environment
	 *
	 * @return array associative array of string token to SV_WC_Payment_Gateway_Payment_Token object
	 * @since 1.0.0
	 *
	 */
	public function get_tokens( $user_id, $args = array() ) {

		// default to current environment
		if ( ! isset( $args['environment_id'] ) ) {
			$args['environment_id'] = $this->get_environment_id();
		}

		if ( ! isset( $args['customer_id'] ) ) {
			$args['customer_id'] = $this->get_gateway()->get_customer_id( $user_id, array( 'environment_id' => $args['environment_id'] ) );
		}

		$environment_id = $args['environment_id'];
		$customer_id    = $args['customer_id'];
		$transient_key  = $this->get_transient_key( $user_id );

		// return tokens cached during a single request
		if ( isset( $this->tokens[ $environment_id ][ $user_id ] ) ) {
			return $this->tokens[ $environment_id ][ $user_id ];
		}

		// return tokens cached in transient
		if ( $transient_key && ( false !== ( $this->tokens[ $environment_id ][ $user_id ] = get_transient( $transient_key ) ) ) ) {
			return $this->tokens[ $environment_id ][ $user_id ];
		}

		$this->tokens[ $environment_id ][ $user_id ] = array();
		$tokens                                      = array();

		// retrieve the datastore persisted tokens first, so we have them for
		// gateways that don't support fetching them over an API, as well as the
		// default token for those that do
		if ( $user_id ) {

			$args = array(
				'token_id'   => '',
				'user_id'    => $user_id,
				'gateway_id' => NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID,
				'type'       => '',
			);

			$_tokens = WC_Payment_Tokens::get_tokens( $args );

			/**  Filtering production and sandbox tokens **/
			foreach ( is_array( $_tokens ) ? $_tokens : array() as $token_id => $token ) {
				if ( $environment_id !== $token->get_meta( 'mode' ) ) {
					unset( $_tokens[ $token_id ] );
				}
			}

			// from database format
			if ( is_array( $_tokens ) ) {
				foreach ( $_tokens as $token ) {
					$tokens[ $token->get_token() ] = $this->build_token( $token->get_token(), $this->get_token_data( $token ) );
				}
			}

			$this->tokens[ $environment_id ][ $user_id ] = $tokens;

		}

		// set the payment type image url, if any, for convenience
		foreach ( $this->tokens[ $environment_id ][ $user_id ] as $key => $token ) {
			$this->tokens[ $environment_id ][ $user_id ][ $key ]->set_image_url( $this->get_gateway()->get_payment_method_image_url( $token->get_card_type() ) );
		}


		if ( $transient_key ) {
			set_transient( $transient_key, $this->tokens[ $environment_id ][ $user_id ], 60 );
		}

		/**
		 * Direct Payment Gateway Payment Tokens Loaded Action.
		 *
		 * Fired when payment tokens have been completely loaded.
		 *
		 * @param array $tokens array of SV_WC_Payment_Gateway_Payment_Tokens
		 * @param NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Direct direct gateway class instance
		 *
		 * @since 1.0.0
		 *
		 */
		do_action( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_tokens_loaded', $this->tokens[ $environment_id ][ $user_id ], $this );

		return $this->tokens[ $environment_id ][ $user_id ];
	}

	/**
	 * @param $token
	 *
	 * @return array
	 */
	public function get_token_data( $token ) {
		if ( ! empty( $token ) ) {
			// credit card
			return array(
				'default'            => false,
				'type'               => $token->get_type(),
				'last_four'          => $token->get_last4(),
				'card_type'          => $token->get_card_type(),
				'exp_month'          => $token->get_expiry_month(),
				'exp_year'           => $token->get_expiry_year(),
				'billing_address_id' => null,
			);
		}

		return __return_null();
	}

	/**
	 * Delete a credit card token from user meta
	 *
	 * @param int $user_id user identifier
	 * @param NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Payment_Token|string $token the payment token to delete
	 * @param string $environment_id optional environment id, defaults to plugin current environment
	 *
	 * @return bool|int false if not deleted, updated user meta ID if deleted
	 * @since 1.0.0
	 *
	 */
	public function remove_token( $user_id, $token, $environment_id = null ) {

		// default to current environment
		if ( is_null( $environment_id ) ) {
			$environment_id = $this->gateway->get_environment();
		}

		// unknown token?
		if ( ! $this->user_has_token( $user_id, $token, $environment_id ) ) {
			return false;
		}

		// get the payment token object as needed
		if ( ! is_object( $token ) ) {
			$token = $this->get_token( $user_id, $token, $environment_id );
		}

		/** Removing token from WC Payment tokens **/
		$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );

		foreach ( is_array( $customer_tokens ) ? $customer_tokens : array() as $token_id => $customer_token ) {
			if ( $token->get_id() === $customer_token->get_token() ) {
				WC_Payment_Tokens::delete( $token_id );
			}
		}

		// for direct gateways that allow it, attempt to delete the token from the endpoint
		if ( $this->get_gateway()->get_api()->supports_remove_tokenized_payment_method() ) {

			try {

				$response = $this->get_gateway()->get_api()->remove_tokenized_payment_method( $token->get_id(), $this->get_gateway()->get_customer_id( $user_id, array( 'environment_id' => $environment_id ) ) );

				if ( ! $response->transaction_approved() && ! $this->should_delete_token( $token, $response ) ) {
					return false;
				}

			} catch ( NMI_Gateway_Woocommerce_Framework\SV_WC_Plugin_Exception $e ) {

				if ( $this->get_gateway()->debug_log() ) {
					$this->get_gateway()->get_plugin()->log( $e->getMessage(), $this->get_gateway()->get_id() );
				}

				return false;
			}
		}

		return $this->delete_token( $user_id, $token );
	}

	/**
	 *
	 * Tokenize the current payment method and adds the standard transaction data to the order post record.
	 *
	 * @param WC_Order $order
	 * @param null $response
	 * @param null $environment_id
	 *
	 * @return NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response|WC_Order
	 * @return WC_Order
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 *
	 * @throws Exception
	 * @since 1.0.0
	 */
	public function create_token( \WC_Order $order, $response = null, $environment_id = null ) {

		$gateway  = $this->get_gateway();
		$order_id = BWF_WC_Compatibility::get_order_id( $order );

		$order_token = $gateway->get_order_meta( $order, 'payment_token' );

		NMI_Gateway_Woocommerce_Logger::log( "XL NMI: For order id: $order_id, token in meta is: $order_token, payment object: " . print_r( $order->payment, true ) );

		$token_id        = isset( $_POST[ 'wc-' . $gateway->get_id() . '-payment-token' ] ) ? wc_clean( $_POST[ 'wc-' . $gateway->get_id() . '-payment-token' ] ) : '';
		$changed_payment = isset( $_POST['woocommerce_change_payment'] ) ? wc_clean( $_POST['woocommerce_change_payment'] ) : 0;

		if ( ! empty( $order_token ) && 'new' !== $token_id && $changed_payment < 1 ) { //Order having token in meta (like subscriptions renewals and authorized orders
			if ( ! isset( $order->payment ) ) {
				$order->payment = new stdClass();
			}
			$order->payment->token = $order_token;
			NMI_Gateway_Woocommerce_Logger::log( "Tokenized order having order id: $order_id, token in meta is: $order_token." );

			return $order;
		}

		if ( ( $order->get_customer_id() && $gateway->tokenization_enabled() ) || $gateway->should_force_tokenize() ) { //Logged in user

			$js_response     = isset( $_POST['xl_wc_nmi_js_response'] ) ? json_decode( stripslashes( $_POST['xl_wc_nmi_js_response'] ), true ) : [];
			$card_data       = ( isset( $js_response['card'] ) && is_array( $js_response['card'] ) ) ? $js_response['card'] : [];
			$xl_nmi_js_token = isset( $_POST['xl_wc_nmi_js_token'] ) ? $_POST['xl_wc_nmi_js_token'] : '';
			$xl_nmi_js_token = ( empty( $xl_nmi_js_token ) && isset( $card_data['token'] ) ) ? $card_data['token'] : $xl_nmi_js_token;

			if ( empty( $xl_nmi_js_token ) && $token_id && 'new' !== $token_id ) {
				$nmi_csc = '';
				if ( $gateway->csc_enabled_for_tokens() && isset( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-csc-' . $token_id ] ) && ! empty( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-csc-' . $token_id ] ) && strlen( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-csc-' . $token_id ] ) > 2 ) {
					$nmi_csc = wc_clean( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-csc-' . $token_id ] );

					if ( function_exists( 'WFOCU_Core' ) ) {
						WFOCU_Core()->data->set( 'nmi_csc', $nmi_csc );
					}
				} elseif ( $gateway->csc_enabled_for_tokens() && $changed_payment < 1 ) {
					$message = __( 'Please enter your 3 or 4 digit card code to proceed', 'woofunnels-woocommerce-nmi-gateway' );
					throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
				}

				$order       = $gateway->add_card_id_in_order_from_token( $token_id, $nmi_csc, $order );
				$order_token = isset( $order->payment->token ) ? $order->payment->token : '';
				NMI_Gateway_Woocommerce_Logger::log( "Selected token id: $token_id for order id: $order_id, updated meta is: $order_token " );

				return $order;
			}

			$maybe_save_my_card = isset( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-tokenize-payment-method' ] ) && ! empty( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-tokenize-payment-method' ] );
			if ( $maybe_save_my_card || $gateway->should_force_tokenize() ) {
				if ( empty( $xl_nmi_js_token ) ) {
					if ( empty( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-account-number' ] ) || empty( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-expiry' ] ) || empty( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-csc' ] ) ) {
						$message = __( 'Please enter all credit card details', 'woofunnels-woocommerce-nmi-gateway' );
						NMI_Gateway_Woocommerce_Logger::log( "$message, Posted data: " . print_r( $_POST, true ) );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}

					// Check for card type supported or not before sending request to create a customer
					if ( ! in_array( $order->payment->card_type, $gateway->settings['card_types'], true ) ) {
						$message = __( 'This Card Type is Not Accepted, please enter any other card.', 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}
					$nmi_csc = isset( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-csc' ] ) ? wc_clean( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-csc' ] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing

					if ( empty( $nmi_csc ) || strlen( $nmi_csc ) < 3 ) {
						$message = __( 'Please enter your 3 or 4 digits card code to proceed.', 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}
					if ( function_exists( 'WFOCU_Core' ) ) {
						WFOCU_Core()->data->set( 'nmi_csc', $nmi_csc );
					}

					$expiry = isset( $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-expiry' ] ) ? explode( ' / ', $_POST[ 'wc-' . $gateway->get_id_dasherized() . '-expiry' ] ) : '';

					$exp_month = isset( $order->payment->exp_month ) ? $order->payment->exp_month : '';
					$exp_month = ( empty( $exp_month ) && isset( $expiry[0] ) ) ? $expiry[0] : $exp_month;
					if ( empty( $exp_month ) || '00' === $exp_month ) {
						$message = __( 'The card expiration month is invalid, please re-enter and try again. Error in function: ' . __FUNCTION__ . ' on line: ' . __LINE__, 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}
					$exp_year = isset( $order->payment->exp_year ) ? $order->payment->exp_year : '';
					$exp_year = ( empty( $exp_year ) && isset( $expiry[1] ) ) ? $expiry[1] : $exp_year;

					if ( empty( $exp_year ) || ! $exp_year || ( strlen( $exp_year ) !== 2 && strlen( $exp_year ) !== 4 ) ) {
						$message = __( 'Please enter a valid card expiry year to proceed.', 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}

					$ex_year = date_create_from_format( 'Y', $exp_year )->format( 'y' );

					if ( date( 'y' ) > $ex_year ) {
						$message = __( 'The card expiration year is invalid, please re-enter and try again.', 'woofunnels-woocommerce-nmi-gateway' );
						throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( $message );
					}
				}

				$order->payment->payment_token = $xl_nmi_js_token;

				$payment_obj    = isset( $order->payment ) ? $order->payment : new stdClass();
				$account_number = isset( $payment_obj->account_number ) ? $payment_obj->account_number : '';
				$nmi_csc        = isset( $payment_obj->csc ) ? $payment_obj->csc : '';
				if ( ! empty( $account_number ) ) { //Masking account number for logging.
					$payment_obj->account_number = substr( $account_number, 0, 6 ) . '******' . substr( $account_number, - 4 );
					if ( ! empty( $nmi_csc ) ) {
						$payment_obj->csc = '***';
					}
				}
				NMI_Gateway_Woocommerce_Logger::log( "Going to create customer_vault_id for the new card. Payment object: " . print_r( $payment_obj, true ) );
				if ( ! empty( $account_number ) ) {
					$order->payment->account_number = $account_number;
					$order->payment->csc            = $nmi_csc;
					$order->payment->tokenize       = true;
				}

				$response = $gateway->get_api()->tokenize_payment_method( $order );
				NMI_Gateway_Woocommerce_Logger::log( "Token created for order: $order_id, Transaction approved: {$response->transaction_approved()}" );
				if ( $response->transaction_approved() ) {
					$gateway->maybe_add_avs_result_to_order_note( $response, $order );
					if ( $response instanceof NMI_Gateway_Woocommerce_API_Customer_Response ) {
						NMI_Gateway_Woocommerce_Logger::log( "Response: NMI_Gateway_Woocommerce_API_Customer_Response" );
						$customer_vault_id = $response->get_customer_vault_id();
					} elseif ( $response instanceof NMI_Gateway_Woocommerce_Remote_Response ) {
						NMI_Gateway_Woocommerce_Logger::log( "Response: NMI_Gateway_Woocommerce_Remote_Response" );
						$customer_vault_id = isset( $response->customer_vault_id ) ? $response->customer_vault_id : '';
					}
					NMI_Gateway_Woocommerce_Logger::log( "Created customer vault id for order id: $order_id is: " . print_r( $customer_vault_id, true ) );
					if ( ! empty( $customer_vault_id ) ) {
						$order->payment->token       = $customer_vault_id;
						$order->payment->token_added = true;
					}
				}
			}
		}

		return $order;
	}
}
