<?php

namespace DirectPay\Directpay\Controller\Payment;

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
        $publicKey = $this->scopeConfig->getValue('payment/directpay/publicKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $postBody = (array)json_decode(file_get_contents('php://input'));

        $signature = $postBody['signature'];
        $dataString = $postBody['orderId'] . $postBody['trnId'] . $postBody['status'] . $postBody['desc'];

        $signatureVerify = openssl_verify($dataString, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);

        if ($signatureVerify) {

            $order = $this->orderRepository->get($postBody['orderId']);

            if ($order) {
                if ($postBody['status'] === 'SUCCESS') {

                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                    $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $order->addStatusToHistory($order->getStatus(), 'Payment Processed Successfully.');
                    $order->save();

                    $quote = $this->_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());

                    if ($quote->getId()) {
                        $quote->setIsActive(0)->setReservedOrderId(null)->save();
                        $this->_checkoutSession->replaceQuote($quote);
                    }

                }
            }
        }
    }

}
