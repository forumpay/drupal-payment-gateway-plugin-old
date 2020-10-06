<?php

namespace Drupal\commerce_forumpay\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines TurtleCoinBaseController class.
 */
class ForumpayController extends ControllerBase
{

    public function ApiCall(Request $request)
    {
        $attached['library'][] = 'commerce_forumpay/payform';

        if ($_REQUEST['act'] == 'webhook') {
            $ipnres = file_get_contents('php://input');
            $ipnrear = json_decode($ipnres, true);

            if ($ipnrear == '') {
                echo "Invalid body JSON payload.";
                exit;
            }

            $apiurl = 'https://pay.limitlex.com/api/v2/CheckPayment/';

            $orderid = $ipnrear['reference_no'];
            $order = $this->load_order($orderid);

            $paymentGateway = $order->payment_gateway->entity;
            $config = $paymentGateway->get('configuration');

            $ForumPayParam = array(
                "pos_id" => $ipnrear['pos_id'],
                "payment_id" => $ipnrear['payment_id'],
                "address" => $ipnrear['address'],
                "currency" => $ipnrear['currency'],
            );

            $payres = $this->api_call($apiurl, $ForumPayParam, $config['api_user'], $config['api_key']);

            if ($payres['reference_no'] != $orderid) {
                echo 'Invalid reference_no in JSON payload.';
                exit;
            }

            $data['status'] = $payres['status'];

            if (($payres['status'] == 'Confirmed') || ($payres['status'] == 'Cancelled')) {

                $payres['orderid'] = $payres['reference_no'];
                $order = $this->load_order($orderid);

                if ($payres['status'] == 'Confirmed') {
                    $this->update_transaction($order, $payres);
                } else {
                    $order->set('state', 'cancelled');
                    $order->save();
                }

                echo "OK";
            } else {
                echo "Transaction is pending.";
            }

            exit;
        }

        if ($_REQUEST['act'] == 'getrate') {
            $apiurl = 'https://pay.limitlex.com/api/v2/GetRate/';
            $orderid = $_REQUEST['orderid'];
            $order = $this->load_order($orderid);
            $paymentGateway = $order->payment_gateway->entity;
            $config = $paymentGateway->get('configuration');

            $currency_code = $order->getTotalPrice()->getCurrencyCode();

            $total = $order->getTotalPrice()->getNumber();

            $ForumPayParam = array(
                "pos_id" => $config['pos_id'],
                "invoice_currency" => $currency_code,
                "invoice_amount" => $total,
                "currency" => $_REQUEST['currency'],
                "reference_no" => $orderid,
            );

            $payres = $this->api_call($apiurl, $ForumPayParam, $config['api_user'], $config['api_key']);

            if ($payres['err']) {
                $data['errmgs'] = $payres['err'];
                $data['status'] = 'No';
            } else {
                $data['status'] = 'Yes';
                $data['ordamt'] = $payres['invoice_amount'] . ' ' . $payres['invoice_currency'];
                $data['exrate'] = '1 ' . $payres['currency'] . ' = ' . $payres['rate'] . ' ' . $payres['invoice_currency'];
                $data['examt'] = $payres['amount_exchange'];
                $data['netpfee'] = $payres['network_processing_fee'];
                $data['amount'] = $payres['amount'] . ' ' . $payres['currency'];
                $data['payment_id'] = $payres['payment_id'];
                $data['txfee'] = $payres['fast_transaction_fee'] . ' ' . $payres['fast_transaction_fee_currency'];
                $data['waittime'] = $payres['wait_time'];
            }
            echo json_encode($data, true);
            exit;
        }

        if ($_REQUEST['act'] == 'getqr') {

            $apiurl = 'https://pay.limitlex.com/api/v2/StartPayment/';
            $orderid = $_REQUEST['orderid'];
            $order = $this->load_order($orderid);
            $paymentGateway = $order->payment_gateway->entity;
            $config = $paymentGateway->get('configuration');

            $currency_code = $order->getTotalPrice()->getCurrencyCode();

            $total = $order->getTotalPrice()->getNumber();

            $ForumPayParam = array(
                "pos_id" => $config['pos_id'],
                "invoice_currency" => $currency_code,
                "invoice_amount" => $total,
                "currency" => $_REQUEST['currency'],
                "reference_no" => $orderid,
            );

            $payres = $this->api_call($apiurl, $ForumPayParam, $config['api_user'], $config['api_key']);

            if ($payres['err']) {
                $data['errmgs'] = $payres['err'];
                $data['status'] = 'No';
            } else {
                $data['status'] = 'Yes';
                $data['ordamt'] = $payres['invoice_amount'] . ' ' . $payres['invoice_currency'];
                $data['exrate'] = '1 ' . $payres['currency'] . ' = ' . $payres['rate'] . ' ' . $payres['invoice_currency'];
                $data['examt'] = $payres['amount_exchange'];
                $data['netpfee'] = $payres['network_processing_fee'];

                $data['addr'] = $payres['address'];
                $data['qr_img'] = $payres['qr_img'];
                $data['amount'] = $payres['amount'] . ' ' . $payres['currency'];
                $data['payment_id'] = $payres['payment_id'];
                $data['txfee'] = $payres['fast_transaction_fee'] . ' ' . $payres['fast_transaction_fee_currency'];
                $data['waittime'] = $payres['wait_time'];
            }
            echo json_encode($data, true);
            exit;

        }
        if ($_REQUEST['act'] == 'getst') {
            $apiurl = 'https://pay.limitlex.com/api/v2/CheckPayment/';

            $orderid = $_REQUEST['orderid'];
            $order = $this->load_order($orderid);
            $paymentGateway = $order->payment_gateway->entity;
            $config = $paymentGateway->get('configuration');

            $currency_code = $order->getTotalPrice()->getCurrencyCode();
            $total = $order->getTotalPrice()->getNumber();

            $ForumPayParam = array(
                "pos_id" => $config['pos_id'],
                "payment_id" => $_REQUEST['paymentid'],
                "address" => $_REQUEST['addr'],
                "currency" => $_REQUEST['currency'],
            );

            $payres = $this->api_call($apiurl, $ForumPayParam, $config['api_user'], $config['api_key']);

            $data['status'] = $payres['status'];

            if (($payres['status'] == 'Confirmed') || ($payres['status'] == 'Cancelled')) {

                if ($payres['status'] == 'Confirmed') {
                    $payres['payment_id'] = $_REQUEST['paymentid'];
                    $this->update_transaction($order, $payres);
                }

            }

            echo json_encode($data, true);
        }

        exit;
    }

