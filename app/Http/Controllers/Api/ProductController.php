<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * API produk untuk aplikasi kasir mobile (minggu 10).
     */
    public function index(): AnonymousResourceCollection
    {
        $products = Product::active()->with('category')->orderBy('name')->paginate(20);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('category'));
    }
}
