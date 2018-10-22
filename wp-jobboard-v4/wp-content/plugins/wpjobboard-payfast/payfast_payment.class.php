<?php

include_once dirname( __FILE__ ).'/payfast_common.inc';
require_once dirname( __FILE__ ).'/payfast_admin_controll.php';

class Payment_PayFast extends Wpjb_Payment_Abstract
{
     public function __construct( Wpjb_Model_Payment $data = null )
     {
       $this->_data = $data;
     }

     public function getEngine()
     {
       return "payfast_payment";
     }

     private function getMerchant()
     {
        $merchant = array();
        if( ( $this->conf( 'payfast_sandbox' ) == 1 ) and ( empty( $this->conf( 'payfast_merchant_id' ) ) or ( empty( $this->conf( 'payfast_merchant_key' ) ) ) ) )
        {
            //Default Sandbox account (Recurring)
            $merchant['id'] = '10004002';
            $merchant['key'] = 'q1cd2rdny4a53';
            $merchant['passphrase'] = 'payfast';
        }
        else
        {
            $merchant['id'] = $this->conf( 'payfast_merchant_id' );
            $merchant['key'] = $this->conf( 'payfast_merchant_key' );
            $merchant['passphrase'] = $this->conf( 'payfast_passphrase' );
        }
        return $merchant;
     }

     public function getTitle()
     {
       return "PayFast"; 
     }

     public function getForm()
     {        
        return "Config_PayFast";
     }

     private function getUrl()
     {
        $url = $this->conf( 'payfast_sandbox' ) == 1 ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
        return $url;
     }

     public function bind( array $post, array $get )
     {
       // This is a good place to set $this->data
       $this->setObject( new Wpjb_Model_Payment( $get["id"] ) );
       parent::bind( $post, $get );
     }

     public function processTransaction()
     {
        $post = $this->_post; // $_POST

        if( $post["pf_payment_id"] < 1 )
        {
           throw new Exception( "Invalid PayFast Payment ID" );
        }
        $pfError = false;
        $pfErrMsg = '';
        $pfDone = false;
        $pfData = array();     
        $pfParamString = '';
                
        define( 'PF_DEBUG',( $this->conf( 'payfast_debug' ) == 1 ? true : false ) );
        
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

            $merchant = $this->getMerchant();
            $passphrase = $merchant['passphrase'];
            $pfPassPhrase = empty( $passphrase ) ? null : $passphrase;
        
            // If signature different, log for debugging
            if( !pfValidSignature( $pfData, $pfParamString, $pfPassPhrase ) )
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
        
        if ( $pfError )
        {
            throw new Exception( $pfErrMsg );
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
                     throw new Exception( 'PayFast ITN Verified Transaction ID: '.$transaction_id.' [' . $_POST['payment_status'] . '] ' );
                    break;     
                default:
                    // If unknown status, do nothing (safest course of action)
                break;
            }
          pflog( 'PayFast ITN Verified Transaction ID: '.$transaction_id.' [' . $_POST['payment_status'] . '] ' );
        }
        throw new Exception( 'Something went wrong with the PayFast ITN' );
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
           
        $product = str_replace( "{num}", $this->_data->getId(), __( "Job Board order #{num} at: ", "wpjobboard" ) );
        $product.= get_bloginfo( "name" );

        $merchant = $this->getMerchant();

        $html = "";
        $html .= '<form action="https://'.$this->getUrl().'/eng/process" method="post">';

        $varArray = array(
            'merchant_id'=>$merchant['id'],
            'merchant_key'=>$merchant['key'],
            'return_url'=> wpjb_link_to( "employer_panel" ),
            'cancel_url'=> home_url(),
            'notify_url'=> admin_url( 'admin-ajax.php' )."?".http_build_query( $arr ),
            'm_payment_id'=>$this->_data->getId(),
            'amount'=>$this->_data->payment_sum-$this->_data->payment_paid,
            'item_name'=>$product,
            'custom_str1'=>constant( 'PF_MODULE_NAME' ).'_'.constant( 'PF_SOFTWARE_VER' ).'_'.constant( 'PF_MODULE_VER' )
        );
        $secureString = '';
        foreach( $varArray as $k=>$v )
        {
            $html.= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
            $secureString .= $k .'=' . urlencode( stripslashes( trim( $v ) ) ) . '&';
        }

        $passphrase = $merchant['passphrase'];
        if ( empty( $passphrase ) )
        {
            $secureString = substr( $secureString, 0, -1 );
        }
        else
        {
            $secureString = $secureString . "passphrase=" . urlencode( trim( $passphrase ) );
        }

        $secureSig = md5( $secureString );
        $userAgent = 'WP-Jobboard 4.x';
        
        $html .= '<input type="hidden" name="signature" value="'.$secureSig.'" />';
        $html .= '<input type="hidden" name="user_agent" value="'.$userAgent.'" />';
        $html .= '<div><p><strong>Pay now with:</strong></p><input title="Click Here to Pay" type="image" src="https://www.payfast.co.za/images/logo/PayFast_Logo_150.png" align="bottom" style="width:initial;"/></div>';
        $html .= '</form>';
        return $html;
     }
}

?>