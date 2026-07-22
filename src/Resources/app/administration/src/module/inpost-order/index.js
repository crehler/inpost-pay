import './extension/sw-order-detail';
import './component/inpost-order-transactions';
import plPL from './snippet/pl-PL.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('inpost-order', {
    type: 'plugin',
    name: 'InpostPay',
    title: 'inpost-order.general.title',
    description: 'inpost-order.general.description',
    version: '1.0.0',

    snippets: {
        'pl-PL': plPL,
        'en-GB': enGB,
    },

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                name: 'sw.order.detail.inpost',
                path: '/sw/order/detail/:id/inpost',
                component: 'inpost-order-transactions',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.viewer',
                },
            });
        }
        next(currentRoute);
    },
});
