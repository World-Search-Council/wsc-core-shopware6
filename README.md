# WSC Analytics & AI Bot Shield for Shopware 6

Official Shopware 6 plugin of the **World Search Council (WSC) Hybrid Analytics Standard**.

Provides cookieless, privacy-first analytics, Symfony HTTP kernel bot tracking, 100% ad-blocker immune server-side order tracking via `CheckoutOrderPlacedEvent`, local SQLite WAL queueing, and the standard **3-Card Anti-GA4 Smart Dashboard**.

---

## 🌟 Key Features

* **Cookieless & Privacy-First**: 100% GDPR and TTDSG/TDDDG compliant.
* **100% Ad-Blocker Immune Server-Side Order Tracking**:
  * Listens to Shopware 6's `CheckoutOrderPlacedEvent` on the Symfony kernel layer.
  * Records order revenue, line items, and currency directly into the local SQLite queue (`queue_temp.sqlite`) without depending on browser pixels or client JS.
* **Zero DB-Locking Dual SQLite WAL Architecture**:
  * Writes into `queue_temp.sqlite` without creating database lock overhead on Shopware's primary MySQL/MariaDB database.
  * Cached reports stored in `reports_temp.sqlite`.
* **Early HTTP Edge Bot Sensor**:
  * Captures AI crawlers (`GPTBot`, `ClaudeBot`), Live RAG bots (`ChatGPT-User`, `Perplexity`), and search engines (`Googlebot`, `Bingbot`) at `KernelEvents::REQUEST` (priority 250).
* **3-Card Smart Dashboard**:
  * **Card 1**: Traffic & E-Commerce Acquisition Channels (AI Citations, Search, Social, Direct).
  * **Card 2**: Strategic Growth Insights & Conversion Recommendations.
  * **Card 3**: AI Bot Shield Sensor Network & Data Quality.
* **Shopware 6 Scheduled Tasks**:
  * Automated micro-batch synchronization to the WSC Gateway (`POST /v1/ingest`) encrypted via AES-256-CBC and signed with HMAC-SHA256 (`X-WSC-Sig`).

---

## 📦 Directory Structure

```text
wsc-core-shopware6/
├── composer.json                       # Shopware 6 Platform Plugin definition
├── src/
│   ├── WscAnalytics.php                # Plugin base class
│   ├── Service/
│   │   ├── WscDatabaseManager.php      # SQLite WAL queue & reports manager
│   │   ├── WscBotTracker.php           # AI & Search bot taxonomy
│   │   └── WscCoreService.php          # AES-256 encryption, HMAC signing & Gateway sync
│   ├── Subscriber/
│   │   ├── WscRequestSubscriber.php    # KernelEvents::REQUEST bot interceptor
│   │   └── WscOrderSubscriber.php      # CheckoutOrderPlacedEvent server order hook
│   ├── ScheduledTask/
│   │   ├── WscSyncTask.php             # Scheduled task definition
│   │   └── WscSyncTaskHandler.php      # Background micro-batch pusher
│   ├── Storefront/
│   │   └── Controller/
│   │       ├── WscApiController.php    # /wsc/v1/track, /wsc/v1/webhook
│   │       └── WscDashboardController.php
│   └── Resources/
│       ├── config/
│       │   ├── config.xml              # Admin configuration
│       │   └── services.xml            # Symfony DI service container
│       ├── views/
│       │   └── storefront/
│       │       ├── layout/meta.html.twig # Tracker & window.WSC_CONFIG injection
│       │       └── page/wsc-dashboard.html.twig # 3-Card Dashboard
│       └── public/
│           └── static/
│               ├── js/
│               │   ├── wsc-tracker.js
│               │   └── wsc-dashboard.js
│               └── css/
│                   └── wsc-dashboard.css
└── README.md
```

---

## 🚀 Installation

1. Copy the plugin directory to `custom/plugins/WscAnalytics`.
2. Run in the Shopware 6 root directory:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate WscAnalytics
   bin/console cache:clear
   ```
3. Configure the plugin under **Settings → System → Plugins → WSC Analytics & AI Bot Shield**.
