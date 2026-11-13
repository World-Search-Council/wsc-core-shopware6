# WSC Search & Health Analytics for Shopware 6 (`v2.2.3`)

<p align="center">
  <strong>Official Shopware 6 Enterprise Connector of the World Search Council (WSC) Standard</strong><br>
  100% Cookieless Storefront Analytics, Zero-PII Bot Sensor, WSC Member Bridge, Dynamic Industry Benchmarks, <code>/llms.txt</code>, Opt-In <code>IndexNow</code> &amp; Server-Side Order Tracking
</p>

<p align="center">
  <a href="https://www.worldsearchcouncil.org"><img src="https://img.shields.io/badge/Portal-worldsearchcouncil.org-0F172A?style=flat-square" alt="World Search Council"></a>
  <img src="https://img.shields.io/badge/Version-2.2.3-189EFF?style=flat-square&logo=shopware" alt="Shopware 6 v2.2.3">
  <img src="https://img.shields.io/badge/Shopware-6.5_|_6.6-189EFF?style=flat-square" alt="Shopware 6">
  <img src="https://img.shields.io/badge/Privacy-100%25_Cookieless_%26_Zero_PII-10B981?style=flat-square" alt="Cookieless">
  <img src="https://img.shields.io/badge/License-GPL--2.0--or--later-orange?style=flat-square" alt="License">
</p>

---

## 🌟 Key Features (`v2.2.3` Parity with WordPress SSOT)

* **Opt-In Cloud Handshake & Canonical Gateway v2.3 Envelope**:
  * `hybridEnabled` defaults strictly to **`false`** (zero unconsented network traffic).
  * When opted in, pairs via `POST https://api.worldsearchcouncil.org/v1/register` to provision `projectId` and `apiSecret`.
  * Micro-batches (`POST /v1/ingest`) encrypt inner payloads with `AES-256-CBC` (`localKey`, random 16-byte IV) and sign outer JSON envelopes via `HMAC-SHA256` (`X-WSC-Sig` with `apiSecret`).
* **100% Cookieless & Zero-PII Privacy**:
  * Never stores raw client IPs in SQLite (`SHA256(IP + UA + gmdate('Y-m-d') + localKey)`).
  * Dual SQLite WAL databases (`queue_temp.sqlite` and `reports_temp.sqlite`) protect Shopware's MySQL/MariaDB cluster from lock contention.
* **Live WSC Member Credential Bridge, Agency Co-Branding & Partner Feed**:
  * Verifies credentials against `POST https://www.worldsearchcouncil.org/api/v1/license/verify` (with offline sandbox support strictly for `WSC-MEMBER-DEMO`, `WSC-AGENCY-DEMO`, and `WSC-PARTNER-DEMO`).
  * 100% of local features remain unlocked without a license key; active licensed members enjoy verified badges, Agency Co-Branding synchronization, and a **100% ad-free** dashboard.
* **AI Coverage Inspector & Dynamic Industry Benchmarks**:
  * Interactive URL visibility inspector (`POST /v1/ai-coverage/query` with local SQLite `wsc_bot_hits` fallback).
  * Anonymized k-anonymity benchmark comparisons (`POST /v1/benchmarks/query`, 6h SQLite cache, and `POST /v1/benchmarks/classify` NLP classifier + local centroid fallback) with gamification badges (`🏆 Top 10%`, `🟢 +X% vs. Median`).
* **Public `/llms.txt` AI Manifest & Opt-In `IndexNow` Instant Push**:
  * Serves dynamic `/llms.txt` (`text/plain; charset=utf-8`, enabled by default) listing store metadata, `/sitemap.xml`, active categories, featured products, and CMS pages.
  * Opt-in `IndexNow` (`indexnowEnabled` default `false`): auto-generated 32-char hex key, virtual `/{key}.txt` verification endpoint, non-blocking `3s` ping on product save (`ProductEvents::PRODUCT_WRITTEN_EVENT`), and manual URL submission console storing the last 20 log entries.
* **SSRF Hardening & Port-25 Ban**:
  * Restricts PageSpeed and Sentinel scans strictly to the registered storefront hostname resolving to public IPs (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`), with zero outbound TCP Port 25 connections.

---

## 📦 Directory Structure

```text
wsc-core-shopware6/
├── composer.json                        # wsc/wsc-analytics-shopware6 v2.2.3
└── src/
    ├── WscAnalytics.php                 # Shopware 6 Plugin bootstrap (v2.2.3)
    ├── Service/
    │   ├── WscDatabaseManager.php       # Hardened Dual SQLite WAL storage (Zero-PII)
    │   ├── WscBotTracker.php            # Canonical AI/RAG/Search bot taxonomy
    │   └── WscCoreService.php           # Handshake, AES-256/HMAC, Member Bridge, Benchmarks, llms.txt & IndexNow
    ├── Subscriber/
    │   ├── WscRequestSubscriber.php     # /llms.txt, /{key}.txt & early KernelEvents::REQUEST bot sensor
    │   ├── WscOrderSubscriber.php       # CheckoutOrderPlacedEvent server-side conversion hook
    │   └── WscProductSubscriber.php     # Opt-in non-blocking IndexNow push on product save
    ├── ScheduledTask/
    │   ├── WscSyncTask.php              # 5-minute Scheduled Task
    │   └── WscSyncTaskHandler.php       # Encrypted micro-batch exporter & retention cleanup
    ├── Storefront/Controller/
    │   ├── WscApiController.php         # REST endpoints (/wsc/v1/* & /llms.txt)
    │   └── WscDashboardController.php   # Bento Command Center controller
    └── Resources/
        ├── config/
        │   ├── config.xml               # System configuration (opt-in defaults)
        │   └── services.xml             # Symfony DI container definitions
        ├── views/storefront/
        │   ├── layout/meta.html.twig    # First-party tracker injection
        │   └── page/wsc-dashboard.html.twig # v2.2.3 Bento Dashboard UI
        └── public/static/
            ├── js/
            │   ├── wsc-tracker.js       # Canonical Vanilla ES6 tracker
            │   └── wsc-dashboard.js     # Interactive Inspector, Benchmarks, IndexNow & Member Bridge
            └── css/
                └── wsc-dashboard.css    # Bento Grid, Gamification Chips & Co-Branding styles
```

---

## 🚀 Installation

1. Copy this directory into `custom/plugins/WscAnalytics`.
2. Install and activate the plugin via the Shopware CLI:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate WscAnalytics
   bin/console cache:clear
   ```
3. Configure optional WSC Gateway sync, Member credentials, `/llms.txt`, and `IndexNow` under **Extensions → My extensions → WSC Search & Health Analytics → Configure**, or open `/wsc/admin/dashboard`.

---

## 🔗 Resources & License

- **Official Portal & Member Console:** [https://www.worldsearchcouncil.org](https://www.worldsearchcouncil.org)
- **GitHub Organization & Maintainer:** [World-Search-Council](https://github.com/World-Search-Council) · Founding Lead Maintainer: **[@thebennos](https://github.com/thebennos)**
- **License:** [GNU General Public License v2.0 or later (GPL-2.0-or-later)](LICENSE)
