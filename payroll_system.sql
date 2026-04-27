-- =============================================
-- 1. CREATE DATABASE
-- =============================================
CREATE DATABASE payroll_system;
USE payroll_system;
-- =============================================
-- 2. LOOKUP TABLES (Reference/Static Data)
-- =============================================
-- Department lookup
CREATE TABLE departments (
department_id INT AUTO_INCREMENT PRIMARY KEY,
department_name VARCHAR(100) UNIQUE NOT NULL,
description TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Position lookup with salary grade
CREATE TABLE positions (
position_id INT AUTO_INCREMENT PRIMARY KEY,
position_name VARCHAR(100) UNIQUE NOT NULL,
position_level VARCHAR(50),
base_salary DECIMAL(10,2) NOT NULL CHECK (base_salary >= 0),
overtime_rate_multiplier DECIMAL(3,2) DEFAULT 1.5,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Deduction type lookup (normalized to remove repeating groups)
CREATE TABLE deduction_types (
deduction_type_id INT AUTO_INCREMENT PRIMARY KEY,
type_code VARCHAR(20) UNIQUE NOT NULL,
type_name VARCHAR(50) NOT NULL,
is_mandatory BOOLEAN DEFAULT TRUE,
is_percentage BOOLEAN DEFAULT FALSE,
default_rate DECIMAL(5,2),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Bonus type lookup
CREATE TABLE bonus_types (
bonus_type_id INT AUTO_INCREMENT PRIMARY KEY,
type_name VARCHAR(50) UNIQUE NOT NULL,
description TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- =============================================
-- 3. CORE TABLES (2NF - No partial dependencies)
-- =============================================
-- Employees table (main entity)
CREATE TABLE employees (
employee_id INT AUTO_INCREMENT PRIMARY KEY,
employee_code VARCHAR(20) UNIQUE,
first_name VARCHAR(50) NOT NULL,
last_name VARCHAR(50) NOT NULL,
middle_name VARCHAR(50),
suffix VARCHAR(10),
gender ENUM('Male', 'Female', 'Other', 'Prefer not to say'),
birth_date DATE,
contact_number VARCHAR(20),
alternate_contact VARCHAR(20),
email VARCHAR(100) UNIQUE,
address TEXT,
bank_account_number VARCHAR(50),
tin_number VARCHAR(50) COMMENT 'Tax Identification Number',
sss_number VARCHAR(20),
philhealth_number VARCHAR(20),
pagibig_number VARCHAR(20),
date_hired DATE NOT NULL,
date_regularized DATE,
date_resigned DATE,
status ENUM('Active', 'Resigned', 'Terminated', 'On Leave') DEFAULT 'Active',
department_id INT,
position_id INT,
supervisor_id INT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE
CURRENT_TIMESTAMP,
FOREIGN KEY (department_id) REFERENCES departments(department_id),
FOREIGN KEY (position_id) REFERENCES positions(position_id),
FOREIGN KEY (supervisor_id) REFERENCES employees(employee_id),
INDEX idx_status (status),
INDEX idx_department (department_id),
INDEX idx_position (position_id),
INDEX idx_date_hired (date_hired),
CONSTRAINT chk_dates CHECK (date_hired <= COALESCE(date_resigned,
CURDATE())),
CONSTRAINT chk_birth_date CHECK (birth_date < date_hired)
);
-- Attendance table (tracks daily time records)
CREATE TABLE attendance (
attendance_id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
date DATE NOT NULL,
time_in TIME,
time_out TIME,
total_hours DECIMAL(5,2) GENERATED ALWAYS AS (
CASE
WHEN time_in IS NOT NULL AND time_out IS NOT NULL
THEN TIMESTAMPDIFF(MINUTE, time_in, time_out) / 60
ELSE 0
END
) STORED,
status ENUM('Present', 'Absent', 'Late', 'Half Day', 'On Leave') DEFAULT 'Present',
late_minutes INT DEFAULT 0,
undertime_minutes INT DEFAULT 0,
remarks TEXT,
FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE
CASCADE,
UNIQUE KEY unique_attendance (employee_id, date),
INDEX idx_date (date),
INDEX idx_status (status),
INDEX idx_employee_date (employee_id, date)
);
-- =============================================
-- 4. FINANCIAL TRANSACTIONS TABLES (3NF)
-- =============================================
-- Employee deductions (normalized - each deduction type is separate row)
CREATE TABLE employee_deductions (
employee_deduction_id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
deduction_type_id INT NOT NULL,
amount DECIMAL(10,2) NOT NULL CHECK (amount >= 0),
effective_date DATE NOT NULL,
end_date DATE,
is_recurring BOOLEAN DEFAULT TRUE,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE
CASCADE,
FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(deduction_type_id),
INDEX idx_employee (employee_id),
INDEX idx_type (deduction_type_id),
INDEX idx_effective_date (effective_date),
CONSTRAINT chk_deduction_dates CHECK (effective_date <= COALESCE(end_date,
effective_date))
);
-- Employee bonuses (normalized - each bonus is separate row)
CREATE TABLE employee_bonuses (
employee_bonus_id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
bonus_type_id INT NOT NULL,
bonus_amount DECIMAL(10,2) NOT NULL CHECK (bonus_amount >= 0),
bonus_date DATE NOT NULL,
description TEXT,
approved_by INT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE
CASCADE,
FOREIGN KEY (bonus_type_id) REFERENCES bonus_types(bonus_type_id),
FOREIGN KEY (approved_by) REFERENCES employees(employee_id),
INDEX idx_employee (employee_id),
INDEX idx_date (bonus_date)
);
-- Overtime requests
CREATE TABLE overtime_requests (
overtime_id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
overtime_date DATE NOT NULL,
hours_rendered DECIMAL(5,2) NOT NULL CHECK (hours_rendered > 0),
rate_multiplier DECIMAL(3,2) DEFAULT 1.5,
approved_by INT,
status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
remarks TEXT,
FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE
CASCADE,
FOREIGN KEY (approved_by) REFERENCES employees(employee_id),
INDEX idx_employee_date (employee_id, overtime_date),
INDEX idx_status (status)
);
-- Leave requests
CREATE TABLE leave_requests (
leave_id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
leave_type VARCHAR(50) NOT NULL,
start_date DATE NOT NULL,
end_date DATE NOT NULL,
total_days INT GENERATED ALWAYS AS (DATEDIFF(end_date, start_date) + 1)
STORED,
reason TEXT,
status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
approved_by INT,
request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
approved_date DATETIME,
FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE
CASCADE,
FOREIGN KEY (approved_by) REFERENCES employees(employee_id),
INDEX idx_dates (start_date, end_date),
INDEX idx_status (status),
CONSTRAINT chk_leave_dates CHECK (start_date <= end_date)
);
-- =============================================
-- 5. PAYROLL PROCESSING TABLES
-- =============================================
-- Payroll period master
CREATE TABLE payroll_periods (
period_id INT AUTO_INCREMENT PRIMARY KEY,
period_code VARCHAR(20) UNIQUE NOT NULL,
period_start DATE NOT NULL,
period_end DATE NOT NULL,
payroll_date DATE NOT NULL,
period_type ENUM('Monthly', 'Semi-Monthly', 'Weekly') DEFAULT 'Monthly',
status ENUM('Draft', 'Processing', 'Completed', 'Posted') DEFAULT 'Draft',
processed_by INT,
processed_date DATETIME,
posted_by INT,
posted_date DATETIME,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (processed_by) REFERENCES employees(employee_id),
FOREIGN KEY (posted_by) REFERENCES employees(employee_id),
UNIQUE KEY unique_period (period_start, period_end),
INDEX idx_status (status),
INDEX idx_payroll_date (payroll_date),
CONSTRAINT chk_period_dates CHECK (period_start <= period_end AND period_end
<= payroll_date)
);
-- Main payroll table (normalized - summarized per period)
CREATE TABLE payroll (
payroll_id INT AUTO_INCREMENT PRIMARY KEY,
employee_id INT NOT NULL,
period_id INT NOT NULL,
-- Salary components
base_salary DECIMAL(10,2) NOT NULL CHECK (base_salary >= 0),
regular_hours DECIMAL(5,2) DEFAULT 160,
regular_pay DECIMAL(10,2) GENERATED ALWAYS AS (base_salary / 160 *
regular_hours) STORED,
-- Overtime components
overtime_hours DECIMAL(5,2) DEFAULT 0,
overtime_pay DECIMAL(10,2) DEFAULT 0 CHECK (overtime_pay >= 0),
-- Bonus components
total_bonus DECIMAL(10,2) DEFAULT 0 CHECK (total_bonus >= 0),
-- Gross salary
gross_salary DECIMAL(10,2) GENERATED ALWAYS AS (
(base_salary / 160 * regular_hours) + overtime_pay + total_bonus
) STORED,
-- Deduction components
total_deductions DECIMAL(10,2) DEFAULT 0 CHECK (total_deductions >= 0),
-- Net salary
net_salary DECIMAL(10,2) GENERATED ALWAYS AS (gross_salary - total_deductions)
STORED,
-- Payroll details
payment_method ENUM('Bank Transfer', 'Cash', 'Check') DEFAULT 'Bank Transfer',
check_number VARCHAR(50),
reference_number VARCHAR(100),
remarks TEXT,
status ENUM('Draft', 'Processed', 'Paid', 'Cancelled') DEFAULT 'Draft',
processed_by INT,
processed_date DATETIME,
paid_by INT,
paid_date DATETIME,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE
CURRENT_TIMESTAMP,
FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE
CASCADE,
FOREIGN KEY (period_id) REFERENCES payroll_periods(period_id),
FOREIGN KEY (processed_by) REFERENCES employees(employee_id),
FOREIGN KEY (paid_by) REFERENCES employees(employee_id),
UNIQUE KEY unique_employee_period (employee_id, period_id),
INDEX idx_status (status),
INDEX idx_period (period_id),
INDEX idx_employee (employee_id),
INDEX idx_payroll_date (payroll_date)
) COMMENT 'Main payroll table - normalized and computed';
-- Payroll deductions detail (line items for each payroll)
CREATE TABLE payroll_deductions_detail (
detail_id INT AUTO_INCREMENT PRIMARY KEY,
payroll_id INT NOT NULL,
deduction_type_id INT NOT NULL,
amount DECIMAL(10,2) NOT NULL CHECK (amount >= 0),
notes VARCHAR(255),
FOREIGN KEY (payroll_id) REFERENCES payroll(payroll_id) ON DELETE CASCADE,
FOREIGN KEY (deduction_type_id) REFERENCES deduction_types(deduction_type_id),
INDEX idx_payroll (payroll_id),
INDEX idx_type (deduction_type_id)
);
-- Payroll bonuses detail (line items for each payroll)
CREATE TABLE payroll_bonuses_detail (
detail_id INT AUTO_INCREMENT PRIMARY KEY,
payroll_id INT NOT NULL,
bonus_type_id INT NOT NULL,
amount DECIMAL(10,2) NOT NULL CHECK (amount >= 0),
notes VARCHAR(255),
FOREIGN KEY (payroll_id) REFERENCES payroll(payroll_id) ON DELETE CASCADE,
FOREIGN KEY (bonus_type_id) REFERENCES bonus_types(bonus_type_id),
INDEX idx_payroll (payroll_id),
INDEX idx_type (bonus_type_id)
);
-- =============================================
-- 6. AUDIT TABLES (For tracking changes)
-- =============================================
CREATE TABLE payroll_audit_log (
log_id INT AUTO_INCREMENT PRIMARY KEY,
table_name VARCHAR(50),
record_id INT,
action VARCHAR(20),
old_data JSON,
new_data JSON,
changed_by INT,
changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (changed_by) REFERENCES employees(employee_id),
INDEX idx_table_record (table_name, record_id),
INDEX idx_changed_at (changed_at)
);
-- =============================================
-- 7. INSERT LOOKUP DATA
-- =============================================
-- Departments
INSERT INTO departments (department_name, description) VALUES
('Human Resources', 'Handles employee relations and recruitment'),
('Finance', 'Manages financial operations and payroll'),
('Information Technology', 'Handles technical infrastructure'),
('Operations', 'Manages day-to-day operations'),
('Sales', 'Handles sales and client relationships'),
('Marketing', 'Handles marketing and promotions'),
('Administration', 'Handles administrative tasks');
-- Positions
INSERT INTO positions (position_name, position_level, base_salary,
overtime_rate_multiplier) VALUES
('Chief Executive Officer', 'Executive', 100000.00, 2.0),
('Department Manager', 'Managerial', 50000.00, 1.75),
('Team Supervisor', 'Supervisory', 35000.00, 1.5),
('Senior Staff', 'Regular', 25000.00, 1.5),
('Staff', 'Regular', 20000.00, 1.5),
('Junior Staff', 'Entry', 15000.00, 1.25),
('Intern', 'Trainee', 10000.00, 1.0);
-- Deduction types (normalized)
INSERT INTO deduction_types (type_code, type_name, is_mandatory, is_percentage,
default_rate) VALUES
('TAX', 'Withholding Tax', TRUE, TRUE, 10.00),
('SSS', 'SSS Contribution', TRUE, FALSE, 500.00),
('PHILHEALTH', 'PhilHealth Contribution', TRUE, FALSE, 300.00),
('PAGIBIG', 'Pag-IBIG Contribution', TRUE, FALSE, 200.00),
('LOAN', 'Employee Loan', FALSE, FALSE, NULL),
('CASH_ADV', 'Cash Advance', FALSE, FALSE, NULL),
('LATE', 'Late Deduction', FALSE, FALSE, NULL);
-- Bonus types
INSERT INTO bonus_types (type_name, description) VALUES
('Performance Bonus', 'Based on employee performance rating'),
('13th Month Pay', 'Mandatory 13th month pay'),
('Christmas Bonus', 'Holiday bonus'),
('Attendance Bonus', 'Perfect attendance incentive'),
('Project Bonus', 'Project completion incentive'),
('Annual Incentive', 'Year-end incentive'),
('Referral Bonus', 'Employee referral incentive');
-- =============================================
-- 8. INSERT SAMPLE DATA (MINIMUM 5 EMPLOYEES)
-- =============================================
-- Generate employee codes and insert employees
INSERT INTO employees (
employee_code, first_name, last_name, middle_name, gender,
birth_date, contact_number, email, address,
bank_account_number, tin_number, sss_number,
philhealth_number, pagibig_number, date_hired,
department_id, position_id, status
) VALUES
('EMP001', 'Juan', 'Dela Cruz', 'Santos', 'Male', '1990-05-15',
'09123456789', 'juan.delacruz@company.com', '123 Manila Street, Manila',
'BAN123456', '123-456-789', '12-3456789-0', '123456789012', '123456789012',
'2023-01-10', 1, 5, 'Active'),
('EMP002', 'Maria', 'Santos', 'Reyes', 'Female', '1985-08-22',
'09123456780', 'maria.santos@company.com', '456 Quezon Avenue, Quezon City',
'BAN123457', '123-456-790', '12-3456790-1', '123456789013', '123456789013',
'2022-05-15', 2, 2, 'Active'),
('EMP003', 'Pedro', 'Reyes', 'Garcia', 'Male', '1995-03-10',
'09123456781', 'pedro.reyes@company.com', '789 Cavite Road, Cavite',
'BAN123458', '123-456-791', '12-3456791-2', '123456789014', '123456789014',
'2024-02-01', 3, 6, 'Active'),
('EMP004', 'Ana', 'Lopez', 'Fernandez', 'Female', '1988-11-30',
'09123456782', 'ana.lopez@company.com', '321 Laguna Blvd, Laguna',
'BAN123459', '123-456-792', '12-3456792-3', '123456789015', '123456789015',
'2023-07-20', 1, 4, 'Active'),
('EMP005', 'Luis', 'Garcia', 'Martinez', 'Male', '1992-09-18',
'09123456783', 'luis.garcia@company.com', '654 Batangas St, Batangas',
'BAN123460', '123-456-793', '12-3456793-4', '123456789016', '123456789016',
'2021-11-11', 3, 4, 'Active'),
('EMP006', 'Carla', 'Mendoza', 'Villanueva', 'Female', '1993-07-25',
'09123456784', 'carla.mendoza@company.com', '987 Pampanga Road, Pampanga',
'BAN123461', '123-456-794', '12-3456794-5', '123456789017', '123456789017',
'2024-01-15', 4, 5, 'Active');
-- Insert employee deductions (normalized - separate rows)
INSERT INTO employee_deductions (employee_id, deduction_type_id, amount,
effective_date, is_recurring) VALUES
(1, 1, 1500.00, '2024-01-01', TRUE), -- Tax
(1, 2, 500.00, '2024-01-01', TRUE), -- SSS
(1, 3, 300.00, '2024-01-01', TRUE), -- PhilHealth
(1, 4, 200.00, '2024-01-01', TRUE), -- Pag-IBIG
(2, 1, 3000.00, '2024-01-01', TRUE),
(2, 2, 800.00, '2024-01-01', TRUE),
(2, 3, 400.00, '2024-01-01', TRUE),
(2, 4, 300.00, '2024-01-01', TRUE),
(3, 1, 1200.00, '2024-01-01', TRUE),
(3, 2, 400.00, '2024-01-01', TRUE),
(3, 3, 250.00, '2024-01-01', TRUE),
(3, 4, 150.00, '2024-01-01', TRUE),
(4, 1, 1800.00, '2024-01-01', TRUE),
(4, 2, 600.00, '2024-01-01', TRUE),
(4, 3, 350.00, '2024-01-01', TRUE),
(4, 4, 200.00, '2024-01-01', TRUE),
(5, 1, 2500.00, '2024-01-01', TRUE),
(5, 2, 700.00, '2024-01-01', TRUE),
(5, 3, 400.00, '2024-01-01', TRUE),
(5, 4, 300.00, '2024-01-01', TRUE);
-- Insert bonuses (normalized)
INSERT INTO employee_bonuses (employee_id, bonus_type_id, bonus_amount,
bonus_date, description) VALUES
(1, 1, 1000.00, '2024-03-15', 'Q1 Performance Bonus'),
(2, 1, 2000.00, '2024-03-15', 'Q1 Performance Bonus'),
(3, 4, 800.00, '2024-03-15', 'Perfect attendance for Q1'),
(4, 1, 1200.00, '2024-03-15', 'Q1 Performance Bonus'),
(5, 6, 1500.00, '2024-03-15', 'Project completion incentive'),
(6, 1, 1000.00, '2024-03-15', 'Q1 Performance Bonus');
-- Insert attendance records
INSERT INTO attendance (employee_id, date, time_in, time_out, status, late_minutes)
VALUES
(1, '2024-03-01', '08:00:00', '17:00:00', 'Present', 0),
(2, '2024-03-01', '08:15:00', '17:05:00', 'Late', 15),
(3, '2024-03-01', '08:00:00', '17:00:00', 'Present', 0),
(4, '2024-03-01', '08:10:00', '17:00:00', 'Late', 10),
(5, '2024-03-01', '08:00:00', '17:30:00', 'Present', 0),
(6, '2024-03-01', '08:20:00', '16:50:00', 'Late', 20);
-- =============================================
-- 9. STORED PROCEDURES (For automated payroll)
-- =============================================
DELIMITER //
-- Procedure to create payroll period
CREATE PROCEDURE CreatePayrollPeriod(
IN p_start DATE,
IN p_end DATE,
IN p_payroll_date DATE,
IN p_type VARCHAR(20)
)
BEGIN
DECLARE v_period_code VARCHAR(20);
SET v_period_code = CONCAT('PERIOD_', DATE_FORMAT(p_start, '%Y%m'), '_',
DATE_FORMAT(p_end, '%d'));
INSERT INTO payroll_periods (period_code, period_start, period_end, payroll_date,
period_type)
VALUES (v_period_code, p_start, p_end, p_payroll_date, p_type);
END //
-- Procedure to process payroll for a period
CREATE PROCEDURE ProcessPayroll(IN p_period_id INT, IN p_processed_by INT)
BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_employee_id INT;
DECLARE v_base_salary DECIMAL(10,2);
DECLARE v_regular_hours DECIMAL(5,2);
DECLARE v_total_bonus DECIMAL(10,2);
DECLARE v_total_deductions DECIMAL(10,2);
DECLARE v_overtime_pay DECIMAL(10,2);
DECLARE cur_employees CURSOR FOR
SELECT employee_id FROM employees WHERE status = 'Active';
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
OPEN cur_employees;
read_loop: LOOP
FETCH cur_employees INTO v_employee_id;
IF done THEN
LEAVE read_loop;
END IF;
-- Get base salary from position
SELECT p.base_salary INTO v_base_salary
FROM employees e
JOIN positions p ON e.position_id = p.position_id
WHERE e.employee_id = v_employee_id;
-- Calculate regular hours (assuming 8 hours/day for working days in period)
SET v_regular_hours = 160; -- Default for monthly
-- Calculate total bonuses for the period
SELECT COALESCE(SUM(bonus_amount), 0) INTO v_total_bonus
FROM employee_bonuses
WHERE employee_id = v_employee_id
AND bonus_date BETWEEN (SELECT period_start FROM payroll_periods WHERE
period_id = p_period_id)
AND (SELECT period_end FROM payroll_periods WHERE period_id =
p_period_id);
-- Calculate total deductions for the period
SELECT COALESCE(SUM(amount), 0) INTO v_total_deductions
FROM employee_deductions
WHERE employee_id = v_employee_id
AND effective_date <= (SELECT period_end FROM payroll_periods WHERE period_id
= p_period_id)
AND (end_date IS NULL OR end_date >= (SELECT period_start FROM
payroll_periods WHERE period_id = p_period_id));
-- Calculate overtime pay
SELECT COALESCE(SUM(hours_rendered * (v_base_salary / 160) * rate_multiplier),
0) INTO v_overtime_pay
FROM overtime_requests
WHERE employee_id = v_employee_id
AND status = 'Approved'
AND overtime_date BETWEEN (SELECT period_start FROM payroll_periods WHERE
period_id = p_period_id)
AND (SELECT period_end FROM payroll_periods WHERE period_id =
p_period_id);
-- Insert into payroll
INSERT INTO payroll (
employee_id, period_id, base_salary, regular_hours,
overtime_pay, total_bonus, total_deductions,
status, processed_by, processed_date
) VALUES (
v_employee_id, p_period_id, v_base_salary, v_regular_hours,
v_overtime_pay, v_total_bonus, v_total_deductions,
'Processed', p_processed_by, NOW()
);
-- Update payroll period status
UPDATE payroll_periods
SET status = 'Completed', processed_by = p_processed_by, processed_date = NOW()
WHERE period_id = p_period_id;
END LOOP;
CLOSE cur_employees;
END //
DELIMITER ;
-- =============================================
-- 10. USEFUL VIEWS
-- =============================================
-- Employee summary view
CREATE VIEW vw_employee_summary AS
SELECT
e.employee_id,
e.employee_code,
CONCAT(e.first_name, ' ', e.last_name) AS full_name,
e.position,
e.base_salary,
d.department_name,
e.date_hired,
e.status,
COUNT(DISTINCT p.payroll_id) AS payroll_processed_count,
COALESCE(AVG(p.net_salary), 0) AS avg_net_salary
FROM employees e
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN payroll p ON e.employee_id = p.employee_id
GROUP BY e.employee_id;
-- Payroll summary view
CREATE VIEW vw_payroll_summary AS
SELECT
p.period_code,
p.payroll_date,
COUNT(DISTINCT pr.employee_id) AS employee_count,
SUM(pr.gross_salary) AS total_gross,
SUM(pr.total_deductions) AS total_deductions,
SUM(pr.net_salary) AS total_net
FROM payroll_periods p
JOIN payroll pr ON p.period_id = pr.period_id
GROUP BY p.period_id;
-- Monthly payroll report
CREATE VIEW vw_monthly_payroll_report AS
SELECT
DATE_FORMAT(p.payroll_date, '%Y-%m') AS month,
COUNT(DISTINCT pr.employee_id) AS employees_paid,
SUM(pr.gross_salary) AS total_gross_payroll,
SUM(pr.total_deductions) AS total_deductions,
SUM(pr.net_salary) AS total_net_payroll
FROM payroll_periods p
JOIN payroll pr ON p.period_id = pr.period_id
GROUP BY DATE_FORMAT(p.payroll_date, '%Y-%m')
ORDER BY month DESC;
-- =============================================
-- 11. SAMPLE QUERIES (Based on your requirements)
-- =============================================
-- VIEW ALL EMPLOYEES
SELECT * FROM vw_employee_summary;
-- SEARCH EMPLOYEE BY NAME
SELECT * FROM employees WHERE first_name = 'Juan';
-- UPDATE SALARY (via position change)
UPDATE employees e
JOIN positions p ON p.position_name = 'Senior Staff'
SET e.position_id = p.position_id
WHERE e.employee_id = 1;
-- DELETE EMPLOYEE (soft delete recommended)
UPDATE employees SET status = 'Resigned' WHERE employee_id = 3;
-- COMPLETE PAYROLL DETAILS (JOIN QUERY)
SELECT
e.employee_code,
CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
pos.position_name,
pos.base_salary,
p.payroll_date,
p.gross_salary,
p.total_deductions,
p.net_salary,
p.status AS payroll_status
FROM employees e
JOIN positions pos ON e.position_id = pos.position_id
JOIN payroll p ON e.employee_id = p.employee_id
WHERE p.status = 'Processed'
ORDER BY p.payroll_date DESC;
-- ORDER BY SALARY (highest first)
SELECT * FROM vw_employee_summary ORDER BY base_salary DESC;
-- GROUP BY POSITION (count employees)
SELECT
pos.position_name,
COUNT(*) AS total_employees,
AVG(e.base_salary) AS avg_salary
FROM employees e
JOIN positions pos ON e.position_id = pos.position_id
WHERE e.status = 'Active'
GROUP BY pos.position_name
ORDER BY avg_salary DESC;
-- =============================================
-- 12. INDEXES FOR PERFORMANCE
-- =============================================
CREATE INDEX idx_employee_status_active ON employees(status, department_id);
CREATE INDEX idx_payroll_status_period ON payroll(status, period_id);
CREATE INDEX idx_attendance_month ON attendance(date, employee_id);
CREATE INDEX idx_deductions_effective ON employee_deductions(effective_date,
employee_id);
-- =============================================
-- End of Database Schema
-- =============================================
