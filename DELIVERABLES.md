# Bulk Order Assignment System

## 1. Database Schema

The system uses a MySQL relational database with the following key tables.

### `orders`
Stores all inbound orders.
- `order_id` (PK): Unique identifier.
- `status`: Lifecycle state (`NEW`, `PROCESSING`, `ASSIGNED`, `UNASSIGNED`).
- `delivery_city`, `delivery_zone`: Location data for routing.
- `order_value`: Value of the order.

### `couriers`
Stores static courier information.
- `id` (PK): Unique identifier.
- `daily_capacity`: Maximum orders per day.
- `is_active`: Soft delete flag.

### `courier_locations`
Maps couriers to specific serviceable areas.
- `courier_id`, `city`, `zone`: Composite unique key.
- Determines if a courier can accept an order.

### `courier_daily_stats`
Tracks dynamic capacity usage per day.
- `courier_id`, `stat_date`: Composite PK.
- `assigned_count`: Number of orders currently assigned for that date.
- Used to calculate `remaining_capacity`.

### `assignment_jobs`
Tracks the execution of bulk assignment processes.
- `job_id`: Unique run identifier.
- `status`: `RUNNING`, `COMPLETED`, `FAILED`.
- Stats: `total_orders`, `total_assigned`, `total_failed`.

### `assignment_failures`
Tracks orders that could not be assigned.
- `order_id`: The failed order.
- `reason`: Error message (e.g., "No capacity").
- `next_retry_at`: Timestamp for retry logic.

---

## 2. APIs

The system exposes the following REST endpoints (implemented in PHP):

### **GET /orders/unassigned**
- **Purpose**: Fetch a list of orders waiting for assignment.
- **Response**: JSON array of orders with status `NEW` or `UNASSIGNED`.

### **GET /couriers/available**
- **Purpose**: List couriers that are active.
- **Query Params**: `?city=X&zone=Y` (Optional filtering).

### **POST /assignments/bulk**
- **Purpose**: Trigger the bulk assignment algorithm.
- **Body**: `{ "batch_size": 100, "date": "2023-10-27" }`
- **Behavior**: Starts a background process or runs synchronously (depending on config) to assign pending orders.

### **GET /assignments/jobs/{id}**
- **Purpose**: Check the status of a bulk assignment job.
- **Response**: `{ "job_id": 1, "status": "COMPLETED", "assigned": 50, ... }`

---

## 3. Assignment Logic

The core logic resides in `services/AssignmentService.php`. It follows a **Capacity-First Strategy** with robust concurrency control.

### Algorithm Steps:
1.  **Iterative Batching**:
    - The job runs in a loop, fetching batches of `NEW` or `UNASSIGNED` orders.
    - Uses `SELECT ... FOR UPDATE SKIP LOCKED` to allow multiple workers to run in parallel without blocking each other.
2.  **State Transition**:
    - Selected orders are immediately marked as `PROCESSING` to prevent other jobs from picking them up.
3.  **Courier Selection (`findBestCourier`)**:
    - For each order, fetches candidate couriers matching the `city` and `zone`.
    - **Optimization**: Sorts candidates by **Max Remaining Capacity** (Descending).
    - **Concurrency Lock**: Explicitly locks the courier's `daily_stats` row (`FOR UPDATE`) to ensure the capacity check is atomic.
4.  **Assignment**:
    - If a valid courier is found:
        - Increment `assigned_count` in `courier_daily_stats`.
        - Update order status to `ASSIGNED`.
        - Log success in `order_assignments`.
    - If no courier is found:
        - Update order status to `UNASSIGNED`.
        - Log failure in `assignment_failures` with a retry timestamp.
5.  **Transaction Management**:
    - Operations are wrapped in database transactions to ensure data integrity.

---

## 4. Actual Code (Key Snippets)

The full source code is available in the repository. Below is the core `runJob` implementation from `AssignmentService.php`.

```php
public function runJob($jobId, $batchSize, $date) {
    try {
        while (true) {
            $this->conn->beginTransaction();

            // 1. Fetch Batch safely
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
                break; // No more orders
            }

            // 2. Mark as Processing
            $orderIds = array_column($orders, 'order_id');
            $idList = implode(',', $orderIds);
            $this->conn->exec("UPDATE orders SET status = 'PROCESSING' WHERE order_id IN ($idList)");

            // 3. Process each order
            foreach ($orders as $order) {
                try {
                    $courierId = $this->findBestCourier($order, $date);

                    if ($courierId) {
                        $this->assignOrder($order['order_id'], $courierId, $jobId, $date);
                    } else {
                        $this->failOrder($order['order_id'], $jobId, "No available courier or capacity");
                    }
                } catch (Exception $e) {
                     $this->failOrder($order['order_id'], $jobId, "Error: " . $e->getMessage());
                }
            }

            $this->conn->commit();
        }
    } catch (Exception $e) {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        // Handle critical job failure...
    }
}
```
