<?php

declare(strict_types=1);

namespace Northvia\Modules\Products\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Northvia\Core\Database\Database;
use Northvia\Core\Http\Response;
use Northvia\Core\Validation\Validator;
use Northvia\Modules\Products\Models\Product;

/**
 * Product management controller
 */
class ProductController
{
    private Database $db;
    private Validator $validator;

    public function __construct(Database $db, Validator $validator)
    {
        $this->db = $db;
        $this->validator = $validator;
    }

    /**
     * Get all products with filters and pagination
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->query();
        
        // Pagination
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(50, max(10, (int) ($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        // Build query
        $queryBuilder = $this->db->table('products p')
            ->select([
                'p.*',
                'c.name as category_name',
                'b.name as brand_name',
                'v.business_name as vendor_name'
            ]);

        // Join related tables
        $queryBuilder = $this->addProductJoins($queryBuilder);
        
        // Apply filters
        $this->applyProductFilters($queryBuilder, $query);
        
        // Get total count before applying limit
        $countQuery = clone $queryBuilder;
        $totalCount = $countQuery->count();
        
        // Apply sorting
        $this->applyProductSorting($queryBuilder, $query);
        
        // Apply pagination
        $products = $queryBuilder->limit($limit)->offset($offset)->get();
        
        // Transform products
        $transformedProducts = [];
        foreach ($products as $productData) {
            $product = Product::fromArray((array) $productData);
            
            // Add images
            $product->images = $this->getProductImages($product->id);
            
            $transformedProducts[] = $product->toArray();
        }

        return Response::success($transformedProducts, 'Products retrieved successfully')
            ->withPagination([
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'last_page' => (int) ceil($totalCount / $limit),
                'from' => $offset + 1,
                'to' => min($offset + $limit, $totalCount)
            ]);
    }

    /**
     * Get single product by ID or slug
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $identifier = $request->getAttribute('id');
        
        $queryBuilder = $this->db->table('products p')
            ->select([
                'p.*',
                'c.name as category_name',
                'b.name as brand_name',
                'v.business_name as vendor_name'
            ]);

        $queryBuilder = $this->addProductJoins($queryBuilder);

        // Check if identifier is numeric (ID) or string (slug)
        if (is_numeric($identifier)) {
            $queryBuilder->where('p.id', $identifier);
        } else {
            $queryBuilder->where('p.slug', $identifier);
        }

        $productData = $queryBuilder->first();

        if (!$productData) {
            return Response::notFound('Product not found');
        }

        $product = Product::fromArray((array) $productData);
        
        // Add related data
        $product->images = $this->getProductImages($product->id);
        $product->variants = $this->getProductVariants($product->id);
        
        // Get recent reviews
        $reviews = $this->getProductReviews($product->id, 5);
        
        // Get related products
        $relatedProducts = $this->getRelatedProducts($product->id, $product->category_id, 4);

        return Response::success([
            'product' => $product->toArray(),
            'reviews' => $reviews,
            'related_products' => $relatedProducts
        ], 'Product retrieved successfully');
    }

    /**
     * Search products
     */
    public function search(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->query();
        $searchTerm = trim($query['q'] ?? '');
        
        if (empty($searchTerm)) {
            return Response::error('Search term is required', 400);
        }

        // Pagination
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(50, max(10, (int) ($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Build search query
        $queryBuilder = $this->db->table('products p')
            ->select([
                'p.*',
                'c.name as category_name',
                'b.name as brand_name',
                'v.business_name as vendor_name'
            ]);

        $queryBuilder = $this->addProductJoins($queryBuilder);

        // Apply search filters
        $queryBuilder->where(function($query) use ($searchTerm) {
            $query->where('p.name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('p.description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('p.short_description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('p.search_keywords', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('p.sku', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('c.name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('b.name', 'LIKE', "%{$searchTerm}%");
        });

        // Only show active products
        $queryBuilder->where('p.status', 'active');

        // Apply additional filters
        $this->applyProductFilters($queryBuilder, $query, ['status']);

        // Get total count
        $totalCount = $queryBuilder->count();

        // Apply sorting (relevance by default)
        $sort = $query['sort'] ?? 'relevance';
        if ($sort === 'relevance') {
            $queryBuilder->orderBy('p.featured', 'DESC')
                        ->orderBy('p.total_sales', 'DESC')
                        ->orderBy('p.rating', 'DESC');
        } else {
            $this->applyProductSorting($queryBuilder, $query);
        }

        // Apply pagination
        $products = $queryBuilder->limit($limit)->offset($offset)->get();

        // Transform products
        $transformedProducts = [];
        foreach ($products as $productData) {
            $product = Product::fromArray((array) $productData);
            $product->images = $this->getProductImages($product->id);
            $transformedProducts[] = $product->toArray();
        }

        // Log search term for analytics
        $this->logSearchTerm($searchTerm, $totalCount, $request->getAttribute('user')?->id);

        return Response::success($transformedProducts, 'Search completed successfully')
            ->withPagination([
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'last_page' => (int) ceil($totalCount / $limit),
                'from' => $offset + 1,
                'to' => min($offset + $limit, $totalCount)
            ])
            ->withMeta([
                'search_term' => $searchTerm,
                'filters_applied' => array_intersect_key($query, array_flip(['category', 'brand', 'min_price', 'max_price', 'in_stock']))
            ]);
    }

    /**
     * Get product categories
     */
    public function getCategories(ServerRequestInterface $request): ResponseInterface
    {
        $categories = $this->db->table('categories')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Build category tree
        $categoryTree = $this->buildCategoryTree($categories);

        return Response::success($categoryTree, 'Categories retrieved successfully');
    }

    /**
     * Get product brands
     */
    public function getBrands(ServerRequestInterface $request): ResponseInterface
    {
        $brands = $this->db->table('brands')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return Response::success($brands, 'Brands retrieved successfully');
    }

    /**
     * Get featured products
     */
    public function getFeatured(ServerRequestInterface $request): ResponseInterface
    {
        $limit = min(20, max(4, (int) ($request->query('limit') ?? 8)));

        $queryBuilder = $this->db->table('products p')
            ->select([
                'p.*',
                'c.name as category_name',
                'b.name as brand_name'
            ]);

        $queryBuilder = $this->addProductJoins($queryBuilder);
        
        $queryBuilder->where('p.featured', 1)
                    ->where('p.status', 'active')
                    ->orderBy('p.total_sales', 'DESC')
                    ->orderBy('p.rating', 'DESC')
                    ->limit($limit);

        $products = $queryBuilder->get();

        $transformedProducts = [];
        foreach ($products as $productData) {
            $product = Product::fromArray((array) $productData);
            $product->images = $this->getProductImages($product->id);
            $transformedProducts[] = $product->toArray();
        }

        return Response::success($transformedProducts, 'Featured products retrieved successfully');
    }

    /**
     * Get products by category
     */
    public function getByCategory(ServerRequestInterface $request): ResponseInterface
    {
        $categoryId = $request->getAttribute('category_id');
        $query = $request->query();

        // Pagination
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(50, max(10, (int) ($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->db->table('products p')
            ->select([
                'p.*',
                'c.name as category_name',
                'b.name as brand_name'
            ]);

        $queryBuilder = $this->addProductJoins($queryBuilder);
        
        $queryBuilder->where('p.category_id', $categoryId)
                    ->where('p.status', 'active');

        // Apply additional filters
        $this->applyProductFilters($queryBuilder, $query, ['category']);

        // Get total count
        $totalCount = $queryBuilder->count();

        // Apply sorting
        $this->applyProductSorting($queryBuilder, $query);

        // Apply pagination
        $products = $queryBuilder->limit($limit)->offset($offset)->get();

        $transformedProducts = [];
        foreach ($products as $productData) {
            $product = Product::fromArray((array) $productData);
            $product->images = $this->getProductImages($product->id);
            $transformedProducts[] = $product->toArray();
        }

        return Response::success($transformedProducts, 'Category products retrieved successfully')
            ->withPagination([
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'last_page' => (int) ceil($totalCount / $limit),
                'from' => $offset + 1,
                'to' => min($offset + $limit, $totalCount)
            ]);
    }

    // Helper methods

    private function addProductJoins($queryBuilder)
    {
        return $queryBuilder
            ->leftJoin('categories c', 'p.category_id', '=', 'c.id')
            ->leftJoin('brands b', 'p.brand_id', '=', 'b.id')
            ->leftJoin('vendors v', 'p.vendor_id', '=', 'v.id');
    }

    private function applyProductFilters($queryBuilder, array $filters, array $excludeFilters = []): void
    {
        if (!in_array('status', $excludeFilters) && isset($filters['status'])) {
            $queryBuilder->where('p.status', $filters['status']);
        }

        if (!in_array('category', $excludeFilters) && isset($filters['category'])) {
            $queryBuilder->where('p.category_id', $filters['category']);
        }

        if (!in_array('brand', $excludeFilters) && isset($filters['brand'])) {
            $queryBuilder->where('p.brand_id', $filters['brand']);
        }

        if (isset($filters['min_price'])) {
            $queryBuilder->where('p.price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $queryBuilder->where('p.price', '<=', (float) $filters['max_price']);
        }

        if (isset($filters['in_stock']) && $filters['in_stock'] === 'true') {
            $queryBuilder->where(function($query) {
                $query->where('p.track_inventory', 0)
                      ->orWhere('p.inventory_quantity', '>', 0);
            });
        }

        if (isset($filters['featured']) && $filters['featured'] === 'true') {
            $queryBuilder->where('p.featured', 1);
        }

        if (isset($filters['vendor'])) {
            $queryBuilder->where('p.vendor_id', $filters['vendor']);
        }
    }

    private function applyProductSorting($queryBuilder, array $query): void
    {
        $sort = $query['sort'] ?? 'created_at';
        $direction = strtoupper($query['direction'] ?? 'DESC');

        switch ($sort) {
            case 'price':
                $queryBuilder->orderBy('p.price', $direction);
                break;
            case 'name':
                $queryBuilder->orderBy('p.name', $direction);
                break;
            case 'rating':
                $queryBuilder->orderBy('p.rating', $direction);
                break;
            case 'popularity':
                $queryBuilder->orderBy('p.total_sales', $direction);
                break;
            case 'newest':
                $queryBuilder->orderBy('p.created_at', 'DESC');
                break;
            default:
                $queryBuilder->orderBy('p.created_at', $direction);
        }
    }

    private function getProductImages(int $productId): array
    {
        return $this->db->table('product_images')
            ->where('product_id', $productId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order')
            ->get();
    }

    private function getProductVariants(int $productId): array
    {
        return $this->db->table('product_variants')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();
    }

    private function getProductReviews(int $productId, int $limit = 5): array
    {
        return $this->db->table('product_reviews pr')
            ->select([
                'pr.*',
                'u.first_name',
                'u.last_name',
                'u.avatar'
            ])
            ->join('users u', 'pr.user_id', '=', 'u.id')
            ->where('pr.product_id', $productId)
            ->where('pr.status', 'approved')
            ->orderBy('pr.created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    private function getRelatedProducts(int $productId, int $categoryId, int $limit = 4): array
    {
        $products = $this->db->table('products p')
            ->select(['p.*'])
            ->where('p.category_id', $categoryId)
            ->where('p.id', '!=', $productId)
            ->where('p.status', 'active')
            ->orderBy('p.total_sales', 'DESC')
            ->limit($limit)
            ->get();

        $transformedProducts = [];
        foreach ($products as $productData) {
            $product = Product::fromArray((array) $productData);
            $product->images = $this->getProductImages($product->id);
            $transformedProducts[] = $product->toArray();
        }

        return $transformedProducts;
    }

    private function buildCategoryTree(array $categories): array
    {
        $tree = [];
        $categoryMap = [];

        // Create category map
        foreach ($categories as $category) {
            $categoryMap[$category->id] = (array) $category;
            $categoryMap[$category->id]['children'] = [];
        }

        // Build tree structure
        foreach ($categoryMap as $category) {
            if ($category['parent_id'] === null) {
                $tree[] = $category;
            } else {
                if (isset($categoryMap[$category['parent_id']])) {
                    $categoryMap[$category['parent_id']]['children'][] = $category;
                }
            }
        }

        return $tree;
    }

    private function logSearchTerm(string $term, int $resultCount, ?int $userId = null): void
    {
        try {
            // Log search for analytics
            $normalizedTerm = strtolower(trim($term));
            
            // Update or insert search term
            $existing = $this->db->table('search_terms')
                ->where('normalized_term', $normalizedTerm)
                ->first();

            if ($existing) {
                $this->db->table('search_terms')
                    ->where('id', $existing->id)
                    ->update([
                        'search_count' => $existing->search_count + 1,
                        'result_count' => $resultCount,
                        'last_searched' => date('Y-m-d H:i:s')
                    ]);
            } else {
                $this->db->table('search_terms')->insert([
                    'term' => $term,
                    'normalized_term' => $normalizedTerm,
                    'search_count' => 1,
                    'result_count' => $resultCount,
                    'last_searched' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

        } catch (\Exception $e) {
            // Silently fail search logging
        }
    }
}