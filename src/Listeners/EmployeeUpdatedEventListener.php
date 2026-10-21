<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\EmployeeUpdatedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class EmployeeUpdatedEventListener
{
    public function handle(EmployeeUpdatedEvent $event): void
    {
        $employee = $event->data['message']['employee'] ?? null;
        Log::info('Employee Updated', ['employee' => $employee]);

        if (empty($employee)) {
            return;
        }

        $staffMapping = config('daotalent.staff');
        $unitModel = config('daotalent.unit_model', \Wiltechsteam\DaotalentServiceSingle\Models\Unit::class);

        $unitIds = array_filter([$employee['department_id'], $employee['company_id']]);
        $unitNames = $unitModel::query()->whereIn('id', $unitIds)->pluck('unit_name', 'id')->toArray();

        $staffData = SyncHelper::mapStaffData($employee, $staffMapping, $unitNames);

        try {
            $staffModel = config('daotalent.models_namespace') . '\Staff';
            SyncHelper::updateById($staffModel, $staffData);
        } catch (\Throwable $e) {
            Log::error('员工更新同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'employee' => $employee,
            ]);
            throw $e;
        }
    }
}
