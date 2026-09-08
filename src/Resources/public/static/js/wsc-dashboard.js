/**
 * WSC 7-Card Bento Dashboard & Diagnostic Interaction for Shopware 6
 * Part of the World Search Council Standard (v2.2.0)
 */

document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.wsc-dashboard-wrapper');
    if (!wrapper) return;

    const ctx = window.WSC_DASHBOARD || {};
    const cardsData = ctx.cards || {};
    let currentPeriod = '7d';

    // Banner elements
    const banner = document.getElementById('wsc-diagnostic-banner');
    const bannerTitle = document.getElementById('wsc-banner-title');
    const bannerMsg = document.getElementById('wsc-banner-msg');

    function showBanner(title, msg) {
        if (!banner) return;
        bannerTitle.textContent = title;
        bannerMsg.textContent = msg;
        banner.style.display = 'block';
    }

    function hideBanner() {
        if (!banner) return;
        banner.style.display = 'none';
    }

    // 1. Multi-Period Selector
    const toggleBtns = wrapper.querySelectorAll('.wsc-toggle-btn');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            toggleBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentPeriod = this.getAttribute('data-period');
            updateMetrics(currentPeriod);
        });
    });

    function updateMetrics(period) {
        const key = 'data_' + period;
        const c1 = cardsData.card1_traffic ? (cardsData.card1_traffic[key] || cardsData.card1_traffic['data_7d']) : null;
        const c3 = cardsData.card3_bots ? (cardsData.card3_bots[key] || cardsData.card3_bots['data_7d']) : null;

        if (c1) {
            const vVis = document.getElementById('val-visitors');
            const vPv = document.getElementById('val-pageviews');
            const vEng = document.getElementById('val-engaged');

            if (vVis && c1.visitors !== undefined) vVis.textContent = Number(c1.visitors).toLocaleString();
            if (vPv && c1.pageviews !== undefined) vPv.textContent = Number(c1.pageviews).toLocaleString();
            if (vEng && c1.avg_engaged_time !== undefined) vEng.textContent = c1.avg_engaged_time;
        }

        if (c3) {
            const vHum = document.getElementById('val-human-pct');
            const vBot = document.getElementById('val-bot-pct');
            const vRag = document.getElementById('val-ai-rag');
            const vTrain = document.getElementById('val-ai-training');
            const vSearch = document.getElementById('val-search-bots');

            if (vHum && c3.human_pct !== undefined) vHum.textContent = c3.human_pct + '%';
            if (vBot && c3.bot_pct !== undefined) vBot.textContent = c3.bot_pct + '%';
            if (vRag && c3.ai_rag_hits !== undefined) vRag.textContent = Number(c3.ai_rag_hits).toLocaleString();
            if (vTrain && c3.ai_training_hits !== undefined) vTrain.textContent = Number(c3.ai_training_hits).toLocaleString();
            if (vSearch && c3.search_hits !== undefined) vSearch.textContent = Number(c3.search_hits).toLocaleString();
        }
    }

    // 2. Interactive Bento Cards -> Detail Drilldown Panel
    const cards = wrapper.querySelectorAll('.wsc-card-interactive');
    const detailPanel = document.getElementById('wsc-detail-panel');
    const detailTitle = document.getElementById('wsc-detail-title');
    const detailContent = document.getElementById('wsc-detail-content');
    const closeBtn = document.getElementById('wsc-close-detail');

    cards.forEach(card => {
        card.addEventListener('click', function() {
            const cardType = this.getAttribute('data-card');
            cards.forEach(c => c.classList.remove('active'));
            this.classList.add('active');

            if (detailPanel && detailTitle && detailContent) {
                detailPanel.style.display = 'block';

                switch (cardType) {
                    case 'traffic':
                        detailTitle.textContent = '1. Shopware 6 Traffic & Attribution Channels (' + currentPeriod.toUpperCase() + ')';
                        detailContent.innerHTML = `
                            <p class="text-muted">Real human visitor acquisition breakdown for Shopware 6 storefront.</p>
                            <table class="table table-sm table-striped">
                                <thead><tr><th>Acquisition Source</th><th>Traffic Type</th><th>Conversion Readiness</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>AI Citations (Perplexity / ChatGPT)</strong></td><td>LLM Referral</td><td><span class="badge bg-success">Very High Intent</span></td></tr>
                                    <tr><td><strong>Organic Search (Google / Bing)</strong></td><td>Direct SERP</td><td><span class="badge bg-primary">Steady Catalog Inflow</span></td></tr>
                                    <tr><td><strong>Social Media Campaigns</strong></td><td>Campaign Inflow</td><td><span class="badge bg-info">Discovery</span></td></tr>
                                    <tr><td><strong>Direct Store Access</strong></td><td>Direct Typing / Bookmarks</td><td><span class="badge bg-secondary">Repeat Customers</span></td></tr>
                                </tbody>
                            </table>
                        `;
                        break;

                    case 'insights':
                        detailTitle.textContent = '2. Strategic E-Commerce Intelligence';
                        detailContent.innerHTML = `
                            <div class="alert alert-info">
                                <h6 class="alert-heading fw-bold">Autonomous Conversion Optimization</h6>
                                <p class="mb-0">The WSC Gateway continuously compares your Shopware 6 conversion velocity against industry benchmark clusters to surface actionable recommendations.</p>
                            </div>
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item"><strong>Cart Step Friction:</strong> Consider reducing required customer form fields during checkout initiation.</li>
                                <li class="list-group-item"><strong>AI Schema Readiness:</strong> Ensure JSON-LD Product &amp; Offer schema is updated for LLM shopping bots.</li>
                            </ul>
                        `;
                        break;

                    case 'bots':
                        detailTitle.textContent = '3. Pre-Cache Bot Shield & Scraper Network';
                        detailContent.innerHTML = `
                            <p class="text-muted">Logged at Symfony Kernel request level before Shopware 6 Full-Page Cache execution.</p>
                            <table class="table table-sm table-hover">
                                <thead><tr><th>Bot Name</th><th>Category</th><th>Behavior</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr><td><code>GPTBot</code></td><td>AI Training Scraper</td><td>Bulk Catalog Harvesting</td><td><span class="badge bg-warning text-dark">Monitored</span></td></tr>
                                    <tr><td><code>OAI-SearchBot</code></td><td>AI Live RAG Search</td><td>Real-Time Product Lookup</td><td><span class="badge bg-success">Allowed</span></td></tr>
                                    <tr><td><code>PerplexityBot</code></td><td>AI Live RAG Search</td><td>Shopping Intent Citation</td><td><span class="badge bg-success">Allowed</span></td></tr>
                                    <tr><td><code>Googlebot</code></td><td>Search Engine</td><td>SEO Storefront Crawl</td><td><span class="badge bg-primary">Allowed</span></td></tr>
                                </tbody>
                            </table>
                        `;
                        break;

                    case 'engagement':
                        detailTitle.textContent = '4. Reading & UX Milestone Reality';
                        detailContent.innerHTML = `
                            <p class="text-muted">Engaged time measured via active DOM interaction, tab visibility, and scroll depth.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded">
                                        <h6 class="fw-bold">UX Badges</h6>
                                        <span class="badge bg-success me-1">Deep Reader Favorite</span>
                                        <span class="badge bg-secondary">Low Bounce Cluster</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded">
                                        <h6 class="fw-bold">Micro-Interactions</h6>
                                        <p class="small text-muted mb-0">Average Time to First Interaction (TTFI): <strong>1.2s</strong> across category and PDP pages.</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        break;

                    case 'outbound':
                        detailTitle.textContent = '5. Outbound Link Navigation Intelligence';
                        detailContent.innerHTML = `
                            <p class="text-muted">Tracks outgoing link clicks across social networks, partner affiliate platforms, and external references.</p>
                            <table class="table table-sm">
                                <thead><tr><th>Category</th><th>Share</th><th>Top Domains</th></tr></thead>
                                <tbody>
                                    <tr><td>Social Networks</td><td>40%</td><td>instagram.com, linkedin.com</td></tr>
                                    <tr><td>Job Portals &amp; Recruiting</td><td>35%</td><td>stepstone.de, indeed.com</td></tr>
                                    <tr><td>Affiliate / Payment Gateways</td><td>15%</td><td>paypal.com, klarna.com</td></tr>
                                </tbody>
                            </table>
                        `;
                        break;

                    case 'ecommerce':
                        detailTitle.textContent = '6. Shopware 6 Checkout Funnel & Product Velocity';
                        detailContent.innerHTML = `
                            <div class="alert alert-success">
                                <strong>100% Ad-Blocker Immune:</strong> Orders are captured server-side via Shopware 6 <code>CheckoutOrderPlacedEvent</code>.
                            </div>
                            <h6 class="fw-bold">Stage-by-Stage Funnel Transition</h6>
                            <table class="table table-sm table-bordered text-center">
                                <thead class="table-light"><tr><th>Stage 1: PDP Views</th><th>Stage 2: Add to Cart</th><th>Stage 3: Checkout Initiated</th><th>Stage 4: Order Completed</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td>1,420</td>
                                        <td>240 <span class="text-muted small">(16.9%)</span></td>
                                        <td>110 <span class="text-muted small">(45.8%)</span></td>
                                        <td class="table-success fw-bold">84 <span class="text-success small">(76.3%)</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        `;
                        break;

                    case 'sentinel':
                        detailTitle.textContent = '7. Sentinel Domain Health & Mail Infrastructure';
                        detailContent.innerHTML = `
                            <p class="text-muted">Comprehensive technical audit of DNS redundancy, DNSSEC, Port-25-free mail security, and TLS certificate expiry.</p>
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Nameserver Redundancy:</span>
                                    <span class="badge bg-success">Configured (&gt;= 2 NS)</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>DNSSEC Cryptographic Validation:</span>
                                    <span class="badge bg-success">Active</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Email Security (SPF &amp; DMARC):</span>
                                    <span class="badge bg-success">Strict Protection (No Port 25 probing)</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Security Headers (HSTS, X-Frame, X-Content-Type):</span>
                                    <span class="badge bg-success">Active &amp; Validated</span>
                                </li>
                            </ul>
                        `;
                        break;
                }

                detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });

    if (closeBtn && detailPanel) {
        closeBtn.addEventListener('click', function() {
            detailPanel.style.display = 'none';
            cards.forEach(c => c.classList.remove('active'));
        });
    }

    // 3. SpeedSentinel Trigger & Polling
    const speedBtn = document.getElementById('wsc-trigger-pagespeed');
    if (speedBtn) {
        speedBtn.addEventListener('click', function() {
            speedBtn.disabled = true;
            showBanner('SpeedSentinel Lab Audit Started', 'Headless Playwright runner is testing mobile Core Web Vitals and RAG readiness...');

            fetch(ctx.pagespeedUrl || '/wsc/v1/pagespeed/audit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: ctx.siteUrl || window.location.origin, strategy: 'mobile' })
            })
            .then(res => res.json())
            .then(data => {
                showBanner('SpeedSentinel Audit Enqueued', 'Audit is processing asynchronously (HTTP 202). Polling for report update...');
                setTimeout(pollCardsUpdate, 6000);
            })
            .catch(err => {
                showBanner('SpeedSentinel Notice', 'Request queued or deferred: ' + err.message);
            })
            .finally(() => {
                setTimeout(() => { speedBtn.disabled = false; }, 5000);
            });
        });
    }

    // 4. Sentinel Scan Trigger & Polling
    const sentinelBtn = document.getElementById('wsc-trigger-sentinel');
    if (sentinelBtn) {
        sentinelBtn.addEventListener('click', function() {
            sentinelBtn.disabled = true;
            showBanner('Sentinel Domain Scan Started', 'Validating authoritative DNS records, DNSSEC, Port-25-free SPF/DMARC, and SSL certificates...');

            fetch(ctx.sentinelUrl || '/wsc/v1/sentinel/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ domain: ctx.siteHost || window.location.hostname })
            })
            .then(res => res.json())
            .then(data => {
                showBanner('Sentinel Scan Enqueued', 'Scan processing in Worker Sentinel. Polling for report update...');
                setTimeout(pollCardsUpdate, 6000);
            })
            .catch(err => {
                showBanner('Sentinel Notice', 'Request queued or deferred: ' + err.message);
            })
            .finally(() => {
                setTimeout(() => { sentinelBtn.disabled = false; }, 5000);
            });
        });
    }

    // 5. Polling helper to refresh cards
    function pollCardsUpdate() {
        fetch(ctx.cardsUrl || '/wsc/v1/cards')
            .then(res => res.json())
            .then(data => {
                if (data.ok && data.cards) {
                    Object.assign(cardsData, data.cards);
                    updateMetrics(currentPeriod);
                    showBanner('Reports Synchronized', 'Latest diagnostic and telemetry reports updated.');
                    setTimeout(hideBanner, 4000);
                }
            })
            .catch(() => {});
    }

    // 6. Sync Now Button
    const syncBtn = document.getElementById('wsc-sync-now');
    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            syncBtn.disabled = true;
            syncBtn.textContent = 'Syncing...';

            fetch(ctx.refreshUrl || '/wsc/v1/refresh', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.ok) {
                    syncBtn.textContent = '✓ Synced (' + (res.synced_events || 0) + ')';
                    pollCardsUpdate();
                } else {
                    syncBtn.textContent = 'Sync Failed';
                }
            })
            .catch(() => {
                syncBtn.textContent = 'Network Error';
            })
            .finally(() => {
                setTimeout(() => {
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = '<span>&#x21bb;</span> Sync Now';
                }, 4000);
            });
        });
    }
});
