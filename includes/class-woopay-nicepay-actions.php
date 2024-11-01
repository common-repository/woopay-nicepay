<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayNicePayActions' ) ) {
	class WooPayNicePayActions extends WooPayNicePay {
		function api_action( $type ) {
			@ob_clean();
			header( 'HTTP/1.1 200 OK' );
			switch ( $type ) {
				case 'check_api' :
					$this->do_check_api( $_REQUEST );
					exit;
					break;
				case 'response' :
					$this->do_response( $_REQUEST );
					exit;
					break;
				case 'mobile_return' :
					$this->do_mobile_return( $_REQUEST );
					exit;
					break;
				case 'mobile_response' :
					$this->do_mobile_response( $_REQUEST );
					exit;
					break;
				case 'cas_response' :
					$this->do_cas_response( $_REQUEST );
					exit;
					break;
				case 'refund_request' :
					$this->do_refund_request( $_REQUEST );
					exit;
					break;
				case 'escrow_request' :
					$this->do_escrow_request( $_REQUEST );
					exit;
					break;
				case 'delete_log' :
					$this->do_delete_log( $_REQUEST );
					exit;
					break;
				default :
					exit;
			}
		}

		private function do_check_api( $params ) {
			$result = array(
				'result'	=> 'success',
			);

			echo json_encode( $result );
		}

		private function do_response( $params ) {
			if ( empty( $params[ 'TrKey' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			if ( empty( $params[ 'Moid' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'Moid' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting response process.', $this->woopay_domain ), $orderid );

			require_once $this->woopay_plugin_basedir . '/bin/lib/NicepayLite.php';

			$nicepay = new NicepayLite;

			$nicepay->m_NicepayHome = $this->woopay_plugin_basedir . '/bin/log';

			$GoodsName					= $params[ 'GoodsName' ];
			$GoodsCnt					= $params[ 'GoodsCnt' ];
			$Amt						= $params[ 'Amt' ];
			$Moid						= $params[ 'Moid' ];
			$BuyerName					= $params[ 'BuyerName' ];
			$BuyerEmail					= $params[ 'BuyerEmail' ];
			$BuyerTel					= $params[ 'BuyerTel' ];
			$MallUserID					= $params[ 'MallUserID' ];
			$GoodsCl					= $params[ 'GoodsCl' ];
			$MID						= $params[ 'MID' ];
			$MallIP						= $params[ 'MallIP' ];
			$TrKey						= $params[ 'TrKey' ];
			$EncryptData				= $params[ 'EncryptData' ];
			$PayMethod					= $params[ 'PayMethod' ];
			$TransType					= $params[ 'TransType' ];

			$nicepay->m_GoodsName		= $GoodsName;
			$nicepay->m_GoodsCnt		= $GoodsCnt;
			$nicepay->m_Price			= $Amt;
			$nicepay->m_Moid			= $Moid;
			$nicepay->m_BuyerName		= $BuyerName;
			$nicepay->m_BuyerEmail		= $BuyerEmail;
			$nicepay->m_BuyerTel		= $BuyerTel;
			$nicepay->m_MallUserID		= $MallUserID;
			$nicepay->m_GoodsCl			= $GoodsCl; 
			$nicepay->m_MID				= $MID;
			$nicepay->m_MallIP			= $MallIP;
			$nicepay->m_TrKey			= $TrKey;
			$nicepay->m_EncryptedData	= $EncryptData;
			$nicepay->m_PayMethod		= $PayMethod;
			$nicepay->m_TransType		= $TransType;
			$nicepay->m_ActionType		= 'PYO';

			$nicepay->m_LicenseKey		= ( $this->testmode=='yes' ) ? '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R++d5hDd3Frmuos/XLx8XhXpe+LDYAbpGKZYSwtlyyLOtS/8aD7A==' : $this->MerchantKey;

			$nicepay->m_charSet			= 'UTF8';

			$nicepay->m_NetCancelAmt	= $Amt;
			$nicepay->m_NetCancelPW		= $this->CancelKey;

			$nicepay->m_ssl				= 'true';

			$nicepay->m_log				= ( $this->testmode=='yes' ) ? true : false;

			$nicepay->startAction();

			$resultCode					= $nicepay->m_ResultData[ 'ResultCode' ];
			$resultMsg					= $nicepay->m_ResultData[ 'ResultMsg' ];

			$tid						= $nicepay->m_ResultData[ 'TID' ];
			$vbankBankName				= isset( $nicepay->m_ResultData[ 'VbankBankName' ] ) ? $nicepay->m_ResultData[ 'VbankBankName' ] : '';
			$vbankNum					= isset( $nicepay->m_ResultData[ 'VbankNum' ] ) ? $nicepay->m_ResultData[ 'VbankNum' ] : '';
			$vbankExpDate				= isset( $nicepay->m_ResultData[ 'VbankExpDate' ] ) ? $nicepay->m_ResultData[ 'VbankExpDate' ] : '';

			$this->log( __( 'Result Code: ', $this->woopay_domain ) . $resultCode, $orderid );
			$this->log( __( 'Result Message: ', $this->woopay_domain ) . $resultMsg, $orderid );

			$paySuccess = false;

			if ( $PayMethod == 'CARD' ) {
				if ( $resultCode == '3001' ) $paySuccess = true;
			} elseif ( $PayMethod == 'BANK' ) {
				if ( $resultCode == '4000') $paySuccess = true;
			} elseif ( $PayMethod == 'CELLPHONE' ) {
				if ( $resultCode == 'A000' ) $paySuccess = true;
			} elseif ( $PayMethod == 'VBANK' ) {
				if ( $resultCode == '4100' ) $paySuccess = true;
			}

			if ( (int)$Amt != (int)$order->get_total() ) {
				$paySuccess = false;

				$this->woopay_payment_integrity_failed( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}

			if ( $paySuccess == true ) {
				if ( $PayMethod == 'VBANK' ) {
					$this->woopay_payment_awaiting( $orderid, $tid, $PayMethod, $vbankBankName, $vbankNum, $vbankExpDate );
				} else {
					$this->woopay_payment_complete( $orderid, $tid, $PayMethod );
				}

				WC()->cart->empty_cart();
				wp_redirect( $this->get_return_url( $order ) );
				exit;
			} else {
				$this->woopay_payment_failed( $orderid, $resultCode, $resultMsg );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}
		}

		private function do_mobile_return( $params ) {
			if ( empty( $params[ 'Moid' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'Moid' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Mobile return received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting return process.', $this->woopay_domain ), $orderid );

			if ( in_array( $order->status, array( 'pending', 'processing', 'awaiting' ) ) ) {
				wp_redirect( $this->get_return_url( $order ) );
			} else {
				$this->woopay_payment_failed( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
			}
		}

		private function do_mobile_response( $params ) {
			if ( empty( $params[ 'Moid' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= $params[ 'Moid' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Mobile response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting mobile response process.', $this->woopay_domain ), $orderid );

			$tid				= $params[ 'TID' ];
			$amt				= $params[ 'Amt' ];
			$PayMethod			= $params[ 'PayMethod' ];
			$MallUserID			= $params[ 'MallUserID' ];
			$GoodsName			= $params[ 'GoodsName' ];
			$Moid				= $params[ 'Moid' ];
			$BuyerName			= $params[ 'BuyerName' ];
			$BuyerTel			= $params[ 'BuyerTel' ];
			$BuyerEmail			= $params[ 'BuyerEmail' ];
			$resultCode			= $params[ 'ResultCode' ];
			$resultMsg			= $params[ 'ResultMsg' ];
			$DstAddr			= isset( $params[ 'DstAddr' ] ) ? $params[ 'DstAddr' ] : '';
			$vbankBankName		= isset( $params[ 'VbankBankName' ] ) ? $params[ 'VbankBankName' ] : '';
			$vbankNum			= isset( $params[ 'VbankNum' ] ) ? $params[ 'VbankNum' ] : '';
			$vbankExpDate		= isset( $params[ 'VbankExpDate' ] ) ? $params[ 'VbankExpDate' ] : '';

			$this->log( __( 'Result Code: ', $this->woopay_domain ) . $resultCode, $orderid );
			$this->log( __( 'Result Message: ', $this->woopay_domain ) . $resultMsg, $orderid );

			$paySuccess = false;

			if ( $PayMethod == 'CARD' ) {
				if ( $resultCode == '3001' ) $paySuccess = true;
			} elseif ( $PayMethod == 'BANK' ) {
				if ( $resultCode == '4000') $paySuccess = true;
			} elseif ( $PayMethod == 'CELLPHONE' ) {
				if ( $resultCode == 'A000' ) $paySuccess = true;
			} elseif ( $PayMethod == 'VBANK' ) {
				if ( $resultCode == '4100' ) $paySuccess = true;
			}

			if ( (int)$amt != (int)$order->get_total() ) {
				$paySuccess = false;

				$this->woopay_payment_integrity_failed( $orderid );

				echo 'FAIL';
				exit;
			}

			if ( $paySuccess == true ) {
				if ( $PayMethod == 'VBANK' ) {
					$this->woopay_payment_awaiting( $orderid, $tid, $PayMethod, $vbankBankName, $vbankNum, $vbankExpDate );
				} else {
					$this->woopay_payment_complete( $orderid, $tid, $PayMethod );
				}

				echo 'OK';
				exit;
			} else {
				$this->woopay_payment_failed( $orderid, $resultCode, $resultMsg );

				echo 'FAIL';
				exit;
			}

			echo 'FAIL';
			exit;
		}

		private function do_cas_response( $params ) {
			if ( empty( $params[ 'MOID' ] ) ) {
				echo 'FAIL';
				exit;
			}

			if ( empty( $params[ 'TID' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= $params[ 'MOID' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'CAS response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting CAS response process.', $this->woopay_domain ), $orderid );

			$tid				= $params[ 'TID' ];
			$moid				= $params[ 'MOID' ];
			$resultCode			= isset( $params[ 'ResultCode' ] ) ? $params[ 'ResultCode' ] : '';
			$resultMsg			= isset( $params[ 'ResultMsg' ] ) ? $params[ 'ResultMsg' ] : '';

			if ( $resultCode == '4110' ) {
				$this->woopay_cas_payment_complete( $orderid, $tid, 'VBANK' );

				echo 'OK';
				exit;
			} else {
				$this->woopay_payment_failed( $orderid, $resultCode, $resultMsg, 'CAS' );

				echo 'FAIL';
				exit;
			}


			exit;
		}

		private function do_refund_request( $params ) {
			if ( ! isset( $params[ 'orderid' ] ) || ! isset( $params[ 'tid' ] ) || ! isset( $params[ 'type' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'orderid' ];
			$tid			= $params[ 'tid' ];

			$woopay_refund = new WooPayNicePayRefund();
			$return = $woopay_refund->do_refund( $orderid, null, __( 'Refund request by customer', $this->woopay_domain ), $tid, 'customer' );

			if ( $return[ 'result' ] == 'success' ) {
				wc_add_notice( $return[ 'message' ], 'notice' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			} else {
				wc_add_notice( $return[ 'message' ], 'error' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			}
			exit;
		}

		private function do_escrow_request( $params ) {
			exit;
		}

		private function do_delete_log( $params ) {
			if ( ! isset( $params[ 'file' ] ) ) {
				$return = array(
					'result' => 'failure',
				);
			} else {
				$file = trailingslashit( WC_LOG_DIR ) . $params[ 'file' ];

				if ( file_exists( $file ) ) {
					unlink( $file );
				}

				$return = array(
					'result' => 'success',
					'message' => __( 'Log file has been deleted.', $this->woopay_domain )
				);
			}

			echo json_encode( $return );

			exit;
		}
	}
}