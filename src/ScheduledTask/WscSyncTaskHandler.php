<?php

declare(strict_types=1);

namespace Wsc\Analytics\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Wsc\Analytics\Service\WscCoreService;
use Psr\Log\LoggerInterface;

/**
 * Executes micro-batch synchronization and daily retention maintenance for Shopware 6.
 */
class WscSyncTaskHandler extends ScheduledTaskHandler
{
    private WscCoreService $coreService;
    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        WscCoreService $coreService,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->coreService = $coreService;
        $this->logger = $logger;
    }

    public function run(): void
    {
        try {
            $synced = $this->coreService->processBatchExport(500);
            if ($synced > 0) {
                $this->logger->info("WSC Shopware Task: Synced $synced micro-batch events to Gateway.");
            }

            // Routine cleanup
            $this->coreService->cleanupOldData();
        } catch (\Throwable $e) {
            $this->logger->error('WSC Shopware Task Error: ' . $e->getMessage());
        }
    }
}
