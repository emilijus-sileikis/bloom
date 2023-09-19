<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BloomCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:create {name : Class (singular), e.g. Post}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates CRUD';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        if ($name)
        {
            $this->bloomCreate($name);
        } else
        {
            $this->error("ERROR 0: No name provided.");
        }
    }

    protected function bloomCreate($name)
    {
        // File existence checks
        $controllerExists = file_exists(app_path("/Http/Controllers/{$name}Controller.php"));
        $modelExists = file_exists(app_path("/{$name}.php"));
        $requestExists = file_exists(app_path("/Http/Requests/{$name}Request.php"));

        if ($controllerExists || $modelExists || $requestExists) {
            $this->error("ERROR 1: {$name} CRUD already exists.");
            return;
        }

        $this->controller($name);
        $this->model($name);
        $this->request($name);

        File::append(base_path('routes/api.php'), "\n".'Route::resource(\'' . Str::plural(strtolower($name)) . "', '{$name}Controller');");

        Artisan::call('make:migration create_' . strtolower(Str::plural($name)) . '_table --create=' . strtolower(Str::plural($name)));

        $this->info("CREATION SUCCESS: {$name} CRUD created successfully.");
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
                '{{modelNameSingularLowerCase}}',
            ],
            [
                $name,
                strtolower(Str::plural($name)),
                strtolower($name),
            ],
            $this->getStub('Controller')
        );

        file_put_contents(app_path("/Http/Controllers/{$name}Controller.php"), $template);
    }

    protected function model($name)
    {
        $template = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Model')
        );

        file_put_contents(app_path("/{$name}.php"), $template);
    }

    protected function request($name)
    {
        $template = str_replace(
            ['{{modelName}}'],
            [$name],
            $this->getStub('Request')
        );

        if (!file_exists($path = app_path('/Http/Requests')))
        {
            mkdir($path, 0755, true);
        }

        file_put_contents(app_path("/Http/Requests/{$name}Request.php"), $template);
    }
}
