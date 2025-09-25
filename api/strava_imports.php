<?php
header('Content-Type: application/json');
require_once '../config/database.php';
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase();

if ($method === 'GET') {
    // Return all imported Strava IDs
    $stmt = $pdo->query("SELECT strava_id FROM strava_imports ORDER BY imported_at DESC");
    $imports = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($imports);
   
} elseif ($method === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
   
    if (!$data) {
        echo json_encode(['error' => 'Invalid JSON data']);
        exit;
    }
   
    if (isset($data['action']) && $data['action'] === 'insert') {
        // Insert a new Strava import record
        $stravaId = $data['strava_id'] ?? null;
        $activityId = $data['activity_id'] ?? null;
       
        if (!$stravaId || !$activityId) {
            echo json_encode(['error' => 'Missing strava_id or activity_id']);
            exit;
        }
       
        // Check if already exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM strava_imports WHERE strava_id = ?");
        $checkStmt->execute([$stravaId]);
       
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'Already imported', 'exists' => true]);
            exit;
        }
       
        // Insert new record
        $insertStmt = $pdo->prepare("INSERT INTO strava_imports (strava_id, activity_id) VALUES (?, ?)");
        $result = $insertStmt->execute([$stravaId, $activityId]);
       
        if ($result) {
            echo json_encode([
                'id' => $pdo->lastInsertId(),
                'success' => true
            ]);
        } else {
            echo json_encode(['error' => 'Failed to insert record']);
        }
       
    } else {
        // Check if single Strava ID exists
        $stravaId = $data['strava_id'] ?? null;
       
        if (!$stravaId) {
            echo json_encode(['exists' => false, 'error' => 'Missing strava_id']);
            exit;
        }
       
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM strava_imports WHERE strava_id = ?");
        $stmt->execute([$stravaId]);
        $exists = $stmt->fetchColumn() > 0;
       
        echo json_encode(['exists' => $exists]);
    }
   
} else {
    echo json_encode(['error' => 'Method not supported']);
}