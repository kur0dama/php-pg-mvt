<?php
require_once "php_pg_mvt.php";

////////////////////////////////////////////////////////////////////////////////
// Example class, creates connection to PGSQL DB
////////////////////////////////////////////////////////////////////////////////

class dbcon {

     private $db_connection = null;

     // reads parameters from supplied .ini file, connects to DB
     public function __construct($ini_params)
     {
         $host = $ini_params['host'];
         $port = $ini_params['port'];
         $db   = $ini_params['db'];
         $user = $ini_params['user'];
         $pass = $ini_params['pass'];
 
         try {
             $this->db_connection = new \PDO("pgsql:host=$host;port=$port;dbname=$db",$user,$pass);
         } catch (\PDOException $e) {
             exit($e->getMessage());
         }
     }
 
     public function get_connection()
     {
         return $this->db_connection;
     }
}

////////////////////////////////////////////////////////////////////////////////
// Example class, manages interactions with tileserver
////////////////////////////////////////////////////////////////////////////////

class controller {
     
     private $db;

     public function __construct($db, $params) {
          $this->db = $db;
          $this->params = $params;
     }

     public function run_query() {
          $response = $this->get_results();
          header($response['status_code_header']);
          if ($response['body']) {
               echo $response['body'];
          }
     }

     // initialize tile_request_handler, request tiles based on params
     private function get_results() {
          $handler = new tile_request_handler($this->db, $this->params);
          $result = $handler->pull_mvt();
          $response['status_code_header'] = 'HTTP/1.1 200 OK';
          $response['body'] = $result;
          return $response;
     }
}

////////////////////////////////////////////////////////////////////////////////
// Example headers
// SHOULD NOT BE USED IN PRODUCTION, PURELY FOR ILLUSTRATIVE PURPOSES
////////////////////////////////////////////////////////////////////////////////

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

////////////////////////////////////////////////////////////////////////////////
// Parse URI, send call to tileserver
////////////////////////////////////////////////////////////////////////////////

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);

// take only the params we want
// expects a request in the format: /table/z/x/y.mvt
$params = array_slice($uri,2);

// example only shows GET call to API
// pulls from sample .ini file to get DB connection details
$ini = parse_ini_file('db_params.ini');

// Content-Type header assumes you are serving vector tiles; if you are serving tiles
// If you are server GeoJSON, modify accordingly
if ($_SERVER["REQUEST_METHOD"]=='GET') {
    header("Content-Type: application/vnd.mapbox-vector-tile");
    $dbcon = new dbcon($ini);
    $controller = new controller($dbcon->get_connection(), $params);
    $controller->run_query();
}
?>