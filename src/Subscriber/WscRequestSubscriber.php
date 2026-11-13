<?php

declare(strict_types=1);

namespace Wsc\Analytics\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Wsc\Analytics\Service\WscBotTracker;
use Wsc\Analytics\Service\WscCoreService;

/**
 * Early Edge AI crawler sensor, `/llms.txt` manifest server, virtual `/{key}.txt` IndexNow verifier,
 * and Storefront configuration injector for Shopware 6 (v2.2.3).
 */
class WscRequestSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfig;
    private WscBotTracker $botTracker;
    private WscCoreService $coreService;

    public function __construct(
        SystemConfigService $systemConfig,
        WscBotTracker $botTracker,
        WscCoreService $coreService
    ) {
        $this->systemConfig = $systemConfig;
        $this->botTracker = $botTracker;
        $this->coreService = $coreService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 250],
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    /**
     * Early Kernel request hook:
     * 1. Serves dynamic `/llms.txt` when enabled
     * 2. Serves virtual `/{indexnowKey}.txt` key file
     * 3. Captures AI & Search bot hits before Full-Page Cache (Zero-PII)
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = trim($request->getPathInfo(), '/');

        // 1. Serve dynamic /llms.txt AI Manifest
        if ($path === 'llms.txt' && $request->isMethod('GET') && $this->coreService->isLlmsTxtEnabled()) {
            $body = $this->coreService->generateLlmsTxt($request->getSchemeAndHttpHost());
            $event->setResponse(new Response($body, Response::HTTP_OK, [
                'Content-Type'  => 'text/plain; charset=utf-8',
                'X-Robots-Tag'  => 'noindex, follow',
                'Cache-Control' => 'public, max-age=3600',
            ]));
            return;
        }

        // 2. Serve virtual IndexNow key verification endpoint /{indexnowKey}.txt
        if ($request->isMethod('GET') && preg_match('/^([a-f0-9]{32})\.txt$/i', $path, $matches)) {
            $configuredKey = $this->coreService->getIndexNowKey();
            if (hash_equals(strtolower($configuredKey), strtolower($matches[1]))) {
                $event->setResponse(new Response($configuredKey, Response::HTTP_OK, [
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'X-Robots-Tag' => 'noindex, follow',
                ]));
                return;
            }
        }

        // 3. Edge Bot Sensor (Zero-PII)
        if (!$this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'trackingActive')) {
            return;
        }

        $botLevel = $this->systemConfig->getString(WscCoreService::CONFIG_PREFIX . 'botTrackingLevel') ?: 'search_ai';
        if ($botLevel === 'off') {
            return;
        }

        $ua = (string) $request->headers->get('user-agent', '');
        if ($ua === '') {
            return;
        }

        $bot = $this->botTracker->detectBot($ua);
        if (!$bot) {
            return;
        }

        if ($botLevel === 'ai_only' && ($bot['category'] ?? '') !== 'ai') {
            return;
        }

        $this->coreService->recordBotHit(
            $bot,
            $request->getPathInfo(),
            (string) $request->headers->get('referer', ''),
            (string) ($request->getClientIp() ?: ''),
            $ua
        );
    }

    /**
     * Inject WSC runtime configuration into Storefront Twig templates.
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();

        if (!$this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'trackingActive', $salesChannelId)) {
            return;
        }

        $config = [
            'endpoint'   => '/wsc/v1/track',
            'mode'       => $this->systemConfig->getString(WscCoreService::CONFIG_PREFIX . 'trackingMode', $salesChannelId) ?: 'cookieless',
            'consentKey' => 'wsc_consent',
            'auto'       => [
                'downloads'  => $this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'autoDownloads', $salesChannelId),
                'timeOnPage' => $this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'autoTimeOnPage', $salesChannelId),
            ],
        ];

        $event->setParameter('wscConfig', $config);
    }
}
