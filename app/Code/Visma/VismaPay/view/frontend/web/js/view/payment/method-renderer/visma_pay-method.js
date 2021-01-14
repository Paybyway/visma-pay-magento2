define(
	[
	'jquery',
	'Magento_Checkout/js/view/payment/default',
	'mage/url',
	'mage/translate'
	],
	function ($, Component, url) {
		'use strict';
		var desc = window.checkoutConfig.payment.visma_pay.description;
		var payment_methods = window.checkoutConfig.payment.visma_pay.payment_methods;
		return Component.extend({
			
			defaults: {
				template: 'Visma_VismaPay/payment/visma_pay'
			},
			redirectAfterPlaceOrder: false,

			afterPlaceOrder: function () {
				window.location.replace(url.build('visma_pay/checkout/redirect'));
			},
			getTitle: function () {
				if (this.item.method !== "visma_pay")
				{
					return $.mage.__(this.item.title);
				}
				else
				{
					return $.mage.__(this.item.title) + payment_methods;
				}
			},
			getDescription: function () {
				if (this.item.method !== "visma_pay")
				{
					return "";
				}
				else
				{
					return $.mage.__(desc);
				}
			},
			getBpfLogoSrc: function () {
				var bpfsrc = window.checkoutConfig.payment.visma_pay.vp_logo_src;
				return bpfsrc;
			}
		});
	}

	);
