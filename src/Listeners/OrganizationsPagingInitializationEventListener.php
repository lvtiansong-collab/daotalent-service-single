<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\OrganizationsPagingInitializationEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class OrganizationsPagingInitializationEventListener
{
    public function handle(OrganizationsPagingInitializationEvent $event): void
    {
        $eventData = $event->data['message'] ?? null;
        Log::info('OrganizationsPagingInitializationEvent', ['eventData' => $eventData]);

        if (empty($eventData['organizations'])) {
            return;
        }

        $unitMapping = config('daotalent.unit');
        $updateColumns = array_values(array_diff($unitMapping, ['id']));

        $units = collect($eventData['organizations'])->map(function ($item) use ($unitMapping) {
            return SyncHelper::mapData($item, $unitMapping);
        });

        try {
            $unitModel = config('daotalent.models_namespace') . '\Unit';
            SyncHelper::bulkUpsert($unitModel, $units, $updateColumns);
            SyncHelper::resolveBulkUnitTypes();
        } catch (\Throwable $e) {
            Log::error('组织批量同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
