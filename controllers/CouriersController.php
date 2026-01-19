<?php
include_once 'config/db.php';
include_once 'repositories/CourierRepository.php';
include_once 'utils/Response.php';

class CouriersController {
    private $db;
    private $courierRepository;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->courierRepository = new CourierRepository($this->db);
    }

    public function getAvailable() {
        $city = isset($_GET['city']) ? $_GET['city'] : null;
        $zone = isset($_GET['zone']) ? $_GET['zone'] : null;
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

        if (!$city) {
            Response::send(400, ["message" => "City is required"]);
        }

        $couriers = $this->courierRepository->getAvailableCouriers($city, $zone, $date);

        Response::send(200, [
            "city" => $city,
            "zone" => $zone,
            "date" => $date,
            "couriers" => $couriers
        ]);
    }
}
