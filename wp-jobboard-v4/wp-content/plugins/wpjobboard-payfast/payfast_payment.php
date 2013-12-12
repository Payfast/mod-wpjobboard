<?php
/**
 * payfast_payment.php
 * 
 * Description of PayFast
 * 
 * Copyright (c) 2009-2012 PayFast (Pty) Ltd
 * 
 * LICENSE:
 * 
 * This payment module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version.
 * 
 * This payment module is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 *  
 * @package PayFast Payment
 * @version 1.0.0
 * 
 * @author     Ron Darby - PayFast
 * @copyright  2009-2012 PayFast (Pty) Ltd
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
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


