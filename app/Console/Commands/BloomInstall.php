<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BloomInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install bloom admin dashboard';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //Installing Laravel Breeze
        exec('composer require laravel/breeze');

        Artisan::call('breeze:install');

        // Creating the admin user
        $name = $this->ask('Enter the name of the admin user:');
        $email = $this->ask('Enter the admin email:');
        $password = $this->secret('Enter the admin password:');

        $adminUser = new User();
        $adminUser->name = $name;
        $adminUser->email = $email;
        $adminUser->password = bcrypt($password);

        $adminUser->save();

        Artisan::call('make:middleware AdminMiddleware');

        $this->registerMiddleware();

        $this->protectDashboardRoutes();

        $this->info('Bloom admin dashboard and admin user installed successfully.');
    }

    protected function registerMiddleware()
    {
        $kernel = app_path('Http/Kernel.php');
        $content = file_get_contents($kernel);

        if (!str_contains($content, 'AdminMiddleware::class')) {
            // Add the AdminMiddleware
            $replacement = "'admin' => \App\Http\Middleware\AdminMiddleware::class,";

            $content = preg_replace(
                "/'auth' => \\\\App\\\\Http\\\\Middleware\\\\Authenticate::class,/",
                "$0\n        $replacement",
                $content
            );

            file_put_contents($kernel, $content);
        }
    }

    protected function protectDashboardRoutes()
    {
        // Apply the middleware to routes
        $routes = base_path('routes/web.php');
        $content = file_get_contents($routes);

        if (!str_contains($content, 'admin')) {
            $content .= "\n\nRoute::middleware(['admin'])->group(function () {
        Route::get('/dashboard', 'DashboardController@index');
    });";

            file_put_contents($routes, $content);
        }
    }
}
