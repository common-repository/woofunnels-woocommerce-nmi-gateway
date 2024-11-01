<?php

/**
 * Disable REST API
 * https://wordpress.org/plugins/disable-json-api/
 */

if ( ! class_exists( 'BWF_Compatibility_With_Disable_Rest_API' ) ) {
	class BWF_Compatibility_With_Disable_Rest_API {

		public function __construct() {
			$saved_data = get_option( 'disable_rest_api_options' );
			if ( empty( $saved_data ) ) {
				return;
			}

			add_filter( 'pre_option_disable_rest_api_options', function ( $value_return ) use ( $saved_data ) {
				if ( ! is_array( $saved_data ) || ! isset( $saved_data['roles']['none']['allow_list'] ) ) {
					return $value_return;
				}

				$allowed_list = $saved_data['roles']['none']['allow_list'];

				/** FunnelKit public json endpoints */
				if ( isset( $allowed_list['/woofunnels/v1'] ) ) {
					$allowed_list['/woofunnels/v1']        = true;
					$allowed_list['/woofunnels/v1/worker'] = true;
				}

				if ( isset( $allowed_list['/autonami/v1'] ) ) {
					$allowed_list['/autonami/v1']                                                    = true;
					$allowed_list['/autonami/v1/events']                                             = true;
					$allowed_list['/autonami/v1/worker']                                             = true;
					$allowed_list['/autonami/v1/autonami-cron']                                      = true;
					$allowed_list['/autonami/v1/delete-tasks']                                       = true;
					$allowed_list['/autonami/v1/update-contact-automation']                          = true;
					$allowed_list['/autonami/v1/update-generated-increment']                         = true;
					$allowed_list['/autonami/v1/wc-add-to-cart']                                     = true;
					$allowed_list['/autonami/v1/hubspot/webhook(?:/(?P<hubspot_id>\d+))?']           = true;
					$allowed_list['/autonami/v1/keap/webhook(?:/(?P<bwfan_keap_id>\d+))?']           = true;
					$allowed_list['/autonami/v1/mailchimp/webhook(?:/(?P<mailchimp_id>\d+))?']       = true;
					$allowed_list['/autonami/v1/mautic/webhook(?:/(?P<bwfan_mautic_id>\d+))?']       = true;
					$allowed_list['/autonami/v1/ontraport/webhook(?:/(?P<bwfan_ontraport_id>\d+))?'] = true;
					$allowed_list['/autonami/v1/twilio/webhook(?:/(?P<twilio_id>\d+))?']             = true;
					$allowed_list['/autonami-webhook/sms/twilio/(?P<key>[a-zA-Z0-9-]+)']             = true;
					$allowed_list['/autonami/v1/webhook(?:/(?P<bwfan_autonami_webhook_id>\d+))?']    = true;
					$allowed_list['/autonami/v1/ac/webhook(?:/(?P<ac_id>\d+))?']                     = true;
					$allowed_list['/autonami/v1/drip/webhook(?:/(?P<drip_id>\d+))?']                 = true;
				}

				if ( isset( $allowed_list['/autonami/v2'] ) ) {
					$allowed_list['/autonami/v2']        = true;
					$allowed_list['/autonami/v2/worker'] = true;
				}

				if ( isset( $allowed_list['/woofunnel_customer/v1'] ) ) {
					$allowed_list['/woofunnel_customer/v1']                      = true;
					$allowed_list['/woofunnel_customer/v1/offer_accepted']       = true;
					$allowed_list['/woofunnel_customer/v1/order_status_changed'] = true;
					$allowed_list['/woofunnel_customer/v1/wp_user_login']        = true;
					$allowed_list['/woofunnel_customer/v1/wp_profile_update']    = true;
				}

				if ( isset( $allowed_list['/autonami-webhook'] ) ) {
					$allowed_list['/autonami-webhook']                                                       = true;
					$allowed_list['/autonami-webhook/emails/(?P<slug>[a-zA-Z0-9-]+)/(?P<key>[a-zA-Z0-9-]+)'] = true;
				}

				$saved_data['roles']['none']['allow_list'] = $allowed_list;

				return $saved_data;
			}, PHP_INT_MAX );
		}

		public function is_enable() {
			return class_exists( 'Disable_REST_API' );
		}

	}

	/** Checking Disable rest api plugin is activated */
	if ( class_exists( 'Disable_REST_API' ) ) {
		BWF_Plugin_Compatibilities::register( new BWF_Compatibility_With_Disable_Rest_API(), 'disable_rest_api' );
	}
}