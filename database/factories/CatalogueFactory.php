<?php

namespace Database\Factories;

use App\Models\Catalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Catalogue>
 */
class CatalogueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'file_path' => 'catalogues/'.fake()->uuid().'.pdf',
            'file_name' => 'catalogue.pdf',
            'file_size' => 1024,
            'is_published' => true,
        ];
    }
}
