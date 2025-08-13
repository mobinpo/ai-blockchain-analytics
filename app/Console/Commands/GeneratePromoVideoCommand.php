<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

final class GeneratePromoVideoCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video:promo
                            {--duration=120 : Video duration in seconds}
                            {--resolution=1920x1080 : Video resolution}
                            {--fps=30 : Frames per second}
                            {--format=mp4 : Output video format}
                            {--output-dir=video-output : Output directory}
                            {--auto-record : Automatically start recording}
                            {--generate-assets : Generate visual assets}
                            {--create-scenes : Create scene templates}';

    /**
     * The console command description.
     */
    protected $description = 'Generate promotional video with automated recording and scene management';

    private array $sceneConfig = [];
    private string $outputDir;
    private Carbon $startTime;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = now();
        $this->outputDir = $this->option('output-dir');

        $this->displayBanner();

        try {
            // Setup video production environment
            $this->setupVideoEnvironment();

            // Generate visual assets if requested
            if ($this->option('generate-assets')) {
                $this->generateVideoAssets();
            }

            // Create scene templates
            if ($this->option('create-scenes')) {
                $this->createSceneTemplates();
            }

            // Setup recording configuration
            $this->setupRecordingConfig();

            // Generate recording scripts
            $this->generateRecordingScripts();

            // Auto-record if requested
            if ($this->option('auto-record')) {
                $this->startAutomaticRecording();
            }

            // Generate post-production pipeline
            $this->generatePostProductionPipeline();

            $this->info("\n🎉 Promotional video production system ready!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Video generation failed: {$e->getMessage()}");
            Log::error('Promo video generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Display video production banner
     */
    private function displayBanner(): void
    {
        $this->line('');
        $this->line('<fg=cyan>═══════════════════════════════════════════════════════════════════</>');
        $this->line('<fg=cyan>   🎬 AI Blockchain Analytics - Promotional Video Generator</>');
        $this->line('<fg=cyan>   🎥 Automated Video Production & Recording System</>');
        $this->line('<fg=cyan>═══════════════════════════════════════════════════════════════════</>');
        $this->line('');

        $duration = $this->option('duration');
        $resolution = $this->option('resolution');
        $fps = $this->option('fps');

        $this->info("🎯 Video Specifications:");
        $this->line("   Duration: {$duration} seconds");
        $this->line("   Resolution: {$resolution}");
        $this->line("   Frame Rate: {$fps} FPS");
        $this->line("   Output: {$this->outputDir}/");
        $this->line('');
    }

    /**
     * Setup video production environment
     */
    private function setupVideoEnvironment(): void
    {
        $this->info('🔧 Setting up video production environment...');

        // Create output directories
        $directories = [
            $this->outputDir,
            "{$this->outputDir}/scenes",
            "{$this->outputDir}/assets",
            "{$this->outputDir}/scripts",
            "{$this->outputDir}/raw-footage",
            "{$this->outputDir}/final"
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $this->line("   ✅ Created directory: {$dir}");
            }
        }

        // Load scene configuration
        $this->sceneConfig = $this->getSceneConfiguration();
        $this->line("   ✅ Loaded " . count($this->sceneConfig) . " scene configurations");
    }

    /**
     * Get scene configuration
     */
    private function getSceneConfiguration(): array
    {
        return [
            'hook' => [
                'name' => 'Problem Hook',
                'duration' => 10,
                'start_time' => 0,
                'description' => 'News headlines about DeFi exploits',
                'visuals' => ['headlines', 'exploit_notifications', 'dollar_amounts'],
                'audio' => 'urgent_professional_tone'
            ],
            'solution' => [
                'name' => 'Platform Introduction',
                'duration' => 20,
                'start_time' => 10,
                'description' => 'Dashboard overview and platform introduction',
                'visuals' => ['dashboard', 'logo_animation', 'feature_icons'],
                'audio' => 'confident_solution_oriented'
            ],
            'analysis_demo' => [
                'name' => 'Contract Analysis Demo',
                'duration' => 15,
                'start_time' => 30,
                'description' => 'Live contract analysis demonstration',
                'visuals' => ['contract_input', 'analysis_progress', 'risk_score', 'findings'],
                'audio' => 'technical_explanation'
            ],
            'sentiment_demo' => [
                'name' => 'Sentiment Monitoring',
                'duration' => 15,
                'start_time' => 45,
                'description' => 'Real-time sentiment dashboard',
                'visuals' => ['social_feeds', 'sentiment_charts', 'multi_platform'],
                'audio' => 'data_focused'
            ],
            'reporting_demo' => [
                'name' => 'PDF Generation',
                'duration' => 15,
                'start_time' => 60,
                'description' => 'Professional report generation',
                'visuals' => ['pdf_generation', 'report_preview', 'download'],
                'audio' => 'professional_benefits'
            ],
            'famous_contracts' => [
                'name' => 'Famous Contracts Database',
                'duration' => 15,
                'start_time' => 75,
                'description' => 'Historical exploit analysis',
                'visuals' => ['contracts_list', 'risk_comparison', 'historical_data'],
                'audio' => 'educational_tone'
            ],
            'proof_points' => [
                'name' => 'Statistics & Achievements',
                'duration' => 20,
                'start_time' => 90,
                'description' => 'Performance metrics and credibility',
                'visuals' => ['statistics', 'performance_graphs', 'certifications'],
                'audio' => 'credibility_building'
            ],
            'call_to_action' => [
                'name' => 'Call to Action',
                'duration' => 10,
                'start_time' => 110,
                'description' => 'Contact information and next steps',
                'visuals' => ['contact_info', 'website_url', 'get_started_button'],
                'audio' => 'compelling_close'
            ]
        ];
    }

    /**
     * Generate video assets
     */
    private function generateVideoAssets(): void
    {
        $this->info('🎨 Generating video assets...');

        // Generate HTML templates for web-based scenes
        $this->generateWebScenes();

        // Generate data for demonstrations
        $this->generateDemoData();

        // Create visual assets configuration
        $this->generateVisualAssets();

        $this->line("   ✅ Video assets generated");
    }

    /**
     * Generate web scenes for recording
     */
    private function generateWebScenes(): void
    {
        // Headlines scene for the hook
        $headlinesHtml = $this->generateHeadlinesScene();
        file_put_contents("{$this->outputDir}/scenes/headlines.html", $headlinesHtml);

        // Statistics scene for proof points
        $statisticsHtml = $this->generateStatisticsScene();
        file_put_contents("{$this->outputDir}/scenes/statistics.html", $statisticsHtml);

        // Contact scene for CTA
        $contactHtml = $this->generateContactScene();
        file_put_contents("{$this->outputDir}/scenes/contact.html", $contactHtml);

        $this->line("   ✅ Generated web scenes");
    }

    /**
     * Generate headlines scene HTML
     */
    private function generateHeadlinesScene(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeFi Exploits Headlines</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            font-family: 'Arial', sans-serif;
            color: white;
            overflow: hidden;
        }
        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .headline {
            font-size: 3rem;
            font-weight: bold;
            margin: 20px 0;
            opacity: 0;
            animation: fadeInSlide 2s ease-in-out forwards;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        .headline.critical {
            color: #ff4757;
            border: 2px solid #ff4757;
            padding: 20px;
            border-radius: 10px;
            background: rgba(255, 71, 87, 0.1);
        }
        .amount {
            font-size: 4rem;
            color: #ff6b6b;
            font-weight: 900;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.9);
        }
        @keyframes fadeInSlide {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .headline:nth-child(2) { animation-delay: 0.5s; }
        .headline:nth-child(3) { animation-delay: 1s; }
        .headline:nth-child(4) { animation-delay: 1.5s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="headline critical">EULER FINANCE EXPLOITED</div>
        <div class="headline amount">\$200 MILLION LOST</div>
        <div class="headline critical">MULTICHAIN BRIDGE COMPROMISED</div>
        <div class="headline amount">\$126 MILLION STOLEN</div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate statistics scene HTML
     */
    private function generateStatisticsScene(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Statistics</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea, #764ba2);
            font-family: 'Arial', sans-serif;
            color: white;
            overflow: hidden;
        }
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-gap: 40px;
            height: 100vh;
            padding: 60px;
            align-items: center;
        }
        .stat-box {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .stat-number {
            font-size: 4rem;
            font-weight: 900;
            color: #4ecdc4;
            margin-bottom: 10px;
            counter-reset: num var(--num);
            animation: countUp 3s ease-out forwards;
        }
        .stat-label {
            font-size: 1.5rem;
            font-weight: 600;
            opacity: 0.9;
        }
        @keyframes countUp {
            from { --num: 0; }
            to { --num: var(--target); }
        }
        .stat-number::after {
            content: counter(num);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="stat-box">
            <div class="stat-number" style="--target: 500;">500</div>
            <div class="stat-label">Concurrent Analyses Tested</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="--target: 99;">99.9%</div>
            <div class="stat-label">Uptime Achieved</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="--target: 100;">&lt;100ms</div>
            <div class="stat-label">Response Time</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="--target: 5;">5+</div>
            <div class="stat-label">Famous Contracts Analyzed</div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate contact scene HTML
     */
    private function generateContactScene(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started - AI Blockchain Analytics</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            font-family: 'Arial', sans-serif;
            color: white;
            overflow: hidden;
        }
        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .logo {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 40px;
            color: #4ecdc4;
        }
        .cta-button {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            padding: 20px 60px;
            font-size: 2rem;
            font-weight: bold;
            border: none;
            border-radius: 50px;
            color: white;
            cursor: pointer;
            margin: 20px;
            animation: pulse 2s infinite;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .contact-info {
            font-size: 1.5rem;
            margin: 10px 0;
            opacity: 0.9;
        }
        .website {
            font-size: 2rem;
            font-weight: bold;
            color: #4ecdc4;
            margin: 20px 0;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🔍 AI Blockchain Analytics</div>
        <div class="website">ai-blockchain-analytics.com</div>
        <button class="cta-button">GET STARTED TODAY</button>
        <div class="contact-info">📧 contact@ai-blockchain-analytics.com</div>
        <div class="contact-info">🐙 GitHub: /mobinpo/ai-blockchain-analytics</div>
        <div class="contact-info">🔗 LinkedIn | 🐦 Twitter | 📱 Telegram</div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate demo data
     */
    private function generateDemoData(): void
    {
        $demoData = [
            'contracts' => [
                [
                    'address' => '0x5C69bEe701ef814a2B6a3EDD4B1652CB9cc5aA6f',
                    'name' => 'Uniswap V2 Factory',
                    'risk_score' => 25,
                    'status' => 'Low Risk'
                ],
                [
                    'address' => '0x27182842E098f60e3D576794A5bFFb0777E025d3',
                    'name' => 'Euler Finance',
                    'risk_score' => 95,
                    'status' => 'Critical Risk'
                ],
                [
                    'address' => '0x4ddc2d193948926d02f9b1fe9e1daa0718270ed5',
                    'name' => 'Compound ETH',
                    'risk_score' => 40,
                    'status' => 'Medium Risk'
                ]
            ],
            'sentiment_data' => [
                ['platform' => 'Twitter', 'sentiment' => 0.65, 'volume' => 1250],
                ['platform' => 'Reddit', 'sentiment' => 0.45, 'volume' => 890],
                ['platform' => 'Telegram', 'sentiment' => 0.72, 'volume' => 2100]
            ]
        ];

        file_put_contents(
            "{$this->outputDir}/assets/demo-data.json",
            json_encode($demoData, JSON_PRETTY_PRINT)
        );

        $this->line("   ✅ Generated demo data");
    }

    /**
     * Generate visual assets
     */
    private function generateVisualAssets(): void
    {
        $assets = [
            'colors' => [
                'primary' => '#4ecdc4',
                'secondary' => '#44a08d',
                'danger' => '#ff4757',
                'warning' => '#ffa726',
                'success' => '#26de81',
                'background' => '#2c3e50'
            ],
            'fonts' => [
                'heading' => 'Arial, sans-serif',
                'body' => 'Helvetica, Arial, sans-serif',
                'mono' => 'Monaco, Consolas, monospace'
            ],
            'animations' => [
                'fade_duration' => '0.5s',
                'slide_duration' => '0.8s',
                'pulse_duration' => '2s'
            ]
        ];

        file_put_contents(
            "{$this->outputDir}/assets/visual-config.json",
            json_encode($assets, JSON_PRETTY_PRINT)
        );

        $this->line("   ✅ Generated visual assets configuration");
    }

    /**
     * Create scene templates
     */
    private function createSceneTemplates(): void
    {
        $this->info('📝 Creating scene templates...');

        foreach ($this->sceneConfig as $sceneId => $scene) {
            $template = $this->generateSceneTemplate($sceneId, $scene);
            file_put_contents(
                "{$this->outputDir}/scenes/{$sceneId}-template.md",
                $template
            );
        }

        $this->line("   ✅ Created " . count($this->sceneConfig) . " scene templates");
    }

    /**
     * Generate scene template
     */
    private function generateSceneTemplate(string $sceneId, array $scene): string
    {
        return <<<TEMPLATE
# 🎬 Scene: {$scene['name']}

**Duration**: {$scene['duration']} seconds  
**Start Time**: {$scene['start_time']}s  
**Description**: {$scene['description']}

## 📋 Recording Checklist

### Pre-Recording Setup
- [ ] Browser in full-screen mode
- [ ] Demo data loaded and ready
- [ ] Audio levels checked
- [ ] Screen resolution set to 1920x1080
- [ ] Recording software configured

### Visual Elements
TEMPLATE . "\n" . implode("\n", array_map(fn($visual) => "- [ ] {$visual}", $scene['visuals'])) . "\n\n" . <<<TEMPLATE

### Audio Requirements
- **Tone**: {$scene['audio']}
- **Pacing**: Measured but engaging
- **Volume**: Consistent levels
- **Quality**: Studio-grade recording

### Recording Instructions
1. Start recording 2 seconds before action
2. Maintain smooth cursor movements
3. Pause briefly between major actions
4. End recording 2 seconds after completion

### Post-Recording Notes
- [ ] Review for smooth transitions
- [ ] Check audio sync
- [ ] Verify visual clarity
- [ ] Note any retakes needed

## 🎯 Success Criteria
- Clear visual demonstration
- Smooth, professional movements
- Excellent audio quality
- Proper timing alignment
TEMPLATE;
    }

    /**
     * Setup recording configuration
     */
    private function setupRecordingConfig(): void
    {
        $this->info('⚙️ Setting up recording configuration...');

        $config = [
            'video' => [
                'resolution' => $this->option('resolution'),
                'fps' => (int) $this->option('fps'),
                'format' => $this->option('format'),
                'codec' => 'h264',
                'bitrate' => '10M'
            ],
            'audio' => [
                'sample_rate' => '48000',
                'channels' => 2,
                'codec' => 'aac',
                'bitrate' => '192k'
            ],
            'scenes' => $this->sceneConfig,
            'output' => [
                'directory' => $this->outputDir,
                'naming_pattern' => 'scene_{id}_{timestamp}',
                'backup_copies' => 3
            ]
        ];

        file_put_contents(
            "{$this->outputDir}/recording-config.json",
            json_encode($config, JSON_PRETTY_PRINT)
        );

        $this->line("   ✅ Recording configuration saved");
    }

    /**
     * Generate recording scripts
     */
    private function generateRecordingScripts(): void
    {
        $this->info('📜 Generating recording scripts...');

        // Main recording script
        $mainScript = $this->generateMainRecordingScript();
        file_put_contents("{$this->outputDir}/scripts/record-promo.sh", $mainScript);
        chmod("{$this->outputDir}/scripts/record-promo.sh", 0755);

        // Individual scene scripts
        foreach ($this->sceneConfig as $sceneId => $scene) {
            $sceneScript = $this->generateSceneRecordingScript($sceneId, $scene);
            file_put_contents("{$this->outputDir}/scripts/record-{$sceneId}.sh", $sceneScript);
            chmod("{$this->outputDir}/scripts/record-{$sceneId}.sh", 0755);
        }

        $this->line("   ✅ Generated recording scripts");
    }

    /**
     * Generate main recording script
     */
    private function generateMainRecordingScript(): string
    {
        $resolution = $this->option('resolution');
        $fps = $this->option('fps');
        $duration = $this->option('duration');

        return <<<SCRIPT
#!/bin/bash

# AI Blockchain Analytics - Promotional Video Recording Script
# Generated: {$this->startTime->toISOString()}

echo "🎬 Starting AI Blockchain Analytics Promo Video Recording"
echo "=================================================="

# Configuration
RESOLUTION="{$resolution}"
FPS="{$fps}"
DURATION="{$duration}"
OUTPUT_DIR="{$this->outputDir}"

# Create output directories
mkdir -p "\$OUTPUT_DIR/raw-footage"
mkdir -p "\$OUTPUT_DIR/final"

# Check for required tools
command -v ffmpeg >/dev/null 2>&1 || { echo "❌ ffmpeg required but not installed. Aborting." >&2; exit 1; }

echo "✅ Configuration loaded"
echo "   Resolution: \$RESOLUTION"
echo "   FPS: \$FPS"
echo "   Duration: \$DURATION seconds"
echo ""

# Record each scene
SCENES=("hook" "solution" "analysis_demo" "sentiment_demo" "reporting_demo" "famous_contracts" "proof_points" "call_to_action")

for scene in "\${SCENES[@]}"; do
    echo "🎥 Recording scene: \$scene"
    echo "Press ENTER when ready to start recording \$scene..."
    read -r
    
    # Start scene-specific recording
    ./record-\$scene.sh
    
    echo "✅ Scene \$scene recorded"
    echo ""
done

echo "🎉 All scenes recorded successfully!"
echo "Next steps:"
echo "1. Review raw footage in \$OUTPUT_DIR/raw-footage/"
echo "2. Run post-production pipeline"
echo "3. Generate final video"

SCRIPT;
    }

    /**
     * Generate scene recording script
     */
    private function generateSceneRecordingScript(string $sceneId, array $scene): string
    {
        return <<<SCRIPT
#!/bin/bash

# Scene: {$scene['name']}
# Duration: {$scene['duration']} seconds

echo "🎬 Recording: {$scene['name']}"
echo "Duration: {$scene['duration']} seconds"
echo ""

OUTPUT_FILE="{$this->outputDir}/raw-footage/{$sceneId}_\$(date +%Y%m%d_%H%M%S).mp4"

# Scene-specific instructions
echo "📋 Scene Instructions:"
echo "   {$scene['description']}"
echo ""
echo "🎯 Visual Elements:"
SCRIPT . "\n" . implode("\n", array_map(fn($visual) => "echo \"   - {$visual}\"", $scene['visuals'])) . "\n\n" . <<<SCRIPT

echo ""
echo "🎤 Audio: {$scene['audio']}"
echo ""
echo "Press ENTER to start recording in 3 seconds..."
read -r

echo "Starting in 3..."
sleep 1
echo "2..."
sleep 1
echo "1..."
sleep 1
echo "🔴 RECORDING!"

# Use ffmpeg to record screen (adjust input source as needed)
# This is a template - adjust for your specific recording setup
ffmpeg -f x11grab -s {$this->option('resolution')} -r {$this->option('fps')} -i :0.0 \\
       -f pulse -i default \\
       -c:v libx264 -preset ultrafast -crf 18 \\
       -c:a aac -b:a 192k \\
       -t {$scene['duration']} \\
       "\$OUTPUT_FILE"

echo "✅ Scene recorded: \$OUTPUT_FILE"

SCRIPT;
    }

    /**
     * Start automatic recording
     */
    private function startAutomaticRecording(): void
    {
        $this->info('🤖 Starting automatic recording...');
        
        $this->warn('⚠️ Automatic recording requires manual setup of:');
        $this->line('   - Screen recording software (OBS Studio recommended)');
        $this->line('   - Audio input configuration');
        $this->line('   - Browser setup with demo environment');
        $this->line('');
        
        if ($this->confirm('Continue with recording setup?')) {
            $this->line('📋 Recording setup checklist:');
            $this->line('   1. Open browser in full-screen mode');
            $this->line('   2. Navigate to platform dashboard');
            $this->line('   3. Prepare demo data and contracts');
            $this->line('   4. Start recording software');
            $this->line('   5. Begin scene recording');
            
            $this->info('Recording scripts available in: ' . $this->outputDir . '/scripts/');
        }
    }

    /**
     * Generate post-production pipeline
     */
    private function generatePostProductionPipeline(): void
    {
        $this->info('🎞️ Generating post-production pipeline...');

        $editingScript = $this->generateEditingScript();
        file_put_contents("{$this->outputDir}/scripts/edit-video.sh", $editingScript);
        chmod("{$this->outputDir}/scripts/edit-video.sh", 0755);

        $exportScript = $this->generateExportScript();
        file_put_contents("{$this->outputDir}/scripts/export-final.sh", $exportScript);
        chmod("{$this->outputDir}/scripts/export-final.sh", 0755);

        $this->line("   ✅ Post-production pipeline ready");
    }

    /**
     * Generate editing script
     */
    private function generateEditingScript(): string
    {
        return <<<SCRIPT
#!/bin/bash

# AI Blockchain Analytics - Video Editing Pipeline
# Combines all scenes into final promotional video

echo "🎞️ Starting video editing pipeline..."

INPUT_DIR="{$this->outputDir}/raw-footage"
OUTPUT_DIR="{$this->outputDir}/final"
TEMP_DIR="{$this->outputDir}/temp"

# Create temp directory
mkdir -p "\$TEMP_DIR"

# Scene order and timing
SCENES=(
    "hook:0:10"
    "solution:10:30" 
    "analysis_demo:30:45"
    "sentiment_demo:45:60"
    "reporting_demo:60:75"
    "famous_contracts:75:90"
    "proof_points:90:110"
    "call_to_action:110:120"
)

echo "📝 Creating scene list..."
SCENE_LIST="\$TEMP_DIR/scenes.txt"
> "\$SCENE_LIST"

for scene_info in "\${SCENES[@]}"; do
    IFS=':' read -r scene start end <<< "\$scene_info"
    
    # Find the latest recording for this scene
    SCENE_FILE=\$(ls -t "\$INPUT_DIR"/\${scene}_*.mp4 2>/dev/null | head -n1)
    
    if [ -n "\$SCENE_FILE" ]; then
        echo "file '\$SCENE_FILE'" >> "\$SCENE_LIST"
        echo "✅ Added scene: \$scene (\$SCENE_FILE)"
    else
        echo "❌ Missing scene: \$scene"
    fi
done

echo ""
echo "🔧 Combining scenes..."

# Combine all scenes
ffmpeg -f concat -safe 0 -i "\$SCENE_LIST" \\
       -c copy \\
       -avoid_negative_ts make_zero \\
       "\$OUTPUT_DIR/promo-video-raw.mp4"

echo "✅ Raw video created: \$OUTPUT_DIR/promo-video-raw.mp4"

# Add transitions and effects
echo "✨ Adding transitions and effects..."

ffmpeg -i "\$OUTPUT_DIR/promo-video-raw.mp4" \\
       -vf "fade=in:0:30,fade=out:3570:30" \\
       -c:a copy \\
       "\$OUTPUT_DIR/promo-video-with-effects.mp4"

echo "✅ Video with effects: \$OUTPUT_DIR/promo-video-with-effects.mp4"

# Cleanup
rm -rf "\$TEMP_DIR"

echo "🎉 Video editing complete!"

SCRIPT;
    }

    /**
     * Generate export script
     */
    private function generateExportScript(): string
    {
        return <<<SCRIPT
#!/bin/bash

# Export final video in multiple formats

INPUT_FILE="{$this->outputDir}/final/promo-video-with-effects.mp4"
OUTPUT_DIR="{$this->outputDir}/final"

echo "📤 Exporting final video formats..."

# High quality version (YouTube/Web)
ffmpeg -i "\$INPUT_FILE" \\
       -c:v libx264 -preset slow -crf 18 \\
       -c:a aac -b:a 192k \\
       -movflags +faststart \\
       "\$OUTPUT_DIR/ai-blockchain-analytics-promo-hq.mp4"

# Mobile optimized version
ffmpeg -i "\$INPUT_FILE" \\
       -vf scale=1280:720 \\
       -c:v libx264 -preset fast -crf 23 \\
       -c:a aac -b:a 128k \\
       -movflags +faststart \\
       "\$OUTPUT_DIR/ai-blockchain-analytics-promo-mobile.mp4"

# Social media version (60 seconds)
ffmpeg -i "\$INPUT_FILE" \\
       -t 60 \\
       -c:v libx264 -preset fast -crf 23 \\
       -c:a aac -b:a 128k \\
       -movflags +faststart \\
       "\$OUTPUT_DIR/ai-blockchain-analytics-promo-social.mp4"

# GIF preview (first 10 seconds)
ffmpeg -i "\$INPUT_FILE" \\
       -t 10 -vf scale=800:450 \\
       -r 15 \\
       "\$OUTPUT_DIR/ai-blockchain-analytics-preview.gif"

echo "✅ Export complete!"
echo "Files available in: \$OUTPUT_DIR"
echo "   - ai-blockchain-analytics-promo-hq.mp4 (Full quality)"
echo "   - ai-blockchain-analytics-promo-mobile.mp4 (Mobile optimized)"
echo "   - ai-blockchain-analytics-promo-social.mp4 (60s social media)"
echo "   - ai-blockchain-analytics-preview.gif (Preview GIF)"

SCRIPT;
    }
}
