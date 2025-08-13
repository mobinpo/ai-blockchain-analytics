<?php

/**
 * 🤖 Google Cloud NLP Pipeline Demo
 * 
 * Demonstrates: Text → Google Cloud NLP (batch sentiment) → Daily aggregates
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "🤖 Google Cloud NLP Pipeline Demo\n";
echo str_repeat("=", 50) . "\n\n";

// Sample texts for demonstration
$sampleTexts = [
    "Bitcoin is revolutionary technology that will change finance forever! 🚀",
    "Cryptocurrency markets are very volatile and risky for investors",
    "DeFi protocols are providing amazing yields and opportunities",
    "Ethereum 2.0 staking rewards are attractive for long-term holders",
    "Regulatory uncertainty is concerning for crypto adoption",
    "Blockchain technology has incredible potential beyond just currency",
    "NFT market seems overpriced and speculative right now",
    "Web3 development is creating new possibilities for developers"
];

echo "📝 Sample Texts for Processing:\n";
foreach ($sampleTexts as $index => $text) {
    echo "  " . ($index + 1) . ". " . $text . "\n";
}
echo "\n";

// Simulate Google Cloud NLP processing
echo "🤖 Simulating Google Cloud NLP Processing...\n";
echo str_repeat("-", 40) . "\n";

$simulatedResults = [];
foreach ($sampleTexts as $index => $text) {
    // Simulate sentiment analysis (in real implementation, this would call Google Cloud NLP)
    $score = (rand(-100, 100) / 100); // Random score between -1 and 1
    $magnitude = (rand(20, 100) / 100); // Random magnitude between 0.2 and 1
    
    $label = match (true) {
        $score > 0.25 => 'positive',
        $score < -0.25 => 'negative',
        abs($score) > 0.1 => 'mixed',
        default => 'neutral'
    };
    
    $simulatedResults[] = [
        'text' => $text,
        'sentiment_score' => round($score, 3),
        'sentiment_magnitude' => round($magnitude, 3),
        'sentiment_label' => $label,
        'error' => null
    ];
    
    echo "  Text " . ($index + 1) . ": " . $label . " (score: " . round($score, 3) . ")\n";
}

echo "\n";

// Simulate daily aggregates calculation
echo "📊 Generating Daily Aggregates...\n";
echo str_repeat("-", 35) . "\n";

$sentimentCounts = [
    'positive' => 0,
    'negative' => 0,
    'neutral' => 0,
    'mixed' => 0
];

$scores = [];
$magnitudes = [];

foreach ($simulatedResults as $result) {
    $sentimentCounts[$result['sentiment_label']]++;
    if ($result['sentiment_score'] !== null) {
        $scores[] = $result['sentiment_score'];
    }
    if ($result['sentiment_magnitude'] !== null) {
        $magnitudes[] = $result['sentiment_magnitude'];
    }
}

$dailyAggregate = [
    'date' => date('Y-m-d'),
    'platform' => 'demo',
    'category' => 'cryptocurrency',
    'total_documents' => count($simulatedResults),
    'avg_sentiment_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 3) : 0,
    'avg_magnitude' => count($magnitudes) > 0 ? round(array_sum($magnitudes) / count($magnitudes), 3) : 0,
    'positive_count' => $sentimentCounts['positive'],
    'negative_count' => $sentimentCounts['negative'],
    'neutral_count' => $sentimentCounts['neutral'],
    'mixed_count' => $sentimentCounts['mixed'],
    'min_sentiment' => count($scores) > 0 ? round(min($scores), 3) : 0,
    'max_sentiment' => count($scores) > 0 ? round(max($scores), 3) : 0,
];

echo "Daily Aggregate for " . $dailyAggregate['date'] . ":\n";
echo "  📈 Total Documents: " . $dailyAggregate['total_documents'] . "\n";
echo "  📊 Average Sentiment: " . $dailyAggregate['avg_sentiment_score'] . "\n";
echo "  📏 Average Magnitude: " . $dailyAggregate['avg_magnitude'] . "\n";
echo "  😊 Positive: " . $dailyAggregate['positive_count'] . "\n";
echo "  😞 Negative: " . $dailyAggregate['negative_count'] . "\n";
echo "  😐 Neutral: " . $dailyAggregate['neutral_count'] . "\n";
echo "  🤔 Mixed: " . $dailyAggregate['mixed_count'] . "\n";
echo "  📉 Min Score: " . $dailyAggregate['min_sentiment'] . "\n";
echo "  📈 Max Score: " . $dailyAggregate['max_sentiment'] . "\n";

echo "\n";

// Demonstrate pipeline workflow
echo "🔄 Complete Pipeline Workflow:\n";
echo str_repeat("-", 35) . "\n";
echo "1. ✅ Input Texts Received (" . count($sampleTexts) . " texts)\n";
echo "2. ✅ Batch Created (ID: demo_batch_" . time() . ")\n";
echo "3. ✅ Google Cloud NLP Processing (simulated)\n";
echo "4. ✅ Individual Results Stored\n";
echo "5. ✅ Daily Aggregates Generated\n";
echo "6. ✅ Batch Completed Successfully\n";

echo "\n";

// Show CLI commands
echo "🛠️  Available CLI Commands:\n";
echo str_repeat("-", 30) . "\n";
echo "# Process single text:\n";
echo "docker compose exec app php artisan nlp:process-text \\\n";
echo "  --text=\"Bitcoin is going to the moon!\" \\\n";
echo "  --platform=twitter --category=crypto\n\n";

echo "# Process from file:\n";
echo "docker compose exec app php artisan nlp:process-text \\\n";
echo "  --file=texts.txt \\\n";
echo "  --platform=reddit --category=blockchain \\\n";
echo "  --async --aggregates\n\n";

echo "# Interactive mode:\n";
echo "docker compose exec app php artisan nlp:process-text\n\n";

// Show API endpoints
echo "🌐 Available API Endpoints:\n";
echo str_repeat("-", 30) . "\n";
echo "POST /api/google-nlp/process-texts     - Process multiple texts\n";
echo "POST /api/google-nlp/process-single    - Process single text\n";
echo "GET  /api/google-nlp/batch/{id}/status - Get batch status\n";
echo "GET  /api/google-nlp/daily-aggregates  - Get daily aggregates\n";
echo "GET  /api/google-nlp/health            - Health check\n";

echo "\n";

// Configuration info
echo "⚙️  Configuration Required:\n";
echo str_repeat("-", 30) . "\n";
echo "1. Google Cloud Project ID\n";
echo "2. Service Account JSON credentials\n";
echo "3. Natural Language API enabled\n";
echo "4. Environment variables set:\n";
echo "   - GOOGLE_CLOUD_PROJECT_ID\n";
echo "   - GOOGLE_APPLICATION_CREDENTIALS\n";

echo "\n";

echo "🎯 Pipeline Benefits:\n";
echo str_repeat("-", 25) . "\n";
echo "✅ Streamlined: Text → NLP → Aggregates in one flow\n";
echo "✅ Scalable: Handle thousands of texts efficiently\n";
echo "✅ Async: Queue-based processing for large datasets\n";
echo "✅ Monitored: Health checks and status tracking\n";
echo "✅ Aggregated: Automatic daily statistical summaries\n";
echo "✅ API Ready: REST endpoints for easy integration\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "🚀 Google Cloud NLP Pipeline Ready!\n";
echo "📖 See GOOGLE_CLOUD_NLP_PIPELINE_GUIDE.md for full documentation\n";