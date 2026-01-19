<?php
include_once 'config/db.php';

class OrderRepository {
    private $conn;
    private $table_name = "orders";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUnassignedOrders($page, $limit, $city = null, $zone = null, $date = null) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT * FROM " . $this->table_name . " WHERE status IN ('NEW', 'UNASSIGNED')";
        $countQuery = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE status IN ('NEW', 'UNASSIGNED')";
        
        $params = [];

        if ($city) {
            $query .= " AND delivery_city = :city";
            $countQuery .= " AND delivery_city = :city";
            $params[':city'] = $city;
        }
        if ($zone) {
            $query .= " AND delivery_zone = :zone";
            $countQuery .= " AND delivery_zone = :zone";
            $params[':zone'] = $zone;
        }
        if ($date) {
            $query .= " AND DATE(order_date) = :date";
            $countQuery .= " AND DATE(order_date) = :date";
            $params[':date'] = $date;
        }

        $query .= " ORDER BY order_date ASC LIMIT :limit OFFSET :offset";

        $stmtAcc = $this->conn->prepare($countQuery);
        $stmtAcc->execute($params);
        $totalRow = $stmtAcc->fetch(PDO::FETCH_ASSOC);
        $total = $totalRow['total'];

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "total" => $total,
            "orders" => $orders
        ];
    }
}
