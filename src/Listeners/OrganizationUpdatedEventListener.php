<?php

namespace Wiltechsteam\DaotalentServiceSingle\Listeners;

use Illuminate\Support\Facades\Log;
use Wiltechsteam\DaotalentServiceSingle\Events\OrganizationUpdatedEvent;
use Wiltechsteam\DaotalentServiceSingle\SyncHelper;

class OrganizationUpdatedEventListener
{
    public function handle(OrganizationUpdatedEvent $event): void
    {
        $organization = $event->data['message']['organization'] ?? null;
        Log::info('Organization Updated', ['organization' => $organization]);

        if (empty($organization)) {
            return;
        }

        $unitMapping = config('daotalent.unit');
        $unitData = SyncHelper::mapData($organization, $unitMapping);

        try {
            $unitModel = config('daotalent.models_namespace') . '\Unit';
            SyncHelper::updateById($unitModel, $unitData);

            $unitModel::query()->where('id', $unitData['id'])->update([
                'type' => SyncHelper::resolveUnitType($unitData['id'], $unitModel),
            ]);
        } catch (\Throwable $e) {
            Log::error('组织更新同步失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'organization' => $organization,
            ]);
            throw $e;
        }
    }
}
