<?php
include_once 'config/db.php';

class CourierRepository {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAvailableCouriers($city, $zone, $date) {
        $query = "
            SELECT 
                c.id, 
                c.name, 
                c.daily_capacity, 
                COALESCE(cds.assigned_count, 0) as assigned_count
            FROM couriers c
            JOIN courier_locations cl ON c.id = cl.courier_id
            LEFT JOIN courier_daily_stats cds ON c.id = cds.courier_id AND cds.stat_date = :date
            WHERE 
                c.is_active = 1 
                AND cl.city = :city
        ";

        if ($zone) {
            $query .= " AND cl.zone = :zone";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':city', $city);
        if ($zone) {
            $stmt->bindValue(':zone', $zone);
        }
        $stmt->bindValue(':date', $date);
        
        $stmt->execute();
        $couriers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($couriers as &$courier) {
            $courier['id'] = (int) $courier['id'];
            $courier['daily_capacity'] = (int) $courier['daily_capacity'];
            $courier['assigned_count'] = (int) $courier['assigned_count'];
            $courier['remaining_capacity'] = $courier['daily_capacity'] - $courier['assigned_count'];
        }

        return $couriers;
    }
}
