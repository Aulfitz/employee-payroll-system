-- =============================================
-- SQL QUERY DEMONSTRATION
-- CCS110 — Employee Payroll System
-- =============================================
USE payroll_system;

-- ── SELECT ───────────────────────────────────────────────────────────────────

-- Basic SELECT: all active employees
SELECT * FROM employees WHERE status = 'Active';

-- SELECT with JOIN: employee name, department, position, salary
SELECT
    e.employee_code,
    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
    d.department_name,
    p.position_name,
    p.base_salary
FROM employees e
JOIN departments d ON e.department_id = d.department_id
JOIN positions  p ON e.position_id   = p.position_id
WHERE e.status = 'Active'
ORDER BY p.base_salary DESC;

-- SELECT with GROUP BY: headcount and average salary per department
SELECT
    d.department_name,
    COUNT(e.employee_id)  AS total_employees,
    AVG(p.base_salary)    AS average_salary
FROM departments d
LEFT JOIN employees e ON d.department_id = e.department_id AND e.status = 'Active'
LEFT JOIN positions p ON e.position_id   = p.position_id
GROUP BY d.department_id
ORDER BY total_employees DESC;

-- ── INSERT ───────────────────────────────────────────────────────────────────

INSERT INTO employees
    (employee_code, first_name, last_name, email, date_hired, department_id, position_id, status)
VALUES
    ('EMP010', 'Test', 'User', 'test.user@company.com', CURDATE(), 1, 5, 'Active');

-- ── UPDATE ───────────────────────────────────────────────────────────────────

-- Update employee contact number
UPDATE employees
SET contact_number = '09999999999'
WHERE employee_code = 'EMP010';

-- Mark payroll as Paid
UPDATE payroll
SET status = 'Paid', paid_date = NOW()
WHERE payroll_id = 1;

-- ── DELETE ───────────────────────────────────────────────────────────────────

-- Hard DELETE (permanent — for demo only)
DELETE FROM employees WHERE employee_code = 'EMP010';

-- Soft DELETE (recommended — preserves history)
UPDATE employees SET status = 'Resigned' WHERE employee_id = 6;

-- ── WHERE ────────────────────────────────────────────────────────────────────

SELECT * FROM attendance
WHERE status = 'Late' AND late_minutes > 10
ORDER BY date DESC;

-- ── ORDER BY ─────────────────────────────────────────────────────────────────

SELECT
    CONCAT(first_name, ' ', last_name) AS full_name,
    date_hired
FROM employees
WHERE status = 'Active'
ORDER BY date_hired ASC;

-- ── GROUP BY ─────────────────────────────────────────────────────────────────

SELECT
    pp.period_code,
    COUNT(DISTINCT pr.employee_id) AS employees_paid,
    SUM(pr.gross_salary)           AS total_gross,
    SUM(pr.total_deductions)       AS total_deductions,
    SUM(pr.net_salary)             AS total_net_pay
FROM payroll_periods pp
JOIN payroll pr ON pp.period_id = pr.period_id
GROUP BY pp.period_id
ORDER BY pp.payroll_date DESC;

-- ── JOIN ─────────────────────────────────────────────────────────────────────

SELECT
    a.date,
    e.employee_code,
    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
    a.time_in,
    a.time_out,
    a.total_hours,
    a.late_minutes,
    a.status
FROM attendance a
JOIN employees e ON a.employee_id = e.employee_id
WHERE a.date = CURDATE()
ORDER BY a.status, e.first_name;
