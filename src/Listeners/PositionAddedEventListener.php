<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\PositionAddedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class PositionAddedEventListener
{
    public function handle(PositionAddedEvent $event): void
    {
        $position = $event->data['message']['position'] ?? null;
        Log::info('Position Added', ['position' => $position]);

        if (empty($position)) {
            return;
        }

        $positionMapping = config('daotalent.position');
        $positionData = SyncHelper::mapData($position, $positionMapping);

        try {
            $positionModel = config('daotalent.models_namespace') . '\Position';
            SyncHelper::updateOrCreate($positionModel, $positionData);
        } catch (\Throwable $e) {
            Log::error('职位新增同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'position' => $position,
            ]);
            throw $e;
        }
    }
}
