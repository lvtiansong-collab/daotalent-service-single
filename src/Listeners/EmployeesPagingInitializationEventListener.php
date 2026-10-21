<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\EmployeesPagingInitializationEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class EmployeesPagingInitializationEventListener
{
    public function handle(EmployeesPagingInitializationEvent $event): void
    {
        $eventData = $event->data['message'] ?? null;
        Log::info('EmployeesPagingInitializationEvent', ['eventData' => $eventData]);

        if (empty($eventData['employees'])) {
            return;
        }

        $staffMapping = config('daotalent.staff');
        $updateColumns = array_values(array_diff($staffMapping, ['id']));
        $unitModel = config('daotalent.unit_model', \Wiltechsteam\DaotalentServiceSingle\Models\Unit::class);

        $employees = $eventData['employees'];

        // 批量预查 unit name
        $allUnitIds = array_unique(array_merge(
            array_column($employees, 'department_id'),
            array_column($employees, 'company_id'),
        ));
        $unitNames = $unitModel::query()->whereIn('id', $allUnitIds)->pluck('unit_name', 'id')->toArray();

        $staffs = collect($employees)->map(function ($employee) use ($staffMapping, $unitNames) {
            return SyncHelper::mapStaffData($employee, $staffMapping, $unitNames);
        });

        try {
            $staffModel = config('daotalent.models_namespace') . '\Staff';
            SyncHelper::bulkUpsert($staffModel, $staffs, $updateColumns);
        } catch (\Throwable $e) {
            Log::error('员工初始化同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
