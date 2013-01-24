<?php
/**
 * Description of PayPal
 *
 * @author greg
 * @package 
 */

class Wpjb_Form_Admin_Config_Payment extends Daq_Form_Abstract
{
    private $_env;

    public $name = null;

    /**
 * public function init()
 *     {
 *         
 *         $this->name = __("Payment Settings", WPJB_DOMAIN);
 *         $this->_env = array(
 *             1 => __("Sandbox (For testing only)", WPJB_DOMAIN),
 *             2 => __("Production (Real money)", WPJB_DOMAIN)
 *         );

 *         $instance = Wpjb_Project::getInstance();

 *         $e = new Daq_Form_Element("paypal_email");
 *         $e->setValue($instance->getConfig("paypal_email"));
 *         $e->setLabel(__("PayPal eMail", WPJB_DOMAIN));
 *         $e->addValidator(new Daq_Validate_Email());
 *         $this->addElement($e);

 *         $e = new Daq_Form_Element("paypal_env", Daq_Form_Element::TYPE_SELECT);
 *         $e->setValue($instance->getConfig("paypal_env"));
 *         $e->setLabel(__("PayPal Environment", WPJB_DOMAIN));
 *         $e->addValidator(new Daq_Validate_InArray(array_keys($this->_env)));
 *         foreach($this->_env as $k => $v) {
 *             $e->addOption($k, $k,  $v);
 *         }
 *         $this->addElement($e);
 *         
 *         apply_filters("wpja_form_init_config_payment", $this);

 *     }
 */
 
 
     public function init()
    {
        
        $this->name = __("Payment Settings", WPJB_DOMAIN);
        $this->_env = array(
            1 => __("Sandbox (For testing only)", WPJB_DOMAIN),
            2 => __("Production (Real money)", WPJB_DOMAIN)
        );

        $instance = Wpjb_Project::getInstance();

        $e = new Daq_Form_Element("paypal_email");
        $e->setValue($instance->getConfig("paypal_email"));
        $e->setLabel(__("PayPal eMail", WPJB_DOMAIN));
        $e->addValidator(new Daq_Validate_Email());
        $this->addElement($e);

        $e = new Daq_Form_Element("paypal_env", Daq_Form_Element::TYPE_SELECT);
        $e->setValue($instance->getConfig("paypal_env"));
        $e->setLabel(__("PayPal Environment", WPJB_DOMAIN));
        $e->addValidator(new Daq_Validate_InArray(array_keys($this->_env)));
        foreach($this->_env as $k => $v) {
            $e->addOption($k, $k,  $v);
        }
        $this->addElement($e);
        
        
        
        $payfast = new Daq_Form_Element("payfast_merchant_id");
        $payfast->setValue($instance->getConfig("payfast_merchant_id"));
        $payfast->setLabel(__("PayFast Merchant ID", WPJB_DOMAIN));
        
        $this->addElement($payfast);
        
         $payfast = new Daq_Form_Element("payfast_merchant_key");
        $payfast->setValue($instance->getConfig("payfast_merchant_key"));
        $payfast->setLabel(__("PayFast Merchant Key", WPJB_DOMAIN));
        
        $this->addElement($payfast);
        
        $payfast = new Daq_Form_Element("payfast_sandbox",Daq_Form_Element::TYPE_RADIO);
        $payfast->setValue($instance->getConfig("payfast_sandbox"));
        $payfast->setLabel(__("PayFast Sandbox/Live", WPJB_DOMAIN));
        $payfast->addOption(0,'true','Sandbox');
        $payfast->addOption(1,'false','Live');
        $this->addElement($payfast);
        
        $payfast = new Daq_Form_Element("payfast_debug",Daq_Form_Element::TYPE_RADIO);
        $payfast->setValue($instance->getConfig("payfast_debug"));
        $payfast->setLabel(__("PayFast ITN Debug", WPJB_DOMAIN));
        $payfast->addOption(0,'true','On');
        $payfast->addOption(1,'false','Off');
        $payfast->setHint("If set to 'On', debug will output a file located at '/wp-content/plugins/wpjobboard/application/libraries/Payment/payfast.log' when
        an ITN call is made from PayFast.");
        $this->addElement($payfast);
        
        
        $payfast = new Daq_Form_Element("payfast_button",Daq_Form_Element::TYPE_RADIO);
        $payfast->setValue($instance->getConfig("payfast_button"));
        $payfast->setLabel(__("PayFast Pay Now Button", WPJB_DOMAIN));
        $payfast->addOption(0,'light','<img src="'.site_url().'/wp-content/plugins/wpjobboard/application/public/paynow-light.png" alt="PayFast Pay Now light" align="top" />');
        $payfast->addOption(1,'dark','<img src="'.site_url().'/wp-content/plugins/wpjobboard/application/public/paynow-dark.png" alt="PayFast Pay Now dark" align="top" />');
        $this->addElement($payfast);
        
        apply_filters("wpja_form_init_config_payment", $this);

    }
}

?>