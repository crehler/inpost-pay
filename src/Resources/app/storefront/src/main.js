import InpostPayPlugin from "./inpost-pay/inpost-pay.plugin";
import InpostPayAnalyticsCapturePlugin from "./inpost-pay/analytics-capture.plugin";

const PluginManager = window.PluginManager;
PluginManager.register("InpostPay", InpostPayPlugin, '[data-inpost-pay]');
PluginManager.register("InpostPayAnalyticsCapture", InpostPayAnalyticsCapturePlugin, 'body');