import InpostPayApiService from '../core/service/api/inpost-pay-api.service';

const { Application } = Shopware;

Application.addServiceProvider('InpostPayApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new InpostPayApiService(initContainer.httpClient, container.loginService);
});
