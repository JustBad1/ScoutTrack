<?php
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? null;

$pdo = getDatabase();

switch ($method) {
    case 'GET': //read activity
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            echo json_encode($row ?: []);
        } else {
            $stmt = $pdo->query("SELECT * FROM activities ORDER BY date DESC, created_at DESC");
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST': //create activity
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("
            INSERT INTO activities (name, date, type, duration, distance, elevation, nights,
                role, category, weather, start_location, end_location, comments,
                is_scouting_activity, source, source_id
            ) VALUES (
                :name, :date, :type, :duration, :distance, :elevation, :nights,
                :role, :category, :weather, :start_location, :end_location, :comments,
                :is_scouting_activity, :source, :source_id
            )
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':date' => $data['date'],
            ':type' => $data['type'],
            ':duration' => $data['duration'],
            ':distance' => $data['distance'],
            ':elevation' => $data['elevation'],
            ':nights' => $data['nights'],
            ':role' => $data['role'],
            ':category' => $data['category'],
            ':weather' => $data['weather'],
            ':start_location' => $data['start_location'],
            ':end_location' => $data['end_location'],
            ':comments' => $data['comments'],
            ':is_scouting_activity' => $data['is_scouting_activity'] ?? 0,
            ':source' => $data['source'] ?? 'manual',
            ':source_id' => $data['source_id'] ?? null
        ]);
        echo json_encode(['id' => $pdo->lastInsertId()]);
        break;

    case 'PUT': //update activity
        if (!$id) { echo json_encode([]); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("
            UPDATE activities SET
                name=:name, date=:date, type=:type, duration=:duration,
                distance=:distance, elevation=:elevation, nights=:nights,
                role=:role, category=:category, weather=:weather,
                start_location=:start_location, end_location=:end_location,
                comments=:comments, is_scouting_activity=:is_scouting_activity
            WHERE id=:id
        ");
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':date' => $data['date'],
            ':type' => $data['type'],
            ':duration' => $data['duration'],
            ':distance' => $data['distance'],
            ':elevation' => $data['elevation'],
            ':nights' => $data['nights'],
            ':role' => $data['role'],
            ':category' => $data['category'],
            ':weather' => $data['weather'],
            ':start_location' => $data['start_location'],
            ':end_location' => $data['end_location'],
            ':comments' => $data['comments'],
            ':is_scouting_activity' => $data['is_scouting_activity'] ?? 0
        ]);
        echo json_encode(['updated' => $stmt->rowCount()]);
        break;

    case 'DELETE'://delete activity
        if (!$id) { echo json_encode([]); break; }
        $stmt = $pdo->prepare("DELETE FROM activities WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['deleted' => $stmt->rowCount()]);
        break;

    default:
        echo json_encode([]);
}
