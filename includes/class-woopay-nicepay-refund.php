<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayNicePayRefund' ) ) {
	class WooPayNicePayRefund extends WooPayNicePay {
		public function __construct() {
			parent::__construct();

			$this->init_refund();
		}

		function init_refund() {
			// For Customer Refund
			add_filter( 'woocommerce_my_account_my_orders_actions',  array( $this, 'add_customer_refund' ), 10, 2 );
		}

		public function do_refund( $orderid, $amount = null, $reason = '', $rcvtid = null, $type = null ) {
			$order			= wc_get_order( $orderid );

			if ( $order == null ) {
				$message = __( 'Refund request received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting refund process.', $this->woopay_domain ), $orderid );

			require_once $this->woopay_plugin_basedir . '/bin/lib/NicepayLite.php';

			$nicepay = new NicepayLite;

			$nicepay->m_NicepayHome = $this->woopay_plugin_basedir . '/bin/log';

			if ( $amount == null ) {
				$amount = $order->get_total();
			}

			$tid = get_post_meta( $orderid, '_' . $this->woopay_api_name . '_tid', true );

			if ( $tid == '' ) {
				$message = __( 'No TID found.', $this->woopay_domain );
				$this->log( $message, $orderid );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			if ( $rcvtid != null ) {
				if ( $tid != $rcvtid ) {
					$message = __( 'TID does not match.', $this->woopay_domain );
					$this->log( $message, $orderid );

					return array(
						'result' 	=> 'failure',
						'message'	=> $message
					);
				}
			}

			if ( $type == 'customer' ) {
				$refunder = __( 'Customer', $this->woopay_domain );
			} else {
				$refunder = __( 'Administrator', $this->woopay_domain );
			}

			if ( $reason == '' ) {
				$reason = '.';
			}

			$nicepay->m_ssl = 'true';	

			$nicepay->m_ActionType			= 'CLO';
			$nicepay->m_CancelAmt			= $amount;
			$nicepay->m_TID					= $tid;
			$nicepay->m_CancelMsg			= iconv( 'UTF-8', 'EUC-KR', $reason );
			$nicepay->m_PartialCancelCode	= '0';
			$nicepay->m_CancelPwd			= ( $this->testmode ) ? '123456' : $this->CancelKey;
			$nicepay->m_charSet				= 'UTF8';

			$nicepay->m_log					= ( $this->testmode ) ? true : false;

			$nicepay->startAction();

			$resultCode		= $nicepay->m_ResultData[ 'ResultCode' ];
			$resultMsg		= $nicepay->m_ResultData[ 'ResultMsg' ];

			if ( $resultCode == '2001' || $resultCode == '2211' ) {
				$message = sprintf( __( 'Refund process complete. Refunded by %s. Reason: %s.', $this->woopay_domain ), $refunder, $reason );

				$this->log( $message, $orderid );

				$message = sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() );

				$order->update_status( 'refunded', $message );

				return array(
					'result' 	=> 'success',
					'message'	=> __( 'Your refund request has been processed.', $this->woopay_domain )
				);
			} else {
				$message = __( 'An error occurred while processing the refund.', $this->woopay_domain );

				$this->log( $message, $orderid );
				$this->log( __( 'Result Code: ', $this->woopay_domain ) . $resultCode, $orderid );
				$this->log( __( 'Result Message: ', $this->woopay_domain ) . $resultMsg, $orderid );

				$order->add_order_note( sprintf( __( '%s Code: %s. Message: %s. Timestamp: %s.', $this->woopay_domain ), $message, $resultCode, $resultMsg, $this->get_timestamp() ) );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}
		}
	}

	return new WooPayNicePayRefund();
}