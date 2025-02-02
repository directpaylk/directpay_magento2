# Magento-2.x-DirectPay IPG Plugin

## Installation

>Please make sure that `app/` directory is writable.

1. Copy `code` folder and its contents into `app/` directory.
2. Then run below commands as `root` from `app/` directory.
```
$ cd ..
$ bin/magento cache:clean 
$ bin/magento cache:flush 
$ bin/magento setup:upgrade
$ bin/magento module:enable DirectPay_Directpay --clear-static-content
$ bin/magento setup:di:compile
```
3. Navigate to `https://<your_server_domain>/admin/` in your browser to configure DirectPay Payment.
4. Navigate to ``Stores > Configuration > Sales > Payment Methods``.
5. Find ``DirectPay`` Payment Method.
6. Enter your DirectPay Merchant details and click ``Save Config``.

>If `DirectPay` is not visible as a payment method, try clearing cache from ``System > Cache Management``.

[Click here](https://www.directpay.lk/ipg/) to register for DirectPay.
