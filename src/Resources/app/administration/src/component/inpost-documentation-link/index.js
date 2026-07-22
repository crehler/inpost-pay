import template from './inpost-documentation-link.html.twig';
import './inpost-documentation-link.scss';

const { Component } = Shopware;

Component.register('inpost-documentation-link', {
    template,

    props: {
        url: {
            type: String,
            required: false,
            default: 'https://crehler.atlassian.net/wiki/x/AQCHlgn'
        },
        label: {
            type: String,
            required: false,
            default: 'Documentation'
        }
    },

    computed: {
        documentationUrl() {
            return this.url;
        },

        buttonLabel() {
            return this.label;
        }
    }
});
