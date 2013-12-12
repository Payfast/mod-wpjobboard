<?php

include_once dirname(__FILE__).'/payfast_common.inc';
require_once dirname(__FILE__).'/payfast_admin_controll.php';
class Payment_PayFast extends Wpjb_Payment_Abstract 
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
        if($this->conf('payfast_sandbox')==1)
        {
            $merchant['id'] = '10000100';
            $merchant['key'] = '46f0cd694581a';
        }
        else
        {
            $merchant['id'] = $this->conf('payfast_merchant_id');
            $merchant['key'] = $this->conf('payfast_merchant_key');
        }
        return $merchant;
     }

     public function getTitle() {
       return "PayFast"; 
     }

     public function getForm()
     {        
        return "Config_PayFast";
     }

     private function getUrl()
     {
        $url = $this->conf('payfast_sandbox')==1 ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
        return $url;
     }

     public function bind(array $post, array $get) {
       // this is a good place to set $this->data
       $this->setObject(new Wpjb_Model_Payment($get["id"]));
       parent::bind($post, $get);
     }

     public function processTransaction() {
        $post = $this->_post; // $_POST

        if($post["pf_payment_id"] < 1) {
           throw new Exception("Invalid PayFast Payment ID");
        }
        $pfError = false;
        $pfErrMsg = '';
        $pfDone = false;
        $pfData = array();     
        $pfParamString = '';
                
        define('PF_DEBUG',($this->conf('payfast_debug')==1 ? true : false ) );
        
        pflog( 'PayFast ITN call received' );

        //// Notify PayFast that information has been received
        if( !$pfError && !$pfDone )
        {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }

        //// Get data sent by PayFast
        if( !$pfError && !$pfDone )
        {
            pflog( 'Get posted data' );
        
            // Posted variables from ITN
            $pfData = pfGetData();

            pflog( 'PayFast Data: '. print_r( $pfData, true ) );
        
            if( $pfData === false )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }
       
        //// Verify security signature
        if( !$pfError && !$pfDone )
        {
            pflog( 'Verify security signature' );
        
            // If signature different, log for debugging
            if( !pfValidSignature( $pfData, $pfParamString ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }

        //// Verify source IP (If not in debug mode)
        if( !$pfError && !$pfDone && PF_DEBUG )
        {
            pflog( 'Verify source IP' );
        
            if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }
        
        
        
        //// Verify data received
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
        
        //// Check data against internal order
        if( !$pfError && !$pfDone )
        {
           pflog( 'Check data against internal order' );
          
            // Check order amount
            if( !pfAmountsEqual( $pfData['amount_gross'],$this->_data->payment_sum ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }          
            
        }
        
        if ($pfError) {
            throw new Exception($pfErrMsg);
        }        
        
        //// Check status and update order
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
                    // If unknown status, do nothing (safest course of action)
                break;
            }
          pflog('PayFast ITN Verified Transaction ID: '.$transaction_id.' [' . $_POST['payment_status'] . '] ');   
             
        }
        throw new Exception('Something went wrong with the PayFast ITN'); 
        return false;
     }
     public function render() {
      
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