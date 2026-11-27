<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'product_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'product_code' => 'PRD-' . fake()->unique()->numerify('#####'),
            'price' => fake()->randomElement([3500, 5000, 12000, 18500, 25000, 74000]),
            'stock' => fake()->numberBetween(10, 100),
            'minimum_stock' => 10,
            'unit' => fake()->randomElement(['pcs', 'pack', 'bottle', 'box', 'kg', 'liter']),
            'image_path' => null,
            'is_active' => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'stock' => 2,
            'minimum_stock' => 10,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'stock' => 0,
            'minimum_stock' => 10,
        ]);
    }
}
