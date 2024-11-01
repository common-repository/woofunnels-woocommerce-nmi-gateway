<?php

/**
 * Class NMI_Gateway_Response
 */

class NMI_Gateway_Woocommerce_Remote_Response {
	public $response;
	public $approved;
	public $declined;
	public $error;
	public $error_code;
	public $responsetext;
	public $authcode;
	public $transactionid;
	public $avsresponse;
	public $cvvresponse;
	public $orderid;
	public $type;
	public $response_code;
	public $customer_vault_id;

	/**
	 * NMI_Gateway_Woocommerce_Remote_Response constructor.
	 *
	 * @param $response_data
	 */
	public function __construct( $response_obj ) {
		$res_arr = array();

		$response_body = wp_remote_retrieve_body( $response_obj );
		if ( ! is_wp_error( $response_body ) ) {
			$response_data = $response_body;
		} else {
			$response_data       = '';
			$this->approved      = false;
			$this->response      = 3;
			$this->response_code = 3004; //Timeout error
		}

		if ( ! empty( $response_data ) ) {
			$results = explode( '&', $response_data );
			if ( count( $results ) > 7 ) {  //When NMI return complete response
				foreach ( $results as $key => $result ) {
					$res                = explode( '=', $result );
					$res_arr[ $res[0] ] = $res[1];
				}
			} else {
				$this->approved = false;
				$this->error    = true;

				return;
			}
		} else {
			$this->approved = false;
			$this->error    = true;
		}

		if ( count( $res_arr ) > 0 ) {
			foreach ( $res_arr as $key => $res_val ) {
				$this->$key = $res_val;
			}
		}

		$this->approved = ( intval( $this->response ) === 1 );
		$this->declined = ( intval( $this->response ) === 2 );
		$this->error    = ( intval( $this->response ) === 3 );

		if ( $this->declined ) {
			$this->error = true; //Card declined
		}
	}
}
