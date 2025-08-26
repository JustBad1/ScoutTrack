<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration 
$host = 'localhost';
$dbname = 'outdoor_logbook';
$username = 'root'; 
$password = '';  

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Debug: log what receiving
error_log("Method: $method, Endpoint: $endpoint");

// Route requests
switch($endpoint) {
    case 'activities':
        handleActivities($pdo, $method);
        break;
    case 'activity':
        handleActivity($pdo, $method);
        break;
    case 'strava-imports':
        handleStravaImports($pdo, $method);
        break;
    case 'stats':
        handleStats($pdo, $method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => "Endpoint not found: '$endpoint'. Available: activities, activity, strava-imports, stats"]);
}

function handleActivities($pdo, $method) {
    if ($method === 'GET') {
        // Get all activities
        try {
            $stmt = $pdo->query("
                SELECT * FROM activities 
                ORDER BY date DESC, created_at DESC
            ");
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert format data
            foreach($activities as &$activity) {
                $activity['is_scouting_activity'] = (bool)$activity['is_scouting_activity'];
                $activity['duration'] = (float)$activity['duration'];
                $activity['distance'] = (float)$activity['distance'];
                $activity['elevation'] = (int)$activity['elevation'];
                $activity['nights'] = (int)$activity['nights'];
            }
            
            echo json_encode($activities);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'POST') {
        // Create new activity
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data', 'received' => $input]);
            return;
        }
        
        // Validate required fields
        $required = ['name', 'date', 'type', 'duration', 'distance', 'elevation', 'nights', 'role', 'category', 'weather', 'start_location', 'end_location', 'comments'];
        foreach($required as $field) {
            if (!isset($data[$field]) && $data[$field] !== 0 && $data[$field] !== '0') {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field", 'data' => $data]);
                return;
            }
        }
        
        try {
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
            
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleActivity($pdo, $method) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Activity ID required']);
        return;
    }
    
    if ($method === 'GET') {
        // Get single activity
        try {
            $stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
            $stmt->execute([$id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$activity) {
                http_response_code(404);
                echo json_encode(['error' => 'Activity not found']);
                return;
            }
            
            $activity['is_scouting_activity'] = (bool)$activity['is_scouting_activity'];
            $activity['duration'] = (float)$activity['duration'];
            $activity['distance'] = (float)$activity['distance'];
            $activity['elevation'] = (int)$activity['elevation'];
            $activity['nights'] = (int)$activity['nights'];
            
            echo json_encode($activity);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'PUT') {
        // Update activity
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE activities SET 
                    name = :name, date = :date, type = :type, duration = :duration, 
                    distance = :distance, elevation = :elevation, nights = :nights,
                    role = :role, category = :category, weather = :weather,
                    start_location = :start_location, end_location = :end_location,
                    comments = :comments, is_scouting_activity = :is_scouting_activity
                WHERE id = :id
            ");
            
            $result = $stmt->execute([
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
            
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'DELETE') {
        // Delete activity
        try {
            $stmt = $pdo->prepare("DELETE FROM activities WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Activity deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Activity not found']);
            }
            
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleStravaImports($pdo, $method) {
    if ($method === 'GET') {
        // Get all imported Strava IDs
        try {
            $stmt = $pdo->query("SELECT strava_id FROM strava_imports");
            $imports = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($imports);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'POST') {
        // Check if Strava ID is already imported
        $data = json_decode(file_get_contents('php://input'), true);
        $stravaId = $data['strava_id'] ?? null;
        
        if (!$stravaId) {
            http_response_code(400);
            echo json_encode(['error' => 'Strava ID required']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM strava_imports WHERE strava_id = ?");
            $stmt->execute([$stravaId]);
            $exists = $stmt->fetchColumn() > 0;
            
            echo json_encode(['exists' => $exists]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleStats($pdo, $method) {
    if ($method === 'GET') {
        try {
            // Get dashboard statistics
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_activities,
                    COALESCE(SUM(distance), 0) as total_distance,
                    COALESCE(SUM(duration), 0) as total_duration,
                    COALESCE(SUM(nights), 0) as total_nights
                FROM activities
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get monthly stats for charts (last 12 months)
            $monthlyStmt = $pdo->query("
                SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month,
                    COUNT(*) as count,
                    COALESCE(SUM(distance), 0) as distance
                FROM activities
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(date, '%Y-%m')
                ORDER BY month
            ");
            $monthlyStats = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent activities (last 5)
            $recentStmt = $pdo->query("
                SELECT name, date, type, distance, duration
                FROM activities
                ORDER BY date DESC, created_at DESC
                LIMIT 5
            ");
            $recentActivities = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'stats' => $stats,
                'monthly' => $monthlyStats,
                'recent' => $recentActivities
            ]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
?>