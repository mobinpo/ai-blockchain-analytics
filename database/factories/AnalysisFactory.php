<?php

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalysisFactory extends Factory
{
    protected $model = Analysis::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'engine' => $this->faker->randomElement(['openai', 'mythril', 'slither']),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'analysis_type' => $this->faker->randomElement(['security', 'gas', 'code_quality']),
            'target_type' => 'contract',
            'target_address' => $this->faker->optional()->ethereumAddress(),
            'openai_model' => $this->faker->optional()->randomElement(['gpt-4', 'gpt-3.5-turbo']),
            'token_limit' => $this->faker->optional()->numberBetween(500, 4000),
            'temperature' => $this->faker->optional()->randomFloat(2, 0, 2),
            'tokens_used' => $this->faker->optional()->numberBetween(100, 2000),
            'tokens_streamed' => $this->faker->optional()->numberBetween(50, 1500),
            'priority' => $this->faker->numberBetween(1, 10),
            'findings_count' => $this->faker->numberBetween(0, 10),
            'critical_findings_count' => $this->faker->numberBetween(0, 3),
            'high_findings_count' => $this->faker->numberBetween(0, 5),
            'risk_score' => $this->faker->optional()->numberBetween(0, 100),
            'sentiment_score' => $this->faker->optional()->randomFloat(2, -1, 1),
            'triggered_by' => $this->faker->randomElement(['manual', 'api', 'scheduled']),
            'triggered_by_user_id' => User::factory(),
            'payload' => [
                'code' => $this->generateSampleContract(),
                'options' => []
            ],
            'metadata' => [
                'version' => '1.0',
                'environment' => 'test'
            ],
            'verified' => $this->faker->boolean(20), // 20% chance of being verified
            'archived' => $this->faker->boolean(10), // 10% chance of being archived
        ];
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'started_at' => $this->faker->dateTimeBetween('-1 hour', '-30 minutes'),
                'completed_at' => $this->faker->dateTimeBetween('-30 minutes', 'now'),
                'streaming_started_at' => $this->faker->dateTimeBetween('-1 hour', '-45 minutes'),
                'streaming_completed_at' => $this->faker->dateTimeBetween('-35 minutes', '-30 minutes'),
                'duration_seconds' => $this->faker->numberBetween(30, 1800),
                'stream_duration_ms' => $this->faker->numberBetween(5000, 60000),
                'tokens_per_second' => $this->faker->randomFloat(2, 1, 20),
                'structured_result' => $this->generateStructuredResult(),
                'raw_openai_response' => $this->generateAnalysisResponse()
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'started_at' => $this->faker->dateTimeBetween('-1 hour', '-30 minutes'),
                'failed_at' => $this->faker->dateTimeBetween('-30 minutes', 'now'),
                'error_message' => $this->faker->randomElement([
                    'OpenAI API rate limit exceeded',
                    'Invalid contract code',
                    'Analysis timeout',
                    'Insufficient tokens'
                ]),
                'error_details' => [
                    'code' => $this->faker->randomElement(['RATE_LIMIT', 'TIMEOUT', 'INVALID_INPUT']),
                    'attempts' => $this->faker->numberBetween(1, 3)
                ]
            ];
        });
    }

    public function processing(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'processing',
                'started_at' => $this->faker->dateTimeBetween('-30 minutes', 'now'),
                'streaming_started_at' => $this->faker->dateTimeBetween('-25 minutes', '-5 minutes'),
                'tokens_streamed' => $this->faker->numberBetween(50, 800),
                'job_id' => $this->faker->uuid()
            ];
        });
    }

    public function openai(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'engine' => 'openai',
                'openai_model' => $this->faker->randomElement(['gpt-4', 'gpt-3.5-turbo']),
                'token_limit' => $this->faker->numberBetween(1000, 4000),
                'temperature' => $this->faker->randomFloat(2, 0.1, 1.5),
                'job_id' => $this->faker->uuid()
            ];
        });
    }

    public function security(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'analysis_type' => 'security',
                'findings_count' => $this->faker->numberBetween(1, 8),
                'critical_findings_count' => $this->faker->numberBetween(0, 2),
                'high_findings_count' => $this->faker->numberBetween(0, 3),
                'risk_score' => $this->faker->numberBetween(20, 90)
            ];
        });
    }

    private function generateSampleContract(): string
    {
        $contracts = [
            'pragma solidity ^0.8.0; contract Sample { uint256 public value; }',
            'contract Token { mapping(address => uint256) balances; }',
            'contract Vault { function withdraw() public { /* code */ } }'
        ];

        return $this->faker->randomElement($contracts);
    }

    private function generateStructuredResult(): array
    {
        $severities = ['low', 'medium', 'high', 'critical'];
        $findingsCount = $this->faker->numberBetween(0, 5);
        $findings = [];

        for ($i = 0; $i < $findingsCount; $i++) {
            $findings[] = [
                'severity' => $this->faker->randomElement($severities),
                'title' => $this->faker->sentence(4),
                'description' => $this->faker->paragraph(2)
            ];
        }

        return [
            'summary' => $this->faker->paragraph(3),
            'findings' => $findings,
            'recommendations' => [
                $this->faker->sentence(8),
                $this->faker->sentence(6),
                $this->faker->sentence(10)
            ],
            'risk_score' => $this->faker->numberBetween(0, 100)
        ];
    }

    private function generateAnalysisResponse(): string
    {
        return "Security Analysis Report\n\n" .
               "The smart contract has been analyzed for potential vulnerabilities.\n\n" .
               "Findings:\n" .
               "- " . $this->faker->sentence(10) . "\n" .
               "- " . $this->faker->sentence(8) . "\n\n" .
               "Recommendations:\n" .
               "- " . $this->faker->sentence(12) . "\n" .
               "- " . $this->faker->sentence(9) . "\n\n" .
               "Risk Assessment: " . $this->faker->randomElement(['Low', 'Medium', 'High']);
    }
}