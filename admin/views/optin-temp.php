<?php
defined( 'ABSPATH' ) || exit;

global $current_user;
$user_instance = __( 'Hey', 'woofunnels' );
if ( is_object( $current_user ) ) {
	$user_instance .= ' ' . $current_user->display_name . ',';
}
$non_sensitive_page_link = esc_url( "https://buildwoofunnels.com/non-sensitive-usage-tracking/?utm_source=nmi-gateway-woocommerce&utm_campaign=optin&utm_medium=text-click&utm_term=non-sensitive" );
$accept_link             = esc_url( wp_nonce_url( add_query_arg( array(
	'woofunnels-optin-choice' => 'yes',
	'ref'                     => filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING )
) ), 'woofunnels_optin_nonce', '_woofunnels_optin_nonce' ) );
$skip_link               = esc_url( wp_nonce_url( add_query_arg( 'woofunnels-optin-choice', 'no' ), 'woofunnels_optin_nonce', '_woofunnels_optin_nonce' ) );
?>
<div id="woofunnelso-wrap" class="woofunnelso_wrap">
    <div class="woofunnelso-logos">
        <img class="woofunnelso-wrap-nmi-logo" width="80" height="80" src="<?php echo esc_url( plugin_dir_url( NMI_GATEWAY_WOOCOMMERCE_FILE ) ) . 'admin/assets/img/nmi.png'; ?>"/>
        <i class="dashicons dashicons-plus"></i>
        <img class="xlo-wrap-logo" width="80" height="80" src="<?php echo esc_url( plugin_dir_url( NMI_GATEWAY_WOOCOMMERCE_FILE ) ) . 'admin/assets/img/xlplugins.png'; ?>"/>
    </div>
    <div class="woofunnelso-content">
        <p><?php echo esc_html( $user_instance ); ?><br></p>
        <h2><?php esc_html_e( 'Thank you for choosing XL WooCommerce NMI (Network Merchants) Gateway!', 'woofunnels' ) ?></h2>
        <p><?php esc_html_e( 'We are constantly improving the plugin and building in new features.', 'woofunnels' ) ?></p>
        <p><?php esc_html_e( 'Never miss an update! Opt in for security, feature updates and non-sensitive diagnostic tracking. Click Allow &amp; Continue!', 'woofunnels' ) ?></p>
    </div>
    <div class="woofunnelso-actions" data-source="NMI-Gateway-Woocommerce">
        <a href="<?php echo esc_url( $skip_link ); ?>" class="button button-secondary" data-status="no"><?php _e( 'Skip', 'woofunnels' ) ?></a>
        <a href="<?php echo esc_url( $accept_link ); ?>" class="button button-primary" data-status="yes"><?php _e( 'Allow &amp; Continue', 'woofunnels' ); ?></a>
        <div style="display: none" class="woofunnelso_loader">&nbsp;</div>
    </div>
    <div class="woofunnelso-permissions">
        <a class="woofunnelso-trigger" href="#" tabindex="1"><?php esc_html_e( 'What permissions are being granted?', 'woofunnels' ) ?></a>
        <ul>
            <li id="woofunnelso-permission-profile" class="woofunnelso-permission woofunnelso-profile">
                <i class="dashicons dashicons-admin-users"></i>
                <div>
                    <span><?php esc_html_e( 'Your Profile Overview', 'woofunnels' ); ?></span>
                    <p><?php esc_html_e( 'Name and email address', 'woofunnels' ) ?></p>
                </div>
            </li>
            <li id="woofunnelso-permission-site" class="woofunnelso-permission woofunnelso-site">
                <i class="dashicons dashicons-admin-settings"></i>
                <div>
                    <span><?php esc_html_e( 'Your Site Overview', 'woofunnels' ) ?></span>
                    <p><?php esc_html_e( 'Site URL, WP version, PHP info, plugins &amp; themes', 'woofunnels' ) ?></p>
                </div>
            </li>
        </ul>
    </div>
    <div class="woofunnelso-terms">
        <a href="<?php echo esc_url( $non_sensitive_page_link ); ?>" target="_blank"><?php _e( 'Non-Sensitive Usage Tracking', 'woofunnels' ) ?></a>
    </div>
</div>
<script type="text/javascript">
    (function ($) {
        $('.woofunnelso-permissions .woofunnelso-trigger').on('click', function () {
            $('.woofunnelso-permissions').toggleClass('woofunnelso-open');

            return false;
        });
        $('.woofunnelso-actions a').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            var source = $this.parents('.woofunnelso-actions').data('source');
            var status = $this.data('status');
            $this.parents('.woofunnelso-actions').find(".woofunnelso_loader").show();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'woofunnelso_optin_call',
                    source: source,
                    status: status,
                    _ajax_nonce: bwf_secure.nonce,
                },
                success: function (result) {
                    window.location = $this.attr('href');
                }
            });
        })
    })(jQuery);
</script>

