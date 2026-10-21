<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\PositionDeletedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class PositionDeletedEventListener
{
    public function handle(PositionDeletedEvent $event): void
    {
        $positionId = strtoupper($event->data['message']['deletedId'] ?? '');
        Log::info('Position Deleted', ['positionId' => $positionId]);

        if (empty($positionId)) {
            return;
        }

        $positionModel = config('daotalent.models_namespace') . '\Position';

        try {
            SyncHelper::deleteById($positionModel, $positionId);
        } catch (\Throwable $e) {
            Log::error('职位删除同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'position_id' => $positionId,
            ]);
            throw $e;
        }
    }
}
