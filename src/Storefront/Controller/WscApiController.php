<?php

declare(strict_types=1);

namespace Wsc\Analytics\Storefront\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;
use Wsc\Analytics\Service\WscCoreService;
use Psr\Log\LoggerInterface;

/**
 * REST Endpoint Controller for Client Telemetry & Gateway Webhooks in Shopware 6.
 *
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class WscApiController extends StorefrontController
{
    private WscCoreService $coreService;
    private LoggerInterface $logger;

    public function __construct(WscCoreService $coreService, LoggerInterface $logger)
    {
        $this->coreService = $coreService;
        $this->logger = $logger;
    }

    /**
     * @Route("/wsc/v1/track", name="frontend.wsc.track", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function track(Request $request): JsonResponse
    {
        $content = $request->getContent();
        if (empty($content)) {
            return new JsonResponse(['ok' => false, 'error' => 'empty_payload'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['events']) || !is_array($data['events'])) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_format'], Response::HTTP_BAD_REQUEST);
        }

        $ip = $request->getClientIp() ?: '';
        $ua = (string) $request->headers->get('user-agent', '');
        $count = 0;

        foreach ($data['events'] as $item) {
            if (empty($item['e'])) {
                continue;
            }

            $eventType = (string) $item['e'];
            $urlPath   = (string) ($item['p'] ?? '/');
            $referrer  = (string) ($item['r'] ?? '');
            $meta      = (isset($item['m']) && is_array($item['m'])) ? $item['m'] : [];

            $meta['ip'] = $ip;
            $meta['user_agent'] = $ua;

            if ($this->coreService->trackEvent($eventType, $urlPath, $referrer, $meta)) {
                $count++;
            }
        }

        return new JsonResponse(['ok' => true, 'queued' => $count]);
    }

    /**
     * @Route("/wsc/v1/webhook", name="frontend.wsc.webhook", methods={"POST"})
     */
    public function webhook(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $signature = (string) $request->headers->get('X-WSC-Sig', '');

        $creds = $this->coreService->getProjectCredentials();
        $expectedSig = hash_hmac('sha256', $content, $creds['local_key']);

        if (!hash_equals($expectedSig, $signature)) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_signature'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $saved = $this->coreService->saveWebhookReport($data);
        return new JsonResponse(['ok' => $saved]);
    }

    /**
     * @Route("/wsc/v1/ai-coverage", name="frontend.wsc.ai_coverage", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function aiCoverage(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true);
        $queryUrl = trim((string) ($data['query'] ?? ''));
        $timeframe = trim((string) ($data['timeframe'] ?? '30d'));

        if (empty($queryUrl)) {
            return new JsonResponse(['ok' => false, 'error' => 'query_required'], Response::HTTP_BAD_REQUEST);
        }

        $creds = $this->coreService->getProjectCredentials();
        if (empty($creds['project_id']) || empty($creds['api_secret'])) {
            return new JsonResponse(['ok' => false, 'error' => 'not_configured'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_encode([
            'project_id' => $creds['project_id'],
            'query'      => $queryUrl,
            'timeframe'  => in_array($timeframe, ['7d', '30d'], true) ? $timeframe : '30d'
        ]);

        $signature = hash_hmac('sha256', $payload, $creds['api_secret']);

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->post('https://api.worldsearchcouncil.org/v1/ai-coverage/query', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-WSC-Sig'    => $signature,
                ],
                'body' => $payload
            ]);

            $result = json_decode((string) $response->getBody(), true);
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->logger->error('WSC AI Coverage query failed: ' . $e->getMessage());
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * @Route("/wsc/v1/refresh", name="frontend.wsc.refresh", methods={"POST"})
     */
    public function refresh(): JsonResponse
    {
        $synced = $this->coreService->processBatchExport(500);
        return new JsonResponse([
            'ok' => true,
            'synced_events' => $synced,
            'last_sync' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @Route("/wsc/v1/cards", name="frontend.wsc.cards", methods={"GET"})
     */
    public function cards(): JsonResponse
    {
        $cards = $this->coreService->getDashboardCards();
        return new JsonResponse([
            'ok'    => true,
            'cards' => $cards,
        ]);
    }

    /**
     * @Route("/wsc/v1/pagespeed/audit", name="frontend.wsc.pagespeed_audit", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function pagespeedAudit(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true) ?: [];
        $url = trim((string) ($data['url'] ?? $request->getSchemeAndHttpHost()));
        $strategy = trim((string) ($data['strategy'] ?? 'mobile'));

        $result = $this->coreService->triggerSpeedSentinelAudit($url, $strategy);
        return new JsonResponse($result);
    }

    /**
     * @Route("/wsc/v1/sentinel/scan", name="frontend.wsc.sentinel_scan", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function sentinelScan(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true) ?: [];
        $domain = trim((string) ($data['domain'] ?? $request->getHost()));

        $result = $this->coreService->triggerSentinelScan($domain);
        return new JsonResponse($result);
    }
}
