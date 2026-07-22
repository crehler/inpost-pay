import Plugin from 'src/plugin-system/plugin.class';

export default class InpostPayAnalyticsCapturePlugin extends Plugin {
    static options = {
        storageKey: 'inpost_pay_analytics',
        gclidParam: 'gclid',
        fbclidParam: 'fbclid',
        gaCookieName: '_ga',
    };

    init() {
        this._captureUrlParams();
        this._captureClientId();
    }

    _captureUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);

        const gclid = urlParams.get(this.options.gclidParam);
        const fbclid = urlParams.get(this.options.fbclidParam);

        if (gclid) {
            this._updateStorage('gclid', gclid);
        }

        if (fbclid) {
            this._updateStorage('fbclid', fbclid);
        }
    }

    _captureClientId() {
        const gaCookie = this._getCookie(this.options.gaCookieName);

        if (gaCookie) {
            const parts = gaCookie.split('.');
            if (parts.length >= 4) {
                const clientId = parts.slice(2).join('.');
                this._updateStorage('client_id', clientId);
            }
        }
    }

    _getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }

    _updateStorage(key, value) {
        try {
            const stored = JSON.parse(localStorage.getItem(this.options.storageKey) || '{}');
            stored[key] = value;
            localStorage.setItem(this.options.storageKey, JSON.stringify(stored));
        } catch (e) {
            // Ignore storage errors
        }
    }

    static getAnalyticsData() {
        try {
            return JSON.parse(localStorage.getItem('inpost_pay_analytics') || '{}');
        } catch (e) {
            return {};
        }
    }

    static clearAnalyticsData() {
        try {
            localStorage.removeItem('inpost_pay_analytics');
        } catch (e) {
            // Ignore
        }
    }
}
