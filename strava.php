<?php

// --- Basic config for Strava ---
$CLIENT_ID     = '172542';
$CLIENT_SECRET = '4e104a4c801c1040da62f9c6166c370a85cbfe0e';
$SCOPE         = 'activity:read';                   // read-only access
$REDIRECT_URI  = 'http://localhost/strava.php';     // where Strava sends the user back
$INSECURE_SSL  = true;
$CACHE_FILE    = __DIR__ . DIRECTORY_SEPARATOR . 'token_cache.json'; // token storage

function makeRequest($url, $method = 'GET', $fields = null, $headers = [], $insecure_ssl = false) {
    $ch = curl_init($url);

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = is_array($fields) ? http_build_query($fields) : $fields;
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

// Small file cache for tokens
function loadCache($file) {
    if (!is_file($file)) return ['refresh_token' => null, 'access_token' => null, 'expires_at' => 0];
    $j = @file_get_contents($file);
    $d = json_decode($j, true);
    if (!is_array($d)) $d = [];
    return $d + ['refresh_token' => null, 'access_token' => null, 'expires_at' => 0];
}
function saveCache($file, $data) {
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

// Read existing tokens (if any)
$cache = loadCache($CACHE_FILE);

// Handle the first redirect back from Strava
if (isset($_GET['code']) && $_GET['code']) {
    [$code, $body, $err] = makeRequest(
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
    saveCache($CACHE_FILE, $cache);

    header("Location: " . strtok($REDIRECT_URI, '?'));
    exit;
}

// If not connected yet, show an "Authorize with Strava" button
if (empty($cache['refresh_token'])) {
    $authUrl = "https://www.strava.com/oauth/authorize?" . http_build_query([
        'client_id'       => $CLIENT_ID,
        'redirect_uri'    => $REDIRECT_URI,
        'response_type'   => 'code',
        'approval_prompt' => 'auto',
        'scope'           => $SCOPE,
    ]);
    echo <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>Connect Strava</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head><body class="section">
  <div class="container">
    <h1 class="title">Connect your Strava</h1>
    <p class="content">Click the button below and approve access to Strava.</p>
    <a class="button is-link" href="{$authUrl}">Authorize with Strava</a>
  </div>
</body></html>
HTML;
    exit;
}

// Ensure the access token is valid, refresh it if needed
$needNewAccess = empty($cache['access_token']) || time() >= (int)$cache['expires_at'] - 60;

if ($needNewAccess) {
    [$code, $body, $err] = makeRequest(
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
    saveCache($CACHE_FILE, $cache);
}

$access_token = $cache['access_token'];

// Fetch activities (a few pages, then stop)
$all = [];
$perPage = 100;
for ($page = 1; $page <= 5; $page++) {
    $url = "https://www.strava.com/api/v3/athlete/activities?per_page={$perPage}&page={$page}";
    [$code, $body, $err] = makeRequest($url, 'GET', null, ["Authorization: Bearer $access_token"], $INSECURE_SSL);

    if ($code !== 200) {
        http_response_code($code);
        echo "<h1>Strava API error</h1><p>HTTP $code</p><pre>" . htmlspecialchars($body ?: $err) . "</pre>";
        exit;
    }

    $chunk = json_decode($body, true);
    if (!is_array($chunk) || empty($chunk)) break;
    $all = array_merge($all, $chunk);
    if (count($chunk) < $perPage) break;
}

// If there are no activities, show a message
if (empty($all)) {
    echo "<!doctype html><html><head><meta charset='utf-8'><title>No Activities</title>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css'>
</head><body class='section'><div class='container'>
<h1 class='title'>No activities found</h1><p>Try recording some rides/runs first.</p>
</div></body></html>";
    exit;
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Strava Routes & Activities</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  body { background: #f7f8fb; }
  .kpi-card { border-radius: 16px; }
  #map { height: 500px; border-radius: 12px; }
  tr.is-clickable { cursor: pointer; }
</style>
</head>
<body class="section">
<div class="container">

  <div class="level">
    <div class="level-left">
      <h1 class="title">Your Strava Summary</h1>
    </div>
  </div>

  <!-- Top Stats (over ALL time fetched) -->
  <div class="columns">
    <div class="column">
      <div class="box kpi-card">
        <p class="heading">Total Distance</p>
        <p class="title" id="kpi-distance">–</p>
        <p class="subtitle is-size-6" id="kpi-rides">– activities</p>
      </div>
    </div>
    <div class="column">
      <div class="box kpi-card">
        <p class="heading">Moving Time</p>
        <p class="title" id="kpi-time">–</p>
        <p class="subtitle is-size-6">hh:mm:ss</p>
      </div>
    </div>
    <div class="column">
      <div class="box kpi-card">
        <p class="heading">Elevation Gain</p>
        <p class="title" id="kpi-elev">– m</p>
        <p class="subtitle is-size-6">total</p>
      </div>
    </div>
  </div>

  <!-- Map -->
  <div class="box">
    <p class="title is-5">Route map</p>
    <p class="subtitle is-6">Click an activity below to view its route.</p>
    <div id="map"></div>
  </div>

  <!-- Activity table -->
  <div class="box">
    <p class="title is-5">All Activities</p>
    <div class="table-container">
      <table class="table is-fullwidth is-striped is-hoverable">
        <thead>
          <tr>
            <th>Date</th><th>Type</th><th>Name</th><th>Distance (km)</th><th>Moving</th><th>Elev (m)</th>
          </tr>
        </thead>
        <tbody id="activity-rows"></tbody>
      </table>
    </div>
  </div>

</div>

<!-- Libraries -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Raw activities from PHP
const ACTIVITIES = <?php echo json_encode($all, JSON_UNESCAPED_SLASHES); ?>;

function toKm(m){ return m / 1000; }       // meters to km
function formatTime(sec){                   // 3661 to "01:01:01"
  const h = Math.floor(sec/3600);
  const m = Math.floor((sec%3600)/60);
  const s = Math.floor(sec%60);
  return [h,m,s].map(n => String(n).padStart(2,'0')).join(':');
}

// Decode an encoded polyline into [lat, lng] pairs
function decodeRouteLine(str, precision=5){
  let index=0, lat=0, lng=0, coordinates=[];
  const factor = Math.pow(10, precision);
  while (index < str.length){
    let b, shift=0, result=0;
    do { b = str.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
    const dlat = (result & 1) ? ~(result >> 1) : (result >> 1);
    lat += dlat;
    shift=0; result=0;
    do { b = str.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
    const dlng = (result & 1) ? ~(result >> 1) : (result >> 1);
    lng += dlng;
    coordinates.push([lat / factor, lng / factor]);
  }
  return coordinates;
}

// Render the activities table (all activities)
function renderTable(list){
  const tbody = document.getElementById('activity-rows');
  tbody.innerHTML = '';
  list.slice(0, 500).forEach(a=>{
    const tr = document.createElement('tr');
    tr.classList.add('is-clickable');

    // keep data we need for the map
    tr.dataset.polyline = a.map && a.map.summary_polyline ? a.map.summary_polyline : '';
    tr.dataset.name = a.name || '(no title)';

    const dist = a.distance ? toKm(a.distance).toFixed(2) : '';
    const mov  = a.moving_time ? formatTime(a.moving_time) : '';
    const elev = (a.total_elevation_gain ?? '') !== '' ? Number(a.total_elevation_gain).toFixed(0) : '';

    tr.innerHTML = `
      <td>${(a.start_date_local||'-').replace('T',' ').replace('Z','')}</td>
      <td>${a.type||'-'}</td>
      <td>${(a.name||'(no title)').replace(/</g,'&lt;')}</td>
      <td>${dist}</td>
      <td>${mov}</td>
      <td>${elev}</td>
    `;

    tr.addEventListener('click', ()=> drawRouteFromRow(tr));
    tbody.appendChild(tr);
  });
}

// Update stats (over ALL activities)
function updateKpis(list){
  let dist=0, time=0, elev=0, count=list.length;
  list.forEach(a=>{
    if (a.distance) dist += a.distance;
    if (a.moving_time) time += a.moving_time;
    if (a.total_elevation_gain) elev += a.total_elevation_gain;
  });
  document.getElementById('kpi-distance').textContent = (dist/1000).toFixed(1) + ' km';
  document.getElementById('kpi-time').textContent     = formatTime(time);
  document.getElementById('kpi-elev').textContent     = Math.round(elev).toLocaleString();
  document.getElementById('kpi-rides').textContent    = `${count} activities`;
}

// Map + route drawing
let map=null, routeLayer=null;

function ensureMapReady(){
  if (!map){
    map = L.map('map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'&copy; OpenStreetMap' }).addTo(map);
    map.setView([0,0], 2);
  }
}
function clearRouteLayer(){
  if (routeLayer){
    map.removeLayer(routeLayer);
    routeLayer = null;
  }
}
function drawRoute(coords, name='Activity route'){
  ensureMapReady();
  clearRouteLayer();
  routeLayer = L.polyline(coords, { weight: 4 }).addTo(map);
  routeLayer.bindPopup(`<b>${name.replace(/</g,'&lt;')}</b>`).openPopup();
  map.fitBounds(routeLayer.getBounds(), { padding: [20,20] });
}
function drawRouteFromRow(tr){
  const encoded = tr.dataset.polyline || '';
  if (!encoded){
    alert('No route available for this activity.');
    return;
  }
  try{
    const coords = decodeRouteLine(encoded, 5);
    drawRoute(coords, tr.dataset.name || 'Activity route');
  }catch(e){
    console.error(e);
    alert('Failed to decode route.');
  }
}

// Initial render (ALL activities)
(function init(){
  updateKpis(ACTIVITIES);
  renderTable(ACTIVITIES);
  ensureMapReady();
})();
</script>
</body>
</html>
