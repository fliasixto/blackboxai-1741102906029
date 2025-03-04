<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

class StudentAPI {
    private $db;
    private $table = "students";

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Get all students with optional filters
    public function getStudents($filters = []) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            // Apply filters if provided
            if (!empty($filters['grade_level'])) {
                $sql .= " AND grade_level = :grade_level";
                $params[':grade_level'] = $filters['grade_level'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (first_name LIKE :search OR last_name LIKE :search OR student_code LIKE :search)";
                $params[':search'] = "%{$filters['search']}%";
            }

            $sql .= " ORDER BY created_at DESC";

            $students = $this->db->getAll($sql, $params);
            
            return [
                'error' => false,
                'message' => 'Estudiantes recuperados exitosamente',
                'data' => $students
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar estudiantes: " . $e->getMessage());
        }
    }

    // Get a single student by ID
    public function getStudent($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = :id";
            $student = $this->db->getOne($sql, [':id' => $id]);

            if (!$student) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Estudiante no encontrado'
                ];
            }

            return [
                'error' => false,
                'message' => 'Estudiante recuperado exitosamente',
                'data' => $student
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar estudiante: " . $e->getMessage());
        }
    }

    // Create a new student
    public function createStudent($data) {
        try {
            // Validate required fields
            $requiredFields = ['student_code', 'first_name', 'last_name', 'email', 'grade_level'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    return [
                        'error' => true,
                        'message' => "El campo {$field} es requerido"
                    ];
                }
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'Formato de email inválido'
                ];
            }

            // Check if student code already exists
            if ($this->db->exists("SELECT 1 FROM {$this->table} WHERE student_code = :code", [':code' => $data['student_code']])) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'El código de estudiante ya existe'
                ];
            }

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $sql = "INSERT INTO {$this->table} (
                student_code, first_name, last_name, email, phone, 
                grade_level, parent_name, parent_email, parent_phone
            ) VALUES (
                :student_code, :first_name, :last_name, :email, :phone,
                :grade_level, :parent_name, :parent_email, :parent_phone
            )";

            $params = [
                ':student_code' => $sanitizedData['student_code'],
                ':first_name' => $sanitizedData['first_name'],
                ':last_name' => $sanitizedData['last_name'],
                ':email' => $sanitizedData['email'],
                ':phone' => $sanitizedData['phone'] ?? null,
                ':grade_level' => $sanitizedData['grade_level'],
                ':parent_name' => $sanitizedData['parent_name'] ?? null,
                ':parent_email' => $sanitizedData['parent_email'] ?? null,
                ':parent_phone' => $sanitizedData['parent_phone'] ?? null
            ];

            $id = $this->db->insert($sql, $params);

            return [
                'error' => false,
                'message' => 'Estudiante creado exitosamente',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al crear estudiante: " . $e->getMessage());
        }
    }

    // Update an existing student
    public function updateStudent($id, $data) {
        try {
            // Check if student exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Estudiante no encontrado'
                ];
            }

            // Validate email if provided
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'Formato de email inválido'
                ];
            }

            // Sanitize input data
            $sanitizedData = Database::sanitize($data);

            $updateFields = [];
            $params = [':id' => $id];

            // Build dynamic update query
            foreach ($sanitizedData as $key => $value) {
                if ($key !== 'id' && $value !== null) {
                    $updateFields[] = "{$key} = :{$key}";
                    $params[":{$key}"] = $value;
                }
            }

            if (empty($updateFields)) {
                http_response_code(400);
                return [
                    'error' => true,
                    'message' => 'No hay campos para actualizar'
                ];
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $updateFields) . " WHERE id = :id";
            
            $this->db->update($sql, $params);

            return [
                'error' => false,
                'message' => 'Estudiante actualizado exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al actualizar estudiante: " . $e->getMessage());
        }
    }

    // Delete a student
    public function deleteStudent($id) {
        try {
            // Check if student exists
            if (!$this->db->exists("SELECT 1 FROM {$this->table} WHERE id = :id", [':id' => $id])) {
                http_response_code(404);
                return [
                    'error' => true,
                    'message' => 'Estudiante no encontrado'
                ];
            }

            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $this->db->delete($sql, [':id' => $id]);

            return [
                'error' => false,
                'message' => 'Estudiante eliminado exitosamente'
            ];
        } catch (Exception $e) {
            throw new Exception("Error al eliminar estudiante: " . $e->getMessage());
        }
    }

    // Get student statistics
    public function getStudentStats($id) {
        try {
            // Get average grades
            $gradesSQL = "SELECT 
                AVG(g.grade) as average_grade,
                COUNT(DISTINCT s.id) as total_subjects,
                COUNT(g.id) as total_evaluations
            FROM students st
            LEFT JOIN grades g ON st.id = g.student_id
            LEFT JOIN subjects s ON g.subject_id = s.id
            WHERE st.id = :id";

            $grades = $this->db->getOne($gradesSQL, [':id' => $id]);

            // Get attendance statistics
            $attendanceSQL = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
            FROM attendance
            WHERE student_id = :id";

            $attendance = $this->db->getOne($attendanceSQL, [':id' => $id]);

            return [
                'error' => false,
                'message' => 'Estadísticas recuperadas exitosamente',
                'data' => [
                    'grades' => $grades,
                    'attendance' => $attendance
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("Error al recuperar estadísticas: " . $e->getMessage());
        }
    }
}

// Handle API requests
try {
    $api = new StudentAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            if ($id) {
                if (isset($_GET['stats'])) {
                    $response = $api->getStudentStats($id);
                } else {
                    $response = $api->getStudent($id);
                }
            } else {
                $filters = [
                    'grade_level' => $_GET['grade_level'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];
                $response = $api->getStudents($filters);
            }
            break;

        case 'POST':
            $response = $api->createStudent($data);
            http_response_code(201);
            break;

        case 'PUT':
            if (!$id) {
                throw new Exception("ID de estudiante no proporcionado");
            }
            $response = $api->updateStudent($id, $data);
            break;

        case 'DELETE':
            if (!$id) {
                throw new Exception("ID de estudiante no proporcionado");
            }
            $response = $api->deleteStudent($id);
            break;

        default:
            http_response_code(405);
            $response = [
                'error' => true,
                'message' => 'Método no permitido'
            ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    handleError($e);
}
?>
