<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayNicePayPayment' ) ) {
	class WooPayNicePayPayment extends WooPayNicePay {
		public $title_default;
		public $desc_default;
		public $default_checkout_img;
		public $allowed_currency;
		public $allow_other_currency;
		public $allow_testmode;

		function __construct() {
			parent::__construct();

			$this->method_init();
			$this->init_settings();
			$this->init_form_fields();

			$this->get_woopay_settings();

			// Actions
			add_action( 'wp_enqueue_scripts', array( $this, 'pg_scripts' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_virtual_information' ) );
			add_action( 'woocommerce_view_order', array( $this, 'get_virtual_information' ), 9 );

			if ( isset( $this->method ) ) {
				if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
					if ( ! $this->allow_other_currency ) {
						$this->enabled = 'no';
					}
				}

				if ( ! $this->testmode ) {
					if ( $this->MID == '' || $this->MerchantKey == '' || $this->CancelKey == '' ) {
						$this->enabled = 'no';
					}
				} else {
					$this->title		= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->title;
					$this->description	= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->description;
				}
			}
		}

		function method_init() {
		}

		function pg_scripts() {
			if ( is_checkout() ) {
				if ( ! $this->check_mobile() ) {
					$script_url = 'https://web.nicepay.co.kr/flex/js/nicepay_tr_utf.js';
					wp_register_script( 'nicepay_script', $script_url, array( 'jquery' ), '1.0.0', false );
					wp_enqueue_script( 'nicepay_script' );
				}
			}
		}

		function receipt( $orderid ) {
			$order = new WC_Order( $orderid );

			if ( $this->checkout_img ) {
				echo '<div class="p8-checkout-img"><img src="' . $this->checkout_img . '"></div>';
			}

			echo '<div class="p8-checkout-txt">' . str_replace( "\n", '<br>', $this->checkout_txt ) . '</div>';

			if ( $this->show_chrome_msg == 'yes' ) {
				if ( $this->get_chrome_version() >= 42 && $this->get_chrome_version() < 45 ) {
					echo '<div class="p8-chrome-msg">';
					echo __( 'If you continue seeing the message to install the plugin, please enable NPAPI settings by following these steps:', $this->woopay_domain );
					echo '<br>';
					echo __( '1. Enter <u>chrome://flags/#enable-npapi</u> on the address bar.', $this->woopay_domain );
					echo '<br>';
					echo __( '2. Enable NPAPI.', $this->woopay_domain );
					echo '<br>';
					echo __( '3. Restart Chrome and refresh this page.', $this->woopay_domain );
					echo '</div>';
				}
			}

			$currency_check = $this->currency_check( $order, $this->allowed_currency );

			if ( $currency_check ) {
				echo $this->woopay_form( $orderid );
			} else {
				$currency_str = $this->get_currency_str( $this->allowed_currency );

				echo sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_post_meta( $order->id, '_order_currency', true ), $currency_str );
			}
		}

		function get_woopay_args( $order ) {
			$orderid = $order->id;

			$this->billing_phone = $order->billing_phone;

			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					if ( $item[ 'qty' ] ) {
						$item_name = $item[ 'name' ];
					}
				}
			}

			$timestamp = $this->get_timestamp();

			$Amt			= (int)$order->order_total;
			$MID			= ( $this->testmode ) ? 'nictest00m' : $this->MID;
			$MerchantKey	= ( $this->testmode ) ? '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R++d5hDd3Frmuos/XLx8XhXpe+LDYAbpGKZYSwtlyyLOtS/8aD7A==' : $this->MerchantKey;

			if ( ! $this->check_mobile() ) {
				$nicepay_args =
					array(
						'PayMethod'				=> $this->method,
						'GoodsCnt'				=> '1',
						'GoodsName'				=> sanitize_text_field( $item_name ),
						'Amt'					=> $Amt,
						'MID'		 			=> $MID,
						'MerchantKey'			=> $MerchantKey,
						'BuyerName'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'BuyerTel'				=> $order->billing_phone,
						'UserIP'				=> $this->get_client_ip(),
						'MallIP'				=> $this->get_server_ip(),
						'EncodeParameters'		=> '',
						'SocketYN'				=> 'Y',
						'EdiDate'				=> $this->get_hashdata( $MerchantKey, $MID, $Amt, 'date' ),
						'EncryptData'			=> $this->get_hashdata( $MerchantKey, $MID, $Amt, 'encrypt' ),
						'GoodsCl'				=> $this->GoodsCl,
						'Moid'					=> $order->id,
						'BuyerAuthNum'			=> '',
						'BuyerEmail'			=> $order->billing_email,
						'ParentEmail'			=> '',
						'BuyerAddr'				=> $order->billing_address_1,
						'BuyerPostNo'			=> '',
						'SUB_ID'				=> '',
						'MallUserID'			=> '',
						'VbankExpDate'			=> $this->get_expirytime( $this->expiry_time, 'Ymd' ),
						'SkinType'				=> $this->skintype,
						'TrKey'					=> '',
						'TransType'				=> ( $this->escw_yn=='yes' ) ? '1' : '0',
						//'SelectQuota'			=> '00',
						'LogoImage'				=> $this->LogoImage,
						'BgImage'				=> $this->BgImage,
						'checkout_url'			=> WC()->cart->get_checkout_url(),
						'testmode'				=> $this->testmode,
						'response_url'			=> $this->get_api_url( 'response' ),
					);
			} else {
				$nicepay_args =
					array(
						'PayMethod'				=> $this->method,
						'GoodsCnt'				=> '1',
						'Moid'					=> $order->id,
						'BuyerTel'				=> $order->billing_phone,
						'BuyerEmail'			=> $order->billing_email,
						'BuyerAddr'				=> $order->billing_address_1,
						'VbankExpDate'			=> $this->get_expirytime( $this->expiry_time, 'Ymd' ),
						'MallReserved'			=> '',
						'ReturnURL'				=> $this->get_api_url( 'mobile_return' ),
						'RetryURL'				=> $this->get_api_url_http( 'mobile_response' ),
						'GoodsCl'				=> $this->GoodsCl,
						'CharSet'				=> 'utf-8',
						'MerchantKey'			=> $MerchantKey,
						'Amt'					=> $Amt,
						'GoodsName'				=> sanitize_text_field( $item_name ),
						'BuyerName'				=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'MID'		 			=> $MID,
						'EncryptData'			=> $this->get_hashdata( $MerchantKey, $MID, $Amt, 'encrypt' ),
						'EdiDate'				=> $this->get_hashdata( $MerchantKey, $MID, $Amt, 'date' ),
						'MallUserID'			=> '',
						'SelectQuota'			=> '00',
						'checkout_url'			=> WC()->cart->get_checkout_url(),
						'testmode'				=> $this->testmode,
					);
			}

			$nicepay_args = apply_filters( 'woocommerce_nicepay_args', $nicepay_args );

			return $nicepay_args;
		}

		function get_hashdata( $MerchantKey, $MID, $Amt, $arg ) {
			$date = $this->get_timestamp( 'YmdHis' );

			if ( $arg == 'date' ) {
				return $date;
			} elseif ( $arg == 'encrypt' ) {
				// gaegoms / 2017-01-17 / 보안권고사항 적용
				// return base64_encode( md5( $date . $MID . $Amt . $MerchantKey ) );
				$source = $date . $MID . $Amt . $MerchantKey;
				return bin2hex( hash( 'sha256', $source, true ) );
			} else {
				return "";
			}
		}

		function woopay_form( $orderid ) {
			$order = new WC_Order( $orderid );

			$nicepay_args = $this->get_woopay_args( $order );

			$nicepay_args_array = array();

			foreach ( $nicepay_args as $key => $value ) {
				//$nicepay_args_array[] = esc_attr( $key ).'<input type="text" style="width:150px;" id="'.esc_attr( $key ).'" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" /><br>';
				$nicepay_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" id="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			if ( ! $this->check_mobile() ) {
				$woopay_form = "<form method='post' id='order_info' name='order_info'>" . implode( '', $nicepay_args_array ) . "</form>";
			} else {
				$woopay_form = "<form method='post' id='order_info' name='order_info' accept-charset='EUC-KR'>" . implode( '', $nicepay_args_array ) . "</form>";
			}

			if ( ! $this->check_mobile() ) {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay.js';
			} else {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay-mobile.js';
			}

			wp_register_script( $this->woopay_api_name . 'woopay_script', $woopay_script_url, array( 'jquery' ), '1.0.0', true );

			$translation_array = array(
				'testmode_msg'	=> __( 'Test mode is enabled. Continue?', $this->woopay_domain ),
				'cancel_msg'	=> __( 'You have cancelled your transaction. Returning to cart.', $this->woopay_domain ),
				'method_msg'	=> __( 'You cannot use this payment method for amounts less than 500 Won.', $this->woopay_domain ),
				'name_msg'		=> __( 'Please enter more than 2 characters for your name', $this->woopay_domain ),
			);

			wp_localize_script( $this->woopay_api_name . 'woopay_script', 'woopay_string', $translation_array );
			wp_enqueue_script( $this->woopay_api_name . 'woopay_script' );

			return $woopay_form;
		}

		public function process_payment( $orderid ) {
			$order = new WC_Order( $orderid );

			$this->woopay_start_payment( $orderid );

			if ( ! $this->check_mobile() ) {
				$nicepay_args = $this->get_woopay_args( $order );

				require_once $this->woopay_plugin_basedir . '/bin/lib/NicepayLite.php';

				$nicepay = new NicepayLite;

				$MID						= ( $this->testmode ) ? 'nictest00m' : $this->MID;
				$MerchantKey				= ( $this->testmode ) ? '33F49GnCMS1mFYlGXisbUDzVf2ATWCl9k3R++d5hDd3Frmuos/XLx8XhXpe+LDYAbpGKZYSwtlyyLOtS/8aD7A==' : $this->MerchantKey;

				$nicepay->m_MID				= $MID;
				$nicepay->m_MerchantKey		= $MerchantKey;
				$nicepay->m_EdiDate			= $this->get_timestamp();
				$nicepay->m_Price			= $nicepay_args[ 'Amt' ];

				$nicepay->requestProcess();
			}

			if ( $this->testmode ) {
				wc_add_notice( __( '<strong>Test mode is enabled!</strong> Please disable test mode if you aren\'t testing anything.', $this->woopay_domain ), 'error' );
			}

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}

		public function process_refund( $orderid, $amount = null, $reason = '' ) {
			$woopay_refund = new WooPayNicePayRefund();
			$return = $woopay_refund->do_refund( $orderid, $amount, $reason );

			if ( $return[ 'result' ] == 'success' ) {
				return true;
			} else {
				return false;
			}
		}

		function admin_options() {
			$currency_str = $this->get_currency_str( $this->allowed_currency );

			echo '<h3>' . $this->method_title . '</h3>';

			$this->get_woopay_settings();

			$hide_form = "";

			if ( ! $this->woopay_check_api() ) {
				echo '<div class="inline error"><p><strong>' . sprintf( __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please check your permalink settings. You must use a permalink structure other than \'General\'. Click <a href="%s">here</a> to change your permalink settings.', $this->woopay_domain ), $this->get_url( 'admin', 'options-permalink.php' ) ) . '</p></div>';

				$hide_form = "display:none;";
			} else {
				if ( ! $this->testmode ) {
					if ( $this->MID == '' ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Merchant ID.', $this->woopay_domain ). '</p></div>';
					} else if ( $this->MerchantKey == '' ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Merchant Key.', $this->woopay_domain ). '</p></div>';
					} else if ( $this->CancelKey == '' ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Cancel Key.', $this->woopay_domain ). '</p></div>';
					}
				} else {
					echo '<div class="inline error"><p><strong>' . __( 'Test mode is enabled!', $this->woopay_domain ) . '</strong> ' . __( 'Please disable test mode if you aren\'t testing anything', $this->woopay_domain ) . '</p></div>';
				}
			}

			if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
				if ( ! $this->allow_other_currency ) {
					echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
				} else {
					echo '<div class="inline notice notice-info"><p><strong>' . __( 'Please Note', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not recommended by this payment method. This payment method recommeds the following currency: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
				}
			}

			echo '<div id="' . $this->woopay_plugin_name . '" style="' . $hide_form . '">';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
			echo '</div>';
		}

		function init_form_fields() {
			// General Settings
			$general_array = array(
				'general_title' => array(
					'title' => __( 'General Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'enabled' => array(
					'title' => __( 'Enable/Disable', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable this method.', $this->woopay_domain ),
					'default' => 'yes'
				),
				'testmode' => array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable test mode.', $this->woopay_domain ),
					'description' => '',
					'default' => 'no'
				),
				'log_enabled' => array(
					'title' => __( 'Enable/Disable Logs', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable logging.', $this->woopay_domain ),
					'description' => __( 'Logs will be automatically created when in test mode.', $this->woopay_domain ),
					'default' => 'no'
				),
				'log_control' => array(
					'title' => __( 'View/Delete Log', $this->woopay_domain ),
					'type' => 'log_control',
					'description' => '',
					'desc_tip' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Title that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->title_default,
				),
				'description' => array(
					'title' => __( 'Description', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Description that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->desc_default,
				),					
				'MID' => array(
					'title' => __( 'Merchant ID', $this->woopay_domain ),
					'type' => 'text',
					'class' => 'nicepay_mid',
					'description' => __( 'Please enter your Merchant ID.', $this->woopay_domain ),
					'default' => ''
				),
				'MerchantKey' => array(
					'title' => __( 'Merchant Key', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Merchant Key.', $this->woopay_domain ),
					'default' => ''
				),
				'CancelKey' => array(
					'title' => __( 'Cancel Key', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Cancel Key. Default is: <code>1111</code>.', $this->woopay_domain ),
					'default' => ''
				),
				'GoodsCl' => array(
					'title' => __( 'Product Type', $this->woopay_domain ),
					'type' => 'select',
					'description' => __( 'Select the product types of your shop.', $this->woopay_domain ),
					'options' => array(
					    '1' => __( 'Real Product', $this->woopay_domain ),
					    '0' => __( 'Virtual Product', $this->woopay_domain )
					),
					'default' => '1'
				),
				'expiry_time' => array(
					'title' => __( 'Expiry time in days', $this->woopay_domain ),
					'type'=> 'select',
					'description' => __( 'Select the virtual account transfer expiry time in days.', $this->woopay_domain ),
					'options'	=> array(
						'1'			=> __( '1 day', $this->woopay_domain ),
						'2'			=> __( '2 days', $this->woopay_domain ),
						'3'			=> __( '3 days', $this->woopay_domain ),
						'4'			=> __( '4 days', $this->woopay_domain ),
						'5'			=> __( '5 days', $this->woopay_domain ),
						'6'			=> __( '6 days', $this->woopay_domain ),
						'7'			=> __( '7 days', $this->woopay_domain ),
						'8'			=> __( '8 days', $this->woopay_domain ),
						'9'			=> __( '9 days', $this->woopay_domain ),
						'10'		=> __( '10 days', $this->woopay_domain ),
					),
					'default' => ( '5' ),
				),
				'escw_yn' => array(
					'title' => __( 'Escrow Settings', $this->woopay_domain ),
					'type' => 'checkbox',
					'description' => __( 'Force escrow settings.', $this->woopay_domain ),
					'default' => 'no',
				)
			);

			// Refund Settings
			$refund_array = array(
				'refund_title' => array(
					'title' => __( 'Refund Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'refund_btn_txt' => array(
					'title' => __( 'Refund Button Text', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Text for refund button that users will see.', $this->woopay_domain ),
					'default' => __( 'Refund', $this->woopay_domain ),
				),
				'customer_refund' => array (
					'title' => __( 'Refundable Satus for Customer', $this->woopay_domain ),
					'type' => 'multiselect',
					'class' => 'chosen_select',
					'description' => __( 'Select the order status for allowing refund.', $this->woopay_domain ),
					'options' => $this->get_status_array(),
				)
			);

			// Design Settings
			$design_array = array(
				'design_title' => array(
					'title' => __( 'Design Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'skintype' => array(
					'title' => __( 'Skin Type', $this->woopay_domain ),
					'type' => 'select',
					'description' => __( 'Select the skin type for your NicePay form.', $this->woopay_domain ),
					'options' => array(
						'BLUE' => 'Blue',
						'RED' => 'Red',
						'PURPLE' => 'Purple',
						'GREEN' => 'Green',
					)
				),
				'LogoImage' => array(
					'title' => __( 'Logo Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your logo. The size should be 95*35. You can use GIF/JPG/PNG.', $this->woopay_domain ),
					'default' => '',
					'btn_name' => __( 'Select/Upload Logo', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Logo', $this->woopay_domain ),
					'default_btn_url' => ''
				),
				'BgImage' => array(
					'title' => __( 'Background Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your image for the background of the payment window. The size should be 505*512. You can use GIF/JPG/PNG.', $this->woopay_domain ),
					'default' => '',
					'btn_name' => __( 'Select/Upload Background', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Background', $this->woopay_domain ),
					'default_btn_url' => ''
				),
				'checkout_img' => array(
					'title' => __( 'Checkout Processing Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your image for the checkout processing page. Leave blank to show no image.', $this->woopay_domain ),
					'default' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
					'btn_name' => __( 'Select/Upload Image', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Image', $this->woopay_domain ),
					'default_btn_name' => __( 'Use Default', $this->woopay_domain ),
					'default_btn_url' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
				),	
				'checkout_txt' => array(
					'title' => __( 'Checkout Processing Text', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Text that users will see on the checkout processing page. You can use some HTML tags as well.', $this->woopay_domain ),
					'default' => __( "<strong>Please wait while your payment is being processed.</strong>\nIf you see this page for a long time, please try to refresh the page.", $this->woopay_domain )
				),
				'show_chrome_msg' => array(
					'title' => __( 'Chrome Message', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Show steps to enable NPAPI for Chrome users using less than v45.', $this->woopay_domain ),
					'description' => '',
					'default' => 'yes'
				)
			);

			if ( $this->id == 'nicepay_virtual' ) {
				$general_array = array_merge( $general_array,
					array(
						'callback_url' => array(
							'title' => __( 'Callback URL', $this->woopay_domain ),
							'type' => 'txt_info',
							'txt' => $this->get_api_url( 'cas_response' ),
							'description' => __( 'Callback URL used for payment notice from NicePay.', $this->woopay_domain )
						)
					)
				);
			}

			if ( ! $this->allow_testmode ) {
				$general_array[ 'testmode' ] = array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'txt_info',
					'txt' => __( 'You cannot test this payment method.', $this->woopay_domain ),
					'description' => '',
				);
			}

			if ( $this->id == 'nicepay_mobile' ) {
				unset( $general_array[ 'escw_yn' ] );
				unset( $general_array[ 'ConfirmMail' ] );
				unset( $general_array[ 'customer_decline' ] );
			}

			if ( $this->id != 'nicepay_mobile' ) {
				unset( $general_array[ 'GoodsCl' ] );
			}

			if ( $this->id != 'nicepay_virtual' ) {
				unset( $general_array[ 'expiry_time' ] );
			}

			if ( ! in_array( 'refunds', $this->supports ) ) {
				unset( $refund_array[ 'refund_btn_txt' ] );
				unset( $refund_array[ 'customer_refund' ] );

				$refund_array[ 'refund_title' ][ 'description' ] = __( 'This payment method does not support refunds. You can refund each transaction using the merchant page.', $this->woopay_domain );
			}

			$form_array = array_merge( $general_array, $refund_array );
			$form_array = array_merge( $form_array, $design_array );

			$this->form_fields = $form_array;

			$nicepay_mid_bad_msg = __( 'This Merchant ID is not from Planet8. Please visit the following page for more information: <a href="http://www.planet8.co/woopay-nicepay-change-mid/" target="_blank">http://www.planet8.co/woopay-nicepay-change-mid/</a>', $this->woopay_domain );

			if ( is_admin() ) {
				if ( $this->id != '' ) {
					wc_enqueue_js( "
						function checkNicePay( payment_id, mid ) {
							var bad_mid = '<span style=\"color:red;font-weight:bold;\">" . $nicepay_mid_bad_msg . "</span>';
							var mids = [ \"00planet8m\", \"010990682m\", \"1dayhousem\", \"dromeda01m\", \"ezstreet1m\", \"forwardfcm\", \"gagaa0320m\", \"gaguckr01m\", \"hesedorkrm\", \"imagelab0m\", \"imean0820m\", \"inflab001m\", \"innofilm1m\", \"jiayou505m\", \"lounge105m\", \"masterkr1m\", \"milspec01m\", \"pipnice11m\", \"pting2014m\", \"seopshop1m\", \"urbout001m\" ];

							if ( mid == '' || mid == undefined ) {
								jQuery( '#woocommerce_' + payment_id + '_MID' ).closest( 'tr' ).css( 'background-color', 'transparent' );
								jQuery( '#nicepay_mid_bad_msg' ).html( '' );
							} else {
								if ( mid.substring( 0, 3 ) == 'PLA' ) {
									jQuery( '#woocommerce_' + payment_id + '_MID' ).closest( 'tr' ).css( 'background-color', 'transparent' );
									jQuery( '#nicepay_mid_bad_msg' ).html( '' );
								} else if ( jQuery.inArray( mid, mids ) > 0 ) {
									jQuery( '#woocommerce_' + payment_id + '_MID' ).closest( 'tr' ).css( 'background-color', 'transparent' );
									jQuery( '#nicepay_mid_bad_msg' ).html( '' );
								} else {
									jQuery( '#woocommerce_' + payment_id + '_MID' ).closest( 'tr' ).css( 'background-color', '#FFC1C1' );
									jQuery( '#nicepay_mid_bad_msg' ).html( bad_mid );
								}
							}
						}

						jQuery( '.nicepay_mid' ).on( 'blur', function() {
							var val = jQuery( this ).val();

							checkNicePay( '" . $this->id . "', val );
						});

						jQuery( document ).ready( function() {
							jQuery( '#woocommerce_" . $this->id . "_MID' ).closest( 'td' ).append( '<div id=\"nicepay_mid_bad_msg\"></div>' );

							var val = jQuery( '.nicepay_mid' ).val();

							checkNicePay( '" . $this->id . "', val );
						});
					" );
				}
			}
		}
	}

	return new WooPayNicePayPayment();
}