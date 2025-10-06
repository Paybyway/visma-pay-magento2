define(
	[
	'jquery',
	'uiComponent',
	'Magento_Checkout/js/model/payment/renderer-list',
	'mage/translate'
	],
	function (
		$,
		Component,
		rendererList,
		$t
		) {
		'use strict';
		var vp_embed =  window.checkoutConfig.payment.visma_pay.embed;
		if(vp_embed == 1)
		{
			rendererList.push(
				{type: 'visma_pay_osuuspankki', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_nordea', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_danskebank', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_aktia', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_alandsbanken', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_handelsbanken', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_paikallisosuuspankki', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_spankki', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_saastopankki', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_omasaastopankki', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_nordeab2b', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_danskebankb2b', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_creditcards', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_mobilepay', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_applepay', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_googlepay', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_siirto', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_klarna', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'},
				{type: 'visma_pay_oplasku', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'}
				);
		}
		else
		{
			rendererList.push(
				{type: 'visma_pay', component: 'Visma_VismaPay/js/view/payment/method-renderer/visma_pay-method'}
				);
		}
		/** Add view logic here if needed */
		return Component.extend({});
	}
	);