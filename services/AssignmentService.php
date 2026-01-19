<?php
include_once 'config/db.php';
include_once 'repositories/CourierRepository.php';

class AssignmentService {
    private $db;
    private $conn;
    private $assignmentRepo;
    private $courierRepo;

    public function __construct($db, $assignmentRepo) {
        $this->conn = $db;
        $this->assignmentRepo = $assignmentRepo;
        $this->courierRepo = new CourierRepository($db);
    }

    public function runJob($jobId, $batchSize, $date) {
        $totalOrders = 0;
        $totalAssigned = 0;
        $totalFailed = 0;

        try {
            while (true) {
                $this->conn->beginTransaction();

                $sql = "SELECT * FROM orders 
                        WHERE status IN ('NEW', 'UNASSIGNED') 
                        ORDER BY order_date ASC 
                        LIMIT :limit 
                        FOR UPDATE SKIP LOCKED"; 
                
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(':limit', (int)$batchSize, PDO::PARAM_INT);
                $stmt->execute();
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($orders)) {
                    $this->conn->commit();
                    break; 
                }

                $orderIds = array_column($orders, 'order_id');
                $idList = implode(',', $orderIds);
                $this->conn->exec("UPDATE orders SET status = 'PROCESSING' WHERE order_id IN ($idList)");

                foreach ($orders as $order) {
                    $totalOrders++;
                    
                    try {
                        $courierId = $this->findBestCourier($order, $date);

                        if ($courierId) {
                            $this->assignOrder($order['order_id'], $courierId, $jobId, $date);
                            $totalAssigned++;
                        } else {
                            $this->failOrder($order['order_id'], $jobId, "No available courier or capacity");
                            $totalFailed++;
                        }
                    } catch (Exception $e) {
                         $this->failOrder($order['order_id'], $jobId, "Error: " . $e->getMessage());
                         $totalFailed++;
                    }
                }

                $this->conn->commit();
                
                $this->assignmentRepo->updateJob($jobId, 'RUNNING', $totalOrders, $totalAssigned, $totalFailed);
            }

            $this->assignmentRepo->updateJob($jobId, 'COMPLETED', $totalOrders, $totalAssigned, $totalFailed);

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->assignmentRepo->updateJob($jobId, 'FAILED', $totalOrders, $totalAssigned, $totalFailed);
        }
    }

    public function processSingleOrder($orderId, $date, $retryId = null) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT * FROM orders WHERE order_id = :id FOR UPDATE");
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order || $order['status'] == 'ASSIGNED') {
                $this->conn->commit();
                return true; 
            }

            $courierId = $this->findBestCourier($order, $date);

            if ($courierId) {
                $stmtOrder = $this->conn->prepare("UPDATE orders SET status = 'ASSIGNED' WHERE order_id = :id");
                $stmtOrder->execute([':id' => $orderId]);

                $stmtStats = $this->conn->prepare("UPDATE courier_daily_stats SET assigned_count = assigned_count + 1 WHERE courier_id = :id AND stat_date = :date");
                $stmtStats->execute([':id' => $courierId, ':date' => $date]);

                if ($retryId) {
                    $stmtRetry = $this->conn->prepare("UPDATE assignment_failures SET status = 'RETRIED' WHERE failure_id = :fid");
                    $stmtRetry->execute([':fid' => $retryId]);
                }

                $stmtAsg = $this->conn->prepare("INSERT INTO order_assignments (order_id, courier_id, assignment_date, job_id, status) VALUES (:oid, :cid, NOW(), 0, 'SUCCESS')");
                $stmtAsg->execute([':oid' => $orderId, ':cid' => $courierId]);

                $this->conn->commit();
                return true;
            } else {
                 if ($retryId) {
                 }
                 $this->conn->commit();
                 return false;
            }

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    private function findBestCourier($order, $date) {
        $city = $order['delivery_city'];
        $zone = $order['delivery_zone'];

        
        $candidates = $this->courierRepo->getAvailableCouriers($city, $zone, $date);
        
        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function($a, $b) {
            return $b['remaining_capacity'] <=> $a['remaining_capacity'];
        });

        foreach ($candidates as $candidate) {
            
            $cid = $candidate['id'];
            
            
            $this->conn->exec("INSERT IGNORE INTO courier_daily_stats (courier_id, stat_date, assigned_count) VALUES ($cid, '$date', 0)");
            
            $stmtLock = $this->conn->prepare("SELECT daily_capacity, assigned_count FROM couriers c 
                JOIN courier_daily_stats cds ON c.id = cds.courier_id 
                WHERE courier_id = :id AND stat_date = :date FOR UPDATE");
            $stmtLock->execute([':id' => $cid, ':date' => $date]);
            $stats = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if ($stats && $stats['assigned_count'] < $stats['daily_capacity']) {
                return $cid; 
            }
        }

        return null;
    }

    private function assignOrder($orderId, $courierId, $jobId, $date) {
        $stmtOrder = $this->conn->prepare("UPDATE orders SET status = 'ASSIGNED' WHERE order_id = :id");
        $stmtOrder->execute([':id' => $orderId]);

        
        
        $stmtStats = $this->conn->prepare("UPDATE courier_daily_stats SET assigned_count = assigned_count + 1 WHERE courier_id = :id AND stat_date = :date");
        $stmtStats->execute([':id' => $courierId, ':date' => $date]);

        $stmtAsg = $this->conn->prepare("INSERT INTO order_assignments (order_id, courier_id, assignment_date, job_id, status) VALUES (:oid, :cid, NOW(), :jid, 'SUCCESS')");
        $stmtAsg->execute([':oid' => $orderId, ':cid' => $courierId, ':jid' => $jobId]);
    }

    private function failOrder($orderId, $jobId, $reason) {
        $stmtOrder = $this->conn->prepare("UPDATE orders SET status = 'UNASSIGNED' WHERE order_id = :id");
        $stmtOrder->execute([':id' => $orderId]);

        $stmtFail = $this->conn->prepare("INSERT INTO assignment_failures (job_id, order_id, reason, status, next_retry_at) VALUES (:jid, :oid, :r, 'PENDING', DATE_ADD(NOW(), INTERVAL 1 MINUTE))");
        $stmtFail->execute([':jid' => $jobId, ':oid' => $orderId, ':r' => $reason]);
    }
}