<style>
    #woofunnelso-wrap {
        width: 480px;
        -moz-box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        -webkit-box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        margin: 30px 0;
        max-width: 100%
    }

    #woofunnelso-wrap .woofunnelso-content {
        background: #fff;
        padding: 0 20px 15px
    }

    #woofunnelso-wrap .woofunnelso-content p {
        margin: 0 0 1em;
        padding: 0;
        font-size: 1.1em
    }

    #woofunnelso-wrap .woofunnelso-actions {
        padding: 10px 20px;
        background: #C0C7CA;
        position: relative
    }

    #woofunnelso-wrap .woofunnelso-actions .woofunnelso_loader {
        background: url("<?php echo esc_url(admin_url('images/spinner.gif')); ?>") no-repeat rgba(238, 238, 238, 0.5);
        background-position: center;
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0
    }

    #woofunnelso-wrap .woofunnelso-actions .button {
        padding: 0 10px 1px;
        line-height: 35px;
        height: 37px;
        font-size: 16px;
        margin-bottom: 0
    }

    #woofunnelso-wrap .woofunnelso-actions .button .dashicons {
        font-size: 37px;
        margin-left: -8px;
        margin-right: 12px
    }

    #woofunnelso-wrap .woofunnelso-actions .button.button-primary {
        padding-right: 15px;
        padding-left: 15px
    }

    #woofunnelso-wrap .woofunnelso-actions .button.button-primary:after {
        content: ' \279C'
    }

    #woofunnelso-wrap .woofunnelso-actions .button.button-primary {
        float: right
    }

    #woofunnelso-wrap.woofunnelso-anonymous-disabled .woofunnelso-actions .button.button-primary {
        width: 100%
    }

    #woofunnelso-wrap .woofunnelso-permissions {
        padding: 10px 20px;
        background: #FEFEFE;
        -moz-transition: background .5s ease;
        -o-transition: background .5s ease;
        -ms-transition: background .5s ease;
        -webkit-transition: background .5s ease;
        transition: background .5s ease
    }

    #woofunnelso-wrap .woofunnelso-permissions .woofunnelso-trigger {
        font-size: .9em;
        text-decoration: none;
        text-align: center;
        display: block
    }

    #woofunnelso-wrap .woofunnelso-permissions ul {
        height: 0;
        overflow: hidden;
        margin: 0
    }

    #woofunnelso-wrap .woofunnelso-permissions ul li {
        margin-bottom: 12px
    }

    #woofunnelso-wrap .woofunnelso-permissions ul li:last-child {
        margin-bottom: 0
    }

    #woofunnelso-wrap .woofunnelso-permissions ul li i.dashicons {
        float: left;
        font-size: 40px;
        width: 40px;
        height: 40px
    }

    #woofunnelso-wrap .woofunnelso-permissions ul li div {
        margin-left: 55px
    }

    #woofunnelso-wrap .woofunnelso-permissions ul li div span {
        font-weight: 700;
        text-transform: uppercase;
        color: #23282d
    }

    #woofunnelso-wrap .woofunnelso-permissions ul li div p {
        margin: 2px 0 0
    }

    #woofunnelso-wrap .woofunnelso-permissions.woofunnelso-open {
        background: #fff
    }

    #woofunnelso-wrap .woofunnelso-permissions.woofunnelso-open ul {
        height: auto;
        margin: 20px 20px 10px
    }

    #woofunnelso-wrap .woofunnelso-logos {
        padding: 20px;
        line-height: 0;
        background: #fafafa;
        height: 84px;
        position: relative
    }

    #woofunnelso-wrap .woofunnelso-logos .xlo-wrap-logo {
        position: absolute;
        left: 75%;
        top: 20px
    }

    #woofunnelso-wrap .woofunnelso-logos img, #woofunnelso-wrap .woofunnelso-logos object {
        width: 80px;
        height: 80px
    }

    #woofunnelso-wrap .woofunnelso-logos .dashicons-plus {
        position: absolute;
        top: 50%;
        font-size: 30px;
        margin-top: -10px;
        color: #bbb
    }

    i.dashicons.dashicons-plus {
        position: absolute;
        left: 45%;
    }

    #woofunnelso-wrap .woofunnelso-logos .woofunnelso-wrap-nmi-logo {
        left: 5%;
        position: absolute;
    }

    #woofunnelso-wrap .woofunnelso-terms {
        text-align: center;
        font-size: .85em;
        padding: 5px;
        background: rgba(0, 0, 0, 0.05)
    }

    #woofunnelso-wrap .woofunnelso-terms, #woofunnelso-wrap .woofunnelso-terms a {
        color: #999
    }

    #woofunnelso-wrap .woofunnelso-terms a {
        text-decoration: none
    }

    #woofunnelso-theme_connect_wrapper #woofunnelso-wrap {
        top: 0;
        text-align: left;
        display: inline-block;
        vertical-align: middle;
        margin-top: 52px;
        margin-bottom: 20px
    }

    #woofunnelso-theme_connect_wrapper #woofunnelso-wrap .woofunnelso-terms {
        background: rgba(140, 140, 140, 0.64)
    }

    #woofunnelso-theme_connect_wrapper #woofunnelso-wrap .woofunnelso-terms, #woofunnelso-theme_connect_wrapper #woofunnelso-wrap .woofunnelso-terms a {
        color: #c5c5c5
    }
</style>
