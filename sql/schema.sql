CREATE TABLE orders (
  order_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_date DATETIME NOT NULL,
  delivery_city VARCHAR(100) NOT NULL,
  delivery_zone VARCHAR(100) NULL,
  order_value DECIMAL(10,2) NOT NULL,
  status ENUM('NEW','PROCESSING','ASSIGNED','UNASSIGNED') DEFAULT 'NEW',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_orders_status_city ON orders(status, delivery_city);
CREATE INDEX idx_orders_date ON orders(order_date);

CREATE TABLE couriers (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  daily_capacity INT NOT NULL,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_couriers_active ON couriers(is_active);

CREATE TABLE courier_locations (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  courier_id BIGINT NOT NULL,
  city VARCHAR(100) NOT NULL,
  zone VARCHAR(100) NULL,
  UNIQUE KEY uq_courier_city_zone (courier_id, city, zone),
  FOREIGN KEY (courier_id) REFERENCES couriers(id)
);

CREATE INDEX idx_city_zone ON courier_locations(city, zone);

CREATE TABLE courier_daily_stats (
  courier_id BIGINT NOT NULL,
  stat_date DATE NOT NULL,
  assigned_count INT DEFAULT 0,
  PRIMARY KEY (courier_id, stat_date),
  FOREIGN KEY (courier_id) REFERENCES couriers(id)
);

CREATE TABLE assignment_jobs (
  job_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  status ENUM('RUNNING','COMPLETED','FAILED') DEFAULT 'RUNNING',
  total_orders INT DEFAULT 0,
  total_assigned INT DEFAULT 0,
  total_failed INT DEFAULT 0,
  notes TEXT NULL
);

CREATE INDEX idx_jobs_status ON assignment_jobs(status);

CREATE TABLE order_assignments (
  assignment_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT NOT NULL,
  courier_id BIGINT NOT NULL,
  assignment_date DATETIME NOT NULL,
  job_id BIGINT NOT NULL,
  status ENUM('SUCCESS','FAILED') DEFAULT 'SUCCESS',
  failure_reason TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_once(order_id),
  FOREIGN KEY (order_id) REFERENCES orders(order_id),
  FOREIGN KEY (courier_id) REFERENCES couriers(id)
);

CREATE INDEX idx_assignments_job ON order_assignments(job_id);
CREATE INDEX idx_assignments_courier_date ON order_assignments(courier_id, assignment_date);

CREATE TABLE assignment_failures (
  failure_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  job_id BIGINT NOT NULL,
  order_id BIGINT NOT NULL,
  reason TEXT NOT NULL,
  retry_count INT DEFAULT 0,
  next_retry_at DATETIME NULL,
  status ENUM('PENDING','RETRIED','GAVE_UP') DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_failed_order(order_id),
  FOREIGN KEY (job_id) REFERENCES assignment_jobs(job_id)
);

CREATE INDEX idx_failures_status_retry ON assignment_failures(status, next_retry_at);
CREATE INDEX idx_failures_job ON assignment_failures(job_id);
