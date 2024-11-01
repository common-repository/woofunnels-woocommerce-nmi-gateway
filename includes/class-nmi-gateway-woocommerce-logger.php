<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NMI logging class which saves important data to the log
 *
 * @since 1.0.0
 */
class NMI_Gateway_Woocommerce_Logger {

	public static $logger;

	/**
	 * Adding log
	 *
	 * @param $message
	 * @param bool $debug
	 * @param string $level
	 */
	public static function log( $message, $debug = false, $level = 'info' ) {
		if ( empty( self::$logger ) ) {
			self::$logger = new WC_Logger();
		}

		$all_gateways     = WC()->payment_gateways()->payment_gateways();
		$nmi_gateway_base = isset( $all_gateways[ NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID ] ) ? $all_gateways[ NMI_Gateway_Woocommerce::CREDIT_CARD_GATEWAY_ID ] : [];
		if ( $nmi_gateway_base instanceof NMI_Gateway_Woocommerce_Credit_Card ) {
			$debug = empty( $debug ) ? $nmi_gateway_base->debug_log() : $debug;
		}
		if ( $debug && self::$logger instanceof WC_Logger && did_action( 'plugins_loaded' ) ) {
			$get_user_ip     = WC_Geolocation::get_ip_address();
			$message_with_ip = $get_user_ip . ' ' . $message;

			self::$logger->log( $level, $message_with_ip, array( 'source' => 'xl-nmi-cc-' . self::get_postfix() ) );
		}
	}

	public static function get_postfix() {
		$get_time = new WC_DateTime();
		$get_hour = absint( $get_time->date( 'H' ) );
		$postfix  = strtotime( gmdate( 'd F Y 00:00:00' ) );
		if ( $get_hour > 12 ) {
			$postfix = strtotime( gmdate( 'd F Y 12:00:00' ) );
		}

		return $postfix;
	}
}

new NMI_Gateway_Woocommerce_Logger();
