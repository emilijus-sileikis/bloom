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
     * Execute the console command.
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
            if (str_contains($migrationFile, "_create_{$tableName}_table.php")) {
                $filesToDelete[] = database_path('migrations/' . $migrationFile);
            }
        }

        // Remove the route definition from routes/api.php.
        $apiRouteFile = base_path('routes/api.php');
        $routeDefinition = "Route::resource('" . $tableName . "', '{$name}Controller');";

        if (file_exists($apiRouteFile)) {
            $content = file_get_contents($apiRouteFile);
            $content = str_replace($routeDefinition, '', $content);
            file_put_contents($apiRouteFile, $content);
        } else {
            $this->error("Error locating api.php file.");
            return;
        }

        foreach ($filesToDelete as $fileToDelete) {
            if (File::exists($fileToDelete)) {
                File::delete($fileToDelete);
            } else {
                $this->error("ERROR 2: File not found: {$fileToDelete}");
                return;
            }
        }

        $this->info("DELETION SUCCESS: {$name} CRUD deleted successfully.");
    }
}
