<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ProjectController extends Controller
{
    /**
     * Store a newly created project
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'blockchain_network' => 'required|string|in:ethereum,bsc,polygon,arbitrum,optimism,avalanche,fantom',
            'main_contract_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'project_type' => 'required|string|in:smart_contract,defi,nft,dao,bridge,dex,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = auth()->id();
        $data['status'] = 'active';

        try {
            $project = Project::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'network' => $project->blockchain_network,
                    'contractAddress' => $project->main_contract_address,
                    'status' => 'pending',
                    'riskLevel' => 'unknown',
                    'created_at' => $project->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's projects
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $projects = Project::where('user_id', auth()->id())
                ->with(['analyses' => function ($query) {
                    $query->latest()->limit(1);
                }])
                ->latest()
                ->get()
                ->map(function ($project) {
                    $latestAnalysis = $project->analyses->first();
                    
                    // Calculate issue counts from findings if available
                    $criticalIssues = 0;
                    $highIssues = 0;
                    $mediumIssues = 0;
                    $lowIssues = 0;
                    
                    if ($latestAnalysis && $latestAnalysis->findings) {
                        $findings = is_string($latestAnalysis->findings) ? json_decode($latestAnalysis->findings, true) : $latestAnalysis->findings;
                        if (is_array($findings)) {
                            foreach ($findings as $finding) {
                                if (isset($finding['severity'])) {
                                    switch (strtolower($finding['severity'])) {
                                        case 'critical':
                                            $criticalIssues++;
                                            break;
                                        case 'high':
                                            $highIssues++;
                                            break;
                                        case 'medium':
                                            $mediumIssues++;
                                            break;
                                        case 'low':
                                        case 'info':
                                            $lowIssues++;
                                            break;
                                    }
                                }
                            }
                        }
                    }

                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'network' => $project->blockchain_network,
                        'blockchain_network' => $project->blockchain_network, // For compatibility
                        'contractAddress' => $project->main_contract_address,
                        'main_contract_address' => $project->main_contract_address, // For compatibility
                        'riskLevel' => $project->risk_level ?: 'low',
                        'findings' => $latestAnalysis ? $latestAnalysis->findings_count : 0,
                        'sentiment' => $latestAnalysis ? ($latestAnalysis->sentiment_score ?: 0.5) : 0.5,
                        'lastAnalyzed' => $latestAnalysis ? $latestAnalysis->created_at->diffForHumans() : 'Never',
                        'status' => $latestAnalysis ? $latestAnalysis->status : 'pending',
                        'criticalIssues' => $criticalIssues,
                        'highIssues' => $highIssues,
                        'mediumIssues' => $mediumIssues,
                        'lowIssues' => $lowIssues,
                        'lastAnalysis' => $latestAnalysis ? $latestAnalysis->created_at->diffForHumans() : 'Never',
                        'progress' => $latestAnalysis && $latestAnalysis->status === 'processing' ? ($latestAnalysis->progress ?? 0) : 0,
                        'created_at' => $project->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'projects' => $projects
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch projects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific project
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $project = Project::where('user_id', auth()->id())
                ->where('id', $id)
                ->with(['analyses'])
                ->firstOrFail();

            $latestAnalysis = $project->analyses()->latest()->first();
            
            $projectData = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'network' => $project->blockchain_network,
                'contractAddress' => $project->main_contract_address,
                'riskLevel' => $project->risk_level ?: 'unknown',
                'status' => $project->status,
                'analyses' => $project->analyses->map(function ($analysis) {
                    return [
                        'id' => $analysis->id,
                        'status' => $analysis->status,
                        'findings_count' => $analysis->findings_count,
                        'sentiment_score' => $analysis->sentiment_score,
                        'created_at' => $analysis->created_at,
                        'findings' => $analysis->findings,
                    ];
                }),
                'created_at' => $project->created_at,
            ];

            return response()->json([
                'success' => true,
                'project' => $projectData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found'
            ], 404);
        }
    }
}