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

// Strava API configuration
$STRAVA_CONFIG = [
    'CLIENT_ID' => '172542',
    'CLIENT_SECRET' => '4e104a4c801c1040da62f9c6166c370a85cbfe0e', // Get this from 'strava.com/settings/api'
    'REDIRECT_URI' => 'http://localhost/',
];

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Route requests
switch($action) {
    case 'exchange_token':
        exchangeToken();
        break;
    case 'refresh_token':
        refreshToken();
        break;
    case 'get_activities':
        getActivities();
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => "Action not found: '$action'. Available: exchange_token, refresh_token, get_activities"]);
}

function exchangeToken() {
    global $STRAVA_CONFIG;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $code = $input['code'] ?? null;
    
    if (!$code) {
        http_response_code(400);
        echo json_encode(['error' => 'Authorization code required']);
        return;
    }
    
    // Exchange code for tokens with Strava
    $postData = [
        'client_id' => $STRAVA_CONFIG['CLIENT_ID'],
        'client_secret' => $STRAVA_CONFIG['CLIENT_SECRET'],
        'code' => $code,
        'grant_type' => 'authorization_code'
    ];
    
    $response = makeStravaRequest('https://www.strava.com/oauth/token', 'POST', $postData);
    
    if ($response['success']) {
        echo json_encode($response['data']);
    } else {
        http_response_code($response['http_code']);
        echo json_encode(['error' => $response['error']]);
    }
}

function refreshToken() {
    global $STRAVA_CONFIG;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $refreshToken = $input['refresh_token'] ?? null;
    
    if (!$refreshToken) {
        http_response_code(400);
        echo json_encode(['error' => 'Refresh token required']);
        return;
    }
    
    // Refresh tokens with Strava
    $postData = [
        'client_id' => $STRAVA_CONFIG['CLIENT_ID'],
        'client_secret' => $STRAVA_CONFIG['CLIENT_SECRET'],
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token'
    ];
    
    $response = makeStravaRequest('https://www.strava.com/oauth/token', 'POST', $postData);
    
    if ($response['success']) {
        echo json_encode($response['data']);
    } else {
        http_response_code($response['http_code']);
        echo json_encode(['error' => $response['error']]);
    }
}

function getActivities() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $accessToken = $_GET['access_token'] ?? null;
    $perPage = $_GET['per_page'] ?? 50;
    $page = $_GET['page'] ?? 1;
    
    if (!$accessToken) {
        http_response_code(400);
        echo json_encode(['error' => 'Access token required']);
        return;
    }
    
    // Get activities from Strava
    $url = "https://www.strava.com/api/v3/athlete/activities?per_page={$perPage}&page={$page}";
    $headers = ["Authorization: Bearer {$accessToken}"];
    
    $response = makeStravaRequest($url, 'GET', null, $headers);
    
    if ($response['success']) {
        echo json_encode($response['data']);
    } else {
        http_response_code($response['http_code']);
        echo json_encode(['error' => $response['error']]);
    }
}

function makeStravaRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    // SSL Configuration - SOLUTIONS FOR SSL ISSUES
    $sslOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Outdoor Activity Logbook/1.0',
        
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
   ];
    
    curl_setopt_array($ch, $sslOptions);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL error: ' . $error,
            'http_code' => 500
        ];
    }
    
    $decodedResponse = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'data' => $decodedResponse,
            'http_code' => $httpCode
        ];
    } else {
        return [
            'success' => false,
            'error' => $decodedResponse['message'] ?? "HTTP {$httpCode}",
            'http_code' => $httpCode
        ];
    }
}
?>