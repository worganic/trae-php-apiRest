<?php
require_once 'TaskController.php';
require_once 'db.php';
require_once 'ResponseHandler.php';

// Initialiser la base de données
$database = Database::getInstance();
$database->initializeDatabase();

// Gérer les requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

// Analyser l'URL de la requête
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// Déterminer la ressource demandée (par défaut 'tasks')
$resource = isset($uri[0]) && !empty($uri[0]) ? $uri[0] : null;
$id = isset($uri[1]) && is_numeric($uri[1]) ? (int)$uri[1] : null;

// Récupérer la liste des tables disponibles dans la base de données
$pdo = Database::getInstance()->getPdo();
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Vérifier si la ressource est valide (existe dans la base de données ou est null)
if ($resource !== null && !in_array($resource, $tables)) {
    ResponseHandler::notFound("Ressource '$resource' non trouvée");
}

// Créer le contrôleur
$controller = new TaskController();

// Router les requêtes
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($resource === null) {
        // Page d'accueil avec documentation et liste des tables
        $endpointsInfo = [];
        
        // Ajouter les endpoints pour chaque table, organisés par table
        foreach ($tables as $table) {
            $endpointsInfo[$table] = [
                "GET /$table" => "Liste tous les éléments",
                "GET /$table/{id}" => "Récupère un élément spécifique",
                "POST /$table" => "Crée un nouvel élément",
                "PUT /$table/{id}" => "Met à jour un élément existant",
                "PATCH /$table/{id}" => "Met à jour partiellement un élément",
                "DELETE /$table/{id}" => "Supprime un élément"
            ];
        }
        
        ResponseHandler::json([
            'message' => 'Bienvenue sur l\'API REST PHP7',
            'database' => [
                'name' => $pdo->query("SELECT DATABASE()")->fetchColumn(),
                'tables' => $tables
            ],
            'endpoints' => $endpointsInfo
        ]);
    } else if ($resource === 'tasks') {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $controller->getById($id);
                } else {
                    $controller->getAll();
                }
                break;
                
            case 'POST':
                if ($id) {
                    ResponseHandler::error("Impossible de créer une tâche avec un ID spécifié", 400);
                }
                $controller->create();
                break;
                
            case 'PUT':
                if (!$id) {
                    ResponseHandler::error("ID de tâche requis pour la mise à jour", 400);
                }
                $controller->update($id);
                break;
                
            case 'PATCH':
                if (!$id) {
                    ResponseHandler::error("ID de tâche requis pour la mise à jour partielle", 400);
                }
                $controller->partialUpdate($id);
                break;
                
            case 'DELETE':
                if (!$id) {
                    ResponseHandler::error("ID de tâche requis pour la suppression", 400);
                }
                $controller->delete($id);
                break;
                
            default:
                ResponseHandler::error("Méthode non supportée", 405);
        }
    } else {
        // Gestion générique pour toutes les autres tables
        switch ($method) {
            case 'GET':
                if ($id) {
                    // Récupérer un élément spécifique
                    $stmt = $pdo->prepare("SELECT * FROM $resource WHERE id = ?");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch();
                    
                    if (!$item) {
                        ResponseHandler::notFound("Élément avec l'ID $id non trouvé dans $resource");
                    }
                    
                    ResponseHandler::json($item);
                } else {
                    // Récupérer tous les éléments
                    $stmt = $pdo->query("SELECT * FROM $resource ORDER BY id DESC");
                    $items = $stmt->fetchAll();
                    ResponseHandler::json($items);
                }
                break;
                
            case 'POST':
                if ($id) {
                    ResponseHandler::error("Impossible de créer un élément avec un ID spécifié", 400);
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (empty($data)) {
                    ResponseHandler::error("Données invalides", 400);
                }
                
                // Construire la requête d'insertion dynamiquement
                $columns = array_keys($data);
                $placeholders = array_fill(0, count($columns), '?');
                
                $sql = "INSERT INTO $resource (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
                
                $id = $pdo->lastInsertId();
                
                // Récupérer l'élément créé
                $stmt = $pdo->prepare("SELECT * FROM $resource WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                
                ResponseHandler::json($item, 201);
                break;
                
            case 'PUT':
                if (!$id) {
                    ResponseHandler::error("ID requis pour la mise à jour", 400);
                }
                
                // Vérifier si l'élément existe
                $checkStmt = $pdo->prepare("SELECT id FROM $resource WHERE id = ?");
                $checkStmt->execute([$id]);
                
                if (!$checkStmt->fetch()) {
                    ResponseHandler::notFound("Élément avec l'ID $id non trouvé dans $resource");
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (empty($data)) {
                    ResponseHandler::error("Données invalides", 400);
                }
                
                // Construire la requête de mise à jour dynamiquement
                $updates = [];
                $values = [];
                
                foreach ($data as $key => $value) {
                    $updates[] = "$key = ?";
                    $values[] = $value;
                }
                
                $values[] = $id; // Ajouter l'ID à la fin pour la clause WHERE
                
                $sql = "UPDATE $resource SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                // Récupérer l'élément mis à jour
                $stmt = $pdo->prepare("SELECT * FROM $resource WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                
                ResponseHandler::json($item);
                break;
                
            case 'PATCH':
                if (!$id) {
                    ResponseHandler::error("ID requis pour la mise à jour partielle", 400);
                }
                
                // Vérifier si l'élément existe
                $checkStmt = $pdo->prepare("SELECT * FROM $resource WHERE id = ?");
                $checkStmt->execute([$id]);
                $item = $checkStmt->fetch();
                
                if (!$item) {
                    ResponseHandler::notFound("Élément avec l'ID $id non trouvé dans $resource");
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (empty($data)) {
                    ResponseHandler::error("Données invalides", 400);
                }
                
                // Construire la requête de mise à jour dynamiquement
                $updates = [];
                $values = [];
                
                foreach ($data as $key => $value) {
                    $updates[] = "$key = ?";
                    $values[] = $value;
                }
                
                $values[] = $id; // Ajouter l'ID à la fin pour la clause WHERE
                
                $sql = "UPDATE $resource SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                // Récupérer l'élément mis à jour
                $stmt = $pdo->prepare("SELECT * FROM $resource WHERE id = ?");
                $stmt->execute([$id]);
                $updatedItem = $stmt->fetch();
                
                ResponseHandler::json($updatedItem);
                break;
                
            case 'DELETE':
                if (!$id) {
                    ResponseHandler::error("ID requis pour la suppression", 400);
                }
                
                // Vérifier si l'élément existe
                $checkStmt = $pdo->prepare("SELECT id FROM $resource WHERE id = ?");
                $checkStmt->execute([$id]);
                
                if (!$checkStmt->fetch()) {
                    ResponseHandler::notFound("Élément avec l'ID $id non trouvé dans $resource");
                }
                
                $stmt = $pdo->prepare("DELETE FROM $resource WHERE id = ?");
                $stmt->execute([$id]);
                
                ResponseHandler::json(null, 204);
                break;
                
            default:
                ResponseHandler::error("Méthode non supportée", 405);
        }
    }
} catch (Exception $e) {
    ResponseHandler::error($e->getMessage(), 500);
}