<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDatabase();
    
    if ($method === 'GET') {
        getImportedIds($pdo);
    } elseif ($method === 'POST') {
        checkIfImported($pdo);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function getImportedIds($pdo) {
    $stmt = $pdo->query("SELECT strava_id FROM strava_imports");
    $imports = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($imports);
}

function checkIfImported($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $stravaId = $data['strava_id'] ?? null;
    
    if (!$stravaId) {
        http_response_code(400);
        echo json_encode(['error' => 'Strava ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM strava_imports WHERE strava_id = ?");
    $stmt->execute([$stravaId]);
    $exists = $stmt->fetchColumn() > 0;
    
    echo json_encode(['exists' => $exists]);
}
?>