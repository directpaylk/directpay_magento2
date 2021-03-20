<?php

namespace DirectPay\IPG\Controller\Payment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use mysql_xdevapi\Exception;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\RequestInterface;


class Response extends Action
{
    protected $logger;
    protected $resultRedirectFactory;
    protected $scopeConfig;
    protected $_orderFactory;
    protected $_quoteFactory;
    protected $_formKey;
    protected $_checkoutSession;
    protected $request;
    protected $orderRepository;

    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        Context $context,
        LoggerInterface $logger

    )
    {
        $this->_quoteFactory = $quoteFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->scopeConfig = $scopeConfig;
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->_formKey = $formKey;
        $this->request = $request;
        $this->orderRepository = $orderRepository;

        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            if ($this->request->isPost()) {
                $keyVal = $this->_formKey->getFormKey();
                $this->request->setPostValue('form_key', $keyVal);
            }
        }

        parent::__construct($context);
    }

    public function execute()
    {
        $log = 'DIRECTPAY | PAYMENT RESPONSE | ';
        try {
            $postBody_raw = file_get_contents('php://input');
            $postBody = json_decode(base64_decode($postBody_raw), true);

            $this->logger->info($log . "BODY : " . $postBody_raw);

            $headers = array();
            foreach ($_SERVER as $key => $value) {
                if (substr($key, 0, 5) <> 'HTTP_') {
                    continue;
                }
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }

            $this->logger->info($log . "HEADERS : " . json_encode($headers));

            $transactionType = $postBody["type"];
            $orderId = $postBody["order_id"];
            $transactionId = $postBody["transaction_id"];
            $transactionStatus = isset($postBody["transaction"]) ? $postBody["transaction"]["status"] : "-";
            $transactionDesc = isset($postBody["transaction"]) ? $postBody["transaction"]["description"] : "-";
            $paymentAmount = isset($postBody["transaction"]) ? $postBody["transaction"]["amount"] : "0.00";
            $paymentCurrency = isset($postBody["transaction"]) ? $postBody["transaction"]["currency"] : "LKR";


            $secret = $this->scopeConfig->getValue('payment/directpay/secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            $authHeaders = explode(' ', $headers['Authorization']);

            if (count($authHeaders) == 2) {
                $hash = hash_hmac('sha256', $postBody_raw, $secret);
                if (strcmp($authHeaders[1], $hash) == 0) {
                    echo " Signature Verified. ";

                    $formattedOrderId = substr($orderId, 2, -6);

                    $order = $this->orderRepository->get($formattedOrderId);

                    if ($order) {
                        if ($transactionStatus == 'SUCCESS') {
                            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                            $order->addStatusToHistory($order->getStatus(), 'Payment Processed Successfully.');
                            $order->save();

                            echo " Payment Processed Successfully. ";

                        } else {
                            $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                            $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                            $order->addStatusToHistory($order->getStatus(), 'Payment Failed.');
                            $order->save();

                            echo " Payment Failed. State saved as CANCELLED. ";
                        }

                        $quote = $this->_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());

                        if ($quote->getId()) {
                            $quote->setIsActive(0)->setReservedOrderId(null)->save();
                            $this->_checkoutSession->replaceQuote($quote);

                            echo " Cart Invalidated. ";
                        }

                    } else {
                        echo " Order Not Found. OrderId: " . $orderId . ' | originalOrderId: ' . $formattedOrderId;
                    }

                } else {
                    echo " Signature Verification Failed. ";
                }
            } else {
                echo " Invalid Signature. Headers: " . json_encode($headers) . " | Raw Headers: " . json_encode($_SERVER);
            }
        } catch (\Exception $exception) {
            echo " PAYMENT RESPONSE | EXCEPTION : " . $exception->getMessage() . " -> : " . $exception->getLine() . " ";
        }
    }

}
