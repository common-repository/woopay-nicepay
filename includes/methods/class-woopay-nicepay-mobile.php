<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayNicePayMobile' ) ) {
	class WooPayNicePayMobile extends WooPayNicePayPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'nicepay_mobile';
			$this->section					= 'woopaynicepaymobile';
			$this->method 					= 'CELLPHONE';
			$this->method_title 			= __( 'NicePay Mobile Payment', $this->woopay_domain );
			$this->title_default 			= __( 'Mobile Payment', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via mobile payment.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'mobile';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= false;
		}

	}

	function add_nicepay_mobile( $methods ) {
		$methods[] = 'WooPayNicePayMobile';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_nicepay_mobile' );
}