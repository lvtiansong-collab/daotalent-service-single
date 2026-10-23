<?php

namespace Wiltechsteam\DaotalentServiceSingle;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncHelper
{
    /**
     * 通用数据映射：源字段 → 数据库字段
     *
     * @param array $source  源数据
     * @param array $mapping 字段映射配置 ['source_field' => 'db_column', ...]
     * @return array
     */
    public static function mapData(array $source, array $mapping): array
    {
        return collect($mapping)->mapWithKeys(function ($dbColumn, $sourceField) use ($source) {
            return [$dbColumn => $source[$sourceField] ?? null];
        })->toArray();
    }

    /**
     * 员工数据映射（含 unit name 查询）
     *
     * @param array $employee     员工源数据
     * @param array $staffMapping 员工字段映射配置
     * @param array $unitNames    unit_id => unit_name 映射
     * @return array
     */
    public static function mapStaffData(array $employee, array $staffMapping, array $unitNames): array
    {
        return collect($staffMapping)->mapWithKeys(function ($dbColumn, $sourceField) use ($employee, $unitNames) {
            if ($dbColumn == 'group_id') {
                return [$dbColumn => $employee['department_id']];
            }
            if ($dbColumn == 'group_name') {
                return [$dbColumn => $unitNames[$employee['department_id']] ?? null];
            }
            if ($dbColumn == 'department_name') {
                return [$dbColumn => $unitNames[$employee['department_id']] ?? null];
            }
            if ($dbColumn == 'company_name') {
                return [$dbColumn => $unitNames[$employee['company_id']] ?? null];
            }
            return [$dbColumn => $employee[$sourceField] ?? null];
        })->toArray();
    }

    /**
     * 批量 chunk upsert
     *
     * @param string     $modelClass     模型类名
     * @param Collection $records        待写入数据集合
     * @param array      $updateColumns  需要更新的字段（不含主键）
     * @return void
     */
    public static function bulkUpsert(string $modelClass, Collection $records, array $updateColumns): void
    {
        DB::transaction(function () use ($modelClass, $records, $updateColumns) {
            foreach ($records->chunk(500) as $chunk) {
                $rows = $chunk->map(function ($row) use ($modelClass) {
                    return (new $modelClass)->fill($row)->getAttributes();
                })->values()->toArray();

                $modelClass::query()->upsert($rows, ['id'], $updateColumns);
            }
        });
    }

    /**
     * 单条 updateOrCreate
     *
     * @param string $modelClass 模型类名
     * @param array  $data       数据
     * @return void
     */
    public static function updateOrCreate(string $modelClass, array $data): void
    {
        $modelClass::query()->updateOrCreate(['id' => $data['id']], $data);
    }

    /**
     * 单条 update by id
     *
     * @param string $modelClass 模型类名
     * @param array  $data       数据
     * @return void
     */
    public static function updateById(string $modelClass, array $data): void
    {
        $record = $modelClass::query()->find($data['id']);

        if (empty($record)) {
            return;
        }

        $record->fill($data)->save();
    }

    /**
     * 按 ID 删除
     *
     * @param string $modelClass 模型类名
     * @param string $id         记录 ID
     * @return bool
     */
    public static function deleteById(string $modelClass, string $id): bool
    {
        $record = $modelClass::query()->find($id);

        if (empty($record)) {
            return false;
        }

        $record->delete();
        return true;
    }

    /**
     * 根据 unit id 推算组织类型（单条同步用）
     * 沿 parent_id 链逐级查上去计算层级深度
     *
     * @param string|null $unitId    当前组织的 id
     * @param string      $unitModel Unit 模型类名
     * @return int
     */
    public static function resolveUnitType(?string $unitId, string $unitModel): int
    {
        $depth = 0;
        $parentId = $unitModel::query()->where('id', $unitId)->value('parent_id');

        // parent_id 可能成环（如自引用），必须防死循环
        $visited = [(string)$unitId => true];
        while ($parentId && !isset($visited[$parentId])) {
            $depth++;
            $visited[$parentId] = true;
            $parentId = $unitModel::query()->where('id', $parentId)->value('parent_id');
        }

        if ($depth <= 1) {
            return 8; // 公司
        }

        if ($depth == 2) {
            return 4; // 部门
        }

        return 2; // 小组
    }

    /**
     * 批量修正组织架构类型（初始化用）
     * 一次查出 id + parent_id，在内存中算好层级深度，再按类型分组批量更新
     *
     * @return void
     */
    public static function resolveBulkUnitTypes(): void
    {
        $parentMap = DB::table('units')->pluck('parent_id', 'id')->toArray();

        $grouped = [8 => [], 4 => [], 2 => []];
        foreach ($parentMap as $id => $parentId) {
            $depth = 0;
            // parent_id 可能成环（如自引用），必须防死循环
            $visited = [$id => true];
            while ($parentId && !isset($visited[$parentId])) {
                $depth++;
                $visited[$parentId] = true;
                $parentId = $parentMap[$parentId] ?? null;
            }

            if ($depth <= 1) {
                $type = 8; // 公司
            } elseif ($depth == 2) {
                $type = 4; // 部门
            } else {
                $type = 2; // 小组
            }

            $grouped[$type][] = $id;
        }

        foreach ($grouped as $type => $ids) {
            if (!empty($ids)) {
                DB::table('units')
                    ->whereIn('id', $ids)
                    ->where('type', '!=', $type)
                    ->update(['type' => $type]);
            }
        }
    }
}
