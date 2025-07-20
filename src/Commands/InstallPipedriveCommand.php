<?php

namespace Skeylup\LaravelPipedrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallPipedriveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pipedrive:install 
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Pipedrive service provider for dashboard authorization';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Pipedrive package...');

        // Publish package assets
        $this->publishPackageAssets();

        // Publish the service provider
        $this->publishServiceProvider();

        $this->info('');
        $this->info('Pipedrive package installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Run migrations: php artisan migrate');
        $this->info('2. Add PipedriveServiceProvider to your config/app.php providers array');
        $this->info('3. Customize the viewPipedrive gate in app/Providers/PipedriveServiceProvider.php');
        $this->info('4. Configure authorized emails in config/pipedrive.php');
        $this->info('5. Set up your Pipedrive credentials in .env file');

        return self::SUCCESS;
    }

    /**
     * Publish package assets using vendor:publish.
     */
    protected function publishPackageAssets(): void
    {
        $this->info('Publishing package assets...');

        // Publish config
        $this->call('vendor:publish', [
            '--provider' => 'Skeylup\LaravelPipedrive\LaravelPipedriveServiceProvider',
            '--tag' => 'laravel-pipedrive-config',
            '--force' => $this->option('force'),
        ]);

        // Publish migrations
        $this->call('vendor:publish', [
            '--provider' => 'Skeylup\LaravelPipedrive\LaravelPipedriveServiceProvider',
            '--tag' => 'laravel-pipedrive-migrations',
            '--force' => $this->option('force'),
        ]);

        // Publish views
        $this->call('vendor:publish', [
            '--provider' => 'Skeylup\LaravelPipedrive\LaravelPipedriveServiceProvider',
            '--tag' => 'laravel-pipedrive-views',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Publish the service provider.
     */
    protected function publishServiceProvider(): void
    {
        $filesystem = new Filesystem();

        $stub = __DIR__ . '/../../stubs/PipedriveServiceProvider.stub';
        $target = app_path('Providers/PipedriveServiceProvider.php');

        if ($filesystem->exists($target) && !$this->option('force')) {
            $this->warn('PipedriveServiceProvider already exists. Use --force to overwrite.');
            return;
        }

        $filesystem->ensureDirectoryExists(dirname($target));
        $filesystem->copy($stub, $target);

        $this->info('Published: ' . $target);
    }
}
