<?php

namespace DirectPay\Directpay\Controller\Payment;

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
        \Magento\Sales\Api\Data\OrderInterface $order
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

                if (!$paymode) {
                    $checkout_url = "https://pay.directpay.lk";
                } else {
                    $checkout_url = "https://testpay.directpay.lk";
                }

                $this->postToCheckout($checkout_url, $this->getPayload($order));

            } else {
                $this->logger->debug('Order in unrecognized state: ' . $order->getState());
                $this->_redirect('checkout/cart');
            }
        } catch (Exception $ex) {
            $this->logger->debug('An exception was encountered in directpay/payment/checkout: ' . $ex->getMessage());
            $this->logger->debug($ex->getTraceAsString());
            $this->getMessageManager()->addErrorMessage(__('Unable to Checkout.'));
            $this->_redirect('checkout/cart');
        }

    }

    private function postToCheckout($checkoutUrl, $payload)
    {
        echo
        "<html>
            <body>
            <form id='directPayCheckoutForm' action='$checkoutUrl' method='post'>";
        foreach ($payload as $key => $value) {
            echo "<input type='hidden' id='$key' name='$key' value='" . htmlspecialchars($value, ENT_QUOTES) . "'/>";
        }
        echo
        '</form>
            </body>';
        echo
        '<script>
                var form = document.getElementById("directPayCheckoutForm");
                form.submit();
            </script>
        </html>';
    }

    private function getPayload($order)
    {
        if ($order == null) {
            $this->logger->debug('Unable to get order from last order id.');
            $this->getMessageManager()->addErrorMessage(__('Order Not Found!'));
            $this->_redirect('checkout/onepage/error', array('_secure' => false));
        }

        $merchantId = $this->scopeConfig->getValue('payment/directpay/merchantid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->scopeConfig->getValue('payment/directpay/apikey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $privateKey = $this->scopeConfig->getValue('payment/directpay/privateKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $grandTotal = number_format((float)$order->getGrandTotal(), 2, '.', '');
        $currency = $order->getOrderCurrencyCode();
        $orderId = $order->getId();
        $pluginName = 'magento-dp';
        $pluginVersion = '2.x';
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

        $data = array(
            '_mId' => $merchantId,
            'api_key' => $apiKey,
            '_returnUrl' => $returnUrl,
            '_cancelUrl' => $cancelUrl,
            '_responseUrl' => $responseUrl,
            '_amount' => $grandTotal,
            '_currency' => $currency,
            '_reference' => $reference,
            '_orderId' => $orderId,
            '_pluginName' => $pluginName,
            '_pluginVersion' => $pluginVersion,
            '_description' => substr($description,0, -2),
            '_firstName' => $firstName,
            '_lastName' => $lastName,
            '_email' => $email
        );

        foreach ($data as $key => $value) {
            $data[$key] = preg_replace('/\r\n|\r|\n/', ' ', $value);
        }

        $dataString = $merchantId .
            $grandTotal .
            $currency .
            $pluginName .
            $pluginVersion .
            $returnUrl .
            $cancelUrl .
            $orderId .
            $reference .
            $firstName .
            $lastName .
            $email .
            substr($description,0, -2) .
            $apiKey .
            $responseUrl;


        $signature = null;
        $pkeyid = openssl_pkey_get_private($privateKey);
        $signResult = openssl_sign($dataString, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);
        openssl_free_key($pkeyid);

        $data['signature'] = $signature;

        return $data;
    }

}
