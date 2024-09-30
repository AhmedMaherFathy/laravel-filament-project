<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Brand;
use App\Models\Product;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin'),
        ]);

        Category::create([
            'is_active' => true,
            'name' => 'Phone',
            'slug' => 'phone',
        ]);

        Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
        ]);

        Product::create([
            'name' => 'iPhone 15',
            'slug' => 'iphone-15',
            'category_id' => 1,
            'brand_id' => 1,
            'is_active' => true,
            'is_featured' => true,
            'in_stock' => true,
            'on_sale' => false,
            'price' => 14000,
        ]);
    }
}
