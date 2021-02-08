============================================
***** Magento-2.x-DirectPay IPG Plugin *****
============================================

> Installation

!IMPORTANT: Please make sure that app/ directory is writable.

Copy code folder and its contents into app/ directory.
Then run below commands as root from app/ directory.

$ cd ..
$ bin/magento cache:clean
$ bin/magento cache:flush
$ bin/magento setup:upgrade
$ bin/magento module:enable DirectPay_Directpay --clear-static-content
$ bin/magento setup:di:compile

Navigate to 'https://<your_server_domain>/admin/' in your browser to configure DirectPay Payment.
Navigate to 'Stores > Configuration > Sales > Payment Methods'.
Find 'DirectPay' Payment Method.
Enter your DirectPay Merchant details and click 'Save Config'.
If DirectPay is not visible as a payment method, try clearing cache from 'System > Cache Management'.

