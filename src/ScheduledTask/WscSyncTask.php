<?php

declare(strict_types=1);

namespace Wsc\Analytics\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Scheduled Task for recurring WSC micro-batch synchronization in Shopware 6.
 */
class WscSyncTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'wsc.analytics_sync';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // 5 minutes
    }
}
