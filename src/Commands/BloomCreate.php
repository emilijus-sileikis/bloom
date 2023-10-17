<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BloomCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:create
    {name : Class (singular), e.g. Post}
    {attributes : Comma separated list of attributes, e.g. "title:string, content:text"}
    {--skip-relationships : Skip creating relationships}
    {--relation-type= : The type of relationship (used only from dashboard)}
    {--related-model= : The related model name (used only from dashboard)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates CRUD based on the provided name, attributes and relations if needed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $attributes = $this->argument('attributes');
        $relationType = $this->option('relation-type');
        $relatedModel = $this->option('related-model');

        if ($name && $attributes) {
            $this->bloomCreate($name, $attributes, $relationType, $relatedModel);
        } else {
            $this->error("ERROR 0: No name or attributes provided.");
        }
    }

    /**
     * Create a CRUD for the given name and attributes.
     *
     * @param string $name
     * @param string $attributes
     */
    protected function bloomCreate($name, $attributes, $relationType, $relatedModel)
    {
        // File existence check
        if ($this->fileExistence($name)) {
            $this->error("ERROR 1: {$name} CRUD already exists.");
            return;
        }

        // Creating the controller
        $this->controller($name);
        // Creating the model
        $this->model($name, $attributes);
        // Creating the request
        $this->request($name, $attributes);
        // Creating the migration
        $this->makeMigration($name, $attributes);

        if (!$this->option('skip-relationships')) {
            if ($this->confirm('Do you want to create a relationship between this and another model?')) {
                $this->makeRelationship($name, $relationType, $relatedModel);
            }
        }

        if ($relationType && $relatedModel) {
            $this->makeRelationship($name, $relationType, $relatedModel);
        }

        // Adding the resource route
        File::append(base_path('routes/api.php'), "\n".'Route::resource(\'' . Str::plural(strtolower($name)) . "', '{$name}Controller');");

        $this->info("CREATION SUCCESS: {$name} CRUD created successfully.");
    }

    /**
     * Create a controller for the given name.
     *
     * @param string $name
     */
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

    /**
     * Create a model for the given name.
     *
     * @param string $name
     * @param string $attributes
     */
    protected function model($name, $attributes)
    {
        $normalisedAttributes = explode(',', $attributes);

        // Trim to remove any whitespace and add tabs for formatting
        $fillables = array_map(function ($attribute) {
            return "\t\t'" . trim($attribute) . "'";
        }, $normalisedAttributes);

        $template = str_replace(
            [
                '{{modelName}}',
                '{{modelAttributes}}',
            ],
            [
                $name,
                implode(",\n", $fillables),
            ],
            $this->getStub('Model')
        );

        file_put_contents(app_path("/Models/{$name}.php"), $template);
    }

    /**
     * Create a request for the given name.
     *
     * @param string $name
     * @param string $attributes
     */
    protected function request($name, $attributes)
    {
        $attributePairs = explode(", ", $attributes);
        $result = [];

        // Take the names of the attributes and create a string of rules
        foreach ($attributePairs as $pair) {
            list($attrName) = explode(":", $pair);
            $result[] = "\t\t\t'$attrName' => 'required'";
        }

        $template = str_replace(
            [
                '{{modelName}}',
                '{{rules}}',
            ],
            [
                $name,
                implode(",\n", $result),
            ],
            $this->getStub('Request')
        );

        if (!file_exists($path = app_path('/Http/Requests')))
        {
            mkdir($path, 0755, true);
        }

        file_put_contents(app_path("/Http/Requests/{$name}Request.php"), $template);
    }

    /**
     * Create a relationship for the given name.
     *
     * @param string $name
     */
    protected function makeRelationship($name, $relationshipType, $relatedModel)
    {
        $relationships = [];

        if ($relationshipType && $relatedModel) {
            $relationships[] = compact('relatedModel', 'relationshipType');
        } else
        {
            // Multiple relatinoships can be created
            while (true) {
                $relatedModel = $this->ask("Enter the related model name (e.g., User) (Make sure that the model exists!):");

                if (!$relatedModel) {
                    $this->info("No related model provided. Exiting relationship creation.");
                    break;
                }

                $relationshipType = $this->choice(
                    "Select the relationship type (1:1(hasOne), 1:N(hasMany), N:1(belongsTo), N:M(belongsToMany)):",
                    ['1:1', '1:N', 'N:1', 'N:M'],
                    0
                );

                $this->info("You entered the following relationship:");
                $this->table(['Your Model', 'Relationship Type', 'Related Model'], [[$name, $relationshipType, $relatedModel]]);

                if (!$this->confirm("Is this correct?")) {
                    $this->info("Starting over. Please provide the relationship details again.");
                    continue;
                }

                $relationships[] = compact('relatedModel', 'relationshipType');

                if (!$this->confirm("Do you want to create another relationship?")) {
                    break;
                }
            }
        }

        // Handle the relationships
        foreach ($relationships as $relationship) {
            if (!config('command_from_frontend'))
            {
                $relationshipType = $this->mapRelationshipTypes($relationship['relationshipType']);
            }
            $this->updateModelRelationship($name, $relationship['relatedModel'], $relationshipType);
        }
    }

    /**
     * Update the model with the given relationship.
     *
     * @param string $name
     * @param string $relatedModel
     * @param string $relationshipType
     */
    protected function updateModelRelationship($name, $relatedModel, $relationshipType)
    {
        $modelPath = app_path("/Models/{$name}.php");
        $modelContents = file_get_contents($modelPath);
        $relationshipMethodName = strtolower($relatedModel);
        $relatedModelClass = ucfirst($relatedModel);
        $foreignKeyName = strtolower($relatedModel) . '_id';

        // Content that will be added to the model
        $relationshipContent = "\n    public function $relationshipMethodName()\n";
        $relationshipContent .= "    {\n";
        $relationshipContent .= "        return \$this->$relationshipType('App\Models\\$relatedModelClass'";
        $relationshipContent .= ");\n";
        $relationshipContent .= "    }\n";

        // Insert the relationship method into the model class
        $modelContents = preg_replace(
            "/(class $name extends Model\n{)/",
            "$1$relationshipContent",
            $modelContents
        );

        file_put_contents($modelPath, $modelContents);

        $this->info("Updated $name model with $relationshipType relationship to $relatedModel.");

        // Create a migration for foreign keys when needed
        if ($relationshipType === 'belongsTo' || $relationshipType === 'hasOne' || $relationshipType === 'hasMany') {
            $this->createForeignKeyMigration($name, $relatedModel, $foreignKeyName);
        }
        // Create a pivot migration for N:M relationships
        if ($relationshipType === "belongsToMany") {
            $this->createPivotMigration($name, $relatedModel);
        }
    }

    /**
     * Create a migration for the foreign key.
     *
     * @param string $name
     * @param string $relatedModel
     * @param string $foreignKeyName
     */
    protected function createForeignKeyMigration($name, $relatedModel, $foreignKeyName)
    {
        $tableName = strtolower(Str::plural($name));
        $relatedTable = strtolower(Str::plural($relatedModel));
        $time = $this->getCurrentTime();
        $migrationFileName = "{$time}_add_{$foreignKeyName}_to_" . strtolower(Str::plural($name)) . "_table.php";

        $content = str_replace(
            [
                '{{tableName}}',
                '{{columnName}}',
                '{{relatedTable}}',
            ],
            [
                $tableName,
                $foreignKeyName,
                $relatedTable,
            ],
            $this->getStub('RelationMigration')
        );

        file_put_contents(database_path("/migrations/{$migrationFileName}"), $content);
    }

    /**
     * Create a pivot migration for the N:M relationship.
     *
     * @param string $name
     * @param string $relatedModel
     */
    protected function createPivotMigration ($name, $relatedModel)
    {
        $time = $this->getCurrentTime();
        $nameToLower = strtolower($name);
        $relatedNameToLower = strtolower($relatedModel);
        $className = "Create" . Str::studly($name) . Str::studly($relatedModel) . "Table";
        $pivotTableName = strtolower(Str::singular($name)) . "_" . strtolower(Str::singular($relatedModel));
        $table1 = strtolower(Str::singular($name));
        $table2 = strtolower(Str::singular($relatedModel));
        $migrationFileName = "{$time}_create_{$nameToLower}_{$relatedNameToLower}_table.php";

        $content = str_replace(
            [
                '{{migrationClassName}}',
                '{{pivotTableName}}',
                '{{tableName1}}',
                '{{tableName2}}',
            ],
            [
                $className,
                $pivotTableName,
                $table1,
                $table2,
            ],
            $this->getStub('PivotMigration')
        );

        file_put_contents(database_path("/migrations/{$migrationFileName}"), $content);
    }

    /**
     * Create a migration for the given name and attributes.
     *
     * @param string $name
     * @param string $attributes
     * @return string
     */
    protected function makeMigration($name, $attributes)
    {
        $time = $this->getCurrentTime();
        $table = Str::plural(strtolower($name));
        $migrationFileName = "{$time}_create_{$table}_table.php";
        $migrationStub = $this->getStub('Migration');
        $attributeNames = array_map('trim', explode(',', $attributes));

        // Creating the migration schema
        $schema = '';
        foreach ($attributeNames as $attributeName) {
            list($columnName, $columnType) = array_map('trim', explode(':', $attributeName));
            $schema .= "\$table->$columnType('$columnName');\n            ";
        }

        $migrationStub = str_replace([
            'SomeFields',
            'MigrationClass',
            'SomeTable',
        ], [
            $schema,
            'Create' . Str::studly($table) . 'Table',
            $table,
        ], $migrationStub);

        file_put_contents(database_path('migrations/' . $migrationFileName), $migrationStub);

        $this->info("Migration File Updated: $migrationFileName");

        return $migrationFileName;
    }

    /**
     * Get the stub file for the generator.
     *
     * @param string $type
     * @return false|string
     */
    protected function getStub($type)
    {
        return file_get_contents(resource_path("stubs/$type.stub"));
    }

    /**
     * Maps the relationship types.
     *
     * @param string $notation
     * @return string
     */
    protected function mapRelationshipTypes($notation)
    {
        $map = [
            '1:1' => 'hasOne',
            '1:N' => 'hasMany',
            'N:1' => 'belongsTo',
            'N:M' => 'belongsToMany',
        ];

        return $map[$notation];
    }

    /**
     * Get the current time.
     *
     * @return string
     */
    protected function getCurrentTime()
    {
        return now()->format('Y_m_d_His');
    }

    /**
     * Check if the files already exist.
     *
     * @param string $name
     * @return int
     */
    protected function fileExistence($name)
    {
        $controllerExists = file_exists(app_path("/Http/Controllers/{$name}Controller.php"));
        $modelExists = file_exists(app_path("/{$name}.php"));
        $requestExists = file_exists(app_path("/Http/Requests/{$name}Request.php"));

        if ($controllerExists || $modelExists || $requestExists) {
            return 1;
        } else return 0;
    }
}
