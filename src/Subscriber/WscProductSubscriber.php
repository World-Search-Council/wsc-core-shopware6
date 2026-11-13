<?php

declare(strict_types=1);

namespace Wsc\Analytics\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Wsc\Analytics\Service\WscCoreService;
use Psr\Log\LoggerInterface;

/**
 * Opt-in automatic IndexNow URL push on Shopware 6 product save (`timeout = 3s`, non-blocking).
 */
class WscProductSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfig;
    private WscCoreService $coreService;
    private LoggerInterface $logger;

    public function __construct(
        SystemConfigService $systemConfig,
        WscCoreService $coreService,
        LoggerInterface $logger
    ) {
        $this->systemConfig = $systemConfig;
        $this->coreService = $coreService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        // Strictly opt-in: do nothing unless indexnowEnabled is true
        if (!$this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'indexnowEnabled')) {
            return;
        }

        try {
            $ids = $event->getIds();
            if (empty($ids)) {
                return;
            }

            $urls = $this->coreService->resolveProductUrlsByIds(array_slice($ids, 0, 10));
            if (!empty($urls)) {
                $this->coreService->submitIndexNowUrls($urls, 'auto_product_save', 3);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('WSC IndexNow Product Save Hook Notice: ' . $e->getMessage());
        }
    }
}
