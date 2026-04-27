<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/auth.php';
require_once '../includes/db_connection.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$conn   = getConnection();

switch ($method) {

    case 'GET':
        if (isset($_GET['id'])) {
            $id  = intval($_GET['id']);
            $sql = "SELECT e.*, d.department_name, p.position_name, p.base_salary
                    FROM employees e
                    JOIN departments d ON e.department_id = d.department_id
                    JOIN positions   p ON e.position_id   = p.position_id
                    WHERE e.employee_id = $id";
            $result = $conn->query($sql);
            echo json_encode($result->fetch_assoc());
        } else {
            $sql = "SELECT e.*, d.department_name, p.position_name, p.base_salary
                    FROM employees e
                    JOIN departments d ON e.department_id = d.department_id
                    JOIN positions   p ON e.position_id   = p.position_id
                    WHERE e.status = 'Active'
                    ORDER BY e.employee_id DESC";
            $result    = $conn->query($sql);
            $employees = [];
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
            echo json_encode($employees);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['employee_id'])) {
            $employee_code = 'EMP' . rand(1000, 9999);
            $sql  = "INSERT INTO employees
                        (employee_code, first_name, last_name, middle_name,
                         email, contact_number, gender, birth_date, date_hired,
                         department_id, position_id, address, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssiis",
                $employee_code,         $data['first_name'],    $data['last_name'],
                $data['middle_name'],   $data['email'],         $data['contact_number'],
                $data['gender'],        $data['birth_date'],    $data['date_hired'],
                $data['department_id'], $data['position_id'],   $data['address']
            );
            echo $stmt->execute()
                ? json_encode(['success' => true,  'message' => 'Employee added successfully'])
                : json_encode(['success' => false, 'message' => $stmt->error]);
            $stmt->close();
        } else {
            $sql  = "UPDATE employees SET
                        first_name=?, last_name=?, middle_name=?,
                        email=?, contact_number=?, gender=?, birth_date=?,
                        date_hired=?, department_id=?, position_id=?, address=?
                     WHERE employee_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssiis",
                $data['first_name'],    $data['last_name'],   $data['middle_name'],
                $data['email'],         $data['contact_number'], $data['gender'],
                $data['birth_date'],    $data['date_hired'],
                $data['department_id'], $data['position_id'],
                $data['address'],       $data['employee_id']
            );
            echo $stmt->execute()
                ? json_encode(['success' => true,  'message' => 'Employee updated successfully'])
                : json_encode(['success' => false, 'message' => $stmt->error]);
            $stmt->close();
        }
        break;

    case 'DELETE':
        $id  = intval($_GET['id']);
        $sql = "UPDATE employees SET status = 'Resigned' WHERE employee_id = $id";
        echo $conn->query($sql)
            ? json_encode(['success' => true,  'message' => 'Employee removed successfully'])
            : json_encode(['success' => false, 'message' => $conn->error]);
        break;
}

$conn->close();
?>
