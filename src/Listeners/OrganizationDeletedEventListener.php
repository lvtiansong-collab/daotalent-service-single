<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\OrganizationDeletedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class OrganizationDeletedEventListener
{
    public function handle(OrganizationDeletedEvent $event): void
    {
        $unitId = strtoupper($event->data['message']['deletedId'] ?? '');
        Log::info('Organization Deleted', ['unitId' => $unitId]);

        if (empty($unitId)) {
            return;
        }

        $unitModel = config('daotalent.models_namespace') . '\Unit';

        try {
            SyncHelper::deleteById($unitModel, $unitId);
        } catch (\Throwable $e) {
            Log::error('组织删除同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'unit_id' => $unitId,
            ]);
            throw $e;
        }
    }
}
