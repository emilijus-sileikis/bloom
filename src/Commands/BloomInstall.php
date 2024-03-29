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

class BloomInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:install
        {--create-admin : Creates a new admin user.}
        {--update-user-table : Updates the User table and adds \'is_admin\' column (Use if the database was refreshed).}';

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

        if ($this->option('create-admin') && $this->option('update-user-table')) {
            $this->updateUserTable();
            $this->createAdmin();
            $this->info('Table updated successfully and a new admin user was created.');
        } else if ($this->option('create-admin')) {
            $this->createAdmin();
            $this->info('Admin user created successfully.');
        } else if ($this->option('update-user-table')) {
            $this->updateUserTable();
            $this->info('User table updated successfully.');
        } else {
            $this->setupBreeze();

            $this->updateUserTable();

            $this->createAdmin();

            $this->registerMiddleware();

            $this->replaceOldRoute();

            $this->createDashboard('Dashboard');

            $this->info('Bloom admin dashboard and admin user installed successfully.');
        }
    }

    /**
     * Installs Breeze carcass.
     *
     * @throws ProcessFailedException
     */
    protected function setupBreeze()
    {
        $this->call('breeze:install');
    }

    /**
     * Updates the User table and adds 'is_admin' column.
     */
    protected function updateUserTable($table = 'users', $column = 'is_admin')
    {
        if (!Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $table) use ($column) {
                $table->boolean($column)->default(0);
            });

            $this->info("$column column added to the $table table.");
        } else {
            $this->info("$column column already exists in the $table table. No changes made.");
        }

        $userModelPath = app_path('Models/User.php');
        $userModelContents = file_get_contents($userModelPath);

        if (!strpos($userModelContents, "'is_admin'")) {
            $fillableCode = <<<'EOT'

    'is_admin',
EOT;
            file_put_contents($userModelPath, str_replace("'password',", "'password',\n" . $fillableCode, $userModelContents));
        }

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
     * Replaces the old dashboard route.
     */
    private function replaceOldRoute()
    {
        $webFilePath = base_path('routes/web.php');

        $content = File::get($webFilePath);

        $oldRoute = "Route::get('/dashboard', function () {\n    return view('dashboard');\n})->middleware(['auth', 'verified'])->name('dashboard');\n";
        $newRoute = "Route::get('/dashboard', [App\Http\Controllers\Admin\CommandController::class, 'index'])->middleware(['auth', 'admin'])->name('dashboard');\n";
        $content = str_replace($oldRoute, $newRoute, $content);

        File::put($webFilePath, $content);

        $this->info('Dashboard route replaced successfully.');
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

        // Append the new routes
        $newRoutes = "
    Route::get('/dashboard/commands', [App\Http\Controllers\Admin\CommandController::class, 'commands'])->middleware(['auth', 'admin'])->name('dashboard.commands.index');

    Route::get('/dashboard/examples', [App\Http\Controllers\Admin\CommandController::class, 'examples'])->middleware(['auth', 'admin'])->name('dashboard.examples');

    Route::put('/dashboard/migrate', [App\Http\Controllers\Admin\CommandController::class, 'migrate'])->middleware(['auth', 'admin'])->name('dashboard.migrate');

    Route::get('/dashboard/commands/{command}', [App\Http\Controllers\Admin\CommandController::class, 'show'])->middleware(['auth', 'admin'])->name('dashboard.commands.show');

    Route::post('/dashboard/commands/execute/{command}', [App\Http\Controllers\Admin\CommandController::class, 'execute'])->middleware(['auth', 'admin'])->name('dashboard.commands.execute');

    Route::get('/dashboard/cruds/', [App\Http\Controllers\Admin\CommandController::class, 'createdCruds'])->middleware(['auth', 'admin'])->name('dashboard.cruds.index');

    Route::get('/dashboard/cruds/{tableName}', [App\Http\Controllers\Admin\CommandController::class, 'showTableData'])->middleware(['auth', 'admin'])->name('dashboard.cruds.data');

    Route::put('/dashboard/{tableName}/delete', [App\Http\Controllers\Admin\CommandController::class, 'tableDelete'])->middleware(['auth', 'admin'])->name('table.delete');
    ";

        $routesContents .= $newRoutes;

        file_put_contents($routesPath, $routesContents);

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

        $controllersPath = app_path('Http/Controllers/Admin');

        if (!file_exists($controllersPath)) {
            mkdir($controllersPath, 0755, true);
        }

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
        $cruds = $this->getStub('Cruds');
        $crudData = $this->getStub('Cruds-data');
        $example = $this->getStub('Examples');

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
        file_put_contents(resource_path("views/admin/cruds.blade.php"), $cruds);
        file_put_contents(resource_path("views/admin/cruds-data.blade.php"), $crudData);
        file_put_contents(resource_path("views/admin/examples.blade.php"), $example);
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
