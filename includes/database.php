<?php
// database.php - Reusable Database Connection Class with getDBCredentials()
// ==========================================================================

class Database {
    private static $instances = [];
    private $connection;
    private $database;
    private $host;
    private $user;
    private $pass;
    
    private function __construct($db_name) {
        $this->database = $db_name;
        
        // Get credentials for this specific database using getDBCredentials()
        $credentials = getDBCredentials($db_name);
        $this->host = $credentials['host'];
        $this->user = $credentials['user'];
        $this->pass = $credentials['pass'];
        
        $this->connect();
    }
    
    public static function getInstance($db_name) {
        if (!isset(self::$instances[$db_name])) {
            self::$instances[$db_name] = new self($db_name);
        }
        return self::$instances[$db_name];
    }
    
    private function connect() {
        $this->connection = new mysqli($this->host, $this->user, $this->pass, $this->database);
        
        if ($this->connection->connect_error) {
            throw new Exception("Database connection failed ({$this->database}): " . $this->connection->connect_error);
        }
        
        // Set connection settings
        $this->connection->set_charset(DB_CHARSET);
        $this->connection->query("SET SESSION wait_timeout=28800");
        $this->connection->query("SET SESSION interactive_timeout=28800");
    }
    
    public function getConnection() {
        // Reconnect if connection is lost
        if (!$this->connection || !$this->connection->ping()) {
            $this->connect();
        }
        return $this->connection;
    }
    
    public function query($sql) {
        $conn = $this->getConnection();
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error . " - SQL: " . $sql);
        }
        return $result;
    }
    
    public function preparedQuery($sql, $params = []) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_double($param)) $types .= 'd';
                else $types .= 's';
            }
            
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
    
    public function escape($value) {
        $conn = $this->getConnection();
        if ($value === null) return 'NULL';
        return "'" . $conn->real_escape_string($value) . "'";
    }
    
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }
    
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    public function commit() {
        $this->connection->commit();
    }
    
    public function rollback() {
        $this->connection->rollback();
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            unset(self::$instances[$this->database]);
        }
    }
    
    public static function closeAll() {
        foreach (self::$instances as $instance) {
            $instance->close();
        }
        self::$instances = [];
    }
    
    // Helper method to get table columns
    public function getTableColumns($tableName) {
        $columns = [];
        $result = $this->query("SHOW COLUMNS FROM $tableName");
        
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        return $columns;
    }
    
    // Debug method to show current connection info
    public function getConnectionInfo() {
        return [
            'database' => $this->database,
            'host' => $this->host,
            'user' => $this->user,
            'connected' => $this->connection ? $this->connection->ping() : false
        ];
    }
}
?>