import Plugin from 'src/plugin-system/plugin.class';
import InpostPayAnalyticsCapturePlugin from './analytics-capture.plugin';
import InpostPayLogger from './logger';

export default class InpostPayPlugin extends Plugin {
    static options = {
        merchantClientId: null,
        basketBindingApiKey: null,
    };

    /**
     * Initialize the InPostPay widget globally (only once per page)
     */
    init() {
        InpostPayLogger.debug('Plugin options:', this.options);

        // Check if widget is already initialized globally
        if (window.inpostPayWidget) {
            InpostPayLogger.debug('Widget already initialized, skipping...');
            return;
        }

        this._initializeWidget();

        this._registerOffCanvasListener();

        this._registerPopupObserver();
    }

    _registerOffCanvasListener() {
        const offcanvasTrigger = document.querySelector('[data-off-canvas-cart], [data-offcanvas-cart]');

          if (!offcanvasTrigger) {
                  InpostPayLogger.warning('Off-canvas cart trigger element not found');
                  return;
              }

          InpostPayLogger.debug('Found off-canvas cart trigger, attempting to subscribe...');

          // Get the OffCanvasCart plugin instance from this element
          const pluginInstances = window.PluginManager.getPluginInstancesFromElement(offcanvasTrigger);
          const offcanvasCartPlugin = pluginInstances.get('OffCanvasCart');

          if (!offcanvasCartPlugin) {
                  InpostPayLogger.warning('OffCanvasCart plugin instance not found on trigger element');
                  return;
              }

          InpostPayLogger.debug('Successfully found OffCanvasCart plugin instance, subscribing to offCanvasOpened ' +
              'event');

          // Subscribe to the offCanvasOpened event
          offcanvasCartPlugin.$emitter.subscribe('offCanvasOpened', () => {
                  InpostPayLogger.debug('Received offCanvasOpened event!');
                  this._onOffCanvasOpened();
              });
       }

    /**
     * Initialize the InPostPay widget
     * @private
     */
    _initializeWidget() {
        if (typeof window.InPostPayWidget === 'undefined') {
            InpostPayLogger.warning('InPostPayWidget is not available yet. Waiting for script to load...');
            this._waitForWidget();
            return;
        }

        this._initWidgetInstance();
    }

    /**
     * Wait for the InPostPayWidget script to load
     * @private
     */
    _waitForWidget() {
        const maxRetries = 50;
        let retries = 0;

        const checkInterval = setInterval(() => {
            retries++;

            if (typeof window.InPostPayWidget !== 'undefined') {
                clearInterval(checkInterval);
                this._initWidgetInstance();
                return;
            }

            if (retries >= maxRetries) {
                clearInterval(checkInterval);
                InpostPayLogger.error('Failed to load InPostPayWidget after multiple retries');
            }
        }, 100);
    }

    /**
     * Create and initialize the widget instance globally
     * @private
     */
    _initWidgetInstance() {
        // Prevent multiple initializations
        if (window.inpostPayWidget) {
            InpostPayLogger.debug('Widget already initialized');
            return;
        }

        const options = {
            merchantClientId: this.options.merchantClientId,
            unboundWidgetClicked: this._onUnboundWidgetClicked.bind(this),
            handleBasketEvent: this._handleBasketEvent.bind(this), // TODO: Uncomment after fixing basket deletion issue
        };

        // Only add basketBindingApiKey if it's not null, undefined, or empty string
        // InPost Pay widget requires undefined (not null) when basket is not bound
        if (this.options.basketBindingApiKey !== null &&
            this.options.basketBindingApiKey !== undefined &&
            this.options.basketBindingApiKey !== '') {
            options.basketBindingApiKey = this.options.basketBindingApiKey;
        }

        try {
            InpostPayLogger.debug('=== Initializing widget ===');
            InpostPayLogger.debug('Init options:', options);

            // Initialize widget globally - it will find all <inpost-izi-button> placeholders in DOM
            window.inpostPayWidget = window.InPostPayWidget.init(options);

            InpostPayLogger.debug('Widget initialized successfully');
            InpostPayLogger.debug('Widget instance:', window.inpostPayWidget);
            InpostPayLogger.debug('Widget has refresh method?', typeof window.inpostPayWidget.refresh === 'function');

            // Emit custom event for other scripts
            this.$emitter.publish('InpostPayWidgetInitialized', {
                widget: window.inpostPayWidget,
                options: options,
            });
        } catch (error) {
            InpostPayLogger.error('Failed to initialize widget:', error);
            InpostPayLogger.error('Error stack:', error instanceof Error ? error.stack : String(error));
        }
    }

