import InpostPayPlugin from "./inpost-pay/inpost-pay.plugin";
import InpostPayAnalyticsCapturePlugin from "./inpost-pay/analytics-capture.plugin";
import InpostPayLogger from "./inpost-pay/logger";

const widgetEl = document.querySelector('[data-inpost-pay]');
let widgetOptions = {};
if (widgetEl) {
    try {
        const parsed = JSON.parse(widgetEl.dataset.inpostPayOptions || '{}');
        widgetOptions = (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : {};
    } catch {
        widgetOptions = {};
    }
}
InpostPayLogger.configure(widgetOptions.logLevel || 'error');

const PluginManager = window.PluginManager;
PluginManager.register("InpostPay", InpostPayPlugin, '[data-inpost-pay]');
PluginManager.register("InpostPayAnalyticsCapture", InpostPayAnalyticsCapturePlugin, 'body');
