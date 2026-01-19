<?php
include_once 'config/db.php';
include_once 'repositories/OrderRepository.php';
include_once 'utils/Response.php';

class OrdersController {
    private $db;
    private $orderRepository;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->orderRepository = new OrderRepository($this->db);
    }

    public function getUnassigned() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $city = isset($_GET['city']) ? $_GET['city'] : null;
        $zone = isset($_GET['zone']) ? $_GET['zone'] : null;
        $date = isset($_GET['date']) ? $_GET['date'] : null;

        $result = $this->orderRepository->getUnassignedOrders($page, $limit, $city, $zone, $date);

        Response::send(200, [
            "page" => $page,
            "limit" => $limit,
            "total" => $result['total'],
            "orders" => $result['orders']
        ]);
    }
}
