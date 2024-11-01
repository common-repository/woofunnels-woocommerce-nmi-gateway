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
 *
 * @package   NMI_Gateway_Woocommerce/Gateway/Payment-Method
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce Payment Method Class
 *
 * Extends the framework Payment Token class to provide NMI_Gateway_Woocommerce-specific
 * functionality like billing addresses
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_Payment_Method extends NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Payment_Token {

	/** credit card payment method type */
	const CREDIT_CARD_TYPE = 'credit_card';

	/**
	 * Bootstrap the payment method
	 *
	 * @param string $id token ID
	 * @param array $data token data
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct( $id, array $data ) {
		parent::__construct( $id, $data );
	}

	/**
	 * Get the billing address ID associated with this credit card
	 *
	 * @return string|null
	 * @since 1.0.0
	 */
	public function get_billing_address_id() {

		return ! empty( $this->data['billing_address_id'] ) ? $this->data['billing_address_id'] : null;
	}
}
