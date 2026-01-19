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

if (isset($uri[2]) && $uri[2] === 'api') {
    $resource = isset($uri[3]) ? $uri[3] : null;
    $action = isset($uri[4]) ? $uri[4] : null; 

    
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
        elseif ($resource === 'assignments' && $action === 'jobs' && isset($uri[5])) {
            include_once 'controllers/AssignmentsController.php';
            $controller = new AssignmentsController();
            $controller->getJobStatus($uri[5]);
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
