<?php
include_once 'config/db.php';
include_once 'repositories/AssignmentRepository.php';
include_once 'services/AssignmentService.php';
include_once 'utils/Response.php';

class AssignmentsController {
    private $db;
    private $assignmentRepo;
    private $assignmentService;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->assignmentRepo = new AssignmentRepository($this->db);
        $this->assignmentService = new AssignmentService($this->db, $this->assignmentRepo);
    }

    public function triggerBulkAssignment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::send(405, ["message" => "Method Not Allowed"]);
        }

        $input = json_decode(file_get_contents("php://input"), true);
        $batchSize = isset($input['batch_size']) ? (int)$input['batch_size'] : 200;
        $strategy = isset($input['strategy']) ? $input['strategy'] : 'MAX_REMAINING_CAPACITY';
        $date = isset($input['date']) ? $input['date'] : date('Y-m-d');

        $jobId = $this->assignmentRepo->createJob($batchSize, $strategy);

        $cmd = "start /B php " . __DIR__ . "/../cron/run_job.php --job_id=$jobId --batch_size=$batchSize --date=$date";
        pclose(popen($cmd, "r"));


        Response::send(200, [
            "job_id" => $jobId,
            "status" => "RUNNING",
            "message" => "Bulk assignment started in background"
        ]);
    }

    public function getJobStatus($jobId) {
        $job = $this->assignmentRepo->getJob($jobId);

        if (!$job) {
            Response::send(404, ["message" => "Job not found"]);
        }

        $failures = $this->assignmentRepo->getJobFailures($jobId);

        Response::send(200, [
            "job_id" => $job['job_id'],
            "status" => $job['status'],
            "started_at" => $job['started_at'],
            "finished_at" => $job['finished_at'],
            "total_orders" => $job['total_orders'],
            "total_assigned" => $job['total_assigned'],
            "total_failed" => $job['total_failed'],
            "failures" => $failures
        ]);
    }
}
