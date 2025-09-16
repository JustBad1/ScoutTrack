<?php
// basic Strava config
$STRAVA_CONFIG = [
    'CLIENT_ID'     => '172542',
    'CLIENT_SECRET' => '4e104a4c801c1040da62f9c6166c370a85cbfe0e',
    'REDIRECT_URI'  => 'http://localhost/',
];

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ''; // which action?

switch ($action) {
    case 'exchange_token': // POST: code - tokens
        exchangeToken($STRAVA_CONFIG);
        break;

    case 'refresh_token': // POST: refresh - new access token
        refreshToken($STRAVA_CONFIG);
        break;

    case 'get_activities': // GET: list activities
        getActivities();
        break;

    default:
        echo json_encode([]); // unknown action
}

// exchange OAuth code for access/refresh tokens
function exchangeToken($config) {
    $input = json_decode(file_get_contents('php://input'), true);
    $code  = $input['code'] ?? null;

    $postData = [
        'client_id'     => $config['CLIENT_ID'],
        'client_secret' => $config['CLIENT_SECRET'],
        'code'          => $code,
        'grant_type'    => 'authorization_code',
    ];

    $resp = makeStravaRequest('https://www.strava.com/oauth/token', 'POST', $postData);
    echo json_encode($resp);
}

// refresh access token
function refreshToken($config) {
    $input = json_decode(file_get_contents('php://input'), true);
    $refreshToken = $input['refresh_token'] ?? null;

    $postData = [
        'client_id'     => $config['CLIENT_ID'],
        'client_secret' => $config['CLIENT_SECRET'],
        'refresh_token' => $refreshToken,
        'grant_type'    => 'refresh_token',
    ];

    $resp = makeStravaRequest('https://www.strava.com/oauth/token', 'POST', $postData);
    echo json_encode($resp);
}

// fetch activities for the athlete
function getActivities() {
    $accessToken = $_GET['access_token'] ?? null;
    $perPage     = $_GET['per_page'] ?? 50;
    $page        = $_GET['page'] ?? 1;

    $url = "https://www.strava.com/api/v3/athlete/activities?per_page={$perPage}&page={$page}";
    $headers = $accessToken ? ["Authorization: Bearer {$accessToken}"] : [];

    $resp = makeStravaRequest($url, 'GET', null, $headers);
    echo json_encode($resp);
}

// HTTP client for Strava
function makeStravaRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();

    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,        
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Outdoor Activity Logbook/1.0',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($data) {
            // send JSON body
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            $headers[] = 'Content-Type: application/json';
        }
    }

    if ($headers) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }

    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($raw, true);
    return $decoded !== null ? $decoded : $raw; // return array if JSON, else raw string
}
