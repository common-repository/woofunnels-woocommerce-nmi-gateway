<?php

/**
 * Class NMI_Gateway_Woocommerce_Remote_Request
 */

class NMI_Gateway_Woocommerce_Remote_Request {

	/** @var \http\Url */
	protected $request_uri;

	/** @var array request args */
	protected $request_data;

	/** @var string request user-agent */
	protected $request_user_agent;

	/** @var string request HTTP version, defaults to 1.0 */
	protected $request_http_version = '1.0';

	/** @var object response */
	public $response;

	/** @var string nmi user name */
	protected $username;

	/** @var string nmi user password */
	protected $password;

	/** @var string nmi security key */
	protected $security_key;

	/**
	 * NMI_Gateway_Woocommerce_Remote_Request constructor.
	 *
	 * @param $username
	 * @param $password
	 */
	public function __construct( $username, $password, $security_key ) {
		$this->username     = $username;
		$this->password     = $password;
		$this->security_key = $security_key;
		$this->request_uri  = 'https://secure.nmi.com/api/transact.php';
		if ( empty( $this->security_key ) ) {
			$this->request_uri = 'https://secure.networkmerchants.com/api/transact.php';
		}
		$this->request_data = $this->get_request_args();
	}

	/**
	 * @param $args
	 *
	 * @return NMI_Gateway_Woocommerce_Remote_Response
	 */
	public function do_wp_remote_request( $args ) {

		if ( empty( $this->security_key ) ) {
			$args['username'] = $this->username;
			$args['password'] = $this->password;
		} else {
			$args['security_key'] = $this->security_key;
		}

		$query = http_build_query( $args );

		$this->request_uri = $this->request_uri . '?' . $query;

		$this->response = wp_remote_get( $this->request_uri, $this->request_data );

		//NMI_Gateway_Woocommerce_Logger::log( "Complete request data for NMI: " . print_r( $this->request_data, true ) );
		NMI_Gateway_Woocommerce_Logger::log( "Complete request URI for NMI: " . print_r( $this->request_uri, true ) );

		$response_body = wp_remote_retrieve_body( $this->response );

		if ( ! is_wp_error( $this->response ) ) {
			NMI_Gateway_Woocommerce_Logger::log( "Complete response body from NMI: " . print_r( $response_body, true ) );
		} else {
			NMI_Gateway_Woocommerce_Logger::log( "Complete Error response from NMI: " . print_r( $this->response, true ) );
		}
		$results = new NMI_Gateway_Woocommerce_Remote_Response( $this->response );

		return $results;
	}

	/**
	 * Get the request arguments in the format required by wp_remote_request()
	 *
	 * @return mixed|void
	 * @since 2.2.0
	 */
	protected function get_request_args() {

		$args = array(
			'method'      => 'GET',
			'timeout'     => MINUTE_IN_SECONDS,
			'redirection' => 0,
			'httpversion' => $this->get_request_http_version(),
			'sslverify'   => true,
			'blocking'    => true,
			'user-agent'  => $this->get_request_user_agent(),
			'cookies'     => array(),
		);

		/**
		 * Request arguments.
		 *
		 * Allow other actors to filter the request arguments. Note that
		 * child classes can override this method, which means this filter may
		 * not be invoked, or may be invoked prior to the overridden method
		 *
		 * @param array $args request arguments
		 * @param \SV_WC_API_Base class instance
		 *
		 * @since 2.2.0
		 *
		 */
		return apply_filters( 'wc_' . NMI_Gateway_Woocommerce::PLUGIN_ID . '_http_request_args', $args );
	}

	/**
	 * Get the request HTTP version, 1.1 by default
	 *
	 * @return string
	 * @since 2.2.0
	 */
	protected function get_request_http_version() {

		return $this->request_http_version;
	}

	/**
	 * Get the request user agent, defaults to:
	 *
	 * Dasherized-Plugin-Name/Plugin-Version (WooCommerce/WC-Version; WordPress/WP-Version)
	 *
	 *
	 * @return string
	 * @since 2.2.0
	 */
	protected function get_request_user_agent() {

		return sprintf( '%s/%s (WooCommerce/%s; WordPress/%s)', str_replace( ' ', '-', NMI_GATEWAY_WOOCOMMERCE_FULL_NAME ), NMI_GATEWAY_WOOCOMMERCE_VERSION, WC_VERSION, $GLOBALS['wp_version'] );
	}

}