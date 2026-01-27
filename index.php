<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'config/db.php';
include_once 'utils/Response.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

$apiIndex = false;
if (isset($uri[1]) && $uri[1] === 'api') {
    $apiIndex = 1;
} elseif (isset($uri[2]) && $uri[2] === 'api') {
    $apiIndex = 2; 
}

if ($apiIndex !== false) {
    $resource = isset($uri[$apiIndex + 1]) ? $uri[$apiIndex + 1] : null;
    $action = isset($uri[$apiIndex + 2]) ? $uri[$apiIndex + 2] : null; 
    $param = isset($uri[$apiIndex + 3]) ? $uri[$apiIndex + 3] : null;

    try {
        if ($resource === 'orders' && $action === 'unassigned') {
            include_once 'controllers/OrdersController.php';
            $controller = new OrdersController();
            $controller->getUnassigned();
        } 
        elseif ($resource === 'couriers' && $action === 'available') {
            include_once 'controllers/CouriersController.php';
            $controller = new CouriersController();
            $controller->getAvailable();
        }
        elseif ($resource === 'assignments' && $action === 'bulk') {
            include_once 'controllers/AssignmentsController.php';
            $controller = new AssignmentsController();
            $controller->triggerBulkAssignment();
        }
        elseif ($resource === 'assignments' && $action === 'jobs' && $param) {
            include_once 'controllers/AssignmentsController.php';
            $controller = new AssignmentsController();
            $controller->getJobStatus($param);
        }
        else {
            Response::send(404, ["message" => "Endpoint not found"]);
        }
    } catch (Exception $e) {
        Response::send(500, ["message" => "Internal Server Error: " . $e->getMessage()]);
    }
} else {
    echo "Welcome to Order Assignment System. Use /api/... endpoints.";
}
