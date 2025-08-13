<?php

require_once dirname(__FILE__) . '/payfast_admin_controll.php';
require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/payfast_exception.php';

use Payfast\PayfastCommon\Aggregator\Request\PaymentRequest;

class Payment_Payfast extends Wpjb_Payment_Abstract
{
    const PF_MODULE_VER = '2.3.0';

    public function __construct(Wpjb_Model_Payment $data = null)
    {
        $this->_data        = $data;
    }

    public function getEngine()
    {
        return "payfast_payment";
    }

    /**
     * @param bool $pfError
     * @param bool $pfDone
     *
     * @return void
     */
    public function notifyPF(bool $pfError, bool $pfDone): void
    {
        if (!$pfError && !$pfDone) {
            header('HTTP/1.0 200 OK');
            flush();
        }
    }

    /**
     * @param $pf_payment_id
     *
     * @return void
     * @throws Exception
     */
    public function checkPaymentId($pf_payment_id): void
    {
        if ($pf_payment_id < 1) {
            throw new PayfastException("Invalid Payfast Payment ID");
        }
    }

    private function getMerchant()
    {
        $merchant               = array();
        $merchant['id']         = $this->conf('payfast_merchant_id');
        $merchant['key']        = $this->conf('payfast_merchant_key');
        $merchant['passphrase'] = $this->conf('payfast_passphrase');

        return $merchant;
    }

    public function getTitle()
    {
        return "Payfast";
    }

    public function getForm()
    {
        return "Config_Payfast";
    }

    private function getUrl()
    {
        return $this->conf('payfast_sandbox') == 1 ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
    }

    public function bind(array $post, array $get)
    {
        // This is a good place to set $this->data
        $this->setObject(new Wpjb_Model_Payment($get["id"]));
        parent::bind($post, $get);
    }

    public function processTransaction()
    {
        $post = $this->_post;
        $this->checkPaymentId($post["pf_payment_id"]);

        $isDebugMode = $this->conf('payfast_debug') == 1;
        $paymentRequest = new PaymentRequest($isDebugMode);
        $paymentRequest->pflog('Payfast ITN call received');

        $pfError = false;
        $pfDone = false;
        $pfErrMsg = '';
        $pfParamString = '';
        $pfData = [];

        $this->notifyPF($pfError, $pfDone);
        $pfData = $this->handlePostedData($paymentRequest, $pfError, $pfErrMsg, $pfDone);
        if(!$pfError){
            $this->handleSignatureCheck($paymentRequest, $pfData, $pfParamString, $pfError, $pfErrMsg, $pfDone);
        }
        if(!$pfError){
            $this->handleModuleDataCheck($paymentRequest, $pfParamString, $pfError, $pfErrMsg);
        }
        if(!$pfError){
            $this->handleAmountCheck($paymentRequest, $pfData, $pfError, $pfErrMsg, $pfDone);
        }


        if ($pfError) {
            $paymentRequest->pflog('Error: ' . $pfErrMsg); // Log the error message
            throw new PayfastException($pfErrMsg);
        }

        return $this->handleStatusUpdate($paymentRequest, $pfData, $pfDone);

    }
    private function handlePostedData($paymentRequest, &$pfError, &$pfErrMsg, $pfDone)
    {
        if ($pfError || $pfDone) {
            return [];
        }

        $paymentRequest->pflog('Get posted data');
        $pfData = $paymentRequest->pfGetData();
        $paymentRequest->pflog('Payfast Data: ' . print_r($pfData, true));

        if ($pfData === false) {
            $pfError = true;
            $pfErrMsg = PaymentRequest::PF_ERR_BAD_ACCESS;
            $paymentRequest->pflog('Error: ' . PaymentRequest::PF_ERR_BAD_ACCESS);
        }

        return $pfData;
    }

    private function handleSignatureCheck($paymentRequest, $pfData, &$pfParamString, &$pfError, &$pfErrMsg, $pfDone)
    {
        if ($pfError || $pfDone) {
            return;
        }

        $paymentRequest->pflog('Verify security signature');
        $passphrase = $this->getMerchant()['passphrase'] ?? null;

        if (!$paymentRequest->pfValidSignature($pfData, $pfParamString, $passphrase)) {
            $pfError = true;
            $pfErrMsg = PaymentRequest::PF_ERR_INVALID_SIGNATURE;
            $paymentRequest->pflog('Error: ' . PaymentRequest::PF_ERR_INVALID_SIGNATURE);
        }
    }

    private function handleModuleDataCheck($paymentRequest, $pfParamString, &$pfError, &$pfErrMsg)
    {
        if ($pfError) {
            return;
        }

        $paymentRequest->pflog('Verify data received');
        $moduleInfo = [
            "pfSoftwareName" => 'WP Job Board',
            "pfSoftwareVer" => '4.x',
            "pfSoftwareModuleName" => 'PF_WPJB',
            "pfModuleVer" => self::PF_MODULE_VER,
        ];

        if (!$paymentRequest->pfValidData($moduleInfo, $this->getUrl(), $pfParamString)) {
            $pfError = true;
            $pfErrMsg = PaymentRequest::PF_ERR_BAD_ACCESS;
            $paymentRequest->pflog('Error: ' . PaymentRequest::PF_ERR_BAD_ACCESS);
        }
    }

    private function handleAmountCheck($paymentRequest, $pfData, &$pfError, &$pfErrMsg, $pfDone)
    {
        if ($pfError || $pfDone) {
            return;
        }

        $paymentRequest->pflog('Check data against internal order');
        if (!$paymentRequest->pfAmountsEqual($pfData['amount_gross'], $this->_data->payment_sum)) {
            $pfError = true;
            $pfErrMsg = PaymentRequest::PF_ERR_AMOUNT_MISMATCH;
            $paymentRequest->pflog('Error: ' . PaymentRequest::PF_ERR_AMOUNT_MISMATCH);
        }
    }

