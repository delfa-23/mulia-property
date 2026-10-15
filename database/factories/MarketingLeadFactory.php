<?php

namespace Database\Factories;

use App\Models\MarketingLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingLead>
 */
class MarketingLeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'date_received' => fake()->date(),
            'phone' => fake()->phoneNumber(),
            'domicile' => fake()->city(),
            'category' => fake()->randomElement(MarketingLead::CATEGORIES),
            'source' => fake()->randomElement(MarketingLead::SOURCES),
            'last_progress' => fake()->sentence(),
            'last_contact_at' => fake()->optional()->date(),
        ];
    }
}
