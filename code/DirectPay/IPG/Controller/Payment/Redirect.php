<?php

namespace DirectPay\IPG\Controller\Payment;

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
    protected $_messageManager;
    protected $orderRepository;

    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
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
        $this->_messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        $status = $_GET['status'];
        $description = $_GET['description'];
        $desc = $_GET['desc'];
        $orderId = $_GET['orderId'];
        $trnId = $_GET['trnId'];

        if ($trnId == "-") {
            $this->_messageManager->addErrorMessage('Payment Failed. ' . $_GET['desc'] . '.');
            $this->_redirect('checkout/cart', array('_secure' => false));
        } else {
            if ($status == 'SUCCESS') {
                $this->_messageManager->addSuccessMessage('Payment Successful!');
                $this->_redirect('checkout/onepage/success', array('_secure' => false));
            } else {
                $this->_messageManager->addErrorMessage('Payment Failed. ' . $_GET['desc'] . '.');
                $this->_redirect('checkout/cart', array('_secure' => false));
            }
        }
    }
}
