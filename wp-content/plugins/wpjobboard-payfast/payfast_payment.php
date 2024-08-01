<?php
/**
 * payfast_payment.php
 *
 * @package Payfast Payment
 * @version 2.2.0
 *
 * @author {Payfast}
 */

/*
Plugin Name: Payfast WPJB
Plugin URI: https://payfast.io/integration/plugins/wp-job-board/
Description: This plugin is to integrate WP Job Board v4 with the Payfast Payment system. Please contact Payfast for assistance, support@payfast.help
Author: Payfast
Author URI: https://payfast.io/
Version: 2.2.0
*/

function wpjb_payment_payfast($list)
{
    global $wpjobboard;

    include_once dirname(__FILE__) . "/payfast_payment.class.php";
    $payfast = new Payment_Payfast;
    // registers new payment method
    $list[$payfast->getEngine()] = get_class($payfast);

    return $list;
}

add_filter('wpjb_payments_list', 'wpjb_payment_payfast');

add_filter('wpjb_list_currency', 'zar_wpjb_list_currency');
function zar_wpjb_list_currency($list)
{
    $list[] = array(
        'code'    => 'ZAR',
        'name'    => __('South African Rand', "wpjobboard"),
        'symbol'  => 'R',
        'decimal' => 2
    );

    return $list;
}
