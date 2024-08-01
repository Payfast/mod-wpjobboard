<?php

require_once dirname(__FILE__) . '/payfast_admin_controll.php';
require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/payfast_exception.php';

use Payfast\PayfastCommon\PayfastCommon;

class Payment_Payfast extends Wpjb_Payment_Abstract
{
    public function __construct(Wpjb_Model_Payment $data = null)
    {
        $this->_data = $data;
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
        $post = $this->_post; // $_POST

        $this->checkPaymentId($post["pf_payment_id"]);
        $pfError       = false;
        $pfErrMsg      = '';
        $pfDone        = false;
        $pfData        = array();
        $pfParamString = '';

        $isDebugmode   = $this->conf('payfast_debug') == 1;
        $payfastCommon = new PayfastCommon($isDebugmode);

        $payfastCommon->pflog('Payfast ITN call received');

        //// Notify Payfast that information has been received
        $this->notifyPF($pfError, $pfDone);

        //// Get data sent by Payfast
        if (!$pfError && !$pfDone) {
            $payfastCommon->pflog('Get posted data');

            // Posted variables from ITN
            $pfData = $payfastCommon->pfGetData();

            $payfastCommon->pflog('Payfast Data: ' . print_r($pfData, true));

            if ($pfData === false) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_BAD_ACCESS;
            }
        }

        //// Verify security signature
        if (!$pfError && !$pfDone) {
            $payfastCommon->pflog('Verify security signature');

            $merchant     = $this->getMerchant();
            $passphrase   = $merchant['passphrase'];
            $pfPassPhrase = $passphrase ?? null;

            // If signature different, log for debugging
            if (!$payfastCommon->pfValidSignature($pfData, $pfParamString, $pfPassPhrase)) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_INVALID_SIGNATURE;
            }
        }

        //// Verify data received
        if (!$pfError) {
            $payfastCommon->pflog('Verify data received');

            $moduleInfo = [
                "pfSoftwareName"       => 'WP Job Board',
                "pfSoftwareVer"        => '4.x',
                "pfSoftwareModuleName" => 'PF_WPJB',
                "pfModuleVer"          => '2.2.0',
            ];


            $pfValid = $payfastCommon->pfValidData($moduleInfo, $this->getUrl(), $pfParamString);

            if (!$pfValid) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_BAD_ACCESS;
            }
        }

        //// Check data against internal order
        if (!$pfError && !$pfDone) {
            $payfastCommon->pflog('Check data against internal order');

            // Check order amount
            if (!$payfastCommon->pfAmountsEqual($pfData['amount_gross'], $this->_data->payment_sum)) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_AMOUNT_MISMATCH;
            }
        }

        if ($pfError) {
            throw new PayfastException($pfErrMsg);
        }

        //// Check status and update order
        if (!$pfError && !$pfDone) {
            $payfastCommon->pflog('Check status and update order');


            $transaction_id = $pfData['pf_payment_id'];

            match ($pfData['payment_status']) {
                'COMPLETE' => [
                    "external_id" => $transaction_id,
                    "paid"        => $post["amount_gross"]
                ],
                'FAILED' => throw new PayfastException(
                    'Payfast ITN Verified Transaction ID: '
                    . $transaction_id . ' [' . $_POST['payment_status'] . '] '
                ),
                default => throw new PayfastException('Something went wrong with the Payfast ITN')
            };

            $payfastCommon->pflog(
                'Payfast ITN Verified Transaction ID: '
                . $transaction_id . ' [' . $_POST['payment_status'] . '] '
            );
        }

        return false;
    }

    public function render()
    {
        $arr = array(
            "action" => "wpjb_payment_accept",
            "engine" => $this->getEngine(),
            "id"     => $this->_data->id
        );

        $product = str_replace("{num}", $this->_data->getId(), __("Job Board order #{num} at: ", "wpjobboard"));
        $product .= get_bloginfo("name");

        $merchant = $this->getMerchant();

        $html = "";
        $html .= '<form action="https://' . $this->getUrl() . '/eng/process" method="post">';

        $varArray     = array(
            'merchant_id'  => $merchant['id'],
            'merchant_key' => $merchant['key'],
            'return_url'   => wpjb_link_to("employer_panel"),
            'cancel_url'   => home_url(),
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

        $imageUrl = "https://payfast.io/wp-content/uploads/2024/04/Full-Colour-on-White.svg";
        $html     .= '<input type="hidden" name="signature" value="' . $secureSig . '" />';
        $html     .= '<input type="hidden" name="user_agent" value="' . $userAgent . '" />';
        $html     .= '<div><p><strong>Pay now with:</strong></p>';
        $html     .= '<input title="Click Here to Pay" type="image" src="' . $imageUrl . '" ';
        $html     .= 'align="bottom" style="width:150px;"/></div>';
        $html     .= '</form>';

        return $html;
    }

}


