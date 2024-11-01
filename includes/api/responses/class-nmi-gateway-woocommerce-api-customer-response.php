<?php
/**
 * WooCommerce NMI_Gateway_Woocommerce Gateway
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
 *
 * @package   NMI_Gateway_Woocommerce/Gateway/API/Responses/Customer
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Customer Response Class
 *
 * Handles parsing customer responses
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_API_Customer_Response extends NMI_Gateway_Woocommerce_API_Vault_Response implements NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response, NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Get_Tokenized_Payment_Methods_Response, NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Customer_Response {

	/**
	 * Override the default constructor to set created payment method since
	 * NMI_Gateway_Woocommerce simply provides a list of payment methods instead of an object
	 * containing the one just created ಠ_ಠ
	 *
	 * @param $response
	 *
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 *
	 * NMI_Gateway_Woocommerce_API_Customer_Response constructor.
	 *
	 */
	public function __construct( $response ) {

		parent::__construct( $response );
		// set created payment method when creating customer
		$this->response = $response;
		if ( $this->transaction_approved() ) {
			$this->add_payment_method();
		}

	}


	/**
	 * Get the transaction ID, which is typically only present for create customer
	 * requests when verifying the associated credit card. PayPal
	 * requests (successful or unsuccessful) do not return a transaction ID
	 *
	 * @since 1.0.0
	 */
	public function get_transaction_id() {
		return $this->response->transactionid;
	}


	/**
	 * Get the single payment token from creating a new customer with a payment method
	 *
	 * @return NMI_Gateway_Woocommerce_Payment_Method|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Payment_Token
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 */
	public function get_payment_token() {
		return new NMI_Gateway_Woocommerce_Payment_Method( $this->response->customer_vault_id, $this->get_payment_token_data( $this->response->customer_vault_id ) );
	}

	public function get_customer_vault_id() {
		return isset( $this->response->customer_vault_id ) ? $this->response->customer_vault_id : '';
	}


	/**
	 * Get the payment tokens for the customer
	 *
	 *
	 * @return array associative array of token => NMI_Gateway_Woocommerce_Payment_Method objects
	 * @since 1.0.0
	 */
	public function get_payment_tokens() {

		$args = array(
			'token_id'   => '',
			'user_id'    => $this->get_customer_id(),
			'gateway_id' => NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID,
			'type'       => '',
		);

		$tokens = WC_Payment_Tokens::get_tokens( $args );

		return $tokens;
	}

	/**
	 * Return the customer ID for the request
	 *
	 * @return string|null
	 * @since 1.0.0
	 */
	public function get_customer_id() {

		return ! empty( $this->response->orderid ) ? wc_get_order( $this->response->orderid )->get_customer_id() : get_current_user_id();
	}

	/**
	 * Helper to return the payment method created along with the customer,
	 * @return \NMI_Gateway_Woocommerce_Credit_Card
	 * @since 1.0.0
	 */
	protected function get_created_payment_method() {
		return isset( $this->response->customer_vault_id ) ? $this->response->customer_vault_id : null;
	}

	/**
	 * Adding a token for created customer vault it
	 */
	public function add_payment_method() {
		$order_id        = isset( $this->response->orderid ) ? $this->response->orderid : '';
		$card_no         = isset( $this->response->order->account_number ) ? $this->response->order->account_number : '';
		$nmi_customer_id = isset( $this->response->customer_vault_id ) ? $this->response->customer_vault_id : '';
		$transaction_id  = isset( $this->response->transactionid ) ? $this->response->transactionid : '';
		$order           = $this->get_order();

		if ( ! empty( $nmi_customer_id ) && ( 'Customer Added' === $this->response->responsetext || empty( NMI_Gateway_Woocommerce_Framework\SV_WC_Data_Compatibility::get_meta( $this->get_order(), '_wc_' . NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID . '_payment_token', true ) ) ) ) {
			$this->update_order_meta( 'payment_token', $nmi_customer_id );
			$this->update_order_meta( 'customer_id', $nmi_customer_id );
			$this->update_order_meta( 'transaction_id', $transaction_id );
			NMI_Gateway_Woocommerce_Logger::log( "Updated new card: $nmi_customer_id in order meta( order id: $order_id). " );
		}

		$save_token = ( isset( $this->response ) && isset( $this->response->order ) && isset( $this->response->order->tokenize ) && $this->response->order->tokenize );

		NMI_Gateway_Woocommerce_Logger::log( "Updating new card $nmi_customer_id for order id: $order_id, customer id: {$this->get_customer_id()}, Save token: $save_token" );

		if ( ! empty( $nmi_customer_id ) && $save_token && $this->get_customer_id() > 0 ) {
			$request_data            = array();
			$request_data['payment'] = array(
				'id'       => $nmi_customer_id,
				'last4'    => substr( $card_no, - 4 ),
				'expmonth' => isset( $this->response->order->exp_month ) ? $this->response->order->exp_month : '',
				'expyear'  => isset( $this->response->order->exp_year ) ? $this->response->order->exp_year : '',
				'brand'    => NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Helper::card_type_from_account_number( $card_no ),
				'user_id'  => wc_get_order( $order_id )->get_customer_id(),
				'mode'     => isset( $this->response->order->mode ) ? $this->response->order->mode : '',
			);

			$card  = (object) $request_data['payment'];
			$token = new WC_Payment_Token_CC();

			$token->set_token( $card->id );
			$token->set_gateway_id( NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );
			$token->set_card_type( strtolower( $card->brand ) );
			$token->set_last4( $card->last4 );
			$token->set_expiry_month( $card->expmonth );
			$token->set_expiry_year( '20' . $card->expyear );
			$token->set_user_id( $card->user_id );
			$token->add_meta_data( 'mode', $card->mode, true );
			$token->save();
			NMI_Gateway_Woocommerce_Logger::log( "Updated new token $nmi_customer_id in token table for order id: $order_id and token id: {$token->get_id()}, token user id: {$token->get_user_id()}" );
		}
	}
}
