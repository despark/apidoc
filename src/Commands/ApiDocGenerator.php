<?php

namespace Despark\Apidoc\Commands;

use Illuminate\Console\Command;
use ReflectionClass;

/**
 * Class ApiDocGenerator
 * @package CE\Console\Commands
 * @author Panayot Balkandzhiyski
 */
class ApiDocGenerator extends Command
{
    
    /**
     * Code example
     * @apiDesc Send a reset link to the given user.
     * @apiParam string $email required | Email address for reset of password
     * @apiParam string $password required
     *
     * @apiErr 422 | Validation errors
     * @apiErr 422 | Unauthorized access
     * @apiResp 200 | User is logged in
     */
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apidoc:generate';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate api documentation from controllers';
    
    
    /**
     * generated code for swagger
     * @var array
     */
    protected $swagger;
    
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
     * @return mixed
     */
    public function handle()
    {
        if ( ! env('APP_URL')) {
            return $this->error('Please, set APP_URL in your env file');
        }
        $this->config = $this->setMainSwaggerInfo();
        foreach ($this->getRouteControllerData() as $controller => $methods) {
            
            //write controller resource
            $currentControllerClassName = current($methods);
            foreach ($methods as $method) {
                $this->info($currentControllerClassName['controllerClassName'].'@'.$method['actionName']);
                $this->setTag($method);
                $this->setPaths($method);
            }
            
        }
        
        //write doc text to file
        $this->writeToFile();
    }
    
    /**
     * Get controllers data from routes
     * @return mixed
     */
    protected function getRouteControllerData()
    {
        $controllers = [];
        foreach (\Route::getRoutes() as $route) {
            $controllerName = explode('@', $route->getActionName());
            
            $controllerNameSpace = array_get($controllerName, 0);
            $actionName = array_get($controllerName, 1);
            
            $controllerClassName = explode('\\', $controllerNameSpace);
            $controllerClassName = end($controllerClassName);
            
            if ($controllerClassName === 'Closure') {
                continue;
            }
            
            $controllers[$controllerNameSpace][] = [
                'host'                => $route->domain(),
                'method'              => implode('|', $route->methods()),
                'uri'                 => $route->uri(),
                'name'                => $route->getName(),
                'controllerNameSpace' => $controllerNameSpace,
                'controllerClassName' => $controllerClassName,
                'actionName'          => $actionName,
            ];
        }
        
        return $controllers;
    }
    
    /**
     * Set main data for swagger. Version, title ,etc.
     */
    protected function setMainSwaggerInfo()
    {
        $this->swagger['swagger'] = '2.0';
        $this->swagger['info'] =
            [
                'description' => config('apidoc.apiDescription'),
                'version'     => config('apidoc.apiVersion'),
                'title'       => config('apidoc.apiTitle'),
            ];
        
        $this->swagger['host'] = env('APP_URL');
        $this->swagger['basePath'] = config('apidoc.apiBasePath');
        $this->swagger['tags'] = [];
    }
    
    /**
     * Set all tags
     * @param $methods
     */
    protected function setTag($methods)
    {
        $tag = [
            'name'        => str_replace(config('apidoc.apiBasePath'), '', array_get($methods, 'uri', '')),
            'description' => array_get($methods, 'controllerClassName', ''),
        ];
        
        $this->swagger['tags'][] = $tag;
        // add new tag
    }
    
    /**
     * Set Scheme
     * @return array
     */
    protected function setSchemes()
    {
        return $this->swagger['schemes'];
    }
    
    /**
     * Set path
     * @param $method
     * @return array|void
     */
    protected function setPaths($method)
    {
        $docArray = $this->methodCommentToArray($method);
        
        if ( ! count($docArray)) {
            return;
        }
        
        $methodType = strtolower(str_replace('|HEAD', '', $method['method']));
        
        $path = [
            'tags'        => [
                str_replace('CE\Http\Controllers', '', array_get($method, 'controllerNameSpace', '')),
            ],
            'summary'     => array_get($docArray, 'desc'),
            'description' => array_get($method, 'controllerClassName', ''),
            'operationId' => '',
            'consumes'    => [
                'application/json',
                'application/xml',
            ],
            'produces'    => [
                "application/xml",
                "application/json",
            ],
            'parameters'  => $this->setParams($docArray, $method),
            'responses'   => $this->setResponses($docArray),
        ];
        
        
        return $this->swagger['paths'][str_replace('api/v1', '', array_get($method, 'uri', ''))][$methodType] = $path;
    }
    
    /**
     * Set method params
     * @param $docArray
     * @param $method
     * @return array
     */
    protected function setParams($docArray, $method)
    {
        $params = [];
        if (preg_match_all('/\{(.*?)\}/', $method['uri'], $paramsInPath)) {
            foreach ($paramsInPath[1] as $param) {
                $param = str_replace('?', '', $param);
                $field = [
                    'name'        => $param,
                    'type'        => 'integer',
                    'description' => 'Param in path',
                    'in'          => 'path',
                ];
                if (strpos($param, '?') === false) {
                    $field['required'] = true;
                }
                $params[$param] = $field;
            }
        }
        
        foreach (array_get($docArray, 'params', []) as $paramString) {
            $paramOptions = $this->setParam($paramString, $method, $params);
            if ($paramOptions['type'] === 'file') {
                if ( ! isset($paramOptions['name']) OR empty($paramOptions['name'])) {
                    $paramOptions['name'] = $paramOptions['type'];
                }
            }
            $params[isset($paramOptions['name']) ? $paramOptions['name'] : ''] = $paramOptions;
        }
        
        // We need to reset the array to numeric in order for json to create it as array.
        return array_values($params);
    }
    
