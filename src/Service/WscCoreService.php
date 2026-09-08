<?php

declare(strict_types=1);

namespace Wsc\Analytics\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Core business logic, encryption, and gateway sync for Shopware 6.
 */
class WscCoreService
{
    public const CONFIG_PREFIX = 'WscAnalytics.config.';
    public const GATEWAY_DEFAULT = 'https://api.worldseocouncil.com';

    private SystemConfigService $systemConfig;
    private WscDatabaseManager $dbManager;
    private LoggerInterface $logger;
    private ?HttpClientInterface $httpClient;

    public function __construct(
        SystemConfigService $systemConfig,
        WscDatabaseManager $dbManager,
        LoggerInterface $logger,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->systemConfig = $systemConfig;
        $this->dbManager = $dbManager;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    /**
     * Get or generate local AES-256 key & Project ID.
     */
    public function getProjectCredentials(?string $salesChannelId = null): array
    {
        $projectId = $this->systemConfig->getString(self::CONFIG_PREFIX . 'projectId', $salesChannelId);
        $localKey  = $this->systemConfig->getString(self::CONFIG_PREFIX . 'localKey', $salesChannelId);

        if (empty($projectId) || empty($localKey)) {
            $projectId = 'wsc_sw6_' . bin2hex(random_bytes(8));
            $localKey  = bin2hex(random_bytes(32)); // 256-bit AES key

            $this->systemConfig->set(self::CONFIG_PREFIX . 'projectId', $projectId, $salesChannelId);
            $this->systemConfig->set(self::CONFIG_PREFIX . 'localKey', $localKey, $salesChannelId);

            $this->registerWithGateway($projectId, $localKey, $salesChannelId);
        }

        return [
            'project_id' => $projectId,
            'local_key'  => $localKey,
        ];
    }

    /**
     * Register project credentials with Gateway.
     */
    public function registerWithGateway(string $projectId, string $localKey, ?string $salesChannelId = null): bool
    {
        $gatewayUrl = $this->systemConfig->getString(self::CONFIG_PREFIX . 'gatewayUrl', $salesChannelId) ?: self::GATEWAY_DEFAULT;
        $url = rtrim($gatewayUrl, '/') . '/v1/register';

        $payload = json_encode([
            'project_id' => $projectId,
            'local_key'  => $localKey,
            'cms'        => 'shopware6',
            'version'    => '1.5.0',
        ]);

        try {
            if ($this->httpClient) {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => $payload,
                    'timeout' => 8,
                ]);
                return $response->getStatusCode() === 200 || $response->getStatusCode() === 201;
            }
        } catch (Exception $e) {
            $this->logger->warning('WSC Gateway registration deferred: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Push event to local SQLite queue.
     */
    public function trackEvent(string $type, string $path = '/', ?string $referrer = '', array $meta = []): bool
    {
        try {
            $pdo = $this->dbManager->getQueuePdo();
            $stmt = $pdo->prepare("
                INSERT INTO wsc_events_queue (created_at, event_type, url_path, referrer, payload_json, synced)
                VALUES (:created_at, :event_type, :url_path, :referrer, :payload_json, 0)
            ");

            $stmt->execute([
                ':created_at'   => time(),
                ':event_type'   => $type,
                ':url_path'     => $path ?: '/',
                ':referrer'     => $referrer ?: '',
                ':payload_json' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('WSC Shopware Queue Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process micro-batch export with AES-256-CBC and HMAC-SHA256 signature.
     */
    public function processBatchExport(int $batchSize = 500, ?string $salesChannelId = null): int
    {
        if (!$this->systemConfig->getBool(self::CONFIG_PREFIX . 'hybridEnabled', $salesChannelId)) {
            return 0;
        }

        $creds = $this->getProjectCredentials($salesChannelId);
        $pdo = $this->dbManager->getQueuePdo();

        $stmt = $pdo->prepare("SELECT * FROM wsc_events_queue WHERE synced = 0 ORDER BY id ASC LIMIT :limit");
        $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return 0;
        }

        $ids = [];
        $events = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            $events[] = [
                't' => (int) $row['created_at'],
                'e' => $row['event_type'],
                'p' => $row['url_path'],
                'r' => $row['referrer'],
                'm' => json_decode($row['payload_json'], true) ?: [],
            ];
        }

        $payloadJson = json_encode(['events' => $events], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // AES-256-CBC Encryption
        $keyBinary = hex2bin($creds['local_key']);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($payloadJson, 'AES-256-CBC', $keyBinary, OPENSSL_RAW_DATA, $iv);
        $encryptedBody = base64_encode($iv . $ciphertext);

        // HMAC-SHA256 Signature
        $signature = hash_hmac('sha256', $encryptedBody, $creds['local_key']);

        $gatewayUrl = $this->systemConfig->getString(self::CONFIG_PREFIX . 'gatewayUrl', $salesChannelId) ?: self::GATEWAY_DEFAULT;
        $url = rtrim($gatewayUrl, '/') . '/v1/ingest';

        try {
            if ($this->httpClient) {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'X-WSC-Project' => $creds['project_id'],
                        'X-WSC-Sig'     => $signature,
                    ],
                    'body' => $encryptedBody,
                    'timeout' => 10,
                ]);

                if ($response->getStatusCode() === 200 || $response->getStatusCode() === 202) {
                    $inClause = implode(',', $ids);
                    $pdo->exec("UPDATE wsc_events_queue SET synced = 1 WHERE id IN ($inClause)");
                    return count($ids);
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('WSC Ingest Sync Error: ' . $e->getMessage());
        }

        return 0;
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

            // 1. Process array-based insights list if supplied by Worker dispatcher
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

            // 2. Canonical Card Mappings
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
     * Retrieve canonical 7 Cards for Dashboard View.
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
        ];

        try {
            $pdo = $this->dbManager->getReportsPdo();
            $stmt = $pdo->query("SELECT id, payload FROM wsc_dashboard_reports");
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $id = $row['id'];
                $decoded = json_decode($row['payload'], true);

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

        // Backward compatibility aliases
        $cards['card1'] = $cards['card1_traffic'];
        $cards['card2'] = $cards['card2_insights'];
        $cards['card3'] = $cards['card3_bots'];
        $cards['sentinel'] = $cards['domain_health'];

        return $cards;
    }

    /**
     * Trigger asynchronous SpeedSentinel lab audit via Gateway.
     */
    public function triggerSpeedSentinelAudit(string $url, string $strategy = 'mobile', ?string $salesChannelId = null): array
    {
        $creds = $this->getProjectCredentials($salesChannelId);
        $gatewayUrl = $this->systemConfig->getString(self::CONFIG_PREFIX . 'gatewayUrl', $salesChannelId) ?: self::GATEWAY_DEFAULT;

        $payload = json_encode([
            'project_id' => $creds['project_id'],
            'url'        => $url,
            'strategy'   => in_array($strategy, ['mobile', 'desktop'], true) ? $strategy : 'mobile',
        ]);

        $signature = hash_hmac('sha256', $payload, $creds['local_key']);
        $endpoint = rtrim($gatewayUrl, '/') . '/v1/pagespeed/audit';

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15]);
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-WSC-Sig'    => $signature,
                ],
                'body' => $payload
            ]);

            return json_decode((string) $response->getBody(), true) ?: ['ok' => true];
        } catch (\Throwable $e) {
            $this->logger->error('WSC SpeedSentinel Audit Trigger Error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Trigger asynchronous Sentinel Domain & Security scan via Gateway.
     */
    public function triggerSentinelScan(string $domain, ?string $salesChannelId = null): array
    {
        $creds = $this->getProjectCredentials($salesChannelId);
        $gatewayUrl = $this->systemConfig->getString(self::CONFIG_PREFIX . 'gatewayUrl', $salesChannelId) ?: self::GATEWAY_DEFAULT;

        $payload = json_encode([
            'project_id' => $creds['project_id'],
            'domain'     => $domain,
        ]);

        $signature = hash_hmac('sha256', $payload, $creds['local_key']);
        $endpoint = rtrim($gatewayUrl, '/') . '/v1/sentinel/scan';

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15]);
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-WSC-Sig'    => $signature,
                ],
                'body' => $payload
            ]);

            return json_decode((string) $response->getBody(), true) ?: ['ok' => true];
        } catch (\Throwable $e) {
            $this->logger->error('WSC Sentinel Scan Trigger Error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Daily queue retention cleanup.
     */
    public function cleanupOldData(?string $salesChannelId = null): void
    {
        $retention = $this->systemConfig->getInt(self::CONFIG_PREFIX . 'retentionDays', $salesChannelId) ?: 30;
        $threshold = time() - ($retention * 86400);

        try {
            $pdo = $this->dbManager->getQueuePdo();
            $pdo->exec("DELETE FROM wsc_events_queue WHERE synced = 1 AND created_at < $threshold");

            $pdo->exec('PRAGMA optimize;');
            $this->dbManager->getReportsPdo()->exec('PRAGMA optimize;');
        } catch (Exception $e) {
            $this->logger->error('WSC Cleanup Error: ' . $e->getMessage());
        }
    }
}
