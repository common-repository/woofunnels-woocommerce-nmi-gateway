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
 * @package   NMI_Gateway_Woocommerce/Gateway/API/Responses/Payment-Method
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Payment Method Response Class
 *
 * Handles parsing payment method responses
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_API_Payment_Method_Response extends NMI_Gateway_Woocommerce_API_Vault_Response implements NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response {
	/**
	 * Get the transaction ID, which is typically only present for create customer/
	 * payment method requests when verifying the associated credit card. PayPal
	 * requests (successful or unsuccessful) do not return a transaction ID
	 *
	 * @since 1.0.0
	 */
	public function get_transaction_id() {
		return $this->is_credit_card_response() && isset( $this->response->transactionid ) ? $this->response->transactionid : null;
	}

	/**
	 * Get the single payment token from a NMI create payment method call
	 * @return NMI_Gateway_Woocommerce_Payment_Method|NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Payment_Token
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 */
	public function get_payment_token() {
		if ( isset( $this->response->customer_vault_id ) && ! empty( $this->response->customer_vault_id ) ) {
			$token_id = $this->set_payment_token();
			NMI_Gateway_Woocommerce_Logger::log( "Token id saved in DB: $token_id" );

			$token_data = $this->get_payment_token_data( $token_id );
			if ( $token_data instanceof WC_Payment_Token_CC ) {
				NMI_Gateway_Woocommerce_Logger::log( "Token id found in token table is: {$token_data->get_id()} " );
			}
			$token_data = is_array( $token_data ) ? $token_data : [];

			return new NMI_Gateway_Woocommerce_Payment_Method( $token_id, $token_data );
		}
		throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( __( 'Required credit card token is missing or empty!', 'woofunnels-woocommerce-nmi-gateway' ) );
	}

	/**
	 * Return true if the verification for this payment method has an AVS rejection from the gateway.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function has_avs_rejection() {
		return isset( $this->response->avsresponse ) && 'M' === $this->response->avsresponse->gatewayRejectionReason;
	}

	/**
	 * Return true if the verification for this payment method has an CVV rejection from the gateway.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function has_cvv_rejection() {
		return isset( $this->response->cvvresponse ) && 'M' !== $this->response->cvvresponse;
	}

	/**
	 * Adding a new card to an existing customer after validation
	 * @return int
	 */
	public function set_payment_token() {
		$request_data    = array();
		$order           = isset( $this->response->order ) ? $this->response->order : new stdClass();
		$card_no         = isset( $order->account_number ) ? $order->account_number : ( isset( $this->response->cc_number ) ? $this->response->cc_number : '' );
		$nmi_customer_id = isset( $this->response->customer_vault_id ) ? $this->response->customer_vault_id : '';
		$mode            = isset( $order->mode ) ? $order->mode : 'production';

		$request_data['payment'] = array(
			'id'       => $nmi_customer_id,
			'last4'    => substr( $card_no, - 4 ),
			'expmonth' => isset( $order->exp_month ) ? $order->exp_month : '',
			'expyear'  => isset( $order->exp_year ) ? $order->exp_year : '',
			'brand'    => NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Helper::card_type_from_account_number( $card_no ),
			'user_id'  => get_current_user_id()
		);

		NMI_Gateway_Woocommerce_Logger::log( "Request data for adding a new token for: $nmi_customer_id is: " . print_r( $request_data, true ) );

		$card  = (object) $request_data['payment'];
		$token = new WC_Payment_Token_CC();
		$token->set_token( $card->id );
		$token->set_gateway_id( NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );
		$token->set_card_type( strtolower( $card->brand ) );
		$token->set_last4( $card->last4 );
		$token->set_expiry_month( $card->expmonth );
		$token->set_expiry_year( '20' . $card->expyear );
		$token->set_user_id( $card->user_id );
		$token->add_meta_data( 'mode', $mode, true );
		$token->save();

		$token_id = $token->get_id();

		NMI_Gateway_Woocommerce_Logger::log( "A new token has been saved in woocommerce token table with token id $token_id, Customer vault id: $nmi_customer_id" );

		return $token_id;
	}
}
