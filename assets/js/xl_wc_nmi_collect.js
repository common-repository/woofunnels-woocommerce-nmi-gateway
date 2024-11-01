/**
 * Implementation of collect.js
 */
jQuery(function ($) {
	'use strict';
	let xl_nmi_error = {};
	let is_valid_card = false;
	/**
	 * Handling XL NMI Collect js form posting
	 */
	let xl_wc_nmi_form = {
		/**
		 * Creating XL NMI CC fields
		 */
		createFormFields: function () {
			CollectJS.configure({
				"variant": "inline",
				"styleSniffer": "false",
				"googleFont": "Montserrat:400",
				"customCss": {
					"line-height": "2.1em"
				},
				"invalidCss": {
					"border-color": "red"
				},
				"fields": {
					"ccnumber": {
						"selector": "#xl-wc-nmi-card-number",
						"placeholder": "•••• •••• •••• ••••"
					},
					"ccexp": {
						"selector": "#xl-wc-nmi-card-expiry",
						"placeholder": "MM/YY"
					},
					"cvv": {
						"display": "show",
						"selector": "#xl-wc-nmi-card-cvv",
						"placeholder": "CVC"
					}
				},
				"timeoutDuration": 25000,
				"timeoutCallback": function () {
					$(document).trigger('showNMIError', sv_wc_payment_gateway_payment_form_params.timeout_error);
				},
				"fieldsAvailableCallback": function () {
					xl_wc_nmi_form.showForm();
					console.log("Collect.js loaded the fields onto the collect js credit card form");
				},
				'callback': function (response) {
					xl_wc_nmi_form.processNMIResponse(response);
				},
				'validationCallback': function (field, status, message) {
					if (status) {
						var message = field + " is OK: " + message;
						xl_nmi_error[field] = '';
					} else {
						xl_nmi_error[field] = message;
					}
					console.log(message);
				},
			});
		},


		/**
		 * Initialize event handlers and UI state.
		 */
		init: function () {

			if ('undefined' === typeof CollectJS) {
				alert('Invalid tokenization key. Please contact admin to resolve the issue.');
				return false;
			}

			// checkout page
			if ($('form.woocommerce-checkout').length) {
				this.form = $('form.woocommerce-checkout');
			}

			$('form.woocommerce-checkout')
				.on(
					'checkout_place_order_nmi_gateway_woocommerce_credit_card',
					this.onSubmit
				);

			// pay for order page
			if ($('form#order_review').length) {
				this.form = $('form#order_review');
			}

			$('form#order_review')
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page from my-account
			if ($('form#add_payment_method').length) {
				this.form = $('form#add_payment_method');
			}

			$('form#add_payment_method')
				.on(
					'submit',
					this.onSubmit
				);

			$(document)
				.on(
					'change',
					'#wc-nmi_gateway_woocommerce_credit_card-form :input',
					this.onCCFormChange
				)
				.on(
					'showNMIError',
					this.showNMIError
				)
				.on(
					'checkout_error',
					this.clearToken
				);

			if (xl_wc_nmi_form.isXLNMISelected() && !xl_wc_nmi_form.isTokenAvailable()) {
				xl_wc_nmi_form.hideForm();
				xl_wc_nmi_form.createFormFields();
			}

			/**
			 * Delaying mount of the credit card form to complete other ajax processes
			 */
			if ('yes' === sv_wc_payment_gateway_payment_form_params.is_checkout) {
				$(document.body).on('updated_checkout', function () {
					// Mounting the credit card form after checkout updated event
					if (xl_wc_nmi_form.isXLNMISelected() && !xl_wc_nmi_form.isTokenAvailable()) {
						xl_wc_nmi_form.hideForm();
						xl_wc_nmi_form.createFormFields();
					}
				});
				$(document.body).on('wfacp_step_switching', function () {
					// Mounting the credit card form after checkout updated event
					if (xl_wc_nmi_form.isXLNMISelected() && !xl_wc_nmi_form.isTokenAvailable()) {
						xl_wc_nmi_form.hideForm();
						xl_wc_nmi_form.createFormFields();
					}
				});
			}

			$(document.body).on('payment_method_selected', function () {
				if (xl_wc_nmi_form.isXLNMISelected() && !xl_wc_nmi_form.isTokenAvailable()) {
					xl_wc_nmi_form.hideForm();
					xl_wc_nmi_form.createFormFields();
				}
			});

			if (undefined !== this.form) {
				this.form.on('click change', 'input[name="wc-nmi_gateway_woocommerce_credit_card-payment-token"]', function () {
					if (xl_wc_nmi_form.isXLNMISelected() && !xl_wc_nmi_form.isTokenAvailable()) {
						xl_wc_nmi_form.hideForm();
						xl_wc_nmi_form.createFormFields();
					}
				});
			}
		},

		isXLNMISelected: function () {
			return ($('#payment_method_nmi_gateway_woocommerce_credit_card').is(':checked'));
		},

		isTokenAvailable: function () { //To check is a js token is created and put in the hidden field or a saved token is selected.
			if ((0 < $('input.xl_wc_nmi_js_token').length) && (0 < $('input.xl_wc_nmi_js_response').length)) {
				return true;
			}
			if ($('input[name="wc-nmi_gateway_woocommerce_credit_card-payment-token"]').length > 1 && 'new' !== $('input[name="wc-nmi_gateway_woocommerce_credit_card-payment-token"]:checked').val()) {
				return true;
			}
			return false
		},

		hideForm: function () {
			xl_wc_nmi_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		showForm: function () {
			xl_wc_nmi_form.form.unblock();
		},

		showNMIError: function (e, result) {
			let msg = result;
			let errorDiv = $('#wc-nmi_gateway_woocommerce_credit_card-form').find('.xl-wc-nmi-source-errors');

			xl_wc_nmi_form.onCCFormChange();
			$('.woocommerce-NoticeGroup-checkout').remove();
			$(errorDiv).html('<ul class="woocommerce_error woocommerce-error xl-wc-nmi-error"><li></li></ul>');
			$(errorDiv).find('li').text(msg);

			if ($('.xl-wc-nmi-error').length) {
				$('html, body').animate({
					scrollTop: ($('.xl-wc-nmi-error').offset().top - 200)
				}, 200);
			}
			xl_wc_nmi_form.showForm();
		},

		onSubmit: function (e) {
			if (xl_wc_nmi_form.isXLNMISelected() && !xl_wc_nmi_form.isTokenAvailable()) {
				e.preventDefault();
				xl_wc_nmi_form.hideForm();
				let err_msg;

				console.log(xl_nmi_error);

				let validCardNumber = document.querySelector("#xl-wc-nmi-card-number .CollectJSValid") !== null;
				let validCardExpiry = document.querySelector("#xl-wc-nmi-card-expiry .CollectJSValid") !== null;
				let validCardCvv = document.querySelector("#xl-wc-nmi-card-cvv .CollectJSValid") !== null;

				if (!validCardNumber) {
					err_msg = sv_wc_payment_gateway_payment_form_params.card_number_invalid + (xl_nmi_error.ccnumber ? ' (' + xl_nmi_error.ccnumber + ')' : '');
					$(document.body).trigger('showNMIError', err_msg);
					return false;
				}

				if (!validCardExpiry) {
					err_msg = sv_wc_payment_gateway_payment_form_params.card_exp_date_invalid + (xl_nmi_error.ccexp ? ' (' + xl_nmi_error.ccexp + ')' : '');
					$(document.body).trigger('showNMIError', err_msg);
					return false;
				}

				if (!validCardCvv) {
					err_msg = sv_wc_payment_gateway_payment_form_params.cvv_missing + (xl_nmi_error.cvv ? ' (' + xl_nmi_error.cvv + ')' : '');
					$(document.body).trigger('showNMIError', err_msg);
					return false;
				}

				CollectJS.startPaymentRequest();

				return false;
			}
		},

		onCCFormChange: function () {
			$('.xl-wc-nmi-error, .xl_wc_nmi_js_token, .xl_wc_nmi_js_response').remove();
		},

		processNMIResponse: function (response) {
			console.log('NMI Response: ');
			console.log(response);

			if (response.card.type !== null) {
				sv_wc_payment_gateway_payment_form_params.allowed_card_types.forEach(function (card_type) {
					if (response.card.type === card_type.replace('diners-club', 'diners')) {
						is_valid_card = true;
					}
				});

				if (!is_valid_card) {
					$(document.body).trigger('showNMIError', sv_wc_payment_gateway_payment_form_params.card_number_invalid);
					return false;
				}
			}

			xl_wc_nmi_form.form.append("<input type='hidden' class='xl_wc_nmi_js_token' name='xl_wc_nmi_js_token' value='" + response.token + "'/>");
			xl_wc_nmi_form.form.append("<input type='hidden' class='xl_wc_nmi_js_response' name='xl_wc_nmi_js_response' value='" + JSON.stringify(response) + "'/>");
			xl_wc_nmi_form.form.submit();
		},

		clearToken: function () {
			$('.xl_wc_nmi_js_token, .xl_wc_nmi_js_response').remove();
		}
	};

	xl_wc_nmi_form.init();
});
