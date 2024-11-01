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
 * @package   NMI-Gateway-Woocommerce/Gateway/API/Request
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

/**
 * NMI_Gateway_Woocommerce API Abstract Request Class
 *
 * Provides functionality common to all requests
 *
 * @since 1.0.0
 */
abstract class NMI_Gateway_Woocommerce_API_Request implements NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Request {

	/** @var string NMI_Gateway_Woocommerce SDK resource for the request, e.g. `transaction` */
	protected $resource;

	/** @var string NMI_Gateway_Woocommerce SDK callback for the request, e.g. `generate` */
	protected $callback;

	/** @var array request data passed to the static callback */
	protected $request_data = array();

	/** @var \WC_Order order associated with the request, if any */
	protected $order;

	/**
	 * Setup request
	 *
	 * @param \WC_Order|null $order order if available
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct( $order = null ) {
		$this->order = $order;
	}

	/**
	 * Sets the NMI SDK resource for the request.
	 *
	 * @param string $resource , e.g. `transaction`
	 *
	 * @since 1.0.0
	 *
	 */
	protected function set_resource( $resource ) {
		$this->resource = $resource;
	}

	/**
	 * Gets the NMI resource for the request.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_resource() {
		return $this->resource;
	}


	/**
	 * Set the static callback for the request
	 *
	 * @param string $callback , e.g. `NMI_ClientToken::generate`
	 *
	 * @since 1.0.0
	 *
	 */
	protected function set_callback( $callback ) {
		$this->callback = $callback;
	}

	/**
	 * Get the static callback for the request
	 *
	 * @return string static callback
	 * @since 1.0.0
	 */
	public function get_callback() {
		return $this->callback;
	}

	/**
	 * Get the callback parameters for the request
	 * @return array
	 * @since 1.0.0
	 */
	public function get_callback_params() {
		return $this->get_request_data();
	}

	/**
	 * Return the string representation of the request
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function to_string() {
		return print_r( $this->get_request_data(), true ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}


	/**
	 * Return the string representation of the request, stripped of any
	 * confidential information
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function to_string_safe() {
		// no confidential info to mask...yet
		return $this->to_string();
	}


	/**
	 * Get the request data which is the 1st parameter passed to the static callback set
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_request_data() {
		/**
		 * NMI API Request Data.
		 *
		 * Allow actors to modify the request data before it's sent to NMI
		 *
		 * @param array|mixed $data request data to be filtered
		 * @param \WC_Order $order order instance
		 * @param \NMI_Gateway_Woocommerce_API_Request $this , API request class instance
		 *
		 * @since 1.0.0
		 *
		 */
		$this->request_data = apply_filters( NMI_Gateway_Woocommerce::PLUGIN_ID . '_request_data', $this->request_data, $this );

		$this->remove_empty_data();

