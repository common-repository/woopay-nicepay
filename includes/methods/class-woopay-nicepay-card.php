<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayNicePayCard' ) ) {
	class WooPayNicePayCard extends WooPayNicePayPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'nicepay_card';
			$this->section					= 'woopaynicepaycard';
			$this->method 					= 'CARD';
			$this->method_title 			= __( 'NicePay Credit Card', $this->woopay_domain );
			$this->title_default 			= __( 'Credit Card', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via credit card.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'card';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}

	}

	function add_nicepay_card( $methods ) {
		$methods[] = 'WooPayNicePayCard';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_nicepay_card' );
}