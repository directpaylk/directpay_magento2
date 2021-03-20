<?php

namespace DirectPay\IPG\Controller\Payment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;


class Checkout extends Action
{
    protected $logger;
    protected $_quoteFactory;
    protected $scopeConfig;
    protected $_checkoutSession;
    protected $_storeManager;
    protected $_orderFactory;
    protected $_formKey;
    protected $_messageManager;
    private $order;

    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        Context $context,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\Message\ManagerInterface $messageManager
    )
    {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->order = $order;
        $this->_storeManager = $storeManager;
        $this->_formKey = $formKey;
        $this->_quoteFactory = $quoteFactory;
        $this->_messageManager = $messageManager;
        parent::__construct($context);
    }

    public function getOrder()
    {
        if ($this->_checkoutSession->getLastRealOrderId()) {
            return $this->_orderFactory->create()->loadByIncrementId($this->_checkoutSession->getLastRealOrderId());
        }
        return false;
    }

    public function execute()
    {
        try {
            $order = $this->getOrder();
            $quote = $this->_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());

            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(null)->save();
                $this->_checkoutSession->replaceQuote($quote);
            }

            if ($order->getStatus() === 'pending') {

                $paymode = $this->scopeConfig->getValue('payment/directpay/pay_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $secret = $this->scopeConfig->getValue('payment/directpay/secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                $baseUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

                if (!$paymode) {
                    $checkout_url = "https://gateway.directpay.lk/api/v3/create-session";
                } else {
                    $checkout_url = "https://test-gateway.directpay.lk/api/v3/create-session";
                }

                $sessionPayload = $this->getPayload($order);

                $dataString = base64_encode(json_encode($sessionPayload));
                $signature = 'hmac ' . hash_hmac('sha256', $dataString, $secret);

                $ch = curl_init();

                curl_setopt_array($ch, array(
                    CURLOPT_URL => $checkout_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => base64_encode(json_encode($sessionPayload)),
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "Authorization: $signature",
                    ],
                ));

                $response = curl_exec($ch);

                if ($paymode && curl_error($ch)) {
                    var_dump(curl_errno($ch));
                    var_dump(curl_error($ch));
                }

                curl_close($ch);

                $getSession = json_decode($response);

                if ($getSession->status == 200) {
                    $link = $getSession->data->link;
                    $paymentRedirect = $link;
                } else {
                    $paymentRedirect = '';
                    $this->_messageManager->addErrorMessage(__('Unable to checkout. Please try again.'));
                    $this->_redirect('checkout/cart');
                }


                $this->postToCheckout($paymentRedirect);

            } else {
                $this->logger->debug('Order in unrecognized state: ' . $order->getState());
                $this->_messageManager->addErrorMessage(__('Unable to checkout.'));
                $this->_redirect('checkout/cart');
            }
        } catch (Exception $ex) {
            $this->logger->debug('An exception was encountered in directpay/payment/checkout: ' . $ex->getMessage());
            $this->logger->debug($ex->getTraceAsString());
            $this->_messageManager->addErrorMessage(__('Unable to checkout.'));
            $this->_redirect('checkout/cart');
        }

    }

    private function postToCheckout($checkoutUrl)
    {

        echo '
        <style>
            .loader {
              border: 2px solid #f3f3f3; /* Light grey */
              border-top: 4px solid #1f369c; /* Blue */
              border-radius: 50%;
              width: 6vw;
              height: 6vw;
              animation: spin 0.5s linear infinite;
              position: absolute;
              left: 47vw;
              top: 50vh;
            }

            @keyframes spin {
              0% { transform: rotate(0deg); }
              100% { transform: rotate(360deg); }
            }

            .align-content {
              margin: auto;
              width: 20vw;
              text-align: center;
              position: absolute;
              top: 30vh;
              left: 40vw;
            }
        </style>
        <form id="directpay_payment_form" method="GET" action="' . $checkoutUrl . '">
            <img src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" max-width="20vw" class="align-content" />
            <div class="loader"></div>
            <script>
                var form = document.getElementById(\'directpay_payment_form\');
                form.submit();
            </script>
        </form>
        ';
    }

    private function getPayload($order)
    {
        if ($order == null) {
            $this->logger->debug('Unable to get order from last order id.');
            $this->_messageManager->addErrorMessage(__('Order not found.'));
            $this->_redirect('checkout/onepage/error', array('_secure' => false));
        }

        $merchantId = $this->scopeConfig->getValue('payment/directpay/merchantid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        date_default_timezone_set('Asia/Colombo');
        $grandTotal = number_format((float)$order->getGrandTotal(), 2, '.', '');
        $currency = $order->getOrderCurrencyCode();
        $orderId = 'MG' . $order->getId() . date("His");
        $pluginName = 'magento-dp';
        $pluginVersion = '3.0';
        $firstName = $order->getCustomerFirstname();
        $lastName = $order->getCustomerLastname();
        $email = $order->getData('customer_email');
        $returnUrl = $baseUrl . 'directpay/payment/redirect';
        $reference = $orderId;
        $description = '';
        $cancelUrl = $baseUrl . 'checkout/cart';
        $responseUrl = $baseUrl . 'directpay/payment/response';

        foreach ($order->getAllItems() as $item) {
            $itemData = $item->getData();
            $description .= $itemData['name'] . ', ';
        }

        $requestData = [
            "merchant_id" => $merchantId,
            "amount" => $grandTotal ? (string)$grandTotal : "0.00",
            "source" => "magento-dp_v3.0",
            "type" => "ONE_TIME",
            "order_id" => (string)$orderId,
            "currency" => $currency,
            "response_url" => $responseUrl,
            "return_url" => $returnUrl,
            "first_name" => $firstName,
            "last_name" => $lastName,
            "email" => $email,
            "phone" => $order->getShippingAddress()->getTelephone(),
            "logo" => '',
            "description" => substr($description, 0, -2),
        ];

        foreach ($requestData as $key => $value) {
            $requestData[$key] = preg_replace('/\r\n|\r|\n/', ' ', $value);
        }

        return $requestData;
    }

}
