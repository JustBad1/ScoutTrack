<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

try {
    $pdo = getDatabase();
    
    switch($method) {
        case 'GET':
            if ($id) {
                getSingleActivity($pdo, $id);
            } else {
                getAllActivities($pdo);
            }
            break;
        case 'POST':
            createActivity($pdo);
            break;
        case 'PUT':
            if (!$id) {
                throw new Exception('Activity ID required for update');
            }
            updateActivity($pdo, $id);
            break;
        case 'DELETE':
            if (!$id) {
                throw new Exception('Activity ID required for delete');
            }
            deleteActivity($pdo, $id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getAllActivities($pdo) {
    $stmt = $pdo->query("
        SELECT * FROM activities 
        ORDER BY date DESC, created_at DESC
    ");
    $activities = $stmt->fetchAll();
    
    // Convert data types
    foreach($activities as &$activity) {
        $activity['is_scouting_activity'] = (bool)$activity['is_scouting_activity'];
        $activity['duration'] = (float)$activity['duration'];
        $activity['distance'] = (float)$activity['distance'];
        $activity['elevation'] = (int)$activity['elevation'];
        $activity['nights'] = (int)$activity['nights'];
    }
    
    echo json_encode($activities);
}

function getSingleActivity($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
    $stmt->execute([$id]);
    $activity = $stmt->fetch();
    
    if (!$activity) {
        http_response_code(404);
        echo json_encode(['error' => 'Activity not found']);
        return;
    }
    
    // Convert data types
    $activity['is_scouting_activity'] = (bool)$activity['is_scouting_activity'];
    $activity['duration'] = (float)$activity['duration'];
    $activity['distance'] = (float)$activity['distance'];
    $activity['elevation'] = (int)$activity['elevation'];
    $activity['nights'] = (int)$activity['nights'];
    
    echo json_encode($activity);
}

function createActivity($pdo) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }
    
    // Validate required fields
    $required = ['name', 'date', 'type', 'duration', 'distance', 'elevation', 'nights', 
                'role', 'category', 'weather', 'start_location', 'end_location', 'comments'];
    
    foreach($required as $field) {
        if (!isset($data[$field]) && $data[$field] !== 0 && $data[$field] !== '0') {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO activities (
            name, date, type, duration, distance, elevation, nights, 
            role, category, weather, start_location, end_location, 
            comments, is_scouting_activity, source, source_id
        ) VALUES (
            :name, :date, :type, :duration, :distance, :elevation, :nights,
            :role, :category, :weather, :start_location, :end_location,
            :comments, :is_scouting_activity, :source, :source_id
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
        ':is_scouting_activity' => $data['is_scouting_activity'] ?? false,
        ':source' => $data['source'] ?? 'manual',
        ':source_id' => $data['source_id'] ?? null
    ]);
    
    $activityId = $pdo->lastInsertId();
    
    // If this is a Strava import, record it
    if (isset($data['strava_id'])) {
        $stravaStmt = $pdo->prepare("
            INSERT INTO strava_imports (strava_id, activity_id) 
            VALUES (:strava_id, :activity_id)
        ");
        $stravaStmt->execute([
            ':strava_id' => $data['strava_id'],
            ':activity_id' => $activityId
        ]);
    }
    
    echo json_encode(['id' => $activityId, 'message' => 'Activity created successfully']);
}

function updateActivity($pdo, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE activities SET 
            name = :name, date = :date, type = :type, duration = :duration, 
            distance = :distance, elevation = :elevation, nights = :nights,
            role = :role, category = :category, weather = :weather,
            start_location = :start_location, end_location = :end_location,
            comments = :comments, is_scouting_activity = :is_scouting_activity
        WHERE id = :id
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
        ':is_scouting_activity' => $data['is_scouting_activity'] ?? false
    ]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['message' => 'Activity updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Activity not found or no changes made']);
    }
}

function deleteActivity($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM activities WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['message' => 'Activity deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Activity not found']);
    }
}
?>