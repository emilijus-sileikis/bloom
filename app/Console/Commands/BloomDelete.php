<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BloomDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:delete {name : Class (singular), e.g. Post}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all CRUD parts with provided name';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');

        if ($name)
        {
            $this->bloomDelete($name);
        } else
        {
            $this->error("No name provided.");
        }
        return 0;
    }

    protected function bloomDelete($name)
    {
        $tableName = strtolower(Str::plural($name));
        $migrationFiles = scandir(database_path('migrations'));

        $filesToDelete = [
            app_path("/Http/Controllers/{$name}Controller.php"),
            app_path("/{$name}.php"),
            app_path("/Http/Requests/{$name}Request.php"),
        ];

        foreach ($migrationFiles as $migrationFile) {
            if (strpos($migrationFile, "_create_{$tableName}_table.php") !== false) {
                $filesToDelete[] = database_path('migrations/' . $migrationFile);
            }
        }

        if (!empty($filesToDelete)) {
            File::delete($filesToDelete);
        }

        // Remove the route definition from routes/api.php.
        $apiRouteFile = base_path('routes/api.php');
        $routeDefinition = "Route::resource('" . $tableName . "', '{$name}Controller');";

        if (file_exists($apiRouteFile)) {
            $content = file_get_contents($apiRouteFile);
            $content = str_replace($routeDefinition, '', $content);
            file_put_contents($apiRouteFile, $content);
        }
        $this->info($name.' CRUD deleted successfully.');
    }
}
