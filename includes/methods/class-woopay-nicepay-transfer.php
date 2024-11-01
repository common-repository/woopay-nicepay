<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayNicePayTransfer' ) ) {
	class WooPayNicePayTransfer extends WooPayNicePayPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'nicepay_transfer';
			$this->section					= 'woopaynicepaytransfer';
			$this->method 					= 'BANK';
			$this->method_title 			= __( 'NicePay Account Transfer', $this->woopay_domain );
			$this->title_default 			= __( 'Account Transfer', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via account transfer.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}

	}

	function add_nicepay_transfer( $methods ) {
		$methods[] = 'WooPayNicePayTransfer';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_nicepay_transfer' );
}