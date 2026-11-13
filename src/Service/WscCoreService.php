<?php

declare(strict_types=1);

namespace Wsc\Analytics\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Core business logic, AES-256-CBC/HMAC-SHA256 telemetry, WSC Member Bridge,
 * AI Coverage Inspector, Dynamic Benchmarks, /llms.txt & Opt-In IndexNow for Shopware 6 (v2.2.3).
 */
class WscCoreService
{
    public const VERSION = '2.2.3';
    public const CONFIG_PREFIX = 'WscAnalytics.config.';
    public const GATEWAY_DEFAULT = 'https://api.worldsearchcouncil.org';
    public const PORTAL_LICENSE_VERIFY_URL = 'https://www.worldsearchcouncil.org/api/v1/license/verify';
    public const PORTAL_PARTNERS_FEED_URL = 'https://www.worldsearchcouncil.org/api/v1/partners/featured';
    public const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

    public const DEMO_KEYS = [
        'WSC-MEMBER-DEMO'  => ['tier' => 'member',           'holder' => 'Demo Member Account (Offline Sandbox)'],
        'WSC-AGENCY-DEMO'  => ['tier' => 'agency',           'holder' => 'Demo Partner Agency (Offline Sandbox)'],
        'WSC-PARTNER-DEMO' => ['tier' => 'founding_partner', 'holder' => 'Demo Founding Partner (Offline Sandbox)'],
    ];

