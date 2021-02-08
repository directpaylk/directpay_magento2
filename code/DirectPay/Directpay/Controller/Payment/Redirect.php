<?php

namespace DirectPay\Directpay\Controller\Payment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;


class Redirect extends Action
{
    protected $logger;
    protected $resultRedirectFactory;
    protected $scopeConfig;
    protected $_orderFactory;
    protected $_quoteFactory;
    protected $_checkoutSession;

    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\OrderFactory $orderFactory,
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
        parent::__construct($context);
    }

    public function execute()
    {
        $publicKey = $this->scopeConfig->getValue('payment/directpay/publicKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $postBody = $this->getRequest()->getParams();

        if ($postBody['trnId'] == "-") {
            $this->messageManager->addErrorMessage(isset($postBody['desc'])?ucwords($postBody['desc']).'!':'Payment Failed!');
            $this->_redirect('checkout/cart', array('_secure' => false));
        } else {
            $signature = $postBody['signature'];
            $dataString = $postBody['orderId'] . $postBody['trnId'] . $postBody['status'] . $postBody['desc'];

            $signatureVerify = openssl_verify($dataString, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);

            if ($signatureVerify) {

                if ($postBody['status'] === 'SUCCESS') {
                    $this->messageManager->addSuccessMessage('Payment Successful!');
                    $this->_redirect('checkout/onepage/success', array('_secure' => false));
                } elseif ($postBody['status'] === 'FAILED') {
                    $this->messageManager->addErrorMessage('Payment Failed!');
                    $this->_redirect('checkout/cart', array('_secure' => false));
                } else {
                    $this->messageManager->addErrorMessage('Payment Failed! Invalid Payment Response.');
                    $this->_redirect('checkout/cart', array('_secure' => false));
                }

            } else {
                $this->messageManager->addErrorMessage('Payment Failed! Invalid Payment.');
                $this->_redirect('checkout/cart', array('_secure' => false));
            }
        }
    }
}
