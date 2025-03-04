<?php
class Database {
    // Database credentials
    private $host = "localhost";
    private $db_name = "academic_progress_db";
    private $username = "root";
    private $password = "";
    private $conn = null;
    private static $instance = null;

    // Constructor
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }

    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get database connection
    public function getConnection() {
        return $this->conn;
    }

    // Begin transaction
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    // Commit transaction
    public function commit() {
        return $this->conn->commit();
    }

    // Rollback transaction
    public function rollback() {
        return $this->conn->rollBack();
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserialization of the instance
    private function __wakeup() {}

    // Execute a query and return the result
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            throw new Exception("Error al ejecutar la consulta");
        }
    }

    // Get a single record
    public function getOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get multiple records
    public function getAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Insert a record and return the last inserted ID
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->conn->lastInsertId();
    }

    // Update records and return number of affected rows
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Delete records and return number of affected rows
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Check if a record exists
    public function exists($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn() > 0;
    }

    // Sanitize input
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }

    // Format date for MySQL
    public static function formatDate($date) {
        return date('Y-m-d H:i:s', strtotime($date));
    }
}

// Error handling function
function handleError($error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Ha ocurrido un error en el servidor'
    ]);
    exit;
}

// Set error handler
set_exception_handler('handleError');
?>
