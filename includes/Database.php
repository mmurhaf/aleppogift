<?php
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function __construct() {
        $this->connect();
    }

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());

            $message = (defined('IS_PRODUCTION') && IS_PRODUCTION)
                ? 'Service temporarily unavailable. Please try again later.'
                : 'Database connection error: ' . $exception->getMessage();
            $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
            if (preg_match('#/ajax/#', $script)) {
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(500);
                }
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
            die($message);
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);

        if (!empty($params)) {
            // Check if we have positional parameters (numeric keys) or named parameters
            // Use reset() and key() for PHP compatibility instead of array_key_first()
            reset($params);
            $first_key = key($params);
            
            if (is_int($first_key)) {
                // Positional parameters - use execute() directly
                $stmt->execute($params);
            } else {
                // Named parameters - use bindValue
                foreach ($params as $key => $value) {
                    $param = (strpos($key, ':') === 0) ? $key : ':' . $key;
                    if ($value === null) {
                        $stmt->bindValue($param, null, PDO::PARAM_NULL);
                    } elseif (is_bool($value)) {
                        $stmt->bindValue($param, $value ? 1 : 0, PDO::PARAM_INT);
                    } elseif (is_int($value)) {
                        $stmt->bindValue($param, $value, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue($param, (string)$value, PDO::PARAM_STR);
                    }
                }
                $stmt->execute();
            }
        } else {
            $stmt->execute();
        }

        return $stmt;
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollBack() {
        return $this->conn->rollBack();
    }

    // ✅ Add this method to allow PDO access directly
    public function getPdo() {
        return $this->conn;
    }

    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Execute a query and fetch a single row
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for the query
     * @return array|false Single row as associative array or false
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute a query and fetch all rows
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for the query
     * @return array All rows as associative arrays
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
