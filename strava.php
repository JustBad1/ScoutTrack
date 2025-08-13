<?php
$CLIENT_ID     = '172542';
$CLIENT_SECRET = '4e104a4c801c1040da62f9c6166c370a85cbfe0e';

$SCOPE = 'activity:read';

$REDIRECT_URI = 'http://localhost/strava.php';

$INSECURE_SSL = true;

$CACHE_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'token_cache.json';

function http_call($url, $method = 'GET', $fields = null, $headers = [], $insecure_ssl = false) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST]        = true;
        $opts[CURLOPT_POSTFIELDS]  = is_array($fields) ? http_build_query($fields) : $fields;
        if (!array_filter($headers, fn($h) => stripos($h, 'content-type:') === 0)) {
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }
    }
    if ($insecure_ssl) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = false;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $err];
}

function read_cache($file) {
    if (!is_file($file)) return ['refresh_token' => null, 'access_token' => null, 'expires_at' => 0];
    $j = @file_get_contents($file);
    $d = json_decode($j, true);
    if (!is_array($d)) $d = [];
    return $d + ['refresh_token' => null, 'access_token' => null, 'expires_at' => 0];
}
function write_cache($file, $data) {
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

$cache = read_cache($CACHE_FILE);

if (isset($_GET['code']) && $_GET['code']) {
    [$code, $body, $err] = http_call(
        'https://www.strava.com/oauth/token',
        'POST',
        [
            'client_id'     => $CLIENT_ID,
            'client_secret' => $CLIENT_SECRET,
            'code'          => $_GET['code'],
            'grant_type'    => 'authorization_code',
        ],
        [],
        $INSECURE_SSL
    );
    $json = json_decode($body, true);
    if ($code !== 200 || empty($json['refresh_token'])) {
        http_response_code(500);
        echo "<h1>OAuth exchange failed</h1><p>HTTP $code</p><pre>" . htmlspecialchars($body ?: $err) . "</pre>";
        exit;
    }
    $cache['refresh_token'] = $json['refresh_token'];
    $cache['access_token']  = $json['access_token'] ?? null;
    $cache['expires_at']    = $json['expires_at'] ?? 0;
    write_cache($CACHE_FILE, $cache);

    header("Location: " . strtok($REDIRECT_URI, '?'));
    exit;
}

if (empty($cache['refresh_token'])) {
    $authUrl = "https://www.strava.com/oauth/authorize?"
        . http_build_query([
            'client_id'     => $CLIENT_ID,
            'redirect_uri'  => $REDIRECT_URI,
            'response_type' => 'code',
            'approval_prompt'=> 'auto',
            'scope'         => $SCOPE, 
        ]);
    echo <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>Connect Strava</title>
<style>body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:48px}a.button{display:inline-block;padding:12px 16px;border:1px solid #ddd;border-radius:8px;text-decoration:none}</style>
</head><body>
  <h1>Connect your Strava</h1>
  <ol>
    <li>Click the button below and approve <code>{$SCOPE}</code>.</li>
  </ol>
  <p><a class="button" href="{$authUrl}">Authorize with Strava</a></p>
</body></html>
HTML;
    exit;
}

$needNewAccess = empty($cache['access_token']) || time() >= (int)$cache['expires_at'] - 60;
if ($needNewAccess) {
    [$code, $body, $err] = http_call(
        'https://www.strava.com/oauth/token',
        'POST',
        [
            'client_id'     => $CLIENT_ID,
            'client_secret' => $CLIENT_SECRET,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $cache['refresh_token'],
        ],
        [],
        $INSECURE_SSL
    );
    $json = json_decode($body, true);
    if ($code !== 200 || empty($json['access_token'])) {
        http_response_code(500);
        echo "<h1>Failed to refresh access token</h1><p>HTTP $code</p><pre>" . htmlspecialchars($body ?: $err) . "</pre>";
        exit;
    }
    $cache['access_token']  = $json['access_token'];
    $cache['expires_at']    = $json['expires_at'] ?? (time()+3500);
    if (!empty($json['refresh_token'])) {
        $cache['refresh_token'] = $json['refresh_token']; 
    }
    write_cache($CACHE_FILE, $cache);
}
$access_token = $cache['access_token'];

$PER_PAGE = 10;
$url = 'https://www.strava.com/api/v3/athlete/activities?per_page=' . $PER_PAGE . '&page=1';
[$code, $body, $err] = http_call($url, 'GET', null, ["Authorization: Bearer $access_token"], $INSECURE_SSL);

if ($code !== 200) {
    http_response_code($code);
    echo "<h1>Strava API error</h1><p>HTTP $code</p><pre>" . htmlspecialchars($body ?: $err) . "</pre>";
    exit;
}

$activities = json_decode($body, true);
if (!is_array($activities)) {
    echo "<h1>Unexpected response</h1><pre>" . htmlspecialchars($body) . "</pre>";
    exit;
}

?><!doctype html>
<html><head><meta charset="utf-8"><title>Latest Strava Activities</title>
<style>
 body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px}
 table{border-collapse:collapse;width:100%}
 th,td{border:1px solid #ddd;padding:8px} th{background:#f6f6f6;text-align:left}
</style></head><body>
<h1>Latest Strava Activities</h1>
<?php if (empty($activities)): ?>
  <p>No activities found.</p>
<?php else: ?>
  <table>
    <thead><tr><th>Start (local)</th><th>Type</th><th>Name</th><th>Distance (km)</th><th>Moving</th><th>Avg (km/h)</th></tr></thead>
    <tbody>
    <?php foreach ($activities as $a):
      if (!is_array($a)) continue;
      $name = htmlspecialchars($a['name'] ?? '(no title)');
      $date = htmlspecialchars($a['start_date_local'] ?? '-');
      $type = htmlspecialchars($a['type'] ?? '-');
      $dist = isset($a['distance']) ? round($a['distance']/1000,2) : '';
      $mov  = isset($a['moving_time']) ? gmdate('H:i:s',(int)$a['moving_time']) : '';
      $spd  = isset($a['average_speed']) ? round($a['average_speed']*3.6,2) : '';
    ?>
      <tr><td><?= $date ?></td><td><?= $type ?></td><td><?= $name ?></td><td><?= $dist ?></td><td><?= $mov ?></td><td><?= $spd ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</body></html>
