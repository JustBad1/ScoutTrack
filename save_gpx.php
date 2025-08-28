<?php
// api/save_gpx.php
header('Content-Type: application/json');

try {
  // --- DB CONNECT ---
  $pdo = new PDO(
    'mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4',
    'YOUR_USER',
    'YOUR_PASS',
    [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ]
  );

  // --- INPUT ---
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  $activity_id = $data['activity_id'] ?? null;
  $upload_name = $data['upload_name'] ?? null;
  $track_name  = $data['track_name'] ?? null;
  $start_time  = $data['start_time'] ?? null;
  $end_time    = $data['end_time'] ?? null;
  $distance_km = $data['distance_km'] ?? null;
  $elev_gain_m = $data['elev_gain_m'] ?? null;
  $duration_h  = $data['duration_h'] ?? null;
  $points      = $data['points'] ?? [];

  if (!is_array($points) || count($points) === 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'No points provided']); exit;
  }

  // --- TXN ---
  $pdo->beginTransaction();

  // Insert track
  $stmt = $pdo->prepare("
    INSERT INTO gpx_tracks (activity_id, upload_name, track_name, start_time, end_time,
                            distance_km, elev_gain_m, duration_h, point_count)
    VALUES (:activity_id, :upload_name, :track_name, :start_time, :end_time,
            :distance_km, :elev_gain_m, :duration_h, :point_count)
  ");

  $stmt->execute([
    ':activity_id' => $activity_id,
    ':upload_name' => $upload_name,
    ':track_name'  => $track_name,
    ':start_time'  => $start_time ? date('Y-m-d H:i:s', strtotime($start_time)) : null,
    ':end_time'    => $end_time   ? date('Y-m-d H:i:s', strtotime($end_time))   : null,
    ':distance_km' => $distance_km,
    ':elev_gain_m' => $elev_gain_m,
    ':duration_h'  => $duration_h,
    ':point_count' => count($points),
  ]);

  $track_id = (int)$pdo->lastInsertId();

  // Insert points (batched)
  $pstmt = $pdo->prepare("
    INSERT INTO gpx_points (track_id, seq, tstamp, lat, lon, ele_m)
    VALUES (:track_id, :seq, :tstamp, :lat, :lon, :ele_m)
  ");

  foreach ($points as $p) {
    $pstmt->execute([
      ':track_id' => $track_id,
      ':seq'      => $p['seq'],
      ':tstamp'   => !empty($p['time']) ? date('Y-m-d H:i:s', strtotime($p['time'])) : null,
      ':lat'      => $p['lat'],
      ':lon'      => $p['lon'],
      ':ele_m'    => $p['ele'] ?? null,
    ]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'track_id'=>$track_id,'point_count'=>count($points)]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
