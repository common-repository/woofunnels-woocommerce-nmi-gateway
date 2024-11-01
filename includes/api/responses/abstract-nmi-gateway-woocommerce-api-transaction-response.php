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
 * @package   NMI_Gateway_Woocommerce/Gateway/API/Responses/Transaction
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Abstract Transaction Response Class
 *
 * Provides common functionality to Credit Card & PayPal transaction response classes
 *
 *
 * @since 1.0.0
 */
abstract class NMI_Gateway_Woocommerce_API_Transaction_Response extends NMI_Gateway_Woocommerce_API_Response {
	/** NMI_Gateway_Woocommerce's CSC match value */
	const CSC_MATCH = 'M';

	/**
	 * Gets the response transaction ID
	 *
	 * @since 1.0.0
	 * @see SV_WC_Payment_Gateway_API_Response::get_transaction_id()
	 * @return string transaction id
	 */
	public function get_transaction_id() {

		return ! empty( $this->response->transactionid ) ? $this->response->transactionid : null;
	}

	/**
	 * Return the customer ID for the request
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	public function get_customer_id() {

		return ! empty( $this->response->orderid ) ? wc_get_order( $this->response->orderid )->get_customer_id() : get_current_user_id();
	}

	public function transaction_approved() {
		return $this->response->approved;
	}
}
