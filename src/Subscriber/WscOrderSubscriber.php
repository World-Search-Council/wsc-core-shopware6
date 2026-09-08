<?php

declare(strict_types=1);

namespace Wsc\Analytics\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Wsc\Analytics\Service\WscCoreService;
use Psr\Log\LoggerInterface;

/**
 * Server-side 100% ad-blocker immune order tracking for Shopware 6.
 */
class WscOrderSubscriber implements EventSubscriberInterface
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
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    /**
     * Intercept completed order and write into local SQLite queue.
     */
    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();

        if (!$this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'trackingActive', $salesChannelId)) {
            return;
        }

        if (!$this->systemConfig->getBool(WscCoreService::CONFIG_PREFIX . 'trackServerOrders', $salesChannelId)) {
            return;
        }

        try {
            $order = $event->getOrder();
            $lineItems = [];

            if ($order->getLineItems() !== null) {
                foreach ($order->getLineItems() as $item) {
                    $lineItems[] = [
                        'id'             => $item->getIdentifier(),
                        'product_id'     => $item->getReferencedId(),
                        'label'          => $item->getLabel(),
                        'quantity'       => $item->getQuantity(),
                        'unit_price'     => $item->getUnitPrice(),
                        'total_price'    => $item->getTotalPrice(),
                    ];
                }
            }

            $meta = [
                'order_id'       => $order->getId(),
                'order_number'   => $order->getOrderNumber(),
                'amount_total'   => $order->getAmountTotal(),
                'amount_net'     => $order->getAmountNet(),
                'shipping_total' => $order->getShippingTotal(),
                'currency'       => $order->getCurrency() ? $order->getCurrency()->getIsoCode() : 'EUR',
                'items_count'    => count($lineItems),
                'items'          => $lineItems,
                'source'         => 'server_side_shopware6',
            ];

            $this->coreService->trackEvent('purchase', '/checkout/finish', '', $meta);
        } catch (\Throwable $e) {
            $this->logger->error('WSC Shopware Order Track Error: ' . $e->getMessage());
        }
    }
}
