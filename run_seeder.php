<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

try {
    // Run the specific seeder
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->call('db:seed', [
        '--class' => 'Database\\Seeders\\FamousContractsSeeder',
        '--force' => true
    ]);
    
    echo "✅ Successfully seeded famous contracts!\n";
    echo "5 contracts added: Uniswap V3, Aave V3, OpenSea Seaport, Compound V3, Euler Finance (exploited)\n";
    
} catch (Exception $e) {
    echo "❌ Error running seeder: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}