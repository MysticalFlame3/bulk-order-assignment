<?php

if (php_sapi_name() !== 'cli') {
    die("CLI only");
}

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../repositories/AssignmentRepository.php';
include_once __DIR__ . '/../services/AssignmentService.php';

$database = new Database();
$db = $database->getConnection();
$repo = new AssignmentRepository($db);
$service = new AssignmentService($db, $repo);

echo "Checking for failed assignments...\n";

$query = "SELECT * FROM assignment_failures WHERE status = 'PENDING' AND next_retry_at <= NOW()";
$stmt = $db->prepare($query);
$stmt->execute();
$failures = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($failures)) {
    echo "No pending retries.\n";
    exit();
}

foreach ($failures as $fail) {
    if ($fail['retry_count'] >= 3) {
        // Give Up
        $upd = $db->prepare("UPDATE assignment_failures SET status = 'GAVE_UP' WHERE failure_id = :id");
        $upd->execute([':id' => $fail['failure_id']]);
        echo "Failure ID {$fail['failure_id']}: Gave Up.\n";
        continue;
    }

    $orderId = $fail['order_id'];
    $date = date('Y-m-d'); 
    
    echo "Retrying Order $orderId...\n";
    $success = $service->processSingleOrder($orderId, $date, $fail['failure_id']);

    if ($success) {
        echo "Order $orderId: Success.\n";
    } else {
        $newCount = $fail['retry_count'] + 1;
        $minutes = ($newCount == 1) ? 5 : 15;
        
        $upd = $db->prepare("UPDATE assignment_failures SET retry_count = :rc, next_retry_at = DATE_ADD(NOW(), INTERVAL :min MINUTE) WHERE failure_id = :id");
        $upd->execute([':rc' => $newCount, ':min' => $minutes, ':id' => $fail['failure_id']]);
        echo "Order $orderId: Failed again. Next retry in $minutes mins.\n";
    }
}
