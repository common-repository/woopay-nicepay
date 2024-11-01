<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayNicePayVirtual' ) ) {
	class WooPayNicePayVirtual extends WooPayNicePayPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'nicepay_virtual';
			$this->section					= 'woopaynicepayvirtual';
			$this->method 					= 'VBANK';
			$this->method_title 			= __( 'NicePay Virtual Account', $this->woopay_domain );
			$this->title_default 			= __( 'Virtual Account', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via virtual account.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}

	}

	function add_nicepay_virtual( $methods ) {
		$methods[] = 'WooPayNicePayVirtual';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_nicepay_virtual' );
}