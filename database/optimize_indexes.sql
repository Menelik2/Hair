-- Run once on existing databases
ALTER TABLE tickets ADD INDEX idx_tickets_status_joined (status, joined_at);
ALTER TABLE tickets ADD INDEX idx_tickets_status_priority_joined (status, priority, joined_at);
ALTER TABLE tickets ADD INDEX idx_tickets_status_completed (status, completed_at);
ALTER TABLE tickets ADD INDEX idx_tickets_customer_status (customer_id, status);
ALTER TABLE tickets ADD INDEX idx_tickets_phone_status (customer_phone, status);
ALTER TABLE stylists ADD INDEX idx_stylists_status_available (status, is_available);
ALTER TABLE appointments ADD INDEX idx_appointments_phone_date (customer_phone, appointment_date);
ALTER TABLE appointments ADD INDEX idx_appointments_customer_date (customer_id, appointment_date, status);
