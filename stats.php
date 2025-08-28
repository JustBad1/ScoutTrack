<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $pdo = getDatabase();
    
    // Get dashboard statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_activities,
            COALESCE(SUM(distance), 0) as total_distance,
            COALESCE(SUM(duration), 0) as total_duration,
            COALESCE(SUM(nights), 0) as total_nights
        FROM activities
    ");
    $stats = $stmt->fetch();
    
    // Convert numeric values
    $stats['total_distance'] = (float)$stats['total_distance'];
    $stats['total_duration'] = (float)$stats['total_duration'];
    $stats['total_nights'] = (int)$stats['total_nights'];
    
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
    $monthlyStats = $monthlyStmt->fetchAll();
    
    // Convert numeric values in monthly stats
    foreach($monthlyStats as &$month) {
        $month['distance'] = (float)$month['distance'];
        $month['count'] = (int)$month['count'];
    }
    
    // Get recent activities (last 5)
    $recentStmt = $pdo->query("
        SELECT name, date, type, distance, duration
        FROM activities
        ORDER BY date DESC, created_at DESC
        LIMIT 5
    ");
    $recentActivities = $recentStmt->fetchAll();
    
    // Convert numeric values in recent activities
    foreach($recentActivities as &$activity) {
        $activity['distance'] = (float)$activity['distance'];
        $activity['duration'] = (float)$activity['duration'];
    }
    
    echo json_encode([
        'stats' => $stats,
        'monthly' => $monthlyStats,
        'recent' => $recentActivities
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>