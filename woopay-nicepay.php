<?php
/*
Plugin Name: WooPay - NicePay
Plugin URI: http://www.planet8.co/
Description: Korean Payment Gateway integrated with NicePay for WooCommerce.
Version: 1.1.5
Author: Planet8
Author URI: http://www.planet8.co/
Copyright : Planet8 proprietary.
Developer : Thomas ( thomas@planet8.co )
Text Domain : woopay-nicepay
Domain Path : /languages/
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

// Load Translations
load_plugin_textdomain( 'woopay-nicepay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
load_plugin_textdomain( 'woopay-core', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

// Load WooPay Core
include_once( 'includes/abstracts/abstract-woopay-core.php' );

if ( ! class_exists( 'WooPayNicePay' ) ) {
	class WooPayNicePay extends WooPayCore {
		public $woopay_plugin_name;
		public $woopay_api_name;
		public $woopay_domain;
		public $woopay_plugin_url;
		public $woopay_log_dir;

		public $log_enabled;
		public $log = false;

		public function __construct() {
			$upload_dir						= wp_upload_dir();
			$this->woopay_plugin_name		= 'woopay-nicepay';
			$this->woopay_plugin_nice_name	= 'WooPay - NicePay';
			$this->woopay_plugin_basedir	= dirname( __FILE__ );
			$this->woopay_plugin_basename	= plugin_basename( __FILE__ );
			$this->woopay_plugin_filename	= __FILE__;
			$this->woopay_api_name			= 'woopay_nicepay';
			$this->woopay_domain			= 'woopay-nicepay';
			$this->woopay_plugin_url		= plugins_url( '/', __FILE__ );
			$this->woopay_upload_dir		= $this->get_upload_dir();
			$this->woopay_section			= 'woopaynicepaycard';

			$this->log_enabled				= ( $this->log_enabled == 'yes' ) ? true : false;

			$this->includes();

			register_activation_hook( __FILE__, array( $this, 'set_activation' ) );
		}

		public function get_woopay_settings() {
			// General Settings
			$this->enabled			= $this->get_option( 'enabled' );
			$this->testmode			= ( $this->get_option( 'testmode' ) == 'yes' ) ? true : false;
			$this->log_enabled		= ( $this->get_option( 'log_enabled' ) == 'yes' ) ? true : false;
			$this->title			= $this->get_option( 'title' );
			$this->description		= $this->get_option( 'description' );
			$this->MID				= $this->get_option( 'MID' );
			$this->MerchantKey		= $this->get_option( 'MerchantKey' );
			$this->CancelKey		= $this->get_option( 'CancelKey' );
			$this->escw_yn			= $this->get_option( 'escw_yn' );
			$this->ConfirmMail		= $this->get_option( 'ConfirmMail' );
			$this->expiry_time		= $this->get_option( 'expiry_time' );
			$this->GoodsCl          = $this->get_option( 'GoodsCl' );

			// Design Settings
			$this->LogoImage		= $this->get_option( 'LogoImage' );
			$this->BgImage			= $this->get_option( 'BgImage' );
			$this->skintype			= $this->get_option( 'skintype' );
			$this->checkout_txt		= $this->get_option( 'checkout_txt' );
			$this->checkout_img		= $this->get_option( 'checkout_img' );
			$this->show_chrome_msg	= $this->get_option( 'show_chrome_msg' );

			// Refund Settings
			$this->refund_btn_txt	= $this->get_option( 'refund_btn_txt' );
			$this->customer_refund	= $this->get_option( 'customer_refund' );
		}

		public function includes() {
			// Classes
			include_once( 'includes/class-woopay-logger.php' );
			include_once( 'includes/class-woopay-nicepay-base.php' );
			include_once( 'includes/class-wooshipping.php' );
		}

		public function set_activation( $network_wide ) {
			if ( is_multisite() && $network_wide ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( sprintf( __( '<strong>%s</strong> has been disabled. This plugin can only be activated per site.<br/><br/><a href="javascript:history.go(-1);">Go Back</a>', $this->woopay_domain ), $this->woopay_plugin_nice_name ) );
			} else {
				if ( get_option( $this->woopay_api_name . '_logfile' ) == '' ) {
					update_option( $this->woopay_api_name . '_logfile', $this->get_random_string( 'log' ) );
				}

				if ( ! $this->woopay_check_permission() ) {
					deactivate_plugins( plugin_basename( __FILE__ ) );
					wp_die( sprintf( __( '<strong>%s</strong> has been disabled. Please check your permissions (at least 0755) and owner/group of your plugin folder:<br/><br/><code>%s</code><br/><br/>This plugin needs write permission to create files needed for payment. Try re-installing the plugin from the WordPress plugin screen directly.<br/><br/>For more information, please visit: <a href="http://www.planet8.co/faq/" target="_blank">http://www.planet8.co/faq/</a><br/><br/><a href="javascript:history.go(-1);">Go Back</a>', $this->woopay_domain ), $this->woopay_plugin_nice_name, $this->woopay_plugin_basedir ) );
				}

				if ( ! function_exists( 'mcrypt_encrypt' ) ) {
					deactivate_plugins( plugin_basename( __FILE__ ) );
					wp_die( sprintf( __( '<strong>%s</strong> has been disabled.<br/><br/>This plugin needs the <a href="http://php.net/manual/en/book.mcrypt.php" target="_blank">mcrypt</a> extension. Please check your PHP configuration.<br/><br/>For more information, please visit: <a href="http://www.planet8.co/faq/" target="_blank">http://www.planet8.co/faq/</a><br/><br/><a href="javascript:history.go(-1);">Go Back</a>', $this->woopay_domain ), $this->woopay_plugin_nice_name, $this->woopay_plugin_basedir ) );
				}

				// Cron Events
				$duration = 120;
				wp_clear_scheduled_hook( 'woopay_nicepay_virtual_bank_schedule' );
				wp_schedule_single_event( time() + ( absint( $duration ) * 60 ), 'woopay_nicepay_virtual_bank_schedule' );
			}
		}

		public function set_deactivation() {
			wp_clear_scheduled_hook( 'woopay_nicepay_virtual_bank_schedule' );
		}

		public function woopay_check_permission() {
			try {
				if ( @file_put_contents( $this->woopay_plugin_basedir . '/tmp_file', 'CHECK', FILE_APPEND ) ) {
					@unlink( $this->woopay_plugin_basedir . '/tmp_file' );
					return true;
				} else {
					throw new Exception( 'Failed to write file' );
				}
			} catch( Exception $e ) {
				return false;
			}
		}
	}

	return new WooPayNicePay();
}