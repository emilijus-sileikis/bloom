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
    {--create-view : Creates user sided views, e.g. products-index.blade.php and products-product.blade.php}
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

        if (!$this->checkAttributes($attributes)) {
            $this->error("ERROR 2: {$attributes}. Not supported attribute type found here.");
            return;
        } else {
            $attributeFlags = [];
            $attributes = $this->checkAttributes($attributes, $attributeFlags);
        }

        if ($name && $attributes) {
            $this->bloomCreate($name, $attributes, $relationType, $relatedModel, $attributeFlags);
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
    protected function bloomCreate($name, $attributes, $relationType, $relatedModel, $flags)
    {
        // File existence check
        if ($this->fileExistence($name)) {
            $this->error("ERROR 1: {$name} CRUD already exists.");
            return;
        }

        // Command was launched from terminal
        if (!$this->option('skip-relationships')) {
            if ($this->confirm('Do you want to create a relationship between this and another model?')) {
                $this->makeRelationship($name, $relationType, $relatedModel, $attributes, $flags);
            } else {
                $this->initiateFileCreation($name, $attributes, $relatedModel, $relationType, $flags);
            }
        } else {
            // Create the relation
            $this->makeRelationship($name, $relationType, $relatedModel, $attributes, $flags);
        }

        // Adding the resource routes
        if ($this->option('create-view')) {
            $this->createView($name, $attributes, $relationType, $relatedModel);

            File::append(base_path('routes/web.php'), "\n\n".'    Route::resource(\'' . Str::plural(strtolower($name)) . "', \\App\\Http\\Controllers\\" . $name . "Controller::class)->except(['index', 'show'])->middleware(['auth', 'admin']);");
            File::append(base_path('routes/web.php'), "\n\n".'    Route::get(\'' . Str::plural(strtolower($name)) . "', '\\App\\Http\\Controllers\\" . $name . "Controller@index');");
            File::append(base_path('routes/web.php'), "\n\n".'    Route::get(\'' . Str::plural(strtolower($name)) . "/{".strtolower($name)."}', '\\App\\Http\\Controllers\\" . $name . "Controller@show');");
        } else {
            File::append(base_path('routes/web.php'), "\n\n".'    Route::resource(\'' . Str::plural(strtolower($name)) . "', \\App\\Http\\Controllers\\" . $name . "Controller::class)->middleware(['auth', 'admin']);");
        }

        $this->info("CREATION SUCCESS: {$name} CRUD created successfully.");

        $this->call('route:clear');
    }

    /**
     * Create a controller for the given name.
     *
     * @param string $name
     */
    protected function controller($name, $relatedModel, $relationType, $flags)
    {

        if ($relatedModel)
        {
            $relatedModelUseField = "use App\Models\\" . $relatedModel . ";";
            $relatedModelSelect = "$".strtolower(Str::plural($relatedModel)) . " = " . $relatedModel . "::all();";
            $relatedCompact = ", "."'".strtolower(Str::plural($relatedModel))."'";
            $relatedModelLowerPlural = strtolower(Str::plural($relatedModel));

            if ($relationType === 'belongsToMany')
            {
                $belongsToMany = "$".strtolower($name)."->".strtolower($relatedModel)."()->sync(\$request->input('".$relatedModelLowerPlural."'));";
            } else {$belongsToMany = "";}

            $template = str_replace(
                [
                    '{{modelName}}',
                    '{{modelNameLowerCase}}',
                    '{{modelNameSingularLowerCase}}',
                    '{{relatedModelUseField}}',
                    '{{relatedModelSelect}}',
                    '{{relatedCompact}}',
                    '{{belongsToMany}}',
                    '{{flags}}',
                ],
                [
                    $name,
                    strtolower(Str::plural($name)),
                    strtolower($name),
                    $relatedModelUseField,
                    $relatedModelSelect,
                    $relatedCompact,
                    $belongsToMany,
                    json_encode($flags),
                ],
                $this->getStub('Controller')
            );
        } else
        {
            $template = str_replace(
                [
                    '{{modelName}}',
                    '{{modelNameLowerCase}}',
                    '{{modelNameSingularLowerCase}}',
                    '{{relatedModelUseField}}',
                    '{{relatedModelSelect}}',
                    '{{relatedCompact}}',
                    '{{belongsToMany}}',
                    '{{flags}}',
                ],
                [
                    $name,
                    strtolower(Str::plural($name)),
                    strtolower($name),
                    '',
                    '',
                    '',
                    '',
                    json_encode($flags),
                ],
                $this->getStub('Controller')
            );
        }

        file_put_contents(app_path("/Http/Controllers/{$name}Controller.php"), $template);
    }

    /**
     * Create a model for the given name.
     *
     * @param string $name
     * @param string $attributes
     */
    protected function model($name, $attributes, $relatedModel, $relationType)
    {
        $normalisedAttributes = explode(',', $attributes);

        // Get the names of the attributes
        $attributeNames = array_map(function ($attribute) {
            list($name) = explode(':', $attribute);
            return $name;
        }, $normalisedAttributes);

        // Trim to remove any whitespace and add tabs for formatting
        $fillables = array_map(function ($attribute) {
            return "\t\t'" . trim($attribute) . "'";
        }, $attributeNames);

        // Add the related model id if the relationship exists
        if ($relatedModel && $relationType !== 'belongsToMany')
        {
            $fillables[] = "\t\t'" . strtolower($relatedModel) . "_id'";
        }

        $template = str_replace(
            [
                '{{modelName}}',
                '{{modelAttributes}}',
                '{{modelNameLowerPlural}}'
            ],
            [
                $name,
                implode(",\n", $fillables),
                strtolower(Str::plural($name)),
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
    protected function request($name, $attributes, $flags)
    {
        $attributePairs = explode(", ", $attributes);
        $result = [];

        // Take the names of the attributes and create a string of rules
        foreach ($attributePairs as $pair) {
            list($attrName, $rules) = array_pad(explode(":", $pair, 2), 2, 'string');

            if (isset($flags[$attrName]['isImage']) && $flags[$attrName]['isImage'] === true) {
                $rules = preg_replace('/\bstring\b/', 'image|mimes:jpeg,png,jpg,gif', $rules);
                $rules = preg_replace('/\brequired\b/', 'nullable', $rules);
            }

            if ($rules === 'text') {
                $rules = 'string';
            }

            if ($rules === 'decimal' || $rules === 'float') {
                $rules = 'numeric';
            }

            $result[] = "\t\t\t'$attrName' => '$rules'";
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
    protected function makeRelationship($name, $relationshipType, $relatedModel, $attributes, $flags)
    {
        $relationships = [];

        if ($this->option('skip-relationships')) {

            if ($relationshipType && $relatedModel) {
                $relationships[] = compact('relatedModel', 'relationshipType');
            }

            $this->initiateFileCreation($name, $attributes, $relatedModel, $relationshipType, $flags);
        } else {

            // Multiple relatinoships can be created
            while (true) {
                $relatedModel = $this->ask("Enter the related model name (e.g., User) (Make sure that the model exists!):");

                if (!$relatedModel) {
                    $this->info("No related model provided. Exiting relationship creation.");
                    break;
                }

                $relationshipType = $this->choice(
                    "Select the relationship type (1:1(hasOne), 1:N(hasMany), N:1(belongsTo), N:M(belongsToMany)):",
                    ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany'],
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

            $this->initiateFileCreation($name, $attributes, $relatedModel, $relationshipType, $flags);
        }

        // Handle the relationships
        foreach ($relationships as $relationship) {
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
    protected function updateModelRelationship($name, $relatedModel, $relationshipType, $skipMigration = false)
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
        if ($relationshipType === "belongsToMany" && !$skipMigration) {
            $this->createPivotMigration($name, $relatedModel);

            $this->updateModelRelationship($relatedModel, $name, "belongsToMany", true);
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
        $time = $this->getCurrentTime(-1);
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
        $time = $this->getCurrentTime(-1);
        $nameToLower = strtolower($name);
        $relatedNameToLower = strtolower($relatedModel);

        if (Str::studly($relatedModel) < Str::studly($name)) {
            $class = Str::studly($relatedModel) . Str::studly($name);
            $pivotTableName = strtolower(Str::singular($relatedModel)) . "_" . strtolower(Str::singular($name));
        } else {
            $class = Str::studly($name) . Str::studly($relatedModel);
            $pivotTableName = strtolower(Str::singular($name)) . "_" . strtolower(Str::singular($relatedModel));
        }

        $className = "Create" . $class . "Table";
        $table1 = strtolower(Str::singular($name));
        $table2 = strtolower(Str::singular($relatedModel));
        $table1Plural = Str::plural($table1);
        $table2Plural = Str::plural($table2);

        if ($relatedNameToLower < $nameToLower) {
            $tableName = $relatedNameToLower . "_" . $nameToLower;
        } else {
            $tableName = $nameToLower . "_" . $relatedNameToLower;
        }

        $migrationFileName = "{$time}_create_{$tableName}_table.php";

        $content = str_replace(
            [
                '{{migrationClassName}}',
                '{{pivotTableName}}',
                '{{tableName1}}',
                '{{tableName2}}',
                '{{table1Plural}}',
                '{{table2Plural}}',
            ],
            [
                $className,
                $pivotTableName,
                $table1,
                $table2,
                $table1Plural,
                $table2Plural,
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

        if (config('command_from_frontend'))
        {
            $time = $this->getCurrentTime(1);
        }

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

    protected function createFormView($name, $relatedModel, $relationType)
    {
        if ($relatedModel) {
            $nameLower = strtolower($relatedModel);
            $namePlural = Str::plural($relatedModel);
            $namePluralLower = strtolower($namePlural);
            $nameLowerPlural = strtolower(Str::plural($relatedModel));

            if ($relationType === 'belongsToMany') {
                $scriptName = '';
                $relatedSelect = "
            <div class=\"form-group\">
                <label for=\"{$nameLowerPlural}\">{$namePlural}:</label>
                <select class=\"form-control mb-3 users\" name=\"{$nameLowerPlural}[]\" id=\"{$nameLowerPlural}\" multiple required>
                    @foreach(\${$nameLowerPlural} as \${$nameLower})
                        <option value=\"{{ \${$nameLower}->id }}\">{{ \${$nameLower}->name ?? \${$nameLower}->title ?? \${$nameLower} }}</option>
                    @endforeach
                </select>
            </div>";
            } else {
                $scriptName = '.' . $nameLower . '_id';
                $relatedSelect = '';
            }
        } else {
            $scriptName = '';
            $relatedSelect = '';
            $nameLower = '';
            $namePluralLower = '';
        }

        $entityName = strtolower($name);
        $entityNamePlural = strtolower(Str::plural($name));

        if (!file_exists($path = resource_path('/views/admin/'.$entityNamePlural)))
        {
            mkdir($path, 0755, true);
        }

        $this->placeContents('admin/Edit-form', $entityName, $entityNamePlural, $relatedSelect, $nameLower, $namePluralLower, 'edit', $scriptName);
        $this->placeContents('admin/Create-form', $entityName, $entityNamePlural, $relatedSelect, $nameLower, $namePluralLower, 'create', $scriptName);
    }

    private function createView($name, $attributes, $relationType, $relatedModel)
    {
        $entityName = strtolower($name);
        $entityNamePlural = strtolower(Str::plural($name));
        $entityNameLowerPlural = strtolower($entityNamePlural);

        if (!file_exists($path = resource_path("/views/{$entityNameLowerPlural}/")))
        {
            mkdir($path, 0755, true);
        }

        $content = str_replace(
            [
                '{{ $nameLower }}',
                '{{ $name }}',
            ],
            [
                $entityNamePlural,
                $name,
            ],
            $this->getStub('admin/Index-view')
        );

        $content2 = str_replace(
            [
                '{{ $nameLowerSingular }}',
            ],
            [
                $entityName,
            ],
            $this->getStub('admin/Show-view')
        );

        file_put_contents(resource_path("views/{$entityNameLowerPlural}/index.blade.php"), $content);

        file_put_contents(resource_path("views/{$entityNameLowerPlural}/show.blade.php"), $content2);
    }

    protected function placeContents($stubName, $entityName, $entityNamePlural, $relatedSelect, $nameLower, $namePluralLower, $viewType, $scriptName)
    {
        $formStub = $this->getStub($stubName);

        if ($nameLower === '') {
            $namePluralLower = 'related_models';
            $nameLower = 'related_model';
        }

        $formStub = str_replace([
            '{{ $entityName }}',
            '{{ $entityNamePlural }}',
            '{{ $relatedSelect }}',
            '{{ $relatedLower }}',
            '{{ $relatedLowerSingular }}',
            '{{ $scriptName }}',
        ], [
            $entityName,
            $entityNamePlural,
            $relatedSelect,
            $namePluralLower,
            $nameLower,
            $scriptName,
        ], $formStub);

        file_put_contents(resource_path("views/admin/{$entityNamePlural}/{$viewType}-{$entityName}.blade.php"), $formStub);
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
     * Check if the attributes are supported.
     *
     * @param string $attributes
     * @return bool
     */
    protected function checkAttributes($attributes, &$attributeFlags = [])
    {
        $supportedAttributes = [
            'integer',
            'string',
            'date',
            'text', //padaryt kad butu string validation
            'binary',
            'boolean',
            'decimal', //padaryt kad butu numeric validation
            'float', //padaryt kad butu numeric validation
        ];

        $attributePairs = explode(", ", $attributes);
        $newAttributes = $attributes;
        $attributeFlags = [];

        foreach ($attributePairs as $pair) {
            // Check if the validation rules are provided
            if (str_contains($pair, ':')) {
                list($attrName, $rules) = explode(":", $pair, 2);

                $attributeFlags[$attrName] = [
                    'isImage' => false,
                ];

                // Split the rules by the pipe character
                $individualRules = explode('|', $rules);

                // Validate each provided rule
                foreach ($individualRules as $rule) {
                    $ruleParts = explode(':', $rule);

                    // Extract rule type and value
                    $ruleType = $ruleParts[0];
                    $ruleValue = count($ruleParts) > 1 ? $ruleParts[1] : null;

                    // Handle specific rules
                    switch ($ruleType) {
                        case 'max':
                            if (!is_numeric($ruleValue)) {
                                echo "Invalid 'max' Rule Value\n";
                                return false;
                            }
                            break;

                        case 'min':
                            if (!is_numeric($ruleValue)) {
                                echo "Invalid 'min' Rule Value\n";
                                return false;
                            }
                            break;

                        case 'size':
                            if (!is_numeric($ruleValue)) {
                                echo "Invalid 'size' Rule Value\n";
                                return false;
                            }
                            break;

                        case 'required':
                        case 'numeric':
                        case 'string':
                        case 'binary':
                            break;

                        default:
                            if (!in_array($ruleType, $supportedAttributes)) {
                                echo "Invalid Rule Type\n";
                                return false;
                            }
                            break;
                    }

                    if ($ruleType === 'binary') {
                        $newAttributes = str_replace('binary', 'string', $newAttributes);
                        $attributeFlags[$attrName]['isImage'] = true;
                    }
                }

            } else {
                // If no colon then it's just an attribute
                list($attrName, $attrType) = explode(":", $pair);

                // Check if the attribute type is supported
                if (!in_array($attrType, $supportedAttributes)) {
                    echo "Invalid Attribute Type\n";
                    return false;
                }
            }
        }

        return $newAttributes;
    }

    /**
     * Get the current time.
     *
     * @return string
     */
    protected function getCurrentTime($subtractSeconds = 0)
    {
        $currentTime = now()->subSeconds($subtractSeconds);
        return $currentTime->format('Y_m_d_His');
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

    protected function initiateFileCreation($name, $attributes, $relatedModel, $relationType, $flags)
    {
        // Parse the attributes
        $fixedAttributes = $this->parseAttributes($attributes);

        // Creating the controller
        $this->controller($name, $relatedModel, $relationType, $flags);
        // Creating the model
        $this->model($name, $attributes, $relatedModel, $relationType);
        // Creating the request
        $this->request($name, $attributes, $flags);
        // Creating the migration
        $this->makeMigration($name, $fixedAttributes);
        // Create the form view
        $this->createFormView($name, $relatedModel, $relationType);
    }

    protected function parseAttributes($attributes)
    {
        $parsedAttributes = [];
        $attributeDefinitions = explode(',', $attributes);

        foreach ($attributeDefinitions as $attribute) {

            $attributeDefinition = trim($attribute);
            // Extracting the name
            $name = Str::before($attributeDefinition, ':');
            // Extacting the type and the validations
            $typeAndValidations = Str::after($attributeDefinition, ':');
            // Taking only the type
            $type = Str::before($typeAndValidations, '|');

            // Appending each attribute to the array
            $parsedAttributes[] = "$name:$type";
        }

        // Join them with ','
        return implode(', ', $parsedAttributes);
    }
}
