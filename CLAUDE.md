# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

InPost Pay is a Shopware 6.6 plugin that integrates the InPost Pay payment platform with Shopware e-commerce. It enables multiple payment methods (BLIK, cards, Google Pay, Apple Pay, Pay by Link, deferred payments) and provides real-time basket synchronization with the InPost Pay mobile app.

## Common Commands

### Plugin Management
```bash
# Install and activate the plugin
bin/console plugin:refresh
bin/console plugin:install InpostPay --activate
bin/console cache:clear

# Run database migrations
bin/console database:migrate --all InpostPay
```

### Testing
```bash
# Run tests (from Shopware root directory)
./vendor/bin/phpunit --configuration custom/plugins/InpostPay/phpunit.xml

# Run single test file
./vendor/bin/phpunit --configuration custom/plugins/InpostPay/phpunit.xml tests/Path/To/TestFile.php
```

### Development
```bash
# Build storefront assets
bin/console theme:compile

# Build administration assets
bin/console bundle:dump
npm run --prefix vendor/shopware/administration/Resources/app/administration/ build

# Watch storefront JS during development
bin/watch-storefront.sh
```

### Logs
Plugin-specific logs are written to `var/log/inpostpay_*.log` (rotating, 7 days max).

## Architecture

The plugin follows **Domain-Driven Design (DDD)** with a clean layered architecture:

```
src/
├── Application/          # Application layer - orchestration logic
│   ├── Dto/             # Data Transfer Objects for API communication
│   ├── Facade/          # InpostPayFacadeInterface - main entry point
│   ├── Handler/         # Webhook handlers (payment, refund, settlement)
│   └── Service/         # Application services (BasketService, WebhookService, etc.)
│
├── Domain/              # Domain layer - business logic, no framework dependencies
│   ├── Aggregate/       # Domain aggregates (InpostBasket, Order)
│   ├── Entity/          # Domain entities (OrderLine, BasketProduct, DeliveryOption)
│   ├── ValueObject/     # Immutable value objects (PhoneNumber, PaymentType, etc.)
│   ├── Event/           # Domain events (BasketConfirmedEvent, etc.)
│   └── Exception/       # Domain-specific exceptions
│
├── Infrastructure/      # Infrastructure layer - Shopware/Symfony integration
│   ├── Api/             # REST API controllers (InpostController)
│   ├── Client/          # HTTP client for InPost API communication
│   ├── Facade/          # InpostPayFacade implementation
│   ├── Lifecycle/       # Plugin installers (PaymentMethod, CustomFieldSet)
│   ├── Persistence/     # DAL entities, repositories, migrations
│   ├── Provider/        # Data providers (products, delivery, config)
│   ├── Serializer/      # Request/response serialization
│   ├── Service/         # Infrastructure services (OrderService, CustomerService)
│   ├── StoreApi/        # Store API routes for storefront
│   ├── Storefront/      # Storefront controllers
│   └── Subscriber/      # Event subscribers (cart sync, checkout, context)
│
└── Resources/
    ├── config/          # services.yaml, config.xml, routes.yaml
    ├── views/           # Twig templates for storefront widgets
    └── app/
        ├── administration/  # Admin panel Vue.js components
        └── storefront/      # Storefront JS plugins
```

## Key Components

### InpostPayFacade
The main entry point (`InpostPayFacadeInterface`) orchestrates all operations:
- `bindBasket()` / `bindBasketWithProduct()` - Start InPost Pay session
- `handleBasketEvent()` - Process quantity/promo code changes from InPost app
- `createOrder()` / `getOrder()` - Order lifecycle
- `processWebhook()` - Handle payment status webhooks
- `getTransactions()` / `requestRefund()` - Admin transaction management

### API Endpoints (InpostController)
All InPost API routes are in `Infrastructure/Api/InpostController.php`:
- `POST /api/inpost/v1/izi/basket/{basketId}/confirmation` - Confirm basket
- `GET /api/inpost/v1/izi/basket/{basketId}` - Get basket data
- `POST /api/inpost/v1/izi/basket/{basketId}/event` - Handle basket events
- `POST /api/inpost/v1/izi/order` - Create order
- `POST /api/inpost/events` - Webhook endpoint

### Basket Session
`InpostBasketSessionEntity` persists the mapping between Shopware cart tokens and InPost basket IDs. Uses the `inpost_basket_session` table.

### Webhook Handlers
Located in `Application/Handler/Webhook/`:
- `PaymentWebhookHandler` - PAYMENT_AUTHORIZED, PAYMENT_DECLINED
- `RefundWebhookHandler` - REFUND, REFUND_DECLINED
- `SettlementWebhookHandler` - SETTLEMENT

### Event Subscribers
Key subscribers in `Infrastructure/Subscriber/`:
- `CartDesynchronizationSubscriber` - Syncs cart changes to InPost Pay
- `BasketConfirmationSubscriber` - Handles basket confirmation flow
- `CheckoutSubscriber` - Extends checkout with InPost Pay data

## Plugin Configuration

Configuration is defined in `Resources/config/config.xml` and includes:
- API credentials (Client ID, Client Secret, Post ID, Merchant credentials)
- Sandbox/Production mode toggle
- Payment method toggles (BLIK, Card, Google Pay, Apple Pay, etc.)
- Delivery method mapping (APM/Paczkomat, Courier, Digital)
- Widget display settings per location (product page, mini cart, cart, checkout)

## Domain Conventions

- Value Objects are immutable and validate themselves on construction
- DTOs use `fromArray()` static factory methods for deserialization
- Exceptions extend domain-specific base classes
- All monetary values use minor units (grosze) as integers
