<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function updateParentBySearch(array $keywords, int $parentId, bool $strict = true)
    {
        // safety check
        $parent = DB::table('categories')->where('id', $parentId)->first();

        if (!$parent) {
            return ['error' => 'Parent category not found'];
        }

        $query = DB::table('categories');

        foreach ($keywords as $index => $keyword) {

            $keyword = strtolower(trim($keyword));

            if ($strict) {
                $condition = "LOWER(name) REGEXP ?";
                $value = "[[:<:]]{$keyword}[[:>:]]";
            } else {
                $condition = "LOWER(name) LIKE ?";
                $value = "%{$keyword}%";
            }

            if ($index === 0) {
                $query->whereRaw($condition, [$value]);
            } else {
                $query->orWhereRaw($condition, [$value]);
            }
        }

        $matched = (clone $query)->get();

        $query->update([
            'parent_id' => $parentId,
            'level' => 1
        ]);

        return [
            'matched_count' => $matched->count(),
            'matched' => $matched
        ];
    }
}