    private function handleStatusUpdate($paymentRequest, $pfData, $pfDone)
    {
        if ($pfDone) {
            return;
        }

        $paymentRequest->pflog('Check status and update order');
        $transaction_id = $pfData['pf_payment_id'];
        $job_id = $this->_data->object_id;

        try {
            switch ($pfData['payment_status']) {
                case 'COMPLETE':
                    $paymentRequest->pflog("Transaction $transaction_id completed");

                    $this->_data->payment_paid = $pfData['amount_gross'];
                    $this->_data->external_id = $transaction_id;
                    $this->_data->paid_at = current_time('mysql', true);
                    $this->_data->status = 2; // Mark payment as completed
                    $this->_data->save();
                    $this->_data->log(__("Payment verified by Payfast ITN.", "wpjobboard"));

                    if ($job_id) {
                        $job = new Wpjb_Model_Job($job_id);
                        if ($job->exists()) {
                            $job->is_active = 1;
                            $job->is_approved = 1;
                            $job->save();

                            $paymentRequest->pflog("Job ID $job_id activated.");
                        } else {
                            $paymentRequest->pflog("Warning: Job ID $job_id not found.");
                        }
                    }

                    return [
                        "paid" => $pfData['amount_gross'],
                        "external_id" => $transaction_id
                    ];

                case 'FAILED':
                    $paymentRequest->pflog("Transaction $transaction_id failed");

                    $this->_data->status = 4; // Failed
                    $this->_data->save();

                    if ($job_id) {
                        $job = new Wpjb_Model_Job($job_id);
                        if ($job->exists()) {
                            $job->is_active = 0;
                            $job->save();
                        }
                    }

                    throw new PayfastException("Transaction $transaction_id [FAILED]");
                    break;

                default:
                    throw new PayfastException("Unexpected payment status: {$pfData['payment_status']}");
            }
        } catch (Exception $e) {
            $paymentRequest->pflog('Error updating status: ' . $e->getMessage());
            throw $e;
        }

        $paymentRequest->pflog("Verified Transaction ID: $transaction_id [{$pfData['payment_status']}]");
    }

    public static function enqueue_payfast_script() {
        // Register and enqueue the script
        wp_enqueue_script(
            'wpjb-payfast-auto-submit',
            plugins_url('assets/js/wpjb-payfast-auto-submit.js', __FILE__),
            array('jquery'),
            self::PF_MODULE_VER,
            true
        );
    }

    public function render()
    {
        // Enqueue the auto-submit script
        add_action('wp_footer', array(__CLASS__, 'enqueue_payfast_script'));

        $arr = array(
            "action" => "wpjb_payment_accept",
            "engine" => $this->getEngine(),
            "id"     => $this->_data->id
        );

        $product = str_replace("{num}", $this->_data->getId(), __("Job Board order #{num} at: ", "wpjobboard"));
        $product .= get_bloginfo("name");

        $merchant = $this->getMerchant();

        $html = "";
        // Start wrapper for info and spinner
        $html .= '<div class="wpjb-flash-info">';
        $imageUrl = "https://payfast.io/wp-content/uploads/2024/04/Full-Colour-on-White.svg";
        $html .= '<div style="text-align:center;margin-bottom:16px;"><img src="' . esc_url($imageUrl) . '" alt="Payfast" style="max-width:180px;height:auto;"></div>';
        $html .= '<div class="wpjb-flash-icon"><span class="wpjb-glyphs wpjb-icon-spinner wpjb-animate-spin"></span></div>';
        $html .= '<div class="wpjb-flash-body">';
        $html .= '<p><strong>' . __("Your order has been placed.", "wpjobboard") . '</strong></p>';
        $html .= '<p>' . __("Please wait. You are now being redirected to Payfast.", "wpjobboard") . '</p>';
        $html .= '</div>';

        // Hide the form for auto-submit
        $html .= '<form id="wpjb-payfast-auto-submit" class="wpjb-payment-auto-submit wpjb-none" action="https://' . $this->getUrl() . '/eng/process" method="post">';
        $varArray     = array(
            'merchant_id'  => $merchant['id'],
            'merchant_key' => $merchant['key'],
            'return_url'   => wpjb_link_to("employer_panel"),
            'cancel_url'   => home_url() . '/jobs/post-a-job/save/',
            'notify_url'   => admin_url('admin-ajax.php') . "?" . http_build_query($arr),
            'm_payment_id' => $this->_data->getId(),
            'amount'       => $this->_data->payment_sum - $this->_data->payment_paid,
            'item_name'    => $product,
            'custom_str1'  => 'WP Job Board' . '_' . '4.x' . '_' . 'PF_WPJB'
        );
        $secureString = '';
        foreach ($varArray as $k => $v) {
            $html         .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
            $secureString .= $k . '=' . urlencode(stripslashes(trim($v))) . '&';
        }

        $passphrase = $merchant['passphrase'];
        if (empty($passphrase)) {
            $secureString = substr($secureString, 0, -1);
        } else {
            $secureString = $secureString . "passphrase=" . urlencode(trim($passphrase));
        }

        $secureSig = md5($secureString);
        $userAgent = 'WP-Jobboard 4.x';

        $html     .= '<input type="hidden" name="signature" value="' . $secureSig . '" />';
        $html     .= '<input type="hidden" name="user_agent" value="' . $userAgent . '" />';
        $html     .= '</form>';
        $html     .= '</div>'; // close .wpjb-flash-info

        // Output a JS variable for the form ID (for the enqueued script)
        $html .= '<script>window.wpjbPayfastFormId = "wpjb-payfast-auto-submit";</script>';

        return $html;
    }
}
