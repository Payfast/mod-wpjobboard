<?php
/**
 * payfast_payment.php
 * 
 * Description of PayFast
 * 
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 *  
 * @package PayFast Payment
 * @version 1.0.0
 * 
 * @author     Ron Darby - PayFast
 */
/*
Plugin Name: PayFast WPJB v4 Payment Plugin
Plugin URI: https://www.payfast.co.za/s/std/wp_job_board
Description: This plugin is to integrate WP Job Board v4 with the PayFast Payment system. Please contact PayFast for assistance, support@payfast.co.za
Author: Ron Darby
Version: 1.0.0
Author URI: https://www.payfast.co.za
*/

function wpjb_payment_payfast($list) {
  global $wpjobboard;

  include_once dirname(__FILE__)."/payfast_payment.class.php";
  $payfast = new Payment_PayFast;
  // registers new payment method
  $list[$payfast->getEngine()] = get_class($payfast);
  return $list;
}
add_filter('wpjb_payments_list', 'wpjb_payment_payfast');


