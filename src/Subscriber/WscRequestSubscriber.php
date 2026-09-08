<?php

declare(strict_types=1);

namespace Wsc\Analytics\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Wsc\Analytics\Service\WscBotTracker;
use Wsc\Analytics\Service\WscCoreService;

/**
 * Early Edge AI crawler sensor & Storefront data injector for Shopware 6.
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
     * Edge bot tracking hook executed before controllers.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'trackingActive')) {
            return;
        }

        $botLevel = $this->systemConfig->getString(WscCoreService::CONFIG_PREFIX . 'botTrackingLevel') ?: 'search_ai';
        if ($botLevel === 'off') {
            return;
        }

        $request = $event->getRequest();
        $ua = (string) $request->headers->get('user-agent', '');
        if (empty($ua)) {
            return;
        }

        $bot = $this->botTracker->detectBot($ua);
        if (!$bot) {
            return;
        }

        if ($botLevel === 'ai_only' && ($bot['category'] ?? '') !== 'ai') {
            return;
        }

        $this->coreService->trackEvent('bot_hit', $request->getPathInfo(), (string)$request->headers->get('referer', ''), [
            'bot_name'     => $bot['name'],
            'bot_category' => $bot['category'],
            'bot_type'     => $bot['type'],
            'ip'           => $request->getClientIp(),
        ]);
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
            'endpoint' => '/wsc/v1/track',
            'mode' => $this->systemConfig->getString(WscCoreService::CONFIG_PREFIX . 'trackingMode', $salesChannelId) ?: 'cookieless',
            'consentKey' => 'wsc_consent',
            'auto' => [
                'downloads' => $this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'autoDownloads', $salesChannelId),
                'timeOnPage' => $this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'autoTimeOnPage', $salesChannelId),
            ],
        ];

        $event->setParameter('wscConfig', $config);
    }
}
