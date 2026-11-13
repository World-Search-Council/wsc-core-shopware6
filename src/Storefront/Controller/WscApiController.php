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
 * REST Endpoint Controller for Telemetry, Webhooks, Member Bridge, Benchmarks,
 * AI Coverage Inspector, /llms.txt & Opt-In IndexNow in Shopware 6 (v2.2.3).
 *
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
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
     * @Route("/wsc/v1/track", name="frontend.wsc.track", methods={"POST"}, defaults={"XmlHttpRequest"=true, "csrf_protected"=false})
     */
    #[Route(path: '/wsc/v1/track', name: 'frontend.wsc.track', defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false], methods: ['POST'])]
    public function track(Request $request): JsonResponse
    {
        $content = $request->getContent();
        if ($content === '') {
            return new JsonResponse(['ok' => false, 'error' => 'empty_payload'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['events']) || !is_array($data['events'])) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_format'], Response::HTTP_BAD_REQUEST);
        }

        $rawIp = (string) ($request->getClientIp() ?: '');
        $ua = (string) $request->headers->get('user-agent', '');
        $sessionHash = $this->coreService->computeSessionHash($rawIp, $ua);
        $count = 0;

        foreach ($data['events'] as $item) {
            if (empty($item['e'])) {
                continue;
            }

            $eventType = (string) $item['e'];
            $urlPath   = (string) ($item['p'] ?? '/');
            $referrer  = (string) ($item['r'] ?? '');
            $meta      = (isset($item['m']) && is_array($item['m'])) ? $item['m'] : [];

            // Zero-PII: store salted session hash only, never raw IP
            unset($meta['ip']);
            $meta['session_hash'] = $sessionHash;
            $meta['user_agent'] = $ua;

            if ($this->coreService->trackEvent($eventType, $urlPath, $referrer, $meta)) {
                $count++;
            }
        }

        return new JsonResponse(['ok' => true, 'queued' => $count]);
    }

    /**
     * @Route("/wsc/v1/webhook", name="frontend.wsc.webhook", methods={"POST"}, defaults={"csrf_protected"=false})
     */
    #[Route(path: '/wsc/v1/webhook', name: 'frontend.wsc.webhook', defaults: ['csrf_protected' => false], methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $signature = (string) $request->headers->get('X-WSC-Sig', '');

        if (!$this->coreService->verifyWebhookSignature($content, $signature)) {
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
     * @Route("/wsc/v1/register", name="frontend.wsc.register", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/register', name: 'frontend.wsc.register', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function registerGateway(Request $request): JsonResponse
    {
        $res = $this->coreService->registerWithGateway(null, $request->getSchemeAndHttpHost(), true);
        return new JsonResponse($res, !empty($res['ok']) ? Response::HTTP_OK : Response::HTTP_BAD_GATEWAY);
    }

    /**
     * @Route("/wsc/v1/ai-coverage", name="frontend.wsc.ai_coverage", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/ai-coverage', name: 'frontend.wsc.ai_coverage', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function aiCoverage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $queryUrl = trim((string) ($data['query'] ?? ''));
        $timeframe = trim((string) ($data['timeframe'] ?? '30d'));

        if ($queryUrl === '') {
            return new JsonResponse(['ok' => false, 'error' => 'query_required', 'message' => 'Please enter a URL or path to inspect.'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->coreService->queryAiCoverage($queryUrl, $timeframe, null, $request->getSchemeAndHttpHost());
        return new JsonResponse($result);
    }

    /**
     * @Route("/wsc/v1/benchmarks/query", name="frontend.wsc.benchmarks_query", methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/benchmarks/query', name: 'frontend.wsc.benchmarks_query', defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
    public function benchmarksQuery(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $days = (int) ($data['days'] ?? $request->query->get('days', 7));
        $force = !empty($data['force']);

        $report = $this->coreService->getBenchmarkReport(max(1, $days), $force, null, $request->getSchemeAndHttpHost());
        return new JsonResponse($report);
    }

    /**
     * @Route("/wsc/v1/benchmarks/classify", name="frontend.wsc.benchmarks_classify", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/benchmarks/classify', name: 'frontend.wsc.benchmarks_classify', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function benchmarksClassify(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $manualVertical = !empty($data['vertical']) ? trim((string) $data['vertical']) : null;

        $res = $this->coreService->classifyBenchmarkVertical($manualVertical, null, $request->getSchemeAndHttpHost());
        return new JsonResponse($res);
    }

    /**
     * @Route("/wsc/v1/license/verify", name="frontend.wsc.license_verify", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/license/verify', name: 'frontend.wsc.license_verify', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function licenseVerify(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $key = trim((string) ($data['license_key'] ?? ''));

        $res = $this->coreService->verifyLicense($key, null, $request->getSchemeAndHttpHost());
        return new JsonResponse($res);
    }

    /**
     * @Route("/wsc/v1/license/disconnect", name="frontend.wsc.license_disconnect", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/license/disconnect', name: 'frontend.wsc.license_disconnect', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function licenseDisconnect(): JsonResponse
    {
        return new JsonResponse($this->coreService->disconnectLicense());
    }

    /**
     * @Route("/wsc/v1/cobranding/save", name="frontend.wsc.cobranding_save", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/cobranding/save', name: 'frontend.wsc.cobranding_save', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function coBrandingSave(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $res = $this->coreService->saveCoBranding($data, null, $request->getSchemeAndHttpHost());
        return new JsonResponse($res);
    }

    /**
     * @Route("/wsc/v1/indexnow/submit", name="frontend.wsc.indexnow_submit", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/indexnow/submit', name: 'frontend.wsc.indexnow_submit', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function indexnowSubmit(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $rawUrls = $data['urls'] ?? [];
        if (is_string($rawUrls)) {
            $rawUrls = preg_split('/[\r\n]+/', $rawUrls, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $res = $this->coreService->submitIndexNowUrls(
            is_array($rawUrls) ? $rawUrls : [],
            'manual',
            10,
            $request->getSchemeAndHttpHost()
        );
        return new JsonResponse($res);
    }

    /**
     * @Route("/llms.txt", name="frontend.wsc.llms_txt", methods={"GET"})
     */
    #[Route(path: '/llms.txt', name: 'frontend.wsc.llms_txt', methods: ['GET'])]
    public function llmsTxt(Request $request): Response
    {
        if (!$this->coreService->isLlmsTxtEnabled()) {
            return new Response('Not Found', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $body = $this->coreService->generateLlmsTxt($request->getSchemeAndHttpHost());
        return new Response($body, Response::HTTP_OK, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'X-Robots-Tag'  => 'noindex, follow',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @Route("/wsc/v1/refresh", name="frontend.wsc.refresh", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/refresh', name: 'frontend.wsc.refresh', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $synced = $this->coreService->processBatchExport(500, null, $request->getSchemeAndHttpHost());
        return new JsonResponse([
            'ok'            => true,
            'synced_events' => $synced,
            'hybrid'        => $this->coreService->isHybridEnabled(),
            'last_sync'     => gmdate('Y-m-d H:i:s') . ' UTC',
        ]);
    }

    /**
     * @Route("/wsc/v1/cards", name="frontend.wsc.cards", methods={"GET"})
     */
    #[Route(path: '/wsc/v1/cards', name: 'frontend.wsc.cards', methods: ['GET'])]
    public function cards(Request $request): JsonResponse
    {
        $cards = $this->coreService->getDashboardCards();
        $benchmarks = $this->coreService->getBenchmarkReport(7, false, null, $request->getSchemeAndHttpHost());

        return new JsonResponse([
            'ok'         => true,
            'cards'      => $cards,
            'benchmarks' => $benchmarks,
        ]);
    }

    /**
     * @Route("/wsc/v1/pagespeed/audit", name="frontend.wsc.pagespeed_audit", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/pagespeed/audit', name: 'frontend.wsc.pagespeed_audit', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function pagespeedAudit(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $url = trim((string) ($data['url'] ?? $request->getSchemeAndHttpHost()));
        $strategy = trim((string) ($data['strategy'] ?? 'mobile'));

        $result = $this->coreService->triggerSpeedSentinelAudit($url, $strategy, null, $request->getSchemeAndHttpHost());
        return new JsonResponse($result);
    }

    /**
     * @Route("/wsc/v1/sentinel/scan", name="frontend.wsc.sentinel_scan", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    #[Route(path: '/wsc/v1/sentinel/scan', name: 'frontend.wsc.sentinel_scan', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function sentinelScan(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $domain = trim((string) ($data['domain'] ?? $request->getHost()));

        $result = $this->coreService->triggerSentinelScan($domain, null, $request->getSchemeAndHttpHost());
        return new JsonResponse($result);
    }
}