    public function update_transaction($order, $resdata)
    {
        $paymentStorage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
        $transactionArray = $paymentStorage->loadByProperties(['order_id' => $order->id()]);

        $paymentGateway = $order->payment_gateway->entity;

        if (!empty($transactionArray)) {
            $transaction = array_shift($transactionArray);

        } else {

            $transaction = $paymentStorage->create([
                'payment_gateway' => $paymentGateway->get('id'),
                'order_id' => $order->id(),
                'remote_id' => $resdata['payment_id'],
            ]);
        }

        $transaction->setRemoteId($resdata['payment_id']);
        $transaction->setRemoteState('completed');
        $transaction->setState('completed');
        $transaction->setAmount($order->getTotalPrice());
        $paymentStorage->save($transaction);
        $order->set('state', 'completed');
        $order->set('checkout_step', 'complete');

        $order->save();

    }

    public function PayForm(Request $request)
    {
        $attached['library'][] = 'commerce_forumpay/payform';

        $orderid = $_REQUEST['orderid'];
        $return_url = $_REQUEST['return_url'];
        $cancel_url = $_REQUEST['cancel_url'];
        $order = $this->load_order($orderid);
        $orderamt = $order->getBalance();
        $paymentGateway = $order->payment_gateway->entity;
        $config = $paymentGateway->get('configuration');

        $apiurl = 'https://pay.limitlex.com/api/v2/GetCurrencyList/';
        $cForumPayParam = array();
        $CurrencyList = $this->api_call($apiurl, $cForumPayParam, $config['api_user'], $config['api_key']);
        $param = array('act' => 'getqr');
        $qrurl = Url::fromRoute('commerce_forumpay.apicall', $param, ['absolute' => true])->toString();
        $param = array('act' => 'getst');
        $sturl = Url::fromRoute('commerce_forumpay.apicall', $param, ['absolute' => true])->toString();
        $param = array('act' => 'getrate');
        $rateurl = Url::fromRoute('commerce_forumpay.apicall', $param, ['absolute' => true])->toString();

        $extahtm = '';
        $extahtm .= '<snap id="forumpay-qrurl" data="' . $qrurl . '"></snap>';
        $extahtm .= '<snap id="forumpay-rateurl" data="' . $rateurl . '"></snap>';
        $extahtm .= '<snap id="forumpay-sturl" data="' . $sturl . '"></snap>';
        $extahtm .= '<snap id="forumpay-orderid" data="' . $orderid . '"></snap>';
        $extahtm .= '<snap id="forumpay-returl" data="' . $return_url . '"></snap>';
        $extahtm .= '<snap id="forumpay-cancelurl" data="' . $cancel_url . '"></snap>';

        $base_path = Url::fromRoute('<front>', [], ['absolute' => true])->toString();
        $loadimg = $base_path . '/modules/commerce_forumpay/css/page-load.gif';
        $logoimg = $base_path . '/modules/commerce_forumpay/css/forumpay-logo.png';

        $sCurrencyList = '';

        foreach ($CurrencyList as $Currency) {
            if ($Currency['currency'] != 'USDT') {
                $sCurrencyList .= '<option value=' . $Currency['currency'] . '>' . $Currency['description'] . ' (' . $Currency['currency'] . ')</option>';
            }

        }
        $templatehtml = '<div class="forumpay-main">
	<div class="forumpay-row forumpay-row-img">
 <img src="' . $logoimg . '"  alt="Pay with Crypto (by ForumPay)" />
</div>

<div class="forumpay-row">
<div class="forumpay-col1">Order No</div>
<div class="forumpay-col2">' . $orderid . '</div>
</div>
<div class="forumpay-row">
<div class="forumpay-col1">' . t('Order amount') . '</div>
<div class="forumpay-col2">' . $orderamt . '</div>
</div>

<div class="forumpay-row forumpay-title" id="forumpay-ccy-div">
    <select name="ChangeCurrency" onChange="forumpaygetrate(this.value)">
		<option value="0">--' . t('Select Cryptocurrency') . '--</option>' . $sCurrencyList . '
    </select>
</div>

<div class="fp-details" style="display: none" id="fp-details-div">

<div class="forumpay-rowsm">
<div class="forumpay-col1">' . t('Rate') . ':</div>
<div class="forumpay-col2">
<snap id="forumpay-exrate"> </snap>
</div>
</div>

<div class="forumpay-rowsm">
<div class="forumpay-col1">' . t('Exchange amount') . ':</div>
<div class="forumpay-col2">
<snap id="forumpay-examt"> </snap>
</div>
</div>

<div class="forumpay-rowsm">
<div class="forumpay-col1">' . t('Network processing fee') . ':</div>
<div class="forumpay-col2">
<snap id="forumpay-netpfee"> </snap>
</div>
</div>

<div class="forumpay-row">
<div class="forumpay-col1">' . t('Total') . ':</div>
<div class="forumpay-col2">
<snap id="forumpay-tot"> </snap>
</div>
</div>

<div class="forumpay-rowsm" id="forumpay-wtime-div">
<div class="forumpay-col1">' . t('Expected time to wait') . ':</div>
<div class="forumpay-col2">
<snap id="forumpay-waittime"> </snap>
</div>
</div>

<div class="forumpay-rowsm" id="forumpay-txfee-div">
<div class="forumpay-col1">' . t('TX fee set to') . ':</div>
<div class="forumpay-col2">
<snap id="forumpay-txfee"> </snap>
</div>
</div>

<div class="forumpay-row forumpay-qr" style="display: none" id="qr-img-div">
		 <img src="" id="forumpay-qr-img" style="width: 50%">
</div>

<div class="forumpay-row forumpay-addr">
  <snap id="forumpay-addr"></snap>
</div>

<div class="forumpay-row forumpay-addr" id="forumpay-btn-div">
  <button type="submit" id="forumpay-payment-btn" class="paybtn" style="width:90%;" onclick="forumpaygetqrcode()">
Start payment</button>
</div>

</div>

<div class="forumpay-row forumpay-st" id="forumpay-payst-div" style="display: none">
  ' . t('Status') . ' :
  <snap id="forumpay-payst"> </snap>
</div>

<div class="forumpay-row forumpay-err" id="forumpay-err-div" style="display: none">
  ' . t('Error') . ' :
  <snap id="forumpay-err"> </snap>
</div>

</div>
<div id="forumpay-loading" style="display: none">
  <img id="forumpay-loading-image" src="' . $loadimg . '" alt="Loading..." />
</div>' . $extahtm;

        return array(
            '#attached' => $attached,
            '#type' => 'inline_template',
            '#template' => $templatehtml,
        );

    }

    private function api_call($rest_url, $ForumPay_Params, $api_user, $api_key)
    {
        $ForumPay_Qr = http_build_query($ForumPay_Params);

        $curl = curl_init(trim($rest_url));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, $api_user . ":" . $api_key);
        if (!empty($ForumPay_Qr)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $ForumPay_Qr);
        }
        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
    }
    private function load_order($orderId)
    {
        $order = Order::load($orderId);

        if (!$order) {
            echo 'Order not found: ' . $orderId;
            exit;
        }

        return $order;
    }

}
