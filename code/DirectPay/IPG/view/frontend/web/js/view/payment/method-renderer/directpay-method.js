/**
 * Supun Sadeepa
 * Magento 2.x DirectPay_IPG plugin
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/url'
    ],
    function (Component,
        $,
        quote,
        customer,
        placeOrderAction,
        selectPaymentMethodAction,
        customerData,
        fullScreenLoader,
        url
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'DirectPay_IPG/payment/directpay'
            },
            initialize: function() {
                this._super();
                self = this;
            },
            getCode: function () {
                return 'directpay';
            },
            isActive: function () {
                return true;
            },
            redirectAfterPlaceOrder: false,
            getData: function() {
                return {
                    'method': this.item.method
                };
            },
            afterPlaceOrder : function () {
                window.location.replace(url.build('directpay/payment/checkout'));
            },
            getDirectPayLogo : function(){
                return 'https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png';
            }
        });
    }
);
