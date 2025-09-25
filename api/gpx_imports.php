<?php
require_once '../config/database.php'; // database connection
$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase(); // open DB connection

if ($method === 'GET') {
    // return all imported GPX ids
    $stmt = $pdo->query("SELECT gpx_id FROM gpx_imports");
    $imports = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($imports);
    
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true); // decode JSON
    
    if (isset($data['action']) && $data['action'] === 'insert') {
        // insert a new GPX import
        $gpxId = $data['gpx_id'] ?? null;
        $activityId = $data['activity_id'] ?? null;
       
        if (!$gpxId || !$activityId) {
            echo json_encode(['error' => 'Missing gpx_id or activity_id']);
            return;
        }
       
        // Check if already exists first
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM gpx_imports WHERE gpx_id = ?");
        $checkStmt->execute([$gpxId]);
       
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['error' => 'Already imported', 'exists' => true]);
            return;
        }
       
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO gpx_imports (gpx_id, activity_id) VALUES (?, ?)");
        $stmt->execute([$gpxId, $activityId]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'success' => true]);
        
    } else {
        // check if GPX already imported (original functionality)
        $gpxId = $data['gpx_id'] ?? null;
       
        if (!$gpxId) {
            echo json_encode(['exists' => false, 'error' => 'Missing gpx_id']);
            return;
        }
       
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM gpx_imports WHERE gpx_id = ?");
        $stmt->execute([$gpxId]);
        echo json_encode(['exists' => $stmt->fetchColumn() > 0]);
    }
    
} else {
    // method not supported
    echo json_encode(['error' => 'Method not supported']);
}