INSERT INTO couriers (name, daily_capacity, is_active) VALUES 
('BlueDart', 100, 1),
('Delhivery', 150, 1),
('Shadowfax', 80, 1),
('EcomExpress', 120, 1),
('XpressBees', 200, 1),
('DTDC', 90, 1),
('FedEx', 110, 1),
('Gati', 70, 0), 
('Dunzo', 50, 1),
('UberDirect', 60, 1);

INSERT INTO courier_locations (courier_id, city, zone) VALUES
(1, 'Mumbai', 'Andheri'),
(1, 'Mumbai', 'Bandra'),
(1, 'Delhi', 'Connaught Place');

INSERT INTO courier_locations (courier_id, city, zone) VALUES
(2, 'Mumbai', 'Andheri'),
(2, 'Mumbai', 'Bandra'),
(2, 'Mumbai', 'Powai'),
(2, 'Bangalore', 'Koramangala');

INSERT INTO courier_locations (courier_id, city, zone) VALUES
(3, 'Mumbai', 'Powai');

INSERT INTO orders (order_date, delivery_city, delivery_zone, order_value, status)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n+1 FROM seq WHERE n < 500)
SELECT 
  DATE_ADD('2026-01-17 08:00:00', INTERVAL n SECOND), 
  'Mumbai', 
  'Andheri', 
  100 + (n * 0.5), 
  'NEW'
FROM seq;

INSERT INTO orders (order_date, delivery_city, delivery_zone, order_value, status)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n+1 FROM seq WHERE n < 300)
SELECT 
  DATE_ADD('2026-01-17 08:30:00', INTERVAL n SECOND), 
  'Mumbai', 
  'Bandra', 
  200 + (n * 1.5), 
  'NEW'
FROM seq;

INSERT INTO orders (order_date, delivery_city, delivery_zone, order_value, status)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n+1 FROM seq WHERE n < 200)
SELECT 
  DATE_ADD('2026-01-17 09:00:00', INTERVAL n SECOND), 
  'Bangalore', 
  'Koramangala', 
  500 + n, 
  'NEW'
FROM seq;
