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

    /**
     * Installs Breeze carcass.
     *
     * @throws ProcessFailedException
     */
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

    /**
     * Updates the User table and adds 'is_admin' column.
     */
    protected function updateUserTable($table = 'users', $column = 'is_admin')
    {
        // Check if column already exists
        if (!Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $table) use ($column) {
                $table->boolean($column)->default(0);
            });

            $this->info("$column column added to the $table table.");
        } else {
            $this->info("$column column already exists in the $table table. No changes made.");
        }

        // Add 'is_admin' to $fillable in User.php
        $userModelPath = app_path('Models/User.php');
        $userModelContents = file_get_contents($userModelPath);

        if (!strpos($userModelContents, "'is_admin'")) {
            $fillableCode = <<<'EOT'

    'is_admin', // Add 'is_admin' to the fillable fields
EOT;
            // Append 'is_admin' to $fillable
            file_put_contents($userModelPath, str_replace("'password',", "'password',\n" . $fillableCode, $userModelContents));
        }

        // Migrate the database
        Artisan::call('migrate');
    }

    /**
     * Creates the admin user.
     */
    protected function createAdmin()
    {
        $name = $this->ask('Enter the name of the admin user:');
        $email = $this->ask('Enter the admin email:');
        $password = $this->secret('Enter the admin password:');

        $adminUser = new User();
        $adminUser->name = $name;
        $adminUser->email = $email;
        $adminUser->password = bcrypt($password);
        $adminUser->is_admin = 1;

        $adminUser->save();

        $this->middleware('AdminMiddleware');
    }

    /**
     * Registers the middleware (adds it to Kernel.php).
     */
    protected function registerMiddleware()
    {
        $kernel = app_path('Http/Kernel.php');
        $content = file_get_contents($kernel);

        if (!str_contains($content, 'AdminMiddleware::class')) {
            // Add AdminMiddleware to Kernel.php
            $replacement = "'admin' => \App\Http\Middleware\AdminMiddleware::class,";

            $content = preg_replace(
                "/'auth' => \\\\App\\\\Http\\\\Middleware\\\\Authenticate::class,/",
                "$0\n        $replacement",
                $content
            );

            file_put_contents($kernel, $content);
        }
    }

    /**
     * Creates the Dashboard CRUD (Controller and View).
     */
    protected function createDashboard($name)
    {
        $this->controller('Command');
        $this->view();

        $routesPath = base_path('routes/web.php');
        $routesContents = file_get_contents($routesPath);

        // Replace 'verified' middleware with 'admin' middleware
        $updatedRoutesContents = str_replace(
            "->middleware(['auth', 'verified'])",
            "->middleware(['auth', 'admin'])",
            $routesContents
        );

        // Replace 'return view('dashboard');' with 'return view('admin/dashboard');'
        $updatedRoutesContents = str_replace(
            "return view('dashboard');",
            "return view('admin/index');",
            $updatedRoutesContents
        );

        $routes = <<<EOT
        // List commands
        Route::get('/dashboard/commands', [App\Http\Controllers\Admin\CommandController::class, 'index'])->middleware(['auth', 'admin'])->name('dashboard.commands.index');

        // Show a command
        Route::get('/admin/commands/{command}', [App\Http\Controllers\Admin\CommandController::class, 'show'])->middleware(['auth', 'admin'])->name('dashboard.commands.show');

        // Execute a command
        Route::post('/admin/commands/execute/{command}', [App\Http\Controllers\Admin\CommandController::class, 'execute'])->middleware(['auth', 'admin'])->name('dashboard.commands.execute');
        EOT;

        File::append(base_path('routes/api.php'), $routes);

        file_put_contents($routesPath, $updatedRoutesContents);

        $this->info($name.' CRUD created successfully.');
    }

    /**
     * Gets stub file based on its name/type.
     */
    protected function getStub($type)
    {
        return file_get_contents(resource_path("stubs/admin/$type.stub"));
    }

    /**
     * Creates the controller.
     */
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
            $this->getStub('CommandController')
        );

        file_put_contents(app_path("/Http/Controllers/Admin/{$name}Controller.php"), $template);
    }

    /**
     * Creates the view.
     */
    protected function view()
    {
        $template = $this->getStub('Dashboard');
        $navbar = $this->getStub('Navbar');
        $sidebar = $this->getStub('Sidebar');
        $index = $this->getStub('Index');
        $footer = $this->getStub('Footer');
        $commands = $this->getStub('Commands');
        $details = $this->getStub('Command-details');

        $viewsPath = resource_path("views/admin");

        if (!file_exists($viewsPath)) {
            mkdir($viewsPath, 0755, true);
        }

        file_put_contents(resource_path("views/admin/dashboard.blade.php"), $template);
        file_put_contents(resource_path("views/admin/navbar.blade.php"), $navbar);
        file_put_contents(resource_path("views/admin/sidebar.blade.php"), $sidebar);
        file_put_contents(resource_path("views/admin/index.blade.php"), $index);
        file_put_contents(resource_path("views/admin/footer.blade.php"), $footer);
        file_put_contents(resource_path("views/admin/commands.blade.php"), $commands);
        file_put_contents(resource_path("views/admin/command-details.blade.php"), $details);
    }

    /**
     * Creates the middleware.
     */
    protected function middleware($name)
    {
        $template = $this->getStub('Middleware');

        file_put_contents(app_path("/Http/Middleware/{$name}.php"), $template);
    }
}
