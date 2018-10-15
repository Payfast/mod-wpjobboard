<?php
/**
 * payfast_payment.php
 * 
 * @author     Ron Darby, Cate Faull - PayFast
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


