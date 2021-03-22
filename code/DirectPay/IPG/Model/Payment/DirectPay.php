<?php

namespace DirectPay\IPG\Model\Payment;


class DirectPay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'directpay';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::CODE;

    protected $_isInitializeNeeded = true;

    protected $_isOffline = true;

}
