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
 * @package   woofunnels-woocommerce-nmi-gateway/includes
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Vault Response Class
 *
 * Handles common methods for parsing vault responses (customers/payment methods)
 *
 * @since 1.0.0
 */
abstract class NMI_Gateway_Woocommerce_API_Vault_Response extends NMI_Gateway_Woocommerce_API_Response {

	/**
	 * Get the payment token data from the given payment method
	 *
	 * @param $token_id
	 *
	 * @return array
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 * @since 1.0.0
	 */
	protected function get_payment_token_data( $token_id ) {
		if ( empty( $token_id ) ) {
			throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( __( 'Required credit card token is missing or empty!', 'woofunnels-woocommerce-nmi-gateway' ) );
		}

		$args = array(
			'token_id'   => '',
			'user_id'    => $this->get_customer_id(),
			'gateway_id' => NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID,
			'type'       => '',
		);

		$token = '';

		$tokens = WC_Payment_Tokens::get_tokens( $args );
		foreach ( $tokens as $token_value ) {
			if ( $token_value->get_token() === $this->response->customer_vault_id ) {
				$token = $token_value;
				break;
			}
		}
		NMI_Gateway_Woocommerce_Logger::log( "Found token for nmi customer id: {$this->response->customer_vault_id} is: with token id: {$token->get_id()}" );

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

		return __return_empty_array();

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
}
