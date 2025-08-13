<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\Feature\ComprehensiveVulnerabilityRegressionTest;
use Illuminate\Foundation\Testing\TestCase;

final class TestRegressionSuite extends Command
{
    protected $signature = 'test:regression
                           {--filter= : Filter tests by pattern}
                           {--real-api : Include real API tests}
                           {--fast : Run only simulation tests}
                           {--v : Verbose output}';

    protected $description = 'Run vulnerability regression test suite via PHPUnit';

    public function handle(): int
    {
        $this->displayHeader();

        $command = $this->buildTestCommand();
        
        $this->info("🚀 Running command: {$command}");
        $this->newLine();

        $exitCode = $this->executeTestCommand($command);

        if ($exitCode === 0) {
            $this->info('✅ Regression tests completed successfully!');
            $this->displayPostTestInfo();
        } else {
            $this->error('❌ Regression tests failed!');
        }

        return $exitCode;
    }

    private function displayHeader(): void
    {
        $this->info('🧪 VULNERABILITY REGRESSION TEST RUNNER');
        $this->info('Running PHPUnit test suite for smart contract vulnerability detection');
        $this->newLine();
    }

    private function buildTestCommand(): string
    {
        $command = 'vendor/bin/phpunit';
        
        // Test filter
        if ($filter = $this->option('filter')) {
            $command .= " --filter='{$filter}'";
        } else {
            $command .= ' --group=regression';
        }

        // Include real API tests if requested
        if ($this->option('real-api')) {
            $command .= ' --group=real-api';
        } elseif ($this->option('fast')) {
            $command .= ' --exclude-group=real-api';
        }

        // Verbose output
        if ($this->option('v')) {
            $command .= ' -v';
        }

        // Test class
        $command .= ' tests/Feature/ComprehensiveVulnerabilityRegressionTest.php';

        return $command;
    }

    private function executeTestCommand(string $command): int
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ],
            $pipes,
            base_path()
        );

        if (!is_resource($process)) {
            $this->error('Failed to start test process');
            return 1;
        }

        fclose($pipes[0]);

        // Read output in real-time
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $stdout = fgets($pipes[1], 1024);
            $stderr = fgets($pipes[2], 1024);

            if ($stdout !== false) {
                echo $stdout;
            }

            if ($stderr !== false) {
                echo $stderr;
            }

            // Check if process is still running
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Read any remaining output
                echo stream_get_contents($pipes[1]);
                echo stream_get_contents($pipes[2]);
                break;
            }

            usleep(100000); // 0.1 second
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        return $exitCode;
    }

    private function displayPostTestInfo(): void
    {
        $this->newLine();
        $this->info('📊 Additional commands available:');
        $this->line('• php artisan regression:dashboard  → View test results dashboard');
        $this->line('• php artisan regression:analyze   → Analyze test patterns');
        $this->line('• php artisan regression:run       → Run via Artisan command');
        $this->newLine();
        
        $this->info('📁 Test results saved to:');
        $this->line('• storage/app/regression_tests/');
        $this->newLine();
    }
}