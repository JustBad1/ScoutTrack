<?php
require_once '../config/database.php'; // DB connection helper

$method = $_SERVER['REQUEST_METHOD']; // which HTTP method?
$pdo    = getDatabase(); // open DB

if ($method === 'GET') {
    // list of all imported Strava IDs
    $stmt = $pdo->query("SELECT strava_id FROM strava_imports");
    $imports = $stmt->fetchAll(PDO::FETCH_COLUMN); // flat array
    echo json_encode($imports);

} elseif ($method === 'POST') {
    // check single Strava ID exists
    $data = json_decode(file_get_contents('php://input'), true);
    $stravaId = $data['strava_id'] ?? null;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM strava_imports WHERE strava_id = ?");
    $stmt->execute([$stravaId]);
    $exists = $stmt->fetchColumn() > 0;

    echo json_encode(['exists' => $exists]); // true/false

} else {
    // unsupported method
    echo json_encode([]);
}
