/**
 * Checkout js
 * To show the 'CSC' field only againsed the selected token, hide others
 */
jQuery(document).ready(function () {
	jQuery(document).ajaxComplete(function (event, xhr, options) {
		jQuery('input[name="wc-nmi_gateway_woocommerce_credit_card-payment-token"]').each(function () {
			if (jQuery(this).is(':checked')) {
				const token_id = jQuery(this).val();
				jQuery('.nmi_csc_' + token_id).css('display', 'block');
			}
		});
		jQuery('input[name="wc-nmi_gateway_woocommerce_credit_card-payment-token"]').on('change', function () {
			jQuery('input[name="wc-nmi_gateway_woocommerce_credit_card-payment-token"]').each(function () {
				let token_id = jQuery(this).val();
				jQuery('.nmi_csc_' + token_id).css('display', 'none');
			});
			if (jQuery(this).is(':checked')) {
				let token_id = jQuery(this).val();
				jQuery('.nmi_csc_' + token_id).css('display', 'block');
			}
		});
	});
});
