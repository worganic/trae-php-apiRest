<?php
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $config = require_once 'config.php';
        $dbConfig = $config['db'];
        
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPdo() {
        return $this->pdo;
    }
    
    // Méthode pour initialiser la table tasks si elle n'existe pas
    public function initializeDatabase() {
        $sql = "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            completed BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
    }
}