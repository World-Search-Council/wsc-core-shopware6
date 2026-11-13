# WSC Analytics & AI Bot Shield for Shopware 6 (`v2.2.0`)

<p align="center">
  <strong>Official Shopware 6 Plugin of the World Search Council (WSC) Standard</strong><br>
  100% Cookieless Storefront Analytics, Symfony Kernel AI Bot Sensor, Ad-Blocker Proof Order Tracking & Canonical 7-Card Bento Suite
</p>

<p align="center">
  <a href="https://www.worldsearchcouncil.org"><img src="https://img.shields.io/badge/Portal-worldsearchcouncil.org-0F172A?style=flat-square" alt="World Search Council"></a>
  <img src="https://img.shields.io/badge/Version-2.2.0-189EFF?style=flat-square&logo=shopware" alt="Shopware 6 v2.2.0">
  <img src="https://img.shields.io/badge/Shopware-6.5_|_6.6-189EFF?style=flat-square" alt="Shopware 6">
  <img src="https://img.shields.io/badge/Privacy-100%25_Cookieless-10B981?style=flat-square" alt="Cookieless">
  <img src="https://img.shields.io/badge/License-GPL--2.0--or--later-orange?style=flat-square" alt="License">
</p>

---

## 🌟 Key Features (`v2.2.0`)

* **100% Cookieless & Privacy-First**: GDPR, TDDDG, and ePrivacy compliant without consent banner friction — rotating daily salted session hashes (`SHA256(IP + UA + DailySalt)`) and zero stored raw IPs.
* **100% Ad-Blocker Immune Server-Side Order Tracking**:
  * Subscribes to Shopware's `CheckoutOrderPlacedEvent` (`WscOrderSubscriber`) on the Symfony kernel layer, capturing order totals, currency, and line items directly into `queue_temp.sqlite`.
* **Early Symfony HTTP Kernel Bot Sensor**:
  * Intercepts AI Retrieval/RAG bots (`ChatGPT-User`, `OAI-SearchBot`, `PerplexityBot`), AI Training scrapers (`GPTBot`, `ClaudeBot`, `CCBot`), and Search spiders (`Googlebot`, `Bingbot`) at `KernelEvents::REQUEST` (priority 250).
* **Zero DB-Locking Dual SQLite WAL Storage**:
  * Isolates high-frequency event writes (`queue_temp.sqlite`) and cached Bento snapshots (`reports_temp.sqlite`) in SQLite WAL mode, protecting Shopware's primary MySQL/MariaDB database from lock contention.
* **Canonical 7-Card Bento Suite & Multi-Period Switcher (`1d`, `7d`, `30d`, `90d`, `365d`)**:
  1. **`card1_traffic`** — Real Visitors, Pageviews, Orders, Revenue & Channel Attribution (AI, Search, Social, Direct)
  2. **`card2_insights`** — Algorithmic Growth Insights & Conversion Recommendations
  3. **`card3_bots`** — AI Bot Shield Sensor Network (Live RAG vs. Training vs. Search Crawler Ratio)
  4. **`card_engagement`** — Engaged Reading Time, TTFI, Scroll Milestones & UX Verdict Badges
  5. **`card_outbound`** — Categorized External Link Clicks
  6. **`card_ecommerce`** — 4-Stage Checkout Benchmark Ampel (`Views` → `Cart` → `Checkout` → `Purchase`) & Funnel Leak Diagnosis
  7. **`domain_health` + `pagespeed`** — Port-25-Free Domain Sentinel & **SpeedSentinel 2.0 Dual-Thermometer** (🤖 RAG Readiness vs. 👤 Human CWV)
* **Asynchronous Diagnostics & Scheduled Task Sync**:
  * Built-in REST endpoints (`/wsc/v1/cards`, `/wsc/v1/pagespeed/audit`, `/wsc/v1/sentinel/scan`) with live diagnostic progress banner, plus Shopware Scheduled Task (`WscSyncTask`) pushing AES-256-CBC encrypted, HMAC-SHA256 (`X-WSC-Sig`) signed batches to `https://api.worldsearchcouncil.org`.

---

## 📦 Directory Structure

```text
wsc-core-shopware6/
├── composer.json                        # wsc/shopware6-analytics v2.2.0
└── src/
    ├── WscAnalytics.php                 # Shopware 6 Plugin bootstrap
    ├── Service/
    │   ├── WscDatabaseManager.php       # SQLite WAL queue & 7-Card report storage
    │   ├── WscBotTracker.php            # Canonical AI/RAG/Search bot taxonomy
    │   └── WscCoreService.php           # 7-Card persistence, AES-256, HMAC & Gateway triggers
    ├── Subscriber/
    │   ├── WscRequestSubscriber.php     # Early KernelEvents::REQUEST bot sensor
    │   └── WscOrderSubscriber.php       # CheckoutOrderPlacedEvent server-side conversion hook
    ├── ScheduledTask/
    │   ├── WscSyncTask.php              # Shopware Scheduled Task registration
    │   └── WscSyncTaskHandler.php       # Encrypted micro-batch pusher
    ├── Storefront/Controller/
    │   ├── WscApiController.php         # /wsc/v1/track, /webhook, /cards, /pagespeed/audit, /sentinel/scan
    │   └── WscDashboardController.php   # 7-Card Bento Dashboard controller
    └── Resources/
        ├── config/
        │   ├── config.xml               # Admin plugin configuration
        │   └── services.xml             # Symfony DI container definitions
        ├── views/storefront/
        │   ├── layout/meta.html.twig    # First-party tracker & window.WSC_CONFIG injection
        │   └── page/wsc-dashboard.html.twig # 7-Card Bento Grid template
        └── public/static/
            ├── js/
            │   ├── wsc-tracker.js       # Canonical Vanilla ES6 tracker
            │   └── wsc-dashboard.js     # Period switcher & live diagnostic polling
            └── css/
                └── wsc-dashboard.css    # Responsive Bento Grid styles
```

---

## 🚀 Installation

1. Copy this directory into `custom/plugins/WscAnalytics`.
2. Activate the plugin via the Shopware CLI:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate WscAnalytics
   bin/console cache:clear
   ```
3. Configure optional WSC Gateway pairing under **Extensions → My extensions → WSC Analytics & AI Bot Shield → Configure**.

---

## 🔗 Resources & License

- **Official Portal & Member Console:** [https://www.worldsearchcouncil.org](https://www.worldsearchcouncil.org)
- **Shopware 6 Integration Docs:** [https://world-search-council.github.io/wsc-docs/platforms/shopware6/](https://world-search-council.github.io/wsc-docs/platforms/shopware6/)
- **License:** [GNU General Public License v2.0 or later (GPL-2.0-or-later)](LICENSE)
