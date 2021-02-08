<?php
namespace DirectPay\Directpay\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;

final class DPConfigData implements ConfigProviderInterface
{
	const CODE = 'directpay';

	protected $scopeConfig;

    /**
     * AdminFailed constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        $config = [];

        $merchantId = $this->scopeConfig->getValue('payment/directpay/merchantid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->scopeConfig->getValue('payment/directpay/apikey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $paymode = $this->scopeConfig->getValue('payment/directpay/pay_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $privateKey = $this->scopeConfig->getValue('payment/directpay/privateKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $config['directpay'] = [
        	'merchant_id' => $merchantId,
        	'api_key' => $apiKey,
        	'pay_mode' => $paymode,
            'privateKey' => $privateKey
        ];

        return $config;
    }
}
