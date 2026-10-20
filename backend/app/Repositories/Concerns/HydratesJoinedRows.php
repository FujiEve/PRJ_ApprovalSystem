<?php

namespace App\Repositories\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * JOIN で取得したフラットな行から、Eloquent モデルを組み立てる。
 * 接頭辞付きの列(例: ap_name)を、その接頭辞のモデルの属性(name)として取り出す。
 */
trait HydratesJoinedRows
{
    /**
     * @template TModel of Model
     * @param class-string<TModel> $modelClass
     * @return TModel|null 主キーが null (JOIN 先が存在しない) の場合は null
     */
    protected function hydrateFromRow(string $modelClass, array $row, string $prefix = '', array $columns = []): ?Model
    {
        if ($row[$prefix . 'id'] === null) {
            return null;
        }

        $attributes = [];
        foreach ($columns as $column) {
            $attributes[$column] = $row[$prefix . $column];
        }

        return (new $modelClass())->newFromBuilder($attributes);
    }
}
