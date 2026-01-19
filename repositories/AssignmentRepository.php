<?php
include_once 'config/db.php';

class AssignmentRepository {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createJob($batchSize, $strategy) {
        $query = "INSERT INTO assignment_jobs (started_at, status, notes) VALUES (NOW(), 'RUNNING', :notes)";
        $stmt = $this->conn->prepare($query);
        $notes = "Batch Size: $batchSize, Strategy: $strategy";
        $stmt->bindParam(':notes', $notes);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    public function updateJob($jobId, $status, $total, $assigned, $failed) {
        $query = "UPDATE assignment_jobs SET 
            status = :status, 
            total_orders = :total, 
            total_assigned = :assigned, 
            total_failed = :failed,
            finished_at = CASE WHEN :status != 'RUNNING' THEN NOW() ELSE NULL END
            WHERE job_id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':assigned', $assigned);
        $stmt->bindParam(':failed', $failed);
        $stmt->bindParam(':id', $jobId);
        $stmt->execute();
    }

    public function getJob($jobId) {
        $query = "SELECT * FROM assignment_jobs WHERE job_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $jobId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getJobFailures($jobId) {
        $query = "SELECT * FROM assignment_failures WHERE job_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $jobId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
