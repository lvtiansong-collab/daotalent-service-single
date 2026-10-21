<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\EmployeeDeletedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class EmployeeDeletedEventListener
{
    public function handle(EmployeeDeletedEvent $event): void
    {
        $staffId = strtoupper($event->data['message']['deletedId'] ?? '');
        Log::info('Employee Deleted', ['staffId' => $staffId]);

        if (empty($staffId)) {
            return;
        }

        $staffModel = config('daotalent.models_namespace') . '\Staff';

        try {
            SyncHelper::deleteById($staffModel, $staffId);
        } catch (\Throwable $e) {
            Log::error('员工删除同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'staff_id' => $staffId,
            ]);
            throw $e;
        }
    }
}
