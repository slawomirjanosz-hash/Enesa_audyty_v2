<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListSearch
{
    public static function apply(Builder $query, Request $request, array $columns, array $relations = []): void
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $terms = preg_split('/\s+/u', trim((string) $request->input('q', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($terms as $term) {
            $query->where(function (Builder $group) use ($term, $columns, $relations) {
                foreach ($columns as $column) {
                    $group->orWhere($column, 'like', '%'.$term.'%');
                }
                foreach ($relations as $relation => $fields) {
                    $group->orWhereHas($relation, function (Builder $related) use ($fields, $term) {
                        $related->where(function (Builder $values) use ($fields, $term) {
                            foreach ($fields as $field) {
                                $values->orWhere($field, 'like', '%'.$term.'%');
                            }
                        });
                    });
                }
            });
        }
    }
}
