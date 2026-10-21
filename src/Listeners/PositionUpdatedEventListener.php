<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\PositionUpdatedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class PositionUpdatedEventListener
{
    public function handle(PositionUpdatedEvent $event): void
    {
        $position = $event->data['message']['position'] ?? null;
        Log::info('Position Updated', ['position' => $position]);

        if (empty($position)) {
            return;
        }

        $positionMapping = config('daotalent.position');
        $positionData = SyncHelper::mapData($position, $positionMapping);

        try {
            $positionModel = config('daotalent.models_namespace') . '\Position';
            SyncHelper::updateById($positionModel, $positionData);
        } catch (\Throwable $e) {
            Log::error('职位更新同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'position' => $position,
            ]);
            throw $e;
        }
    }
}
