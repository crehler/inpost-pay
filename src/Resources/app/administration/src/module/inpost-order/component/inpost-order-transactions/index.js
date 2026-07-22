import template from './inpost-order-transactions.html.twig';
import './inpost-order-transactions.scss';

const { Component, Mixin } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('inpost-order-transactions', {
    template,

    inject: ['InpostPayApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            transactions: [],
            pagination: {
                page: 0,
                perPage: 20,
                count: 0,
            },
            showRefundModal: false,
            selectedTransaction: null,
            refundAmount: null,
            externalRefundId: '',
            refundLoading: false,
        };
    },

    computed: {
        ...mapState('swOrderDetail', ['order']),

        orderId() {
            if (!this.order) return null;
            // InPost keys transactions by the merchant order id, which is the Shopware
            // order NUMBER (the "order ID by merchant" half of order_id), not order.id (UUID).
            // Verified against the live API: GET /v1/izi/transaction?order_id=<orderNumber>
            // returns the transaction whose full order_id is "<orderNumber>|<inpostOrderUuid>".
            return this.order.orderNumber;
        },

        transactionColumns() {
            return [
                { property: 'transaction_id', label: this.$tc('inpost-order.columns.transactionId') },
                { property: 'status', label: this.$tc('inpost-order.columns.status') },
                { property: 'amount', label: this.$tc('inpost-order.columns.amount') },
                { property: 'payment_method', label: this.$tc('inpost-order.columns.paymentMethod') },
                { property: 'created_date', label: this.$tc('inpost-order.columns.createdDate') },
            ];
        },
    },

    watch: {
        order: {
            immediate: true,
            handler() {
                if (this.order) {
                    this.loadTransactions();
                }
            },
        },
    },

    methods: {
        async loadTransactions() {
            if (!this.orderId) return;

            this.isLoading = true;
            try {
                const response = await this.InpostPayApiService.getTransactions(this.orderId);
                this.transactions = response.items || [];
                this.pagination = {
                    page: response.page,
                    perPage: response.per_page,
                    count: response.count,
                };
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('inpost-order.error.loadFailed'),
                });
                this.transactions = [];
            } finally {
                this.isLoading = false;
            }
        },

        formatAmount(amount, currency) {
            return new Intl.NumberFormat('pl-PL', {
                style: 'currency',
                currency: currency || 'PLN',
            }).format(amount);
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleString('pl-PL');
        },

        openRefundModal(transaction) {
            this.selectedTransaction = transaction;
            this.refundAmount = transaction.amount;
            this.externalRefundId = '';
            this.showRefundModal = true;
        },

        closeRefundModal() {
            this.showRefundModal = false;
            this.selectedTransaction = null;
            this.refundAmount = null;
            this.externalRefundId = '';
        },

        async submitRefund() {
            if (!this.selectedTransaction || !this.refundAmount) {
                return;
            }

            this.refundLoading = true;
            try {
                await this.InpostPayApiService.requestRefund(
                    this.selectedTransaction.transaction_id,
                    this.refundAmount,
                    this.externalRefundId || null
                );

                this.createNotificationSuccess({
                    message: this.$tc('inpost-order.refund.success'),
                });

                this.closeRefundModal();
                this.loadTransactions();
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('inpost-order.refund.error'),
                });
            } finally {
                this.refundLoading = false;
            }
        },
    },
});
