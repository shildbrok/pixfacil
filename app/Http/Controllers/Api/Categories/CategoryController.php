<?php



namespace App\Http\Controllers\Api\Categories;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{



    private const NO_CACHE = 'no-store, no-cache, must-revalidate, max-age=0';


    public function index(Request $request)
    {
        $fp       = DB::table('categories')->selectRaw('COUNT(*) as c, MAX(updated_at) as m')->first();
        $cacheKey = 'pf:v2:categories:list:' . ($fp->c ?? 0) . ':' . ($fp->m ?? 'none');

        $categories = Cache::remember($cacheKey, now()->addDay(), function () {
            return Category::query()
                ->select(['id', 'name', 'slug', 'image', 'url', 'description', 'created_at', 'updated_at'])
                ->orderBy('name', 'asc')
                ->get()
                ->toArray();
        });

        return response()
            ->json(['categories' => $categories])
            ->header('Cache-Control', self::NO_CACHE);
    }


    public function show(Request $request, string $idOrSlug)
    {
        $isNumeric = ctype_digit($idOrSlug);

        $query = Category::query()
            ->select(['id', 'name', 'slug', 'image', 'url', 'description', 'created_at', 'updated_at']);

        $category = $isNumeric
            ? $query->find($idOrSlug)
            : $query->where('slug', $idOrSlug)->first();

        if (! $category) {
            return response()->json(['message' => 'Categoria não encontrada.'], 404);
        }

        return response()
            ->json(['category' => $category])
            ->header('Cache-Control', self::NO_CACHE);
    }
}