    /**
     * Set param
     * @param       $paramDocString
     * @param       $method
     * @param array $params Already built params from route.
     * @return array
     * @throws \Exception
     */
    protected function setParam($paramDocString, $method, &$params)
    {
        $options = [];
        
        $descMessage = explode('|', $paramDocString);
        $descMessage = array_get($descMessage, 1);
        $paramDocString = str_replace('|'.$descMessage, '', $paramDocString);
        $options['description'] = trim($descMessage);
        
        // get type
        if (strpos($paramDocString, 'string') !== false) {
            $options['type'] = 'string';
            $paramDocString = str_replace('string', '', $paramDocString);
        } // get type
        elseif (strpos($paramDocString, 'integer') !== false) {
            $options['type'] = 'integer';
            $paramDocString = str_replace('integer', '', $paramDocString);
        } // get type
        elseif (strpos($paramDocString, 'bool') !== false) {
            $options['type'] = 'integer';
            $options['description'] .= ' | Boolean';
            $paramDocString = str_replace('bool', '', $paramDocString);
        } // get type
        elseif (strpos($paramDocString, 'boolean') !== false) {
            $options['type'] = 'integer';
            $options['description'] .= ' | Boolean';
            $paramDocString = str_replace('boolean', '', $paramDocString);
        } // get type
        elseif (strpos($paramDocString, 'password') !== false) {
            $pos = strpos($paramDocString, 'password');
            $options['type'] = 'string';
            $options['format'] = 'password';
            $paramDocString = substr_replace($paramDocString, '', $pos, strlen('password'));
        } // get type
        elseif (strpos($paramDocString, 'double') !== false) {
            $options['type'] = 'number';
            $options['format'] = 'double';
            $paramDocString = str_replace('double', '', $paramDocString);
        } // get type
        elseif (strpos($paramDocString, 'array') !== false) {
            $options['type'] = 'array';
            $paramDocString = str_replace('array', '', $paramDocString);
        } elseif (strpos($paramDocString, 'file') !== false) {
            $options['type'] = 'file';
            $paramDocString = str_replace('file', '', $paramDocString);
        } // get type
        
        // get required
        if (strpos($paramDocString, 'required') !== false) {
            $options['required'] = true;
            $paramDocString = str_replace('required', '', $paramDocString);
        }
        
        // parameter send from
        $options['in'] = 'formData';
        
        $paramDocString = str_replace('in_path', '', $paramDocString, $count);
        if ($count) {
            $options['in'] = 'path';
        }
        
        $count = 0;
        
        $paramDocString = str_replace('in_query', '', $paramDocString, $count);
        if ($count) {
            $options['in'] = 'query';
        }
        
        // get parameter
        $paramDocString = trim($paramDocString);
        if (strpos($paramDocString, '$') !== false) {
            $paramDocString = str_replace('$', '', $paramDocString);
            $options['name'] = $paramDocString;
            // We override the param if it exists already
            if (isset($params[$paramDocString])) {
                unset($params[$paramDocString]);
            }
        }
        
        if ( ! isset($options['type']) || ! $options['type']) {
            throw new \Exception('Missing type for '.$method['controllerNameSpace'].'@'.$method['actionName'].' with param '.$options['name']);
        }
        
        return $options;
    }
    
    /**
     * Set response
     * @param $paramDocString
     * @return array
     */
    protected function setResponses($paramDocString)
    {
        $responses = [];
        foreach (array_get($paramDocString, 'responses', []) as $response) {
            $responseMessage = explode('|', $response);
            $responseMessage = array_get($responseMessage, 1);
            $responseCode = str_replace('|'.$responseMessage, '', $response);
            
            $responses[trim($responseCode)]['description'][] = trim($responseMessage);
        }
        
        return $responses;
        
    }
    
    /**
     * Get documentation to array
     * @param $method
     * @return array
     */
    protected function methodCommentToArray($method)
    {
        $actionMethodName = array_get($method, 'actionName', null);
        $controllerNameSpace = array_get($method, 'controllerNameSpace', null);
        
        if ((empty($actionMethodName)) || ( ! $controllerNameSpace)) {
            return [];
        }
        
        $documentationArray = [];
        $reflector = new ReflectionClass($controllerNameSpace);
        if ( ! $reflector->hasMethod($actionMethodName)) {
            return [];
        }
        // to get the Method DocBlock
        $doc = $reflector->getMethod($actionMethodName)->getDocComment();
        
        foreach (explode("\n", $doc) as $row) {
            $this->commentRowReader($row, '@apiDesc', 'desc', $documentationArray);
            $this->commentRowReader($row, '@apiParam', 'params', $documentationArray);
            $this->commentRowReader($row, '@apiErr', 'responses', $documentationArray);
            $this->commentRowReader($row, '@apiResp', 'responses', $documentationArray);
        }
        
        
        return $documentationArray;
        
    }
    
    
    /**
     * Read row data and set data into arrays
     * @param string $row data from the comment
     * @param string $needle needed parameter.
     * @param string $name what name should we use for array key
     * @param array  $documentationArray
     */
    private function commentRowReader($row, $needle, $name, &$documentationArray)
    {
        $data = explode($needle, $row);
        if (count($data >= 1)) {
            $dataString = trim(array_get($data, 1));
            if ( ! empty($dataString)) {
                if ($name === 'desc') {
                    $documentationArray[$name] = $dataString;
                } else {
                    $documentationArray[$name][] = $dataString;
                }
            }
        }
    }
    
    /**
     * Write swagger data to json file
     */
    protected function writeToFile()
    {
        $fileDir = storage_path('appDoc');
        \File::deleteDirectory($fileDir);
        \File::makeDirectory($fileDir);
        \File::put($fileDir.DIRECTORY_SEPARATOR.'resource.json', json_encode($this->swagger));
    }
    
    
}
