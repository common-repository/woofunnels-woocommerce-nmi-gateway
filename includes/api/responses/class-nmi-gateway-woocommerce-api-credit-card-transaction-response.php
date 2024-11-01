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
 * @package   NMI_Gateway_Woocommerce/Gateway/API/Responses/Credit-Card-Transaction
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;


/**
 * NMI_Gateway_Woocommerce API Credit Card Transaction Response Class
 *
 * Handles parsing credit card transaction responses
 *
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_API_Credit_Card_Transaction_Response extends NMI_Gateway_Woocommerce_API_Transaction_Response {
	/**
	 * Get the credit card payment token created during this transaction
	 *
	 * @since 1.0.0
	 * @return \NMI_Gateway_Woocommerce_Payment_Method
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception if token is missing
	 */
	public function get_payment_token() {

		if ( empty( $this->response->customer_vault_id ) ) {
			throw new NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception( __( 'Required credit card token is missing or empty!', 'woofunnels-woocommerce-nmi-gateway' ) );
		}

		return $this->response->customer_vault_id;

	}

	/**
	 * Check if transaction is approved
	 * @return bool
	 */
	public function transaction_approved() {
		return $this->response->approved;
	}
}
