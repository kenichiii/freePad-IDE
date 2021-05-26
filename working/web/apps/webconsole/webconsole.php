<?php

    //ensure we have right include path

    set_include_path(implode(PATH_SEPARATOR, [

            realpath(dirname(__FILE__) )

        ]));



require_once '../safescript.php';    





// Web Console v0.9.5 (2014-02-18)

// (c) 2007-2014 Coderico (http://www.coderico.com)

//

// Author: Nickolay Kovalev (http://resume.nickola.ru)

// GitHub: https://github.com/nickola/web-console

// URL: http://www.web-console.org



// Single-user credentials

// Example: $USER = 'user'; $PASSWORD = 'password';

$USERNAME = function_exists('posix_getpwuid') ? @posix_getpwuid(@posix_geteuid()) : exec('whoami'); 

$USER = 'test';//is_array($USERNAME) ? $USERNAME['name'] : $USERNAME;

$PASSWORD = 'test';

    

// Multi-user credentials

// Example: $ACCOUNTS = array('user1' => 'password1', 'user2' => 'password2');

$ACCOUNTS = array();



// Home directory (absolute or relative path)

$HOME_DIRECTORY = '';



// Code below is automatically generated from different components

// For more information see: https://github.com/nickola/web-console

//

// Used components:

//   - jQuery JavaScript Library: https://github.com/jquery/jquery

//   - jQuery Mouse Wheel Plugin: https://github.com/brandonaaron/jquery-mousewheel

//   - jQuery Terminal Emulator: https://github.com/jcubic/jquery.terminal

//   - PHP JSON-RPC 2.0 Server/Client Implementation: https://github.com/sergeyfast/eazy-jsonrpc

//   - Normalize.css: https://github.com/necolas/normalize.css





    /**

     * JSON RPC Server for Eaze

     *

     * Reads $_GET['rawRequest'] or php://input for Request Data

     * @link http://www.jsonrpc.org/specification

     * @link http://dojotoolkit.org/reference-guide/1.8/dojox/rpc/smd.html

     * @package    Eaze

     * @subpackage Model

     * @author     Sergeyfast

     */

    class BaseJsonRpcServer {



    	const ParseError	 = -32700;



        const InvalidRequest = -32600;



        const MethodNotFound = -32601;



        const InvalidParams  = -32602;



        const InternalError  = -32603;



        /**

         * Exposed Instance

         * @var object

         */

        protected $instance;



        /**

         * Decoded Json Request

         * @var object|array

         */

        protected $request;



        /**

         * Array of Received Calls

         * @var array

         */

        protected $calls = array();



        /**

         * Array of Responses for Calls

         * @var array

         */

        protected $response = array();



        /**

         * Has Calls Flag (not notifications)

         * @var bool

         */

        protected $hasCalls = false;



        /**

         * Is Batch Call in using

         * @var bool

         */

        private $isBatchCall = false;



        /**

         * Hidden Methods

         * @var array

         */

        protected $hiddenMethods = array(

            'execute', '__construct'

        );



        /**

         * Content Type

         * @var string

         */

        public $ContentType = 'application/json';



        /**

         * Alow Cross-Domain Requests

         * @var bool

         */

        public $IsXDR = true;



        /**

         * Error Messages

         * @var array

         */

        protected $errorMessages = array(

            self::ParseError       => 'Parse error'

            , self::InvalidRequest => 'Invalid Request'

            , self::MethodNotFound => 'Method not found'

            , self::InvalidParams  => 'Invalid params'

            , self::InternalError  => 'Internal error'

        );





        /**

         * Cached Reflection Methods

         * @var ReflectionMethod[]

         */

        private $reflectionMethods = array();



        /**

         * Validate Request

         * @return int error

         */

        private function getRequest() {

            $error = null;



            do {

                if ( array_key_exists( 'REQUEST_METHOD', $_SERVER ) && $_SERVER['REQUEST_METHOD'] != 'POST' ) {

                    $error = self::InvalidRequest;

                    break;

                };



                $request       = !empty( $_GET['rawRequest'] ) ? $_GET['rawRequest'] : file_get_contents( 'php://input' );

                $this->request = json_decode( $request, false );

                

                

                

                if ( $this->request === null ) {

                    $error = self::ParseError;

                    break;

                }



                if ( $this->request === array() ) {

                    $error = self::InvalidRequest;

                    break;

                }



                // check for batch call

                if ( is_array( $this->request ) ) {

                    $this->calls       = $this->request;

                    $this->isBatchCall = true;

                } else {

                    $this->calls[] = $this->request;

                }

            } while ( false );



            return $error;

        }





        /**

         * Get Error Response

         * @param int   $code

         * @param mixed $id

         * @param null  $data

         * @return array

         */

        private function getError( $code, $id = null, $data = null ) {

            return array(

                'jsonrpc' => '2.0'

                , 'error' => array(

                    'code'      => $code

                    , 'message' => isset( $this->errorMessages[$code] ) ? $this->errorMessages[$code] : $this->errorMessages[self::InternalError]

                    , 'data'    => $data

                )

                , 'id' => $id

            );

        }





        /**

         * Check for jsonrpc version and correct method

         * @param object $call

         * @return array|null

         */

        private function validateCall( $call ) {

            $result = null;

            $error  = null;

            $data   = null;

            $id     = is_object( $call ) && property_exists( $call, 'id' ) ? $call->id : null;

            do {

                if ( !is_object( $call ) ) {

                    $error = self::InvalidRequest;

                    break;

                }



                // hack for inputEx smd tester

                if ( property_exists( $call, 'version' ) ) {

                    if ( $call->version == 'json-rpc-2.0' ) {

                        $call->jsonrpc = '2.0';

                    }

                }



                if ( !property_exists( $call, 'jsonrpc' ) || $call->jsonrpc != '2.0' ) {

                    $error = self::InvalidRequest;

                    break;

                }



                $method = property_exists( $call, 'method' ) ? $call->method : null;

                if ( !$method || !method_exists( $this->instance, $method ) || in_array( strtolower( $method ), $this->hiddenMethods ) ) {

                    $error = self::MethodNotFound;

                    break;

                }



                if ( !array_key_exists( $method, $this->reflectionMethods ) ) {

                    $this->reflectionMethods[$method] = new ReflectionMethod( $this->instance, $method );

                }



                /** @var $params array */

                $params     = property_exists( $call, 'params' ) ? $call->params: null;

                $paramsType = gettype( $params );

                if ( $params !== null && $paramsType != 'array' && $paramsType != 'object' ) {

                    $error = self::InvalidParams;

                    break;

                }



                // check parameters

                switch( $paramsType ) {

                    case 'array':

                        $totalRequired = 0;

                        // doesn't hold required, null, required sequence of params

                        foreach( $this->reflectionMethods[$method]->getParameters() as $param ) {

                            if ( !$param->isDefaultValueAvailable() ) {

                                $totalRequired ++;

                            }

                        }



                        if ( count( $params ) < $totalRequired ) {

                            $error = self::InvalidParams;

                            $data  = sprintf( 'Check numbers of required params (got %d, expected %d)', count( $params ), $totalRequired  );

                        }

                        break;

                    case 'object':

                        foreach( $this->reflectionMethods[$method]->getParameters() as $param ) {

                            if ( !$param->isDefaultValueAvailable()  && !array_key_exists( $param->getName(), $params ) ) {

                                $error = self::InvalidParams;

                                $data  = $param->getName() . ' not found';



                                break 3;

                            }

                        }

                        break;

                    case 'NULL':

                        if ( $this->reflectionMethods[$method]->getNumberOfRequiredParameters() > 0  ) {

                            $error = self::InvalidParams;

                            $data  = 'Empty required params';

                            break 2;

                        }

                        break;

                }



            } while( false );



            if ( $error ) {

                $result = array( $error, $id, $data );

            }



            return $result;

        }





        /**

         * Process Call

         * @param $call

         * @return array|null

         */

        private function processCall( $call ) {

            $id     = property_exists( $call, 'id' ) ? $call->id : null;

            $params = property_exists( $call, 'params' ) ? $call->params : array();

            $result = null;



            try {

                // set named parameters

                if ( is_object( $params ) ) {

                    $newParams = array();

                    foreach($this->reflectionMethods[$call->method]->getParameters() as $param) {

                        $paramName    = $param->getName();

                        $defaultValue = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;

                        $newParams[]  = property_exists( $params, $paramName ) ? $params->$paramName : $defaultValue;

                    }



                    $params = $newParams;

                }



                // invoke

                $result = $this->reflectionMethods[$call->method]->invokeArgs( $this->instance, $params );

            } catch ( Exception $e ) {

                return $this->getError( $e->getCode(), $id, $e->getMessage() );

            }



            if ( !$id ) {

                return null;

            }



            return array(

                'jsonrpc'  => '2.0'

                , 'result' => $result

                , 'id'     => $id

            );

        }





        /**

         * Create new Instance

         * @param object $instance

         */

        public function __construct( $instance = null ) {

            if ( get_parent_class( $this ) ) {

                $this->instance = $this;

            } else {

                $this->instance = $instance;

                $this->instance->errorMessages = $this->errorMessages;

            }

        }





        /**

         * Handle Requests

         */

        public function Execute() {

            do {

                // check for SMD Discovery request

                if ( array_key_exists( 'smd', $_GET ) ) {

                    $this->response[]   = $this->getServiceMap();

                    $this->hasCalls    = true;

                    break;

                }



                $error = $this->getRequest();

                if ( $error ) {

                    $this->response[] = $this->getError( $error );

                    $this->hasCalls   = true;

                    break;

                }



                foreach( $this->calls as $call ) {

                    $error = $this->validateCall( $call );

                    if ( $error ) {                        

                        $this->response[] = $this->getError( $error[0], $error[1], $error[2] );

                        $this->hasCalls   = true;

                    } else {                        

                        $result = $this->processCall( $call );

                        

                        if ( $result ) {

                            $this->response[] = $result;

                            $this->hasCalls   = true;

                        }

                    }

                }

            } while( false );



            // flush response

            if ( $this->hasCalls ) {

                if ( !$this->isBatchCall ) {

                    $this->response = reset( $this->response );

                }



                // Set Content Type

                if ( $this->ContentType ) {

                    header( 'Content-Type: '. $this->ContentType );

                }



                // Allow Cross Domain Requests

                if ( $this->IsXDR ) {

                    header( 'Access-Control-Allow-Origin: *' );

                    header( 'Access-Control-Allow-Headers: x-requested-with, content-type' );

                }



                echo json_encode( $this->response );

                

                $this->resetVars();

            }

        }





        /**

         * Get Doc Comment

         * @param $comment

         * @return string|null

         */

        private function getDocDescription( $comment ) {

            $result = null;

            if (  preg_match('/\*\s+([^@]*)\s+/s', $comment, $matches ) ) {

                $result = str_replace( '*' , "\n", trim( trim( $matches[1], '*' ) ) );

            }



            return $result;

        }





        /**

         * Get Service Map

         * Maybe not so good realization of auto-discover via doc blocks

         * @return array

         */

        private function getServiceMap() {

            $rc     = new ReflectionClass( $this->instance );

            $result = array(

                'transport'     => 'POST'

                , 'envelope'    => 'JSON-RPC-2.0'

                , 'SMDVersion'  => '2.0'

                , 'contentType' => 'application/json'

                , 'target'      => !empty( $_SERVER['REQUEST_URI'] ) ? substr( $_SERVER['REQUEST_URI'], 0,strpos( $_SERVER['REQUEST_URI'], '?') ) : ''

                , 'services'    => array()

                , 'description' => ''

            );



            // Get Class Description

            if ( $rcDocComment = $this->getDocDescription( $rc->getDocComment()) ) {

                $result['description'] = $rcDocComment;

            }



            foreach( $rc->getMethods() as $method ) {

                /** @var ReflectionMethod $method */

                if ( !$method->isPublic() || in_array( strtolower( $method->getName() ), $this->hiddenMethods ) ) {

                    continue;

                }



                $methodName = $method->getName();

                $docComment = $method->getDocComment();



                $result['services'][$methodName] = array( 'parameters' => array() );



                // set description

                if ( $rmDocComment = $this->getDocDescription( $docComment ) ) {

                    $result['services'][$methodName]['description'] = $rmDocComment;

                }



                // @param\s+([^\s]*)\s+([^\s]*)\s*([^\s\*]*)

                $parsedParams = array();

                if ( preg_match_all('/@param\s+([^\s]*)\s+([^\s]*)\s*([^\n\*]*)/', $docComment, $matches ) ) {

                    foreach( $matches[2] as $number => $name ) {

                        $type = $matches[1][$number];

                        $desc = $matches[3][$number];

                        $name = trim( $name, '$' );



                        $param = array( 'type' => $type, 'description' => $desc );

                        $parsedParams[$name] = array_filter( $param );

                    }

                };



                // process params

                foreach ( $method->getParameters() as $parameter ) {

                    $name  = $parameter->getName();

                    $param = array( 'name' => $name, 'optional' => $parameter->isDefaultValueAvailable() );

                    if ( array_key_exists( $name, $parsedParams ) ) {

                        $param += $parsedParams[$name];

                    }



                    if ( $param['optional'] ) {

                        $param['default']  = $parameter->getDefaultValue();

                    }



                    $result['services'][$methodName]['parameters'][] = $param;

                }



                // set return type

                if ( preg_match('/@return\s+([^\s]+)\s*([^\n\*]+)/', $docComment, $matches ) ) {

                    $returns = array( 'type' => $matches[1], 'description' => trim( $matches[2] ) );

                    $result['services'][$methodName]['returns'] = array_filter( $returns );

                }

            }



            return $result;

        }





        /**

         * Reset Local Class Vars after Execute

         */

        private function resetVars() {

            $this->response = $this->calls = array();

            $this->hasCalls = $this->isBatchCall = false;

        }



    }





// Initializing

if (!isset($ACCOUNTS)) $ACCOUNTS = array();

if (isset($USER) && isset($PASSWORD) && $USER && $PASSWORD) $ACCOUNTS[$USER] = $PASSWORD;

if (!isset($HOME_DIRECTORY)) $HOME_DIRECTORY = '';

$IS_CONFIGURED = count($ACCOUNTS) >= 1 ? true : false;



// Command execution

function execute_command($command) {

    $descriptors = array(

        0 => array('pipe', 'r'), // STDIN

        1 => array('pipe', 'w'), // STDOUT

        2 => array('pipe', 'w')  // STDERR

    );



    $process = proc_open($command . ' 2>&1', $descriptors, $pipes);

    if (!is_resource($process)) die("Can't execute command.");



    // Nothing to push to STDIN

    fclose($pipes[0]);



    $output = stream_get_contents($pipes[1]);

    fclose($pipes[1]);



    $error = stream_get_contents($pipes[2]);

    fclose($pipes[2]);



    // All pipes must be closed before "proc_close"

    $code = proc_close($process);



    return $output;

}



// Command parsing

function parse_command($command) {

    $value = ltrim((string) $command);



    if ($value && !empty($value)) {

        $values = explode(' ', $value);

        $values_total = count($values);



        if ($values_total > 1) {

            $value = $values[$values_total - 1];



            for ($index = $values_total - 2; $index >= 0; $index--) {

                $value_item = $values[$index];



                if (substr($value_item, -1) == '\\')

                    $value = $value_item . ' ' . $value;

                else break;

            }

        }

    }



    return $value;

}



// RPC Server

class WebConsoleRPCServer extends BaseJsonRpcServer {

    protected $home_directory = '';



    private function error($message) {

        throw new Exception($message);

    }



    // Authentication

    private function password_hash($password) {

        return hash('sha256', trim((string) $password));

    }



    private function authenticate_user($user, $password) {                

        $user = trim((string) $user);

        $password = trim((string) $password);



        if ($user && $password) {

            global $ACCOUNTS;



            if (isset($ACCOUNTS[$user]) && $ACCOUNTS[$user] && strcmp($password, $ACCOUNTS[$user]) == 0)

                return $user . ':' . $this->password_hash($password);

        }



        throw new Exception("Incorrect user or password");

    }



    private function authenticate_token($token) {

        $token = trim((string) $token);

        $token_parts = explode(':', $token, 2);



        if (count($token_parts) == 2) {

            $user = trim((string) $token_parts[0]);

            $password_hash = trim((string) $token_parts[1]);



            if ($user && $password_hash) {

                global $ACCOUNTS;



                if (isset($ACCOUNTS[$user]) && $ACCOUNTS[$user]) {

                    $real_password_hash = $this->password_hash($ACCOUNTS[$user]);



                    if (strcmp($password_hash, $real_password_hash) == 0)

                        return true;

                }

            }

        }



        throw new Exception("Incorrect user or password");

    }



    // Environment

    private function get_environment() {

        $hostname = function_exists('gethostname') ? gethostname() : null;

        return array('path' => getcwd(), 'hostname' => $hostname);

    }



    private function set_environment($environment) {

        if ($environment && !empty($environment)) {

            $environment = (array) $environment;



            if (isset($environment['path']) && $environment['path']) {

                $path = $environment['path'];



                if (is_dir($path)) {

                    if (!@chdir($path)) return array('output' => "Unable to change directory to current working directory, updating current directory",

                                                     'environment' => $this->get_environment());

                }

                else return array('output' => "Current working directory not found, updating current directory",

                                  'environment' => $this->get_environment());

            }

        }

    }



    // Initialization

    private function initialize($token, $environment) {

        $this->authenticate_token($token);



        global $HOME_DIRECTORY;

        $this->home_directory = !empty($HOME_DIRECTORY) ? $HOME_DIRECTORY : getcwd();

        $result = $this->set_environment($environment);



        if ($result) return $result;

    }



    // Methods

    public function login($user, $password) {

        $result = array('token' => $this->authenticate_user($user, $password),

                        'environment' => $this->get_environment());



        global $HOME_DIRECTORY;

        if (!empty($HOME_DIRECTORY)) {

            if (is_dir($HOME_DIRECTORY))

                $result['environment']['path'] = $HOME_DIRECTORY;

            else $result['output'] = "Home directory not found: ". $HOME_DIRECTORY;

        }



        return $result;

    }



    public function cd($token, $environment, $path) {

        $result = $this->initialize($token, $environment);

        if ($result) return $result;



        $path = trim((string) $path);

        if (empty($path)) $path = $this->home_directory;



        if (!empty($path)) {

            if (is_dir($path)) {

                if (!@chdir($path)) return array('output' => "cd: ". $path . ": Unable to change directory");

            }

            else return array('output' => "cd: ". $path . ": No such directory");

        }



        return array('environment' => $this->get_environment());

    }



    public function completion($token, $environment, $pattern, $command) {

        $result = $this->initialize($token, $environment);

        if ($result) return $result;



        $scan_path = '';

        $completion_prefix = '';

        $completion = array();



        if (!empty($pattern)) {

            if (!is_dir($pattern)) {

                $pattern = dirname($pattern);

                if ($pattern == '.') $pattern = '';

            }



            if (!empty($pattern)) {

                if (is_dir($pattern)) {

                    $scan_path = $completion_prefix = $pattern;

                    if (substr($completion_prefix, -1) != '/') $completion_prefix .= '/';

                }

            }

            else $scan_path = getcwd();

        }

        else $scan_path = getcwd();



        if (!empty($scan_path)) {

            // Loading directory listing

            $completion = array_values(array_diff(scandir($scan_path), array('..', '.')));

            natsort($completion);



            // Prefix

            if (!empty($completion_prefix) && !empty($completion)) {

                foreach ($completion as &$value) $value = $completion_prefix . $value;

            }



            // Pattern

            if (!empty($pattern) && !empty($completion)) {

                // For PHP version that does not support anonymous functions (available since PHP 5.3.0)

                function filter_pattern($value) {

                    global $pattern;

                    return !strncmp($pattern, $value, strlen($pattern));

                }



                $completion = array_values(array_filter($completion, 'filter_pattern'));

            }

        }



        return array('completion' => $completion);

    }



    public function run($token, $environment, $command) {

        $result = $this->initialize($token, $environment);

        if ($result) return $result;

        $output = ($command && !empty($command)) ? execute_command($command) : '';

        //if(mb_detect_encoding($output) !== 'UTF-8') {

            $output = mb_convert_encoding($output, 'UTF-8'); 

        //}

        if ($output && substr($output, -1) == "\n") $output = substr($output, 0, -1);



        return array('output' => $output);

    }

}



// Processing request

if (array_key_exists('REQUEST_METHOD', $_SERVER) && $_SERVER['REQUEST_METHOD'] == 'POST') {

    $rpc_server = new WebConsoleRPCServer();

    $rpc_server->Execute();

}

