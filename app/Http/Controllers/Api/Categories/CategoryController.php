<?php

namespace App\Http\Controllers\Api\Categories;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoryController extends Controller
{
    private const NO_CACHE = 'no-store, no-cache, must-revalidate, max-age=0';

    public function index(Request $request)
    {
        if (! Schema::hasTable('categories')) {
            return response()->json(['categories' => []])->header('Cache-Control', self::NO_CACHE);
        }

        $columns = $this->availableColumns();
        if ($columns === []) {
            return response()->json(['categories' => []])->header('Cache-Control', self::NO_CACHE);
        }

        try {
            $fingerprint = $this->fingerprint();
            $cacheKey = 'pf:v3:categories:list:' . $fingerprint;

            $categories = Cache::remember($cacheKey, now()->addHour(), function () use ($columns) {
                $query = Category::query()->select($columns);

                if (in_array('name', $columns, true)) {
                    $query->orderBy('name');
                } elseif (in_array('id', $columns, true)) {
                    $query->orderBy('id');
                }

                return $query->get()->map(fn ($category) => $this->normalizeCategory($category))->values();
            });

            return response()->json(['categories' => $categories])->header('Cache-Control', self::NO_CACHE);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['categories' => $this->fallbackRows($columns)])
                ->header('Cache-Control', self::NO_CACHE);
        }
    }

    public function show(Request $request, string $idOrSlug)
    {
        if (! Schema::hasTable('categories')) {
            return response()->json(['message' => 'Categoria não encontrada.'], 404);
        }

        $columns = $this->availableColumns();
        if ($columns === []) {
            return response()->json(['message' => 'Categoria não encontrada.'], 404);
        }

        try {
            $query = Category::query()->select($columns);
            $isNumeric = ctype_digit($idOrSlug);

            if ($isNumeric && in_array('id', $columns, true)) {
                $category = $query->find($idOrSlug);
            } elseif (in_array('slug', $columns, true)) {
                $category = $query->where('slug', $idOrSlug)->first();
            } else {
                $category = null;
            }

            if (! $category) {
                return response()->json(['message' => 'Categoria não encontrada.'], 404);
            }

            return response()->json(['category' => $this->normalizeCategory($category)])
                ->header('Cache-Control', self::NO_CACHE);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Categoria não encontrada.'], 404);
        }
    }

    private function availableColumns(): array
    {
        $preferred = ['id', 'name', 'slug', 'image', 'url', 'description', 'created_at', 'updated_at'];

        return array_values(array_filter(
            $preferred,
            fn (string $column) => Schema::hasColumn('categories', $column)
        ));
    }

    private function normalizeCategory($category): array
    {
        return [
            'id' => $category->id ?? null,
            'name' => $category->name ?? 'Categoria',
            'slug' => $category->slug ?? null,
            'image' => $category->image ?? null,
            'url' => $category->url ?? null,
            'description' => $category->description ?? null,
            'created_at' => $category->created_at ?? null,
            'updated_at' => $category->updated_at ?? null,
        ];
    }

    private function fingerprint(): string
    {
        try {
            $count = DB::table('categories')->count();
            $updated = Schema::hasColumn('categories', 'updated_at')
                ? DB::table('categories')->max('updated_at')
                : null;

            return $count . ':' . ($updated ?: 'none') . ':' . implode(',', $this->availableColumns());
        } catch (\Throwable $e) {
            report($e);
            return 'fallback:' . time();
        }
    }

    private function fallbackRows(array $columns): array
    {
        try {
            $query = DB::table('categories')->select($columns);
            if (in_array('name', $columns, true)) $query->orderBy('name');

            return collect($query->get())->map(function ($row) {
                return [
                    'id' => $row->id ?? null,
                    'name' => $row->name ?? 'Categoria',
                    'slug' => $row->slug ?? null,
                    'image' => $row->image ?? null,
                    'url' => $row->url ?? null,
                    'description' => $row->description ?? null,
                    'created_at' => $row->created_at ?? null,
                    'updated_at' => $row->updated_at ?? null,
                ];
            })->values()->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }
}
