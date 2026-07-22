const ApiService = Shopware.Classes.ApiService;

class InpostPayApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'inpost') {
        super(httpClient, loginService, apiEndpoint);
    }

    getTransactions(orderId) {
        return this.httpClient.get(
            '/v1/izi/transaction',
            {
                params: { order_id: orderId },
                headers: this.getBasicHeaders(),
            }
        ).then(ApiService.handleResponse.bind(this));
    }

    requestRefund(transactionId, refundAmount, externalRefundId = null) {
        const data = {
            refund_amount: refundAmount,
        };

        if (externalRefundId) {
            data.external_refund_id = externalRefundId;
        }

        return this.httpClient.post(
            `/v1/izi/transaction/${transactionId}/refund`,
            data,
            {
                headers: {
                    ...this.getBasicHeaders(),
                    'X-Command-ID': this.generateUuid(),
                },
            }
        ).then(ApiService.handleResponse.bind(this));
    }

    generateUuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
}

export default InpostPayApiService;
