<?php
/**
 * payfast_payment.class.php
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
include('payfast_common.inc');
class Wpjb_Payment_PayFast extends Wpjb_Payment_Abstract 
{

 public function __construct(Wpjb_Model_Payment $data = null) 
 {
   $this->_data = $data; 
 }
 public function getEngine() {
   return "payfast_payment";
 }
 private function getMerchant()
 {
    $merchant = array();
    if(get_option('payfast_sandbox'))
    {
        $merchant['id'] = '10000100';
        $merchant['key'] = '46f0cd694581a';
    }
    else
    {
        $merchant['id'] = get_option('payfast_merchant_id');
        $merchant['key'] = get_option('payfast_merchant_key');
    }
    return $merchant;
 }

 public function getTitle() 
 {
   return "PayFast"; 
 }

 private function getUrl()
 {
    $url = get_option('payfast_sandbox') ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
    return $url;
 }

 public function bind(array $post, array $get) 
 {
   $this->setObject(new Wpjb_Model_Payment($get["id"]));
   parent::bind($post, $get);
 }

 public function processTransaction() 
 {
    $post = $this->_post; 
    if($post["pf_payment_id"] < 1) 
    {
       throw new Exception("Invalid PayFast Payment ID");
    }
    $pfError = false;
    $pfErrMsg = '';
    $pfDone = false;
    $pfData = array();     
    $pfParamString = '';
            
    define('PF_DEBUG',get_option('payfast_debug'));
    
    pflog( 'PayFast ITN call received' );
   
    if( !$pfError && !$pfDone )
    {
        header( 'HTTP/1.0 200 OK' );
        flush();
    }

    if( !$pfError && !$pfDone )
    {
        pflog( 'Get posted data' );    
       
        $pfData = pfGetData();

        pflog( 'PayFast Data: '. print_r( $pfData, true ) );
    
        if( $pfData === false )
        {
            $pfError = true;
            $pfErrMsg = PF_ERR_BAD_ACCESS;
        }
    }

    if( !$pfError && !$pfDone )
    {
        pflog( 'Verify security signature' );
    
       
        if( !pfValidSignature( $pfData, $pfParamString ) )
        {
            $pfError = true;
            $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
        }
    }
  
    if( !$pfError && !$pfDone && PF_DEBUG )
    {
        pflog( 'Verify source IP' );
    
        if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
        {
            $pfError = true;
            $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
        }
    }
    

    if( !$pfError )
    {
        pflog( 'Verify data received' );
    
        $pfValid = pfValidData( $this->getUrl(), $pfParamString );
    
        if( !$pfValid )
        {
            $pfError = true;
            $pfErrMsg = PF_ERR_BAD_ACCESS;
        }
    }
    
    if( !$pfError && !$pfDone )
    {
       pflog( 'Check data against internal order' );      
        
        if( !pfAmountsEqual( $pfData['amount_gross'],$this->_data->payment_sum ) )
        {
            $pfError = true;
            $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
        }          
        
    }
    
    if ($pfError) {
        throw new Exception($pfErrMsg);
    }        
   
    if( !$pfError && !$pfDone )
    {
        pflog( 'Check status and update order' );

        
        $transaction_id = $pfData['pf_payment_id'];
        $comments = '';
        switch( $pfData['payment_status'] )
        {
            case 'COMPLETE':   
                    return array(
                        "external_id" => $transaction_id,
                        "paid" => $post["amount_gross"]
                    );                                                 
                break;    
            case 'FAILED':
                 throw new Exception('PayFast ITN Verified Transaction ID: '.$transaction_id.' [' . $_POST['payment_status'] . '] ');
                break;     
            default:
               return false;
            break;
        }
      pflog('PayFast ITN Verified Transaction ID: '.$transaction_id.' [' . $_POST['payment_status'] . '] ');   
         
    }
    throw new Exception('Something went wrong with the PayFast ITN'); 
    return false;
 }
 public function render() 
 {
  
    $data = $this->_data;
    $arr = array(
            "action" => "wpjb_payment_accept",
            "engine" => $this->getEngine(),
            "id" => $this->_data->id
        );       
       
    $product = str_replace("{num}", $this->_data->getId(), __("Job Board order #{num} at: ", "wpjobboard"));
    $product.= get_bloginfo("name");

    $merchant = $this->getMerchant();

    $html = "";
    $html .= '<form action="https://'.$this->getUrl().'/eng/process" method="post">';
    
    $varArray = array(
        'merchant_id'=>$merchant['id'],
        'merchant_key'=>$merchant['key'],
        'return_url'=> wpjb_link_to("step_complete", $this->_data),
        'cancel_url'=> home_url(),
        'notify_url'=> admin_url('admin-ajax.php')."?".http_build_query($arr),
        'm_payment_id'=>$this->_data->getId(),
        'amount'=>$this->_data->payment_sum-$this->_data->payment_paid,
        'item_name'=>$product
    );
    $secureString = '';
    foreach($varArray as $k=>$v)
    {
        $html.= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
        $secureString .= $k.'='.urlencode($v).'&';
    }
   
    $secureString = substr( $secureString, 0, -1 );
    $secureSig = md5($secureString);
    
    $html .= '<input type="hidden" name="signature" value="'.$secureSig.'" />';
    $html .= '<div style="float:right;">Pay now with:&nbsp;<input title="Click Here to Pay" type="image" src="https://www.payfast.co.za/images/logo/PayFast_Logo_75.png" align="bottom" /></div>';
    $html .= '</form>';
    return $html;
 }
}

?>