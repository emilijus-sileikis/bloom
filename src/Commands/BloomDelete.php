<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BloomDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:delete
    {name : Class (singular), e.g. Post}
    {--drop-table : Deletes the associated DB table}
    {--pivot-table= : Pivot table name (required when the Model has N:M relation), e.g. --pivot-table=post_tag}';

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

    /**
     * Delete all CRUD parts with provided name.
     *
     * @param string $name
     */
    protected function bloomDelete($name)
    {

        $tableName = strtolower(Str::plural($name));
        $tableNameSingular = strtolower($name);
        $migrationFiles = scandir(database_path('migrations'));
        $patternForMigration = "/_create_(\w+)_{$tableNameSingular}_table.php/";
        $patternForMigration2 = "/_create_{$tableNameSingular}_(\w+)_table.php/";
        $migrationsToDelete = [];
        $imageDir = public_path('uploads/' . $tableName);
        $models = $this->findRelatedModels($name);

        if ($this->option('drop-table')) {

            if (!Schema::hasTable($tableName)) {
                $this->error("ERROR 4: The table '{$tableName}' does not exist in the database.");
                return;
            }
        }

        if ($this->option('pivot-table')) {

            $pivotTableName = $this->option('pivot-table');

            if (empty($pivotTableName)) {
                $this->error("ERROR 5: The pivot table name is required.");
                return;
            }

            if (!str_contains($this->option('pivot-table'), $tableNameSingular)) {
                $this->error("ERROR 12: Wrong pivot table or model name.");
                return;
            }

            if (!Schema::hasTable($pivotTableName)) {
                $this->error("ERROR 6: The table '{$pivotTableName}' does not exist in the database.");
                return;
            }
        }

        if ($this->option('pivot-table')) {
            try {
                Schema::dropIfExists($pivotTableName);
            } catch (\Exception $e) {
                $this->error("ERROR 7: {$e->getMessage()}");
                return;
            }
        }

        if ($this->option('drop-table')) {
            try {
                Schema::dropIfExists($tableName);
            } catch (\Exception $e) {
                $this->error("ERROR 8: {$e->getMessage()}");
                return;
            }
        }

        $this->removeRelations($models, $name);

        $filesToDelete = [
            app_path("/Http/Controllers/{$name}Controller.php"),
            app_path("/Models/{$name}.php"),
            app_path("/Http/Requests/{$name}Request.php"),
            resource_path("/views/admin/{$tableName}/create-{$tableNameSingular}.blade.php"),
            resource_path("/views/admin/{$tableName}/edit-{$tableNameSingular}.blade.php"),
        ];

        foreach ($migrationFiles as $migrationFile) {
            if (str_contains($migrationFile, "_create_{$tableName}_table.php") ||
                str_contains($migrationFile, "_id_to_{$tableName}_table.php") ||
                preg_match($patternForMigration, $migrationFile) ||
                preg_match($patternForMigration2, $migrationFile)) {
                $filesToDelete[] = database_path('migrations/' . $migrationFile);
                $migrationName = str_replace('.php', '', $migrationFile);
                $migrationsToDelete[] = $migrationName;
            }
        }

        if (!empty($migrationsToDelete) && $this->option('drop-table')) {
            DB::table('migrations')->whereIn('migration', $migrationsToDelete)->delete();
        }

        $this->deleteRoute($tableName);

        foreach ($filesToDelete as $fileToDelete) {
            if (File::exists($fileToDelete)) {
                File::delete($fileToDelete);
            } else {
                $this->error("ERROR 3: File not found: {$fileToDelete}");
                return;
            }
        }

        $adminDir = resource_path("/views/admin/{$tableName}");
        if (File::exists($adminDir)) {
            File::deleteDirectory($adminDir);
        }

        $pubDir = resource_path("/views/{$tableName}");
        if (File::exists($pubDir)) {
            File::deleteDirectory($pubDir);
        }

        if ($this->deleteImages($imageDir)) {
            $this->info("DELETION SUCCESS: {$imageDir} image directory deleted successfully.");
        }

        $this->info("DELETION SUCCESS: {$name} CRUD deleted successfully.");

        $this->call('route:clear');
    }

    /**
     * Remove the route definition from routes/web.php.
     *
     * @param string $name
     */
    function deleteRoute($name) {

        $filePath = base_path('routes/web.php');
        $lines = file($filePath);

        $newContent = '';
        $isRoute = false;

        foreach ($lines as $line) {

            if (str_contains($line, "Route::")) {
                $isRoute = true;
            }

            if ($isRoute && str_contains($line, $name)) {
                continue;
            }

            if ($isRoute && str_contains($line, ');')) {
                $isRoute = false;
            }

            $newContent .= $line;
        }

        $newContent = trim($newContent);

        file_put_contents($filePath, $newContent);
    }

    /**
     * Delete all images from the directory.
     *
     * @param string $dir
     * @return bool
     */
    function deleteImages($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->deleteImages($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Find related models.
     *
     * @param string $name
     */
    function findRelatedModels($name)
    {
        $models = [];
        $pathToModel = app_path("Models/{$name}.php");

        if (file_exists($pathToModel)) {
            $modelContent = file_get_contents($pathToModel);

            preg_match_all('/\'App\\\\Models\\\\[^\\\']*\'/', $modelContent, $matches);

            foreach ($matches[0] as $match) {
                $modelParts = explode('\\', $match);
                $modelLastPart = end($modelParts);
                $modelNames = str_replace("'", '', STR::lower($modelLastPart));
                $models[] = $modelNames;
            }
        }

        return $models;
    }

    /**
     * Remove relations from the model.
     *
     * @param array $models
     */
    function removeRelations($models, $name)
    {
        foreach ($models as $model) {
            $modelName = STR::ucfirst($model);
            $path = app_path("Models/{$modelName}.php");
            $name = STR::lower($name);

            if (file_exists($path)) {
                $content = file_get_contents($path);
                $pattern = '/\bpublic\s+function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*{[^}]*}\s*/';

                if (preg_match($pattern, $content, $matches)) {
                    $foundFunction = $matches[0];
                    $content = str_replace($foundFunction, '', $content);
                    file_put_contents($path, $content);
                }
            }
        }
    }
}
