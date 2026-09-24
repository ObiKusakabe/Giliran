<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeWithDevMode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'serve:devsimtime 
                            {--host=127.0.0.1 : The host address to serve on}
                            {--port=8000 : The port to serve on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start Laravel development server with Dev Mode time editor UI (floating bottom-right)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = $this->option('port');

        $this->components->info("Laravel development server started on http://{$host}:{$port}");
        $this->components->warn('⚠️  Dev Mode UI Active - Time editor available at bottom-right corner');
        $this->line('');
        $this->line('💡 Tip: Look for the floating time editor in the bottom-right corner of your app');

        // Set environment variable to show dev mode UI
        putenv('DEV_MODE_UI_ENABLED=true');

        // Start artisan serve
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'serve',
            "--host={$host}",
            "--port={$port}",
        ], null, [
            'DEV_MODE_UI_ENABLED' => 'true',
        ]);

        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());

        return $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
    }
}