    /**
     * Detect the context (page type) where the button was clicked
     * Uses binding_place attribute from the button element
     * @private
     * @returns {string} One of: PRODUCT_CARD, BASKET_SUMMARY, MINICART_PAGE
     */
    _detectButtonContext() {
        // Find all visible buttons with binding_place attribute
        const buttons = document.querySelectorAll('inpost-izi-button[binding_place]');

        for (const button of buttons) {
            const rect = button.getBoundingClientRect();
            const isVisible = rect.width > 0 && rect.height > 0;

            if (isVisible) {
                const bindingPlace = button.getAttribute('binding_place');
                InpostPayLogger.debug('Detected binding_place:', bindingPlace);
                return bindingPlace;
            }
        }

        // Fallback: check for product button (has data-product-id)
        const productButton = document.querySelector('inpost-izi-button[data-product-id]');
        if (productButton) {
            InpostPayLogger.debug('Fallback: Found product button');
            return 'PRODUCT_CARD';
        }

        // Default fallback: assume basket context
        InpostPayLogger.debug('Fallback: Assuming BASKET_SUMMARY');
        return 'BASKET_SUMMARY';
    }

    /**
     * Bind basket with a new product (Product Detail Page context)
     * Adds product to cart and binds with InPost Pay
     * @param {string} productId - The product ID to add
     * @private
     */
    async _bindBasketWithProduct(productId) {
        InpostPayLogger.debug('Binding basket WITH product');
        InpostPayLogger.debug('Product ID:', productId);

        // Validate productId
        if (!productId || productId === 'unknown' || productId === '0') {
            throw new Error('Invalid product ID for product context');
        }

        // Get quantity from product detail inputs
        const quantityInput = document.querySelector('.product-detail-quantity-select, .product-detail-quantity-input');
        const quantity = quantityInput ? parseInt(quantityInput.value) : 1;

        InpostPayLogger.debug('Selected quantity:', quantity);
        InpostPayLogger.debug('Calling unified API: /checkout/inpost/basket/bind WITH product');

        const analytics = InpostPayAnalyticsCapturePlugin.getAnalyticsData();

        const response = await fetch('/checkout/inpost/basket/bind', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                productId: productId,
                quantity: quantity,
                analytics: analytics,
            }),
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || error.message || 'Failed to bind basket');
        }

        const data = await response.json();
        InpostPayLogger.debug('=== API Response received ===');
        InpostPayLogger.debug('Full response data:', data);
        InpostPayLogger.debug('basketBindingApiKey:', data.basketBindingApiKey);
        InpostPayLogger.debug('basketId:', data.basketId);
        InpostPayLogger.debug('cartItemCount:', data.cartItemCount);

        // Update cart badge count
        this._updateCartBadge(data.cartItemCount);

        // Show success notification
        this._showNotification('Produkt dodany do koszyka', 'success');

        return data;
    }

    /**
     * Bind existing basket with InPost Pay (Basket/Cart pages context)
     * Does not add any product - only binds the current cart
     * @private
     */
    async _bindExistingBasket() {
        InpostPayLogger.debug('Binding EXISTING basket (no product)');
        InpostPayLogger.debug('Calling unified API: /checkout/inpost/basket/bind WITHOUT product');

        const analytics = InpostPayAnalyticsCapturePlugin.getAnalyticsData();

        const response = await fetch('/checkout/inpost/basket/bind', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                analytics: analytics,
            }),
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || error.message || 'Failed to bind basket');
        }

        const data = await response.json();
        InpostPayLogger.debug('=== API Response received ===');
        InpostPayLogger.debug('Full response data:', data);
        InpostPayLogger.debug('basketBindingApiKey:', data.basketBindingApiKey);

        // Show success notification
        this._showNotification('Koszyk powiązany z InPost Pay', 'success');

        return data;
    }

    /**
     * Handle unbound widget clicks (when no product ID is bound)
     * Detects context and calls appropriate binding method
     * @param {string} productId - The product ID from the clicked button (may be undefined on cart pages)
     * @private
     */
    async _onUnboundWidgetClicked(productId) {
        InpostPayLogger.debug('=== START: Unbound widget clicked ===');
        InpostPayLogger.debug('Received productId parameter:', productId);
        InpostPayLogger.debug('Current basketBindingApiKey:', this.options.basketBindingApiKey);

        try {
            // Show loading state
            this._setLoadingState(true);

            // Detect context (PRODUCT_CARD, BASKET_SUMMARY, or MINICART_PAGE)
            const context = this._detectButtonContext();
            InpostPayLogger.debug('Context detected:', context);

            let apiResponse;

            // Check if productId is valid for product context binding
            const hasValidProductId = productId && productId !== 'unknown' && productId !== '0' && productId !== 'undefined';

            // Choose appropriate action based on context AND valid productId
            // Only use _bindBasketWithProduct when BOTH context is PRODUCT_CARD AND productId is valid
            if (context === 'PRODUCT_CARD' && hasValidProductId) {
                // Product page with valid product: add product to cart + bind basket
                apiResponse = await this._bindBasketWithProduct(productId);
            } else {
                // All other cases: only bind existing cart (no product)
                // This includes: MINICART_PAGE, BASKET_SUMMARY, CHECKOUT_PAGE, REGISTERFORM_PAGE
                // Or PRODUCT_CARD context without valid productId (e.g., clicked from mini-cart overlay)
                if (context === 'PRODUCT_CARD' && !hasValidProductId) {
                    InpostPayLogger.debug('PRODUCT_CARD context but invalid productId, falling back to bindExistingBasket');
                }
                apiResponse = await this._bindExistingBasket();
            }

            InpostPayLogger.debug('=== BEFORE updating options ===');
            InpostPayLogger.debug('OLD this.options.basketBindingApiKey:', this.options.basketBindingApiKey);

            // Update widget configuration with new basketBindingApiKey
            this.options.basketBindingApiKey = apiResponse.basketBindingApiKey;

            InpostPayLogger.debug('=== AFTER updating options ===');
            InpostPayLogger.debug('NEW this.options.basketBindingApiKey:', this.options.basketBindingApiKey);

            // Emit custom event for other scripts to handle
            this.$emitter.publish('InpostPayUnboundWidgetClicked', {
                productId: productId,
                context: context,
                basketBindingApiKey: apiResponse.basketBindingApiKey,
            });

            InpostPayLogger.debug('=== RETURNING basketBindingApiKey to widget ===');
            InpostPayLogger.debug('Returning:', apiResponse.basketBindingApiKey);

            // Return basketBindingApiKey to InPost widget callback
            return apiResponse.basketBindingApiKey;

        } catch (error) {
            InpostPayLogger.error('=== ERROR occurred ===');
            InpostPayLogger.error('Error:', error);
            InpostPayLogger.error('Error stack:', error instanceof Error ? error.stack : String(error));
            this._showNotification('Nie udało się przetworzyć żądania', 'error');
            throw error;

        } finally {
            InpostPayLogger.debug('=== FINALLY: Cleanup ===');
            this._setLoadingState(false);
            InpostPayLogger.debug('=== END: Unbound widget clicked ===');
        }
    }

    /**
     * Update cart badge counter in header
     * @param {number} count - New cart item count
     * @private
     */
    _updateCartBadge(count) {
        // Find cart badge element(s)
        const badges = document.querySelectorAll('.header-cart-count, .cart-widget .badge');

        badges.forEach(badge => {
            badge.textContent = count;

            // Add pulse animation for visual feedback
            badge.classList.add('animate-pulse');
            setTimeout(() => {
                badge.classList.remove('animate-pulse');
            }, 1000);
        });

        // Publish event for other plugins that might listen
        this.$emitter.publish('inpostPayCartUpdated', { count });
    }

    /**
     * Show notification to user
     * @param {string} message - Message to display
     * @param {string} type - Type: 'success', 'error', 'warning', 'info'
     * @private
     */
    _showNotification(message, type = 'success') {
        // Try to use Shopware's FlashMessage system
        try {
            const event = new CustomEvent('flashMessage', {
                detail: {
                    message: message,
                    type: type,
                },
            });
            window.dispatchEvent(event);
        } catch (e) {
            // Fallback to simple alert if FlashMessage not available
            InpostPayLogger.debug(`${type.toUpperCase()}: ${message}`);
        }
    }

    /**
     * Set loading state for widget buttons
     * @param {boolean} isLoading - Whether to show loading state
     * @private
     */
    _setLoadingState(isLoading) {
        const buttons = document.querySelectorAll('inpost-izi-button');

        buttons.forEach(button => {
            if (isLoading) {
                button.style.opacity = '0.5';
                button.style.pointerEvents = 'none';
                button.setAttribute('disabled', 'true');
            } else {
                button.style.opacity = '1';
                button.style.pointerEvents = 'auto';
                button.removeAttribute('disabled');
            }
        });
    }

    /**
     * Get the initialized widget instance
     * @returns {*|null}
     */
    getWidget() {
        return window.inpostPayWidget || null;
    }

    /**
     * Check binding status from backend
     * Fetches current cart binding state to detect desynchronization
     * @private
     * @returns {Promise<{bound: boolean, basketBindingApiKey: string|null}>}
     */
    async _checkBindingStatus() {
        InpostPayLogger.debug('Checking binding status...');

        try {
            const response = await fetch('/checkout/inpost/basket/status', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                InpostPayLogger.warning('Binding status check failed with status:', response.status);
                return { bound: false, basketBindingApiKey: null };
            }

            const data = await response.json();
            InpostPayLogger.debug('Binding status response:', data);

            return {
                bound: data.bound || false,
                basketBindingApiKey: data.basketBindingApiKey || null,
            };
        } catch (error) {
            InpostPayLogger.error('Error checking binding status:', error);
            return { bound: false, basketBindingApiKey: null };
        }
    }

    /**
     * Reset widget state by clearing basketBindingApiKey and reinitializing widget
     * Used when cart binding becomes invalid (e.g., cart reassociation with different phone number)
     * @private
     */
    _resetWidgetState() {
        InpostPayLogger.debug('=== Resetting widget state ===');
        InpostPayLogger.debug('Current basketBindingApiKey:', this.options.basketBindingApiKey);

        // Clear the basketBindingApiKey from plugin options
        this.options.basketBindingApiKey = null;

        InpostPayLogger.debug('Cleared basketBindingApiKey, now null');

        // Destroy existing widget instance
        if (window.inpostPayWidget) {
            InpostPayLogger.debug('Destroying existing widget instance');
            window.inpostPayWidget = null;
        }

        // Reinitialize widget without basketBindingApiKey
        InpostPayLogger.debug('Reinitializing widget without basketBindingApiKey');
        this._initWidgetInstance();

        // Emit event for other scripts
        this.$emitter.publish('InpostPayWidgetStateReset', {
            reason: 'cart_reassociation',
        });

        InpostPayLogger.debug('=== Widget state reset complete ===');
    }

    /**
     * Handle basket events from InPost Pay widget
     * @param {string} event - Event type: 'basketDeleted', 'basketProductChanged', 'orderCreated'
     * @returns {boolean|Promise<boolean>} - true to prevent page refresh
     * @private
     */
    async _handleBasketEvent(event) {
        InpostPayLogger.debug('Received basket event:', event);

        if (event === 'orderCreated') {
            try {
                const response = await fetch('/checkout/inpost/order/confirmation-url', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    InpostPayLogger.error('Failed to get confirmation URL, status:', response.status);
                    return false;
                }

                const data = await response.json();

                if (data.url) {
                    InpostPayLogger.debug('Redirecting to finish page:', data.url);
                    window.location.href = data.url;
                    return true; // Prevent widget from refreshing page
                }

                InpostPayLogger.warning('No URL in response, allowing page refresh');
            } catch (error) {
                InpostPayLogger.error('Error handling orderCreated event:', error);
            }
        }

        // For other events (basketDeleted, basketProductChanged) - let widget refresh page
        return false;
    }

    /**
     * Handle off-canvas cart opening - check binding status and refresh widget
     * Detects desynchronization between frontend widget state and backend session
     * @private
     */
    async _onOffCanvasOpened() {
        InpostPayLogger.debug('Off-canvas cart opened, checking binding status...');

        if (!window.inpostPayWidget) {
            InpostPayLogger.warning('Widget not initialized yet when off-canvas opened');
            return;
        }

        // Check binding status from backend to detect desynchronization
        const status = await this._checkBindingStatus();

        // Detect desync: backend says unbound but frontend still has basketBindingApiKey
        const frontendHasBinding = this.options.basketBindingApiKey !== null &&
                                   this.options.basketBindingApiKey !== undefined &&
                                   this.options.basketBindingApiKey !== '';
        const backendSaysUnbound = !status.bound;

        if (frontendHasBinding && backendSaysUnbound) {
            InpostPayLogger.debug('Widget state reset after desync');
            InpostPayLogger.debug('Frontend had basketBindingApiKey:', this.options.basketBindingApiKey);
            InpostPayLogger.debug('Backend says bound:', status.bound);
            this._resetWidgetState();

            // After reset, refresh widget to render buttons in dynamically loaded content (offcanvas)
            if (window.inpostPayWidget && typeof window.inpostPayWidget.refresh === 'function') {
                window.inpostPayWidget.refresh();
                InpostPayLogger.debug('Widget refreshed after state reset');
            }
            return;
        }

        // Normal case: just refresh widget for new DOM elements
        if (typeof window.inpostPayWidget.refresh !== 'function') {
            InpostPayLogger.warning('Widget refresh method not available');
            return;
        }

        try {
            window.inpostPayWidget.refresh();
            InpostPayLogger.debug('Widget refreshed successfully for off-canvas cart');
        } catch (error) {
            InpostPayLogger.error('Error refreshing widget:', error);
        }
    }

    _registerPopupObserver() {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE && node.id === 'inpostpay-widget-popup') {
                        this._onPopupAdded();
                    }
                }
                for (const node of mutation.removedNodes) {
                    if (node.nodeType === Node.ELEMENT_NODE && node.id === 'inpostpay-widget-popup') {
                        this._onPopupRemoved();
                    }
                }
            }
        });

        observer.observe(document.body, { childList: true });
    }

    _onPopupAdded() {
        const offcanvas = document.querySelector('.offcanvas.show');
        if (offcanvas) {
            offcanvas.dataset.inpostpayHidden = 'true';
            offcanvas.style.visibility = 'hidden';
        }
    }

    _onPopupRemoved() {
        const offcanvas = document.querySelector('.offcanvas[data-inpostpay-hidden="true"]');
        if (offcanvas) {
            offcanvas.style.visibility = 'visible';
            delete offcanvas.dataset.inpostpayHidden;
        }
    }
}
