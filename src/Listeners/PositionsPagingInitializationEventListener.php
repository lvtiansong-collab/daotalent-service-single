<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\PositionsPagingInitializationEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class PositionsPagingInitializationEventListener
{
    public function handle(PositionsPagingInitializationEvent $event): void
    {
        $eventData = $event->data['message'] ?? null;
        Log::info('PositionsPagingInitializationEvent', ['eventData' => $eventData]);

        if (empty($eventData['positions'])) {
            return;
        }

        $positionMapping = config('daotalent.position');
        $updateColumns = array_values(array_diff($positionMapping, ['id']));

        $positions = collect($eventData['positions'])->map(function ($item) use ($positionMapping) {
            return SyncHelper::mapData($item, $positionMapping);
        });

        try {
            $positionModel = config('daotalent.models_namespace') . '\Position';
            SyncHelper::bulkUpsert($positionModel, $positions, $updateColumns);
        } catch (\Throwable $e) {
            Log::error('职位批量同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