    public const BENCHMARK_TAXONOMY = [
        '/Shopping/Apparel' => [
            'label'     => 'E-Commerce: Fashion, Apparel & Footwear',
            'icon'      => '👗',
            'keywords'  => ['mode', 'fashion', 'kleidung', 'schuhe', 'apparel', 'outfit', 'accessoire', 'style', 'dress', 'shirt', 'pants', 'damenmode', 'herrenmode'],
            'nodes'     => 142,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 8.5,  'p75' => 12.1, 'p90' => 17.4],
                'engaged_time'    => ['p50' => 58.0, 'p75' => 72.0, 'p90' => 94.0],
                'ai_coverage_pct' => ['p50' => 46.0, 'p75' => 62.5, 'p90' => 78.0],
                'human_ratio_pct' => ['p50' => 88.5, 'p75' => 93.2, 'p90' => 96.8],
            ],
        ],
        '/Shopping/Consumer Electronics' => [
            'label'     => 'E-Commerce: Consumer Electronics & Tech',
            'icon'      => '💻',
            'keywords'  => ['elektronik', 'electronics', 'computer', 'hardware', 'gadget', 'smartphone', 'tech', 'audio', 'kabel', 'akku', 'display', 'laptop'],
            'nodes'     => 98,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 11.2, 'p75' => 15.4, 'p90' => 21.0],
                'engaged_time'    => ['p50' => 64.0, 'p75' => 82.0, 'p90' => 110.0],
                'ai_coverage_pct' => ['p50' => 52.0, 'p75' => 68.0, 'p90' => 84.5],
                'human_ratio_pct' => ['p50' => 86.0, 'p75' => 91.5, 'p90' => 95.0],
            ],
        ],
        '/Computers & Electronics/Enterprise Software' => [
            'label'     => 'IT, SaaS & Enterprise Software',
            'icon'      => '☁️',
            'keywords'  => ['saas', 'software', 'cloud', 'enterprise', 'api', 'plattform', 'platform', 'developer', 'automation', 'crm', 'erp'],
            'nodes'     => 86,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 18.4, 'p75' => 26.0, 'p90' => 36.5],
                'engaged_time'    => ['p50' => 85.0, 'p75' => 115.0, 'p90' => 155.0],
                'ai_coverage_pct' => ['p50' => 68.0, 'p75' => 82.0, 'p90' => 92.0],
                'human_ratio_pct' => ['p50' => 82.0, 'p75' => 89.0, 'p90' => 94.0],
            ],
        ],
        '/Health/Medical & Wellness' => [
            'label'     => 'Health, Medical & Wellness',
            'icon'      => '🩺',
            'keywords'  => ['gesundheit', 'health', 'medical', 'medizin', 'arzt', 'klinik', 'praxis', 'wellness', 'therapie', 'apotheke', 'pharma', 'fitness'],
            'nodes'     => 74,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 15.2, 'p75' => 21.5, 'p90' => 29.0],
                'engaged_time'    => ['p50' => 78.0, 'p75' => 98.0, 'p90' => 130.0],
                'ai_coverage_pct' => ['p50' => 59.0, 'p75' => 74.0, 'p90' => 88.0],
                'human_ratio_pct' => ['p50' => 89.0, 'p75' => 94.0, 'p90' => 97.2],
            ],
        ],
        '/Business & Industrial/Services' => [
            'label'     => 'B2B, Consulting & Professional Services',
            'icon'      => '🏢',
            'keywords'  => ['beratung', 'consulting', 'handwerk', 'b2b', 'agentur', 'agency', 'dienstleistung', 'service', 'industrie'],
            'nodes'     => 115,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 10.5, 'p75' => 16.0, 'p90' => 24.0],
                'engaged_time'    => ['p50' => 62.0, 'p75' => 84.0, 'p90' => 118.0],
                'ai_coverage_pct' => ['p50' => 48.0, 'p75' => 65.0, 'p90' => 81.0],
                'human_ratio_pct' => ['p50' => 87.0, 'p75' => 92.5, 'p90' => 96.0],
            ],
        ],
        '/Food & Drink/Restaurants & Groceries' => [
            'label'     => 'Food, Restaurants & Groceries',
            'icon'      => '🍽️',
            'keywords'  => ['restaurant', 'essen', 'food', 'lebensmittel', 'rezept', 'recipe', 'cafe', 'bar', 'wein', 'kaffee', 'gourmet'],
            'nodes'     => 62,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 6.4,  'p75' => 9.8,  'p90' => 14.5],
                'engaged_time'    => ['p50' => 44.0, 'p75' => 58.0, 'p90' => 76.0],
                'ai_coverage_pct' => ['p50' => 38.0, 'p75' => 51.0, 'p90' => 68.0],
                'human_ratio_pct' => ['p50' => 91.0, 'p75' => 95.2, 'p90' => 98.0],
            ],
        ],
        '/Home & Garden' => [
            'label'     => 'Furniture, Home & Garden',
            'icon'      => '🏡',
            'keywords'  => ['wohnen', 'moebel', 'furniture', 'garten', 'garden', 'haus', 'pflanzen', 'einrichtung', 'interieur', 'deko', 'kueche'],
            'nodes'     => 58,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 7.8,  'p75' => 11.5, 'p90' => 16.0],
                'engaged_time'    => ['p50' => 52.0, 'p75' => 68.0, 'p90' => 90.0],
                'ai_coverage_pct' => ['p50' => 41.0, 'p75' => 56.0, 'p90' => 73.0],
                'human_ratio_pct' => ['p50' => 89.0, 'p75' => 93.8, 'p90' => 97.0],
            ],
        ],
        '/News/Media & Publishing' => [
            'label'     => 'News, Media & Digital Publishing',
            'icon'      => '📰',
            'keywords'  => ['news', 'nachrichten', 'magazin', 'magazine', 'blog', 'zeitung', 'artikel', 'journalismus'],
            'nodes'     => 69,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 22.0, 'p75' => 31.0, 'p90' => 42.0],
                'engaged_time'    => ['p50' => 92.0, 'p75' => 128.0, 'p90' => 175.0],
                'ai_coverage_pct' => ['p50' => 74.0, 'p75' => 88.0, 'p90' => 96.0],
                'human_ratio_pct' => ['p50' => 81.0, 'p75' => 87.0, 'p90' => 92.5],
            ],
        ],
        '/Finance/Banking & Insurance' => [
            'label'     => 'Finance, Banking & Insurance',
            'icon'      => '💳',
            'keywords'  => ['finanzen', 'finance', 'bank', 'kredit', 'versicherung', 'insurance', 'investment', 'fintech'],
            'nodes'     => 54,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 12.0, 'p75' => 17.5, 'p90' => 25.0],
                'engaged_time'    => ['p50' => 70.0, 'p75' => 92.0, 'p90' => 124.0],
                'ai_coverage_pct' => ['p50' => 54.0, 'p75' => 70.0, 'p90' => 86.0],
                'human_ratio_pct' => ['p50' => 88.0, 'p75' => 93.0, 'p90' => 96.5],
            ],
        ],
        '/Travel/Tourism & Hospitality' => [
            'label'     => 'Travel, Tourism & Hospitality',
            'icon'      => '✈️',
            'keywords'  => ['reise', 'travel', 'urlaub', 'hotel', 'tourismus', 'flug', 'buchen', 'ferien', 'resort'],
            'nodes'     => 47,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 9.6,  'p75' => 14.2, 'p90' => 20.5],
                'engaged_time'    => ['p50' => 66.0, 'p75' => 88.0, 'p90' => 116.0],
                'ai_coverage_pct' => ['p50' => 49.0, 'p75' => 66.0, 'p90' => 83.0],
                'human_ratio_pct' => ['p50' => 90.0, 'p75' => 94.5, 'p90' => 97.5],
            ],
        ],
    ];

    private SystemConfigService $systemConfig;
    private WscDatabaseManager $dbManager;
    private LoggerInterface $logger;
    private ?HttpClientInterface $httpClient;
    private ?Connection $connection;

    public function __construct(
        SystemConfigService $systemConfig,
        WscDatabaseManager $dbManager,
        LoggerInterface $logger,
        ?HttpClientInterface $httpClient = null,
        ?Connection $connection = null
    ) {
        $this->systemConfig = $systemConfig;
        $this->dbManager = $dbManager;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->connection = $connection;
    }

    // =========================================================================
    // PAKET CMS-1: Gateway v2.3 Handshake, Ingest Envelope & Zero-PII Privacy
    // =========================================================================

    public function isHybridEnabled(?string $salesChannelId = null): bool
    {
        return $this->systemConfig->getBool(self::CONFIG_PREFIX . 'hybridEnabled', $salesChannelId);
    }

    public function getGatewayUrl(?string $salesChannelId = null): string
    {
        $url = trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'gatewayUrl', $salesChannelId));
        return $url !== '' ? rtrim($url, '/') : self::GATEWAY_DEFAULT;
    }

    /**
     * Resolves canonical public storefront base URL without trailing slash.
     */
    public function resolveSiteBaseUrl(?string $salesChannelId = null, ?string $explicitBaseUrl = null): string
    {
        if (!empty($explicitBaseUrl)) {
            return rtrim(trim($explicitBaseUrl), '/');
        }

        if ($this->connection !== null) {
            try {
                if ($salesChannelId !== null && ctype_xdigit($salesChannelId)) {
                    $domainUrl = $this->connection->fetchOne(
                        'SELECT url FROM sales_channel_domain WHERE sales_channel_id = UNHEX(:scId) ORDER BY url LIKE "https://%" DESC LIMIT 1',
                        ['scId' => $salesChannelId]
                    );
                    if (is_string($domainUrl) && $domainUrl !== '') {
                        return rtrim($domainUrl, '/');
                    }
                }
                $anyDomainUrl = $this->connection->fetchOne(
                    'SELECT url FROM sales_channel_domain WHERE url LIKE "http%" ORDER BY url LIKE "https://%" DESC LIMIT 1'
                );
                if (is_string($anyDomainUrl) && $anyDomainUrl !== '') {
                    return rtrim($anyDomainUrl, '/');
                }
            } catch (\Throwable $e) {
                // Fallback to env APP_URL below
            }
        }

        $envUrl = $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? '';
        if (is_string($envUrl) && $envUrl !== '') {
            return rtrim($envUrl, '/');
        }

        return 'https://localhost';
    }

    /**
     * Get local AES-256 key & Gateway credentials.
     * Strictly opt-in: NEVER calls /v1/register unless $ensureRegistered is true AND (hybridEnabled or forced).
     */
    public function getProjectCredentials(
        bool $ensureRegistered = false,
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null
    ): array {
        $localKey = trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'localKey', $salesChannelId));
        if (strlen($localKey) !== 64 || !ctype_xdigit($localKey)) {
            $localKey = bin2hex(random_bytes(32)); // 64-char hex (256-bit AES key)
            $this->systemConfig->set(self::CONFIG_PREFIX . 'localKey', $localKey, $salesChannelId);
        }

        $projectId = trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'projectId', $salesChannelId));
        $apiSecret = trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'apiSecret', $salesChannelId));

        if ($ensureRegistered && ($projectId === '' || $apiSecret === '')) {
            if ($this->isHybridEnabled($salesChannelId)) {
                $reg = $this->registerWithGateway($salesChannelId, $siteBaseUrl, false);
                if (!empty($reg['project_id'])) {
                    $projectId = (string) $reg['project_id'];
                }
                if (!empty($reg['api_secret'])) {
                    $apiSecret = (string) $reg['api_secret'];
                }
            }
        }

        return [
            'project_id' => $projectId,
            'api_secret' => $apiSecret,
            'local_key'  => $localKey,
            'hybrid'     => $this->isHybridEnabled($salesChannelId),
        ];
    }

    /**
     * Explicit Gateway v2.3 Handshake (`POST /v1/register`).
     * Persists BOTH `projectId` and `apiSecret` in Shopware SystemConfig.
     */
    public function registerWithGateway(
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null,
        bool $force = false
    ): array {
        $creds = $this->getProjectCredentials(false, $salesChannelId, $siteBaseUrl);
        if (!$force && !empty($creds['project_id']) && !empty($creds['api_secret'])) {
            return [
                'ok'         => true,
                'project_id' => $creds['project_id'],
                'api_secret' => $creds['api_secret'],
            ];
        }

        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
        $webhookUrl = $baseUrl . '/wsc/v1/webhook';
        $endpoint = $this->getGatewayUrl($salesChannelId) . '/v1/register';

        $payload = json_encode([
            'domain'        => $baseUrl,
            'platform'      => 'shopware6',
            'webhook_url'   => $webhookUrl,
            'local_aes_key' => $creds['local_key'],
            'version'       => self::VERSION,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $resp = $this->httpPostJson($endpoint, (string) $payload, [], 12);
        if (in_array($resp['status'], [200, 201], true) && is_array($resp['json'])) {
            $newProjId = trim((string) ($resp['json']['project_id'] ?? ''));
            $newSecret = trim((string) ($resp['json']['api_secret'] ?? ''));
            if ($newProjId !== '' && $newSecret !== '') {
                $this->systemConfig->set(self::CONFIG_PREFIX . 'projectId', $newProjId, $salesChannelId);
                $this->systemConfig->set(self::CONFIG_PREFIX . 'apiSecret', $newSecret, $salesChannelId);
                return [
                    'ok'         => true,
                    'project_id' => $newProjId,
                    'api_secret' => $newSecret,
                ];
            }
        }

        $this->logger->warning('WSC Gateway /v1/register deferred or failed (HTTP ' . $resp['status'] . ').');
        return [
            'ok'     => false,
            'status' => $resp['status'],
            'error'  => $resp['error'] ?? 'gateway_register_failed',
        ];
    }

    /**
     * Computes 100% cookieless, Zero-PII daily rotating session hash:
     * SHA256(IP + UA + gmdate('Y-m-d') + localKey)
     */
    public function computeSessionHash(string $rawIp, string $userAgent, ?string $salesChannelId = null): string
    {
        $creds = $this->getProjectCredentials(false, $salesChannelId);
        return hash('sha256', $rawIp . $userAgent . gmdate('Y-m-d') . $creds['local_key']);
    }

    /**
     * Push telemetry event to local SQLite queue (Zero-PII: raw client IP is NEVER stored).
     */
    public function trackEvent(string $type, string $path = '/', ?string $referrer = '', array $meta = []): bool
    {
        try {
            $ua = (string) ($meta['user_agent'] ?? $meta['ua'] ?? '');
            if (isset($meta['ip'])) {
                $rawIp = (string) $meta['ip'];
                unset($meta['ip']);
                if ($rawIp !== '' && empty($meta['session_hash'])) {
                    $meta['session_hash'] = $this->computeSessionHash($rawIp, $ua);
                }
            }
            if (empty($meta['session_hash'])) {
                $meta['session_hash'] = $this->computeSessionHash('0.0.0.0', $ua);
            }

            $cleanPath = $path !== '' ? $path : '/';
            $now = time();

            $pdo = $this->dbManager->getQueuePdo();
            $stmt = $pdo->prepare("
                INSERT INTO wsc_events_queue (created_at, event_type, url_path, referrer, payload_json, synced)
                VALUES (:created_at, :event_type, :url_path, :referrer, :payload_json, 0)
            ");
            $stmt->execute([
                ':created_at'   => $now,
                ':event_type'   => $type,
                ':url_path'     => $cleanPath,
                ':referrer'     => $referrer ?: '',
                ':payload_json' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            // Update local daily overview counters for offline Bento & Benchmark support
            $this->recordLocalOverviewEvent($type, $meta);

            return true;
        } catch (Exception $e) {
            $this->logger->error('WSC Shopware Queue Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Records edge bot hit in both local SQLite reports (`wsc_bot_hits`, `wsc_bot_stats`) and queue (Zero-PII).
     */
    public function recordBotHit(
        array $bot,
        string $path,
        string $referrer = '',
        string $rawIp = '',
        string $userAgent = ''
    ): void {
        $sessionHash = $this->computeSessionHash($rawIp, $userAgent);
        $cleanPath = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
        $now = time();
        $today = gmdate('Y-m-d', $now);

        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("
                INSERT INTO wsc_bot_hits (hit_at, bot_name, bot_category, bot_type, url_path, session_hash)
                VALUES (:hit_at, :bot_name, :bot_category, :bot_type, :url_path, :session_hash)
            ");
            $stmt->execute([
                ':hit_at'       => $now,
                ':bot_name'     => (string) ($bot['name'] ?? 'Unknown Bot'),
                ':bot_category' => (string) ($bot['category'] ?? 'other'),
                ':bot_type'     => (string) ($bot['type'] ?? 'other'),
                ':url_path'     => $cleanPath,
                ':session_hash' => $sessionHash,
            ]);

            $statStmt = $rPdo->prepare("
                INSERT INTO wsc_bot_stats (date, bot_name, bot_category, bot_type, hits, blocked)
                VALUES (:date, :bot_name, :bot_category, :bot_type, 1, 0)
                ON CONFLICT(date, bot_name) DO UPDATE SET hits = hits + 1
            ");
            $statStmt->execute([
                ':date'         => $today,
                ':bot_name'     => (string) ($bot['name'] ?? 'Unknown Bot'),
                ':bot_category' => (string) ($bot['category'] ?? 'other'),
                ':bot_type'     => (string) ($bot['type'] ?? 'other'),
            ]);
        } catch (Exception $e) {
            $this->logger->warning('WSC Bot Hit SQLite Error: ' . $e->getMessage());
        }

        $this->trackEvent('bot_hit', $cleanPath, $referrer, [
            'bot_name'     => $bot['name'] ?? 'Unknown Bot',
            'bot_category' => $bot['category'] ?? 'other',
            'bot_type'     => $bot['type'] ?? 'other',
            'session_hash' => $sessionHash,
            'user_agent'   => $userAgent,
        ]);
    }

    private function recordLocalOverviewEvent(string $type, array $meta): void
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $today = gmdate('Y-m-d');
            $rPdo->prepare("INSERT OR IGNORE INTO wsc_daily_overview (date) VALUES (:d)")->execute([':d' => $today]);

            if ($type === 'pageview') {
                $rPdo->prepare("UPDATE wsc_daily_overview SET human_pageviews = human_pageviews + 1, human_visitors = human_visitors + 1 WHERE date = :d")
                     ->execute([':d' => $today]);
            } elseif ($type === 'bot_hit') {
                $bType = (string) ($meta['bot_type'] ?? '');
                $ragInc = ($bType === 'rag') ? 1 : 0;
                $trainInc = ($bType === 'training') ? 1 : 0;
                $rPdo->prepare("UPDATE wsc_daily_overview SET bot_hits = bot_hits + 1, ai_rag_hits = ai_rag_hits + :rag, ai_training_hits = ai_training_hits + :tr WHERE date = :d")
                     ->execute([':rag' => $ragInc, ':tr' => $trainInc, ':d' => $today]);
            } elseif ($type === 'purchase') {
                $rev = (float) ($meta['amount_total'] ?? 0.0);
                $rPdo->prepare("UPDATE wsc_daily_overview SET orders_count = orders_count + 1, revenue_total = revenue_total + :rev WHERE date = :d")
                     ->execute([':rev' => $rev, ':d' => $today]);
            }
        } catch (\Throwable $e) {
            // Non-blocking local counter
        }
    }

    /**
     * Process micro-batch export using Canonical Gateway v2.3 Envelope (`POST /v1/ingest`):
     * - Inner batch encrypted via AES-256-CBC (hex2bin($localKey), 16-byte random IV, OPENSSL_RAW_DATA)
     * - Outer envelope signed via HMAC-SHA256 (`X-WSC-Sig`) using `$apiSecret`
     */
    public function processBatchExport(
        int $batchSize = 500,
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null
    ): int {
        if (!$this->isHybridEnabled($salesChannelId)) {
            return 0;
        }

        $creds = $this->getProjectCredentials(true, $salesChannelId, $siteBaseUrl);
        if (empty($creds['project_id']) || empty($creds['api_secret']) || strlen($creds['local_key']) !== 64) {
            return 0;
        }

        $pdo = $this->dbManager->getQueuePdo();
        $stmt = $pdo->prepare("SELECT * FROM wsc_events_queue WHERE synced = 0 ORDER BY id ASC LIMIT :limit");
        $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return 0;
        }

        $mode = $this->systemConfig->getString(self::CONFIG_PREFIX . 'trackingMode', $salesChannelId) ?: 'cookieless';
        $keyBinary = hex2bin($creds['local_key']);
        if ($keyBinary === false) {
            return 0;
        }

        $ids = [];
        $grouped = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            $meta = json_decode((string) $row['payload_json'], true) ?: [];
            $sessionHash = (string) ($meta['session_hash'] ?? hash('sha256', '0.0.0.0' . gmdate('Y-m-d') . $creds['local_key']));
            $ua = (string) ($meta['user_agent'] ?? '');
            unset($meta['ip'], $meta['session_hash'], $meta['user_agent']);

            $groupKey = $sessionHash . '|' . md5($ua);
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'ip_hash' => $sessionHash,
                    'ua'      => $ua,
                    'events'  => [],
                ];
            }

            $grouped[$groupKey]['events'][] = [
                't' => (int) $row['created_at'],
                'e' => (string) $row['event_type'],
                'p' => (string) $row['url_path'],
                'r' => (string) ($row['referrer'] ?? ''),
                'm' => $meta,
            ];
        }

        $encryptedBatches = [];
        foreach ($grouped as $group) {
            $cleartextBatch = json_encode([
                'mode'        => $mode,
                'server_data' => [
                    'ip'      => $group['ip_hash'],
                    'ua'      => $group['ua'],
                    'headers' => [],
                ],
                'events'      => $group['events'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $iv = random_bytes(16);
            $ciphertext = openssl_encrypt((string) $cleartextBatch, 'AES-256-CBC', $keyBinary, OPENSSL_RAW_DATA, $iv);
            if ($ciphertext !== false) {
                $encryptedBatches[] = base64_encode($iv . $ciphertext);
            }
        }

        if (empty($encryptedBatches)) {
            return 0;
        }

        $outerJson = (string) json_encode([
            'project_id' => $creds['project_id'],
            'timestamp'  => time(),
            'source'     => 'wsc-shopware6',
            'version'    => self::VERSION,
            'data'       => $encryptedBatches,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $outerJson, $creds['api_secret']);
        $url = $this->getGatewayUrl($salesChannelId) . '/v1/ingest';

        $resp = $this->httpPostJson($url, $outerJson, [
            'X-WSC-Sig' => $signature,
        ], 15);

        if (in_array($resp['status'], [200, 201, 202], true)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE wsc_events_queue SET synced = 1 WHERE id IN ($placeholders)");
            $upd->execute($ids);
            return count($ids);
        }

        $this->logger->warning('WSC Ingest Sync HTTP ' . $resp['status']);
        return 0;
    }

    /**
     * Verifies inbound Gateway Webhook `X-WSC-Sig` against `$apiSecret`
     * (falling back to `$localKey` ONLY when `$apiSecret` has not yet been provisioned).
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, ?string $salesChannelId = null): bool
    {
        if ($signature === '' || $rawBody === '') {
            return false;
        }

        $creds = $this->getProjectCredentials(false, $salesChannelId);
        $signingSecret = !empty($creds['api_secret']) ? $creds['api_secret'] : $creds['local_key'];
        if ($signingSecret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $signingSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * SSRF Hardening: Validates that a target URL belongs to the registered site host
     * and resolves strictly to public non-private/non-reserved IP addresses.
     */
    public function validateSameHostPublicUrl(string $targetUrl, string $siteBaseUrl): array
    {
        $parsedTarget = parse_url(trim($targetUrl));
        $parsedSite = parse_url(trim($siteBaseUrl));

        $scheme = strtolower((string) ($parsedTarget['scheme'] ?? ''));
        $targetHost = strtolower((string) ($parsedTarget['host'] ?? ''));
        $siteHost = strtolower((string) ($parsedSite['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || $targetHost === '') {
            return ['valid' => false, 'error' => 'Invalid URL scheme or host.'];
        }

        if ($siteHost !== '' && $targetHost !== $siteHost) {
            return ['valid' => false, 'error' => 'Target URL must match the registered site hostname (' . $siteHost . ').'];
        }

        if (!$this->hostResolvesToPublicIp($targetHost)) {
            return ['valid' => false, 'error' => 'Target hostname resolves to a private, loopback, or reserved IP address (SSRF protection).'];
        }

        return ['valid' => true, 'url' => trim($targetUrl), 'host' => $targetHost];
    }

    public function hostResolvesToPublicIp(string $host): bool
    {
        $cleanHost = trim($host, '[]');
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            return filter_var($cleanHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        $ips = [];
        $records = @dns_get_record($cleanHost, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $rec) {
                if (!empty($rec['ip'])) {
                    $ips[] = $rec['ip'];
                }
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            $v4 = @gethostbynamel($cleanHost);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
        }

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Store 7-Card dashboard reports from Gateway Webhook.
     */
    public function saveWebhookReport(array $data): bool
    {
        try {
            $pdo = $this->dbManager->getReportsPdo();
            $stmt = $pdo->prepare("
                INSERT INTO wsc_dashboard_reports (id, report_type, period_type, updated_at, payload)
                VALUES (:id, :report_type, :period_type, :updated_at, :payload)
                ON CONFLICT(id) DO UPDATE SET
                    updated_at = excluded.updated_at,
                    payload = excluded.payload
            ");

            $now = time();

            if (!empty($data['insights']) && is_array($data['insights'])) {
                foreach ($data['insights'] as $item) {
                    $type = (string) ($item['report_type'] ?? $item['type'] ?? 'unknown');
                    $stmt->execute([
                        ':id'          => $type,
                        ':report_type' => $type,
                        ':period_type' => 'multi',
                        ':updated_at'  => $now,
                        ':payload'     => json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            $cardMap = [
                'card1'           => ['id' => 'card1_traffic',   'type' => 'traffic'],
                'card1_traffic'   => ['id' => 'card1_traffic',   'type' => 'traffic'],
                'card2'           => ['id' => 'card2_insights',  'type' => 'insights'],
                'card2_insights'  => ['id' => 'card2_insights',  'type' => 'insights'],
                'card3'           => ['id' => 'card3_bots',      'type' => 'bots'],
                'card3_bots'      => ['id' => 'card3_bots',      'type' => 'bots'],
                'card4'           => ['id' => 'card_engagement', 'type' => 'engagement'],
                'card_engagement' => ['id' => 'card_engagement', 'type' => 'engagement'],
                'card5'           => ['id' => 'card_outbound',   'type' => 'outbound'],
                'card_outbound'   => ['id' => 'card_outbound',   'type' => 'outbound'],
                'card6'           => ['id' => 'card_ecommerce',  'type' => 'ecommerce'],
                'card_ecommerce'  => ['id' => 'card_ecommerce',  'type' => 'ecommerce'],
                'card7'           => ['id' => 'domain_health',   'type' => 'sentinel'],
                'domain_health'   => ['id' => 'domain_health',   'type' => 'sentinel'],
                'sentinel'        => ['id' => 'domain_health',   'type' => 'sentinel'],
                'pagespeed'       => ['id' => 'pagespeed',       'type' => 'pagespeed'],
                'pagespeed_audit' => ['id' => 'pagespeed',       'type' => 'pagespeed'],
                'benchmarks'      => ['id' => 'benchmarks',      'type' => 'benchmarks'],
            ];

            foreach ($cardMap as $key => $meta) {
                if (isset($data[$key]) && is_array($data[$key])) {
                    $stmt->execute([
                        ':id'          => $meta['id'],
                        ':report_type' => $meta['type'],
                        ':period_type' => 'multi',
                        ':updated_at'  => $now,
                        ':payload'     => json_encode($data[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error('WSC Webhook Save Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve canonical 7 Cards + Benchmarks for Dashboard View.
     */
    public function getDashboardCards(): array
    {
        $cards = [
            'card1_traffic'   => null,
            'card2_insights'  => null,
            'card3_bots'      => null,
            'card_engagement' => null,
            'card_outbound'   => null,
            'card_ecommerce'  => null,
            'domain_health'   => null,
            'pagespeed'       => null,
            'benchmarks'      => null,
        ];

        try {
            $pdo = $this->dbManager->getReportsPdo();
            $stmt = $pdo->query("SELECT id, payload FROM wsc_dashboard_reports");
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $id = $row['id'];
                $decoded = json_decode((string) $row['payload'], true);

                if (array_key_exists($id, $cards)) {
                    $cards[$id] = $decoded;
                } elseif ($id === 'card1') {
                    $cards['card1_traffic'] = $decoded;
                } elseif ($id === 'card2') {
                    $cards['card2_insights'] = $decoded;
                } elseif ($id === 'card3') {
                    $cards['card3_bots'] = $decoded;
                } elseif ($id === 'card4') {
                    $cards['card_engagement'] = $decoded;
                } elseif ($id === 'card5') {
                    $cards['card_outbound'] = $decoded;
                } elseif ($id === 'card6') {
                    $cards['card_ecommerce'] = $decoded;
                } elseif ($id === 'sentinel' || $id === 'card7') {
                    $cards['domain_health'] = $decoded;
                } elseif ($id === 'pagespeed_audit') {
                    $cards['pagespeed'] = $decoded;
                }
            }
        } catch (Exception $e) {
            $this->logger->error('WSC Read Cards Error: ' . $e->getMessage());
        }

        $cards['card1'] = $cards['card1_traffic'];
        $cards['card2'] = $cards['card2_insights'];
        $cards['card3'] = $cards['card3_bots'];
        $cards['sentinel'] = $cards['domain_health'];

        return $cards;
    }

    /**
     * Trigger asynchronous SpeedSentinel lab audit via Gateway (SSRF-hardened + apiSecret HMAC).
     */
    public function triggerSpeedSentinelAudit(
        string $url,
        string $strategy = 'mobile',
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
        $check = $this->validateSameHostPublicUrl($url !== '' ? $url : $baseUrl, $baseUrl);
        if (!$check['valid']) {
            return ['ok' => false, 'error' => 'ssrf_validation_failed', 'message' => $check['error']];
        }

        $creds = $this->getProjectCredentials(true, $salesChannelId, $baseUrl);
        if (empty($creds['project_id']) || empty($creds['api_secret'])) {
            return [
                'ok'      => false,
                'error'   => 'hybrid_not_connected',
                'message' => 'Enable Hybrid Cloud Sync in WSC settings to dispatch remote SpeedSentinel Playwright audits.',
            ];
        }

        $payload = (string) json_encode([
            'project_id' => $creds['project_id'],
            'url'        => $check['url'],
            'strategy'   => in_array($strategy, ['mobile', 'desktop'], true) ? $strategy : 'mobile',
            'sync'       => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $payload, $creds['api_secret']);
        $endpoint = $this->getGatewayUrl($salesChannelId) . '/v1/pagespeed/audit';

        $resp = $this->httpPostJson($endpoint, $payload, ['X-WSC-Sig' => $signature], 15);
        if (in_array($resp['status'], [200, 201, 202], true)) {
            return is_array($resp['json']) ? array_merge(['ok' => true], $resp['json']) : ['ok' => true, 'status' => 'queued'];
        }

        return ['ok' => false, 'error' => $resp['error'] ?? ('HTTP ' . $resp['status'])];
    }

    /**
     * Trigger asynchronous Sentinel Domain & Security scan via Gateway (SSRF-hardened + apiSecret HMAC).
     */
    public function triggerSentinelScan(
        string $domain,
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
        $siteHost = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?: ''));
        $cleanDomain = strtolower(trim(preg_replace('#^https?://#i', '', $domain), '/'));
        if ($cleanDomain === '') {
            $cleanDomain = $siteHost;
        }

        if ($siteHost !== '' && $cleanDomain !== $siteHost) {
            return [
                'ok'      => false,
                'error'   => 'domain_mismatch',
                'message' => 'Sentinel scans are restricted to the registered storefront hostname (' . $siteHost . ').',
            ];
        }

        if (!$this->hostResolvesToPublicIp($cleanDomain)) {
            return [
                'ok'      => false,
                'error'   => 'ssrf_validation_failed',
                'message' => 'Domain resolves to a local/private IP address and cannot be audited by cloud workers.',
            ];
        }

        $creds = $this->getProjectCredentials(true, $salesChannelId, $baseUrl);
        if (empty($creds['project_id']) || empty($creds['api_secret'])) {
            return [
                'ok'      => false,
                'error'   => 'hybrid_not_connected',
                'message' => 'Enable Hybrid Cloud Sync in WSC settings to dispatch remote Sentinel scans.',
            ];
        }

        $payload = (string) json_encode([
            'project_id' => $creds['project_id'],
            'domain'     => $cleanDomain,
            'sync'       => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $payload, $creds['api_secret']);
        $endpoint = $this->getGatewayUrl($salesChannelId) . '/v1/sentinel/scan';

        $resp = $this->httpPostJson($endpoint, $payload, ['X-WSC-Sig' => $signature], 15);
        if (in_array($resp['status'], [200, 201, 202], true)) {
            return is_array($resp['json']) ? array_merge(['ok' => true], $resp['json']) : ['ok' => true, 'status' => 'queued'];
        }

        return ['ok' => false, 'error' => $resp['error'] ?? ('HTTP ' . $resp['status'])];
    }

    // =========================================================================
    // PAKET CMS-2: Live WSC Member Credential Bridge, Co-Branding & Partner Feed
    // =========================================================================

    public function getMembershipState(?string $salesChannelId = null): array
    {
        $key = strtoupper(trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'licenseKey', $salesChannelId)));
        $status = strtolower(trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'licenseStatus', $salesChannelId))) ?: 'unregistered';
        if (!in_array($status, ['unregistered', 'active', 'expired', 'revoked', 'sanctioned'], true)) {
            $status = 'unregistered';
        }

        $tier = strtolower(trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'licenseTier', $salesChannelId))) ?: 'community';
        if (!in_array($tier, ['community', 'member', 'agency', 'founding_partner'], true)) {
            $tier = 'community';
        }

        $holder = trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'licenseHolder', $salesChannelId));
        $expires = $this->systemConfig->getInt(self::CONFIG_PREFIX . 'licenseExpires', $salesChannelId);
        $isDemo = $this->systemConfig->getBool(self::CONFIG_PREFIX . 'licenseIsDemo', $salesChannelId) || isset(self::DEMO_KEYS[$key]);

        if ($status === 'active' && $expires > 0 && $expires < time()) {
            $status = 'expired';
        }

        $isActiveLicensed = ($key !== '' && $status === 'active' && in_array($tier, ['member', 'agency', 'founding_partner'], true));
        $isAgencyTier = $isActiveLicensed && in_array($tier, ['agency', 'founding_partner'], true);

        $tierLabels = [
            'community'        => 'Community (Open Source)',
            'member'           => 'Verified WSC Member',
            'agency'           => 'Certified WSC Partner (Agency)',
            'founding_partner' => 'WSC Founding Partner',
        ];

        $rawBranding = $this->systemConfig->getString(self::CONFIG_PREFIX . 'coBranding', $salesChannelId);
        $decodedBranding = json_decode($rawBranding, true);
        $coBranding = $this->normalizeCoBranding(is_array($decodedBranding) ? $decodedBranding : []);

        return [
            'key'                => $key,
            'status'             => $status,
            'tier'               => $tier,
            'tier_label'         => $tierLabels[$tier] ?? $tierLabels['community'],
            'holder'             => $holder,
            'expires_at'         => $expires,
            'is_demo'            => $isDemo,
            'is_active_licensed' => $isActiveLicensed,
            'is_agency_tier'     => $isAgencyTier,
            'verify_url'         => $key !== '' ? ('https://www.worldsearchcouncil.org/verify/' . rawurlencode($key)) : '',
            'member_console_url' => 'https://www.worldsearchcouncil.org/member',
            'co_branding'        => $coBranding,
        ];
    }

    private function normalizeCoBranding(array $input): array
    {
        $logoUrl = trim((string) ($input['logo_url'] ?? $input['agency_logo'] ?? ''));
        $supportUrl = trim((string) ($input['support_url'] ?? $input['agency_url'] ?? ''));
        $email = trim((string) ($input['support_email'] ?? ''));

        return [
            'enabled'       => !empty($input['enabled']),
            'agency_name'   => strip_tags(trim((string) ($input['agency_name'] ?? ''))),
            'logo_url'      => (filter_var($logoUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $logoUrl)) ? $logoUrl : '',
            'support_url'   => (filter_var($supportUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $supportUrl)) ? $supportUrl : '',
            'support_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'custom_footer' => strip_tags(trim((string) ($input['custom_footer'] ?? $input['custom_notice'] ?? ''))),
        ];
    }

    /**
     * Verifies license key against `POST https://www.worldsearchcouncil.org/api/v1/license/verify`.
     * Offline fallback applies STRICTLY to `WSC-MEMBER-DEMO`, `WSC-AGENCY-DEMO`, `WSC-PARTNER-DEMO`.
     */
    public function verifyLicense(
        string $rawKey,
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null,
        ?array $coBrandingUpdate = null
    ): array {
        $key = strtoupper(trim($rawKey));
        if ($key === '') {
            return $this->disconnectLicense($salesChannelId);
        }

        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
        $creds = $this->getProjectCredentials(false, $salesChannelId, $baseUrl);

        $payloadArr = [
            'license_key' => $key,
            'domain'      => $baseUrl,
            'site_url'    => $baseUrl,
            'platform'    => 'shopware6',
            'version'     => self::VERSION,
            'wsc_version' => self::VERSION,
            'project_id'  => $creds['project_id'],
        ];

        if ($coBrandingUpdate !== null) {
            $payloadArr['co_branding_update'] = $coBrandingUpdate;
        }

        $resp = $this->httpPostJson(
            self::PORTAL_LICENSE_VERIFY_URL,
            (string) json_encode($payloadArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ['User-Agent' => 'WSC-Shopware6/' . self::VERSION],
            12
        );

        // Network unreachable or 5xx: Offline sandbox fallback strictly for official DEMO keys
        if ($resp['status'] === 0 || $resp['status'] >= 500 || !is_array($resp['json'])) {
            if (isset(self::DEMO_KEYS[$key])) {
                return $this->activateOfflineDemoKey($key, $salesChannelId);
            }
            return [
                'ok'            => false,
                'success'       => false,
                'network_error' => true,
                'message'       => 'Could not reach WSC Authority Portal (www.worldsearchcouncil.org). Please check connectivity.',
                'membership'    => $this->getMembershipState($salesChannelId),
            ];
        }

        $body = $resp['json'];
        $remoteStatus = strtolower(trim((string) ($body['status'] ?? '')));
        $isValid = !empty($body['valid']) && ($remoteStatus === '' || $remoteStatus === 'active');

        if ($isValid) {
            $rawTier = strtolower(trim((string) ($body['tier'] ?? 'member')));
            $tier = in_array($rawTier, ['member', 'agency', 'founding_partner'], true) ? $rawTier : 'member';
            $holder = strip_tags(trim((string) ($body['holder'] ?? 'Verified WSC Member')));
            $expires = isset($body['expires_at']) ? (int) $body['expires_at'] : (time() + 31536000);
            $isDemo = !empty($body['is_demo']) || isset(self::DEMO_KEYS[$key]);

            $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseKey', $key, $salesChannelId);
            $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseStatus', 'active', $salesChannelId);
            $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseTier', $tier, $salesChannelId);
            $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseHolder', $holder, $salesChannelId);
            $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseExpires', $expires, $salesChannelId);
            $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseIsDemo', $isDemo, $salesChannelId);

            if (!empty($body['co_branding']) && is_array($body['co_branding'])) {
                $existing = $this->getMembershipState($salesChannelId)['co_branding'];
                $merged = $this->normalizeCoBranding(array_merge($existing, $body['co_branding'], ['enabled' => true]));
                $this->systemConfig->set(self::CONFIG_PREFIX . 'coBranding', (string) json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $salesChannelId);
            }

            $state = $this->getMembershipState($salesChannelId);
            return [
                'ok'         => true,
                'success'    => true,
                'message'    => $isDemo
                    ? 'Verified in Demo Sandbox Mode (' . $state['tier_label'] . ').'
                    : 'WSC Credential verified: ' . $state['tier_label'] . ' (' . $holder . ').',
                'membership' => $state,
            ];
        }

        // Demo key fallback if portal returned 404/unrecognized in local staging
        if (isset(self::DEMO_KEYS[$key]) && $remoteStatus === '') {
            return $this->activateOfflineDemoKey($key, $salesChannelId);
        }

        $mappedStatus = in_array($remoteStatus, ['expired', 'revoked', 'sanctioned'], true) ? $remoteStatus : 'unregistered';
        $errMsg = strip_tags(trim((string) ($body['message'] ?? 'Invalid or unrecognized WSC credential key.')));

        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseKey', $mappedStatus === 'unregistered' ? '' : $key, $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseStatus', $mappedStatus, $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseTier', 'community', $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseIsDemo', false, $salesChannelId);

        return [
            'ok'         => false,
            'success'    => false,
            'status'     => $mappedStatus,
            'message'    => $errMsg,
            'membership' => $this->getMembershipState($salesChannelId),
        ];
    }

    private function activateOfflineDemoKey(string $key, ?string $salesChannelId = null): array
    {
        $meta = self::DEMO_KEYS[$key];
        $expires = time() + 31536000;

        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseKey', $key, $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseStatus', 'active', $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseTier', $meta['tier'], $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseHolder', $meta['holder'], $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseExpires', $expires, $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseIsDemo', true, $salesChannelId);

        $state = $this->getMembershipState($salesChannelId);
        return [
            'ok'         => true,
            'success'    => true,
            'is_demo'    => true,
            'message'    => 'Activated in Offline Demo Sandbox Mode (' . $state['tier_label'] . ').',
            'membership' => $state,
        ];
    }

    public function disconnectLicense(?string $salesChannelId = null): array
    {
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseKey', '', $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseStatus', 'unregistered', $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseTier', 'community', $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseHolder', '', $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseExpires', 0, $salesChannelId);
        $this->systemConfig->set(self::CONFIG_PREFIX . 'licenseIsDemo', false, $salesChannelId);

        return [
            'ok'         => true,
            'success'    => true,
            'message'    => 'Credential disconnected. Reverted to Open-Source Community tier (all local features remain unlocked).',
            'membership' => $this->getMembershipState($salesChannelId),
        ];
    }

    public function saveCoBranding(array $brandingInput, ?string $salesChannelId = null, ?string $siteBaseUrl = null): array
    {
        $normalized = $this->normalizeCoBranding($brandingInput);
        $this->systemConfig->set(
            self::CONFIG_PREFIX . 'coBranding',
            (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $salesChannelId
        );

        $state = $this->getMembershipState($salesChannelId);
        if ($state['is_agency_tier'] && !$state['is_demo'] && $state['key'] !== '') {
            $this->verifyLicense($state['key'], $salesChannelId, $siteBaseUrl, $normalized);
            $state = $this->getMembershipState($salesChannelId);
        }

        return [
            'ok'          => true,
            'success'     => true,
            'message'     => 'Agency Co-Branding configuration saved.',
            'co_branding' => $normalized,
            'membership'  => $state,
        ];
    }

    /**
     * Retrieves featured WSC Partners (`GET https://www.worldsearchcouncil.org/api/v1/partners/featured`),
     * cached 12h in SQLite when licensed or `hybridEnabled`, curated local fallback otherwise.
     */
    public function getFeaturedPartners(int $limit = 3, ?string $salesChannelId = null): array
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("SELECT updated_at, payload FROM wsc_dashboard_reports WHERE id = 'partners_feed'");
            $stmt->execute();
            $cached = $stmt->fetch();
            if ($cached && ((int) $cached['updated_at'] >= (time() - 43200))) {
                $decoded = json_decode((string) $cached['payload'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    return array_slice($decoded, 0, $limit);
                }
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        $membership = $this->getMembershipState($salesChannelId);
        $canFetchRemote = $membership['is_active_licensed'] || $this->isHybridEnabled($salesChannelId);

        if ($canFetchRemote) {
            $resp = $this->httpGetJson(self::PORTAL_PARTNERS_FEED_URL . '?limit=6', 6);
            if ($resp['status'] === 200 && is_array($resp['json']) && !empty($resp['json']['partners']) && is_array($resp['json']['partners'])) {
                $normalized = [];
                foreach ($resp['json']['partners'] as $item) {
                    if (empty($item['name'])) {
                        continue;
                    }
                    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($item['slug'] ?? str_replace(' ', '-', (string) $item['name']))));
                    $profileUrl = !empty($item['profile_url']) ? (string) $item['profile_url'] : ('https://www.worldsearchcouncil.org/partner/' . $slug);
                    $normalized[] = [
                        'name'        => strip_tags((string) $item['name']),
                        'slug'        => $slug,
                        'specialty'   => strip_tags((string) ($item['specialty'] ?? 'GEO & Search Health')),
                        'location'    => strip_tags((string) ($item['location'] ?? 'Global')),
                        'rating'      => strip_tags((string) ($item['rating'] ?? '5.0')),
                        'badge'       => strip_tags((string) ($item['badge'] ?? 'Certified Partner')),
                        'profile_url' => $profileUrl,
                    ];
                }

                if (!empty($normalized)) {
                    $this->saveInternalReportCache('partners_feed', 'partners', $normalized);
                    return array_slice($normalized, 0, $limit);
                }
            }
        }

        return array_slice([
            [
                'name'        => 'Apex Search Architecture',
                'slug'        => 'apex-search-architecture',
                'specialty'   => 'Enterprise Shopware GEO & Agentic Indexing',
                'location'    => 'London, UK',
                'rating'      => '5.0',
                'badge'       => 'Founding Partner',
                'profile_url' => 'https://www.worldsearchcouncil.org/partner/apex-search-architecture?ref=shopware6',
            ],
            [
                'name'        => 'Search Sentinel Labs',
                'slug'        => 'search-sentinel-labs',
                'specialty'   => 'AI Bot-Defense & Core Web Vitals',
                'location'    => 'Cologne, DE',
                'rating'      => '5.0',
                'badge'       => 'Founding Partner',
                'profile_url' => 'https://www.worldsearchcouncil.org/partner/search-sentinel-labs?ref=shopware6',
            ],
            [
                'name'        => 'IndexWave Digital',
                'slug'        => 'indexwave-digital',
                'specialty'   => 'Schema 2.0 & E-Commerce Reality Matrix',
                'location'    => 'Zurich, CH',
                'rating'      => '4.9',
                'badge'       => 'Certified Partner',
                'profile_url' => 'https://www.worldsearchcouncil.org/partner/indexwave-digital?ref=shopware6',
            ],
        ], 0, $limit);
    }

    // =========================================================================
    // PAKET CMS-3.1: AI Coverage Inspector (`POST /v1/ai-coverage/query`)
    // =========================================================================

    public function queryAiCoverage(
        string $rawQuery,
        string $timeframe = '30d',
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
        $canonical = $this->canonicalizeCoverageQuery($rawQuery, $baseUrl);
        $tf = in_array($timeframe, ['7d', '30d'], true) ? $timeframe : '30d';

        if ($this->isHybridEnabled($salesChannelId)) {
            $creds = $this->getProjectCredentials(true, $salesChannelId, $baseUrl);
            if (!empty($creds['project_id']) && !empty($creds['api_secret'])) {
                $payload = (string) json_encode([
                    'project_id' => $creds['project_id'],
                    'query'      => $canonical['canonical_url'],
                    'timeframe'  => $tf,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $sig = hash_hmac('sha256', $payload, $creds['api_secret']);
                $resp = $this->httpPostJson(
                    $this->getGatewayUrl($salesChannelId) . '/v1/ai-coverage/query',
                    $payload,
                    ['X-WSC-Sig' => $sig],
                    10
                );

                if ($resp['status'] === 200 && is_array($resp['json']) && ($resp['json']['status'] ?? '') === 'success') {
                    $gw = $resp['json'];
                    $gw['ok'] = true;
                    $gw['source'] = 'gateway';
                    return $gw;
                }
            }
        }

        return $this->queryLocalAiCoverage($canonical);
    }

    private function canonicalizeCoverageQuery(string $query, string $siteBaseUrl): array
    {
        $raw = trim($query);
        $siteHost = strtolower((string) (parse_url($siteBaseUrl, PHP_URL_HOST) ?: ''));

        if (!str_starts_with($raw, 'http://') && !str_starts_with($raw, 'https://')) {
            if (str_starts_with($raw, '/')) {
                $raw = rtrim($siteBaseUrl, '/') . $raw;
            } elseif (str_contains($raw, '.')) {
                $raw = 'https://' . $raw;
            } else {
                $raw = rtrim($siteBaseUrl, '/') . '/' . ltrim($raw, '/');
            }
        }

        $parsed = parse_url($raw);
        $host = strtolower((string) ($parsed['host'] ?? $siteHost));
        $path = (string) ($parsed['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        return [
            'raw'             => $query,
            'canonical_url'   => $raw,
            'host'            => $host,
            'root_domain'     => $host,
            'url_path'        => $path,
            'is_cross_domain' => ($siteHost !== '' && $host !== $siteHost),
        ];
    }

    private function queryLocalAiCoverage(array $canonical): array
    {
        $now = time();
        $thirtyDaysAgo = $now - (30 * 86400);
        $sevenDaysAgo = $now - (7 * 86400);

        $urlMetrics = [
            'is_known_in_network' => false,
            'total_hits_30d'      => 0,
            'rag' => [
                'visited'         => false,
                'hits_7d'         => 0,
                'hits_30d'        => 0,
                'last_seen'       => null,
                'last_seen_human' => 'Never seen locally',
                'top_bots'        => [],
            ],
            'training' => [
                'visited'         => false,
                'hits_7d'         => 0,
                'hits_30d'        => 0,
                'last_seen'       => null,
                'last_seen_human' => 'Never seen locally',
                'top_bots'        => [],
            ],
        ];

        $domainCoverage = [
            'root_domain'           => $canonical['root_domain'],
            'known_urls_count'      => max(1, $this->estimateKnownUrlsCount()),
            'ai_covered_urls_count' => 0,
            'ai_coverage_pct'       => 0.0,
            'rag_coverage_pct'      => 0.0,
            'training_coverage_pct' => 0.0,
            'network_confidence'    => $canonical['is_cross_domain'] ? 'low' : 'local_sqlite',
        ];

        if ($canonical['is_cross_domain']) {
            return [
                'ok'              => true,
                'status'          => 'success',
                'source'          => 'local_offline_notice',
                'query'           => $canonical,
                'url_metrics'     => $urlMetrics,
                'domain_coverage' => $domainCoverage,
                'notice'          => 'Cross-domain AI visibility queries require Opt-In Hybrid Cloud Sync to query the global WSC sensor network.',
            ];
        }

        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $path = $canonical['url_path'];
            $altPath = $path !== '/' ? rtrim($path, '/') : '/';

            $stmt = $rPdo->prepare("
                SELECT bot_name, bot_type, hit_at
                FROM wsc_bot_hits
                WHERE (url_path = :p1 OR url_path = :p2)
                  AND hit_at >= :cutoff
                ORDER BY hit_at DESC
            ");
            $stmt->execute([':p1' => $path, ':p2' => $altPath, ':cutoff' => $thirtyDaysAgo]);
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                $urlMetrics['is_known_in_network'] = true;
                $urlMetrics['total_hits_30d'] = count($rows);

                $ragBots = [];
                $trainBots = [];
                $rag7 = 0;
                $rag30 = 0;
                $train7 = 0;
                $train30 = 0;
                $lastRag = 0;
                $lastTrain = 0;

                foreach ($rows as $r) {
                    $bName = (string) $r['bot_name'];
                    $bType = (string) $r['bot_type'];
                    $hitAt = (int) $r['hit_at'];

                    if ($bType === 'rag') {
                        $rag30++;
                        if ($hitAt >= $sevenDaysAgo) {
                            $rag7++;
                        }
                        if ($hitAt > $lastRag) {
                            $lastRag = $hitAt;
                        }
                        $ragBots[$bName] = ($ragBots[$bName] ?? 0) + 1;
                    } elseif ($bType === 'training') {
                        $train30++;
                        if ($hitAt >= $sevenDaysAgo) {
                            $train7++;
                        }
                        if ($hitAt > $lastTrain) {
                            $lastTrain = $hitAt;
                        }
                        $trainBots[$bName] = ($trainBots[$bName] ?? 0) + 1;
                    }
                }

                arsort($ragBots);
                arsort($trainBots);

                $topRag = [];
                foreach (array_slice($ragBots, 0, 5, true) as $n => $h) {
                    $topRag[] = ['name' => $n, 'hits' => $h];
                }
                $topTrain = [];
                foreach (array_slice($trainBots, 0, 5, true) as $n => $h) {
                    $topTrain[] = ['name' => $n, 'hits' => $h];
                }

                $urlMetrics['rag'] = [
                    'visited'         => $rag30 > 0,
                    'hits_7d'         => $rag7,
                    'hits_30d'        => $rag30,
                    'last_seen'       => $lastRag ?: null,
                    'last_seen_human' => $lastRag > 0 ? gmdate('Y-m-d H:i', $lastRag) . ' UTC' : 'Never seen locally',
                    'top_bots'        => $topRag,
                ];
                $urlMetrics['training'] = [
                    'visited'         => $train30 > 0,
                    'hits_7d'         => $train7,
                    'hits_30d'        => $train30,
                    'last_seen'       => $lastTrain ?: null,
                    'last_seen_human' => $lastTrain > 0 ? gmdate('Y-m-d H:i', $lastTrain) . ' UTC' : 'Never seen locally',
                    'top_bots'        => $topTrain,
                ];
            }

            $domStmt = $rPdo->prepare("SELECT DISTINCT url_path, bot_type FROM wsc_bot_hits WHERE hit_at >= :cutoff");
            $domStmt->execute([':cutoff' => $thirtyDaysAgo]);
            $all = $domStmt->fetchAll();

            $aiUrls = [];
            $ragUrls = [];
            $trainUrls = [];
            foreach ($all as $item) {
                $u = (string) $item['url_path'];
                $bt = (string) $item['bot_type'];
                if ($bt === 'rag' || $bt === 'training') {
                    $aiUrls[$u] = true;
                }
                if ($bt === 'rag') {
                    $ragUrls[$u] = true;
                }
                if ($bt === 'training') {
                    $trainUrls[$u] = true;
                }
            }

            $knownCount = max(count($aiUrls), $domainCoverage['known_urls_count'], 1);
            $domainCoverage['known_urls_count'] = $knownCount;
            $domainCoverage['ai_covered_urls_count'] = count($aiUrls);
            $domainCoverage['ai_coverage_pct'] = round((count($aiUrls) / $knownCount) * 100, 1);
            $domainCoverage['rag_coverage_pct'] = round((count($ragUrls) / $knownCount) * 100, 1);
            $domainCoverage['training_coverage_pct'] = round((count($trainUrls) / $knownCount) * 100, 1);
        } catch (\Throwable $e) {
            $this->logger->warning('WSC Local AI Coverage Error: ' . $e->getMessage());
        }

        return [
            'ok'              => true,
            'status'          => 'success',
            'source'          => 'local_sqlite',
            'query'           => $canonical,
            'url_metrics'     => $urlMetrics,
            'domain_coverage' => $domainCoverage,
        ];
    }

    private function estimateKnownUrlsCount(): int
    {
        if ($this->connection !== null) {
            try {
                $prodCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM product WHERE active = 1 AND parent_id IS NULL');
                $catCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM category WHERE active = 1');
                if (($prodCount + $catCount) > 0) {
                    return $prodCount + $catCount;
                }
            } catch (\Throwable $e) {
                // Ignore and fallback to SQLite URL count
            }
        }

        try {
            $qPdo = $this->dbManager->getQueuePdo();
            $cnt = (int) $qPdo->query("SELECT COUNT(DISTINCT url_path) FROM wsc_events_queue")->fetchColumn();
            return max(1, $cnt);
        } catch (\Throwable $e) {
            return 10;
        }
    }

    // =========================================================================
    // PAKET CMS-3.2: Dynamic Industry Benchmarks (`/v1/benchmarks/query` & `classify`)
    // =========================================================================

    public function getCurrentVertical(?string $salesChannelId = null): string
    {
        $saved = trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'industryVertical', $salesChannelId));
        if ($saved !== '' && isset(self::BENCHMARK_TAXONOMY[$saved])) {
            return $saved;
        }
        return '/Shopping/Apparel';
    }

    public function collectLocalBenchmarkMetrics(int $days = 7): array
    {
        $cutoff = time() - (max(1, $days) * 86400);
        $dateCutoff = gmdate('Y-m-d', $cutoff);

        $aiRagShare = 0.0;
        $engagedTime = 0.0;
        $aiCoveragePct = 0.0;
        $humanRatioPct = 100.0;

        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $qPdo = $this->dbManager->getQueuePdo();

            // 1. AI RAG Share from wsc_bot_hits
            $stmtTot = $rPdo->prepare("SELECT COUNT(*) FROM wsc_bot_hits WHERE hit_at >= :c");
            $stmtTot->execute([':c' => $cutoff]);
            $totBots = (int) $stmtTot->fetchColumn();

            if ($totBots > 0) {
                $stmtRag = $rPdo->prepare("
                    SELECT COUNT(*) FROM wsc_bot_hits
                    WHERE hit_at >= :c AND (bot_type = 'rag' OR bot_name LIKE '%GPTBot%' OR bot_name LIKE '%Perplexity%' OR bot_name LIKE '%Claude%')
                ");
                $stmtRag->execute([':c' => $cutoff]);
                $ragHits = (int) $stmtRag->fetchColumn();
                $aiRagShare = round(($ragHits / $totBots) * 100, 1);
            }

            // 2. Human Ratio from wsc_daily_overview
            $ovStmt = $rPdo->prepare("SELECT SUM(human_pageviews) AS hp, SUM(bot_hits) AS bh FROM wsc_daily_overview WHERE date >= :dc");
            $ovStmt->execute([':dc' => $dateCutoff]);
            $ov = $ovStmt->fetch();
            $hp = (int) ($ov['hp'] ?? 0);
            $bh = (int) ($ov['bh'] ?? 0);
            if (($hp + $bh) > 0) {
                $humanRatioPct = round(($hp / ($hp + $bh)) * 100, 1);
            }

            // 3. Engaged time from queue time_on_page events or cached card_engagement
            $engStmt = $qPdo->prepare("SELECT payload_json FROM wsc_events_queue WHERE event_type IN ('time_on_page', 'engagement') AND created_at >= :c LIMIT 200");
            $engStmt->execute([':c' => $cutoff]);
            $engRows = $engStmt->fetchAll();
            if (!empty($engRows)) {
                $sumSec = 0.0;
                $cntSec = 0;
                foreach ($engRows as $er) {
                    $m = json_decode((string) $er['payload_json'], true) ?: [];
                    $sec = (float) ($m['seconds'] ?? $m['duration'] ?? $m['engaged_time'] ?? 0);
                    if ($sec > 0) {
                        $sumSec += $sec;
                        $cntSec++;
                    }
                }
                if ($cntSec > 0) {
                    $engagedTime = round($sumSec / $cntSec, 1);
                }
            }
            if ($engagedTime <= 0.0) {
                $cards = $this->getDashboardCards();
                $rawEng = (string) ($cards['card1_traffic']['data_7d']['avg_engaged_time'] ?? $cards['card_engagement']['data_7d']['avg_reading_time'] ?? '');
                if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $rawEng, $matches)) {
                    $engagedTime = (float) $matches[1];
                }
            }

            // 4. AI Coverage % from distinct AI-crawled URLs vs known URLs
            $aiUrlStmt = $rPdo->prepare("SELECT COUNT(DISTINCT url_path) FROM wsc_bot_hits WHERE hit_at >= :c AND bot_type IN ('rag', 'training')");
            $aiUrlStmt->execute([':c' => time() - (30 * 86400)]);
            $aiUrlCnt = (int) $aiUrlStmt->fetchColumn();
            if ($aiUrlCnt > 0) {
                $known = max($aiUrlCnt, $this->estimateKnownUrlsCount(), 1);
                $aiCoveragePct = min(100.0, round(($aiUrlCnt / $known) * 100, 1));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('WSC Local Benchmark Metric Error: ' . $e->getMessage());
        }

        return [
            'ai_rag_share'    => $aiRagShare,
            'engaged_time'    => $engagedTime,
            'ai_coverage_pct' => $aiCoveragePct,
            'human_ratio_pct' => $humanRatioPct,
        ];
    }

    public function calculateBenchmarkRating(float $siteVal, float $p50, float $p90): array
    {
        $delta = ($p50 <= 0.0001) ? 0.0 : round((($siteVal - $p50) / $p50) * 100, 1);

        if ($siteVal >= $p90) {
            return [
                'delta_pct' => $delta,
                'rating'    => 'top_10',
                'badge'     => '🏆 Top 10%',
                'css_class' => 'wsc-bm-top10',
            ];
        }

        if ($delta > 10.0) {
            return [
                'delta_pct' => $delta,
                'rating'    => 'above_average',
                'badge'     => sprintf('🟢 +%s%% vs. Median', number_format($delta, 1)),
                'css_class' => 'wsc-bm-above',
            ];
        }

        if ($delta < -10.0) {
            return [
                'delta_pct' => $delta,
                'rating'    => 'below_average',
                'badge'     => sprintf('🔴 %s%% vs. Median', number_format($delta, 1)),
                'css_class' => 'wsc-bm-below',
            ];
        }

        return [
            'delta_pct' => $delta,
            'rating'    => 'average',
            'badge'     => '🟡 Industry Standard',
            'css_class' => 'wsc-bm-avg',
        ];
    }

    /**
     * Queries Gateway `/v1/benchmarks/query` (6h cache in SQLite `wsc_dashboard_reports` id `benchmarks`),
     * falling back cleanly to local baselines when offline or Hybrid Sync is disabled.
     */
    public function getBenchmarkReport(
        int $days = 7,
        bool $forceRefresh = false,
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null
    ): array {
        $vertical = $this->getCurrentVertical($salesChannelId);
        $meta = self::BENCHMARK_TAXONOMY[$vertical] ?? self::BENCHMARK_TAXONOMY['/Shopping/Apparel'];
        $site = $this->collectLocalBenchmarkMetrics($days);
        $baselines = $meta['baselines'];
        $nodesCount = (int) $meta['nodes'];
        $source = 'local_baseline';

        $gwCached = null;
        if (!$forceRefresh) {
            try {
                $rPdo = $this->dbManager->getReportsPdo();
                $stmt = $rPdo->prepare("SELECT updated_at, payload FROM wsc_dashboard_reports WHERE id = 'benchmarks'");
                $stmt->execute();
                $row = $stmt->fetch();
                if ($row && ((int) $row['updated_at'] >= (time() - 21600))) {
                    $decoded = json_decode((string) $row['payload'], true);
                    if (is_array($decoded) && ($decoded['vertical'] ?? '') === $vertical && !empty($decoded['gw_metrics'])) {
                        $gwCached = $decoded;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore cache read error
            }
        }

        if ($gwCached !== null) {
            $source = 'gateway_cached';
            $nodesCount = (int) ($gwCached['nodes_in_benchmark'] ?? $nodesCount);
            foreach (['ai_rag_share', 'engaged_time', 'ai_coverage_pct'] as $mKey) {
                if (!empty($gwCached['gw_metrics'][$mKey])) {
                    $baselines[$mKey]['p50'] = (float) ($gwCached['gw_metrics'][$mKey]['industry_median'] ?? $baselines[$mKey]['p50']);
                    $baselines[$mKey]['p75'] = (float) ($gwCached['gw_metrics'][$mKey]['industry_p75'] ?? $baselines[$mKey]['p75']);
                    $baselines[$mKey]['p90'] = (float) ($gwCached['gw_metrics'][$mKey]['industry_p90'] ?? $baselines[$mKey]['p90']);
                }
            }
        } elseif ($this->isHybridEnabled($salesChannelId)) {
            $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
            $creds = $this->getProjectCredentials(true, $salesChannelId, $baseUrl);
            if (!empty($creds['project_id']) && !empty($creds['api_secret'])) {
                $bodyJson = (string) json_encode([
                    'project_id'           => $creds['project_id'],
                    'vertical'             => $vertical,
                    'period_days'          => $days,
                    'site_ai_rag_share'    => $site['ai_rag_share'],
                    'site_engaged_time'    => $site['engaged_time'],
                    'site_ai_coverage_pct' => $site['ai_coverage_pct'],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $sig = hash_hmac('sha256', $bodyJson, $creds['api_secret']);
                $resp = $this->httpPostJson(
                    $this->getGatewayUrl($salesChannelId) . '/v1/benchmarks/query',
                    $bodyJson,
                    ['X-WSC-Sig' => $sig],
                    8
                );

                if ($resp['status'] === 200 && is_array($resp['json']) && !empty($resp['json']['metrics'])) {
                    $source = 'gateway_live';
                    $nodesCount = (int) ($resp['json']['nodes_in_benchmark'] ?? $nodesCount);
                    $gwm = $resp['json']['metrics'];
                    foreach (['ai_rag_share', 'engaged_time', 'ai_coverage_pct'] as $mKey) {
                        if (!empty($gwm[$mKey]) && is_array($gwm[$mKey])) {
                            $baselines[$mKey]['p50'] = (float) ($gwm[$mKey]['industry_median'] ?? $baselines[$mKey]['p50']);
                            $baselines[$mKey]['p75'] = (float) ($gwm[$mKey]['industry_p75'] ?? $baselines[$mKey]['p75']);
                            $baselines[$mKey]['p90'] = (float) ($gwm[$mKey]['industry_p90'] ?? $baselines[$mKey]['p90']);
                        }
                    }

                    $this->saveInternalReportCache('benchmarks', 'benchmarks', [
                        'vertical'           => $vertical,
                        'nodes_in_benchmark' => $nodesCount,
                        'gw_metrics'         => $gwm,
                    ]);
                }
            }
        }

        $metricDefs = [
            'ai_rag_share'    => ['label' => 'AI Search & RAG Share',       'unit' => '%'],
            'engaged_time'    => ['label' => 'Avg. Engaged Reading Time',   'unit' => 's'],
            'ai_coverage_pct' => ['label' => 'Domain AI Citation Coverage', 'unit' => '%'],
            'human_ratio_pct' => ['label' => 'Human Traffic Shield Ratio',  'unit' => '%'],
        ];

        $metricsOut = [];
        foreach ($metricDefs as $key => $def) {
            $rating = $this->calculateBenchmarkRating($site[$key], $baselines[$key]['p50'], $baselines[$key]['p90']);
            $metricsOut[$key] = array_merge([
                'label'           => $def['label'],
                'site_val'        => $site[$key],
                'unit'            => $def['unit'],
                'industry_median' => $baselines[$key]['p50'],
                'industry_p75'    => $baselines[$key]['p75'],
                'industry_p90'    => $baselines[$key]['p90'],
            ], $rating);
        }

        $verticalOptions = [];
        foreach (self::BENCHMARK_TAXONOMY as $vPath => $vMeta) {
            $verticalOptions[$vPath] = $vMeta['icon'] . ' ' . $vMeta['label'];
        }

        return [
            'ok'                 => true,
            'status'             => 'active',
            'source'             => $source,
            'vertical'           => $vertical,
            'vertical_label'     => $meta['label'],
            'vertical_icon'      => $meta['icon'],
            'nodes_in_benchmark' => $nodesCount,
            'metrics'            => $metricsOut,
            'taxonomy_options'   => $verticalOptions,
        ];
    }

    /**
     * Classifies store vertical via `POST /v1/benchmarks/classify` (or manual selection / local keyword centroid fallback).
     */
    public function classifyBenchmarkVertical(
        ?string $manualVertical = null,
        ?string $salesChannelId = null,
        ?string $siteBaseUrl = null
    ): array {
        if ($manualVertical !== null && isset(self::BENCHMARK_TAXONOMY[$manualVertical])) {
            $this->systemConfig->set(self::CONFIG_PREFIX . 'industryVertical', $manualVertical, $salesChannelId);
            $this->clearInternalReportCache('benchmarks');
            return [
                'ok'         => true,
                'method'     => 'manual',
                'vertical'   => $manualVertical,
                'benchmarks' => $this->getBenchmarkReport(7, true, $salesChannelId, $siteBaseUrl),
            ];
        }

        $storeMeta = $this->collectStoreMetadata($salesChannelId);
        $chosenVertical = '';
        $method = 'centroid_nlp';
        $confidence = 78;

        if ($this->isHybridEnabled($salesChannelId)) {
            $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
            $creds = $this->getProjectCredentials(true, $salesChannelId, $baseUrl);
            if (!empty($creds['project_id']) && !empty($creds['api_secret'])) {
                $bodyJson = (string) json_encode([
                    'project_id'       => $creds['project_id'],
                    'title'            => $storeMeta['name'],
                    'meta_description' => $storeMeta['description'],
                    'keywords'         => implode(', ', $storeMeta['keywords']),
                    'sample_text'      => 'shopware6 ecommerce store products ' . implode(' ', array_slice($storeMeta['products'], 0, 10)),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $sig = hash_hmac('sha256', $bodyJson, $creds['api_secret']);
                $resp = $this->httpPostJson(
                    $this->getGatewayUrl($salesChannelId) . '/v1/benchmarks/classify',
                    $bodyJson,
                    ['X-WSC-Sig' => $sig],
                    8
                );

                if ($resp['status'] === 200 && is_array($resp['json'])) {
                    $gwVert = (string) ($resp['json']['vertical'] ?? '');
                    if ($gwVert !== '' && isset(self::BENCHMARK_TAXONOMY[$gwVert])) {
                        $chosenVertical = $gwVert;
                        $method = 'gateway_nlp';
                        $rawConf = (float) ($resp['json']['confidence'] ?? 0.9);
                        $confidence = $rawConf <= 1.0 ? (int) round($rawConf * 100) : (int) round($rawConf);
                    }
                }
            }
        }

        if ($chosenVertical === '') {
            $corpus = strtolower($storeMeta['name'] . ' ' . $storeMeta['description'] . ' ' . implode(' ', $storeMeta['keywords']) . ' ' . implode(' ', $storeMeta['products']));
            $bestMatch = '/Shopping/Apparel';
            $maxScore = 0;
            foreach (self::BENCHMARK_TAXONOMY as $path => $meta) {
                $score = str_starts_with($path, '/Shopping') ? 3 : 0;
                foreach ($meta['keywords'] as $kw) {
                    if (stripos($corpus, $kw) !== false) {
                        $score += 2;
                    }
                }
                if ($score > $maxScore) {
                    $maxScore = $score;
                    $bestMatch = $path;
                }
            }
            $chosenVertical = $bestMatch;
            $confidence = min(98, max(70, 65 + ($maxScore * 4)));
        }

        $this->systemConfig->set(self::CONFIG_PREFIX . 'industryVertical', $chosenVertical, $salesChannelId);
        $this->clearInternalReportCache('benchmarks');

        return [
            'ok'         => true,
            'method'     => $method,
            'confidence' => $confidence,
            'vertical'   => $chosenVertical,
            'benchmarks' => $this->getBenchmarkReport(7, true, $salesChannelId, $siteBaseUrl),
        ];
    }

    // =========================================================================
    // PAKET CMS-3.3: Public `/llms.txt` AI Manifest
    // =========================================================================

    public function isLlmsTxtEnabled(?string $salesChannelId = null): bool
    {
        $val = $this->systemConfig->get(self::CONFIG_PREFIX . 'llmsTxtEnabled', $salesChannelId);
        return $val === null ? true : (bool) $val;
    }

    public function generateLlmsTxt(?string $siteBaseUrl = null, ?string $salesChannelId = null): string
    {
        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
        $meta = $this->collectStoreMetadata($salesChannelId);

        $lines = [];
        $lines[] = '# ' . ($meta['name'] !== '' ? $meta['name'] : 'Shopware 6 Storefront');
        $lines[] = '';
        $lines[] = '> ' . ($meta['description'] !== '' ? $meta['description'] : 'Official e-commerce catalog and customer information manifest.');
        $lines[] = '';
        $lines[] = '- Canonical Store URL: ' . $baseUrl . '/';
        $lines[] = '- XML Sitemap: ' . $baseUrl . '/sitemap.xml';
        $lines[] = '- Platform: Shopware 6 (WSC Search & Health v' . self::VERSION . ')';
        $lines[] = '';

        if (!empty($meta['categories'])) {
            $lines[] = '## Key Store Categories';
            $lines[] = '';
            foreach ($meta['categories'] as $cat) {
                $lines[] = '- [' . $cat['name'] . '](' . $baseUrl . '/' . ltrim($cat['url'], '/') . ')';
            }
            $lines[] = '';
        }

        if (!empty($meta['product_items'])) {
            $lines[] = '## Featured Active Products';
            $lines[] = '';
            foreach ($meta['product_items'] as $prod) {
                $desc = $prod['description'] !== '' ? (': ' . $prod['description']) : '';
                $lines[] = '- [' . $prod['name'] . '](' . $baseUrl . '/' . ltrim($prod['url'], '/') . ')' . $desc;
            }
            $lines[] = '';
        }

        if (!empty($meta['cms_pages'])) {
            $lines[] = '## Store Information & Policies';
            $lines[] = '';
            foreach ($meta['cms_pages'] as $page) {
                $lines[] = '- ' . $page['name'];
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private function collectStoreMetadata(?string $salesChannelId = null): array
    {
        $name = $this->systemConfig->getString('core.basicInformation.shopName', $salesChannelId) ?: 'Shopware 6 Store';
        $desc = $this->systemConfig->getString('core.basicInformation.metaDescription', $salesChannelId) ?: '';
        $keywords = [];
        $categories = [];
        $productNames = [];
        $productItems = [];
        $cmsPages = [];

        if ($this->connection !== null) {
            try {
                $catRows = $this->connection->fetchAllAssociative("
                    SELECT LOWER(HEX(c.id)) AS hex_id, ct.name, su.seo_path_info
                    FROM category c
                    INNER JOIN category_translation ct ON ct.category_id = c.id AND ct.category_version_id = c.version_id
                    LEFT JOIN seo_url su ON su.foreign_key = c.id AND su.route_name = 'frontend.navigation.page' AND su.is_canonical = 1 AND su.is_deleted = 0
                    WHERE c.active = 1 AND ct.name IS NOT NULL AND ct.name != ''
                    GROUP BY c.id
                    LIMIT 15
                ");
                foreach ($catRows as $cr) {
                    $cName = trim((string) $cr['name']);
                    if ($cName === '' || strtolower($cName) === 'catalogue #1') {
                        continue;
                    }
                    $keywords[] = strtolower($cName);
                    $categories[] = [
                        'name' => $cName,
                        'url'  => !empty($cr['seo_path_info']) ? (string) $cr['seo_path_info'] : ('navigation/' . $cr['hex_id']),
                    ];
                }
            } catch (\Throwable $e) {
                // Continue safely
            }

            try {
                $prodRows = $this->connection->fetchAllAssociative("
                    SELECT LOWER(HEX(p.id)) AS hex_id, pt.name, pt.meta_description, pt.description, su.seo_path_info
                    FROM product p
                    INNER JOIN product_translation pt ON pt.product_id = p.id AND pt.product_version_id = p.version_id
                    LEFT JOIN seo_url su ON su.foreign_key = p.id AND su.route_name = 'frontend.detail.page' AND su.is_canonical = 1 AND su.is_deleted = 0
                    WHERE p.active = 1 AND p.parent_id IS NULL AND pt.name IS NOT NULL AND pt.name != ''
                    GROUP BY p.id
                    LIMIT 20
                ");
                foreach ($prodRows as $pr) {
                    $pName = trim((string) $pr['name']);
                    if ($pName === '') {
                        continue;
                    }
                    $productNames[] = $pName;
                    $rawDesc = trim(strip_tags((string) ($pr['meta_description'] ?: $pr['description'] ?: '')));
                    $rawDesc = preg_replace('/\s+/', ' ', $rawDesc) ?: '';
                    if (strlen($rawDesc) > 120) {
                        $rawDesc = substr($rawDesc, 0, 117) . '...';
                    }
                    $productItems[] = [
                        'name'        => $pName,
                        'description' => $rawDesc,
                        'url'         => !empty($pr['seo_path_info']) ? (string) $pr['seo_path_info'] : ('detail/' . $pr['hex_id']),
                    ];
                }
            } catch (\Throwable $e) {
                // Continue safely
            }

            try {
                $cmsRows = $this->connection->fetchAllAssociative("
                    SELECT DISTINCT cpt.name
                    FROM cms_page cp
                    INNER JOIN cms_page_translation cpt ON cpt.cms_page_id = cp.id AND cpt.cms_page_version_id = cp.version_id
                    WHERE cpt.name IS NOT NULL AND cpt.name != ''
                    LIMIT 10
                ");
                foreach ($cmsRows as $cm) {
                    $cmsPages[] = ['name' => trim((string) $cm['name'])];
                }
            } catch (\Throwable $e) {
                // Continue safely
            }
        }

        return [
            'name'          => $name,
            'description'   => $desc,
            'keywords'      => $keywords,
            'categories'    => $categories,
            'products'      => $productNames,
            'product_items' => $productItems,
            'cms_pages'     => $cmsPages,
        ];
    }

    // =========================================================================
    // PAKET CMS-3.4: Opt-In `IndexNow` Instant Push (`api.indexnow.org`)
    // =========================================================================

    public function isIndexNowEnabled(?string $salesChannelId = null): bool
    {
        return $this->systemConfig->getBool(self::CONFIG_PREFIX . 'indexnowEnabled', $salesChannelId);
    }

    public function getIndexNowKey(?string $salesChannelId = null): string
    {
        $key = strtolower(trim($this->systemConfig->getString(self::CONFIG_PREFIX . 'indexnowKey', $salesChannelId)));
        if (strlen($key) !== 32 || !ctype_xdigit($key)) {
            $key = bin2hex(random_bytes(16)); // 32-char hex
            $this->systemConfig->set(self::CONFIG_PREFIX . 'indexnowKey', $key, $salesChannelId);
        }
        return $key;
    }

    public function getIndexNowLogs(): array
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("SELECT payload FROM wsc_dashboard_reports WHERE id = 'indexnow_logs'");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row) {
                $decoded = json_decode((string) $row['payload'], true);
                if (is_array($decoded)) {
                    return array_slice($decoded, 0, 20);
                }
            }
        } catch (\Throwable $e) {
            // Return empty
        }
        return [];
    }

    /**
     * Resolves canonical product URLs from Shopware product hex IDs (for automatic IndexNow push).
     */
    public function resolveProductUrlsByIds(array $productIds, ?string $salesChannelId = null): array
    {
        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId);
        $urls = [];

        foreach ($productIds as $id) {
            if (!is_string($id) || !ctype_xdigit($id)) {
                continue;
            }
            $seoPath = null;
            if ($this->connection !== null) {
                try {
                    $active = $this->connection->fetchOne(
                        'SELECT active FROM product WHERE id = UNHEX(:id) LIMIT 1',
                        ['id' => $id]
                    );
                    if ($active !== false && (int) $active === 0) {
                        continue;
                    }
                    $seoPath = $this->connection->fetchOne(
                        "SELECT seo_path_info FROM seo_url WHERE foreign_key = UNHEX(:id) AND route_name = 'frontend.detail.page' AND is_canonical = 1 AND is_deleted = 0 LIMIT 1",
                        ['id' => $id]
                    );
                } catch (\Throwable $e) {
                    // Fallback to /detail/{id}
                }
            }

            $rel = (is_string($seoPath) && $seoPath !== '') ? ltrim($seoPath, '/') : ('detail/' . strtolower($id));
            $urls[] = $baseUrl . '/' . $rel;
        }

        return array_values(array_unique($urls));
    }

    /**
     * Submits URLs to IndexNow (`timeout = 3s` for auto_product_save, `10s` for manual) and stores last 20 logs.
     */
    public function submitIndexNowUrls(
        array $rawUrls,
        string $source = 'manual',
        int $timeout = 10,
        ?string $siteBaseUrl = null,
        ?string $salesChannelId = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($salesChannelId, $siteBaseUrl);
        $siteHost = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?: ''));

        $validUrls = [];
        foreach ($rawUrls as $u) {
            $u = trim((string) $u);
            if ($u === '') {
                continue;
            }
            if (str_starts_with($u, '/')) {
                $u = $baseUrl . $u;
            }
            if (!filter_var($u, FILTER_VALIDATE_URL)) {
                continue;
            }
            $uHost = strtolower((string) (parse_url($u, PHP_URL_HOST) ?: ''));
            if ($siteHost !== '' && $uHost !== $siteHost) {
                continue;
            }
            $validUrls[] = $u;
        }

        $validUrls = array_values(array_unique($validUrls));
        if (empty($validUrls)) {
            return [
                'ok'      => false,
                'success' => false,
                'message' => 'Please provide at least one valid URL matching host ' . $siteHost . '.',
                'logs'    => $this->getIndexNowLogs(),
            ];
        }

        $key = $this->getIndexNowKey($salesChannelId);
        $payload = (string) json_encode([
            'host'        => $siteHost,
            'key'         => $key,
            'keyLocation' => $baseUrl . '/' . $key . '.txt',
            'urlList'     => $validUrls,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $resp = $this->httpPostJson(self::INDEXNOW_ENDPOINT, $payload, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], max(1, $timeout));

        $ok = in_array($resp['status'], [200, 202], true);
        $entry = [
            'timestamp'   => gmdate('Y-m-d H:i:s') . ' UTC',
            'source'      => $source,
            'urls'        => $validUrls,
            'count'       => count($validUrls),
            'status_code' => $resp['status'],
            'success'     => $ok,
        ];

        $logs = $this->getIndexNowLogs();
        array_unshift($logs, $entry);
        $logs = array_slice($logs, 0, 20);
        $this->saveInternalReportCache('indexnow_logs', 'indexnow', $logs);

        return [
            'ok'          => $ok,
            'success'     => $ok,
            'status_code' => $resp['status'],
            'submitted'   => count($validUrls),
            'message'     => $ok
                ? sprintf('Submitted %d URL(s) to IndexNow (HTTP %d).', count($validUrls), $resp['status'])
                : sprintf('IndexNow submission returned HTTP %d.', $resp['status']),
            'logs'        => $logs,
        ];
    }

    // =========================================================================
    // Internal Cache, HTTP Transport & Cleanup Helpers
    // =========================================================================

    private function saveInternalReportCache(string $id, string $type, array $payload): void
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("
                INSERT INTO wsc_dashboard_reports (id, report_type, period_type, updated_at, payload)
                VALUES (:id, :type, 'cache', :ts, :payload)
                ON CONFLICT(id) DO UPDATE SET updated_at = excluded.updated_at, payload = excluded.payload
            ");
            $stmt->execute([
                ':id'      => $id,
                ':type'    => $type,
                ':ts'      => time(),
                ':payload' => (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking cache write
        }
    }

    private function clearInternalReportCache(string $id): void
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $rPdo->prepare("DELETE FROM wsc_dashboard_reports WHERE id = :id")->execute([':id' => $id]);
        } catch (\Throwable $e) {
            // Non-blocking
        }
    }

    private function httpPostJson(string $url, string $jsonBody, array $extraHeaders = [], int $timeout = 10): array
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ], $extraHeaders);

        if ($this->httpClient !== null) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => $headers,
                    'body'    => $jsonBody,
                    'timeout' => $timeout,
                ]);
                $status = $response->getStatusCode();
                $raw = $response->getContent(false);
                return [
                    'status' => $status,
                    'body'   => $raw,
                    'json'   => json_decode($raw, true),
                ];
            } catch (\Throwable $e) {
                return ['status' => 0, 'body' => '', 'json' => null, 'error' => $e->getMessage()];
            }
        }

        if (class_exists(\GuzzleHttp\Client::class)) {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => $timeout, 'http_errors' => false]);
                $res = $client->post($url, ['headers' => $headers, 'body' => $jsonBody]);
                $raw = (string) $res->getBody();
                return [
                    'status' => $res->getStatusCode(),
                    'body'   => $raw,
                    'json'   => json_decode($raw, true),
                ];
            } catch (\Throwable $e) {
                return ['status' => 0, 'body' => '', 'json' => null, 'error' => $e->getMessage()];
            }
        }

        return ['status' => 0, 'body' => '', 'json' => null, 'error' => 'no_http_client'];
    }

    private function httpGetJson(string $url, int $timeout = 6): array
    {
        $headers = ['Accept' => 'application/json', 'User-Agent' => 'WSC-Shopware6/' . self::VERSION];

        if ($this->httpClient !== null) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => $headers,
                    'timeout' => $timeout,
                ]);
                $status = $response->getStatusCode();
                $raw = $response->getContent(false);
                return ['status' => $status, 'body' => $raw, 'json' => json_decode($raw, true)];
            } catch (\Throwable $e) {
                return ['status' => 0, 'body' => '', 'json' => null, 'error' => $e->getMessage()];
            }
        }

        if (class_exists(\GuzzleHttp\Client::class)) {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => $timeout, 'http_errors' => false]);
                $res = $client->get($url, ['headers' => $headers]);
                $raw = (string) $res->getBody();
                return ['status' => $res->getStatusCode(), 'body' => $raw, 'json' => json_decode($raw, true)];
            } catch (\Throwable $e) {
                return ['status' => 0, 'body' => '', 'json' => null, 'error' => $e->getMessage()];
            }
        }

        return ['status' => 0, 'body' => '', 'json' => null, 'error' => 'no_http_client'];
    }

    /**
     * Daily queue & bot-hits retention cleanup.
     */
    public function cleanupOldData(?string $salesChannelId = null): void
    {
        $retention = max(1, $this->systemConfig->getInt(self::CONFIG_PREFIX . 'retentionDays', $salesChannelId) ?: 30);
        $threshold = time() - ($retention * 86400);

        try {
            $qPdo = $this->dbManager->getQueuePdo();
            $qPdo->prepare("DELETE FROM wsc_events_queue WHERE synced = 1 AND created_at < :t")->execute([':t' => $threshold]);
            $qPdo->exec('PRAGMA optimize;');

            $rPdo = $this->dbManager->getReportsPdo();
            $rPdo->prepare("DELETE FROM wsc_bot_hits WHERE hit_at < :t")->execute([':t' => time() - (60 * 86400)]);
            $rPdo->exec('PRAGMA optimize;');
        } catch (Exception $e) {
            $this->logger->error('WSC Cleanup Error: ' . $e->getMessage());
        }
    }
}
