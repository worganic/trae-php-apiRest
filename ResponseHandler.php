<?php
class ResponseHandler {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($data !== null) {
            echo json_encode($data);
        }
        exit;
    }
    
    public static function error($message, $statusCode = 400) {
        self::json(['error' => $message], $statusCode);
    }
    
    public static function notFound($message = 'Ressource non trouvée') {
        self::error($message, 404);
    }
}