else if (!$IS_CONFIGURED) {

?>

<!DOCTYPE html>

<html>

    <head>

        <meta charset="utf-8" />

        <meta http-equiv="X-UA-Compatible" content="IE=edge" />

        <title>Web Console</title>

        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <meta name="description" content="Web Console (http://www.web-console.org)" />

        <meta name="author" content="Nickolay Kovalev (http://resume.nickola.ru)" />

        <meta name="robots" content="none" />

        <style type="text/css">html{font-family:sans-serif;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}body{margin:0}article,aside,details,figcaption,figure,footer,header,hgroup,main,nav,section,summary{display:block}audio,canvas,progress,video{display:inline-block;vertical-align:baseline}audio:not([controls]){display:none;height:0}[hidden],template{display:none}a{background:0 0}a:active,a:hover{outline:0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:700}dfn{font-style:italic}h1{font-size:2em;margin:.67em 0}mark{background:#ff0;color:#000}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-.5em}sub{bottom:-.25em}img{border:0}svg:not(:root){overflow:hidden}figure{margin:1em 40px}hr{-moz-box-sizing:content-box;box-sizing:content-box;height:0}pre{overflow:auto}code,kbd,pre,samp{font-family:monospace,monospace;font-size:1em}button,input,optgroup,select,textarea{color:inherit;font:inherit;margin:0}button{overflow:visible}button,select{text-transform:none}button,html input[type=button],input[type=reset],input[type=submit]{-webkit-appearance:button;cursor:pointer}button[disabled],html input[disabled]{cursor:default}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}input{line-height:normal}input[type=checkbox],input[type=radio]{box-sizing:border-box;padding:0}input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{height:auto}input[type=search]{-webkit-appearance:textfield;-moz-box-sizing:content-box;-webkit-box-sizing:content-box;box-sizing:content-box}input[type=search]::-webkit-search-cancel-button,input[type=search]::-webkit-search-decoration{-webkit-appearance:none}fieldset{border:1px solid silver;margin:0 2px;padding:.35em .625em .75em}legend{border:0;padding:0}textarea{overflow:auto}optgroup{font-weight:700}table{border-collapse:collapse;border-spacing:0}td,th{padding:0}.terminal .cmd .format,.terminal .cmd .prompt,.terminal .cmd .prompt div,.terminal .terminal-output .format,.terminal .terminal-output div div{display:inline-block}.terminal .clipboard{position:absolute;bottom:0;left:0;opacity:.01;filter:alpha(opacity=.01);filter:alpha(opacity=.01);width:2px}.cmd>.clipboard{position:fixed}.terminal{padding:10px;position:relative;overflow:hidden}.cmd{padding:0;margin:0;height:1.3em}.terminal .prompt,.terminal .terminal-output div div{display:block;height:auto}.terminal .prompt{float:left}.terminal{background-color:#000}.terminal-output>div{min-height:14px}.terminal .terminal-output div span{display:inline-block}.terminal .cmd span{float:left}.terminal .cmd span.inverted{background-color:#aaa;color:#000}.terminal .terminal-output div div a::-moz-selection,.terminal .terminal-output div div::-moz-selection,.terminal .terminal-output div span::-moz-selection{background-color:#aaa;color:#000}.terminal .cmd>span::selection,.terminal .prompt span::selection,.terminal .terminal-output div div a::selection,.terminal .terminal-output div div::selection,.terminal .terminal-output div span::selection{background-color:#aaa;color:#000}.terminal .terminal-output div.error,.terminal .terminal-output div.error div{color:red}.tilda{position:fixed;top:0;left:0;width:100%;z-index:1100}.clear{clear:both}body{background-color:#000}.terminal,.terminal .prompt,.terminal .terminal-output div div,body{color:#ccc;font-family:monospace,fixed;font-size:15px;line-height:18px}.terminal a,.terminal a:hover,a,a:hover{color:#6c71c4}.spaced{margin:15px 0}.spaced-top{margin:15px 0 0}.spaced-bottom{margin:0 0 15px}.configure{margin:20px}.configure .variable{color:#d33682}.configure p,.configure ul{margin:5px 0 0}</style>

    </head>

    <body>

        <div class="configure">

            <p>Web Console must be configured before use:</p>

            <ul>

                <li>Open Web Console PHP file in your favorite text editor.</li>

                <li>At the top of the file enter your <span class="variable">$USER</span> and <span class="variable">$PASSWORD</span> credentials, edit any other settings that you like (see description in the comments).</li>

                <li>Upload changed file to the web server and open it in the browser.</li>

            </ul>

            <p>For more information visit <a href="http://www.web-console.org">Web Console website</a>.</p>

        </div>

    </body>

</html>

<?php

}

else {



    ?>

<!DOCTYPE html>

<html class="no-js">

    <head>

        <meta charset="utf-8" />

        <meta http-equiv="X-UA-Compatible" content="IE=edge" />

        <title>Web Console</title>

        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <meta name="description" content="Web Console (http://www.web-console.org)" />

        <meta name="author" content="Nickolay Kovalev (http://resume.nickola.ru)" />

        <meta name="robots" content="none" />

        <style type="text/css">html{font-family:sans-serif;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}body{margin:0}article,aside,details,figcaption,figure,footer,header,hgroup,main,nav,section,summary{display:block}audio,canvas,progress,video{display:inline-block;vertical-align:baseline}audio:not([controls]){display:none;height:0}[hidden],template{display:none}a{background:0 0}a:active,a:hover{outline:0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:700}dfn{font-style:italic}h1{font-size:2em;margin:.67em 0}mark{background:#ff0;color:#000}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-.5em}sub{bottom:-.25em}img{border:0}svg:not(:root){overflow:hidden}figure{margin:1em 40px}hr{-moz-box-sizing:content-box;box-sizing:content-box;height:0}pre{overflow:auto}code,kbd,pre,samp{font-family:monospace,monospace;font-size:1em}button,input,optgroup,select,textarea{color:inherit;font:inherit;margin:0}button{overflow:visible}button,select{text-transform:none}button,html input[type=button],input[type=reset],input[type=submit]{-webkit-appearance:button;cursor:pointer}button[disabled],html input[disabled]{cursor:default}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}input{line-height:normal}input[type=checkbox],input[type=radio]{box-sizing:border-box;padding:0}input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{height:auto}input[type=search]{-webkit-appearance:textfield;-moz-box-sizing:content-box;-webkit-box-sizing:content-box;box-sizing:content-box}input[type=search]::-webkit-search-cancel-button,input[type=search]::-webkit-search-decoration{-webkit-appearance:none}fieldset{border:1px solid silver;margin:0 2px;padding:.35em .625em .75em}legend{border:0;padding:0}textarea{overflow:auto}optgroup{font-weight:700}table{border-collapse:collapse;border-spacing:0}td,th{padding:0}.terminal .cmd .format,.terminal .cmd .prompt,.terminal .cmd .prompt div,.terminal .terminal-output .format,.terminal .terminal-output div div{display:inline-block}.terminal .clipboard{position:absolute;bottom:0;left:0;opacity:.01;filter:alpha(opacity=.01);filter:alpha(opacity=.01);width:2px}.cmd>.clipboard{position:fixed}.terminal{padding:10px;position:relative;overflow:hidden}.cmd{padding:0;margin:0;height:1.3em}.terminal .prompt,.terminal .terminal-output div div{display:block;height:auto}.terminal .prompt{float:left}.terminal{background-color:#000}.terminal-output>div{min-height:14px}.terminal .terminal-output div span{display:inline-block}.terminal .cmd span{float:left}.terminal .cmd span.inverted{background-color:#aaa;color:#000}.terminal .terminal-output div div a::-moz-selection,.terminal .terminal-output div div::-moz-selection,.terminal .terminal-output div span::-moz-selection{background-color:#aaa;color:#000}.terminal .cmd>span::selection,.terminal .prompt span::selection,.terminal .terminal-output div div a::selection,.terminal .terminal-output div div::selection,.terminal .terminal-output div span::selection{background-color:#aaa;color:#000}.terminal .terminal-output div.error,.terminal .terminal-output div.error div{color:red}.tilda{position:fixed;top:0;left:0;width:100%;z-index:1100}.clear{clear:both}body{background-color:#000}.terminal,.terminal .prompt,.terminal .terminal-output div div,body{color:#ccc;font-family:monospace,fixed;font-size:15px;line-height:18px}.terminal a,.terminal a:hover,a,a:hover{color:#6c71c4}.spaced{margin:15px 0}.spaced-top{margin:15px 0 0}.spaced-bottom{margin:0 0 15px}.configure{margin:20px}.configure .variable{color:#d33682}.configure p,.configure ul{margin:5px 0 0}</style>

        <script type="text/javascript">!function(a,b){"object"==typeof module&&"object"==typeof module.exports?module.exports=a.document?b(a,!0):function(a){if(!a.document)throw new Error("jQuery requires a window with a document");return b(a)}:b(a)}("undefined"!=typeof window?window:this,function(a,b){function c(a){var b=a.length,c=ab.type(a);return"function"===c||ab.isWindow(a)?!1:1===a.nodeType&&b?!0:"array"===c||0===b||"number"==typeof b&&b>0&&b-1 in a}function d(a,b,c){if(ab.isFunction(b))return ab.grep(a,function(a,d){return!!b.call(a,d,a)!==c});if(b.nodeType)return ab.grep(a,function(a){return a===b!==c});if("string"==typeof b){if(hb.test(b))return ab.filter(b,a,c);b=ab.filter(b,a)}return ab.grep(a,function(a){return U.call(b,a)>=0!==c})}function e(a,b){for(;(a=a[b])&&1!==a.nodeType;);return a}function f(a){var b=ob[a]={};return ab.each(a.match(nb)||[],function(a,c){b[c]=!0}),b}function g(){$.removeEventListener("DOMContentLoaded",g,!1),a.removeEventListener("load",g,!1),ab.ready()}function h(){Object.defineProperty(this.cache={},0,{get:function(){return{}}}),this.expando=ab.expando+Math.random()}function i(a,b,c){var d;if(void 0===c&&1===a.nodeType)if(d="data-"+b.replace(ub,"-$1").toLowerCase(),c=a.getAttribute(d),"string"==typeof c){try{c="true"===c?!0:"false"===c?!1:"null"===c?null:+c+""===c?+c:tb.test(c)?ab.parseJSON(c):c}catch(e){}sb.set(a,b,c)}else c=void 0;return c}function j(){return!0}function k(){return!1}function l(){try{return $.activeElement}catch(a){}}function m(a,b){return ab.nodeName(a,"table")&&ab.nodeName(11!==b.nodeType?b:b.firstChild,"tr")?a.getElementsByTagName("tbody")[0]||a.appendChild(a.ownerDocument.createElement("tbody")):a}function n(a){return a.type=(null!==a.getAttribute("type"))+"/"+a.type,a}function o(a){var b=Kb.exec(a.type);return b?a.type=b[1]:a.removeAttribute("type"),a}function p(a,b){for(var c=0,d=a.length;d>c;c++)rb.set(a[c],"globalEval",!b||rb.get(b[c],"globalEval"))}function q(a,b){var c,d,e,f,g,h,i,j;if(1===b.nodeType){if(rb.hasData(a)&&(f=rb.access(a),g=rb.set(b,f),j=f.events)){delete g.handle,g.events={};for(e in j)for(c=0,d=j[e].length;d>c;c++)ab.event.add(b,e,j[e][c])}sb.hasData(a)&&(h=sb.access(a),i=ab.extend({},h),sb.set(b,i))}}function r(a,b){var c=a.getElementsByTagName?a.getElementsByTagName(b||"*"):a.querySelectorAll?a.querySelectorAll(b||"*"):[];return void 0===b||b&&ab.nodeName(a,b)?ab.merge([a],c):c}function s(a,b){var c=b.nodeName.toLowerCase();"input"===c&&yb.test(a.type)?b.checked=a.checked:("input"===c||"textarea"===c)&&(b.defaultValue=a.defaultValue)}function t(b,c){var d=ab(c.createElement(b)).appendTo(c.body),e=a.getDefaultComputedStyle?a.getDefaultComputedStyle(d[0]).display:ab.css(d[0],"display");return d.detach(),e}function u(a){var b=$,c=Ob[a];return c||(c=t(a,b),"none"!==c&&c||(Nb=(Nb||ab("<iframe frameborder='0' width='0' height='0'/>")).appendTo(b.documentElement),b=Nb[0].contentDocument,b.write(),b.close(),c=t(a,b),Nb.detach()),Ob[a]=c),c}function v(a,b,c){var d,e,f,g,h=a.style;return c=c||Rb(a),c&&(g=c.getPropertyValue(b)||c[b]),c&&(""!==g||ab.contains(a.ownerDocument,a)||(g=ab.style(a,b)),Qb.test(g)&&Pb.test(b)&&(d=h.width,e=h.minWidth,f=h.maxWidth,h.minWidth=h.maxWidth=h.width=g,g=c.width,h.width=d,h.minWidth=e,h.maxWidth=f)),void 0!==g?g+"":g}function w(a,b){return{get:function(){return a()?void delete this.get:(this.get=b).apply(this,arguments)}}}function x(a,b){if(b in a)return b;for(var c=b[0].toUpperCase()+b.slice(1),d=b,e=Xb.length;e--;)if(b=Xb[e]+c,b in a)return b;return d}function y(a,b,c){var d=Tb.exec(b);return d?Math.max(0,d[1]-(c||0))+(d[2]||"px"):b}function z(a,b,c,d,e){for(var f=c===(d?"border":"content")?4:"width"===b?1:0,g=0;4>f;f+=2)"margin"===c&&(g+=ab.css(a,c+wb[f],!0,e)),d?("content"===c&&(g-=ab.css(a,"padding"+wb[f],!0,e)),"margin"!==c&&(g-=ab.css(a,"border"+wb[f]+"Width",!0,e))):(g+=ab.css(a,"padding"+wb[f],!0,e),"padding"!==c&&(g+=ab.css(a,"border"+wb[f]+"Width",!0,e)));return g}function A(a,b,c){var d=!0,e="width"===b?a.offsetWidth:a.offsetHeight,f=Rb(a),g="border-box"===ab.css(a,"boxSizing",!1,f);if(0>=e||null==e){if(e=v(a,b,f),(0>e||null==e)&&(e=a.style[b]),Qb.test(e))return e;d=g&&(Z.boxSizingReliable()||e===a.style[b]),e=parseFloat(e)||0}return e+z(a,b,c||(g?"border":"content"),d,f)+"px"}function B(a,b){for(var c,d,e,f=[],g=0,h=a.length;h>g;g++)d=a[g],d.style&&(f[g]=rb.get(d,"olddisplay"),c=d.style.display,b?(f[g]||"none"!==c||(d.style.display=""),""===d.style.display&&xb(d)&&(f[g]=rb.access(d,"olddisplay",u(d.nodeName)))):f[g]||(e=xb(d),(c&&"none"!==c||!e)&&rb.set(d,"olddisplay",e?c:ab.css(d,"display"))));for(g=0;h>g;g++)d=a[g],d.style&&(b&&"none"!==d.style.display&&""!==d.style.display||(d.style.display=b?f[g]||"":"none"));return a}function C(a,b,c,d,e){return new C.prototype.init(a,b,c,d,e)}function D(){return setTimeout(function(){Yb=void 0}),Yb=ab.now()}function E(a,b){var c,d=0,e={height:a};for(b=b?1:0;4>d;d+=2-b)c=wb[d],e["margin"+c]=e["padding"+c]=a;return b&&(e.opacity=e.width=a),e}function F(a,b,c){for(var d,e=(cc[b]||[]).concat(cc["*"]),f=0,g=e.length;g>f;f++)if(d=e[f].call(c,b,a))return d}function G(a,b,c){var d,e,f,g,h,i,j,k=this,l={},m=a.style,n=a.nodeType&&xb(a),o=rb.get(a,"fxshow");c.queue||(h=ab._queueHooks(a,"fx"),null==h.unqueued&&(h.unqueued=0,i=h.empty.fire,h.empty.fire=function(){h.unqueued||i()}),h.unqueued++,k.always(function(){k.always(function(){h.unqueued--,ab.queue(a,"fx").length||h.empty.fire()})})),1===a.nodeType&&("height"in b||"width"in b)&&(c.overflow=[m.overflow,m.overflowX,m.overflowY],j=ab.css(a,"display"),"none"===j&&(j=u(a.nodeName)),"inline"===j&&"none"===ab.css(a,"float")&&(m.display="inline-block")),c.overflow&&(m.overflow="hidden",k.always(function(){m.overflow=c.overflow[0],m.overflowX=c.overflow[1],m.overflowY=c.overflow[2]}));for(d in b)if(e=b[d],$b.exec(e)){if(delete b[d],f=f||"toggle"===e,e===(n?"hide":"show")){if("show"!==e||!o||void 0===o[d])continue;n=!0}l[d]=o&&o[d]||ab.style(a,d)}if(!ab.isEmptyObject(l)){o?"hidden"in o&&(n=o.hidden):o=rb.access(a,"fxshow",{}),f&&(o.hidden=!n),n?ab(a).show():k.done(function(){ab(a).hide()}),k.done(function(){var b;rb.remove(a,"fxshow");for(b in l)ab.style(a,b,l[b])});for(d in l)g=F(n?o[d]:0,d,k),d in o||(o[d]=g.start,n&&(g.end=g.start,g.start="width"===d||"height"===d?1:0))}}function H(a,b){var c,d,e,f,g;for(c in a)if(d=ab.camelCase(c),e=b[d],f=a[c],ab.isArray(f)&&(e=f[1],f=a[c]=f[0]),c!==d&&(a[d]=f,delete a[c]),g=ab.cssHooks[d],g&&"expand"in g){f=g.expand(f),delete a[d];for(c in f)c in a||(a[c]=f[c],b[c]=e)}else b[d]=e}function I(a,b,c){var d,e,f=0,g=bc.length,h=ab.Deferred().always(function(){delete i.elem}),i=function(){if(e)return!1;for(var b=Yb||D(),c=Math.max(0,j.startTime+j.duration-b),d=c/j.duration||0,f=1-d,g=0,i=j.tweens.length;i>g;g++)j.tweens[g].run(f);return h.notifyWith(a,[j,f,c]),1>f&&i?c:(h.resolveWith(a,[j]),!1)},j=h.promise({elem:a,props:ab.extend({},b),opts:ab.extend(!0,{specialEasing:{}},c),originalProperties:b,originalOptions:c,startTime:Yb||D(),duration:c.duration,tweens:[],createTween:function(b,c){var d=ab.Tween(a,j.opts,b,c,j.opts.specialEasing[b]||j.opts.easing);return j.tweens.push(d),d},stop:function(b){var c=0,d=b?j.tweens.length:0;if(e)return this;for(e=!0;d>c;c++)j.tweens[c].run(1);return b?h.resolveWith(a,[j,b]):h.rejectWith(a,[j,b]),this}}),k=j.props;for(H(k,j.opts.specialEasing);g>f;f++)if(d=bc[f].call(j,a,k,j.opts))return d;return ab.map(k,F,j),ab.isFunction(j.opts.start)&&j.opts.start.call(a,j),ab.fx.timer(ab.extend(i,{elem:a,anim:j,queue:j.opts.queue})),j.progress(j.opts.progress).done(j.opts.done,j.opts.complete).fail(j.opts.fail).always(j.opts.always)}function J(a){return function(b,c){"string"!=typeof b&&(c=b,b="*");var d,e=0,f=b.toLowerCase().match(nb)||[];if(ab.isFunction(c))for(;d=f[e++];)"+"===d[0]?(d=d.slice(1)||"*",(a[d]=a[d]||[]).unshift(c)):(a[d]=a[d]||[]).push(c)}}function K(a,b,c,d){function e(h){var i;return f[h]=!0,ab.each(a[h]||[],function(a,h){var j=h(b,c,d);return"string"!=typeof j||g||f[j]?g?!(i=j):void 0:(b.dataTypes.unshift(j),e(j),!1)}),i}var f={},g=a===vc;return e(b.dataTypes[0])||!f["*"]&&e("*")}function L(a,b){var c,d,e=ab.ajaxSettings.flatOptions||{};for(c in b)void 0!==b[c]&&((e[c]?a:d||(d={}))[c]=b[c]);return d&&ab.extend(!0,a,d),a}function M(a,b,c){for(var d,e,f,g,h=a.contents,i=a.dataTypes;"*"===i[0];)i.shift(),void 0===d&&(d=a.mimeType||b.getResponseHeader("Content-Type"));if(d)for(e in h)if(h[e]&&h[e].test(d)){i.unshift(e);break}if(i[0]in c)f=i[0];else{for(e in c){if(!i[0]||a.converters[e+" "+i[0]]){f=e;break}g||(g=e)}f=f||g}return f?(f!==i[0]&&i.unshift(f),c[f]):void 0}function N(a,b,c,d){var e,f,g,h,i,j={},k=a.dataTypes.slice();if(k[1])for(g in a.converters)j[g.toLowerCase()]=a.converters[g];for(f=k.shift();f;)if(a.responseFields[f]&&(c[a.responseFields[f]]=b),!i&&d&&a.dataFilter&&(b=a.dataFilter(b,a.dataType)),i=f,f=k.shift())if("*"===f)f=i;else if("*"!==i&&i!==f){if(g=j[i+" "+f]||j["* "+f],!g)for(e in j)if(h=e.split(" "),h[1]===f&&(g=j[i+" "+h[0]]||j["* "+h[0]])){g===!0?g=j[e]:j[e]!==!0&&(f=h[0],k.unshift(h[1]));break}if(g!==!0)if(g&&a["throws"])b=g(b);else try{b=g(b)}catch(l){return{state:"parsererror",error:g?l:"No conversion from "+i+" to "+f}}}return{state:"success",data:b}}function O(a,b,c,d){var e;if(ab.isArray(b))ab.each(b,function(b,e){c||zc.test(a)?d(a,e):O(a+"["+("object"==typeof e?b:"")+"]",e,c,d)});else if(c||"object"!==ab.type(b))d(a,b);else for(e in b)O(a+"["+e+"]",b[e],c,d)}function P(a){return ab.isWindow(a)?a:9===a.nodeType&&a.defaultView}var Q=[],R=Q.slice,S=Q.concat,T=Q.push,U=Q.indexOf,V={},W=V.toString,X=V.hasOwnProperty,Y="".trim,Z={},$=a.document,_="2.1.0",ab=function(a,b){return new ab.fn.init(a,b)},bb=/^-ms-/,cb=/-([\da-z])/gi,db=function(a,b){return b.toUpperCase()};ab.fn=ab.prototype={jquery:_,constructor:ab,selector:"",length:0,toArray:function(){return R.call(this)},get:function(a){return null!=a?0>a?this[a+this.length]:this[a]:R.call(this)},pushStack:function(a){var b=ab.merge(this.constructor(),a);return b.prevObject=this,b.context=this.context,b},each:function(a,b){return ab.each(this,a,b)},map:function(a){return this.pushStack(ab.map(this,function(b,c){return a.call(b,c,b)}))},slice:function(){return this.pushStack(R.apply(this,arguments))},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},eq:function(a){var b=this.length,c=+a+(0>a?b:0);return this.pushStack(c>=0&&b>c?[this[c]]:[])},end:function(){return this.prevObject||this.constructor(null)},push:T,sort:Q.sort,splice:Q.splice},ab.extend=ab.fn.extend=function(){var a,b,c,d,e,f,g=arguments[0]||{},h=1,i=arguments.length,j=!1;for("boolean"==typeof g&&(j=g,g=arguments[h]||{},h++),"object"==typeof g||ab.isFunction(g)||(g={}),h===i&&(g=this,h--);i>h;h++)if(null!=(a=arguments[h]))for(b in a)c=g[b],d=a[b],g!==d&&(j&&d&&(ab.isPlainObject(d)||(e=ab.isArray(d)))?(e?(e=!1,f=c&&ab.isArray(c)?c:[]):f=c&&ab.isPlainObject(c)?c:{},g[b]=ab.extend(j,f,d)):void 0!==d&&(g[b]=d));return g},ab.extend({expando:"jQuery"+(_+Math.random()).replace(/\D/g,""),isReady:!0,error:function(a){throw new Error(a)},noop:function(){},isFunction:function(a){return"function"===ab.type(a)},isArray:Array.isArray,isWindow:function(a){return null!=a&&a===a.window},isNumeric:function(a){return a-parseFloat(a)>=0},isPlainObject:function(a){if("object"!==ab.type(a)||a.nodeType||ab.isWindow(a))return!1;try{if(a.constructor&&!X.call(a.constructor.prototype,"isPrototypeOf"))return!1}catch(b){return!1}return!0},isEmptyObject:function(a){var b;for(b in a)return!1;return!0},type:function(a){return null==a?a+"":"object"==typeof a||"function"==typeof a?V[W.call(a)]||"object":typeof a},globalEval:function(a){var b,c=eval;a=ab.trim(a),a&&(1===a.indexOf("use strict")?(b=$.createElement("script"),b.text=a,$.head.appendChild(b).parentNode.removeChild(b)):c(a))},camelCase:function(a){return a.replace(bb,"ms-").replace(cb,db)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toLowerCase()===b.toLowerCase()},each:function(a,b,d){var e,f=0,g=a.length,h=c(a);if(d){if(h)for(;g>f&&(e=b.apply(a[f],d),e!==!1);f++);else for(f in a)if(e=b.apply(a[f],d),e===!1)break}else if(h)for(;g>f&&(e=b.call(a[f],f,a[f]),e!==!1);f++);else for(f in a)if(e=b.call(a[f],f,a[f]),e===!1)break;return a},trim:function(a){return null==a?"":Y.call(a)},makeArray:function(a,b){var d=b||[];return null!=a&&(c(Object(a))?ab.merge(d,"string"==typeof a?[a]:a):T.call(d,a)),d},inArray:function(a,b,c){return null==b?-1:U.call(b,a,c)},merge:function(a,b){for(var c=+b.length,d=0,e=a.length;c>d;d++)a[e++]=b[d];return a.length=e,a},grep:function(a,b,c){for(var d,e=[],f=0,g=a.length,h=!c;g>f;f++)d=!b(a[f],f),d!==h&&e.push(a[f]);return e},map:function(a,b,d){var e,f=0,g=a.length,h=c(a),i=[];if(h)for(;g>f;f++)e=b(a[f],f,d),null!=e&&i.push(e);else for(f in a)e=b(a[f],f,d),null!=e&&i.push(e);return S.apply([],i)},guid:1,proxy:function(a,b){var c,d,e;return"string"==typeof b&&(c=a[b],b=a,a=c),ab.isFunction(a)?(d=R.call(arguments,2),e=function(){return a.apply(b||this,d.concat(R.call(arguments)))},e.guid=a.guid=a.guid||ab.guid++,e):void 0},now:Date.now,support:Z}),ab.each("Boolean Number String Function Array Date RegExp Object Error".split(" "),function(a,b){V["[object "+b+"]"]=b.toLowerCase()});var eb=function(a){function b(a,b,c,d){var e,f,g,h,i,j,l,o,p,q;if((b?b.ownerDocument||b:O)!==G&&F(b),b=b||G,c=c||[],!a||"string"!=typeof a)return c;if(1!==(h=b.nodeType)&&9!==h)return[];if(I&&!d){if(e=sb.exec(a))if(g=e[1]){if(9===h){if(f=b.getElementById(g),!f||!f.parentNode)return c;if(f.id===g)return c.push(f),c}else if(b.ownerDocument&&(f=b.ownerDocument.getElementById(g))&&M(b,f)&&f.id===g)return c.push(f),c}else{if(e[2])return _.apply(c,b.getElementsByTagName(a)),c;if((g=e[3])&&x.getElementsByClassName&&b.getElementsByClassName)return _.apply(c,b.getElementsByClassName(g)),c}if(x.qsa&&(!J||!J.test(a))){if(o=l=N,p=b,q=9===h&&a,1===h&&"object"!==b.nodeName.toLowerCase()){for(j=m(a),(l=b.getAttribute("id"))?o=l.replace(ub,"\\$&"):b.setAttribute("id",o),o="[id='"+o+"'] ",i=j.length;i--;)j[i]=o+n(j[i]);p=tb.test(a)&&k(b.parentNode)||b,q=j.join(",")}if(q)try{return _.apply(c,p.querySelectorAll(q)),c}catch(r){}finally{l||b.removeAttribute("id")}}}return v(a.replace(ib,"$1"),b,c,d)}function c(){function a(c,d){return b.push(c+" ")>y.cacheLength&&delete a[b.shift()],a[c+" "]=d}var b=[];return a}function d(a){return a[N]=!0,a}function e(a){var b=G.createElement("div");try{return!!a(b)}catch(c){return!1}finally{b.parentNode&&b.parentNode.removeChild(b),b=null}}function f(a,b){for(var c=a.split("|"),d=a.length;d--;)y.attrHandle[c[d]]=b}function g(a,b){var c=b&&a,d=c&&1===a.nodeType&&1===b.nodeType&&(~b.sourceIndex||W)-(~a.sourceIndex||W);if(d)return d;if(c)for(;c=c.nextSibling;)if(c===b)return-1;return a?1:-1}function h(a){return function(b){var c=b.nodeName.toLowerCase();return"input"===c&&b.type===a}}function i(a){return function(b){var c=b.nodeName.toLowerCase();return("input"===c||"button"===c)&&b.type===a}}function j(a){return d(function(b){return b=+b,d(function(c,d){for(var e,f=a([],c.length,b),g=f.length;g--;)c[e=f[g]]&&(c[e]=!(d[e]=c[e]))})})}function k(a){return a&&typeof a.getElementsByTagName!==V&&a}function l(){}function m(a,c){var d,e,f,g,h,i,j,k=S[a+" "];if(k)return c?0:k.slice(0);for(h=a,i=[],j=y.preFilter;h;){(!d||(e=jb.exec(h)))&&(e&&(h=h.slice(e[0].length)||h),i.push(f=[])),d=!1,(e=kb.exec(h))&&(d=e.shift(),f.push({value:d,type:e[0].replace(ib," ")}),h=h.slice(d.length));for(g in y.filter)!(e=ob[g].exec(h))||j[g]&&!(e=j[g](e))||(d=e.shift(),f.push({value:d,type:g,matches:e}),h=h.slice(d.length));if(!d)break}return c?h.length:h?b.error(a):S(a,i).slice(0)}function n(a){for(var b=0,c=a.length,d="";c>b;b++)d+=a[b].value;return d}function o(a,b,c){var d=b.dir,e=c&&"parentNode"===d,f=Q++;return b.first?function(b,c,f){for(;b=b[d];)if(1===b.nodeType||e)return a(b,c,f)}:function(b,c,g){var h,i,j=[P,f];if(g){for(;b=b[d];)if((1===b.nodeType||e)&&a(b,c,g))return!0}else for(;b=b[d];)if(1===b.nodeType||e){if(i=b[N]||(b[N]={}),(h=i[d])&&h[0]===P&&h[1]===f)return j[2]=h[2];if(i[d]=j,j[2]=a(b,c,g))return!0}}}function p(a){return a.length>1?function(b,c,d){for(var e=a.length;e--;)if(!a[e](b,c,d))return!1;return!0}:a[0]}function q(a,b,c,d,e){for(var f,g=[],h=0,i=a.length,j=null!=b;i>h;h++)(f=a[h])&&(!c||c(f,d,e))&&(g.push(f),j&&b.push(h));return g}function r(a,b,c,e,f,g){return e&&!e[N]&&(e=r(e)),f&&!f[N]&&(f=r(f,g)),d(function(d,g,h,i){var j,k,l,m=[],n=[],o=g.length,p=d||u(b||"*",h.nodeType?[h]:h,[]),r=!a||!d&&b?p:q(p,m,a,h,i),s=c?f||(d?a:o||e)?[]:g:r;if(c&&c(r,s,h,i),e)for(j=q(s,n),e(j,[],h,i),k=j.length;k--;)(l=j[k])&&(s[n[k]]=!(r[n[k]]=l));if(d){if(f||a){if(f){for(j=[],k=s.length;k--;)(l=s[k])&&j.push(r[k]=l);f(null,s=[],j,i)}for(k=s.length;k--;)(l=s[k])&&(j=f?bb.call(d,l):m[k])>-1&&(d[j]=!(g[j]=l))}}else s=q(s===g?s.splice(o,s.length):s),f?f(null,g,s,i):_.apply(g,s)})}function s(a){for(var b,c,d,e=a.length,f=y.relative[a[0].type],g=f||y.relative[" "],h=f?1:0,i=o(function(a){return a===b},g,!0),j=o(function(a){return bb.call(b,a)>-1},g,!0),k=[function(a,c,d){return!f&&(d||c!==C)||((b=c).nodeType?i(a,c,d):j(a,c,d))}];e>h;h++)if(c=y.relative[a[h].type])k=[o(p(k),c)];else{if(c=y.filter[a[h].type].apply(null,a[h].matches),c[N]){for(d=++h;e>d&&!y.relative[a[d].type];d++);return r(h>1&&p(k),h>1&&n(a.slice(0,h-1).concat({value:" "===a[h-2].type?"*":""})).replace(ib,"$1"),c,d>h&&s(a.slice(h,d)),e>d&&s(a=a.slice(d)),e>d&&n(a))}k.push(c)}return p(k)}function t(a,c){var e=c.length>0,f=a.length>0,g=function(d,g,h,i,j){var k,l,m,n=0,o="0",p=d&&[],r=[],s=C,t=d||f&&y.find.TAG("*",j),u=P+=null==s?1:Math.random()||.1,v=t.length;for(j&&(C=g!==G&&g);o!==v&&null!=(k=t[o]);o++){if(f&&k){for(l=0;m=a[l++];)if(m(k,g,h)){i.push(k);break}j&&(P=u)}e&&((k=!m&&k)&&n--,d&&p.push(k))}if(n+=o,e&&o!==n){for(l=0;m=c[l++];)m(p,r,g,h);if(d){if(n>0)for(;o--;)p[o]||r[o]||(r[o]=Z.call(i));r=q(r)}_.apply(i,r),j&&!d&&r.length>0&&n+c.length>1&&b.uniqueSort(i)}return j&&(P=u,C=s),p};return e?d(g):g}function u(a,c,d){for(var e=0,f=c.length;f>e;e++)b(a,c[e],d);return d}function v(a,b,c,d){var e,f,g,h,i,j=m(a);if(!d&&1===j.length){if(f=j[0]=j[0].slice(0),f.length>2&&"ID"===(g=f[0]).type&&x.getById&&9===b.nodeType&&I&&y.relative[f[1].type]){if(b=(y.find.ID(g.matches[0].replace(vb,wb),b)||[])[0],!b)return c;a=a.slice(f.shift().value.length)}for(e=ob.needsContext.test(a)?0:f.length;e--&&(g=f[e],!y.relative[h=g.type]);)if((i=y.find[h])&&(d=i(g.matches[0].replace(vb,wb),tb.test(f[0].type)&&k(b.parentNode)||b))){if(f.splice(e,1),a=d.length&&n(f),!a)return _.apply(c,d),c;break}}return B(a,j)(d,b,!I,c,tb.test(a)&&k(b.parentNode)||b),c}var w,x,y,z,A,B,C,D,E,F,G,H,I,J,K,L,M,N="sizzle"+-new Date,O=a.document,P=0,Q=0,R=c(),S=c(),T=c(),U=function(a,b){return a===b&&(E=!0),0},V="undefined",W=1<<31,X={}.hasOwnProperty,Y=[],Z=Y.pop,$=Y.push,_=Y.push,ab=Y.slice,bb=Y.indexOf||function(a){for(var b=0,c=this.length;c>b;b++)if(this[b]===a)return b;return-1},cb="checked|selected|async|autofocus|autoplay|controls|defer|disabled|hidden|ismap|loop|multiple|open|readonly|required|scoped",db="[\\x20\\t\\r\\n\\f]",eb="(?:\\\\.|[\\w-]|[^\\x00-\\xa0])+",fb=eb.replace("w","w#"),gb="\\["+db+"*("+eb+")"+db+"*(?:([*^$|!~]?=)"+db+"*(?:(['\"])((?:\\\\.|[^\\\\])*?)\\3|("+fb+")|)|)"+db+"*\\]",hb=":("+eb+")(?:\\(((['\"])((?:\\\\.|[^\\\\])*?)\\3|((?:\\\\.|[^\\\\()[\\]]|"+gb.replace(3,8)+")*)|.*)\\)|)",ib=new RegExp("^"+db+"+|((?:^|[^\\\\])(?:\\\\.)*)"+db+"+$","g"),jb=new RegExp("^"+db+"*,"+db+"*"),kb=new RegExp("^"+db+"*([>+~]|"+db+")"+db+"*"),lb=new RegExp("="+db+"*([^\\]'\"]*?)"+db+"*\\]","g"),mb=new RegExp(hb),nb=new RegExp("^"+fb+"$"),ob={ID:new RegExp("^#("+eb+")"),CLASS:new RegExp("^\\.("+eb+")"),TAG:new RegExp("^("+eb.replace("w","w*")+")"),ATTR:new RegExp("^"+gb),PSEUDO:new RegExp("^"+hb),CHILD:new RegExp("^:(only|first|last|nth|nth-last)-(child|of-type)(?:\\("+db+"*(even|odd|(([+-]|)(\\d*)n|)"+db+"*(?:([+-]|)"+db+"*(\\d+)|))"+db+"*\\)|)","i"),bool:new RegExp("^(?:"+cb+")$","i"),needsContext:new RegExp("^"+db+"*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\("+db+"*((?:-\\d)?\\d*)"+db+"*\\)|)(?=[^-]|$)","i")},pb=/^(?:input|select|textarea|button)$/i,qb=/^h\d$/i,rb=/^[^{]+\{\s*\[native \w/,sb=/^(?:#([\w-]+)|(\w+)|\.([\w-]+))$/,tb=/[+~]/,ub=/'|\\/g,vb=new RegExp("\\\\([\\da-f]{1,6}"+db+"?|("+db+")|.)","ig"),wb=function(a,b,c){var d="0x"+b-65536;return d!==d||c?b:0>d?String.fromCharCode(d+65536):String.fromCharCode(d>>10|55296,1023&d|56320)};try{_.apply(Y=ab.call(O.childNodes),O.childNodes),Y[O.childNodes.length].nodeType}catch(xb){_={apply:Y.length?function(a,b){$.apply(a,ab.call(b))}:function(a,b){for(var c=a.length,d=0;a[c++]=b[d++];);a.length=c-1}}}x=b.support={},A=b.isXML=function(a){var b=a&&(a.ownerDocument||a).documentElement;return b?"HTML"!==b.nodeName:!1},F=b.setDocument=function(a){var b,c=a?a.ownerDocument||a:O,d=c.defaultView;return c!==G&&9===c.nodeType&&c.documentElement?(G=c,H=c.documentElement,I=!A(c),d&&d!==d.top&&(d.addEventListener?d.addEventListener("unload",function(){F()},!1):d.attachEvent&&d.attachEvent("onunload",function(){F()})),x.attributes=e(function(a){return a.className="i",!a.getAttribute("className")}),x.getElementsByTagName=e(function(a){return a.appendChild(c.createComment("")),!a.getElementsByTagName("*").length}),x.getElementsByClassName=rb.test(c.getElementsByClassName)&&e(function(a){return a.innerHTML="<div class='a'></div><div class='a i'></div>",a.firstChild.className="i",2===a.getElementsByClassName("i").length}),x.getById=e(function(a){return H.appendChild(a).id=N,!c.getElementsByName||!c.getElementsByName(N).length}),x.getById?(y.find.ID=function(a,b){if(typeof b.getElementById!==V&&I){var c=b.getElementById(a);return c&&c.parentNode?[c]:[]}},y.filter.ID=function(a){var b=a.replace(vb,wb);return function(a){return a.getAttribute("id")===b}}):(delete y.find.ID,y.filter.ID=function(a){var b=a.replace(vb,wb);return function(a){var c=typeof a.getAttributeNode!==V&&a.getAttributeNode("id");return c&&c.value===b}}),y.find.TAG=x.getElementsByTagName?function(a,b){return typeof b.getElementsByTagName!==V?b.getElementsByTagName(a):void 0}:function(a,b){var c,d=[],e=0,f=b.getElementsByTagName(a);if("*"===a){for(;c=f[e++];)1===c.nodeType&&d.push(c);return d}return f},y.find.CLASS=x.getElementsByClassName&&function(a,b){return typeof b.getElementsByClassName!==V&&I?b.getElementsByClassName(a):void 0},K=[],J=[],(x.qsa=rb.test(c.querySelectorAll))&&(e(function(a){a.innerHTML="<select t=''><option selected=''></option></select>",a.querySelectorAll("[t^='']").length&&J.push("[*^$]="+db+"*(?:''|\"\")"),a.querySelectorAll("[selected]").length||J.push("\\["+db+"*(?:value|"+cb+")"),a.querySelectorAll(":checked").length||J.push(":checked")}),e(function(a){var b=c.createElement("input");b.setAttribute("type","hidden"),a.appendChild(b).setAttribute("name","D"),a.querySelectorAll("[name=d]").length&&J.push("name"+db+"*[*^$|!~]?="),a.querySelectorAll(":enabled").length||J.push(":enabled",":disabled"),a.querySelectorAll("*,:x"),J.push(",.*:")})),(x.matchesSelector=rb.test(L=H.webkitMatchesSelector||H.mozMatchesSelector||H.oMatchesSelector||H.msMatchesSelector))&&e(function(a){x.disconnectedMatch=L.call(a,"div"),L.call(a,"[s!='']:x"),K.push("!=",hb)}),J=J.length&&new RegExp(J.join("|")),K=K.length&&new RegExp(K.join("|")),b=rb.test(H.compareDocumentPosition),M=b||rb.test(H.contains)?function(a,b){var c=9===a.nodeType?a.documentElement:a,d=b&&b.parentNode;return a===d||!(!d||1!==d.nodeType||!(c.contains?c.contains(d):a.compareDocumentPosition&&16&a.compareDocumentPosition(d)))}:function(a,b){if(b)for(;b=b.parentNode;)if(b===a)return!0;return!1},U=b?function(a,b){if(a===b)return E=!0,0;var d=!a.compareDocumentPosition-!b.compareDocumentPosition;return d?d:(d=(a.ownerDocument||a)===(b.ownerDocument||b)?a.compareDocumentPosition(b):1,1&d||!x.sortDetached&&b.compareDocumentPosition(a)===d?a===c||a.ownerDocument===O&&M(O,a)?-1:b===c||b.ownerDocument===O&&M(O,b)?1:D?bb.call(D,a)-bb.call(D,b):0:4&d?-1:1)}:function(a,b){if(a===b)return E=!0,0;var d,e=0,f=a.parentNode,h=b.parentNode,i=[a],j=[b];if(!f||!h)return a===c?-1:b===c?1:f?-1:h?1:D?bb.call(D,a)-bb.call(D,b):0;if(f===h)return g(a,b);for(d=a;d=d.parentNode;)i.unshift(d);for(d=b;d=d.parentNode;)j.unshift(d);for(;i[e]===j[e];)e++;return e?g(i[e],j[e]):i[e]===O?-1:j[e]===O?1:0},c):G},b.matches=function(a,c){return b(a,null,null,c)},b.matchesSelector=function(a,c){if((a.ownerDocument||a)!==G&&F(a),c=c.replace(lb,"='$1']"),!(!x.matchesSelector||!I||K&&K.test(c)||J&&J.test(c)))try{var d=L.call(a,c);if(d||x.disconnectedMatch||a.document&&11!==a.document.nodeType)return d}catch(e){}return b(c,G,null,[a]).length>0},b.contains=function(a,b){return(a.ownerDocument||a)!==G&&F(a),M(a,b)},b.attr=function(a,b){(a.ownerDocument||a)!==G&&F(a);var c=y.attrHandle[b.toLowerCase()],d=c&&X.call(y.attrHandle,b.toLowerCase())?c(a,b,!I):void 0;return void 0!==d?d:x.attributes||!I?a.getAttribute(b):(d=a.getAttributeNode(b))&&d.specified?d.value:null},b.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a)},b.uniqueSort=function(a){var b,c=[],d=0,e=0;if(E=!x.detectDuplicates,D=!x.sortStable&&a.slice(0),a.sort(U),E){for(;b=a[e++];)b===a[e]&&(d=c.push(e));for(;d--;)a.splice(c[d],1)}return D=null,a},z=b.getText=function(a){var b,c="",d=0,e=a.nodeType;if(e){if(1===e||9===e||11===e){if("string"==typeof a.textContent)return a.textContent;for(a=a.firstChild;a;a=a.nextSibling)c+=z(a)}else if(3===e||4===e)return a.nodeValue}else for(;b=a[d++];)c+=z(b);return c},y=b.selectors={cacheLength:50,createPseudo:d,match:ob,attrHandle:{},find:{},relative:{">":{dir:"parentNode",first:!0}," ":{dir:"parentNode"},"+":{dir:"previousSibling",first:!0},"~":{dir:"previousSibling"}},preFilter:{ATTR:function(a){return a[1]=a[1].replace(vb,wb),a[3]=(a[4]||a[5]||"").replace(vb,wb),"~="===a[2]&&(a[3]=" "+a[3]+" "),a.slice(0,4)},CHILD:function(a){return a[1]=a[1].toLowerCase(),"nth"===a[1].slice(0,3)?(a[3]||b.error(a[0]),a[4]=+(a[4]?a[5]+(a[6]||1):2*("even"===a[3]||"odd"===a[3])),a[5]=+(a[7]+a[8]||"odd"===a[3])):a[3]&&b.error(a[0]),a},PSEUDO:function(a){var b,c=!a[5]&&a[2];return ob.CHILD.test(a[0])?null:(a[3]&&void 0!==a[4]?a[2]=a[4]:c&&mb.test(c)&&(b=m(c,!0))&&(b=c.indexOf(")",c.length-b)-c.length)&&(a[0]=a[0].slice(0,b),a[2]=c.slice(0,b)),a.slice(0,3))}},filter:{TAG:function(a){var b=a.replace(vb,wb).toLowerCase();return"*"===a?function(){return!0}:function(a){return a.nodeName&&a.nodeName.toLowerCase()===b}},CLASS:function(a){var b=R[a+" "];return b||(b=new RegExp("(^|"+db+")"+a+"("+db+"|$)"))&&R(a,function(a){return b.test("string"==typeof a.className&&a.className||typeof a.getAttribute!==V&&a.getAttribute("class")||"")})},ATTR:function(a,c,d){return function(e){var f=b.attr(e,a);return null==f?"!="===c:c?(f+="","="===c?f===d:"!="===c?f!==d:"^="===c?d&&0===f.indexOf(d):"*="===c?d&&f.indexOf(d)>-1:"$="===c?d&&f.slice(-d.length)===d:"~="===c?(" "+f+" ").indexOf(d)>-1:"|="===c?f===d||f.slice(0,d.length+1)===d+"-":!1):!0}},CHILD:function(a,b,c,d,e){var f="nth"!==a.slice(0,3),g="last"!==a.slice(-4),h="of-type"===b;return 1===d&&0===e?function(a){return!!a.parentNode}:function(b,c,i){var j,k,l,m,n,o,p=f!==g?"nextSibling":"previousSibling",q=b.parentNode,r=h&&b.nodeName.toLowerCase(),s=!i&&!h;if(q){if(f){for(;p;){for(l=b;l=l[p];)if(h?l.nodeName.toLowerCase()===r:1===l.nodeType)return!1;o=p="only"===a&&!o&&"nextSibling"}return!0}if(o=[g?q.firstChild:q.lastChild],g&&s){for(k=q[N]||(q[N]={}),j=k[a]||[],n=j[0]===P&&j[1],m=j[0]===P&&j[2],l=n&&q.childNodes[n];l=++n&&l&&l[p]||(m=n=0)||o.pop();)if(1===l.nodeType&&++m&&l===b){k[a]=[P,n,m];break}}else if(s&&(j=(b[N]||(b[N]={}))[a])&&j[0]===P)m=j[1];else for(;(l=++n&&l&&l[p]||(m=n=0)||o.pop())&&((h?l.nodeName.toLowerCase()!==r:1!==l.nodeType)||!++m||(s&&((l[N]||(l[N]={}))[a]=[P,m]),l!==b)););return m-=e,m===d||m%d===0&&m/d>=0}}},PSEUDO:function(a,c){var e,f=y.pseudos[a]||y.setFilters[a.toLowerCase()]||b.error("unsupported pseudo: "+a);return f[N]?f(c):f.length>1?(e=[a,a,"",c],y.setFilters.hasOwnProperty(a.toLowerCase())?d(function(a,b){for(var d,e=f(a,c),g=e.length;g--;)d=bb.call(a,e[g]),a[d]=!(b[d]=e[g])}):function(a){return f(a,0,e)}):f}},pseudos:{not:d(function(a){var b=[],c=[],e=B(a.replace(ib,"$1"));return e[N]?d(function(a,b,c,d){for(var f,g=e(a,null,d,[]),h=a.length;h--;)(f=g[h])&&(a[h]=!(b[h]=f))}):function(a,d,f){return b[0]=a,e(b,null,f,c),!c.pop()}}),has:d(function(a){return function(c){return b(a,c).length>0}}),contains:d(function(a){return function(b){return(b.textContent||b.innerText||z(b)).indexOf(a)>-1}}),lang:d(function(a){return nb.test(a||"")||b.error("unsupported lang: "+a),a=a.replace(vb,wb).toLowerCase(),function(b){var c;do if(c=I?b.lang:b.getAttribute("xml:lang")||b.getAttribute("lang"))return c=c.toLowerCase(),c===a||0===c.indexOf(a+"-");while((b=b.parentNode)&&1===b.nodeType);return!1}}),target:function(b){var c=a.location&&a.location.hash;return c&&c.slice(1)===b.id},root:function(a){return a===H},focus:function(a){return a===G.activeElement&&(!G.hasFocus||G.hasFocus())&&!!(a.type||a.href||~a.tabIndex)},enabled:function(a){return a.disabled===!1},disabled:function(a){return a.disabled===!0},checked:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&!!a.checked||"option"===b&&!!a.selected},selected:function(a){return a.parentNode&&a.parentNode.selectedIndex,a.selected===!0},empty:function(a){for(a=a.firstChild;a;a=a.nextSibling)if(a.nodeType<6)return!1;return!0},parent:function(a){return!y.pseudos.empty(a)},header:function(a){return qb.test(a.nodeName)},input:function(a){return pb.test(a.nodeName)},button:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&"button"===a.type||"button"===b},text:function(a){var b;return"input"===a.nodeName.toLowerCase()&&"text"===a.type&&(null==(b=a.getAttribute("type"))||"text"===b.toLowerCase())},first:j(function(){return[0]}),last:j(function(a,b){return[b-1]}),eq:j(function(a,b,c){return[0>c?c+b:c]}),even:j(function(a,b){for(var c=0;b>c;c+=2)a.push(c);return a}),odd:j(function(a,b){for(var c=1;b>c;c+=2)a.push(c);return a}),lt:j(function(a,b,c){for(var d=0>c?c+b:c;--d>=0;)a.push(d);return a}),gt:j(function(a,b,c){for(var d=0>c?c+b:c;++d<b;)a.push(d);return a})}},y.pseudos.nth=y.pseudos.eq;for(w in{radio:!0,checkbox:!0,file:!0,password:!0,image:!0})y.pseudos[w]=h(w);for(w in{submit:!0,reset:!0})y.pseudos[w]=i(w);return l.prototype=y.filters=y.pseudos,y.setFilters=new l,B=b.compile=function(a,b){var c,d=[],e=[],f=T[a+" "];if(!f){for(b||(b=m(a)),c=b.length;c--;)f=s(b[c]),f[N]?d.push(f):e.push(f);f=T(a,t(e,d))}return f},x.sortStable=N.split("").sort(U).join("")===N,x.detectDuplicates=!!E,F(),x.sortDetached=e(function(a){return 1&a.compareDocumentPosition(G.createElement("div"))}),e(function(a){return a.innerHTML="<a href='#'></a>","#"===a.firstChild.getAttribute("href")})||f("type|href|height|width",function(a,b,c){return c?void 0:a.getAttribute(b,"type"===b.toLowerCase()?1:2)}),x.attributes&&e(function(a){return a.innerHTML="<input/>",a.firstChild.setAttribute("value",""),""===a.firstChild.getAttribute("value")})||f("value",function(a,b,c){return c||"input"!==a.nodeName.toLowerCase()?void 0:a.defaultValue}),e(function(a){return null==a.getAttribute("disabled")})||f(cb,function(a,b,c){var d;return c?void 0:a[b]===!0?b.toLowerCase():(d=a.getAttributeNode(b))&&d.specified?d.value:null}),b}(a);ab.find=eb,ab.expr=eb.selectors,ab.expr[":"]=ab.expr.pseudos,ab.unique=eb.uniqueSort,ab.text=eb.getText,ab.isXMLDoc=eb.isXML,ab.contains=eb.contains;var fb=ab.expr.match.needsContext,gb=/^<(\w+)\s*\/?>(?:<\/\1>|)$/,hb=/^.[^:#\[\.,]*$/;ab.filter=function(a,b,c){var d=b[0];return c&&(a=":not("+a+")"),1===b.length&&1===d.nodeType?ab.find.matchesSelector(d,a)?[d]:[]:ab.find.matches(a,ab.grep(b,function(a){return 1===a.nodeType}))},ab.fn.extend({find:function(a){var b,c=this.length,d=[],e=this;if("string"!=typeof a)return this.pushStack(ab(a).filter(function(){for(b=0;c>b;b++)if(ab.contains(e[b],this))return!0}));for(b=0;c>b;b++)ab.find(a,e[b],d);return d=this.pushStack(c>1?ab.unique(d):d),d.selector=this.selector?this.selector+" "+a:a,d

                                       },filter:function(a){return this.pushStack(d(this,a||[],!1))},not:function(a){return this.pushStack(d(this,a||[],!0))},is:function(a){return!!d(this,"string"==typeof a&&fb.test(a)?ab(a):a||[],!1).length}});var ib,jb=/^(?:\s*(<[\w\W]+>)[^>]*|#([\w-]*))$/,kb=ab.fn.init=function(a,b){var c,d;if(!a)return this;if("string"==typeof a){if(c="<"===a[0]&&">"===a[a.length-1]&&a.length>=3?[null,a,null]:jb.exec(a),!c||!c[1]&&b)return!b||b.jquery?(b||ib).find(a):this.constructor(b).find(a);if(c[1]){if(b=b instanceof ab?b[0]:b,ab.merge(this,ab.parseHTML(c[1],b&&b.nodeType?b.ownerDocument||b:$,!0)),gb.test(c[1])&&ab.isPlainObject(b))for(c in b)ab.isFunction(this[c])?this[c](b[c]):this.attr(c,b[c]);return this}return d=$.getElementById(c[2]),d&&d.parentNode&&(this.length=1,this[0]=d),this.context=$,this.selector=a,this}return a.nodeType?(this.context=this[0]=a,this.length=1,this):ab.isFunction(a)?"undefined"!=typeof ib.ready?ib.ready(a):a(ab):(void 0!==a.selector&&(this.selector=a.selector,this.context=a.context),ab.makeArray(a,this))};kb.prototype=ab.fn,ib=ab($);var lb=/^(?:parents|prev(?:Until|All))/,mb={children:!0,contents:!0,next:!0,prev:!0};ab.extend({dir:function(a,b,c){for(var d=[],e=void 0!==c;(a=a[b])&&9!==a.nodeType;)if(1===a.nodeType){if(e&&ab(a).is(c))break;d.push(a)}return d},sibling:function(a,b){for(var c=[];a;a=a.nextSibling)1===a.nodeType&&a!==b&&c.push(a);return c}}),ab.fn.extend({has:function(a){var b=ab(a,this),c=b.length;return this.filter(function(){for(var a=0;c>a;a++)if(ab.contains(this,b[a]))return!0})},closest:function(a,b){for(var c,d=0,e=this.length,f=[],g=fb.test(a)||"string"!=typeof a?ab(a,b||this.context):0;e>d;d++)for(c=this[d];c&&c!==b;c=c.parentNode)if(c.nodeType<11&&(g?g.index(c)>-1:1===c.nodeType&&ab.find.matchesSelector(c,a))){f.push(c);break}return this.pushStack(f.length>1?ab.unique(f):f)},index:function(a){return a?"string"==typeof a?U.call(ab(a),this[0]):U.call(this,a.jquery?a[0]:a):this[0]&&this[0].parentNode?this.first().prevAll().length:-1},add:function(a,b){return this.pushStack(ab.unique(ab.merge(this.get(),ab(a,b))))},addBack:function(a){return this.add(null==a?this.prevObject:this.prevObject.filter(a))}}),ab.each({parent:function(a){var b=a.parentNode;return b&&11!==b.nodeType?b:null},parents:function(a){return ab.dir(a,"parentNode")},parentsUntil:function(a,b,c){return ab.dir(a,"parentNode",c)},next:function(a){return e(a,"nextSibling")},prev:function(a){return e(a,"previousSibling")},nextAll:function(a){return ab.dir(a,"nextSibling")},prevAll:function(a){return ab.dir(a,"previousSibling")},nextUntil:function(a,b,c){return ab.dir(a,"nextSibling",c)},prevUntil:function(a,b,c){return ab.dir(a,"previousSibling",c)},siblings:function(a){return ab.sibling((a.parentNode||{}).firstChild,a)},children:function(a){return ab.sibling(a.firstChild)},contents:function(a){return a.contentDocument||ab.merge([],a.childNodes)}},function(a,b){ab.fn[a]=function(c,d){var e=ab.map(this,b,c);return"Until"!==a.slice(-5)&&(d=c),d&&"string"==typeof d&&(e=ab.filter(d,e)),this.length>1&&(mb[a]||ab.unique(e),lb.test(a)&&e.reverse()),this.pushStack(e)}});var nb=/\S+/g,ob={};ab.Callbacks=function(a){a="string"==typeof a?ob[a]||f(a):ab.extend({},a);var b,c,d,e,g,h,i=[],j=!a.once&&[],k=function(f){for(b=a.memory&&f,c=!0,h=e||0,e=0,g=i.length,d=!0;i&&g>h;h++)if(i[h].apply(f[0],f[1])===!1&&a.stopOnFalse){b=!1;break}d=!1,i&&(j?j.length&&k(j.shift()):b?i=[]:l.disable())},l={add:function(){if(i){var c=i.length;!function f(b){ab.each(b,function(b,c){var d=ab.type(c);"function"===d?a.unique&&l.has(c)||i.push(c):c&&c.length&&"string"!==d&&f(c)})}(arguments),d?g=i.length:b&&(e=c,k(b))}return this},remove:function(){return i&&ab.each(arguments,function(a,b){for(var c;(c=ab.inArray(b,i,c))>-1;)i.splice(c,1),d&&(g>=c&&g--,h>=c&&h--)}),this},has:function(a){return a?ab.inArray(a,i)>-1:!(!i||!i.length)},empty:function(){return i=[],g=0,this},disable:function(){return i=j=b=void 0,this},disabled:function(){return!i},lock:function(){return j=void 0,b||l.disable(),this},locked:function(){return!j},fireWith:function(a,b){return!i||c&&!j||(b=b||[],b=[a,b.slice?b.slice():b],d?j.push(b):k(b)),this},fire:function(){return l.fireWith(this,arguments),this},fired:function(){return!!c}};return l},ab.extend({Deferred:function(a){var b=[["resolve","done",ab.Callbacks("once memory"),"resolved"],["reject","fail",ab.Callbacks("once memory"),"rejected"],["notify","progress",ab.Callbacks("memory")]],c="pending",d={state:function(){return c},always:function(){return e.done(arguments).fail(arguments),this},then:function(){var a=arguments;return ab.Deferred(function(c){ab.each(b,function(b,f){var g=ab.isFunction(a[b])&&a[b];e[f[1]](function(){var a=g&&g.apply(this,arguments);a&&ab.isFunction(a.promise)?a.promise().done(c.resolve).fail(c.reject).progress(c.notify):c[f[0]+"With"](this===d?c.promise():this,g?[a]:arguments)})}),a=null}).promise()},promise:function(a){return null!=a?ab.extend(a,d):d}},e={};return d.pipe=d.then,ab.each(b,function(a,f){var g=f[2],h=f[3];d[f[1]]=g.add,h&&g.add(function(){c=h},b[1^a][2].disable,b[2][2].lock),e[f[0]]=function(){return e[f[0]+"With"](this===e?d:this,arguments),this},e[f[0]+"With"]=g.fireWith}),d.promise(e),a&&a.call(e,e),e},when:function(a){var b,c,d,e=0,f=R.call(arguments),g=f.length,h=1!==g||a&&ab.isFunction(a.promise)?g:0,i=1===h?a:ab.Deferred(),j=function(a,c,d){return function(e){c[a]=this,d[a]=arguments.length>1?R.call(arguments):e,d===b?i.notifyWith(c,d):--h||i.resolveWith(c,d)}};if(g>1)for(b=new Array(g),c=new Array(g),d=new Array(g);g>e;e++)f[e]&&ab.isFunction(f[e].promise)?f[e].promise().done(j(e,d,f)).fail(i.reject).progress(j(e,c,b)):--h;return h||i.resolveWith(d,f),i.promise()}});var pb;ab.fn.ready=function(a){return ab.ready.promise().done(a),this},ab.extend({isReady:!1,readyWait:1,holdReady:function(a){a?ab.readyWait++:ab.ready(!0)},ready:function(a){(a===!0?--ab.readyWait:ab.isReady)||(ab.isReady=!0,a!==!0&&--ab.readyWait>0||(pb.resolveWith($,[ab]),ab.fn.trigger&&ab($).trigger("ready").off("ready")))}}),ab.ready.promise=function(b){return pb||(pb=ab.Deferred(),"complete"===$.readyState?setTimeout(ab.ready):($.addEventListener("DOMContentLoaded",g,!1),a.addEventListener("load",g,!1))),pb.promise(b)},ab.ready.promise();var qb=ab.access=function(a,b,c,d,e,f,g){var h=0,i=a.length,j=null==c;if("object"===ab.type(c)){e=!0;for(h in c)ab.access(a,b,h,c[h],!0,f,g)}else if(void 0!==d&&(e=!0,ab.isFunction(d)||(g=!0),j&&(g?(b.call(a,d),b=null):(j=b,b=function(a,b,c){return j.call(ab(a),c)})),b))for(;i>h;h++)b(a[h],c,g?d:d.call(a[h],h,b(a[h],c)));return e?a:j?b.call(a):i?b(a[0],c):f};ab.acceptData=function(a){return 1===a.nodeType||9===a.nodeType||!+a.nodeType},h.uid=1,h.accepts=ab.acceptData,h.prototype={key:function(a){if(!h.accepts(a))return 0;var b={},c=a[this.expando];if(!c){c=h.uid++;try{b[this.expando]={value:c},Object.defineProperties(a,b)}catch(d){b[this.expando]=c,ab.extend(a,b)}}return this.cache[c]||(this.cache[c]={}),c},set:function(a,b,c){var d,e=this.key(a),f=this.cache[e];if("string"==typeof b)f[b]=c;else if(ab.isEmptyObject(f))ab.extend(this.cache[e],b);else for(d in b)f[d]=b[d];return f},get:function(a,b){var c=this.cache[this.key(a)];return void 0===b?c:c[b]},access:function(a,b,c){var d;return void 0===b||b&&"string"==typeof b&&void 0===c?(d=this.get(a,b),void 0!==d?d:this.get(a,ab.camelCase(b))):(this.set(a,b,c),void 0!==c?c:b)},remove:function(a,b){var c,d,e,f=this.key(a),g=this.cache[f];if(void 0===b)this.cache[f]={};else{ab.isArray(b)?d=b.concat(b.map(ab.camelCase)):(e=ab.camelCase(b),b in g?d=[b,e]:(d=e,d=d in g?[d]:d.match(nb)||[])),c=d.length;for(;c--;)delete g[d[c]]}},hasData:function(a){return!ab.isEmptyObject(this.cache[a[this.expando]]||{})},discard:function(a){a[this.expando]&&delete this.cache[a[this.expando]]}};var rb=new h,sb=new h,tb=/^(?:\{[\w\W]*\}|\[[\w\W]*\])$/,ub=/([A-Z])/g;ab.extend({hasData:function(a){return sb.hasData(a)||rb.hasData(a)},data:function(a,b,c){return sb.access(a,b,c)},removeData:function(a,b){sb.remove(a,b)},_data:function(a,b,c){return rb.access(a,b,c)},_removeData:function(a,b){rb.remove(a,b)}}),ab.fn.extend({data:function(a,b){var c,d,e,f=this[0],g=f&&f.attributes;if(void 0===a){if(this.length&&(e=sb.get(f),1===f.nodeType&&!rb.get(f,"hasDataAttrs"))){for(c=g.length;c--;)d=g[c].name,0===d.indexOf("data-")&&(d=ab.camelCase(d.slice(5)),i(f,d,e[d]));rb.set(f,"hasDataAttrs",!0)}return e}return"object"==typeof a?this.each(function(){sb.set(this,a)}):qb(this,function(b){var c,d=ab.camelCase(a);if(f&&void 0===b){if(c=sb.get(f,a),void 0!==c)return c;if(c=sb.get(f,d),void 0!==c)return c;if(c=i(f,d,void 0),void 0!==c)return c}else this.each(function(){var c=sb.get(this,d);sb.set(this,d,b),-1!==a.indexOf("-")&&void 0!==c&&sb.set(this,a,b)})},null,b,arguments.length>1,null,!0)},removeData:function(a){return this.each(function(){sb.remove(this,a)})}}),ab.extend({queue:function(a,b,c){var d;return a?(b=(b||"fx")+"queue",d=rb.get(a,b),c&&(!d||ab.isArray(c)?d=rb.access(a,b,ab.makeArray(c)):d.push(c)),d||[]):void 0},dequeue:function(a,b){b=b||"fx";var c=ab.queue(a,b),d=c.length,e=c.shift(),f=ab._queueHooks(a,b),g=function(){ab.dequeue(a,b)};"inprogress"===e&&(e=c.shift(),d--),e&&("fx"===b&&c.unshift("inprogress"),delete f.stop,e.call(a,g,f)),!d&&f&&f.empty.fire()},_queueHooks:function(a,b){var c=b+"queueHooks";return rb.get(a,c)||rb.access(a,c,{empty:ab.Callbacks("once memory").add(function(){rb.remove(a,[b+"queue",c])})})}}),ab.fn.extend({queue:function(a,b){var c=2;return"string"!=typeof a&&(b=a,a="fx",c--),arguments.length<c?ab.queue(this[0],a):void 0===b?this:this.each(function(){var c=ab.queue(this,a,b);ab._queueHooks(this,a),"fx"===a&&"inprogress"!==c[0]&&ab.dequeue(this,a)})},dequeue:function(a){return this.each(function(){ab.dequeue(this,a)})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,b){var c,d=1,e=ab.Deferred(),f=this,g=this.length,h=function(){--d||e.resolveWith(f,[f])};for("string"!=typeof a&&(b=a,a=void 0),a=a||"fx";g--;)c=rb.get(f[g],a+"queueHooks"),c&&c.empty&&(d++,c.empty.add(h));return h(),e.promise(b)}});var vb=/[+-]?(?:\d*\.|)\d+(?:[eE][+-]?\d+|)/.source,wb=["Top","Right","Bottom","Left"],xb=function(a,b){return a=b||a,"none"===ab.css(a,"display")||!ab.contains(a.ownerDocument,a)},yb=/^(?:checkbox|radio)$/i;!function(){var a=$.createDocumentFragment(),b=a.appendChild($.createElement("div"));b.innerHTML="<input type='radio' checked='checked' name='t'/>",Z.checkClone=b.cloneNode(!0).cloneNode(!0).lastChild.checked,b.innerHTML="<textarea>x</textarea>",Z.noCloneChecked=!!b.cloneNode(!0).lastChild.defaultValue}();var zb="undefined";Z.focusinBubbles="onfocusin"in a;var Ab=/^key/,Bb=/^(?:mouse|contextmenu)|click/,Cb=/^(?:focusinfocus|focusoutblur)$/,Db=/^([^.]*)(?:\.(.+)|)$/;ab.event={global:{},add:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,n,o,p,q=rb.get(a);if(q)for(c.handler&&(f=c,c=f.handler,e=f.selector),c.guid||(c.guid=ab.guid++),(i=q.events)||(i=q.events={}),(g=q.handle)||(g=q.handle=function(b){return typeof ab!==zb&&ab.event.triggered!==b.type?ab.event.dispatch.apply(a,arguments):void 0}),b=(b||"").match(nb)||[""],j=b.length;j--;)h=Db.exec(b[j])||[],n=p=h[1],o=(h[2]||"").split(".").sort(),n&&(l=ab.event.special[n]||{},n=(e?l.delegateType:l.bindType)||n,l=ab.event.special[n]||{},k=ab.extend({type:n,origType:p,data:d,handler:c,guid:c.guid,selector:e,needsContext:e&&ab.expr.match.needsContext.test(e),namespace:o.join(".")},f),(m=i[n])||(m=i[n]=[],m.delegateCount=0,l.setup&&l.setup.call(a,d,o,g)!==!1||a.addEventListener&&a.addEventListener(n,g,!1)),l.add&&(l.add.call(a,k),k.handler.guid||(k.handler.guid=c.guid)),e?m.splice(m.delegateCount++,0,k):m.push(k),ab.event.global[n]=!0)},remove:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,n,o,p,q=rb.hasData(a)&&rb.get(a);if(q&&(i=q.events)){for(b=(b||"").match(nb)||[""],j=b.length;j--;)if(h=Db.exec(b[j])||[],n=p=h[1],o=(h[2]||"").split(".").sort(),n){for(l=ab.event.special[n]||{},n=(d?l.delegateType:l.bindType)||n,m=i[n]||[],h=h[2]&&new RegExp("(^|\\.)"+o.join("\\.(?:.*\\.|)")+"(\\.|$)"),g=f=m.length;f--;)k=m[f],!e&&p!==k.origType||c&&c.guid!==k.guid||h&&!h.test(k.namespace)||d&&d!==k.selector&&("**"!==d||!k.selector)||(m.splice(f,1),k.selector&&m.delegateCount--,l.remove&&l.remove.call(a,k));g&&!m.length&&(l.teardown&&l.teardown.call(a,o,q.handle)!==!1||ab.removeEvent(a,n,q.handle),delete i[n])}else for(n in i)ab.event.remove(a,n+b[j],c,d,!0);ab.isEmptyObject(i)&&(delete q.handle,rb.remove(a,"events"))}},trigger:function(b,c,d,e){var f,g,h,i,j,k,l,m=[d||$],n=X.call(b,"type")?b.type:b,o=X.call(b,"namespace")?b.namespace.split("."):[];if(g=h=d=d||$,3!==d.nodeType&&8!==d.nodeType&&!Cb.test(n+ab.event.triggered)&&(n.indexOf(".")>=0&&(o=n.split("."),n=o.shift(),o.sort()),j=n.indexOf(":")<0&&"on"+n,b=b[ab.expando]?b:new ab.Event(n,"object"==typeof b&&b),b.isTrigger=e?2:3,b.namespace=o.join("."),b.namespace_re=b.namespace?new RegExp("(^|\\.)"+o.join("\\.(?:.*\\.|)")+"(\\.|$)"):null,b.result=void 0,b.target||(b.target=d),c=null==c?[b]:ab.makeArray(c,[b]),l=ab.event.special[n]||{},e||!l.trigger||l.trigger.apply(d,c)!==!1)){if(!e&&!l.noBubble&&!ab.isWindow(d)){for(i=l.delegateType||n,Cb.test(i+n)||(g=g.parentNode);g;g=g.parentNode)m.push(g),h=g;h===(d.ownerDocument||$)&&m.push(h.defaultView||h.parentWindow||a)}for(f=0;(g=m[f++])&&!b.isPropagationStopped();)b.type=f>1?i:l.bindType||n,k=(rb.get(g,"events")||{})[b.type]&&rb.get(g,"handle"),k&&k.apply(g,c),k=j&&g[j],k&&k.apply&&ab.acceptData(g)&&(b.result=k.apply(g,c),b.result===!1&&b.preventDefault());return b.type=n,e||b.isDefaultPrevented()||l._default&&l._default.apply(m.pop(),c)!==!1||!ab.acceptData(d)||j&&ab.isFunction(d[n])&&!ab.isWindow(d)&&(h=d[j],h&&(d[j]=null),ab.event.triggered=n,d[n](),ab.event.triggered=void 0,h&&(d[j]=h)),b.result}},dispatch:function(a){a=ab.event.fix(a);var b,c,d,e,f,g=[],h=R.call(arguments),i=(rb.get(this,"events")||{})[a.type]||[],j=ab.event.special[a.type]||{};if(h[0]=a,a.delegateTarget=this,!j.preDispatch||j.preDispatch.call(this,a)!==!1){for(g=ab.event.handlers.call(this,a,i),b=0;(e=g[b++])&&!a.isPropagationStopped();)for(a.currentTarget=e.elem,c=0;(f=e.handlers[c++])&&!a.isImmediatePropagationStopped();)(!a.namespace_re||a.namespace_re.test(f.namespace))&&(a.handleObj=f,a.data=f.data,d=((ab.event.special[f.origType]||{}).handle||f.handler).apply(e.elem,h),void 0!==d&&(a.result=d)===!1&&(a.preventDefault(),a.stopPropagation()));return j.postDispatch&&j.postDispatch.call(this,a),a.result}},handlers:function(a,b){var c,d,e,f,g=[],h=b.delegateCount,i=a.target;if(h&&i.nodeType&&(!a.button||"click"!==a.type))for(;i!==this;i=i.parentNode||this)if(i.disabled!==!0||"click"!==a.type){for(d=[],c=0;h>c;c++)f=b[c],e=f.selector+" ",void 0===d[e]&&(d[e]=f.needsContext?ab(e,this).index(i)>=0:ab.find(e,this,null,[i]).length),d[e]&&d.push(f);d.length&&g.push({elem:i,handlers:d})}return h<b.length&&g.push({elem:this,handlers:b.slice(h)}),g},props:"altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){return null==a.which&&(a.which=null!=b.charCode?b.charCode:b.keyCode),a}},mouseHooks:{props:"button buttons clientX clientY offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,b){var c,d,e,f=b.button;return null==a.pageX&&null!=b.clientX&&(c=a.target.ownerDocument||$,d=c.documentElement,e=c.body,a.pageX=b.clientX+(d&&d.scrollLeft||e&&e.scrollLeft||0)-(d&&d.clientLeft||e&&e.clientLeft||0),a.pageY=b.clientY+(d&&d.scrollTop||e&&e.scrollTop||0)-(d&&d.clientTop||e&&e.clientTop||0)),a.which||void 0===f||(a.which=1&f?1:2&f?3:4&f?2:0),a}},fix:function(a){if(a[ab.expando])return a;var b,c,d,e=a.type,f=a,g=this.fixHooks[e];for(g||(this.fixHooks[e]=g=Bb.test(e)?this.mouseHooks:Ab.test(e)?this.keyHooks:{}),d=g.props?this.props.concat(g.props):this.props,a=new ab.Event(f),b=d.length;b--;)c=d[b],a[c]=f[c];return a.target||(a.target=$),3===a.target.nodeType&&(a.target=a.target.parentNode),g.filter?g.filter(a,f):a},special:{load:{noBubble:!0},focus:{trigger:function(){return this!==l()&&this.focus?(this.focus(),!1):void 0},delegateType:"focusin"},blur:{trigger:function(){return this===l()&&this.blur?(this.blur(),!1):void 0},delegateType:"focusout"},click:{trigger:function(){return"checkbox"===this.type&&this.click&&ab.nodeName(this,"input")?(this.click(),!1):void 0},_default:function(a){return ab.nodeName(a.target,"a")}},beforeunload:{postDispatch:function(a){void 0!==a.result&&(a.originalEvent.returnValue=a.result)}}},simulate:function(a,b,c,d){var e=ab.extend(new ab.Event,c,{type:a,isSimulated:!0,originalEvent:{}});d?ab.event.trigger(e,null,b):ab.event.dispatch.call(b,e),e.isDefaultPrevented()&&c.preventDefault()}},ab.removeEvent=function(a,b,c){a.removeEventListener&&a.removeEventListener(b,c,!1)},ab.Event=function(a,b){return this instanceof ab.Event?(a&&a.type?(this.originalEvent=a,this.type=a.type,this.isDefaultPrevented=a.defaultPrevented||void 0===a.defaultPrevented&&a.getPreventDefault&&a.getPreventDefault()?j:k):this.type=a,b&&ab.extend(this,b),this.timeStamp=a&&a.timeStamp||ab.now(),void(this[ab.expando]=!0)):new ab.Event(a,b)},ab.Event.prototype={isDefaultPrevented:k,isPropagationStopped:k,isImmediatePropagationStopped:k,preventDefault:function(){var a=this.originalEvent;this.isDefaultPrevented=j,a&&a.preventDefault&&a.preventDefault()},stopPropagation:function(){var a=this.originalEvent;this.isPropagationStopped=j,a&&a.stopPropagation&&a.stopPropagation()},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=j,this.stopPropagation()}},ab.each({mouseenter:"mouseover",mouseleave:"mouseout"},function(a,b){ab.event.special[a]={delegateType:b,bindType:b,handle:function(a){var c,d=this,e=a.relatedTarget,f=a.handleObj;return(!e||e!==d&&!ab.contains(d,e))&&(a.type=f.origType,c=f.handler.apply(this,arguments),a.type=b),c}}}),Z.focusinBubbles||ab.each({focus:"focusin",blur:"focusout"},function(a,b){var c=function(a){ab.event.simulate(b,a.target,ab.event.fix(a),!0)};ab.event.special[b]={setup:function(){var d=this.ownerDocument||this,e=rb.access(d,b);e||d.addEventListener(a,c,!0),rb.access(d,b,(e||0)+1)},teardown:function(){var d=this.ownerDocument||this,e=rb.access(d,b)-1;e?rb.access(d,b,e):(d.removeEventListener(a,c,!0),rb.remove(d,b))}}}),ab.fn.extend({on:function(a,b,c,d,e){var f,g;if("object"==typeof a){"string"!=typeof b&&(c=c||b,b=void 0);for(g in a)this.on(g,b,c,a[g],e);return this}if(null==c&&null==d?(d=b,c=b=void 0):null==d&&("string"==typeof b?(d=c,c=void 0):(d=c,c=b,b=void 0)),d===!1)d=k;else if(!d)return this;return 1===e&&(f=d,d=function(a){return ab().off(a),f.apply(this,arguments)},d.guid=f.guid||(f.guid=ab.guid++)),this.each(function(){ab.event.add(this,a,d,c,b)})},one:function(a,b,c,d){return this.on(a,b,c,d,1)},off:function(a,b,c){var d,e;if(a&&a.preventDefault&&a.handleObj)return d=a.handleObj,ab(a.delegateTarget).off(d.namespace?d.origType+"."+d.namespace:d.origType,d.selector,d.handler),this;if("object"==typeof a){for(e in a)this.off(e,b,a[e]);return this}return(b===!1||"function"==typeof b)&&(c=b,b=void 0),c===!1&&(c=k),this.each(function(){ab.event.remove(this,a,c,b)})},trigger:function(a,b){return this.each(function(){ab.event.trigger(a,b,this)})},triggerHandler:function(a,b){var c=this[0];return c?ab.event.trigger(a,b,c,!0):void 0}});var Eb=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\w:]+)[^>]*)\/>/gi,Fb=/<([\w:]+)/,Gb=/<|&#?\w+;/,Hb=/<(?:script|style|link)/i,Ib=/checked\s*(?:[^=]|=\s*.checked.)/i,Jb=/^$|\/(?:java|ecma)script/i,Kb=/^true\/(.*)/,Lb=/^\s*<!(?:\[CDATA\[|--)|(?:\]\]|--)>\s*$/g,Mb={option:[1,"<select multiple='multiple'>","</select>"],thead:[1,"<table>","</table>"],col:[2,"<table><colgroup>","</colgroup></table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],_default:[0,"",""]};Mb.optgroup=Mb.option,Mb.tbody=Mb.tfoot=Mb.colgroup=Mb.caption=Mb.thead,Mb.th=Mb.td,ab.extend({clone:function(a,b,c){var d,e,f,g,h=a.cloneNode(!0),i=ab.contains(a.ownerDocument,a);if(!(Z.noCloneChecked||1!==a.nodeType&&11!==a.nodeType||ab.isXMLDoc(a)))for(g=r(h),f=r(a),d=0,e=f.length;e>d;d++)s(f[d],g[d]);if(b)if(c)for(f=f||r(a),g=g||r(h),d=0,e=f.length;e>d;d++)q(f[d],g[d]);else q(a,h);return g=r(h,"script"),g.length>0&&p(g,!i&&r(a,"script")),h},buildFragment:function(a,b,c,d){for(var e,f,g,h,i,j,k=b.createDocumentFragment(),l=[],m=0,n=a.length;n>m;m++)if(e=a[m],e||0===e)if("object"===ab.type(e))ab.merge(l,e.nodeType?[e]:e);else if(Gb.test(e)){for(f=f||k.appendChild(b.createElement("div")),g=(Fb.exec(e)||["",""])[1].toLowerCase(),h=Mb[g]||Mb._default,f.innerHTML=h[1]+e.replace(Eb,"<$1></$2>")+h[2],j=h[0];j--;)f=f.lastChild;ab.merge(l,f.childNodes),f=k.firstChild,f.textContent=""}else l.push(b.createTextNode(e));for(k.textContent="",m=0;e=l[m++];)if((!d||-1===ab.inArray(e,d))&&(i=ab.contains(e.ownerDocument,e),f=r(k.appendChild(e),"script"),i&&p(f),c))for(j=0;e=f[j++];)Jb.test(e.type||"")&&c.push(e);return k},cleanData:function(a){for(var b,c,d,e,f,g,h=ab.event.special,i=0;void 0!==(c=a[i]);i++){if(ab.acceptData(c)&&(f=c[rb.expando],f&&(b=rb.cache[f]))){if(d=Object.keys(b.events||{}),d.length)for(g=0;void 0!==(e=d[g]);g++)h[e]?ab.event.remove(c,e):ab.removeEvent(c,e,b.handle);rb.cache[f]&&delete rb.cache[f]}delete sb.cache[c[sb.expando]]}}}),ab.fn.extend({text:function(a){return qb(this,function(a){return void 0===a?ab.text(this):this.empty().each(function(){(1===this.nodeType||11===this.nodeType||9===this.nodeType)&&(this.textContent=a)})},null,a,arguments.length)},append:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=m(this,a);b.appendChild(a)}})},prepend:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=m(this,a);b.insertBefore(a,b.firstChild)}})},before:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this)})},after:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this.nextSibling)})},remove:function(a,b){for(var c,d=a?ab.filter(a,this):this,e=0;null!=(c=d[e]);e++)b||1!==c.nodeType||ab.cleanData(r(c)),c.parentNode&&(b&&ab.contains(c.ownerDocument,c)&&p(r(c,"script")),c.parentNode.removeChild(c));return this},empty:function(){for(var a,b=0;null!=(a=this[b]);b++)1===a.nodeType&&(ab.cleanData(r(a,!1)),a.textContent="");return this},clone:function(a,b){return a=null==a?!1:a,b=null==b?a:b,this.map(function(){return ab.clone(this,a,b)})},html:function(a){return qb(this,function(a){var b=this[0]||{},c=0,d=this.length;if(void 0===a&&1===b.nodeType)return b.innerHTML;if("string"==typeof a&&!Hb.test(a)&&!Mb[(Fb.exec(a)||["",""])[1].toLowerCase()]){a=a.replace(Eb,"<$1></$2>");try{for(;d>c;c++)b=this[c]||{},1===b.nodeType&&(ab.cleanData(r(b,!1)),b.innerHTML=a);b=0}catch(e){}}b&&this.empty().append(a)},null,a,arguments.length)},replaceWith:function(){var a=arguments[0];return this.domManip(arguments,function(b){a=this.parentNode,ab.cleanData(r(this)),a&&a.replaceChild(b,this)}),a&&(a.length||a.nodeType)?this:this.remove()},detach:function(a){return this.remove(a,!0)},domManip:function(a,b){a=S.apply([],a);var c,d,e,f,g,h,i=0,j=this.length,k=this,l=j-1,m=a[0],p=ab.isFunction(m);if(p||j>1&&"string"==typeof m&&!Z.checkClone&&Ib.test(m))return this.each(function(c){var d=k.eq(c);p&&(a[0]=m.call(this,c,d.html())),d.domManip(a,b)});if(j&&(c=ab.buildFragment(a,this[0].ownerDocument,!1,this),d=c.firstChild,1===c.childNodes.length&&(c=d),d)){for(e=ab.map(r(c,"script"),n),f=e.length;j>i;i++)g=c,i!==l&&(g=ab.clone(g,!0,!0),f&&ab.merge(e,r(g,"script"))),b.call(this[i],g,i);if(f)for(h=e[e.length-1].ownerDocument,ab.map(e,o),i=0;f>i;i++)g=e[i],Jb.test(g.type||"")&&!rb.access(g,"globalEval")&&ab.contains(h,g)&&(g.src?ab._evalUrl&&ab._evalUrl(g.src):ab.globalEval(g.textContent.replace(Lb,"")))}return this}}),ab.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){ab.fn[a]=function(a){for(var c,d=[],e=ab(a),f=e.length-1,g=0;f>=g;g++)c=g===f?this:this.clone(!0),ab(e[g])[b](c),T.apply(d,c.get());return this.pushStack(d)}});var Nb,Ob={},Pb=/^margin/,Qb=new RegExp("^("+vb+")(?!px)[a-z%]+$","i"),Rb=function(a){return a.ownerDocument.defaultView.getComputedStyle(a,null)};!function(){function b(){h.style.cssText="-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;padding:1px;border:1px;display:block;width:4px;margin-top:1%;position:absolute;top:1%",f.appendChild(g);var b=a.getComputedStyle(h,null);c="1%"!==b.top,d="4px"===b.width,f.removeChild(g)}var c,d,e="padding:0;margin:0;border:0;display:block;-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box",f=$.documentElement,g=$.createElement("div"),h=$.createElement("div");h.style.backgroundClip="content-box",h.cloneNode(!0).style.backgroundClip="",Z.clearCloneStyle="content-box"===h.style.backgroundClip,g.style.cssText="border:0;width:0;height:0;position:absolute;top:0;left:-9999px;margin-top:1px",g.appendChild(h),a.getComputedStyle&&ab.extend(Z,{pixelPosition:function(){return b(),c},boxSizingReliable:function(){return null==d&&b(),d},reliableMarginRight:function(){var b,c=h.appendChild($.createElement("div"));return c.style.cssText=h.style.cssText=e,c.style.marginRight=c.style.width="0",h.style.width="1px",f.appendChild(g),b=!parseFloat(a.getComputedStyle(c,null).marginRight),f.removeChild(g),h.innerHTML="",b}})}(),ab.swap=function(a,b,c,d){var e,f,g={};for(f in b)g[f]=a.style[f],a.style[f]=b[f];e=c.apply(a,d||[]);for(f in b)a.style[f]=g[f];return e};var Sb=/^(none|table(?!-c[ea]).+)/,Tb=new RegExp("^("+vb+")(.*)$","i"),Ub=new RegExp("^([+-])=("+vb+")","i"),Vb={position:"absolute",visibility:"hidden",display:"block"},Wb={letterSpacing:0,fontWeight:400},Xb=["Webkit","O","Moz","ms"];ab.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=v(a,"opacity");return""===c?"1":c}}}},cssNumber:{columnCount:!0,fillOpacity:!0,fontWeight:!0,lineHeight:!0,opacity:!0,order:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},cssProps:{"float":"cssFloat"},style:function(a,b,c,d){if(a&&3!==a.nodeType&&8!==a.nodeType&&a.style){var e,f,g,h=ab.camelCase(b),i=a.style;return b=ab.cssProps[h]||(ab.cssProps[h]=x(i,h)),g=ab.cssHooks[b]||ab.cssHooks[h],void 0===c?g&&"get"in g&&void 0!==(e=g.get(a,!1,d))?e:i[b]:(f=typeof c,"string"===f&&(e=Ub.exec(c))&&(c=(e[1]+1)*e[2]+parseFloat(ab.css(a,b)),f="number"),null!=c&&c===c&&("number"!==f||ab.cssNumber[h]||(c+="px"),Z.clearCloneStyle||""!==c||0!==b.indexOf("background")||(i[b]="inherit"),g&&"set"in g&&void 0===(c=g.set(a,c,d))||(i[b]="",i[b]=c)),void 0)}},css:function(a,b,c,d){var e,f,g,h=ab.camelCase(b);return b=ab.cssProps[h]||(ab.cssProps[h]=x(a.style,h)),g=ab.cssHooks[b]||ab.cssHooks[h],g&&"get"in g&&(e=g.get(a,!0,c)),void 0===e&&(e=v(a,b,d)),"normal"===e&&b in Wb&&(e=Wb[b]),""===c||c?(f=parseFloat(e),c===!0||ab.isNumeric(f)?f||0:e):e}}),ab.each(["height","width"],function(a,b){ab.cssHooks[b]={get:function(a,c,d){return c?0===a.offsetWidth&&Sb.test(ab.css(a,"display"))?ab.swap(a,Vb,function(){return A(a,b,d)}):A(a,b,d):void 0},set:function(a,c,d){var e=d&&Rb(a);return y(a,c,d?z(a,b,d,"border-box"===ab.css(a,"boxSizing",!1,e),e):0)}}}),ab.cssHooks.marginRight=w(Z.reliableMarginRight,function(a,b){return b?ab.swap(a,{display:"inline-block"},v,[a,"marginRight"]):void 0}),ab.each({margin:"",padding:"",border:"Width"},function(a,b){ab.cssHooks[a+b]={expand:function(c){for(var d=0,e={},f="string"==typeof c?c.split(" "):[c];4>d;d++)e[a+wb[d]+b]=f[d]||f[d-2]||f[0];return e}},Pb.test(a)||(ab.cssHooks[a+b].set=y)}),ab.fn.extend({css:function(a,b){return qb(this,function(a,b,c){var d,e,f={},g=0;if(ab.isArray(b)){for(d=Rb(a),e=b.length;e>g;g++)f[b[g]]=ab.css(a,b[g],!1,d);return f}return void 0!==c?ab.style(a,b,c):ab.css(a,b)},a,b,arguments.length>1)},show:function(){return B(this,!0)},hide:function(){return B(this)},toggle:function(a){return"boolean"==typeof a?a?this.show():this.hide():this.each(function(){xb(this)?ab(this).show():ab(this).hide()})}}),ab.Tween=C,C.prototype={constructor:C,init:function(a,b,c,d,e,f){this.elem=a,this.prop=c,this.easing=e||"swing",this.options=b,this.start=this.now=this.cur(),this.end=d,this.unit=f||(ab.cssNumber[c]?"":"px")},cur:function(){var a=C.propHooks[this.prop];return a&&a.get?a.get(this):C.propHooks._default.get(this)},run:function(a){var b,c=C.propHooks[this.prop];return this.pos=b=this.options.duration?ab.easing[this.easing](a,this.options.duration*a,0,1,this.options.duration):a,this.now=(this.end-this.start)*b+this.start,this.options.step&&this.options.step.call(this.elem,this.now,this),c&&c.set?c.set(this):C.propHooks._default.set(this),this}},C.prototype.init.prototype=C.prototype,C.propHooks={_default:{get:function(a){var b;return null==a.elem[a.prop]||a.elem.style&&null!=a.elem.style[a.prop]?(b=ab.css(a.elem,a.prop,""),b&&"auto"!==b?b:0):a.elem[a.prop]},set:function(a){ab.fx.step[a.prop]?ab.fx.step[a.prop](a):a.elem.style&&(null!=a.elem.style[ab.cssProps[a.prop]]||ab.cssHooks[a.prop])?ab.style(a.elem,a.prop,a.now+a.unit):a.elem[a.prop]=a.now}}},C.propHooks.scrollTop=C.propHooks.scrollLeft={set:function(a){a.elem.nodeType&&a.elem.parentNode&&(a.elem[a.prop]=a.now)}},ab.easing={linear:function(a){return a},swing:function(a){return.5-Math.cos(a*Math.PI)/2}},ab.fx=C.prototype.init,ab.fx.step={};var Yb,Zb,$b=/^(?:toggle|show|hide)$/,_b=new RegExp("^(?:([+-])=|)("+vb+")([a-z%]*)$","i"),ac=/queueHooks$/,bc=[G],cc={"*":[function(a,b){var c=this.createTween(a,b),d=c.cur(),e=_b.exec(b),f=e&&e[3]||(ab.cssNumber[a]?"":"px"),g=(ab.cssNumber[a]||"px"!==f&&+d)&&_b.exec(ab.css(c.elem,a)),h=1,i=20;if(g&&g[3]!==f){f=f||g[3],e=e||[],g=+d||1;do h=h||".5",g/=h,ab.style(c.elem,a,g+f);while(h!==(h=c.cur()/d)&&1!==h&&--i)}return e&&(g=c.start=+g||+d||0,c.unit=f,c.end=e[1]?g+(e[1]+1)*e[2]:+e[2]),c}]};ab.Animation=ab.extend(I,{tweener:function(a,b){ab.isFunction(a)?(b=a,a=["*"]):a=a.split(" ");for(var c,d=0,e=a.length;e>d;d++)c=a[d],cc[c]=cc[c]||[],cc[c].unshift(b)},prefilter:function(a,b){b?bc.unshift(a):bc.push(a)}}),ab.speed=function(a,b,c){var d=a&&"object"==typeof a?ab.extend({},a):{complete:c||!c&&b||ab.isFunction(a)&&a,duration:a,easing:c&&b||b&&!ab.isFunction(b)&&b};return d.duration=ab.fx.off?0:"number"==typeof d.duration?d.duration:d.duration in ab.fx.speeds?ab.fx.speeds[d.duration]:ab.fx.speeds._default,(null==d.queue||d.queue===!0)&&(d.queue="fx"),d.old=d.complete,d.complete=function(){ab.isFunction(d.old)&&d.old.call(this),d.queue&&ab.dequeue(this,d.queue)},d},ab.fn.extend({fadeTo:function(a,b,c,d){return this.filter(xb).css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(a,b,c,d){var e=ab.isEmptyObject(a),f=ab.speed(b,c,d),g=function(){var b=I(this,ab.extend({},a),f);(e||rb.get(this,"finish"))&&b.stop(!0)};return g.finish=g,e||f.queue===!1?this.each(g):this.queue(f.queue,g)},stop:function(a,b,c){var d=function(a){var b=a.stop;delete a.stop,b(c)};return"string"!=typeof a&&(c=b,b=a,a=void 0),b&&a!==!1&&this.queue(a||"fx",[]),this.each(function(){var b=!0,e=null!=a&&a+"queueHooks",f=ab.timers,g=rb.get(this);if(e)g[e]&&g[e].stop&&d(g[e]);else for(e in g)g[e]&&g[e].stop&&ac.test(e)&&d(g[e]);for(e=f.length;e--;)f[e].elem!==this||null!=a&&f[e].queue!==a||(f[e].anim.stop(c),b=!1,f.splice(e,1));(b||!c)&&ab.dequeue(this,a)})},finish:function(a){return a!==!1&&(a=a||"fx"),this.each(function(){var b,c=rb.get(this),d=c[a+"queue"],e=c[a+"queueHooks"],f=ab.timers,g=d?d.length:0;for(c.finish=!0,ab.queue(this,a,[]),e&&e.stop&&e.stop.call(this,!0),b=f.length;b--;)f[b].elem===this&&f[b].queue===a&&(f[b].anim.stop(!0),f.splice(b,1));for(b=0;g>b;b++)d[b]&&d[b].finish&&d[b].finish.call(this);delete c.finish})}}),ab.each(["toggle","show","hide"],function(a,b){var c=ab.fn[b];ab.fn[b]=function(a,d,e){return null==a||"boolean"==typeof a?c.apply(this,arguments):this.animate(E(b,!0),a,d,e)

                                       }}),ab.each({slideDown:E("show"),slideUp:E("hide"),slideToggle:E("toggle"),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(a,b){ab.fn[a]=function(a,c,d){return this.animate(b,a,c,d)}}),ab.timers=[],ab.fx.tick=function(){var a,b=0,c=ab.timers;for(Yb=ab.now();b<c.length;b++)a=c[b],a()||c[b]!==a||c.splice(b--,1);c.length||ab.fx.stop(),Yb=void 0},ab.fx.timer=function(a){ab.timers.push(a),a()?ab.fx.start():ab.timers.pop()},ab.fx.interval=13,ab.fx.start=function(){Zb||(Zb=setInterval(ab.fx.tick,ab.fx.interval))},ab.fx.stop=function(){clearInterval(Zb),Zb=null},ab.fx.speeds={slow:600,fast:200,_default:400},ab.fn.delay=function(a,b){return a=ab.fx?ab.fx.speeds[a]||a:a,b=b||"fx",this.queue(b,function(b,c){var d=setTimeout(b,a);c.stop=function(){clearTimeout(d)}})},function(){var a=$.createElement("input"),b=$.createElement("select"),c=b.appendChild($.createElement("option"));a.type="checkbox",Z.checkOn=""!==a.value,Z.optSelected=c.selected,b.disabled=!0,Z.optDisabled=!c.disabled,a=$.createElement("input"),a.value="t",a.type="radio",Z.radioValue="t"===a.value}();var dc,ec,fc=ab.expr.attrHandle;ab.fn.extend({attr:function(a,b){return qb(this,ab.attr,a,b,arguments.length>1)},removeAttr:function(a){return this.each(function(){ab.removeAttr(this,a)})}}),ab.extend({attr:function(a,b,c){var d,e,f=a.nodeType;if(a&&3!==f&&8!==f&&2!==f)return typeof a.getAttribute===zb?ab.prop(a,b,c):(1===f&&ab.isXMLDoc(a)||(b=b.toLowerCase(),d=ab.attrHooks[b]||(ab.expr.match.bool.test(b)?ec:dc)),void 0===c?d&&"get"in d&&null!==(e=d.get(a,b))?e:(e=ab.find.attr(a,b),null==e?void 0:e):null!==c?d&&"set"in d&&void 0!==(e=d.set(a,c,b))?e:(a.setAttribute(b,c+""),c):void ab.removeAttr(a,b))},removeAttr:function(a,b){var c,d,e=0,f=b&&b.match(nb);if(f&&1===a.nodeType)for(;c=f[e++];)d=ab.propFix[c]||c,ab.expr.match.bool.test(c)&&(a[d]=!1),a.removeAttribute(c)},attrHooks:{type:{set:function(a,b){if(!Z.radioValue&&"radio"===b&&ab.nodeName(a,"input")){var c=a.value;return a.setAttribute("type",b),c&&(a.value=c),b}}}}}),ec={set:function(a,b,c){return b===!1?ab.removeAttr(a,c):a.setAttribute(c,c),c}},ab.each(ab.expr.match.bool.source.match(/\w+/g),function(a,b){var c=fc[b]||ab.find.attr;fc[b]=function(a,b,d){var e,f;return d||(f=fc[b],fc[b]=e,e=null!=c(a,b,d)?b.toLowerCase():null,fc[b]=f),e}});var gc=/^(?:input|select|textarea|button)$/i;ab.fn.extend({prop:function(a,b){return qb(this,ab.prop,a,b,arguments.length>1)},removeProp:function(a){return this.each(function(){delete this[ab.propFix[a]||a]})}}),ab.extend({propFix:{"for":"htmlFor","class":"className"},prop:function(a,b,c){var d,e,f,g=a.nodeType;if(a&&3!==g&&8!==g&&2!==g)return f=1!==g||!ab.isXMLDoc(a),f&&(b=ab.propFix[b]||b,e=ab.propHooks[b]),void 0!==c?e&&"set"in e&&void 0!==(d=e.set(a,c,b))?d:a[b]=c:e&&"get"in e&&null!==(d=e.get(a,b))?d:a[b]},propHooks:{tabIndex:{get:function(a){return a.hasAttribute("tabindex")||gc.test(a.nodeName)||a.href?a.tabIndex:-1}}}}),Z.optSelected||(ab.propHooks.selected={get:function(a){var b=a.parentNode;return b&&b.parentNode&&b.parentNode.selectedIndex,null}}),ab.each(["tabIndex","readOnly","maxLength","cellSpacing","cellPadding","rowSpan","colSpan","useMap","frameBorder","contentEditable"],function(){ab.propFix[this.toLowerCase()]=this});var hc=/[\t\r\n\f]/g;ab.fn.extend({addClass:function(a){var b,c,d,e,f,g,h="string"==typeof a&&a,i=0,j=this.length;if(ab.isFunction(a))return this.each(function(b){ab(this).addClass(a.call(this,b,this.className))});if(h)for(b=(a||"").match(nb)||[];j>i;i++)if(c=this[i],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(hc," "):" ")){for(f=0;e=b[f++];)d.indexOf(" "+e+" ")<0&&(d+=e+" ");g=ab.trim(d),c.className!==g&&(c.className=g)}return this},removeClass:function(a){var b,c,d,e,f,g,h=0===arguments.length||"string"==typeof a&&a,i=0,j=this.length;if(ab.isFunction(a))return this.each(function(b){ab(this).removeClass(a.call(this,b,this.className))});if(h)for(b=(a||"").match(nb)||[];j>i;i++)if(c=this[i],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(hc," "):"")){for(f=0;e=b[f++];)for(;d.indexOf(" "+e+" ")>=0;)d=d.replace(" "+e+" "," ");g=a?ab.trim(d):"",c.className!==g&&(c.className=g)}return this},toggleClass:function(a,b){var c=typeof a;return"boolean"==typeof b&&"string"===c?b?this.addClass(a):this.removeClass(a):this.each(ab.isFunction(a)?function(c){ab(this).toggleClass(a.call(this,c,this.className,b),b)}:function(){if("string"===c)for(var b,d=0,e=ab(this),f=a.match(nb)||[];b=f[d++];)e.hasClass(b)?e.removeClass(b):e.addClass(b);else(c===zb||"boolean"===c)&&(this.className&&rb.set(this,"__className__",this.className),this.className=this.className||a===!1?"":rb.get(this,"__className__")||"")})},hasClass:function(a){for(var b=" "+a+" ",c=0,d=this.length;d>c;c++)if(1===this[c].nodeType&&(" "+this[c].className+" ").replace(hc," ").indexOf(b)>=0)return!0;return!1}});var ic=/\r/g;ab.fn.extend({val:function(a){var b,c,d,e=this[0];{if(arguments.length)return d=ab.isFunction(a),this.each(function(c){var e;1===this.nodeType&&(e=d?a.call(this,c,ab(this).val()):a,null==e?e="":"number"==typeof e?e+="":ab.isArray(e)&&(e=ab.map(e,function(a){return null==a?"":a+""})),b=ab.valHooks[this.type]||ab.valHooks[this.nodeName.toLowerCase()],b&&"set"in b&&void 0!==b.set(this,e,"value")||(this.value=e))});if(e)return b=ab.valHooks[e.type]||ab.valHooks[e.nodeName.toLowerCase()],b&&"get"in b&&void 0!==(c=b.get(e,"value"))?c:(c=e.value,"string"==typeof c?c.replace(ic,""):null==c?"":c)}}}),ab.extend({valHooks:{select:{get:function(a){for(var b,c,d=a.options,e=a.selectedIndex,f="select-one"===a.type||0>e,g=f?null:[],h=f?e+1:d.length,i=0>e?h:f?e:0;h>i;i++)if(c=d[i],!(!c.selected&&i!==e||(Z.optDisabled?c.disabled:null!==c.getAttribute("disabled"))||c.parentNode.disabled&&ab.nodeName(c.parentNode,"optgroup"))){if(b=ab(c).val(),f)return b;g.push(b)}return g},set:function(a,b){for(var c,d,e=a.options,f=ab.makeArray(b),g=e.length;g--;)d=e[g],(d.selected=ab.inArray(ab(d).val(),f)>=0)&&(c=!0);return c||(a.selectedIndex=-1),f}}}}),ab.each(["radio","checkbox"],function(){ab.valHooks[this]={set:function(a,b){return ab.isArray(b)?a.checked=ab.inArray(ab(a).val(),b)>=0:void 0}},Z.checkOn||(ab.valHooks[this].get=function(a){return null===a.getAttribute("value")?"on":a.value})}),ab.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "),function(a,b){ab.fn[b]=function(a,c){return arguments.length>0?this.on(b,null,a,c):this.trigger(b)}}),ab.fn.extend({hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return 1===arguments.length?this.off(a,"**"):this.off(b,a||"**",c)}});var jc=ab.now(),kc=/\?/;ab.parseJSON=function(a){return JSON.parse(a+"")},ab.parseXML=function(a){var b,c;if(!a||"string"!=typeof a)return null;try{c=new DOMParser,b=c.parseFromString(a,"text/xml")}catch(d){b=void 0}return(!b||b.getElementsByTagName("parsererror").length)&&ab.error("Invalid XML: "+a),b};var lc,mc,nc=/#.*$/,oc=/([?&])_=[^&]*/,pc=/^(.*?):[ \t]*([^\r\n]*)$/gm,qc=/^(?:about|app|app-storage|.+-extension|file|res|widget):$/,rc=/^(?:GET|HEAD)$/,sc=/^\/\//,tc=/^([\w.+-]+:)(?:\/\/(?:[^\/?#]*@|)([^\/?#:]*)(?::(\d+)|)|)/,uc={},vc={},wc="*/".concat("*");try{mc=location.href}catch(xc){mc=$.createElement("a"),mc.href="",mc=mc.href}lc=tc.exec(mc.toLowerCase())||[],ab.extend({active:0,lastModified:{},etag:{},ajaxSettings:{url:mc,type:"GET",isLocal:qc.test(lc[1]),global:!0,processData:!0,async:!0,contentType:"application/x-www-form-urlencoded; charset=UTF-8",accepts:{"*":wc,text:"text/plain",html:"text/html",xml:"application/xml, text/xml",json:"application/json, text/javascript"},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText",json:"responseJSON"},converters:{"* text":String,"text html":!0,"text json":ab.parseJSON,"text xml":ab.parseXML},flatOptions:{url:!0,context:!0}},ajaxSetup:function(a,b){return b?L(L(a,ab.ajaxSettings),b):L(ab.ajaxSettings,a)},ajaxPrefilter:J(uc),ajaxTransport:J(vc),ajax:function(a,b){function c(a,b,c,g){var i,k,r,s,u,w=b;2!==t&&(t=2,h&&clearTimeout(h),d=void 0,f=g||"",v.readyState=a>0?4:0,i=a>=200&&300>a||304===a,c&&(s=M(l,v,c)),s=N(l,s,v,i),i?(l.ifModified&&(u=v.getResponseHeader("Last-Modified"),u&&(ab.lastModified[e]=u),u=v.getResponseHeader("etag"),u&&(ab.etag[e]=u)),204===a||"HEAD"===l.type?w="nocontent":304===a?w="notmodified":(w=s.state,k=s.data,r=s.error,i=!r)):(r=w,(a||!w)&&(w="error",0>a&&(a=0))),v.status=a,v.statusText=(b||w)+"",i?o.resolveWith(m,[k,w,v]):o.rejectWith(m,[v,w,r]),v.statusCode(q),q=void 0,j&&n.trigger(i?"ajaxSuccess":"ajaxError",[v,l,i?k:r]),p.fireWith(m,[v,w]),j&&(n.trigger("ajaxComplete",[v,l]),--ab.active||ab.event.trigger("ajaxStop")))}"object"==typeof a&&(b=a,a=void 0),b=b||{};var d,e,f,g,h,i,j,k,l=ab.ajaxSetup({},b),m=l.context||l,n=l.context&&(m.nodeType||m.jquery)?ab(m):ab.event,o=ab.Deferred(),p=ab.Callbacks("once memory"),q=l.statusCode||{},r={},s={},t=0,u="canceled",v={readyState:0,getResponseHeader:function(a){var b;if(2===t){if(!g)for(g={};b=pc.exec(f);)g[b[1].toLowerCase()]=b[2];b=g[a.toLowerCase()]}return null==b?null:b},getAllResponseHeaders:function(){return 2===t?f:null},setRequestHeader:function(a,b){var c=a.toLowerCase();return t||(a=s[c]=s[c]||a,r[a]=b),this},overrideMimeType:function(a){return t||(l.mimeType=a),this},statusCode:function(a){var b;if(a)if(2>t)for(b in a)q[b]=[q[b],a[b]];else v.always(a[v.status]);return this},abort:function(a){var b=a||u;return d&&d.abort(b),c(0,b),this}};if(o.promise(v).complete=p.add,v.success=v.done,v.error=v.fail,l.url=((a||l.url||mc)+"").replace(nc,"").replace(sc,lc[1]+"//"),l.type=b.method||b.type||l.method||l.type,l.dataTypes=ab.trim(l.dataType||"*").toLowerCase().match(nb)||[""],null==l.crossDomain&&(i=tc.exec(l.url.toLowerCase()),l.crossDomain=!(!i||i[1]===lc[1]&&i[2]===lc[2]&&(i[3]||("http:"===i[1]?"80":"443"))===(lc[3]||("http:"===lc[1]?"80":"443")))),l.data&&l.processData&&"string"!=typeof l.data&&(l.data=ab.param(l.data,l.traditional)),K(uc,l,b,v),2===t)return v;j=l.global,j&&0===ab.active++&&ab.event.trigger("ajaxStart"),l.type=l.type.toUpperCase(),l.hasContent=!rc.test(l.type),e=l.url,l.hasContent||(l.data&&(e=l.url+=(kc.test(e)?"&":"?")+l.data,delete l.data),l.cache===!1&&(l.url=oc.test(e)?e.replace(oc,"$1_="+jc++):e+(kc.test(e)?"&":"?")+"_="+jc++)),l.ifModified&&(ab.lastModified[e]&&v.setRequestHeader("If-Modified-Since",ab.lastModified[e]),ab.etag[e]&&v.setRequestHeader("If-None-Match",ab.etag[e])),(l.data&&l.hasContent&&l.contentType!==!1||b.contentType)&&v.setRequestHeader("Content-Type",l.contentType),v.setRequestHeader("Accept",l.dataTypes[0]&&l.accepts[l.dataTypes[0]]?l.accepts[l.dataTypes[0]]+("*"!==l.dataTypes[0]?", "+wc+"; q=0.01":""):l.accepts["*"]);for(k in l.headers)v.setRequestHeader(k,l.headers[k]);if(l.beforeSend&&(l.beforeSend.call(m,v,l)===!1||2===t))return v.abort();u="abort";for(k in{success:1,error:1,complete:1})v[k](l[k]);if(d=K(vc,l,b,v)){v.readyState=1,j&&n.trigger("ajaxSend",[v,l]),l.async&&l.timeout>0&&(h=setTimeout(function(){v.abort("timeout")},l.timeout));try{t=1,d.send(r,c)}catch(w){if(!(2>t))throw w;c(-1,w)}}else c(-1,"No Transport");return v},getJSON:function(a,b,c){return ab.get(a,b,c,"json")},getScript:function(a,b){return ab.get(a,void 0,b,"script")}}),ab.each(["get","post"],function(a,b){ab[b]=function(a,c,d,e){return ab.isFunction(c)&&(e=e||d,d=c,c=void 0),ab.ajax({url:a,type:b,dataType:e,data:c,success:d})}}),ab.each(["ajaxStart","ajaxStop","ajaxComplete","ajaxError","ajaxSuccess","ajaxSend"],function(a,b){ab.fn[b]=function(a){return this.on(b,a)}}),ab._evalUrl=function(a){return ab.ajax({url:a,type:"GET",dataType:"script",async:!1,global:!1,"throws":!0})},ab.fn.extend({wrapAll:function(a){var b;return ab.isFunction(a)?this.each(function(b){ab(this).wrapAll(a.call(this,b))}):(this[0]&&(b=ab(a,this[0].ownerDocument).eq(0).clone(!0),this[0].parentNode&&b.insertBefore(this[0]),b.map(function(){for(var a=this;a.firstElementChild;)a=a.firstElementChild;return a}).append(this)),this)},wrapInner:function(a){return this.each(ab.isFunction(a)?function(b){ab(this).wrapInner(a.call(this,b))}:function(){var b=ab(this),c=b.contents();c.length?c.wrapAll(a):b.append(a)})},wrap:function(a){var b=ab.isFunction(a);return this.each(function(c){ab(this).wrapAll(b?a.call(this,c):a)})},unwrap:function(){return this.parent().each(function(){ab.nodeName(this,"body")||ab(this).replaceWith(this.childNodes)}).end()}}),ab.expr.filters.hidden=function(a){return a.offsetWidth<=0&&a.offsetHeight<=0},ab.expr.filters.visible=function(a){return!ab.expr.filters.hidden(a)};var yc=/%20/g,zc=/\[\]$/,Ac=/\r?\n/g,Bc=/^(?:submit|button|image|reset|file)$/i,Cc=/^(?:input|select|textarea|keygen)/i;ab.param=function(a,b){var c,d=[],e=function(a,b){b=ab.isFunction(b)?b():null==b?"":b,d[d.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};if(void 0===b&&(b=ab.ajaxSettings&&ab.ajaxSettings.traditional),ab.isArray(a)||a.jquery&&!ab.isPlainObject(a))ab.each(a,function(){e(this.name,this.value)});else for(c in a)O(c,a[c],b,e);return d.join("&").replace(yc,"+")},ab.fn.extend({serialize:function(){return ab.param(this.serializeArray())},serializeArray:function(){return this.map(function(){var a=ab.prop(this,"elements");return a?ab.makeArray(a):this}).filter(function(){var a=this.type;return this.name&&!ab(this).is(":disabled")&&Cc.test(this.nodeName)&&!Bc.test(a)&&(this.checked||!yb.test(a))}).map(function(a,b){var c=ab(this).val();return null==c?null:ab.isArray(c)?ab.map(c,function(a){return{name:b.name,value:a.replace(Ac,"\r\n")}}):{name:b.name,value:c.replace(Ac,"\r\n")}}).get()}}),ab.ajaxSettings.xhr=function(){try{return new XMLHttpRequest}catch(a){}};var Dc=0,Ec={},Fc={0:200,1223:204},Gc=ab.ajaxSettings.xhr();a.ActiveXObject&&ab(a).on("unload",function(){for(var a in Ec)Ec[a]()}),Z.cors=!!Gc&&"withCredentials"in Gc,Z.ajax=Gc=!!Gc,ab.ajaxTransport(function(a){var b;return Z.cors||Gc&&!a.crossDomain?{send:function(c,d){var e,f=a.xhr(),g=++Dc;if(f.open(a.type,a.url,a.async,a.username,a.password),a.xhrFields)for(e in a.xhrFields)f[e]=a.xhrFields[e];a.mimeType&&f.overrideMimeType&&f.overrideMimeType(a.mimeType),a.crossDomain||c["X-Requested-With"]||(c["X-Requested-With"]="XMLHttpRequest");for(e in c)f.setRequestHeader(e,c[e]);b=function(a){return function(){b&&(delete Ec[g],b=f.onload=f.onerror=null,"abort"===a?f.abort():"error"===a?d(f.status,f.statusText):d(Fc[f.status]||f.status,f.statusText,"string"==typeof f.responseText?{text:f.responseText}:void 0,f.getAllResponseHeaders()))}},f.onload=b(),f.onerror=b("error"),b=Ec[g]=b("abort"),f.send(a.hasContent&&a.data||null)},abort:function(){b&&b()}}:void 0}),ab.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/(?:java|ecma)script/},converters:{"text script":function(a){return ab.globalEval(a),a}}}),ab.ajaxPrefilter("script",function(a){void 0===a.cache&&(a.cache=!1),a.crossDomain&&(a.type="GET")}),ab.ajaxTransport("script",function(a){if(a.crossDomain){var b,c;return{send:function(d,e){b=ab("<script>").prop({async:!0,charset:a.scriptCharset,src:a.url}).on("load error",c=function(a){b.remove(),c=null,a&&e("error"===a.type?404:200,a.type)}),$.head.appendChild(b[0])},abort:function(){c&&c()}}}});var Hc=[],Ic=/(=)\?(?=&|$)|\?\?/;ab.ajaxSetup({jsonp:"callback",jsonpCallback:function(){var a=Hc.pop()||ab.expando+"_"+jc++;return this[a]=!0,a}}),ab.ajaxPrefilter("json jsonp",function(b,c,d){var e,f,g,h=b.jsonp!==!1&&(Ic.test(b.url)?"url":"string"==typeof b.data&&!(b.contentType||"").indexOf("application/x-www-form-urlencoded")&&Ic.test(b.data)&&"data");return h||"jsonp"===b.dataTypes[0]?(e=b.jsonpCallback=ab.isFunction(b.jsonpCallback)?b.jsonpCallback():b.jsonpCallback,h?b[h]=b[h].replace(Ic,"$1"+e):b.jsonp!==!1&&(b.url+=(kc.test(b.url)?"&":"?")+b.jsonp+"="+e),b.converters["script json"]=function(){return g||ab.error(e+" was not called"),g[0]},b.dataTypes[0]="json",f=a[e],a[e]=function(){g=arguments},d.always(function(){a[e]=f,b[e]&&(b.jsonpCallback=c.jsonpCallback,Hc.push(e)),g&&ab.isFunction(f)&&f(g[0]),g=f=void 0}),"script"):void 0}),ab.parseHTML=function(a,b,c){if(!a||"string"!=typeof a)return null;"boolean"==typeof b&&(c=b,b=!1),b=b||$;var d=gb.exec(a),e=!c&&[];return d?[b.createElement(d[1])]:(d=ab.buildFragment([a],b,e),e&&e.length&&ab(e).remove(),ab.merge([],d.childNodes))};var Jc=ab.fn.load;ab.fn.load=function(a,b,c){if("string"!=typeof a&&Jc)return Jc.apply(this,arguments);var d,e,f,g=this,h=a.indexOf(" ");return h>=0&&(d=a.slice(h),a=a.slice(0,h)),ab.isFunction(b)?(c=b,b=void 0):b&&"object"==typeof b&&(e="POST"),g.length>0&&ab.ajax({url:a,type:e,dataType:"html",data:b}).done(function(a){f=arguments,g.html(d?ab("<div>").append(ab.parseHTML(a)).find(d):a)}).complete(c&&function(a,b){g.each(c,f||[a.responseText,b,a])}),this},ab.expr.filters.animated=function(a){return ab.grep(ab.timers,function(b){return a===b.elem}).length};var Kc=a.document.documentElement;ab.offset={setOffset:function(a,b,c){var d,e,f,g,h,i,j,k=ab.css(a,"position"),l=ab(a),m={};"static"===k&&(a.style.position="relative"),h=l.offset(),f=ab.css(a,"top"),i=ab.css(a,"left"),j=("absolute"===k||"fixed"===k)&&(f+i).indexOf("auto")>-1,j?(d=l.position(),g=d.top,e=d.left):(g=parseFloat(f)||0,e=parseFloat(i)||0),ab.isFunction(b)&&(b=b.call(a,c,h)),null!=b.top&&(m.top=b.top-h.top+g),null!=b.left&&(m.left=b.left-h.left+e),"using"in b?b.using.call(a,m):l.css(m)}},ab.fn.extend({offset:function(a){if(arguments.length)return void 0===a?this:this.each(function(b){ab.offset.setOffset(this,a,b)});var b,c,d=this[0],e={top:0,left:0},f=d&&d.ownerDocument;if(f)return b=f.documentElement,ab.contains(b,d)?(typeof d.getBoundingClientRect!==zb&&(e=d.getBoundingClientRect()),c=P(f),{top:e.top+c.pageYOffset-b.clientTop,left:e.left+c.pageXOffset-b.clientLeft}):e},position:function(){if(this[0]){var a,b,c=this[0],d={top:0,left:0};return"fixed"===ab.css(c,"position")?b=c.getBoundingClientRect():(a=this.offsetParent(),b=this.offset(),ab.nodeName(a[0],"html")||(d=a.offset()),d.top+=ab.css(a[0],"borderTopWidth",!0),d.left+=ab.css(a[0],"borderLeftWidth",!0)),{top:b.top-d.top-ab.css(c,"marginTop",!0),left:b.left-d.left-ab.css(c,"marginLeft",!0)}}},offsetParent:function(){return this.map(function(){for(var a=this.offsetParent||Kc;a&&!ab.nodeName(a,"html")&&"static"===ab.css(a,"position");)a=a.offsetParent;return a||Kc})}}),ab.each({scrollLeft:"pageXOffset",scrollTop:"pageYOffset"},function(b,c){var d="pageYOffset"===c;ab.fn[b]=function(e){return qb(this,function(b,e,f){var g=P(b);return void 0===f?g?g[c]:b[e]:void(g?g.scrollTo(d?a.pageXOffset:f,d?f:a.pageYOffset):b[e]=f)},b,e,arguments.length,null)}}),ab.each(["top","left"],function(a,b){ab.cssHooks[b]=w(Z.pixelPosition,function(a,c){return c?(c=v(a,b),Qb.test(c)?ab(a).position()[b]+"px":c):void 0})}),ab.each({Height:"height",Width:"width"},function(a,b){ab.each({padding:"inner"+a,content:b,"":"outer"+a},function(c,d){ab.fn[d]=function(d,e){var f=arguments.length&&(c||"boolean"!=typeof d),g=c||(d===!0||e===!0?"margin":"border");return qb(this,function(b,c,d){var e;return ab.isWindow(b)?b.document.documentElement["client"+a]:9===b.nodeType?(e=b.documentElement,Math.max(b.body["scroll"+a],e["scroll"+a],b.body["offset"+a],e["offset"+a],e["client"+a])):void 0===d?ab.css(b,c,g):ab.style(b,c,d,g)},b,f?d:void 0,f,null)}})}),ab.fn.size=function(){return this.length},ab.fn.andSelf=ab.fn.addBack,"function"==typeof define&&define.amd&&define("jquery",[],function(){return ab});var Lc=a.jQuery,Mc=a.$;return ab.noConflict=function(b){return a.$===ab&&(a.$=Mc),b&&a.jQuery===ab&&(a.jQuery=Lc),ab},typeof b===zb&&(a.jQuery=a.$=ab),ab}),function(a){"function"==typeof define&&define.amd?define(["jquery"],a):"object"==typeof exports?module.exports=a:a(jQuery)}(function(a){function b(b){var g=b||window.event,h=i.call(arguments,1),j=0,k=0,l=0,m=0;if(b=a.event.fix(g),b.type="mousewheel","detail"in g&&(l=-1*g.detail),"wheelDelta"in g&&(l=g.wheelDelta),"wheelDeltaY"in g&&(l=g.wheelDeltaY),"wheelDeltaX"in g&&(k=-1*g.wheelDeltaX),"axis"in g&&g.axis===g.HORIZONTAL_AXIS&&(k=-1*l,l=0),j=0===l?k:l,"deltaY"in g&&(l=-1*g.deltaY,j=l),"deltaX"in g&&(k=g.deltaX,0===l&&(j=-1*k)),0!==l||0!==k){if(1===g.deltaMode){var n=a.data(this,"mousewheel-line-height");j*=n,l*=n,k*=n}else if(2===g.deltaMode){var o=a.data(this,"mousewheel-page-height");j*=o,l*=o,k*=o}return m=Math.max(Math.abs(l),Math.abs(k)),(!f||f>m)&&(f=m,d(g,m)&&(f/=40)),d(g,m)&&(j/=40,k/=40,l/=40),j=Math[j>=1?"floor":"ceil"](j/f),k=Math[k>=1?"floor":"ceil"](k/f),l=Math[l>=1?"floor":"ceil"](l/f),b.deltaX=k,b.deltaY=l,b.deltaFactor=f,b.deltaMode=0,h.unshift(b,j,k,l),e&&clearTimeout(e),e=setTimeout(c,200),(a.event.dispatch||a.event.handle).apply(this,h)}}function c(){f=null}function d(a,b){return k.settings.adjustOldDeltas&&"mousewheel"===a.type&&b%120===0}var e,f,g=["wheel","mousewheel","DOMMouseScroll","MozMousePixelScroll"],h="onwheel"in document||document.documentMode>=9?["wheel"]:["mousewheel","DomMouseScroll","MozMousePixelScroll"],i=Array.prototype.slice;if(a.event.fixHooks)for(var j=g.length;j;)a.event.fixHooks[g[--j]]=a.event.mouseHooks;var k=a.event.special.mousewheel={version:"3.1.9",setup:function(){if(this.addEventListener)for(var c=h.length;c;)this.addEventListener(h[--c],b,!1);else this.onmousewheel=b;a.data(this,"mousewheel-line-height",k.getLineHeight(this)),a.data(this,"mousewheel-page-height",k.getPageHeight(this))},teardown:function(){if(this.removeEventListener)for(var a=h.length;a;)this.removeEventListener(h[--a],b,!1);else this.onmousewheel=null},getLineHeight:function(b){return parseInt(a(b)["offsetParent"in a.fn?"offsetParent":"parent"]().css("fontSize"),10)},getPageHeight:function(b){return a(b).height()},settings:{adjustOldDeltas:!0}};a.fn.extend({mousewheel:function(a){return a?this.bind("mousewheel",a):this.trigger("mousewheel")},unmousewheel:function(a){return this.unbind("mousewheel",a)}})}),function(a,b){function c(a,b){var c;if("string"==typeof a&&"string"==typeof b)return localStorage[a]=b,!0;if("object"==typeof a&&"undefined"==typeof b){for(c in a)a.hasOwnProperty(c)&&(localStorage[c]=a[c]);return!0}return!1}function d(a,b){var c,d,e;if(c=new Date,c.setTime(c.getTime()+31536e6),d="; expires="+c.toGMTString(),"string"==typeof a&&"string"==typeof b)return document.cookie=a+"="+b+d+"; path=/",!0;if("object"==typeof a&&"undefined"==typeof b){for(e in a)a.hasOwnProperty(e)&&(document.cookie=e+"="+a[e]+d+"; path=/");return!0}return!1}function e(a){return localStorage[a]}function f(a){var b,c,d,e;for(b=a+"=",c=document.cookie.split(";"),d=0;d<c.length;d++){for(e=c[d];" "===e.charAt(0);)e=e.substring(1,e.length);if(0===e.indexOf(b))return e.substring(b.length,e.length)}return null}function g(a){return delete localStorage[a]}function h(a){return d(a,"",-1)}function i(a,b){var c=[],d=a.length;if(b>d)return[a];for(var e=0;d>e;e+=b)c.push(a.substring(e,e+b));return c}function j(b){var c=b?[b]:[],d=0;a.extend(this,{get:function(){return c},rotate:function(){return 1===c.length?c[0]:(d===c.length-1?d=0:++d,c[d])},length:function(){return c.length},set:function(a){for(var b=c.length;b--;)if(c[b]===a)return void(d=b);this.append(a)},front:function(){return c[d]},append:function(a){c.push(a)}})}function k(b){var c=b?[b]:[];a.extend(this,{size:function(){return c.length},pop:function(){if(0===c.length)return null;var a=c[c.length-1];return c=c.slice(0,c.length-1),a},push:function(a){return c=c.concat([a]),a},top:function(){return c.length>0?c[c.length-1]:null}})}function l(b,c){var d=!0;"string"==typeof b&&""!==b&&(b+="_");var e=a.Storage.get(b+"commands");e=e?new Function("return "+e+";")():[];var f=e.length-1;a.extend(this,{append:function(g){d&&e[e.length-1]!==g&&(e.push(g),c&&e.length>c&&(e=e.slice(-c)),f=e.length-1,a.Storage.set(b+"commands",a.json_stringify(e)))},data:function(){return e},reset:function(){f=e.length-1},last:function(){return e[length-1]},end:function(){return f===e.length-1},position:function(){return f},current:function(){return e[f]},next:function(){return f<e.length-1&&++f,-1!==f?e[f]:void 0},previous:function(){var a=f;return f>0&&--f,-1!==a?e[f]:void 0},clear:function(){e=[],this.purge()},enabled:function(){return d},enable:function(){d=!0},purge:function(){a.Storage.remove(b+"commands")},disable:function(){d=!1}})}function m(b){return a("<div>"+a.terminal.strip(b)+"</div>").text().length}function n(a){var b=/([\^\$\[\]\(\)\+\*\.\|])/g;return a.replace(b,"\\$1")}function o(a,b){var c=a.split(/(\s+)/);return{name:c[0],args:b(c.slice(2).join("")),rest:a.replace(new RegExp("^"+n(c[0])+" "),"")}}function p(b){var c=a(window).scrollTop(),d=c+a(window).height(),e=a(b).offset().top,f=e+a(b).height();return f>=c&&d>=e}function q(b){var c=a('<div class="terminal"><span>&nbsp;</span></div>').appendTo("body"),d=c.find("span").width();c.remove();var e=Math.floor(b.width()/d);if(r(b)){var f=20,g=b.innerWidth()-b.width();e-=Math.ceil((f-g/2)/(d-1))}return e}function r(a){return a.get(0).scrollHeight>a.innerHeight()}a.omap=function(b,c){var d={};return a.each(b,function(a,e){d[a]=c.call(b,a,e)}),d};var s="undefined"!=typeof window.localStorage;a.extend({Storage:{set:s?c:d,get:s?e:f,remove:s?g:h}}),jQuery.fn.extend({everyTime:function(a,b,c,d,e){return this.each(function(){jQuery.timer.add(this,a,b,c,d,e)})},oneTime:function(a,b,c){return this.each(function(){jQuery.timer.add(this,a,b,c,1)})},stopTime:function(a,b){return this.each(function(){jQuery.timer.remove(this,a,b)})}}),jQuery.extend({timer:{guid:1,global:{},regex:/^([0-9]+)\s*(.*s)?$/,powers:{ms:1,cs:10,ds:100,s:1e3,das:1e4,hs:1e5,ks:1e6},timeParse:function(a){if(a===b||null===a)return null;var c=this.regex.exec(jQuery.trim(a.toString()));if(c[2]){var d=parseInt(c[1],10),e=this.powers[c[2]]||1;return d*e}return a},add:function(a,b,c,d,e,f){var g=0;if(jQuery.isFunction(c)&&(e||(e=d),d=c,c=b),b=jQuery.timer.timeParse(b),!("number"!=typeof b||isNaN(b)||0>=b)){e&&e.constructor!==Number&&(f=!!e,e=0),e=e||0,f=f||!1,a.$timers||(a.$timers={}),a.$timers[c]||(a.$timers[c]={}),d.$timerID=d.$timerID||this.guid++;var h=function(){f&&h.inProgress||(h.inProgress=!0,(++g>e&&0!==e||d.call(a,g)===!1)&&jQuery.timer.remove(a,c,d),h.inProgress=!1)};h.$timerID=d.$timerID,a.$timers[c][d.$timerID]||(a.$timers[c][d.$timerID]=window.setInterval(h,b)),this.global[c]||(this.global[c]=[]),this.global[c].push(a)}},remove:function(a,b,c){var d,e=a.$timers;if(e){if(b){if(e[b]){if(c)c.$timerID&&(window.clearInterval(e[b][c.$timerID]),delete e[b][c.$timerID]);else for(var f in e[b])e[b].hasOwnProperty(f)&&(window.clearInterval(e[b][f]),delete e[b][f]);for(d in e[b])if(e[b].hasOwnProperty(d))break;d||(d=null,delete e[b])}}else for(var g in e)e.hasOwnProperty(g)&&this.remove(a,g,c);for(d in e)if(e.hasOwnProperty(d))break;d||(a.$timers=null)}}}}),(jQuery.browser&&jQuery.browser.msie||/(msie) ([\w.]+)/.exec(navigator.userAgent.toLowerCase()))&&jQuery(window).one("unload",function(){var a=jQuery.timer.global;for(var b in a)if(a.hasOwnProperty(b))for(var c=a[b],d=c.length;--d;)jQuery.timer.remove(c[d],b)}),function(a){if(String.prototype.split.toString().match(/\[native/)){var b,c=String.prototype.split,d=/()??/.exec("")[1]===a;return b=function(b,e,f){if("[object RegExp]"!==Object.prototype.toString.call(e))return c.call(b,e,f);var g,h,i,j,k=[],l=(e.ignoreCase?"i":"")+(e.multiline?"m":"")+(e.extended?"x":"")+(e.sticky?"y":""),m=0;for(e=new RegExp(e.source,l+"g"),b+="",d||(g=new RegExp("^"+e.source+"$(?!\\s)",l)),f=f===a?-1>>>0:f>>>0;(h=e.exec(b))&&(i=h.index+h[0].length,!(i>m&&(k.push(b.slice(m,h.index)),!d&&h.length>1&&h[0].replace(g,function(){for(var b=1;b<arguments.length-2;b++)arguments[b]===a&&(h[b]=a)}),h.length>1&&h.index<b.length&&Array.prototype.push.apply(k,h.slice(1)),j=h[0].length,m=i,k.length>=f)));)e.lastIndex===h.index&&e.lastIndex++;return m===b.length?(j||!e.test(""))&&k.push(""):k.push(b.slice(m)),k.length>f?k.slice(0,f):k},String.prototype.split=function(a,c){return b(this,a,c)},b}}(),a.json_stringify=function(c,d){var e,f="";d=d===b?1:d;var g=typeof c;switch(g){case"function":f+=c;break;case"boolean":f+=c?"true":"false";break;case"object":if(null===c)f+="null";else if(c instanceof Array){f+="[";var h=c.length;for(e=0;h-1>e;++e)f+=a.json_stringify(c[e],d+1);f+=a.json_stringify(c[h-1],d+1)+"]"}else{f+="{";for(var i in c)c.hasOwnProperty(i)&&(f+='"'+i+'":'+a.json_stringify(c[i],d+1));f+="}"}break;case"string":var j=c,k={"\\\\":"\\\\",'"':'\\"',"/":"\\/","\\n":"\\n","\\r":"\\r","\\t":"\\t"};for(e in k)k.hasOwnProperty(e)&&(j=j.replace(new RegExp(e,"g"),k[e]));f+='"'+j+'"';break;case"number":f+=String(c)}return f+=d>1?",":"",1===d&&(f=f.replace(/,([\]}])/g,"$1")),f.replace(/([\[{]),/g,"$1")},a.fn.cmd=function(c){function d(){G.toggleClass("inverted")}function e(){u="(reverse-i-search)`"+z+"': ",I()}function f(){u=t,y=!1,A=null,z=""}function g(a){var b,c,d=w.data(),f=d.length;if(a&&A>0&&(f-=A),z.length>0)for(var g=z.length;g>0;g--){c=z.substring(0,g).replace(/([.*+{}\[\]?])/g,"\\$1"),b=new RegExp(c);for(var h=f;h--;)if(b.test(d[h]))return A=d.length-h,D=0,o.set(d[h],!0),H(),void(z.length!==g&&(z=z.substring(0,g),e()))}z=""}function h(){var a=o.width(),b=G.innerWidth();r=Math.floor(a/b)}function j(a){var b=a.substring(0,r-s),c=a.substring(r-s);return[b].concat(i(c,r))}function k(){q.focus(),o.oneTime(1,function(){o.insert(q.val()),q.blur().val("")})}function n(a){var d,h,i;if("function"==typeof c.keydown&&(d=c.keydown(a),d!==b))return d;if(E){if(38===a.which||80===a.which&&a.ctrlKey||(J=!0),!y||35!==a.which&&36!==a.which&&37!==a.which&&38!==a.which&&39!==a.which&&40!==a.which&&13!==a.which&&27!==a.which){if(a.altKey)return 68===a.which?(o.set(C.slice(0,D)+C.slice(D).replace(/[^ ]+ |[^ ]+$/,""),!0),!1):!0;if(13===a.keyCode){w&&C&&(c.historyFilter&&c.historyFilter(C)||!c.historyFilter)&&w.append(C);var j=C;w.reset(),o.set(""),c.commands&&c.commands(j),"function"==typeof u&&I()}else if(8===a.which)y?(z=z.slice(0,-1),e()):""!==C&&D>0&&(C=C.slice(0,D-1)+C.slice(D,C.length),--D,H());else if(9!==a.which||a.ctrlKey||a.altKey){if(46===a.which)return""!==C&&D<C.length&&(C=C.slice(0,D)+C.slice(D+1,C.length),H()),!0;if(w&&38===a.which||80===a.which&&a.ctrlKey)J?(x=C,o.set(w.current())):o.set(w.previous()),J=!1;else if(w&&40===a.which||78===a.which&&a.ctrlKey)o.set(w.end()?x:w.next());else if(37===a.which||66===a.which&&a.ctrlKey)if(a.ctrlKey&&66!==a.which){i=D-1,h=0," "===C[i]&&--i;for(var l=i;l>0;--l){if(" "===C[l]&&" "!==C[l+1]){h=l+1;break}if("\n"===C[l]&&"\n"!==C[l+1]){h=l;break}}o.position(h)}else D>0&&(--D,H());else if(82===a.which&&a.ctrlKey)y?g(!0):(t=u,e(),x=C,C="",H(),y=!0);else if(71==a.which&&a.ctrlKey)y&&(u=t,I(),C=x,H(),y=!1,z="");else if(39===a.which||70===a.which&&a.ctrlKey)if(a.ctrlKey&&70!==a.which){" "===C[D]&&++D;var m=C.slice(D).match(/\S[\n\s]{2,}|[\n\s]+\S?/);!m||m[0].match(/^\s+$/)?D=C.length:" "!==m[0][0]?D+=m.index+1:(D+=m.index+m[0].length-1," "!==m[0][m[0].length-1]&&--D),H()}else D<C.length&&(++D,H());else{if(123===a.which)return!0;if(36===a.which)o.position(0);else if(35===a.which)o.position(C.length);else{if(a.shiftKey&&45==a.which)return k(),!0;if(!a.ctrlKey&&!a.metaKey)return!0;if(192===a.which)return!0;if(a.metaKey){if(82===a.which)return!0;if(76===a.which)return!0}if(a.shiftKey){if(84===a.which)return!0}else{if(87===a.which){if(""!==C){var p=C.slice(0,D),q=C.slice(D+1),r=p.match(/([^ ]+ *$)/);D=p.length-r[0].length,C=p.slice(0,D)+q,H()}return!1}if(72===a.which)return""!==C&&D>0&&(C=C.slice(0,--D),D<C.length-1&&(C+=C.slice(D)),H()),!1;if(65===a.which)o.position(0);else if(69===a.which)o.position(C.length);else{if(88===a.which||67===a.which||84===a.which)return!0;if(86===a.which)return k(),!0;if(75===a.which)0===D?o.set(""):D!==C.length&&o.set(C.slice(0,D));

                                       else if(85===a.which)o.set(C.slice(D,C.length)),o.position(0);else if(17===a.which)return!1}}}}}else o.insert("	")}else f(),I(),27===a.which&&(C=""),H(),n.call(this,a);return!1}}var o=this,p=o.data("cmd");if(p)return p;o.addClass("cmd"),o.append('<span class="prompt"></span><span></span><span class="cursor">&nbsp;</span><span></span>');var q=a("<textarea/>").addClass("clipboard").appendTo(o);c.width&&o.width(c.width);var r,s,t,u,v,w,x,y=!1,z="",A=null,B=c.mask||!1,C="",D=0,E=c.enabled,F=c.historySize||60,G=o.find(".cursor"),H=function(b){function c(b,c){var d=b.length;if(c===d)g.html(a.terminal.encode(b,!0)),G.html("&nbsp;"),h.html("");else if(0===c)g.html(""),G.html(a.terminal.encode(b.slice(0,1),!0)),h.html(a.terminal.encode(b.slice(1),!0));else{var e=a.terminal.encode(b.slice(0,c),!0);g.html(e);var f=b.slice(c,c+1);G.html(" "===f?"&nbsp;":a.terminal.encode(f,!0)),h.html(c===b.length-1?"":a.terminal.encode(b.slice(c+1),!0))}}function d(b){return"<div>"+a.terminal.encode(b,!0)+"</div>"}function e(b){var c=h;a.each(b,function(b,e){c=a(d(e)).insertAfter(c).addClass("clear")})}function f(b){a.each(b,function(a,b){g.before(d(b))})}var g=G.prev(),h=G.next();return function(){var k,l,m=B?C.replace(/./g,"*"):C;if(b.find("div").remove(),g.html(""),m.length>r-s-1||m.match(/\n/)){var n,o=m.match(/\t/g),p=o?3*o.length:0;if(o&&(m=m.replace(/\t/g,"\x00\x00\x00\x00")),m.match(/\n/)){var q=m.split("\n");for(l=r-s-1,k=0;k<q.length-1;++k)q[k]+=" ";for(q[0].length>l?(n=[q[0].substring(0,l)],n=n.concat(i(q[0].substring(l),r))):n=[q[0]],k=1;k<q.length;++k)q[k].length>r?n=n.concat(i(q[k],r)):n.push(q[k])}else n=j(m);if(o&&(n=a.map(n,function(a){return a.replace(/\x00\x00\x00\x00/g,"	")})),l=n[0].length,0===l&&1===n.length);else if(l>D)c(n[0],D),e(n.slice(1));else if(D===l)g.before(d(n[0])),c(n[1],0),e(n.slice(2));else{var t=n.length;if(l>D)c(n[0],D),e(n.slice(1));else if(D===l)g.before(d(n[0])),c(n[1],0),e(n.slice(2));else{var u=n.slice(-1)[0],v=m.length-D,w=u.length,x=0;if(w>=v)f(n.slice(0,-1)),x=w===v?0:w-v,c(u,x+p);else if(3===t)g.before("<div>"+a.terminal.encode(n[0],!0)+"</div>"),c(n[1],D-l-1),h.after('<div class="clear">'+a.terminal.encode(n[2],!0)+"</div>");else{var y,z;for(x=D,k=0;k<n.length;++k){var A=n[k].length;if(!(x>A))break;x-=A}z=n[k],y=k,x===z.length&&(x=0,z=n[++y]),c(z,x),f(n.slice(0,y)),e(n.slice(y+1))}}}}else""===m?(g.html(""),G.html("&nbsp;"),h.html("")):c(m,D)}}(o),I=function(){function b(b){s=m(b),c.html(a.terminal.format(a.terminal.encode(b)))}var c=o.find(".prompt");return function(){switch(typeof u){case"string":b(u);break;case"function":u(b)}}}(),J=!0,K=[];a.extend(o,{name:function(a){if(a!==b){v=a,w=new l(a,F);var c=K.length;return c&&!K[c-1].enabled()&&w.disable(),K.push(w),o}return v},purge:function(){for(var a=K.length;a--;)K[a].purge();return K=[],o},history:function(){return w},set:function(a,d){return a!==b&&(C=a,d||(D=C.length),H(),"function"==typeof c.onCommandChange&&c.onCommandChange(C)),o},insert:function(a,b){return D===C.length?C+=a:C=0===D?a+C:C.slice(0,D)+a+C.slice(D),b||(D+=a.length),H(),"function"==typeof c.onCommandChange&&c.onCommandChange(C),o},get:function(){return C},commands:function(a){return a?(c.commands=a,o):a},destroy:function(){return a(document.documentElement||window).unbind(".cmd"),o.stopTime("blink",d),o.find(".cursor").next().remove().end().prev().remove().end().remove(),o.find(".prompt, .clipboard").remove(),o.removeClass("cmd").removeData("cmd"),o},prompt:function(a){if(a===b)return u;if("string"!=typeof a&&"function"!=typeof a)throw"prompt must be a function or string";return u=a,I(),H(),o},position:function(a){return"number"==typeof a?(D=0>a?0:a>C.length?C.length:a,H(),o):D},visible:function(){var a=o.visible;return function(){a.apply(o,[]),H(),I()}}(),show:function(){var a=o.show;return function(){a.apply(o,[]),H(),I()}}(),resize:function(a){return a?r=a:h(),H(),o},enable:function(){return E||(G.addClass("inverted"),o.everyTime(500,"blink",d),E=!0),o},isenabled:function(){return E},disable:function(){return E&&(o.stopTime("blink",d),G.removeClass("inverted"),E=!1),o},mask:function(a){return"boolean"==typeof a?(B=a,H(),o):B}}),o.name(c.name||c.prompt||""),u=c.prompt||"> ",I(),(c.enabled===b||c.enabled===!0)&&o.enable();return a(document.documentElement||window).bind("keypress.cmd",function(d){var f;if(d.ctrlKey&&99===d.which)return!0;if(y||"function"!=typeof c.keypress||(f=c.keypress(d)),f!==b&&!f)return f;if(E){if(a.inArray(d.which,[38,13,0,8])>-1&&123!==d.keyCode&&(38!==d.which||!d.shiftKey))return!1;if(!d.ctrlKey&&(!d.altKey||100!==d.which)||d.altKey)return y?(z+=String.fromCharCode(d.which),g(),e()):o.insert(String.fromCharCode(d.which)),!1}}).bind("keydown.cmd",n),o.data("cmd",o),o};var t=/(\[\[[gbiuso]*;[^;]*;[^\]]*\](?:[^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]?)/,u=/\[\[([gbiuso]*);([^;]*);([^;\]]*);?([^;\]]*);?([^\]]*)\]([^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]?/g,v=/\[\[([gbiuso]*;[^;\]]*;[^;\]]*(?:;|[^\]()]*);?[^\]]*)\]([^\]]*\\\][^\]]*|[^\]]*|[^\[]*\[[^\]]*)\]?/gi,w=/#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})/,x=/https?:\/\/(?:(?!&[^;]+;)[^\s:"'<>)])+/g,y=/((([^<>('")[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,})))/g,z=/('[^']*'|"(\\"|[^"])*"|\/(\\\/|[^\/])*\/|(\\ |[^ ])+|[\w-]+)/g,A=/(\[\[[gbiuso]*;[^;]*;[^\]]*\])/,B=/\[\[[gbiuso]*;[^;]*;[^\]]*\]?$/;a.terminal={split_equal:function(a,b){for(var c=!1,d=!1,e="",f=[],g=a.replace(v,function(a,b,c){var d=b.match(/;/g).length;return d=2==d?";;":3==d?";":"","[["+b+d+c.replace(/\\\]/g,"&#93;").replace(/\n/g,"\\n")+"]"+c+"]"}).split(/\n/g),h=0,i=g.length;i>h;++h)if(""!==g[h])for(var j=g[h],k=0,l=0,m=0,n=j.length;n>m;++m){if("["===j[m]&&"["===j[m+1])c=!0;else if(c&&"]"===j[m])d?(c=!1,d=!1):d=!0;else if(c&&d||!c){if("&"===j[m]){var o=j.substring(m).match(/^(&[^;]+;)/);if(!o)throw"Unclosed html entity in line "+(h+1)+" at char "+(m+1);m+=o[1].length-2,m===n-1&&f.push(p+o[1]);continue}"]"===j[m]&&"\\"===j[m-1]?--l:++l}if(l===b||m===n-1){var p=j.substring(k,m+1);e&&(p=e+p,p.match("]")&&(e="")),k=m+1,l=0;var q=p.match(v);if(q){var r=q[q.length-1];if("]"!==r[r.length-1])e=r.match(A)[1],p+="]";else if(p.match(B)){{var s=p.length;s-r[r.length-1].length}p=p.replace(B,""),e=r.match(A)[1]}}f.push(p)}}else f.push("");return f},encode:function(a,b){return a=b?a.replace(/&(?![^=]+=)/g,"&amp;"):a.replace(/&(?!#[0-9]+;|[a-zA-Z]+;|[^= "]+=[^=])/g,"&amp;"),a.replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/ /g,"&nbsp;").replace(/\t/g,"&nbsp;&nbsp;&nbsp;&nbsp;")},format:function(b,c){var d=a.extend({},{linksNoReferrer:!1},c||{});if("string"==typeof b){var e=b.split(t);return e&&e.length>1&&(b=a.map(e,function(a){return""===a?a:"["===a.substring(0,1)?a.replace(u,function(a,b,c,d,e,f,g){if(""===g)return"";g=g.replace(/\\]/g,"]");var h="";-1!==b.indexOf("b")&&(h+="font-weight:bold;");var i=[];-1!==b.indexOf("u")&&i.push("underline"),-1!==b.indexOf("s")&&i.push("line-through"),-1!==b.indexOf("o")&&i.push("overline"),i.length&&(h+="text-decoration:"+i.join(" ")+";"),-1!==b.indexOf("i")&&(h+="font-style:italic;"),c.match(w)&&(h+="color:"+c+";",-1!==b.indexOf("g")&&(h+="text-shadow:0 0 5px "+c+";")),d.match(w)&&(h+="background-color:"+d);var j='<span style="'+h+'"'+(""!==e?' class="'+e+'"':"")+' data-text="'+(""===f?g:f.replace(/&#93;/g,"]")).replace('"',"&quote;")+'">'+g+"</span>";return j}):"<span>"+a+"</span>"}).join("")),a.map(b.split(/(<\/?span[^>]*>)/g),function(a){return a.match(/span/)?a:a.replace(x,function(a){var b=a.match(/\.$/);return a=a.replace(/\.$/,""),'<a target="_blank" '+(d.linksNoReferer?' rel="noreferrer" ':"")+'href="'+a+'">'+a+"</a>"+(b?".":"")}).replace(y,'<a href="mailto:$1">$1</a>')}).join("").replace(/<span><br\/?><\/span>/g,"<br/>")}return""},escape_brackets:function(a){return a.replace(/\[/g,"&#91;").replace(/\]/g,"&#93;")},strip:function(a){return a.replace(u,"$6")},active:function(){return K.front()},from_ntroff:function(a){return a.replace(/((?:_\x08.|.\x08_)+)/g,function(a){return"[[u;;]"+a.replace(/_x08|\x08_|_\u0008|\u0008_/g,"")+"]"}).replace(/((?:.\x08.)+)/g,function(a){return"[[b;#fff;]"+a.replace(/(.)(?:\x08|\u0008)(.)/g,function(a,b,c){return c})+"]"})},ansi_colors:{normal:{black:"#000",red:"#A00",green:"#008400",yellow:"#A50",blue:"#00A",magenta:"#A0A",cyan:"#0AA",white:"#AAA"},faited:{black:"#000",red:"#640000",green:"#006100",yellow:"#737300",blue:"#000087",magenta:"#650065",cyan:"#008787",white:"#818181"},bold:{black:"#000",red:"#F55",green:"#44D544",yellow:"#FF5",blue:"#55F",magenta:"#F5F",cyan:"#5FF",white:"#FFF"},palette:["#000000","#AA0000","#00AA00","#AA5500","#0000AA","#AA00AA","#00AAAA","#AAAAAA","#555555","#FF5555","#55FF55","#FFFF55","#5555FF","#FF55FF","#55FFFF","#FFFFFF","#000000","#00005F","#000087","#0000AF","#0000D7","#0000FF","#005F00","#005F5F","#005F87","#005FAF","#005FD7","#005FFF","#008700","#00875F","#008787","#0087AF","#0087D7","#00AF00","#00AF5F","#00AF87","#00AFAF","#00AFD7","#00AFFF","#00D700","#00D75F","#00D787","#00D7AF","#00D7D7","#00D7FF","#00FF00","#00FF5F","#00FF87","#00FFAF","#00FFD7","#00FFFF","#5F0000","#5F005F","#5F0087","#5F00AF","#5F00D7","#5F00FF","#5F5F00","#5F5F5F","#5F5F87","#5F5FAF","#5F5FD7","#5F5FFF","#5F8700","#5F875F","#5F8787","#5F87AF","#5F87D7","#5F87FF","#5FAF00","#5FAF5F","#5FAF87","#5FAFAF","#5FAFD7","#5FAFFF","#5FD700","#5FD75F","#5FD787","#5FD7AF","#5FD7D7","#5FD7FF","#5FFF00","#5FFF5F","#5FFF87","#5FFFAF","#5FFFD7","#5FFFFF","#870000","#87005F","#870087","#8700AF","#8700D7","#8700FF","#875F00","#875F5F","#875F87","#875FAF","#875FD7","#875FFF","#878700","#87875F","#878787","#8787AF","#8787D7","#8787FF","#87AF00","#87AF5F","#87AF87","#87AFAF","#87AFD7","#87AFFF","#87D700","#87D75F","#87D787","#87D7AF","#87D7D7","#87D7FF","#87FF00","#87FF5F","#87FF87","#87FFAF","#87FFD7","#87FFFF","#AF0000","#AF005F","#AF0087","#AF00AF","#AF00D7","#AF00FF","#AF5F00","#AF5F5F","#AF5F87","#AF5FAF","#AF5FD7","#AF5FFF","#AF8700","#AF875F","#AF8787","#AF87AF","#AF87D7","#AF87FF","#AFAF00","#AFAF5F","#AFAF87","#AFAFAF","#AFAFD7","#AFAFFF","#AFD700","#AFD75F","#AFD787","#AFD7AF","#AFD7D7","#AFD7FF","#AFFF00","#AFFF5F","#AFFF87","#AFFFAF","#AFFFD7","#AFFFFF","#D70000","#D7005F","#D70087","#D700AF","#D700D7","#D700FF","#D75F00","#D75F5F","#D75F87","#D75FAF","#D75FD7","#D75FFF","#D78700","#D7875F","#D78787","#D787AF","#D787D7","#D787FF","#D7AF00","#D7AF5F","#D7AF87","#D7AFAF","#D7AFD7","#D7AFFF","#D7D700","#D7D75F","#D7D787","#D7D7AF","#D7D7D7","#D7D7FF","#D7FF00","#D7FF5F","#D7FF87","#D7FFAF","#D7FFD7","#D7FFFF","#FF0000","#FF005F","#FF0087","#FF00AF","#FF00D7","#FF00FF","#FF5F00","#FF5F5F","#FF5F87","#FF5FAF","#FF5FD7","#FF5FFF","#FF8700","#FF875F","#FF8787","#FF87AF","#FF87D7","#FF87FF","#FFAF00","#FFAF5F","#FFAF87","#FFAFAF","#FFAFD7","#FFAFFF","#FFD700","#FFD75F","#FFD787","#FFD7AF","#FFD7D7","#FFD7FF","#FFFF00","#FFFF5F","#FFFF87","#FFFFAF","#FFFFD7","#FFFFFF","#080808","#121212","#1C1C1C","#262626","#303030","#3A3A3A","#444444","#4E4E4E","#585858","#626262","#6C6C6C","#767676","#808080","#8A8A8A","#949494","#9E9E9E","#A8A8A8","#B2B2B2","#BCBCBC","#C6C6C6","#D0D0D0","#DADADA","#E4E4E4","#EEEEEE"]},from_ansi:function(){function b(b){var e,f=b.split(";"),g=!1,h=!1,i=!1,j=[],k="",l="",m=!1,n=!1,o=!1,p=a.terminal.ansi_colors.palette;for(var q in f){switch(e=parseInt(f[q],10)){case 1:j.push("b"),i=!0,g=!1;break;case 4:j.push("u");break;case 3:j.push("i");break;case 5:o=!0;break;case 38:m=!0;break;case 48:n=!0;break;case 2:g=!0,i=!1;break;case 7:h=!0;break;default:m&&o&&p[e-1]?k=p[e-1]:c[e]&&(k=c[e]),n&&o&&p[e-1]?l=p[e-1]:d[e]&&(l=d[e])}5!==e&&(o=!1)}if(h)if(k&&l){var r=l;l=k,k=r}else k="black",l="white";var s,t;return s=t=i?a.terminal.ansi_colors.bold:g?a.terminal.ansi_colors.faited:a.terminal.ansi_colors.normal,[j.join(""),m?k:s[k],n?l:t[l]]}var c={30:"black",31:"red",32:"green",33:"yellow",34:"blue",35:"magenta",36:"cyan",37:"white",39:"white"},d={40:"black",41:"red",42:"green",43:"yellow",44:"blue",45:"magenta",46:"cyan",47:"white",49:"black"};return function(a){var c=a.split(/(\x1B\[[0-9;]*[A-Za-z])/g);if(1==c.length)return a;var d=[];c.length>3&&"[0m"==c.slice(0,3).join("")&&(c=c.slice(3));for(var e,f,g,h,i=!1,j=0;j<c.length;++j)if(h=c[j].match(/^\x1B\[([0-9;]*)([A-Za-z])$/))switch(h[2]){case"m":if(""===h[1])continue;g="0"!==h[1]?b(h[1]):["",""],i?(d.push("]"),"0"==h[1]?(i=!1,e=f=""):(g[1]=g[1]||e,g[2]=g[2]||f,d.push("[["+g.join(";")+"]"),g[1]&&(e=g[1]),g[2]&&(f=g[2]))):(i=!0,d.push("[["+g.join(";")+"]"),g[1]&&(e=g[1]),g[2]&&(f=g[2]))}else d.push(c[j]);return i&&d.push("]"),d.join("")}}(),parseArguments:function(b){return a.map(b.match(z)||[],function(a){return"'"===a[0]&&"'"===a[a.length-1]?a.replace(/^'|'$/g,""):'"'===a[0]&&'"'===a[a.length-1]?(a=a.replace(/^"|"$/g,"").replace(/\\([" ])/g,"$1"),a.replace(/\\\\|\\t|\\n/g,function(a){return"t"===a[1]?"	":"n"===a[1]?"\n":"\\"}).replace(/\\x([0-9a-f]+)/gi,function(a,b){return String.fromCharCode(parseInt(b,16))}).replace(/\\0([0-7]+)/g,function(a,b){return String.fromCharCode(parseInt(b,8))})):"/"===a[0]&&"/"==a[a.length-1]?new RegExp(a.replace(/^\/|\/$/g,"")):a.match(/^-?[0-9]+$/)?parseInt(a,10):a.match(/^[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?$/)?parseFloat(a):a.replace(/\\ /g," ")})},splitArguments:function(b){return a.map(b.match(z)||[],function(a){return"'"===a[0]&&"'"===a[a.length-1]?a.replace(/^'|'$/g,""):'"'===a[0]&&'"'===a[a.length-1]?a.replace(/^"|"$/g,"").replace(/\\([" ])/g,"$1"):"/"===a[0]&&"/"==a[a.length-1]?a:a.replace(/\\ /g," ")})},parseCommand:function(b){return o(b,a.terminal.parseArguments)},splitCommand:function(b){return o(b,a.terminal.splitArguments)},test:function(){function b(){d.css("height",a(window).height()-20)}function c(a,b){d.echo(b+" &#91;"+(a?"[[b;#44D544;]PASS]":"[[b;#FF5555;]FAIL]")+"&#93;")}var d=a.terminal.active();if(!d){d=a("body").terminal(a.noop).css("margin",0);var e=(d.outerHeight()-d.height(),a(window));e.resize(b).resize()}d.echo("Testing...");var f='name "foo bar" baz /^asd [x]/ str\\ str 10 1e10',g=a.terminal.splitCommand(f);c("name"===g.name&&"foo bar"===g.args[0]&&"baz"===g.args[1]&&"/^asd [x]/"===g.args[2]&&"str str"===g.args[3]&&"10"===g.args[4]&&"1e10"===g.args[5],"$.terminal.splitCommand"),g=a.terminal.parseCommand(f),c("name"===g.name&&"foo bar"===g.args[0]&&"baz"===g.args[1]&&"regexp"===a.type(g.args[2])&&"^asd [x]"===g.args[2].source&&"str str"===g.args[3]&&10===g.args[4]&&1e10===g.args[5],"$.terminal.parseCommand"),f="[2;31;46mFoo[1;3;4;32;45mBar[0m[7mBaz",c("[[;#640000;#008787]Foo][[biu;#44D544;#F5F]Bar][[;#000;#AAA]Baz]"===a.terminal.from_ansi(f),"$.terminal.from_ansi"),f="[[biugs;#fff;#000]Foo][[i;;;foo]Bar][[ous;;]Baz]",d.echo("$.terminal.format"),c('<span style="font-weight:bold;text-decoration:underline line-through;font-style:italic;color:#fff;text-shadow:0 0 5px #fff;background-color:#000" data-text="Foo">Foo</span><span style="font-style:italic;" class="foo" data-text="Bar">Bar</span><span style="text-decoration:underline line-through overline;" data-text="Baz">Baz</span>'===a.terminal.format(f),"	formatting"),f="http://terminal.jcubic.pl/examples.php https://www.google.com/?q=jquery%20terminal",c('<a target="_blank" href="http://terminal.jcubic.pl/examples.php">http://terminal.jcubic.pl/examples.php</a> <a target="_blank" href="https://www.google.com/?q=jquery%20terminal">https://www.google.com/?q=jquery%20terminal</a>'===a.terminal.format(f),"	urls"),f="foo@bar.com baz.quux@example.com",c('<a href="mailto:foo@bar.com">foo@bar.com</a> <a href="mailto:baz.quux@example.com">baz.quux@example.com</a>'===a.terminal.format(f),"	emails"),f="-_-[[biugs;#fff;#000]Foo]-_-[[i;;;foo]Bar]-_-[[ous;;]Baz]-_-",c("-_-Foo-_-Bar-_-Baz-_-"===a.terminal.strip(f),"$.terminal.strip"),f="[[bui;#fff;]Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla sed dolor nisl, in suscipit justo. Donec a enim et est porttitor semper at vitae augue. Proin at nulla at dui mattis mattis. Nam a volutpat ante. Aliquam consequat dui eu sem convallis ullamcorper. Nulla suscipit, massa vitae suscipit ornare, tellus] est [[b;;#f00]consequat nunc, quis blandit elit odio eu arcu. Nam a urna nec nisl varius sodales. Mauris iaculis tincidunt orci id commodo. Aliquam] non magna quis [[i;;]tortor malesuada aliquam] eget ut lacus. Nam ut vestibulum est. Praesent volutpat tellus in eros dapibus elementum. Nam laoreet risus non nulla mollis ac luctus [[ub;#fff;]felis dapibus. Pellentesque mattis elementum augue non sollicitudin. Nullam lobortis fermentum elit ac mollis. Nam ac varius risus. Cras faucibus euismod nulla, ac auctor diam rutrum sit amet. Nulla vel odio erat], ac mattis enim.",d.echo("$.terminal.split_equal");for(var h=[10,40,60,400],i=h.length;i--;){for(var j=a.terminal.split_equal(f,h[i]),k=!0,l=0;l<j.length;++l)if(a.terminal.strip(j[l]).length>h[i]){k=!1;break}c(k,"	split "+h[i])}}},a.fn.visible=function(){return this.css("visibility","visible")},a.fn.hidden=function(){return this.css("visibility","hidden")};var C=0;a.jrpc=function(b,c,d,e,f){var g=a.json_stringify({jsonrpc:"2.0",method:c,params:d,id:++C});return a.ajax({url:b,data:g,success:e,error:f,contentType:"application/json",dataType:"json",async:!0,cache:!1,type:"POST"})};var D="{{VER}}",E=!D.match(/^\{\{/),F="Copyright (c) 2011-2013 Jakub Jankiewicz <http://jcubic.pl>",G=E?" version "+D:" ",H=new RegExp(" {"+G.length+"}$"),I=[["jQuery Terminal","(c) 2011-2013 jcubic"],["jQuery Terminal Emulator"+(E?" v. "+D:""),F.replace(/ *<.*>/,"")],["jQuery Terminal Emulator"+(E?G:""),F.replace(/^Copyright /,"")],["      _______                 ________                        __","     / / _  /_ ____________ _/__  ___/______________  _____  / /"," __ / / // / // / _  / _/ // / / / _  / _/     / /  \\/ / _ \\/ /","/  / / // / // / ___/ // // / / / ___/ // / / / / /\\  / // / /__","\\___/____ \\\\__/____/_/ \\__ / /_/____/_//_/ /_/ /_/  \\/\\__\\_\\___/","         \\/          /____/                                   ".replace(H," ")+G,F],["      __ _____                     ________                              __","     / // _  /__ __ _____ ___ __ _/__  ___/__ ___ ______ __ __  __ ___  / /"," __ / // // // // // _  // _// // / / // _  // _//     // //  \\/ // _ \\/ /","/  / // // // // // ___// / / // / / // ___// / / / / // // /\\  // // / /__","\\___//____ \\\\___//____//_/ _\\_  / /_//____//_/ /_/ /_//_//_/ /_/ \\__\\_\\___/","          \\/              /____/                                          ".replace(H,"")+G,F]];a.terminal.defaults={prompt:"> ",history:!0,exit:!0,clear:!0,enabled:!0,historySize:60,checkArity:!0,displayExceptions:!0,cancelableAjax:!0,processArguments:!0,linksNoReferrer:!1,login:null,outputLimit:-1,tabcompletion:null,historyFilter:null,onInit:a.noop,onClear:a.noop,onBlur:a.noop,onFocus:a.noop,onTerminalChange:a.noop,onExit:a.noop,keypress:a.noop,keydown:a.noop};var J=[],K=new j;a.fn.terminal=function(c,d){function e(b){return"function"==typeof W.processArguments?o(b,W.processArguments):W.processArguments?a.terminal.parseCommand(b):a.terminal.splitCommand(b)}function f(b){H.echo("string"==typeof b?b:b instanceof Array?a.map(b,function(b){return a.json_stringify(b)}).join(" "):"object"==typeof b?a.json_stringify(b):b)}function g(b){var c=function(c,d){H.pause(),a.jrpc(b,c,d,function(a){a.error?H.error("&#91;RPC&#93; "+a.error.message):f(a.result),H.resume()},function(a,b){"abort"!==b&&H.error("&#91;AJAX&#93; "+b+" - Server reponse is: \n"+a.responseText),H.resume()})};return function(a,b){if(""!==a)if(a=e(a),W.login&&"help"!==a.name){var d=b.token();d?c(a.name,[d].concat(a.args)):b.error("&#91;AUTH&#93; Access denied (no token)")}else c(a.name,a.args)}}function h(c,d){return function(f,g){if(""!==f){f=e(f);var i=c[f.name],j=a.type(i);if("function"===j){if(!d||i.length===f.args.length)return i.apply(H,f.args);H.error("&#91;Arity&#93; wrong number of arguments. Function '"+f.name+"' expect "+i.length+" got "+f.args.length)}else if("object"===j||"string"===j){var k=[];if("object"===j){for(var l in i)i.hasOwnProperty(l)&&k.push(l);i=h(i,d)}g.push(i,{prompt:f.name+"> ",name:f.name,completion:"object"===j?function(a,b,c){c(k)}:b})}else g.error("Command '"+f.name+"' Not Found")}}}function i(b,c){c=c||a.noop;var d=a.type(b),e={};if("string"===d)H.pause(),a.jrpc(b,"system.describe",[],function(d){var i=[];if(d.procs){var j={};a.each(d.procs,function(c,d){i.push(d.name),j[d.name]=function(){var c=Array.prototype.slice.call(arguments);W.checkArity&&d.params&&d.params.length!==c.length?H.error("&#91;Arity&#93; wrong number of arguments.Function '"+d.name+"' expect "+d.params.length+" got "+c.length):(H.pause(),a.jrpc(b,d.name,c,function(a){a.error?H.error("&#91;RPC&#93; "+a.error.message):f(a.result),H.resume()},function(a,b){"abort"!==b&&H.error("&#91;AJAX&#93; "+b+" - Server reponse is: \n"+a.responseText),H.resume()}))}}),e.interpreter=h(j,!1),e.completion=function(a,b,c){c(i)}}else e.interpreter=g(b),e.completion=W.completion;H.resume(),c(e)},function(){e.completion=W.completion,e.interpreter=g(b),c(e)});else if("object"===d){var i=[];for(var j in b)i.push(j);e.interpreter=h(b,!0),e.completion=function(a,b,c){c(i)},c(e)}else{if("undefined"===d)b=a.noop;else if("function"!==d)throw d+" is invalid interpreter value";c({interpreter:b,completion:W.completion})}}function j(a){return"string"==typeof a?a:"string"==typeof a.fileName?a.fileName+": "+a.message:a.message}function l(b,c){if(W.displayExceptions){var d=j(b);H.error("&#91;"+c+"&#93;: "+d),"string"==typeof b.fileName&&(H.pause(),a.get(b.fileName,function(a){H.resume();var c=b.lineNumber-1,d=a.split("\n")[c];d&&H.error("&#91;"+b.lineNumber+"&#93;: "+d)})),b.stack&&H.error(b.stack)}}function m(){var a=L.prop?L.prop("scrollHeight"):L.attr("scrollHeight");L.scrollTop(a)}function s(a,b){try{if("function"==typeof b)b(function(){});else if("string"!=typeof b){var c=a+" must be string or function";throw c}}catch(d){return l(d,a.toUpperCase()),!1}return!0}function t(b,c){try{var d=a.extend({raw:!1,finalize:a.noop},c||{});b="function"===a.type(b)?b():b,b="string"===a.type(b)?b:String(b);var e,f;if(d.raw||(b=a.terminal.encode(b)),b=a.terminal.from_ntroff(b),b=a.terminal.from_ansi(b),F.push(G),!d.raw&&(b.length>O||b.match(/\n/))){var g=a.terminal.split_equal(b,O);for(e=0,f=g.length;f>e;++e)F.push(""===g[e]||"\r"===g[e]?"&nbsp":d.raw?g[e]:a.terminal.format(g[e],{linksNoReferer:W.linksNoReferer}))}else d.raw||(b=a.terminal.format(b,{linksNoReferer:W.linksNoReferer})),F.push(b);F.push(d.finalize)}catch(h){F=[],alert("Internal Exception(draw_line):"+j(h)+"\n"+h.stack)}}function u(){try{var b;if(W.outputLimit>=0){for(var c,d=H.rows(),e=0===W.outputLimit?d:W.outputLimit,f=0,g=a("<div/>"),h=F.length;h--;)if("function"==typeof F[h])c=F[h],b=a("<div/>");else if(F[h]===G){b.prependTo(g);try{c(b)}catch(i){l(i,"USER:echo(finalize)")}}else if(b.prepend("<div>"+F[h]+"</div>"),++f===e){if(F[h-1]!==G)try{c(b)}catch(i){l(i,"USER:echo(finalize)")}break}g.children().appendTo(N)}else a.each(F,function(c,d){if(d===G)b=a("<div></div>");else if("function"==typeof d){b.appendTo(N);try{d(b)}catch(e){l(e,"USER:echo(finalize)")}}else a("<div/>").html(d).appendTo(b).width("100%")});m(),F=[]}catch(i){alert("flush "+j(i)+"\n"+i.stack)}}function v(){W.greetings===b?H.echo(H.signature):W.greetings&&H.echo(W.greetings)}function w(b){b=a.terminal.escape_brackets(a.terminal.encode(b,!0));var c=_.prompt();_.mask()&&(b=b.replace(/./g,"*")),"function"==typeof c?c(function(a){H.echo(a+b)}):H.echo(c+b)}function x(a,c){try{M=a;var d=$.top();if("exit"===a&&W.exit)if(1===$.size())if(W.login)z();else{var e="You can't exit from main interpeter";c||w(a),H.echo(e)}else H.pop("exit");else{c||w(a);var f=T.length-1;if("clear"===a&&W.clear)H.clear();else{var g=d.interpreter(a,H);g!==b&&(f===T.length-1?(T.pop(),g!==!1&&H.echo(g)):T=g===!1?T.slice(0,f).concat(T.slice(f+1)):T.slice(0,f).concat([g]).concat(T.slice(f+1)),H.resize())}}}catch(h){throw l(h,"USER"),H.resume(),h}}function y(){var b=null;_.prompt("login: "),W.history&&_.history().disable(),_.commands(function(c){try{if(w(c),b){if(_.mask(!1),H.pause(),"function"!=typeof W.login)throw"Value of login property must be a function";var d=c;W.login(b,d,function(c){if(c){var d=W.name;d=(d?d+"_":"")+U+"_",a.Storage.set(d+"token",c),a.Storage.set(d+"login",b),_.commands(x),C()}else H.error("Wrong password try again"),_.prompt("login: "),b=null;H.resume()},H)}else b=c,_.prompt("password: "),_.mask(!0)}catch(e){throw l(e,"LOGIN",H),e}})}function z(){if("function"==typeof W.onBeforelogout)try{if(W.onBeforelogout(H)===!1)return}catch(b){throw l(b,"onBeforelogout"),b}var c=(W.name?W.name+"_":"")+U+"_";if(a.Storage.remove(c+"token"),a.Storage.remove(c+"login"),W.history&&_.history().disable(),y(),"function"==typeof W.onAfterlogout)try{W.onAfterlogout(H)}catch(b){throw l(b,"onAfterlogout"),b}}function A(b){var c=(W.name?W.name+"_":"")+U+"_interpreters",d=a.Storage.get(c);d=d?new Function("return "+d+";")():[],-1==a.inArray(b,d)&&(d.push(b),a.Storage.set(c,a.json_stringify(d)))}function B(){var a=$.top(),b=(W.name?W.name+"_":"")+U+(R.length?"_"+R.join("_"):"");A(b),_.name(b),_.prompt("function"==typeof a.prompt?function(b){a.prompt(b,H)}:a.prompt),_.set(""),"function"==typeof a.onStart&&a.onStart(H)}function C(){if(B(),W.history&&_.history().enable(),v(),"function"==typeof W.onInit)try{W.onInit(H)}catch(a){throw l(a,"OnInit"),a}}function E(c){var d,e,f=$.top();if("function"===a.type(f.keydown)&&(d=f.keydown(c,H),d!==b))return d;if(H.oneTime(10,function(){Y()}),"function"===a.type(W.keydown)&&(d=W.keydown(c,H),d!==b))return d;if(H.paused()){if(68===c.which&&c.ctrlKey){if(J.length){for(e=J.length;e--;){var g=J[e];if(4!==g.readyState)try{g.abort()}catch(h){H.error("error in aborting ajax")}}J=[],H.resume()}return!1}}else{if(9!==c.which&&(S=0),68===c.which&&c.ctrlKey)return""===_.get()?$.size()>1||W.login!==b?H.pop(""):(H.resume(),H.echo("")):H.set_command(""),!1;if(W.tabcompletion&&9===c.which){++S;var i,j=_.get().substring(0,_.position()),k=j.split(" ");if(1==k.length)i=k[0];else for(i=k[k.length-1],e=k.length-1;e>0&&"\\"==k[e-1][k[e-1].length-1];e--)i=k[e-1]+" "+i;var l=new RegExp("^"+n(i));return $.top().completion(H,i,function(a){var b=_.get().substring(0,_.position());if(b===j){var c=[];for(e=a.length;e--;)l.test(a[e])&&c.push(a[e]);if(1===c.length)H.insert(c[0].replace(l,""));else if(c.length>1)if(S>=2)w(j),H.echo(c.join("	")),S=0;else{var d=!1;a:for(var f=i.length;f<c[0].length;++f){for(e=1;e<c.length;++e)if(c[0].charAt(f)!==c[e].charAt(f))break a;d=!0}d&&H.insert(c[0].slice(0,f).replace(l,""))}}}),!1}if(86===c.which&&c.ctrlKey)return void H.oneTime(1,function(){m()});if(9===c.which&&c.ctrlKey){if(K.length()>1)return H.focus(!1),!1}else 34===c.which?H.scroll(H.height()):33===c.which?H.scroll(-H.height()):H.attr({scrollTop:H.attr("scrollHeight")})}}var F=[],G=1,H=this;if(this.length>1)return this.each(function(){a.fn.terminal.call(a(this),c,a.extend({name:H.selector},d))});if(H.data("terminal"))return H.data("terminal");if(0===H.length)throw'Sorry, but terminal said that "'+H.selector+'" is not valid selector!';var L,M,N,O,P,Q,R=[],S=0,T=[],U=K.length(),V=[],W=a.extend({},a.terminal.defaults,{name:H.selector},d||{}),X=!W.enabled;a.extend(H,a.omap({clear:function(){N.html(""),_.set(""),T=[];try{W.onClear(H)}catch(a){throw l(a,"onClear"),a}return H.attr({scrollTop:0}),H},export_view:function(){return{prompt:H.get_prompt(),command:H.get_command(),position:_.position(),lines:T.slice(0)}},import_view:function(a){return H.set_prompt(a.prompt),H.set_command(a.command),_.position(a.position),T=a.lines,H.resize(),H},exec:function(a,b){return X?V.push([a,b]):x(a,b),H},commands:function(){return $.top().interpreter},greetings:function(){return v(),H},paused:function(){return X},pause:function(){return _&&(X=!0,H.disable(),_.hidden()),H},resume:function(){if(_){H.enable();var a=V;for(V=[];a.length;){var b=a.shift();H.exec.apply(H,b)}_.visible(),m()}return H},cols:function(){return O},rows:function(){var b=a('<div class="terminal"><span>&nbsp;</span></div>').appendTo("body"),c=Math.floor(H.height()/b.height());return b.remove(),c},history:function(){return _.history()},next:function(){if(1===K.length())return H;{var b=H.offset().top;H.height(),H.scrollTop()}if(p(H)){K.front().disable();var c=K.rotate().enable(),d=c.offset().top-50;a("html,body").animate({scrollTop:d},500);try{W.onTerminalChange(c)}catch(e){throw l(e,"onTerminalChange"),e}return c}return H.enable(),a("html,body").animate({scrollTop:b-50},500),H},focus:function(a,b){return H.oneTime(1,function(){if(1===K.length())if(a===!1)try{b||W.onBlur(H)===!1||H.disable()}catch(c){throw l(c,"onBlur"),c}else try{b||W.onFocus(H)===!1||H.enable()}catch(c){throw l(c,"onFocus"),c}else if(a===!1)H.next();else{var d=K.front();if(d!=H&&(d.disable(),!b))try{W.onTerminalChange(H)}catch(c){throw l(c,"onTerminalChange"),c}K.set(H),H.enable()}}),H},enable:function(){return O===b&&H.resize(),X&&_&&(_.enable(),X=!1),H},disable:function(){return _&&(X=!0,_.disable()),H},enabled:function(){return X},signature:function(){var a=H.cols(),b=15>a?null:35>a?0:55>a?1:64>a?2:75>a?3:4;return null!==b?I[b].join("\n")+"\n":""},version:function(){return D},get_command:function(){return _.get()},insert:function(a){if("string"==typeof a)return _.insert(a),H;throw"insert function argument is not a string"},set_prompt:function(a){return s("prompt",a)&&(_.prompt("function"==typeof a?function(b){a(b,H)}:a),$.top().prompt=a),H},get_prompt:function(){return $.top().prompt},set_command:function(a){return _.set(a),H},set_mask:function(a){return _.mask(a),H},get_output:function(b){return b?T:a.map(T,function(a){return"function"==typeof a[0]?a[0]():a[0]}).join("\n")},resize:function(b,c){b&&c&&(H.width(b),H.height(c)),b=H.width(),c=H.height();var d=q(H);if(d!==O){O=d,_.resize(O);var e=N.empty().detach();a.each(T,function(a,b){t.apply(null,b)}),_.before(e),u(),"function"!=typeof W.onResize||Q===c&&P===b||W.onResize(H),(Q!==c||P!==b)&&(Q=c,P=b)}return H},flush:function(){u()},echo:function(b,c){try{b=b||"";var d=a.extend({flush:!0,raw:!1,finalize:a.noop},c||{});if(F=[],t(b,d),d.flush&&u(),T.push([b,d]),d.outputLimit>=0){var e=0===d.outputLimit?H.rows():d.outputLimit,f=N.find("div div");f.length>e&&f.slice(0,T.length-e+1).remove()}Y()}catch(g){alert("terminal.echo "+j(g)+"\n"+g.stack)}return H},error:function(b,c){return H.echo("[[;#f00;]"+a.terminal.escape_brackets(b).replace(/\\$/,"&#92;")+"]",c)},scroll:function(a){var b;return a=Math.round(a),L.prop?(a>L.prop("scrollTop")&&a>0&&L.prop("scrollTop",0),b=L.prop("scrollTop"),L.scrollTop(b+a),H):(a>L.attr("scrollTop")&&a>0&&L.attr("scrollTop",0),b=L.attr("scrollTop"),L.scrollTop(b+a),H)},logout:W.login?function(){for(;$.size()>1;)$.pop();return z(),H}:function(){throw"You don't have login function"},token:W.login?function(){var b=W.name;return a.Storage.get((b?b+"_":"")+U+"_token")}:a.noop,login_name:W.login?function(){var b=W.name;return a.Storage.get((b?b+"_":"")+U+"_login")}:a.noop,name:function(){return $.top().name},push:function(b,c){if(c&&(!c.prompt||s("prompt",c.prompt))||!c){c=c||{},c.name=c.name||M,c.prompt=c.prompt||c.name+" ",R.push(c.name);var d=$.top();d&&(d.mask=_.mask()),i(b,function(b){$.push(a.extend({},b,c)),B()})}return H},pop:function(c){if(c!==b&&w(c),R.pop(),$.top().name===W.name){if(W.login&&(z(),"function"===a.type(W.onExit)))try{W.onExit(H)}catch(d){throw l(d,"onExit"),d}}else{var e=$.pop();if(B(),"function"===a.type(e.onExit))try{e.onExit(H)}catch(d){throw l(d,"onExit"),d}H.set_mask($.top().mask)}return H},level:function(){return $.size()},reset:function(){for(H.clear();$.size()>1;)$.pop();return C(),H},purge:function(){var b=(W.name?W.name+"_":"")+U+"_",c=a.Storage.get(b+"interpreters");return a.each(new Function("return "+c+";")(),function(b,c){a.Storage.remove(c+"_commands")}),a.Storage.remove(b+"interpreters"),a.Storage.remove(b+"token"),a.Storage.remove(b+"login"),H},destroy:function(){return _.destroy().remove(),N.remove(),a(document).unbind(".terminal"),a(window).unbind(".terminal"),H.unbind("click, mousewheel"),H.removeData("terminal").removeClass("terminal"),W.width&&H.css("width",""),W.height&&H.css("height",""),H

                                       }},function(a,b){return function(){try{return b.apply(this,Array.prototype.slice.apply(arguments))}catch(c){throw"exec"!==a&&l(c,"TERMINAL"),c}}}));var Y=function(){var a=r(H);return function(){a!==r(H)&&(H.resize(),a=r(H))}}();if(W.width&&H.width(W.width),W.height&&H.height(W.height),L=navigator.userAgent.toLowerCase().match(/(webkit)[ \/]([\w.]+)/)||"body"!=H[0].tagName.toLowerCase()?H:a("html"),a(document).bind("ajaxSend.terminal",function(a,b){J.push(b)}),N=a("<div>").addClass("terminal-output").appendTo(H),H.addClass("terminal"),("ontouchstart"in window||window.DocumentTouch&&document instanceof DocumentTouch)&&(H.click(function(){H.find("textarea").focus()}),H.find("textarea").focus()),W.login&&"function"==typeof W.onBeforeLogin)try{W.onBeforeLogin(H)}catch(Z){throw l(Z,"onBeforeLogin"),Z}if("string"!=typeof c||"string"!=typeof W.login&&!W.login||(W.login=function(b){return function(d,e,f){H.pause(),a.jrpc(c,b,[d,e],function(a){H.resume(),f(!a.error&&a.result?a.result:null)},function(a,b){H.resume(),H.error("&#91;AJAX&#92; Response: "+b+"\n"+a.responseText)})}}("boolean"===a.type(W.login)?"login":W.login)),K.append(H),s("prompt",W.prompt)){var $,_;i(c,function(c){$=new k(a.extend({name:W.name,prompt:W.prompt,greetings:W.greetings},c)),_=a("<div/>").appendTo(H).cmd({prompt:W.prompt,history:W.history,historyFilter:W.historyFilter,historySize:W.historySize,width:"100%",keydown:E,keypress:W.keypress?function(a){return W.keypress(a,H)}:null,onCommandChange:function(b){if("function"===a.type(W.onCommandChange))try{W.onCommandChange(b,H)}catch(c){throw l(c,"onCommandChange"),c}m()},commands:x}),W.enabled===!0?H.focus(b,!0):H.disable(),a(document).bind("click.terminal",function(b){a(b.target).parents().hasClass("terminal")||W.onBlur(H)===!1||H.disable()}),H.click(function(){H.focus()}),W.login&&H.token&&!H.token()&&H.login_name&&!H.login_name()?y():C(),H.is(":visible")&&(O=q(H),_.resize(O)),H.oneTime(100,function(){a(window).bind("resize.terminal",function(){if(H.is(":visible")){var a=H.width(),b=H.height();(Q!==b||P!==a)&&H.resize()}})}),"function"===a.type(a.fn.init.prototype.mousewheel)&&H.mousewheel(function(a,b){return H.scroll(b>0?-40:40),!1},!0)})}return H.data("terminal",H),H}}(jQuery),function(a){a(document).ready(function(){function b(b){b&&t.echo("string"==typeof b?b:b instanceof Array?a.map(b,function(b){return a.json_stringify(b)}).join(" "):"object"==typeof b?a.json_stringify(b):b)}function c(){var a=m.path;return a&&a.length>l.prompt_path_length&&(a="..."+a.slice(a.length-l.prompt_path_length+3)),"[[b;#d33682;]"+(m.user||"user")+"]@[[b;#6c71c4;]"+(m.hostname||l.domain||"web-console")+"] "+(a||"~")+"$ "}function d(a){a.set_prompt(c())}function e(b,c){c&&(a.extend(m,c),d(b))}function f(b,c,d,e,f,g){g=a.extend({pause:!0},g),g.pause&&b.pause(),a.jrpc(l.url,c,d,function(c){if(g.pause&&b.resume(),c.error)if(f)f();else{var d=a.trim(c.error.message||""),h=a.trim(c.error.data||"");!d&&h&&(d=h,h=""),b.error("&#91;ERROR&#93; RPC: "+(d||"Unknown error")+(h?" ("+h+")":""))}else e&&e(c.result)},function(c,d){if(g.pause&&b.resume(),f)f();else if("abort"!==d){var e=a.trim(c.responseText||"");b.error("&#91;ERROR&#93; AJAX: "+(d||"Unknown error")+(e?"\nServer reponse:\n"+e:""))}})}function g(a,b,c,d,e,g){var h=a.token();if(h){var i=[h,m];c&&c.length&&i.push.apply(i,c),f(a,b,i,d,e,g)}else a.error("&#91;ERROR&#93; Access denied (no authentication token found)")}function h(c,d){if(c=a.trim(c||"")){var f=a.terminal.splitCommand(c),h=null,i=[];"cd"===f.name.toLowerCase()?(h="cd",i=[f.args.length?f.args[0]:""]):(h="run",i=[c]),h&&g(d,h,i,function(a){e(d,a.environment),b(a.output)})}}function i(c,d,g,h){c=a.trim(c||""),d=a.trim(d||""),c&&d?f(h,"login",[c,d],function(a){a&&a.token?(m.user=c,e(h,a.environment),b(a.output),g(a.token)):g(null)},function(){g(null)}):g(null)}function j(a,c,d){var e=a.export_view(),f=e.command.substring(0,e.position);g(a,"completion",[c,f],function(a){b(a.output),a.completion&&a.completion.length&&(a.completion.reverse(),d(a.completion))},null,{pause:!1})}function k(){t.clear(),t.logout()}var l={url:"",prompt_path_length:32,domain:document.domain||window.location.host,is_small_window:a(document).width()<625?!0:!1},m={user:"",hostname:"",path:""},n="Web Console",o='login: test | password: test',p='<div class="spaced-bottom">'+o+"</div>";if(!l.is_small_window){n="  _    _      _     _____                       _                \n | |  | |    | |   /  __ \\                     | |            \n | |  | | ___| |__ | /  \\/ ___  _ __  ___  ___ | | ___        \n | |/\\| |/ _ \\ '_ \\| |    / _ \\| '_ \\/ __|/ _ \\| |/ _ \\ \n \\  /\\  /  __/ |_) | \\__/\\ (_) | | | \\__ \\ (_) | |  __/  \n  \\/  \\/ \\___|____/ \\____/\\___/|_| |_|___/\\___/|_|\\___| ";for(var q=15,r="",s=0;q>s;s++)r+="&nbsp;";p='<div class="spaced">'+r+o+"</div>"}var t=a("body").terminal(h,{login:i,prompt:c(),greetings:"You are authenticated",tabcompletion:!0,completion:j,onBlur:function(){return!1}});k(),a(window).unload(function(){k()}),n&&t.echo(n),p&&t.echo(p,{raw:!0})})}(jQuery);</script>

    </head>

    <body></body>

</html>

<?php } ?>