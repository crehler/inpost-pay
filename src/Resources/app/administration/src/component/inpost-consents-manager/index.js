import template from './inpost-consents-manager.html.twig';
import './inpost-consents-manager.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('inpost-consents-manager', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    emits: ['update:value'],

    props: {
        value: {
            type: Array,
            required: false,
            default: () => [],
        },
    },

    data() {
        return {
            consents: [],
            isLoading: false,
            showModal: false,
            currentConsent: null,
            currentConsentIndex: null,
            requirementTypes: [
                { value: 'REQUIRED_ONCE', label: this.$tc('inpost-consents-manager.requirementTypes.requiredOnce') },
                { value: 'REQUIRED_ALWAYS', label: this.$tc('inpost-consents-manager.requirementTypes.requiredAlways') },
                { value: 'OPTIONAL', label: this.$tc('inpost-consents-manager.requirementTypes.optional') },
            ],
            linkTypes: [
                { value: 'cms_page', label: this.$tc('inpost-consents-manager.linkTypes.cmsPage') },
                { value: 'category', label: this.$tc('inpost-consents-manager.linkTypes.category') },
                { value: 'external', label: this.$tc('inpost-consents-manager.linkTypes.external') },
            ],
            availableLocales: ['pl-PL', 'en-GB', 'de-DE'],
            activeLocale: 'pl-PL',
        };
    },

    computed: {
        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        cmsPageCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('type', 'page'));
            criteria.setLimit(100);
            return criteria;
        },

        categoryCriteria() {
            const criteria = new Criteria();
            criteria.setLimit(100);
            return criteria;
        },

        columns() {
            return [
                {
                    property: 'id',
                    label: this.$tc('inpost-consents-manager.columns.id'),
                    primary: true,
                },
                {
                    property: 'requirementType',
                    label: this.$tc('inpost-consents-manager.columns.requirementType'),
                },
                {
                    property: 'enabled',
                    label: this.$tc('inpost-consents-manager.columns.status'),
                },
            ];
        },

        getRequirementTypeLabel() {
            return (type) => {
                const found = this.requirementTypes.find(t => t.value === type);
                return found ? found.label : type;
            };
        },
    },

    watch: {
        value: {
            immediate: true,
            handler(newValue) {
                if (Array.isArray(newValue)) {
                    this.consents = JSON.parse(JSON.stringify(newValue));
                } else {
                    this.consents = [];
                }
            },
        },
    },

    methods: {
        getEmptyConsent() {
            return {
                id: '',
                enabled: true,
                requirementType: 'OPTIONAL',
                version: '1.0',
                linkType: 'external',
                cmsPageId: null,
                externalLink: '',
                translations: {
                    'pl-PL': { description: '', labelLink: '' },
                    'en-GB': { description: '', labelLink: '' },
                    'de-DE': { description: '', labelLink: '' },
                },
            };
        },

        addConsent() {
            this.currentConsent = this.getEmptyConsent();
            this.currentConsentIndex = null;
            this.activeLocale = 'pl-PL';
            this.showModal = true;
        },

        editConsent(item) {
            const index = this.consents.findIndex(c => c.id === item.id);
            this.currentConsent = JSON.parse(JSON.stringify(item));
            this.currentConsentIndex = index;
            this.activeLocale = 'pl-PL';

            // Ensure translations object exists for all locales
            this.availableLocales.forEach(locale => {
                if (!this.currentConsent.translations[locale]) {
                    this.currentConsent.translations[locale] = { description: '', labelLink: '' };
                }
            });

            this.showModal = true;
        },

        deleteConsent(item) {
            const index = this.consents.findIndex(c => c.id === item.id);
            if (index > -1) {
                this.consents.splice(index, 1);
                this.emitUpdate();
                this.createNotificationSuccess({
                    message: this.$tc('inpost-consents-manager.messages.deleteSuccess'),
                });
            }
        },

        closeModal() {
            this.showModal = false;
            this.currentConsent = null;
            this.currentConsentIndex = null;
        },

        saveConsent() {
            if (!this.validateConsent()) {
                return;
            }

            if (this.currentConsentIndex !== null) {
                // Update existing
                this.consents[this.currentConsentIndex] = JSON.parse(JSON.stringify(this.currentConsent));
            } else {
                // Add new
                this.consents.push(JSON.parse(JSON.stringify(this.currentConsent)));
            }

            this.emitUpdate();
            this.closeModal();

            this.createNotificationSuccess({
                message: this.$tc('inpost-consents-manager.messages.saveSuccess'),
            });
        },

        validateConsent() {
            if (!this.currentConsent.id || this.currentConsent.id.trim() === '') {
                this.createNotificationError({
                    message: this.$tc('inpost-consents-manager.validation.idRequired'),
                });
                return false;
            }

            // Check for duplicate ID (only when adding new)
            if (this.currentConsentIndex === null) {
                const exists = this.consents.some(c => c.id === this.currentConsent.id);
                if (exists) {
                    this.createNotificationError({
                        message: this.$tc('inpost-consents-manager.validation.idExists'),
                    });
                    return false;
                }
            }

            // Validate at least one translation has description
            const hasDescription = this.availableLocales.some(locale => {
                const trans = this.currentConsent.translations[locale];
                return trans && trans.description && trans.description.trim() !== '';
            });

            if (!hasDescription) {
                this.createNotificationError({
                    message: this.$tc('inpost-consents-manager.validation.descriptionRequired'),
                });
                return false;
            }

            return true;
        },

        emitUpdate() {
            this.$emit('update:value', this.consents);
        },

        toggleEnabled(item) {
            const index = this.consents.findIndex(c => c.id === item.id);
            if (index > -1) {
                this.consents[index].enabled = !this.consents[index].enabled;
                this.emitUpdate();
            }
        },

        onLocaleChange(locale) {
            this.activeLocale = locale;
        },

        getCurrentTranslation() {
            if (!this.currentConsent || !this.currentConsent.translations) {
                return { description: '', labelLink: '' };
            }
            return this.currentConsent.translations[this.activeLocale] || { description: '', labelLink: '' };
        },

        updateCurrentTranslation(field, value) {
            if (!this.currentConsent.translations[this.activeLocale]) {
                this.currentConsent.translations[this.activeLocale] = { description: '', labelLink: '' };
            }
            this.currentConsent.translations[this.activeLocale][field] = value;
        },
    },
});
