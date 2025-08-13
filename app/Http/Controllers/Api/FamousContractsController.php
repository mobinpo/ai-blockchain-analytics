<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class FamousContractsController extends Controller
{
    /**
     * Display a listing of famous contracts.
     */
    public function index(): JsonResponse
    {
        try {
            $contracts = DB::table('famous_contracts')
                ->orderBy('risk_score')
                ->get()
                ->map(function ($contract) {
                    // Parse JSON fields
                    $contract->security_features = json_decode($contract->security_features, true);
                    $contract->vulnerabilities = json_decode($contract->vulnerabilities, true);
                    $contract->audit_firms = json_decode($contract->audit_firms, true);
                    $contract->exploit_details = json_decode($contract->exploit_details, true);
                    $contract->metadata = json_decode($contract->metadata, true);
                    
                    return $contract;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'contracts' => $contracts,
                    'total_count' => $contracts->count(),
                    'summary' => [
                        'total_tvl' => $contracts->sum('total_value_locked'),
                        'average_risk_score' => round($contracts->avg('risk_score'), 1),
                        'verified_count' => $contracts->where('is_verified', true)->count(),
                        'exploited_count' => $contracts->where('risk_score', '>', 80)->count(),
                    ]
                ],
                'message' => 'Famous contracts retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve famous contracts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified famous contract.
     */
    public function show(string $address): JsonResponse
    {
        try {
            $contract = DB::table('famous_contracts')
                ->where('address', $address)
                ->first();

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contract not found'
                ], 404);
            }

            // Parse JSON fields
            $contract->security_features = json_decode($contract->security_features, true);
            $contract->vulnerabilities = json_decode($contract->vulnerabilities, true);
            $contract->audit_firms = json_decode($contract->audit_firms, true);
            $contract->exploit_details = json_decode($contract->exploit_details, true);
            $contract->metadata = json_decode($contract->metadata, true);

            return response()->json([
                'success' => true,
                'data' => $contract,
                'message' => 'Contract details retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contract details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contracts by risk level.
     */
    public function byRiskLevel(string $level): JsonResponse
    {
        $riskRanges = [
            'low' => [0, 30],
            'medium' => [31, 60],
            'high' => [61, 100],
        ];

        if (!isset($riskRanges[$level])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid risk level. Use: low, medium, or high'
            ], 400);
        }

        try {
            [$min, $max] = $riskRanges[$level];
            
            $contracts = DB::table('famous_contracts')
                ->whereBetween('risk_score', [$min, $max])
                ->orderBy('risk_score')
                ->get()
                ->map(function ($contract) {
                    // Parse JSON fields
                    $contract->security_features = json_decode($contract->security_features, true);
                    $contract->vulnerabilities = json_decode($contract->vulnerabilities, true);
                    $contract->audit_firms = json_decode($contract->audit_firms, true);
                    $contract->exploit_details = json_decode($contract->exploit_details, true);
                    $contract->metadata = json_decode($contract->metadata, true);
                    
                    return $contract;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'risk_level' => $level,
                    'risk_range' => "{$min}-{$max}",
                    'contracts' => $contracts,
                    'count' => $contracts->count()
                ],
                'message' => "Contracts with {$level} risk retrieved successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contracts by risk level',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get exploited contracts.
     */
    public function exploited(): JsonResponse
    {
        try {
            $contracts = DB::table('famous_contracts')
                ->whereNotNull('exploit_details')
                ->orderBy('risk_score', 'desc')
                ->get()
                ->map(function ($contract) {
                    // Parse JSON fields
                    $contract->security_features = json_decode($contract->security_features, true);
                    $contract->vulnerabilities = json_decode($contract->vulnerabilities, true);
                    $contract->audit_firms = json_decode($contract->audit_firms, true);
                    $contract->exploit_details = json_decode($contract->exploit_details, true);
                    $contract->metadata = json_decode($contract->metadata, true);
                    
                    return $contract;
                });

            $totalLoss = $contracts->sum(function ($contract) {
                return $contract->exploit_details['loss_amount_usd'] ?? 0;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'exploited_contracts' => $contracts,
                    'total_exploited' => $contracts->count(),
                    'total_loss_usd' => $totalLoss,
                    'average_loss' => $contracts->count() > 0 ? round($totalLoss / $contracts->count(), 2) : 0
                ],
                'message' => 'Exploited contracts retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exploited contracts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
