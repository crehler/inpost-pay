const LEVELS = {
    error: 0,
    warning: 1,
    info: 2,
    debug: 3,
};

const SENSITIVE_KEYS = [
    'name', 'firstname', 'lastname', 'surname',
    'phone', 'phonenumber', 'customerphone',
    'address', 'street', 'building', 'flat', 'city', 'postalcode',
    'taxid', 'taxidprefix', 'companyname',
];

const SENSITIVE_EMAIL_KEYS = ['email', 'mail', 'digitaldeliveryemail'];

function normalizeKey(key) {
    return key.replace(/(?<!^)(?=[A-Z])/g, '_').toLowerCase().replace(/[_-]/g, '');
}

function maskValue(value) {
    const length = value.length;
    if (length === 0) {
        return value;
    }

    const visible = length <= 4 ? 1 : length === 5 ? 2 : 3;

    return value.slice(0, visible) + '*'.repeat(length - visible);
}

function maskEmail(value) {
    const atPos = value.lastIndexOf('@');
    if (atPos === -1) {
        return maskValue(value);
    }

    return maskValue(value.slice(0, atPos)) + value.slice(atPos);
}

/**
 * Mirrors the PHP InpostPayLogger redaction: recursively walks arrays/objects and masks
 * values whose key name matches a known personal-data field, so any object logged here
 * (widget options, API responses, exceptions) is safe by default, not just allowlisted data.
 */
function redact(value, key = null) {
    if (key !== null) {
        const normalizedKey = normalizeKey(key);

        if (SENSITIVE_EMAIL_KEYS.includes(normalizedKey)) {
            return typeof value === 'string' ? maskEmail(value) : '[REDACTED]';
        }

        if (SENSITIVE_KEYS.includes(normalizedKey)) {
            return typeof value === 'string' ? maskValue(value) : '[REDACTED]';
        }
    }

    if (value instanceof Error) {
        return { message: value.message, name: value.name, stack: value.stack };
    }

    if (Array.isArray(value)) {
        return value.map((item) => redact(item));
    }

    if (value !== null && typeof value === 'object') {
        const redacted = {};
        for (const [k, v] of Object.entries(value)) {
            redacted[k] = redact(v, k);
        }

        return redacted;
    }

    return value;
}

/**
 * The plugin's only console logger. Configured once (see main.js) from the same
 * merchant-facing "Log Level" admin setting that drives PHP logging, so the storefront
 * console stays silent by default and only gets verbose when explicitly turned up.
 */
export default class InpostPayLogger {
    static configure(level) {
        this._level = Object.prototype.hasOwnProperty.call(LEVELS, level) ? level : 'error';
    }

    static error(message, ...data) {
        if (LEVELS.error <= LEVELS[this._level]) {
            console.error(`[InpostPay] ${message}`, ...data.map((d) => redact(d)));
        }
    }

    static warning(message, ...data) {
        if (LEVELS.warning <= LEVELS[this._level]) {
            console.warn(`[InpostPay] ${message}`, ...data.map((d) => redact(d)));
        }
    }

    static info(message, ...data) {
        if (LEVELS.info <= LEVELS[this._level]) {
            console.info(`[InpostPay] ${message}`, ...data.map((d) => redact(d)));
        }
    }

    static debug(message, ...data) {
        if (LEVELS.debug <= LEVELS[this._level]) {
            console.debug(`[InpostPay] ${message}`, ...data.map((d) => redact(d)));
        }
    }
}

InpostPayLogger._level = 'error';
