<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SubscriptionPlanService
{
    /**
     * Get all available subscription plans
     */
    public function getAllPlans(): Collection
    {
        return collect(config('billing.plans'));
    }

    /**
     * Get a specific plan by name
     */
    public function getPlan(string $planName): ?array
    {
        return config("billing.plans.{$planName}");
    }

    /**
     * Get plan features with formatted display
     */
    public function getPlanFeatures(string $planName): array
    {
        $plan = $this->getPlan($planName);
        
        if (!$plan) {
            return [];
        }

        $features = $plan['features'];
        
        return [
            'Analysis per month' => $this->formatLimit($features['analysis_limit']),
            'API calls per month' => $this->formatLimit($features['api_calls_limit']),
            'AI tokens per month' => $this->formatLimit($features['tokens_limit']),
            'Projects' => $this->formatLimit($features['projects_limit']),
            'Support' => ucfirst($features['support']),
            'Features' => $this->getFeaturesList($features),
        ];
    }

    /**
     * Get formatted features list
     */
    private function getFeaturesList(array $features): array
    {
        $featuresList = [];
        
        if ($features['vulnerability_scanning']) {
            $featuresList[] = 'Vulnerability Scanning';
        }
        
        if ($features['code_analysis']) {
            $featuresList[] = 'Smart Contract Analysis';
        }
        
        if (isset($features['basic_reporting']) && $features['basic_reporting']) {
            $featuresList[] = 'Basic Reporting';
        }
        
        if (isset($features['advanced_reporting']) && $features['advanced_reporting']) {
            $featuresList[] = 'Advanced Reporting';
        }
        
        if (isset($features['enterprise_reporting']) && $features['enterprise_reporting']) {
            $featuresList[] = 'Enterprise Reporting';
        }
        
        if (isset($features['team_collaboration']) && $features['team_collaboration']) {
            $featuresList[] = 'Team Collaboration';
        }
        
        if (isset($features['custom_integrations']) && $features['custom_integrations']) {
            $featuresList[] = 'Custom Integrations';
        }
        
        if (isset($features['webhook_support']) && $features['webhook_support']) {
            $featuresList[] = 'Webhook Support';
        }
        
        if (isset($features['sso_integration']) && $features['sso_integration']) {
            $featuresList[] = 'SSO Integration';
        }
        
        if (isset($features['compliance_reporting']) && $features['compliance_reporting']) {
            $featuresList[] = 'Compliance Reporting';
        }
        
        if (isset($features['priority_processing']) && $features['priority_processing']) {
            $featuresList[] = 'Priority Processing';
        }
        
        return $featuresList;
    }

    /**
     * Format limit values for display
     */
    private function formatLimit($limit): string
    {
        if ($limit === -1) {
            return 'Unlimited';
        }
        
        if ($limit >= 1000000) {
            return number_format($limit / 1000000, 1) . 'M';
        }
        
        if ($limit >= 1000) {
            return number_format($limit / 1000, 1) . 'K';
        }
        
        return number_format($limit);
    }

    /**
     * Get plan pricing information
     */
    public function getPlanPricing(string $planName): array
    {
        $plan = $this->getPlan($planName);
        
        if (!$plan) {
            return [];
        }

        return [
            'monthly' => [
                'price' => $plan['monthly_price'],
                'stripe_price_id' => $plan['stripe_monthly_price_id'],
                'formatted_price' => '$' . $plan['monthly_price'] . '/month',
            ],
            'yearly' => [
                'price' => $plan['yearly_price'],
                'stripe_price_id' => $plan['stripe_yearly_price_id'],
                'formatted_price' => '$' . $plan['yearly_price'] . '/year',
                'monthly_equivalent' => round($plan['yearly_price'] / 12, 2),
                'savings' => ($plan['monthly_price'] * 12) - $plan['yearly_price'],
            ],
        ];
    }

    /**
     * Get overage rates for a plan
     */
    public function getOverageRates(string $planName): array
    {
        $plan = $this->getPlan($planName);
        
        return $plan['overage_rates'] ?? [];
    }

    /**
     * Calculate overage cost
     */
    public function calculateOverageCost(string $planName, array $usage, array $limits): float
    {
        $overageRates = $this->getOverageRates($planName);
        $totalCost = 0;

        foreach ($usage as $type => $quantity) {
            if (isset($limits[$type . '_limit']) && $quantity > $limits[$type . '_limit']) {
                $overage = $quantity - $limits[$type . '_limit'];
                $rate = $overageRates[$type] ?? 0;
                $totalCost += $overage * $rate;
            }
        }

        return $totalCost;
    }

    /**
     * Get recommended plan based on usage
     */
    public function getRecommendedPlan(array $usage): string
    {
        $plans = $this->getAllPlans();
        
        foreach (['starter', 'professional', 'enterprise'] as $planName) {
            $plan = $plans[$planName];
            $features = $plan['features'];
            
            $withinLimits = true;
            
            foreach ($usage as $type => $quantity) {
                $limitKey = $type . '_limit';
                if (isset($features[$limitKey]) && $features[$limitKey] !== -1 && $quantity > $features[$limitKey]) {
                    $withinLimits = false;
                    break;
                }
            }
            
            if ($withinLimits) {
                return $planName;
            }
        }
        
        return 'enterprise';
    }

    /**
     * Check if user can perform action based on plan limits
     */
    public function canPerformAction(string $planName, string $action, array $currentUsage): bool
    {
        $plan = $this->getPlan($planName);
        
        if (!$plan) {
            return false;
        }

        $features = $plan['features'];
        $limitKey = $action . '_limit';
        
        if (!isset($features[$limitKey])) {
            return true; // No limit defined
        }
        
        $limit = $features[$limitKey];
        
        if ($limit === -1) {
            return true; // Unlimited
        }
        
        $currentCount = $currentUsage[$action] ?? 0;
        
        return $currentCount < $limit;
    }

    /**
     * Get usage percentage for a plan
     */
    public function getUsagePercentage(string $planName, array $currentUsage): array
    {
        $plan = $this->getPlan($planName);
        
        if (!$plan) {
            return [];
        }

        $features = $plan['features'];
        $percentages = [];
        
        foreach (['analysis', 'api_calls', 'tokens'] as $type) {
            $limitKey = $type . '_limit';
            $limit = $features[$limitKey] ?? 0;
            $current = $currentUsage[$type] ?? 0;
            
            if ($limit === -1) {
                $percentages[$type] = 0; // Unlimited
            } elseif ($limit > 0) {
                $percentages[$type] = min(100, ($current / $limit) * 100);
            } else {
                $percentages[$type] = 100;
            }
        }
        
        return $percentages;
    }

    /**
     * Get free tier limits
     */
    public function getFreeTierLimits(): array
    {
        return config('billing.free_tier');
    }

    /**
     * Check if user is on free tier
     */
    public function isFreeTier(?string $planName): bool
    {
        return empty($planName);
    }
}