
Laravel 10 CRUD Generator
<a href="https://packagist.org/packages/emilijus/bloom">Download here </a>
### Requirements
    Laravel >= 10
    PHP >= 8.2

## Installation

1. Run:
    ```
    composer create-project laravel/laravel name
    ```

2. Set the necessary permissions for the folders (storage, app, resources). Could be this (but not recommended):
    ```
    sudo chmod -R 777 folderName
    ```

3. Run:
    ```
    composer require emilijus/bloom:dev-master
    ```

4. Publish the vendor files:
    ```
    php artisan vendor:publish --tag=bloom
    ```
  

5. Connect to your database (inside the .env file) and make migration:
    ```
    php artisan migrate
    ```
6. Install the scaffolding of the program (it is recommended to use blade as the Breeze stack and PHPUnit option for testing since others were not tested just yet! ):
    ```
    php artisan bloom:install
    ```
    
## Commands

#### Install command:
TBA

#### Create command:
TBA

#### Delete command:
TBA

### Supported Field Types

These fields are supported for command input:

* string - for shorter text fields (name, title, etc.)
* integer - for int values
* date - for dates/timestamps
* text - for longer text fields (description)
* binary - for images
* boolean - for checkboxes
* decimal - for decimal values
* float - for float values

### Supported Validation Parameters

These validation parameters are supported for command input:

* max: - for maximum character limit/number value
* min: - for minimum character limit/number value
* size: - for maximum picture size
* required - for the field to be required (this is default if nullable is not set!!)

For images the mime types are set automatically (for now) they are: jpeg, png, jpg and gif.

### CRUD creation example
TBA
