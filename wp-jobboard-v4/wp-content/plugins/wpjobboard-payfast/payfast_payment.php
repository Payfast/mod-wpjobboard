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
Description: This plugin is to integrate WP Job Board v4 with the PayFast Payment system. Please contact PayFast for assistance: support@payfast.co.za
Author: Ron Darby
Version: 1.0.0
Author URI: https://www.payfast.co.za
*/

function wpjb_payment_payfast($list) 
{
  global $wpjobboard;
  include_once dirname(__FILE__)."/payfast_payment.class.php";
  $payfast = new Wpjb_Payment_PayFast;
  $list[$payfast->getEngine()] = get_class($payfast);
  return $list;
}

function payfast_load_menu() 
{
  add_menu_page( 'PayFast Payment', 'PayFast Payment', 'manage_options', 'payfast_conf','payfast_conf');     
}

function payfast_conf()
{
    if(!empty($_POST))
    {
      foreach($_POST as $k=>$v)
      {
        update_option($k,$v);        
      }      
    }
    ?>
    <h2>PayFast Payment Settings</h2>
    <table>
      <form action='' method='post'>
        <tr>
          <td>
            Merchant ID:
          </td>
          <td>
            <input type="text" name="payfast_merchant_id" value="<?php echo get_option('payfast_merchant_id'); ?>">
          </td>
        </tr>
        <tr>
          <td>
            Merchant Key:
          </td>
          <td>
            <input type="text" name="payfast_merchant_key" value="<?php echo get_option('payfast_merchant_key'); ?>">
          </td>
        </tr>
        <tr>
          <td>
            Mode:
          </td>
          <td>
            <select name="payfast_sandbox">
              <option value="1" <?php if(get_option('payfast_sandbox')){echo "selected";} ?> >Sandbox/Test</option>
              <option value="0" <?php if(!get_option('payfast_sandbox')){echo "selected";} ?> >Live</option>
            </select>
          </td>
        </tr>
        <tr>
          <td>
            Debug:
          </td>
          <td>
            <select name="payfast_debug">
              <option value="1" <?php if(get_option('payfast_debug')){echo "selected";}?> >Yes</option>
              <option value="0" <?php if(!get_option('payfast_debug')){echo "selected";} ?> >No</option>
            </select>
          </td>
        </tr>
        <tr>
          <td></td>
          <td>
            <input type="submit" value="Save Settings">
          </td>
        </tr>
      </form>
    </table>
    <?php
}

add_filter('wpjb_payments_list', 'wpjb_payment_payfast');
add_action( 'admin_menu', 'payfast_load_menu' );
