import template from './sw-order-detail.html.twig';

const { Component } = Shopware;

Component.override('sw-order-detail', {
    template,

    computed: {
        isInpostPayment() {
            if (!this.order || !this.order.transactions) {
                return false;
            }
            const transaction = this.order.transactions.last();
            return transaction?.paymentMethod?.handlerIdentifier?.includes('InpostPay');
        },
    },
});
