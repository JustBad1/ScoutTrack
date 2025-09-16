<?php
require_once '../config/database.php'; // database connection

$method = $_SERVER['REQUEST_METHOD']; 
$pdo = getDatabase(); // open DB connection

switch ($method) {
    case 'GET':
        // return all imported GPX ids
        $stmt = $pdo->query("SELECT gpx_id FROM gpx_imports");
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true); // decode JSON

        if (isset($data['action']) && $data['action'] === 'insert') {
            // insert a new GPX import
            $stmt = $pdo->prepare("INSERT INTO gpx_imports (gpx_id, activity_id) VALUES (?, ?)");
            $stmt->execute([$data['gpx_id'], $data['activity_id']]);
            echo json_encode(['id' => $pdo->lastInsertId()]);
        } else {
            // check if GPX already imported
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM gpx_imports WHERE gpx_id = ?");
            $stmt->execute([$data['gpx_id']]);
            echo json_encode(['exists' => $stmt->fetchColumn() > 0]);
        }
        break;

    default:
        // method not supported
        echo json_encode([]);
}
