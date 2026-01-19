<?php
if (php_sapi_name() !== 'cli') {
    die("CLI only");
}

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../repositories/AssignmentRepository.php';
include_once __DIR__ . '/../services/AssignmentService.php';

$options = getopt("", ["job_id:", "batch_size:", "date:"]);
$jobId = $options['job_id'] ?? null;
$batchSize = $options['batch_size'] ?? 200;
$date = $options['date'] ?? date('Y-m-d');

if (!$jobId) {
    die("Job ID required");
}

$database = new Database();
$db = $database->getConnection();
$repo = new AssignmentRepository($db);
$service = new AssignmentService($db, $repo);

echo "Starting Job $jobId...\n";
$service->runJob($jobId, $batchSize, $date);
echo "Job $jobId Finished.\n";