		return $this->request_data;
	}


	/**
	 * Remove null or blank string values from the request data (up to 2 levels deep)
	 *
	 * @since 1.0.0
	 */
	protected function remove_empty_data() {

		foreach ( (array) $this->request_data as $key => $value ) {

			if ( is_array( $value ) ) {

				if ( empty( $value ) ) {

					unset( $this->request_data[ $key ] );

				} else {

					foreach ( $value as $inner_key => $inner_value ) {

						if ( is_null( $inner_value ) || '' === $inner_value ) {
							unset( $this->request_data[ $key ][ $inner_key ] );
						}
					}
				}

			} else {

				if ( is_null( $value ) || '' === $value ) {
					unset( $this->request_data[ $key ] );
				}
			}
		}
	}


	/**
	 * Gets a property from the associated order from database
	 *
	 * @param string $prop the desired order property
	 *
	 * @return mixed
	 * @since 1.0.0
	 *
	 */
	public function get_order_prop( $prop ) {
		return NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $this->get_order(), $prop );
	}

	/**
	 * Get the order associated with the request, if any
	 *
	 * @return \WC_Order|null
	 * @since 1.0.0
	 */
	public function get_order() {
		return $this->order;
	}


	/**
	 * NMI_Gateway_Woocommerce requests do not require a method per request
	 * @return string|void
	 * @since 1.0.0
	 */
	public function get_method() {
	}


	/**
	 * NMI_Gateway_Woocommerce requests do not require a path per request
	 * @return string|void
	 * @since 1.0.0
	 */
	public function get_path() {
	}

	/**
	 * @return array|void
	 */
	public function get_params() {
		// TODO: Implement get_params() method.
	}

	/**
	 * @return array|void
	 */
	public function get_data() {
		// TODO: Implement get_data() method.
	}

	/**
	 * Set the customer data for the transaction
	 *
	 * @since 1.0.0
	 */
	protected function set_customer() {
		// a customer will only be created if tokenization is required
		$this->request_data['customer'] = array(
			'phone' => $this->get_order_prop( 'billing_phone' ),
			'email' => $this->get_order_prop( 'billing_email' ),
		);
	}

	/**
	 * Get the billing address for the transaction
	 * @since 1.0.0
	 */
	protected function set_billing() {
		$order     = $this->order;
		$firstName = $lastName = '';
		$company   = $address1 = $address2 = $city = $state = $country = $zip = 'NA';
		if ( $order instanceof WC_Order ) {
			$firstName = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_first_name' );
			$lastName  = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_last_name' );
			$company   = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_company' );
			$address1  = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_address_1' );
			$address2  = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_address_2' );
			$city      = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_city' );
			$state     = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_state' );
			$country   = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_country' );
			$zip       = NMI_Gateway_Woocommerce_Framework\SV_WC_Order_Compatibility::get_prop( $order, 'billing_postcode' );
		} else if ( get_current_user_id() > 0 ) {
			$user_id   = get_current_user_id();
			$user_meta = get_user_meta( $user_id );

			$firstName = $this->get_first_name( $user_meta, $user_id );
			$lastName  = $this->get_last_name( $user_meta, $user_id );
			$address1  = $this->get_user_detail( $user_meta, 'billing_address_1' );
			$address2  = $this->get_user_detail( $user_meta, 'billing_address_2' );
			$company   = $this->get_user_detail( $user_meta, 'billing_company' );
			$city      = $this->get_user_detail( $user_meta, 'billing_city' );
			$state     = $this->get_user_detail( $user_meta, 'billing_state' );
			$zip       = $this->get_user_detail( $user_meta, 'billing_postcode' );
			$country   = $this->get_user_detail( $user_meta, 'billing_country' );
		}

		$this->request_data['billing'] = array(
			'firstName' => $firstName,
			'lastName'  => $lastName,
			'address1'  => $address1,
			'address2'  => $address2,
			'company'   => $company,
			'city'      => $city,
			'state'     => $state,
			'zip'       => $zip,
			'country'   => $country
		);
	}

	/**
	 * Finding billing first name
	 *
	 * @param $user_meta
	 * @param $user_id
	 *
	 * @return mixed|string
	 */
	public function get_first_name( $user_meta, $user_id ) {
		$f_name = '';
		if ( isset( $user_meta['billing_first_name'] ) && is_array( $user_meta['billing_first_name'] ) ) {
			$f_name = $user_meta['billing_first_name']['0'];
		} elseif ( isset( $user_meta['shipping_first_name'] ) && is_array( $user_meta['shipping_first_name'] ) ) {
			$f_name = $user_meta['shipping_first_name']['0'];
		} elseif ( isset( $user_meta['first_name'] ) && is_array( $user_meta['first_name'] ) ) {
			$f_name = $user_meta['first_name']['0'];
		} else {
			$f_name = get_user_by( 'id', $user_id )->user_login;
		}

		return $f_name;
	}

	/**
	 * Finding billing last name
	 *
	 * @param $user_meta
	 * @param $user_id
	 *
	 * @return string
	 */
	public function get_last_name( $user_meta, $user_id ) {
		$l_name = '';
		if ( isset( $user_meta['billing_last_name'] ) && is_array( $user_meta['billing_last_name'] ) ) {
			$l_name = $user_meta['billing_last_name']['0'];
		} elseif ( isset( $user_meta['shipping_last_name'] ) && is_array( $user_meta['shipping_last_name'] ) ) {
			$l_name = $user_meta['shipping_last_name']['0'];
		} elseif ( isset( $user_meta['last_name'] ) && is_array( $user_meta['last_name'] ) ) {
			$l_name = $user_meta['last_name']['0'];
		} else {
			$l_name = get_user_by( 'id', $user_id )->user_login;
		}

		return $l_name;

	}

	/**
	 * @param $user_meta
	 * @param $key
	 *
	 * @return mixed|string
	 */
	public function get_user_detail( $user_meta, $key ) {
		if ( isset( $user_meta[ $key ] ) && is_array( $user_meta[ $key ] ) && ! empty( $usermeta[ $key ]['0'] ) ) {
			return $user_meta[ $key ]['0'];
		}
		$key = str_replace( 'billing_', 'shipping_', $key );
		if ( isset( $user_meta[ $key ] ) && is_array( $user_meta[ $key ] ) && ! empty( $user_meta[ $key ]['0'] ) ) {
			return $user_meta[ $key ]['0'];
		}

		return '';
	}

	/**
	 * Set the payment method for the transaction, either a previously saved payment method (token) or a new payment method
	 * @throws NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_Exception
	 */
	protected function set_payment_method() {
		$wc_pre_30                                 = version_compare( WC_VERSION, '3.0.0', '<' );
		$this->request_data                        = is_array( $this->request_data ) ? $this->request_data : [];
		$this->request_data['payment']             = ( isset( $this->request_data['payment'] ) && is_array( $this->request_data['payment'] ) ) ? $this->request_data['payment'] : [];
		$this->request_data['payment']['currency'] = $wc_pre_30 ? $this->get_order_prop( 'order_currency' ) : $this->get_order_prop( 'currency' );


	}

	public function get_id_dasherized() {
		return str_replace( '_', '-', NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID );
	}
}
