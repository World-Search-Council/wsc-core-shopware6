/**
 * WSC v2.2.3 Bento Dashboard, Member Bridge, AI Coverage Inspector,
 * Dynamic Industry Benchmarks & Opt-In IndexNow for Shopware 6
 */

document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.wsc-dashboard-wrapper');
    if (!wrapper) return;

    const ctx = window.WSC_DASHBOARD || {};
    const cardsData = ctx.cards || {};
    let currentPeriod = '7d';

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

    function escapeHtml(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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

            if (!detailPanel || !detailTitle || !detailContent) return;
            detailPanel.style.display = 'block';

            switch (cardType) {
                case 'traffic':
                    detailTitle.textContent = '1. Shopware 6 Traffic & Attribution Channels (' + currentPeriod.toUpperCase() + ')';
                    detailContent.innerHTML = '<p class="text-muted">Cookieless visitor acquisition breakdown (Zero-PII daily salted session hashes).</p>' +
                        '<table class="table table-sm table-striped">' +
                        '<thead><tr><th>Acquisition Source</th><th>Traffic Type</th><th>Conversion Readiness</th></tr></thead>' +
                        '<tbody>' +
                        '<tr><td><strong>AI Citations (Perplexity / ChatGPT)</strong></td><td>LLM Referral</td><td><span class="badge bg-success">Very High Intent</span></td></tr>' +
                        '<tr><td><strong>Organic Search (Google / Bing)</strong></td><td>Direct SERP</td><td><span class="badge bg-primary">Catalog Inflow</span></td></tr>' +
                        '<tr><td><strong>Social Media Campaigns</strong></td><td>Campaign Inflow</td><td><span class="badge bg-info">Discovery</span></td></tr>' +
                        '<tr><td><strong>Direct Store Access</strong></td><td>Direct / Bookmarks</td><td><span class="badge bg-secondary">Repeat Customers</span></td></tr>' +
                        '</tbody></table>';
                    break;
                case 'insights':
                    detailTitle.textContent = '2. Strategic E-Commerce Intelligence';
                    detailContent.innerHTML = '<div class="alert alert-info mb-2"><strong>Autonomous Conversion &amp; GEO Analysis:</strong> Combines local SQLite funnel signals with anonymized WSC vertical benchmarks.</div>';
                    break;
                case 'bots':
                    detailTitle.textContent = '3. Pre-Cache Bot Shield & Scraper Network';
                    detailContent.innerHTML = '<p class="text-muted mb-0">Captured at Symfony <code>KernelEvents::REQUEST</code> (priority 250) with hashed session tokens and zero raw IPs.</p>';
                    break;
                case 'engagement':
                    detailTitle.textContent = '4. Reading & UX Milestone Reality';
                    detailContent.innerHTML = '<p class="text-muted mb-0">Engaged reading time measured via active DOM focus, tab visibility, and scroll milestones.</p>';
                    break;
                case 'outbound':
                    detailTitle.textContent = '5. Outbound Link Navigation Intelligence';
                    detailContent.innerHTML = '<p class="text-muted mb-0">Categorizes external departures across payment gateways, social profiles, and partner domains.</p>';
                    break;
                case 'ecommerce':
                    detailTitle.textContent = '6. Shopware 6 Checkout Funnel & Server Orders';
                    detailContent.innerHTML = '<div class="alert alert-success mb-0"><strong>100% Ad-Blocker Immune:</strong> Orders are captured server-side via <code>CheckoutOrderPlacedEvent</code>.</div>';
                    break;
                case 'sentinel':
                    detailTitle.textContent = '7. Sentinel Domain Health & Mail Infrastructure';
                    detailContent.innerHTML = '<p class="text-muted mb-0">Validates authoritative DNS redundancy, DNSSEC, Port-25-free SPF/DMARC records, and SSL expiry.</p>';
                    break;
            }
            detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    if (closeBtn && detailPanel) {
        closeBtn.addEventListener('click', function() {
            detailPanel.style.display = 'none';
            cards.forEach(c => c.classList.remove('active'));
        });
    }

    // 3. SpeedSentinel & Sentinel Triggers
    const speedBtn = document.getElementById('wsc-trigger-pagespeed');
    if (speedBtn) {
        speedBtn.addEventListener('click', function() {
            speedBtn.disabled = true;
            showBanner('SpeedSentinel Lab Audit', 'Validating host SSRF rules and dispatching Playwright CWV audit...');

            fetch(ctx.pagespeedUrl || '/wsc/v1/pagespeed/audit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: ctx.siteUrl || window.location.origin, strategy: 'mobile' })
            })
            .then(res => res.json())
            .then(res => {
                if (res.ok === false) {
                    showBanner('SpeedSentinel Notice', res.message || res.error || 'Could not queue audit.');
                } else {
                    showBanner('SpeedSentinel Audit Enqueued', 'Processing asynchronously (HTTP 202). Polling for updated report...');
                    setTimeout(pollCardsUpdate, 6000);
                }
            })
            .catch(err => showBanner('SpeedSentinel Notice', err.message))
            .finally(() => setTimeout(() => { speedBtn.disabled = false; }, 4000));
        });
    }

    const sentinelBtn = document.getElementById('wsc-trigger-sentinel');
    if (sentinelBtn) {
        sentinelBtn.addEventListener('click', function() {
            sentinelBtn.disabled = true;
            showBanner('Sentinel Domain Scan', 'Dispatching Port-25-free DNS, SPF/DMARC & TLS inspection...');

            fetch(ctx.sentinelUrl || '/wsc/v1/sentinel/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ domain: ctx.siteHost || window.location.hostname })
            })
            .then(res => res.json())
            .then(res => {
                if (res.ok === false) {
                    showBanner('Sentinel Notice', res.message || res.error || 'Could not queue scan.');
                } else {
                    showBanner('Sentinel Scan Enqueued', 'Worker Sentinel is scanning domain infrastructure...');
                    setTimeout(pollCardsUpdate, 6000);
                }
            })
            .catch(err => showBanner('Sentinel Notice', err.message))
            .finally(() => setTimeout(() => { sentinelBtn.disabled = false; }, 4000));
        });
    }

    function pollCardsUpdate() {
        fetch(ctx.cardsUrl || '/wsc/v1/cards')
            .then(res => res.json())
            .then(data => {
                if (data.ok && data.cards) {
                    Object.assign(cardsData, data.cards);
                    updateMetrics(currentPeriod);
                    if (data.benchmarks) renderBenchmarks(data.benchmarks);
                    showBanner('Reports Synchronized', 'Latest diagnostic and benchmark telemetry updated.');
                    setTimeout(hideBanner, 4000);
                }
            })
            .catch(() => {});
    }

    // 4. Sync Now Button
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
                    syncBtn.textContent = res.hybrid ? ('✓ Synced (' + (res.synced_events || 0) + ')') : 'Local Mode (Opt-In Disabled)';
                    pollCardsUpdate();
                } else {
                    syncBtn.textContent = 'Sync Deferred';
                }
            })
            .catch(() => { syncBtn.textContent = 'Network Error'; })
            .finally(() => {
                setTimeout(() => {
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = '<span>&#x21bb;</span> Sync Now';
                }, 3500);
            });
        });
    }

    // 5. Industry Benchmarks Vertical Select & Auto-Classify NLP
    function renderBenchmarks(bm) {
        if (!bm || !bm.metrics) return;
        const iconEl = document.getElementById('wsc-bm-icon');
        const labelEl = document.getElementById('wsc-bm-label');
        const nodesEl = document.getElementById('wsc-bm-nodes');
        const gridEl = document.getElementById('wsc-bm-metrics-grid');
        const selectEl = document.getElementById('wsc-bm-vertical-select');

        if (iconEl) iconEl.textContent = bm.vertical_icon || '🏆';
        if (labelEl) labelEl.textContent = bm.vertical_label || bm.vertical;
        if (nodesEl) nodesEl.textContent = bm.nodes_in_benchmark || 50;
        if (selectEl && bm.vertical) selectEl.value = bm.vertical;

        if (gridEl) {
            let html = '';
            Object.keys(bm.metrics).forEach(k => {
                const m = bm.metrics[k];
                html += '<div class="col-lg-3 col-md-6">' +
                    '<div class="p-3 border rounded bg-light h-100 d-flex flex-column justify-content-between">' +
                    '<div><div class="small text-muted fw-semibold">' + escapeHtml(m.label) + '</div>' +
                    '<div class="h4 fw-bold my-1">' + escapeHtml(m.site_val) + escapeHtml(m.unit) +
                    ' <span class="small text-muted fw-normal">(Med: ' + escapeHtml(m.industry_median) + escapeHtml(m.unit) + ')</span></div></div>' +
                    '<div><span class="wsc-bm-chip ' + escapeHtml(m.css_class) + '">' + escapeHtml(m.badge) + '</span></div>' +
                    '</div></div>';
            });
            gridEl.innerHTML = html;
        }
    }

    const bmSelect = document.getElementById('wsc-bm-vertical-select');
    if (bmSelect) {
        bmSelect.addEventListener('change', function() {
            fetch(ctx.benchmarksClassifyUrl || '/wsc/v1/benchmarks/classify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vertical: this.value })
            })
            .then(r => r.json())
            .then(res => { if (res.benchmarks) renderBenchmarks(res.benchmarks); });
        });
    }

    const bmClassifyBtn = document.getElementById('wsc-bm-classify-btn');
    if (bmClassifyBtn) {
        bmClassifyBtn.addEventListener('click', function() {
            bmClassifyBtn.disabled = true;
            bmClassifyBtn.textContent = 'Classifying...';
            fetch(ctx.benchmarksClassifyUrl || '/wsc/v1/benchmarks/classify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            })
            .then(r => r.json())
            .then(res => {
                if (res.benchmarks) renderBenchmarks(res.benchmarks);
                showBanner('Vertical Classified', 'Detected vertical: ' + (res.vertical || '') + ' (' + (res.method || 'nlp') + ').');
                setTimeout(hideBanner, 4000);
            })
            .finally(() => {
                bmClassifyBtn.disabled = false;
                bmClassifyBtn.textContent = 'Auto-Classify NLP';
            });
        });
    }

    // 6. AI Coverage URL Inspector
    const covBtn = document.getElementById('wsc-coverage-btn');
    const covInput = document.getElementById('wsc-coverage-query');
    const covTf = document.getElementById('wsc-coverage-timeframe');
    const covResult = document.getElementById('wsc-coverage-result');

    if (covBtn && covInput && covResult) {
        covBtn.addEventListener('click', function() {
            const q = covInput.value.trim();
            if (!q) return;
            covBtn.disabled = true;
            covResult.innerHTML = '<span class="text-muted">Inspecting AI bot crawl telemetry...</span>';

            fetch(ctx.coverageUrl || '/wsc/v1/ai-coverage', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: q, timeframe: covTf ? covTf.value : '30d' })
            })
            .then(r => r.json())
            .then(res => {
                if (!res || res.ok === false) {
                    covResult.innerHTML = '<span class="text-danger">' + escapeHtml((res && res.message) || 'Query failed.') + '</span>';
                    return;
                }
                const um = res.url_metrics || {};
                const dc = res.domain_coverage || {};
                const rag = um.rag || {};
                const tr = um.training || {};
                const srcBadge = res.source === 'gateway' ? 'Cloud Gateway' : 'Local SQLite Sensor';

                let html = '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<strong>' + escapeHtml((res.query && res.query.canonical_url) || q) + '</strong>' +
                    '<span class="badge bg-secondary">' + escapeHtml(srcBadge) + '</span></div>';

                if (res.notice) {
                    html += '<div class="alert alert-warning py-1 px-2 mb-2">' + escapeHtml(res.notice) + '</div>';
                }

                html += '<div class="row g-2 text-center mb-2">' +
                    '<div class="col-4"><div class="p-2 bg-white border rounded"><div class="text-muted">Live RAG Hits (30d)</div><strong class="h6 mb-0">' + Number(rag.hits_30d || 0) + '</strong></div></div>' +
                    '<div class="col-4"><div class="p-2 bg-white border rounded"><div class="text-muted">Training Hits (30d)</div><strong class="h6 mb-0">' + Number(tr.hits_30d || 0) + '</strong></div></div>' +
                    '<div class="col-4"><div class="p-2 bg-white border rounded"><div class="text-muted">Domain AI Coverage</div><strong class="h6 mb-0">' + escapeHtml(dc.ai_coverage_pct || 0) + '%</strong></div></div>' +
                    '</div>';
                html += '<div class="text-muted">Last RAG Visit: <strong>' + escapeHtml(rag.last_seen_human || 'Never') + '</strong> &bull; Last Training Crawl: <strong>' + escapeHtml(tr.last_seen_human || 'Never') + '</strong></div>';
                covResult.innerHTML = html;
            })
            .catch(err => {
                covResult.innerHTML = '<span class="text-danger">' + escapeHtml(err.message) + '</span>';
            })
            .finally(() => { covBtn.disabled = false; });
        });
    }

    // 7. Manual IndexNow URL Submission
    const inBtn = document.getElementById('wsc-indexnow-submit');
    const inUrls = document.getElementById('wsc-indexnow-urls');
    const inStatus = document.getElementById('wsc-indexnow-status');
    const inTable = document.getElementById('wsc-indexnow-logs-table');

    if (inBtn && inUrls) {
        inBtn.addEventListener('click', function() {
            const raw = inUrls.value.trim();
            if (!raw) return;
            inBtn.disabled = true;
            if (inStatus) inStatus.textContent = 'Submitting...';

            fetch(ctx.indexnowSubmitUrl || '/wsc/v1/indexnow/submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ urls: raw })
            })
            .then(r => r.json())
            .then(res => {
                if (inStatus) {
                    inStatus.textContent = res.message || '';
                    inStatus.className = 'small fw-semibold ' + (res.ok ? 'text-success' : 'text-danger');
                }
                if (res.logs && inTable) {
                    const tbody = inTable.querySelector('tbody');
                    if (tbody) {
                        tbody.innerHTML = res.logs.map(l =>
                            '<tr><td>' + escapeHtml(l.timestamp) + '</td>' +
                            '<td><code>' + escapeHtml(l.source) + '</code></td>' +
                            '<td><span class="badge ' + (l.success ? 'bg-success' : 'bg-danger') + '">' + escapeHtml(l.status_code) + '</span></td>' +
                            '<td>' + escapeHtml(l.count) + '</td></tr>'
                        ).join('');
                    }
                }
            })
            .finally(() => { inBtn.disabled = false; });
        });
    }

    // 8. WSC Member Credential Verify, Disconnect & Co-Branding Save
    const licVerifyBtn = document.getElementById('wsc-license-verify-btn');
    const licDiscBtn = document.getElementById('wsc-license-disconnect-btn');
    const licInput = document.getElementById('wsc-license-input');
    const licMsg = document.getElementById('wsc-license-msg');
    const licBadge = document.getElementById('wsc-license-badge');

    if (licVerifyBtn && licInput) {
        licVerifyBtn.addEventListener('click', function() {
            const key = licInput.value.trim();
            if (!key) return;
            licVerifyBtn.disabled = true;
            if (licMsg) licMsg.textContent = 'Verifying credential with www.worldsearchcouncil.org...';

            fetch(ctx.licenseVerifyUrl || '/wsc/v1/license/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ license_key: key })
            })
            .then(r => r.json())
            .then(res => {
                if (licMsg) {
                    licMsg.textContent = res.message || '';
                    licMsg.className = 'small mb-3 ' + (res.ok ? 'text-success fw-semibold' : 'text-danger');
                }
                if (res.membership && licBadge) {
                    licBadge.textContent = String(res.membership.status || '').toUpperCase() + ' • ' + (res.membership.tier_label || '');
                    licBadge.className = 'badge ' + (res.membership.is_active_licensed ? 'bg-success' : 'bg-secondary');
                }
                if (res.ok) {
                    setTimeout(() => window.location.reload(), 900);
                }
            })
            .finally(() => { licVerifyBtn.disabled = false; });
        });
    }

    if (licDiscBtn) {
        licDiscBtn.addEventListener('click', function() {
            licDiscBtn.disabled = true;
            fetch(ctx.licenseDisconnectUrl || '/wsc/v1/license/disconnect', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(() => window.location.reload());
        });
    }

    const cbSaveBtn = document.getElementById('wsc-cb-save-btn');
    const cbStatus = document.getElementById('wsc-cb-status');
    if (cbSaveBtn) {
        cbSaveBtn.addEventListener('click', function() {
            cbSaveBtn.disabled = true;
            const payload = {
                enabled: !!(document.getElementById('wsc-cb-enabled') && document.getElementById('wsc-cb-enabled').checked),
                agency_name: (document.getElementById('wsc-cb-name') || {}).value || '',
                support_email: (document.getElementById('wsc-cb-email') || {}).value || '',
                logo_url: (document.getElementById('wsc-cb-logo') || {}).value || '',
                support_url: (document.getElementById('wsc-cb-url') || {}).value || '',
                custom_footer: (document.getElementById('wsc-cb-footer') || {}).value || ''
            };

            fetch(ctx.coBrandingSaveUrl || '/wsc/v1/cobranding/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (cbStatus) cbStatus.textContent = res.message || 'Saved.';
                setTimeout(() => window.location.reload(), 700);
            })
            .finally(() => { cbSaveBtn.disabled = false; });
        });
    }
});
