<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\OrganizationAddedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class OrganizationAddedEventListener
{
    public function handle(OrganizationAddedEvent $event): void
    {
        $organization = $event->data['message']['organization'] ?? null;
        Log::info('Organization Added', ['organization' => $organization]);

        if (empty($organization)) {
            return;
        }

        $unitMapping = config('daotalent.unit');
        $unitData = SyncHelper::mapData($organization, $unitMapping);

        try {
            $unitModel = config('daotalent.models_namespace') . '\Unit';
            SyncHelper::updateOrCreate($unitModel, $unitData);

            $unitModel::query()->where('id', $unitData['id'])->update([
                'type' => SyncHelper::resolveUnitType($unitData['id'] ?? null, $unitModel),
            ]);
        } catch (\Throwable $e) {
            Log::error('组织新增同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'organization' => $organization,
            ]);
            throw $e;
        }
    }
}
