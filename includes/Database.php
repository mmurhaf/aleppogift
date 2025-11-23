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
            die("Database connection error: " . $exception->getMessage());
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
                    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue($param, $value, $type);
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
}
?>
