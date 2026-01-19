# Bulk Order Assignment System

## Overview
This backend service assigns orders to couriers in bulk, ensuring efficiency, correctness, and scalability.

## Architecture
- **Language**: PHP (Vanilla for performance/simplicity)
- **Database**: MySQL 8.0
- **Pattern**: MVC-Service-Repository

## Database Schema
The system uses 7 tables:
1. `orders`: Stores order details and status.
2. `couriers`: Static courier data.
3. `courier_locations`: Maps couriers to cities/zones.
4. `courier_daily_stats`: Tracks daily capacity usage.
5. `assignment_jobs`: Tracks bulk assignment runs.
6. `order_assignments`: Audit log of assignments.
7. `assignment_failures`: Tracks failures for retry.

## APIs
1. `GET /api/orders/unassigned`: Fetch unassigned orders.
2. `GET /api/couriers/available`: Fetch available couriers.
3. `POST /api/assignments/bulk`: Trigger bulk assignment.
4. `GET /api/assignments/jobs/:id`: View job status.

## Assignment Logic
- **Strategy**: MAX_REMAINING_CAPACITY (Pick courier with most space).
- **Concurrency**: Relies on DB transactions and 'FOR UPDATE' locks.
- **Race Condition Prevention**: Orders are marked 'PROCESSING' before assignment.

## Setup
1. Import `sql/schema.sql` into MySQL.
2. Import `sql/seed.sql` for test data.
3. Configure `config/db.php` with your credentials.
4. Run `php -S localhost:8000` to start the server.
