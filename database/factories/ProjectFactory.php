<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true) . ' Project',
            'description' => $this->faker->optional()->paragraph(2),
            'blockchain_network' => $this->faker->randomElement(['ethereum', 'polygon', 'bsc', 'arbitrum']),
            'contract_address' => $this->faker->optional()->regexify('0x[a-fA-F0-9]{40}'),
            'contract_type' => $this->faker->randomElement(['erc20', 'erc721', 'erc1155', 'defi', 'dao', 'other']),
            'project_url' => $this->faker->optional()->url(),
            'github_repo' => $this->faker->optional()->url(),
            'documentation_url' => $this->faker->optional()->url(),
            'is_public' => $this->faker->boolean(70), // 70% chance of being public
            'settings' => [
                'auto_scan' => $this->faker->boolean(),
                'notifications' => $this->faker->boolean(80),
                'scan_frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly'])
            ],
            'tags' => $this->faker->optional()->randomElements(['defi', 'nft', 'dao', 'gaming', 'metaverse'], 2),
            'status' => $this->faker->randomElement(['active', 'paused', 'archived']),
        ];
    }

    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'is_public' => true
            ];
        });
    }

    public function withContract(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'contract_address' => $this->faker->regexify('0x[a-fA-F0-9]{40}'),
                'blockchain_network' => $this->faker->randomElement(['ethereum', 'polygon', 'bsc']),
                'contract_type' => $this->faker->randomElement(['erc20', 'erc721', 'defi'])
            ];
        });
    }

    public function defi(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'contract_type' => 'defi',
                'tags' => ['defi', 'yield-farming'],
                'name' => $this->faker->company() . ' DeFi Protocol'
            ];
        });
    }

    public function nft(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'contract_type' => 'erc721',
                'tags' => ['nft', 'collectibles'],
                'name' => $this->faker->words(2, true) . ' NFT Collection'
            ];
        });
    }
}