<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
        $this->setupBreeze();

        $this->updateUserTable();

        $this->createAdmin();

        $this->registerMiddleware();

        $this->createDashboard('Dashboard');

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

    protected function getStub($type)
    {
        return file_get_contents(resource_path("stubs/$type.stub"));
    }

    protected function controller($name)
    {
        $template = str_replace(
            [
                '{{modelName}}',
                '{{modelNameLowerCase}}',
            ],
            [
                $name,
                strtolower(Str::plural($name)),
            ],
            $this->getStub('AdminController')
        );

        file_put_contents(app_path("/Http/Controllers/Admin/{$name}Controller.php"), $template);
    }

    protected function view($name)
    {
        $template = $this->getStub('Dashboard');

        file_put_contents(resource_path("views/{$name}.blade.php"), $template);
    }

    protected function createDashboard($name)
    {
        $this->controller($name);
        $this->view($name);

//        Artisan::call('make:migration create_' . strtolower(Str::plural($name)) . '_table --create=' . strtolower(Str::plural($name)));

        $this->info($name.' CRUD created successfully.');
    }

    protected function setupBreeze()
    {
        // Create a new process
        $process = new Process(['php', 'artisan', 'breeze:install']);

        // Set the input stream to allow interaction
        $process->setInput("blade\n\n");

        // Start the process
        $process->start();

        // Wait for the process to complete
        $process->wait();

        // Check if the process was successful
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    protected function createAdmin()
    {
        // Creating the admin user
        $name = $this->ask('Enter the name of the admin user:');
        $email = $this->ask('Enter the admin email:');
        $password = $this->secret('Enter the admin password:');

        $adminUser = new User();
        $adminUser->name = $name;
        $adminUser->email = $email;
        $adminUser->password = bcrypt($password);
        $adminUser->is_admin = 1;

        $adminUser->save();

        Artisan::call('make:middleware AdminMiddleware');
    }

    protected function updateUserTable()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(0);
        });

        // Add 'is_admin' to the $fillable array in User.php
        $userModelPath = app_path('Models/User.php');
        $userModelContents = file_get_contents($userModelPath);

        if (!strpos($userModelContents, "'is_admin'")) {
            $fillableCode = <<<'EOT'

    'is_admin', // Add 'is_admin' to the fillable fields
EOT;
            // Append 'is_admin' to the $fillable array
            file_put_contents($userModelPath, str_replace("'password',", "'password',\n" . $fillableCode, $userModelContents));
        }

        // Run the migration to apply the changes to the database
        Artisan::call('migrate');
    }
}
