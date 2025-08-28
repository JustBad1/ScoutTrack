<?php
/**
 * Database Configuration
 */

class Database {
    private $host = 'localhost';
    private $dbname = 'outdoor_logbook';
    private $username = 'root';
    private $password = '';
    private $pdo = null;

    public function __construct() {
        // Allow environment variables to override defaults
        $this->host = $_ENV['DB_HOST'] ?? $this->host;
        $this->dbname = $_ENV['DB_NAME'] ?? $this->dbname;
        $this->username = $_ENV['DB_USER'] ?? $this->username;
        $this->password = $_ENV['DB_PASS'] ?? $this->password;
    }

    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch(PDOException $e) {
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
        
        return $this->pdo;
    }

    public function disconnect() {
        $this->pdo = null;
    }
}

// Global function for easy access
function getDatabase() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database->connect();
}
?>