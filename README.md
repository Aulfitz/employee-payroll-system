# Employee Payroll System
CCS110 Information Management 1 — Final Project
University of Cabuyao (Pamantasan ng Cabuyao)

## How to Run

### Requirements
- XAMPP, WAMP, or Laragon
- PHP 8.0+, MySQL 5.7+

### Step 1 — Copy project folder
Place the `payroll_system` folder inside:
- XAMPP: `C:/xampp/htdocs/payroll_system`
- WAMP: `C:/wamp64/www/payroll_system`
- Laragon: `C:/laragon/www/payroll_system`

### Step 2 — Import the database
1. Go to `http://localhost/phpmyadmin`
2. Click New → name it `payroll_system` → Create
3. Click Import tab → Choose File → select `payroll_system.sql` → Go
4. After that, import `users_setup.sql` the same way

### Step 3 — Configure DB connection
Open `includes/db.php` and `includes/db_connection.php`
If your MySQL has a password, update:
`define('DB_PASS', '');`  ← put your password here

### Step 4 — Open the system
Go to: http://localhost/payroll_system

## Login Credentials
| Username | Password | Role          |
|----------|----------|---------------|
| admin    | admin123 | Administrator |
| hr       | hr1234   | HR Manager    |

## Folder Structure
```
payroll_system/
├── index.php                  ← Login page
├── payroll_system.sql         ← Main database schema + data
├── users_setup.sql            ← Users table
├── sql_query_demo.sql         ← SQL query demonstrations
├── README.md
├── includes/
│   ├── auth.php               ← Session/login helpers
│   ├── db.php                 ← DB connection
│   ├── db_connection.php      ← DB connection (getConnection)
│   ├── header.php             ← Shared navigation
│   └── footer.php             ← Shared footer
├── pages/
│   ├── dashboard.php          ← Stats and summary
│   ├── employees.php          ← Employee CRUD + search
│   ├── payroll.php            ← Payroll processing
│   ├── attendance.php         ← Attendance tracking
│   ├── reports.php            ← SQL reports
│   └── logout.php
├── api/
│   └── employees_api.php      ← REST API endpoints
└── assets/
```

## Features
1. User Authentication — SHA256 hashed passwords, session-based login
2. Employee Management — Add, edit, search, soft-delete employees
3. Payroll Processing — Compute and track payroll per period
4. Attendance Tracking — Daily time-in/out records per employee
5. Reports Module — SELECT, JOIN, GROUP BY, ORDER BY, WHERE queries
6. Dashboard — Live statistics pulled from the database
