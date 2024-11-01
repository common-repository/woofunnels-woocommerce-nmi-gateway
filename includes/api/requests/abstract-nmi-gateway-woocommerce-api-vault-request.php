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
 * @package   NMI_Gateway_Woocommerce/Gateway/API/Requests/Vault
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Abstract Vault Request class
 *
 * Handles common methods for vault requests - Customers/Payment Methods
 *
 * @since 1.0.0
 */
abstract class NMI_Gateway_Woocommerce_API_Vault_Request extends NMI_Gateway_Woocommerce_API_Request {
	/**
	 * Return the billing address in the format required by NMI
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function get_billing_address() {
		return array(
			'firstName'         => $this->get_order_prop( 'billing_first_name' ),
			'lastName'          => $this->get_order_prop( 'billing_last_name' ),
			'company'           => $this->get_order_prop( 'billing_company' ),
			'streetAddress'     => $this->get_order_prop( 'billing_address_1' ),
			'extendedAddress'   => $this->get_order_prop( 'billing_address_2' ),
			'locality'          => $this->get_order_prop( 'billing_city' ),
			'region'            => $this->get_order_prop( 'billing_state' ),
			'postalCode'        => $this->get_order_prop( 'billing_postcode' ),
			'countryCodeAlpha2' => $this->get_order_prop( 'billing_country' ),
		);
	}

	/**
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	protected function set_payment_method() {
		parent::set_payment_method();
		$this->request_data['payment']['customer_vault'] = 'add_customer';
	}
}
