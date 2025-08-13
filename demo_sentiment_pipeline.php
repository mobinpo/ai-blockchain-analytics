<?php

/**
 * Sentiment Pipeline Demo
 * 
 * This script demonstrates the Text → Google Cloud NLP → Daily Aggregates pipeline
 * without requiring database connectivity.
 */

echo "🚀 Sentiment Pipeline Demo\n";
echo "==========================\n\n";

// Sample texts from the JSON file
$sampleTexts = [
    [
        "text" => "Bitcoin is showing incredible growth potential this month. The technology behind blockchain continues to amaze investors worldwide.",
        "metadata" => [
            "platform" => "twitter",
            "category" => "crypto",
            "engagement" => 150,
            "timestamp" => "2024-01-15T10:30:00Z"
        ]
    ],
    [
        "text" => "I'm really concerned about the recent market volatility. These wild price swings make me nervous about investing more.",
        "metadata" => [
            "platform" => "reddit",
            "category" => "investment",
            "engagement" => 45,
            "timestamp" => "2024-01-15T11:15:00Z"
        ]
    ],
    [
        "text" => "Smart contracts are revolutionizing how we think about automated agreements. This technology will transform multiple industries.",
        "metadata" => [
            "platform" => "telegram",
            "category" => "technology",
            "engagement" => 89,
            "timestamp" => "2024-01-15T12:00:00Z"
        ]
    ],
    [
        "text" => "Market manipulation is becoming a serious issue. Regulatory clarity is desperately needed to protect retail investors.",
        "metadata" => [
            "platform" => "reddit",
            "category" => "regulation",
            "engagement" => 75,
            "timestamp" => "2024-01-15T14:45:00Z"
        ]
    ]
];

echo "📝 Step 1: Text Preprocessing\n";
echo "-----------------------------\n";
$processedTexts = [];
foreach ($sampleTexts as $index => $item) {
    $text = trim(preg_replace('/https?:\/\/[^\s]+/', '', $item['text']));
    $text = preg_replace('/\s+/', ' ', $text);
    
    $processedTexts[] = [
        'text' => $text,
        'metadata' => $item['metadata'],
        'word_count' => str_word_count($text),
        'char_count' => strlen($text)
    ];
    
    echo "Text " . ($index + 1) . ": " . substr($text, 0, 60) . "...\n";
    echo "  Platform: {$item['metadata']['platform']}\n";
    echo "  Category: {$item['metadata']['category']}\n";
    echo "  Engagement: {$item['metadata']['engagement']}\n";
    echo "  Words: " . str_word_count($text) . " | Chars: " . strlen($text) . "\n\n";
}

echo "🧠 Step 2: Google Cloud NLP Simulation\n";
echo "---------------------------------------\n";
$sentimentResults = [];
foreach ($processedTexts as $index => $item) {
    // Simulate sentiment analysis (replace with actual Google NLP in production)
    $text = strtolower($item['text']);
    
    // Simple keyword-based sentiment simulation
    $positiveWords = ['amazing', 'incredible', 'growth', 'potential', 'revolutionizing', 'transform', 'opportunities'];
    $negativeWords = ['concerned', 'nervous', 'volatility', 'manipulation', 'issue', 'problem'];
    
    $positiveCount = 0;
    $negativeCount = 0;
    
    foreach ($positiveWords as $word) {
        if (strpos($text, $word) !== false) $positiveCount++;
    }
    
    foreach ($negativeWords as $word) {
        if (strpos($text, $word) !== false) $negativeCount++;
    }
    
    // Calculate simulated sentiment score (-1 to 1)
    $score = ($positiveCount - $negativeCount) / max(1, $positiveCount + $negativeCount + 1);
    $magnitude = min(1.0, ($positiveCount + $negativeCount) / 3);
    
    $category = 'neutral';
    if ($magnitude > 0.1) {
        if ($score > 0.6) $category = 'very_positive';
        elseif ($score > 0.2) $category = 'positive';
        elseif ($score < -0.6) $category = 'very_negative';
        elseif ($score < -0.2) $category = 'negative';
    }
    
    $sentimentData = [
        'score' => round($score, 3),
        'magnitude' => round($magnitude, 3),
        'category' => $category,
        'confidence' => round(min(abs($score) + ($magnitude * 0.5), 1.0), 3)
    ];
    
    $sentimentResults[] = array_merge($item, [
        'sentiment_data' => $sentimentData,
        'processing_cost' => ceil(strlen($item['text']) / 1000) * 0.0005
    ]);
    
    echo "Text " . ($index + 1) . " Analysis:\n";
    echo "  Score: {$sentimentData['score']} | Magnitude: {$sentimentData['magnitude']}\n";
    echo "  Category: {$sentimentData['category']} | Confidence: {$sentimentData['confidence']}\n";
    echo "  Cost: $" . number_format(ceil(strlen($item['text']) / 1000) * 0.0005, 4) . "\n\n";
}

