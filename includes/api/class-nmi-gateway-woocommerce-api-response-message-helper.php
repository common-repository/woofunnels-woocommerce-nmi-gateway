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
 * DISCLAIMER
 *
 * @package   woofunnels-woocommerce-nmi-gateway/Response-Message-Helper
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use SkyVerge\WooCommerce\PluginFramework\v5_2_1 as NMI_Gateway_Woocommerce_Framework;

defined( 'ABSPATH' ) or exit;

/**
 * NMI_Gateway_Woocommerce API Response Message Helper
 *
 * Builds customer-friendly response messages by mapping the various NMI
 * error codes to standardized messages
 *
 * @since 1.0.0
 * @see SV_WC_Payment_Gateway_API_Response_Message_Helper
 */
class NMI_Gateway_Woocommerce_API_Response_Message_Helper extends NMI_Gateway_Woocommerce_Framework\SV_WC_Payment_Gateway_API_Response_Message_Helper {


	/** @var \NMI_Gateway_Woocommerce_API_Response response */
	protected $response;

	/** @var array decline codes */
	protected $decline_codes = array(
		'200' => 'decline',                     //Transaction was declined by processor.//
		'201' => 'dont-honor',                  // Do not honor.//
		'202' => 'insufficient_funds',          //Insufficient funds.
		'203' => 'credit_limit_reached',        //Over limit.
		'204' => 'not_allowed',                 //Transaction not allowed.//
		'220' => 'incorrect_info',              //Incorrect payment information.//
		'221' => 'card_number_type_invalid',    //No such card issuer.
		'222' => 'card_number_invalid',         //No card number on file with issuer.
		'223' => 'card_expired',                //Expired card.
		'224' => 'card_expiry_invalid',         //Invalid expiration date.
		'225' => 'csc_mismatch',                //Invalid card security code.
		'226' => 'csc_invalid',                 //Invalid PIN.
		'240' => 'call_issuer',                 //Call issuer for further information.//
		'250' => 'pickup_card',                 //Pick up card.//
		'251' => 'lost_card',                   //Lost card.//
		'252' => 'stolen',                      //Stolen card.//
		'253' => 'fraud',                       //Fraudulent card.//
		'260' => 'decline_with_instruction',    //Declined with further instructions available. (See response text)//
		'261' => 'decline_recurring',           //Declined-Stop all recurring payments.//
		'262' => 'decline_program',             //Declined-Stop this recurring program.//
		'263' => 'decline_update',              //Declined-Update cardholder data available.//
		'264' => 'decline_retry',               //Declined-Retry in a few days.//
		'300' => 'rejected_gateway',            //Transaction was rejected by gateway.//
		'400' => 'error_processor',             //Transaction error returned by processor.//
		'410' => 'invalid-merchant',            //Invalid merchant configuration.//
		'411' => 'inactive_account',            //Merchant account is inactive.//
		'420' => 'communication-error',         //Communication error.//
		'421' => 'communication-issuer',        //Communication error with issuer.//
		'430' => 'duplicate',                   //Duplicate transaction at processor.//
		'440' => 'format_error',                //Processor format error.//
		'441' => 'invalid_info',                //Invalid transaction information.//
		'460' => 'feature_unavailable',         //Processor feature not available.//
		'461' => 'unsupported',                 //Unsupported card type.//
	);

	/**
	 * Initialize the API response message handler
	 *
	 * @param \NMI_Gateway_Woocommerce_API_Response $response
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct( $response ) {

		$this->response = $response;
	}

	/**
	 * Get the user-facing error/decline message. Used in place of the get_user_message()
	 * method because this class is instantiated with the response class and handles
	 * generating the message ID internally
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_message() {

		// note that $this->get_response()->response-text contains a NMI-provided humanized error message, but it's generally
		// not appropriate for display to customers so it's not used here

		$response_code = $this->get_response()->get_failure_status_info();

		$message_id = isset( $this->decline_codes[ $response_code['code'] ] ) ? $this->decline_codes[ $response_code['code'] ] : 'custom-error';

		return $this->get_user_message( $message_id );
	}


	/**
	 * Returns a message appropriate for a frontend user.  This should be used
	 * to provide enough information to a user to allow them to resolve an
	 * issue on their own, but not enough to help nefarious folks fishing for
	 * info. Adds a few custom XL-NMI-specific user error messages.
	 *
	 * @param string $message_id identifies the message to return
	 *
	 * @return string a user message
	 * @since 1.0.0
	 * @see SV_WC_Payment_Gateway_API_Response_Message_Helper::get_user_message()
	 *
	 */
	public function get_user_message( $message_id ) {
		$message = '';

		if ( 'custom-error' !== $message_id ) {
			$message = parent::get_user_message( $message_id );
		}

		if ( empty( $message ) ) {
			$message = $this->get_gateway_user_message( $message_id );
		}

		/**
		 * NMI_Gateway_Woocommerce API Response User Message Filter.
		 *
		 * Allow actors to change the message displayed to customers as a result
		 * of a transaction error.
		 *
		 * @param string $message message displayed to customers
		 * @param string $message_id parsed message ID, e.g. 'decline'
		 * @param \NMI_Gateway_Woocommerce_API_Response_Message_Helper $this instance
		 *
		 * @since 1.0.0
		 *
		 */
		return apply_filters( NMI_Gateway_Woocommerce::PLUGIN_ID . '_api_response_user_message', $message, $message_id, $this );
	}


	/**
	 * Return the response object for this user message
	 *
	 * @return NMI_Gateway_Woocommerce_API_Response
	 */
	public function get_response() {

		return $this->response;
	}

	/**
	 * @param $message_id
	 *
	 * @return string
	 */
	public function get_gateway_user_message( $message_id ) {
		$response_code = $this->get_response()->get_failure_status_info();
		$message       = $response_code['message'];

		if ( 'decline' === $message_id && 'DECLINE' === $message ) {
			$message = esc_html__( 'Your card has been declined, please add any other card.', 'woofunnels-woocommerce-nmi-gateway' );
		}

		if ( '3004' === $response_code['code'] ) {
			$message = esc_html__( 'Server timeout, please try again!!.', 'woofunnels-woocommerce-nmi-gateway' );
		}

		return $message;
	}
}
