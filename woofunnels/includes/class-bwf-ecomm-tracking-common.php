<?php
/**
 * Class BWF_Ecomm_Tracking_Common
 */
if ( ! class_exists( 'BWF_Ecomm_Tracking_Common' ) ) {
	class BWF_Ecomm_Tracking_Common {
		public $api_events = [];
		public $gtag_rendered = false;
		/**
		 * @var BWF_Admin_General_Settings|null
		 */
		public $admin_general_settings = null;
		private static $ins = null;

		public function __construct() {
			add_action( 'wp_head', array( $this, 'render' ), 12 );
			$this->admin_general_settings = BWF_Admin_General_Settings::get_instance();

			if ( true === apply_filters( 'wffn_conversion_tracking_persistant', false ) ) {

				add_action( 'wffn_optin_form_submit', array( $this, 'update_optin_tracking_data' ), 10, 2 );
				add_action( 'woocommerce_checkout_order_processed', array( $this, 'update_order_tracking_data' ), 9999, 1 );
				add_filter( 'bwf_add_db_table_schema', array( $this, 'create_db_tables' ), 10, 2 );
				add_action( 'add_meta_boxes', array( $this, 'add_single_order_meta_box' ), 50, 2 );
			}
		}

		/**
		 * @return BWF_Ecomm_Tracking_Common|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function render() {

			if ( $this->is_enable_tracking() ) {
				$this->render_js_to_track_referer();
			}
		}

		public function is_enable_tracking() {
			if ( true === bwf_string_to_bool( $this->admin_general_settings->get_option( 'track_utms' ) ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Render UTM js to fire to fire events
		 */
		public function render_js_to_track_referer() {
			$min = '.min';
			if ( defined( 'WFFN_IS_DEV' ) && true === WFFN_IS_DEV ) {
				$min = '';
			}

			$data = [
				'utc_offset'         => esc_attr( $this->get_timezone_offset() ),
				'site_url'           => esc_url( site_url() ),
				'genericParamEvents' => wp_json_encode( $this->get_generic_event_params() )
			];

			wp_enqueue_script( 'wfco-utm-tracking', plugin_dir_url( WooFunnel_Loader::$ultimate_path ) . 'woofunnels/assets/js/utm-tracker' . $min . '.js', array(), WooFunnel_Loader::$version, false );
			wp_localize_script( 'wfco-utm-tracking', 'wffnUtm', $data );

		}

		/**
		 * Add Generic event params to the data in events
		 * @return array
		 */
		public function get_generic_event_params() {
			$user = wp_get_current_user();
			if ( $user->ID !== 0 ) {
				$user_roles = implode( ',', $user->roles );
			} else {
				$user_roles = 'guest';
			}

			return array(
				'domain'     => substr( get_home_url( null, '', 'http' ), 7 ),
				'user_roles' => $user_roles,
				'plugin'     => 'Funnel Builder',
			);

		}


		/**
		 * Create DB tables
		 * Actions and bwf_conversion_tracking
		 */
		public function create_db_tables( $args, $tables ) {

			if ( $tables['version'] !== BWF_DB_VERSION || ! in_array( 'bwf_conversion_tracking', $tables['tables'], true ) ) {
				$args[] = [
					'name'   => 'bwf_conversion_tracking',
					'schema' => "CREATE TABLE `{table_prefix}bwf_conversion_tracking` (
						`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						`contact_id` bigint(20) unsigned NOT NULL default 0,
						`utm_source` longtext,
						`utm_medium` longtext,
						`utm_campaign` longtext,
						`utm_term` longtext,
						`utm_content` longtext,
						`click_id` varchar(255),						
						`type` tinyint(2) unsigned COMMENT '1- optin 2- wc_order 3- edd_order',
						`value` varchar(255),
						`step_id` bigint(20) unsigned NOT NULL default 0,
						`funnel_id` bigint(20) unsigned NOT NULL default 0,
						`automation_id` bigint(20) unsigned NOT NULL default 0,
						`first_click` DateTime NOT NULL,
						`referrer` longtext,
						`source` bigint(20) unsigned NOT NULL default 0,
						`device` varchar(100),
						`browser` varchar(100),
						`country` char(2),
						`timestamp` DateTime NOT NULL,
						PRIMARY KEY (`id`),
						KEY `id` (`id`),
						KEY `step_id` (`step_id`),
						KEY `funnel_id` (`funnel_id`)				
						) {table_collate};",
				];
			}

			return $args;
		}

		/**
		 * @param $optin_id
		 * @param $posted_data
		 *
		 * @return void
		 */
		public function update_optin_tracking_data( $optin_id, $posted_data ) {//phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement
			if ( ! $this->is_enable_tracking() ) {
				return;
			}
			$args               = $this->tracking_data();
			$funnel_id          = get_post_meta( $optin_id, '_bwf_in_funnel', true );
			$args['contact_id'] = $posted_data['cid'];
			$args['type']       = 1;
			$args['step_id']    = $optin_id;
			$args['funnel_id']  = ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) ? $funnel_id : 0;
			$args['source']     = isset( $posted_data['optin_entry_id'] ) ? $posted_data['optin_entry_id'] : 0;
			$this->insert_tracking_data( $args );
		}

		/**
		 * @param $order_id
		 *
		 * @return void
		 */
		public function update_order_tracking_data( $order_id ) {//phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement
			$order = apply_filters( 'bwf_tracking_insert_order', wc_get_order( $order_id ) );

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( ! $this->is_enable_tracking() ) {
				return;
			}
			if ( 'yes' === BWF_WC_Compatibility::get_order_meta( $order, '_wffn_insert_tracking' ) ) {
				return;
			}

			/** Insert data */
			$wfacp_id           = BWF_WC_Compatibility::get_order_meta( $order, '_wfacp_post_id' );
			$cid                = BWF_WC_Compatibility::get_order_meta( $order, '_woofunnel_cid' );
			$funnel_id          = get_post_meta( $wfacp_id, '_bwf_in_funnel', true );
			$args               = $this->tracking_data();
			$args['contact_id'] = ! ( empty( $cid ) ) ? $cid : 0;
			$args['type']       = 2;
			$args['value']      = $order->get_total();
			$args['step_id']    = ! ( empty( $wfacp_id ) ) ? $wfacp_id : 0;
			$args['funnel_id']  = ! ( empty( $funnel_id ) ) ? $funnel_id : 0;
			$args['source']     = $order->get_id();

			$lastId = $this->insert_tracking_data( $args );
			if ( intval( $lastId ) > 0 ) {
				$order->update_meta_data( '_wffn_insert_tracking', 'yes' );
				$order->save_meta_data();
			}
		}

		/**
		 * create default tracking data
		 */
		public function tracking_data() {

			$click_id = '';
			$get_data = $_COOKIE; //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			if ( isset( $get_data['wffn_fbclid'] ) ) {
				$click_id = $get_data['wffn_fbclid'];
			} elseif ( isset( $get_data['wffn_gclid'] ) ) {
				$click_id = $get_data['wffn_gclid'];
			}
			$timezone     = isset( $get_data['wffn_timezone'] ) ? bwf_clean( $get_data['wffn_timezone'] ) : '';
			$country_data = $this->get_country_and_timezone( $timezone );
			$args         = [
				'contact_id'    => 0,
				'utm_source'    => isset( $get_data['wffn_utm_source'] ) ? bwf_clean( $get_data['wffn_utm_source'] ) : '',
				'utm_medium'    => isset( $get_data['wffn_utm_medium'] ) ? bwf_clean( $get_data['wffn_utm_medium'] ) : '',
				'utm_campaign'  => isset( $get_data['wffn_utm_campaign'] ) ? bwf_clean( $get_data['wffn_utm_campaign'] ) : '',
				'utm_term'      => isset( $get_data['wffn_utm_term'] ) ? bwf_clean( $get_data['wffn_utm_term'] ) : '',
				'utm_content'   => isset( $get_data['wffn_utm_content'] ) ? bwf_clean( $get_data['wffn_utm_content'] ) : '',
				'click_id'      => $click_id,
				'type'          => '',
				'value'         => 0,
				'step_id'       => 0,
				'funnel_id'     => 0,
				'automation_id' => 0,
				'first_click'   => isset( $get_data['wffn_flt'] ) ? bwf_clean( $get_data['wffn_flt'] ) : '',
				'referrer'      => isset( $get_data['wffn_referrer'] ) ? bwf_clean( $get_data['wffn_referrer'] ) : '',
				'source'        => 0,
				'device'        => isset( $get_data['wffn_is_mobile'] ) ? ( true === bwf_string_to_bool( $get_data['wffn_is_mobile'] ) ? 'mobile' : 'desktop' ) : '',
				'browser'       => isset( $get_data['wffn_browser'] ) ? bwf_clean( $get_data['wffn_browser'] ) : '',
				'country'       => ( is_array( $country_data ) && isset( $country_data['country_code'] ) ) ? $country_data['country_code'] : '',
				'timestamp'     => current_time( 'mysql' ),//phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			];

			return $args;

		}

		/**
		 * Insert tracking data
		 *
		 * @param $args
		 *
		 * @return int
		 */
		public function insert_tracking_data( $args ) {
			global $wpdb;

			$args     = apply_filters( 'bwf_insert_conversion_tracking_data', $args );
			$inserted = $wpdb->insert( $wpdb->prefix . "bwf_conversion_tracking", $args );

			$lastId = 0;
			if ( $inserted ) {
				$lastId = $wpdb->insert_id;
			}
			if ( ! empty( $wpdb->last_error ) ) {
				BWF_Logger::get_instance()->log( 'Get last error in bwf_conversion_tracking : ' . $wpdb->last_error . ' --- Last query ' . $wpdb->last_query, 'woofunnel-failed-actions', 'buildwoofunnels', true ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return $lastId;
		}

		/**
		 * @param $timezone
		 *
		 * @return array|string
		 */
		public function get_country_and_timezone( $timezone ) {
			$result = '';
			if ( '' === $timezone ) {
				return $result;
			}

			ob_start();
			include dirname( __DIR__ ) . '/contact/data/contries-timzone.json'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingNonPHPFile.IncludingNonPHPFile
			$list = ob_get_clean();
			$list = json_decode( $list, true );

			$country_list = wp_list_pluck( $list, 'timezone' );

			//check valid timezone
			foreach ( $country_list as $key => $item ) {
				if ( false !== array_search( $timezone, $item, true ) ) {
					$result = array(
						'country_code' => $key,
						'timezone'     => $timezone
					);
					break;
				}
			}

			return $result;
		}

		/**
		 * get the timezone offset in minutes
		 * @return float|int
		 */
		public function get_timezone_offset() {
			$offset                 = 0;
			$offset_diff_in_seconds = current_time( 'timestamp' ) - current_time( 'timestamp', true );
			if ( absint( $offset_diff_in_seconds ) > 0 ) {
				$offset = $offset_diff_in_seconds / 60;
			}

			return $offset;
		}

		public function add_single_order_meta_box( $post_type, $post ) {
			if ( 'shop_order' !== $post_type && 'woocommerce_page_wc-orders' !== $post_type ) {
				return;
			}
			if ( ! $this->is_enable_tracking() ) {
				return;
			}
			$order_id = $_GET['id'] ?? $post->ID;
			$order    = wc_get_order( $order_id );
			if ( 'yes' !== BWF_WC_Compatibility::get_order_meta( $order, '_wffn_insert_tracking' ) ) {
				return;
			}
			$data = array(
				'bwf_source_id' => $order_id,
			);
			add_meta_box( 'bwfan_utm_info_box', __( 'Conversion Tracking', 'woofunnels' ), array(
				$this,
				'order_meta_box_data'
			), function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order', 'side', 'default', $data );
		}

		public function order_meta_box_data( $post, $meta_data ) {
			$args = $meta_data['args'];
			global $wpdb;

			if ( ! isset( $args['bwf_source_id'] ) || empty( $args['bwf_source_id'] ) ) {
				return;
			}
			$query    = $wpdb->prepare( "SELECT * from " . $wpdb->prefix . "bwf_conversion_tracking WHERE source = %d", $args['bwf_source_id'] );
			$get_data = $wpdb->get_row( $query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( empty( $get_data ) ) {
				return;
			}

			$first_click = ( isset( $get_data['first_click'] ) && '0000-00-00 00:00:00' !== $get_data['first_click'] ) ? $get_data['first_click'] : '';
			$timestamp   = isset( $get_data['timestamp'] ) ? $get_data['timestamp'] : '';

			$funnel_id = isset( $get_data['funnel_id'] ) ? $get_data['funnel_id'] : 0;
			$funnel    = $funnel_id;

			if ( class_exists( 'WFFN_Funnel' ) && class_exists( 'WFFN_Common' ) ) {
				$funnel_obj = new WFFN_Funnel( $funnel_id );
				if ( $funnel_obj instanceof WFFN_Funnel && $funnel_obj->get_id() > 0 ) {
					$link         = WFFN_Common::get_funnel_edit_link( $funnel_obj->get_id() );
					$funnel_title = ! empty( $funnel_obj->get_title() ) ? $funnel_obj->get_title() : $funnel_obj->get_id();
					$funnel       = '<a href="' . $link . '" target="_blank">' . $funnel_title . '</a>';
				}

			}
			$diff = '';
			$ref  = '';
			$data = [];
			if ( ! empty( $first_click ) ) {
				$d1   = strtotime( $timestamp );
				$d2   = strtotime( $first_click );
				$diff = human_time_diff( $d1, $d2 );
			}

			if ( isset( $get_data['referrer'] ) && $get_data['referrer'] !== '' ) {
				$ref = explode( '?', $get_data['referrer'] );
			}
			$data['funnel'] = array(
				'name'  => __( 'Funnel', 'woofunnels' ),
				'value' => $funnel
			);
			if ( '' !== $first_click ) {
				$data['first_click'] = array(
					'name'  => __( 'First Interaction', 'woofunnels' ),
					'value' => $first_click
				);
			}
			if ( '' !== $diff ) {
				$data['convert'] = array(
					'name'  => __( 'Conversion Time', 'woofunnels' ),
					'value' => $diff,
				);
			}
			if ( isset( $get_data['utm_source'] ) && '' !== $get_data['utm_source'] ) {
				$data['utm_source'] = array(
					'name'  => __( 'UTM Source', 'woofunnels' ),
					'value' => ucfirst( $get_data['utm_source'] ),
				);
			}
			if ( isset( $get_data['utm_medium'] ) && '' !== $get_data['utm_medium'] ) {
				$data['utm_medium'] = array(
					'name'  => __( 'UTM Medium', 'woofunnels' ),
					'value' => ucfirst( $get_data['utm_medium'] ),
				);
			}
			if ( isset( $get_data['utm_campaign'] ) && '' !== $get_data['utm_campaign'] ) {
				$data['utm_campaign'] = array(
					'name'  => __( 'UTM Campaign', 'woofunnels' ),
					'value' => $get_data['utm_campaign'],
				);
			}
			if ( isset( $get_data['utm_term'] ) && '' !== $get_data['utm_term'] ) {
				$data['utm_term'] = array(
					'name'  => __( 'UTM Term', 'woofunnels' ),
					'value' => $get_data['utm_term'],
				);
			}
			if ( isset( $get_data['utm_content'] ) && '' !== $get_data['utm_content'] ) {
				$data['utm_content'] = array(
					'name'  => __( 'UTM Content', 'woofunnels' ),
					'value' => $get_data['utm_content'],
				);
			}
			if ( isset( $get_data['referrer'] ) && '' !== $get_data['referrer'] ) {
				$data['referrer'] = array(
					'name'  => __( 'Referrer', 'woofunnels' ),
					'value' => ( is_array( $ref ) && isset( $ref[0] ) ) ? '<a href="' . $ref[0] . '" target="_blank">' . $ref[0] . '</a>' : ''
				);
			}
			if ( isset( $get_data['click_id'] ) ) {
				$data['click_id'] = array(
					'name'  => __( 'Click ID', 'woofunnels' ),
					'value' => ( '' !== $get_data['click_id'] ) ? __( 'Yes', 'woofunnels' ) : __( 'No', 'woofunnels' ),
				);
			}
			if ( isset( $get_data['device'] ) && '' !== $get_data['device'] ) {
				$data['device'] = array(
					'name'  => __( 'Device', 'woofunnels' ),
					'value' => ucfirst( $get_data['device'] ),
				);
			}
			if ( isset( $get_data['browser'] ) && '' !== $get_data['browser'] ) {
				$data['browser'] = array(
					'name'  => __( 'Browser', 'woofunnels' ),
					'value' => $get_data['browser'],
				);
			}
			$data = apply_filters( 'bwf_utm_tracking_meta_box', $data, $meta_data, $post );
			if ( empty( $data ) ) {
				return;
			}
			?>
            <style>
                .bwf-utm-box-data {
                    margin: 10px 0;
                }

                .bwf-utm-box-data > div > span:nth-child(1) {
                    font-weight: 500;
                    width: 80px;
                    display: inline-block;
                    min-width: 105px;
                }

                .bwf-utm-box-data > div {
                    margin-bottom: 8px;
                    display: flex;
                    word-break: break-all;
                }

                .bwf-utm-box-data .bwf-utm-data-gap {
                    display: block;
                    clear: both;
                    height: 1px;
                    border-bottom: 1px solid #eee;
                    margin-bottom: 10px;
                }
            </style>
            <div class="bwf-utm-box-data">
                <div class="bwf-utm-data-gap"></div>
				<?php
				foreach ( $data as $item ) {
					?>
                    <div>
                        <span class="bwf-utm-lable"><?php echo $item['name'] . ': '; ?></span>
                        <span class="bwf-utm-text"><?php echo $item['value']; ?></span>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
		}
	}

	BWF_Ecomm_Tracking_Common::get_instance();
}

