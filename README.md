# apidoc
Laravel 5 api documentation generator, based on [Swagger](http://swagger.io/) 

**apidoc** use just a few lines of code added to your controllers methods.  

##Installation
Require this package with composer using the following command:

    composer require despark/apidoc
     
After that add to the providers array in config/app.php
 
    Despark\Apidoc\ApiDocServiceProvider::class,
    
Then call

    php artisan vendor:publish

Now you are ready to use the generator.

##Usage
If you do all steps mentioned above than the file /yourapp/config/apidoc.php should be generated for you. 

    <?php
    return [
        'apiVersion'     => '1.0.0',
        'apiTitle'       => 'My api',
        'apiDescription' => 'My api',
        'apiBasePath'    => '/api/v1',
    ];

All those parameters are displayed on swagger api doc page so you can change them to fit your settings. 

####Controllers and methods
Every single method that has been documented in **apidoc** documentation way and present in laravel's routs.php will be parsed and shown in the api documentation.
Method documentation example:

       
        /**
         * @apiDesc A description of the method
         * @apiParam string $parameterName required in_path | Description of the parameterName  
         * @apiParam array $parameterName2 | Description2 of the parameterName
         *
         * @apiErr 422 | Validation errors
         * @apiErr 403 | Unauthorized access
         * @apiResp 200 | Whatever message is send from backend on sucess
         */
        public function index($id, DesignRequest $request){}

**Notice:** Every single "@api" element and description should be on a single row.
- @apiDesc - A description of the method
- @apiParam - There is no limit of parameters. Parameters can be required or not required. If **word required** is typed the parameter is marked as **required**. Check the example above. 

    Params types:
    - string
    - file - uploadable file
    - array
    - bool or boolean (both are available and equal)
    - integer
    - password
    - double 

- @apiErr XXX - can have more than one error messages that are returned in response. XXX is the http status code.
- @apiResp XXX - the response data of the api call. XXX is the http status code.
- url parameters (such id in the example), are taken automatically and declared as integer
  
You can set how the parameters are send and there are 3 options:

- formData - this is the default option and it is not needed to be mention in the comments
- in_path - where the parameter value is actually part of the operation's URL. For example, in /items/{itemId}, the path parameter is itemId.
- in_query - Parameters that are appended to the URL. For example, in /items?id=123, the query parameter is id.
- more information at [Swagger specification page](http://swagger.io/specification/) 

**NOTICE:**
Everything after **"|"** symbol is assumed as a description text. So use just one **"|"** symbol on a row. 

####Command
After everything is setup, the controllers are declared in route.php file and there are comments in the controllers we can call the command.

    php artisan apidoc:generate

That's it. Now you can access your new documentation at APP_URL/docs#/

**Notice** APP_URL is used for swagger integration. If it is not set in your .env file, the command will return a message, asking you to set up APP_URL.      
**Notice** APP_URL example: http://example.info/       