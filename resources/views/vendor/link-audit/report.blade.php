<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Audit Report - {{ date('Y-m-d H:i:s') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .diff-before { background-color: #fee; color: #c53030; }
        .diff-after { background-color: #efe; color: #38a169; }
        .confidence-high { background-color: #22c55e; }
        .confidence-medium { background-color: #eab308; }
        .confidence-low { background-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-link text-blue-600 mr-3"></i>
                            Link Audit Report
                        </h1>
                        <p class="text-gray-600 mt-1">Generated on {{ $timestamp }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Base URL</div>
                        <div class="font-mono text-sm">{{ $config['base_url'] }}</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Summary -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-route text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">Total Routes</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $routes_count }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">Total Issues</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $summary['total_issues'] }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-code text-yellow-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">Static Issues</div>
                            <div class="text-2xl font-bold text-gray-900">{{ count($static_findings) }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-browser text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-500">Browser Issues</div>
                            <div class="text-2xl font-bold text-gray-900">{{ count($browser_findings) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confidence Distribution -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Fix Confidence Distribution</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-2">
                                <span class="text-white font-bold text-xl">{{ $summary['high_confidence_fixes'] }}</span>
                            </div>
                            <div class="text-sm font-medium">High Confidence</div>
                            <div class="text-xs text-gray-500">≥ 90%</div>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-yellow-500 rounded-full flex items-center justify-center mx-auto mb-2">
                                <span class="text-white font-bold text-xl">{{ $summary['medium_confidence_fixes'] }}</span>
                            </div>
                            <div class="text-sm font-medium">Medium Confidence</div>
                            <div class="text-xs text-gray-500">70-89%</div>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-2">
                                <span class="text-white font-bold text-xl">{{ $summary['low_confidence_fixes'] }}</span>
                            </div>
                            <div class="text-sm font-medium">Low Confidence</div>
                            <div class="text-xs text-gray-500">< 70%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div x-data="{ activeTab: 'static' }" class="mb-8">
                <nav class="flex space-x-8 border-b border-gray-200">
                    <button 
                        @click="activeTab = 'static'" 
                        :class="{ 'border-blue-500 text-blue-600': activeTab === 'static', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'static' }"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        <i class="fas fa-code mr-2"></i>
                        Static Analysis ({{ count($static_findings) }})
                    </button>
                    <button 
                        @click="activeTab = 'browser'" 
                        :class="{ 'border-blue-500 text-blue-600': activeTab === 'browser', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'browser' }"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        <i class="fas fa-browser mr-2"></i>
                        Browser Results ({{ count($browser_findings) }})
                    </button>
                    <button 
                        @click="activeTab = 'suggestions'" 
                        :class="{ 'border-blue-500 text-blue-600': activeTab === 'suggestions', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'suggestions' }"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                    >
                        <i class="fas fa-lightbulb mr-2"></i>
                        Suggestions ({{ count($suggestions) }})
                    </button>
                </nav>

                <!-- Static Analysis Tab -->
                <div x-show="activeTab === 'static'" class="mt-6">
                    @if(empty($static_findings))
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                            <i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>
                            <h3 class="text-lg font-medium text-green-800">No Static Issues Found</h3>
                            <p class="text-green-600">All Blade templates appear to have valid links and routes.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($static_findings as $finding)
                                <div class="bg-white rounded-lg shadow border-l-4 border-red-400">
                                    <div class="p-6">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">
                                                        {{ ucfirst(str_replace('_', ' ', $finding['type'])) }}
                                                    </span>
                                                    <span class="ml-2 text-sm text-gray-500">{{ $finding['file'] }}:{{ $finding['line'] }}</span>
                                                </div>
                                                <p class="text-gray-900 mb-3">{{ $finding['issue'] }}</p>
                                                
                                                <div class="code-block mb-4">
                                                    <div class="text-xs text-gray-400 mb-2">Line {{ $finding['line'] }}:</div>
                                                    <code>{{ $finding['content'] }}</code>
                                                </div>

                                                @if(isset($finding['suggestion']) && $finding['suggestion'])
                                                    <div class="bg-blue-50 border border-blue-200 rounded p-4">
                                                        <h4 class="font-medium text-blue-900 mb-2">
                                                            <i class="fas fa-lightbulb mr-1"></i>
                                                            Suggested Fix
                                                        </h4>
                                                        <div class="text-sm">
                                                            <div class="mb-2">
                                                                <strong>Route:</strong> {{ $finding['suggestion']['name'] ?? 'N/A' }} 
                                                                <span class="text-gray-500">({{ $finding['suggestion']['uri'] ?? 'N/A' }})</span>
                                                            </div>
                                                            <div class="mb-2">
                                                                <strong>Confidence:</strong> 
                                                                <span class="px-2 py-1 text-xs font-medium rounded 
                                                                    @if(($finding['suggestion']['similarity_score'] ?? 0) >= 0.9) bg-green-100 text-green-800
                                                                    @elseif(($finding['suggestion']['similarity_score'] ?? 0) >= 0.7) bg-yellow-100 text-yellow-800
                                                                    @else bg-red-100 text-red-800 @endif">
                                                                    {{ round(($finding['suggestion']['similarity_score'] ?? 0) * 100) }}%
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Browser Results Tab -->
                <div x-show="activeTab === 'browser'" class="mt-6">
                    @if(empty($browser_findings))
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                            <i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>
                            <h3 class="text-lg font-medium text-green-800">No Browser Issues Found</h3>
                            <p class="text-green-600">All tested pages loaded successfully with working links.</p>
                        </div>
                    @else
                        <div class="space-y-6">
                            @foreach($browser_findings as $url => $finding)
                                <div class="bg-white rounded-lg shadow">
                                    <div class="px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900">{{ $url }}</h3>
                                        <div class="flex items-center mt-1">
                                            <span class="px-2 py-1 text-xs font-medium rounded
                                                @if($finding['status'] === 'success') bg-green-100 text-green-800
                                                @elseif($finding['status'] === 'error') bg-red-100 text-red-800
                                                @else bg-yellow-100 text-yellow-800 @endif">
                                                {{ ucfirst($finding['status']) }}
                                            </span>
                                            <span class="ml-2 text-sm text-gray-500">{{ $finding['timestamp'] }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-6">
                                        @if(isset($finding['broken_links']) && !empty($finding['broken_links']))
                                            <h4 class="font-medium text-gray-900 mb-3">Broken Links ({{ count($finding['broken_links']) }})</h4>
                                            <div class="space-y-3">
                                                @foreach($finding['broken_links'] as $brokenLink)
                                                    <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1">
                                                                <div class="font-medium text-red-900">{{ $brokenLink['href'] }}</div>
                                                                @if(!empty($brokenLink['text']))
                                                                    <div class="text-sm text-red-700 mt-1">Text: "{{ $brokenLink['text'] }}"</div>
                                                                @endif
                                                                <div class="text-sm text-red-600 mt-1">
                                                                    Status: {{ $brokenLink['status'] }}
                                                                    @if(isset($brokenLink['error']))
                                                                        - {{ $brokenLink['error'] }}
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">
                                                                {{ $brokenLink['status'] }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if(isset($finding['errors']) && !empty($finding['errors']))
                                            <h4 class="font-medium text-gray-900 mb-3 mt-6">JavaScript Errors</h4>
                                            <div class="space-y-2">
                                                @foreach($finding['errors'] as $error)
                                                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
                                                        <code class="text-sm text-yellow-800">{{ $error }}</code>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Suggestions Tab -->
                <div x-show="activeTab === 'suggestions'" class="mt-6">
                    @if(empty($suggestions))
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center">
                            <i class="fas fa-lightbulb text-blue-500 text-3xl mb-3"></i>
                            <h3 class="text-lg font-medium text-blue-800">No Suggestions Available</h3>
                            <p class="text-blue-600">No actionable fix suggestions were generated.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($suggestions as $suggestion)
                                <div class="bg-white rounded-lg shadow border-l-4 
                                    @if($suggestion['confidence'] >= 0.9) border-green-400
                                    @elseif($suggestion['confidence'] >= 0.7) border-yellow-400
                                    @else border-red-400 @endif">
                                    <div class="p-6">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <span class="px-2 py-1 text-xs font-medium rounded
                                                        @if($suggestion['confidence'] >= 0.9) bg-green-100 text-green-800
                                                        @elseif($suggestion['confidence'] >= 0.7) bg-yellow-100 text-yellow-800
                                                        @else bg-red-100 text-red-800 @endif">
                                                        {{ ucfirst(str_replace('_', ' ', $suggestion['type'])) }}
                                                    </span>
                                                    <span class="ml-2 text-sm text-gray-500">
                                                        @if(isset($suggestion['file']))
                                                            {{ $suggestion['file'] }}:{{ $suggestion['line'] ?? 'N/A' }}
                                                        @endif
                                                    </span>
                                                </div>
                                                <p class="text-gray-900 mb-3">{{ $suggestion['issue'] ?? 'Fix suggested' }}</p>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm text-gray-500">Confidence</div>
                                                <div class="text-lg font-bold 
                                                    @if($suggestion['confidence'] >= 0.9) text-green-600
                                                    @elseif($suggestion['confidence'] >= 0.7) text-yellow-600
                                                    @else text-red-600 @endif">
                                                    {{ round($suggestion['confidence'] * 100) }}%
                                                </div>
                                            </div>
                                        </div>

                                        @if(!empty($suggestion['before']) && !empty($suggestion['after']))
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Before</h4>
                                                    <div class="code-block diff-before">
                                                        <code>{{ $suggestion['before'] }}</code>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-700 mb-2">After</h4>
                                                    <div class="code-block diff-after">
                                                        <code>{{ $suggestion['after'] }}</code>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4 flex items-center space-x-3">
                                                <button 
                                                    onclick="copyPatch(this)" 
                                                    data-before="{{ addslashes($suggestion['before']) }}" 
                                                    data-after="{{ addslashes($suggestion['after']) }}"
                                                    class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition"
                                                >
                                                    <i class="fas fa-copy mr-1"></i>
                                                    Copy Patch
                                                </button>
                                            </div>
                                        @endif

                                        @if(isset($suggestion['route_suggestion']))
                                            <div class="mt-4 bg-gray-50 border border-gray-200 rounded p-4">
                                                <h4 class="font-medium text-gray-900 mb-2">Suggested Route</h4>
                                                <div class="text-sm space-y-1">
                                                    <div><strong>Name:</strong> {{ $suggestion['route_suggestion']['name'] ?? 'N/A' }}</div>
                                                    <div><strong>URI:</strong> {{ $suggestion['route_suggestion']['uri'] ?? 'N/A' }}</div>
                                                    <div><strong>Methods:</strong> {{ implode(', ', $suggestion['route_suggestion']['methods'] ?? []) }}</div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyPatch(button) {
            const before = button.getAttribute('data-before');
            const after = button.getAttribute('data-after');
            const patch = `- ${before}\n+ ${after}`;
            
            navigator.clipboard.writeText(patch).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check mr-1"></i>Copied!';
                button.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                button.classList.add('bg-green-500');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('bg-green-500');
                    button.classList.add('bg-blue-500', 'hover:bg-blue-600');
                }, 2000);
            });
        }

        // Auto-refresh every 30 seconds if in development
        @if(config('app.debug'))
            setInterval(() => {
                window.location.reload();
            }, 30000);
        @endif
    </script>
</body>
</html>