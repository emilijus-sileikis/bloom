
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
    composer require emilijus/bloom
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

The installation command: ```php artisan bloom:install``` can be used for mainly three purposes. 

The first one is the initial Admin Dashboard installation which is the first step in order to start using the generator. 

The second purpose is to update the user table to add back the 'is_admin' flag if the database was refreshed, and the row got removed. This is done by running the command with a '--update-user-table flag': ```php artisan bloom:install --update-user-table``` inside the terminal. This will add the 'is_admin' column back to the user table.

The third and final use is to create a new user with administrator privileges. This is done by running the command with a '--create-admin' flag: ```php artisan bloom:install --create-admin```. After providing the required information, a new user will be created with administrator privileges.

Of course, the two flags can be used simultaneously to do both actions at the same time to save time.

#### Create command:

The create command is used to create a new CRUD module. It can be used by running the command: ```php artisan bloom:create``` inside the terminal. This will start the process of creating a new CRUD module. However, the command requires some arguments to be passed in order to work properly. The arguments are as follows:

* Name: The name of the CRUD module. This will be used to create the model, controller, migration, and views. The name MUST have the first letter in uppercase as well as be in a singular form.
* Attributes: The fields that the CRUD module will have including the validations if necessary. This will be used to create the migration and views.

The creation command also has a few flags that can be used to customize the process of creating a CRUD module. The flags are as follows:

* --create-view: This flag is used if a user wants to generate a carcass of front-ended views for the CRUD that a regular user could see.
* --skip-relationships: This flag is used if a user prefers to skip creating relations between two models and just create a carcass.

#### Delete command:

The delete command is used to remove a CRUD module. It can be used by running the command: ```php artisan bloom:delete``` inside the terminal. This will start the process of removing the models, views, controllers, and even migrations. The command requires only one argument: Name to be passed in order to work properly.

The deletion command also has a few flags that can be used to customize the process of deleting a CRUD module. The flags are as follows:

* --drop-table: This flag is used if a user wants to delete the associated database table.
* --pivot-table=: This flag is used if a N:M relation was made and there is a need to delete the pivot table, e.g. ```--pivot-table=post_tag```.

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
* required - for the field to be required
* nullable - for the field to be not required (this is the default if required is not set!)

For images the mime types are set automatically (for now) they are: jpeg, png, jpg and gif.
<strong>For images, it is recommended to use nullable (do not provide the required parameter when creating a CRUD with an image attribute).</strong>
For example: ```php artisan bloom:create Post "title:string|required|max:30, description:text|required|max:255, photo:binary|max:20000" --create-view```.

### CRUD creation via terminal example

Creating a CRUD via terminal is as simple as typing in a few sentences. Below you can find a step-by-step guide on how to create a full CRUD via the terminal commands. In this example, we are going to create a <b>one-to-many</b> relation between an <b>Author</b> and a <b>Post</b>. We will also use the <em>--create-view</em> flag to create a simple front-end view for our posts.

Here is what to do:

* Type in the <b>create command</b> and the required parameters: <em>php artisan bloom:create Author "name:string|required|max:30"</em>.
* For now, select no when asked if we want to create a relation.
* Type in the create command again and create the Post CRUD:<em>php artisan bloom:create Post "title:string|required|max:30, description:text|required|max:255, photo:binary|max:20000" --create-view</em>.
* Select yes when asked if you want to create a relationship.
* Enter the name of the CRUD we created earlier:.<em>Author</em>.
* For the relation type, choose the one that best suits your needs: <em>N:1</em>. The terminal will show the selected relation and ask if this is what you want to select. In our case it will show: <em>Post belongsTo Author</em> which is what we need, so we type in <b>yes</b>.
* Select no when asked if we want to create another relation.

That is it! Now all you have to do is run the migration and everything will appear. To run the migration you can type: <em>php artisan migrate</em> into the terminal.

Now, let us go to the <b>pages</b> page and create a new author and some posts. To do that just click on the <b>View</b> button and then select <b>Create</b> in the newly opened screen. After creating the author and a post we can proceed to the created view to see if the post appears on the frontend. Type in <em>/posts</em> in your website URL or just use the <em>Show</em> button in the pages section to see the created posts. From here you can edit the views as you want to fit your needs.

### CRUD creation via dashboard example
TBA
