<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\ProductSchema;
use App\Models\AmazonSchema;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use App\Controller\TestController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        return view('admin.categories.index', compact('categories'));
    }
   
    public function importCategories()
    {
        $sqlFile = database_path('seeders/sql/categories.sql');
        if (!File::exists($sqlFile)) {
            return response()->json(['message' => 'File not found']);
        }

        $sql = File::get($sqlFile);
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        $total = count($queries);
        $processed = 0;

        foreach ($queries as $query) {
            if (!empty($query)) {
                DB::statement($query);
                $processed++;
                if ($processed % 100 == 0) {
                    echo "Processed {$processed}/{$total}<br>";
                    ob_flush();
                    flush();
                }
            }
        }

        return response()->json([
            'success' => true,
            'processed' => $processed
        ]);
    }

    public function create()
    {
        $categories = Category::all();
        return view('admin.categories.create', compact('categories'));
    }

    public function store(Request $request)
    {
        
        $dataamazon = new TestController();
        $data = $dataamazon->getUnitCountSchema('KEYBOARDS');

        $url = $data->original['full_schema']['schema']['link']['resource']??'';
        $content = Http::timeout(120)->get($url)->body();
        $schemaJson = json_decode($content, true );

        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories',
            'marketplaceIds' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:active,draft'
        ]);

        Category::create($request->all());
        return redirect()->route('admin.categories')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        $categories = Category::where('id', '!=', $category->id)->get();
        return view('admin.categories.edit', compact('category', 'categories'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories,slug,' . $category->id,
            'marketplaceIds' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:active,draft'
        ]);

        $category->update($request->all());

        return redirect()->route('admin.categories')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories')->with('success', 'Category deleted successfully.');
    }

    public function activate(Category $category)
    {
        $category->update(['status' => 'active']);
        return redirect()->route('admin.categories')->with('success', 'Category activated successfully.');
    }

    public function deactivate(Category $category)
    {
        $category->update(['status' => 'draft']);
        return redirect()->route('admin.categories')->with('success', 'Category deactivated successfully.');
    }


}
