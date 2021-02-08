/**
 * Supun Sadeepa
 * Magento 2.x DirectPay_IPG plugin
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'directpay',
                component: 'DirectPay_Directpay/js/view/payment/method-renderer/directpay-method'
            }
        );

        return Component.extend({});
    }
);
