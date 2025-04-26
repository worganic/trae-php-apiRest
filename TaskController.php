<?php
require_once 'db.php';
require_once 'ResponseHandler.php';

class TaskController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }
    
    // Récupérer toutes les tâches
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM tasks ORDER BY id DESC");
        $tasks = $stmt->fetchAll();
        ResponseHandler::json($tasks);
    }
    
    // Récupérer une tâche par ID
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            ResponseHandler::notFound("Tâche avec l'ID $id non trouvée");
        }
        
        ResponseHandler::json($task);
    }
    
    // Créer une nouvelle tâche
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation basique
        if (!isset($data['title']) || empty($data['title'])) {
            ResponseHandler::error("Le titre est obligatoire", 400);
        }
        
        $sql = "INSERT INTO tasks (title, description, completed) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        $title = $data['title'];
        $description = $data['description'] ?? '';
        $completed = isset($data['completed']) ? (bool)$data['completed'] : false;
        
        $stmt->execute([$title, $description, $completed]);
        $id = $this->db->lastInsertId();
        
        // Récupérer la tâche créée
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        
        ResponseHandler::json($task, 201);
    }
    
    // Mettre à jour une tâche (PUT)
    public function update($id) {
        // Vérifier si la tâche existe
        $checkStmt = $this->db->prepare("SELECT id FROM tasks WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if (!$checkStmt->fetch()) {
            ResponseHandler::notFound("Tâche avec l'ID $id non trouvée");
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation basique
        if (!isset($data['title']) || empty($data['title'])) {
            ResponseHandler::error("Le titre est obligatoire", 400);
        }
        
        $sql = "UPDATE tasks SET title = ?, description = ?, completed = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        $title = $data['title'];
        $description = $data['description'] ?? '';
        $completed = isset($data['completed']) ? (bool)$data['completed'] : false;
        
        $stmt->execute([$title, $description, $completed, $id]);
        
        // Récupérer la tâche mise à jour
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        
        ResponseHandler::json($task);
    }
    
    // Mettre à jour partiellement une tâche (PATCH)
    public function partialUpdate($id) {
        // Vérifier si la tâche existe
        $checkStmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ?");
        $checkStmt->execute([$id]);
        $task = $checkStmt->fetch();
        
        if (!$task) {
            ResponseHandler::notFound("Tâche avec l'ID $id non trouvée");
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Construire la requête dynamiquement en fonction des champs fournis
        $fields = [];
        $values = [];
        
        if (isset($data['title'])) {
            if (empty($data['title'])) {
                ResponseHandler::error("Le titre ne peut pas être vide", 400);
            }
            $fields[] = "title = ?";
            $values[] = $data['title'];
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $values[] = $data['description'];
        }
        
        if (isset($data['completed'])) {
            $fields[] = "completed = ?";
            $values[] = (bool)$data['completed'];
        }
        
        if (empty($fields)) {
            ResponseHandler::error("Aucun champ à mettre à jour", 400);
        }
        
        $sql = "UPDATE tasks SET " . implode(", ", $fields) . " WHERE id = ?";
        $values[] = $id;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        // Récupérer la tâche mise à jour
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $updatedTask = $stmt->fetch();
        
        ResponseHandler::json($updatedTask);
    }
    
    // Supprimer une tâche
    public function delete($id) {
        // Vérifier si la tâche existe
        $checkStmt = $this->db->prepare("SELECT id FROM tasks WHERE id = ?");
        $checkStmt->execute([$id]);
        
        if (!$checkStmt->fetch()) {
            ResponseHandler::notFound("Tâche avec l'ID $id non trouvée");
        }
        
        $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        
        ResponseHandler::json(null, 204);
    }
}