echo "📊 Step 3: Daily Aggregation\n";
echo "-----------------------------\n";

// Group by platform and category
$grouped = [];
foreach ($sentimentResults as $result) {
    $platform = $result['metadata']['platform'];
    $category = $result['metadata']['category'];
    $key = "{$platform}:{$category}";
    
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'platform' => $platform,
            'category' => $category,
            'items' => []
        ];
    }
    
    $grouped[$key]['items'][] = $result;
}

// Calculate aggregates for each group
$dailyAggregates = [];
foreach ($grouped as $groupKey => $group) {
    $items = $group['items'];
    $scores = [];
    $magnitudes = [];
    $categoryCounts = [
        'very_positive' => 0,
        'positive' => 0,
        'neutral' => 0,
        'negative' => 0,
        'very_negative' => 0
    ];
    
    $totalEngagement = 0;
    $weightedSum = 0;
    
    foreach ($items as $item) {
        $sentiment = $item['sentiment_data'];
        $scores[] = $sentiment['score'];
        $magnitudes[] = $sentiment['magnitude'];
        
        $category = $sentiment['category'];
        if (isset($categoryCounts[$category])) {
            $categoryCounts[$category]++;
        }
        
        $engagement = $item['metadata']['engagement'] ?? 1;
        $totalEngagement += $engagement;
        $weightedSum += $sentiment['score'] * $engagement;
    }
    
    $totalPosts = count($items);
    $averageSentiment = $totalPosts > 0 ? array_sum($scores) / $totalPosts : 0;
    $averageMagnitude = $totalPosts > 0 ? array_sum($magnitudes) / $totalPosts : 0;
    $weightedSentiment = $totalEngagement > 0 ? $weightedSum / $totalEngagement : 0;
    
    // Calculate volatility (standard deviation)
    $volatility = 0;
    if ($totalPosts > 1) {
        $mean = $averageSentiment;
        $squaredDiffs = array_map(fn($score) => pow($score - $mean, 2), $scores);
        $volatility = sqrt(array_sum($squaredDiffs) / $totalPosts);
    }
    
    $aggregate = [
        'date' => '2025-08-06',
        'platform' => $group['platform'],
        'category' => $group['category'],
        'total_posts' => $totalPosts,
        'average_sentiment' => round($averageSentiment, 3),
        'weighted_sentiment' => round($weightedSentiment, 3),
        'average_magnitude' => round($averageMagnitude, 3),
        'sentiment_volatility' => round($volatility, 3),
        'total_engagement' => $totalEngagement,
        'sentiment_distribution' => $categoryCounts
    ];
    
    $dailyAggregates[] = $aggregate;
    
    echo "📈 {$group['platform']} - {$group['category']}:\n";
    echo "  Posts: {$totalPosts} | Engagement: {$totalEngagement}\n";
    echo "  Avg Sentiment: {$aggregate['average_sentiment']} | Weighted: {$aggregate['weighted_sentiment']}\n";
    echo "  Volatility: {$aggregate['sentiment_volatility']} | Avg Magnitude: {$aggregate['average_magnitude']}\n";
    echo "  Distribution: " . json_encode($categoryCounts) . "\n\n";
}

echo "💰 Step 4: Cost & Performance Summary\n";
echo "-------------------------------------\n";
$totalCost = array_sum(array_column($sentimentResults, 'processing_cost'));
$totalTexts = count($sentimentResults);
$successfulAnalyses = count(array_filter($sentimentResults, fn($r) => isset($r['sentiment_data'])));

echo "Total Texts Processed: {$totalTexts}\n";
echo "Successful Analyses: {$successfulAnalyses}\n";
echo "Success Rate: " . round(($successfulAnalyses / $totalTexts) * 100, 1) . "%\n";
echo "Total Processing Cost: $" . number_format($totalCost, 4) . "\n";
echo "Daily Aggregates Created: " . count($dailyAggregates) . "\n";

echo "\n🎯 Pipeline Complete!\n";
echo "===================\n";
echo "✅ Text → Google Cloud NLP → Daily Aggregates pipeline executed successfully\n";
echo "📊 Data is ready for visualization and trend analysis\n";
echo "🔄 In production, this would be stored in PostgreSQL and accessible via API\n";

?> 