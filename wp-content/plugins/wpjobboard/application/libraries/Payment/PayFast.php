<?php

/**
 * payfast_common.inc
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
 * @author     Ron Darby - PayFast
 * @copyright  2009-2012 PayFast (Pty) Ltd
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

include('payfast_common.inc'); 
 
class Wpjb_Payment_PayFast implements Wpjb_Payment_Interface
{

    /**
     * PayFast sandbox enviroment
     *
     * @var bool 
     */
    private $sandbox;    
    

    /**
     * List of PayFast currencies
     *
     * @var array
     */
    private static $_currency = array();

    /**
     * Job object
     *
     * @var Wpjb_Model_Job
     */
    private $_data = null;
    
    /**
     * Returned Data From PayFast 
     *
     * @var array 
     */     
    private $pfData = array();

    public function __construct(Wpjb_Model_Payment $data = null)
    {
        self::_init();
        $sandbox = Wpjb_Project::getInstance()->conf("payfast_sandbox");
        
        $this->setEnviroment($sandbox);        
        $this->_data = $data;
    }
    
    private static function _init()
    {
        self::$_currency = Wpjb_List_Currency::getAll();
    }

    protected function _notifyJob($id)
    {
        $job = new Wpjb_Model_Job($id);
        $mod = Wpjb_Project::getInstance()->conf("posting_moderation");
        if(!$mod) {
            $job->is_active = 1;
            $job->is_approved = 1;
            Wpjb_Utility_Messanger::send(2, $job);
        }
        
        $job->payment_paid = $this->pfData["amount_gross"];   
        
        $job->save();
    }

    protected function _notifyResume($id)
    {
        $object = new Wpjb_Model_ResumesAccess($id);
        $emp = new Wpjb_Model_Employer($object->employer_id);
        $emp->addAccess($object->extend);
        $emp->save();
    }
    
    /**
     * Returns array representing given currency
     *
     * @param string $id
     * @return array
     */
    public static function getCurrency($id)
    {
        self::_init();
        if(isset(self::$_currency[$id])) {
            return self::$_currency[$id];
        }
        return array();
    }

    public static function getCurrencySymbol($code, $space = " ")
    {
        $currency = self::getCurrency($code);

        if(!is_null($currency['symbol'])) {
            return $currency['symbol'];
        } else {
            return $currency['code'].$space;
        }
    }
    
     /**
     * getDomain
     *
     * @author Ron Darby
     */
    public function getDomain()
    {
        if($this->sandbox)
        {            
            return "sandbox.payfast.co.za";
        }
        else
        {
            return "www.payfast.co.za";
        }
    }

    public function getEngine()
    {
        return "PayFast";
    }
    
    /**
     * Returns list of available currencies
     *
     * @return ArrayIterator
     */
    public static function getList()
    {
        self::_init();
        return new ArrayIterator(self::$_currency);
    }
  
    /**
     * Returns PayFast Merchant Info.
     * 
     * @author  Ron Darby
     * @return array('merchant_id','merchant_key')
     */
    public function getMerchant()
    {
        if($this->sandbox)
        {
        return array('merchant_id'=>'10000100','merchant_key'=>'46f0cd694581a');    
            
        }else{
        return array('merchant_id'=>Wpjb_Project::getInstance()->conf("payfast_merchant_id"),'merchant_key'=>Wpjb_Project::getInstance()->conf("payfast_merchant_key"));
        }
    }
    

    public function getTitle()
    {
        return "PayFast";
    }
    
    /**
     * Depending on settings return either sandbox or production URL
     *
     * @return string
     */
    public function getUrl()
    {
        return "https://" . $this->getDomain() . "/eng/process";
    }


    /**
     * Procesess PayFast transaction.
     * @author Ron Darby
     * @param array $ppData
     * @return boolean
     */
    public function processTransaction(array $data)
    {
        
        $pfError = false;
        $pfErrMsg = '';
        $pfDone = false;
        $pfData = array();	   
        $pfParamString = '';
				
		define('PF_DEBUG',Wpjb_Project::getInstance()->conf("payfast_debug"));
        
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
            $this->pfData = $pfData;
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
        
            $pfValid = pfValidData( $this->getDomain(), $pfParamString );
        
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
                        $payment = new Wpjb_Model_Payment($pfData['m_payment_id']);
        
                        if($payment->made_at != "0000-00-00 00:00:00") {
                            return;
                        }

                        $payment->made_at = date("Y-m-d H:i:s");            
                         if($payment->object_type == Wpjb_Model_Payment::FOR_JOB) {
                            $this->_notifyJob($payment->object_id);
                        } elseif($payment->object_type == Wpjb_Model_Payment::FOR_RESUMES) {
                            $this->_notifyResume($payment->object_id);
                        } else {
                            // wtf?
                        }       
                        
                        $payment->payment_paid = $pfData["amount_gross"];
                        $payment->external_id = $pfData["pf_payment_id"]; 
                        $payment->is_valid = 1;
                        $payment->save();
                        pflog('PayFast ITN Verified Transaction ID: '.$transaction_id.' [' . $_POST['payment_status'] . '] ');
                        exit;
                        return false;
                        
                        
                        
                        
                                   
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
        exit;
        return false;
    }
    
     /**
     * render
     *
     * @author Ron Darby
     */
    public function render()
    {
        $router = Wpjb_Project::getInstance()->getApplication("frontend")->getRouter();
        /* @var $router Daq_Router */
        $merchant = $this->getMerchant();
        $product = str_replace("{num}", $this->_data->getId(), __("Job Board order #{num} at: ", WPJB_DOMAIN));
        $product.= get_bloginfo("name");
        $html = "";
        $html .= '<form action="'.$this->getUrl().'" method="post">';
        
        $varArray = array(
            'merchant_id'=>$merchant['merchant_id'],
            'merchant_key'=>$merchant['merchant_key'],
            'return_url'=>Wpjb_Project::getInstance()->getUrl()."/".$router->linkTo("step_complete", $this->_data),
            'cancel_url'=>Wpjb_Project::getInstance()->getUrl()."/",
            'notify_url'=> Wpjb_Project::getInstance()->getUrl()."/".$router->linkTo("step_notify", $this->_data),
            'm_payment_id'=>$this->_data->getId(),
            'amount'=>$this->_data->payment_sum-$this->_data->payment_paid,
            'item_name'=>$product
        );
        $secureString = '';
        foreach($varArray as $k=>$v){
            $html.= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
            $secureString .= $k.'='.urlencode($v).'&';
        }
       
        $secureString = substr( $secureString, 0, -1 );
        $secureSig = md5($secureString);
        
        $html .= '<input type="hidden" name="signature" value="'.$secureSig.'" />';
        $html .= '<input type="image" src="'.site_url().'/wp-content/plugins/wpjobboard/application/public/paynow-'.Wpjb_Project::getInstance()->conf("payfast_button").'.png" />';
        $html .= '</form>';
        return $html;
    }
    
    /**
     * setEnvironMent
     *
     * @author Ron Darby
     */
    public function setEnviroment($env)
    {
        $this->sandbox = $env;
    }

}

?>