<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'category',
        'slug',
        'marketplaceIds',
        'parent_id',
        'level',
        'status',
        'self_added'
    ];

    // Parent Category
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Child Categories
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Recursive children (for deep hierarchy like Amazon)
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }
}