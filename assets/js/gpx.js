let currentGPXData = null; // holds latest parsed GPX summary for import

function initialiseGPX() {
  // file picker
  const gpxInput = document.getElementById('gpx-file');
  if (gpxInput) {
    gpxInput.addEventListener('change', () => {
      const file = gpxInput.files && gpxInput.files[0];
      if (!file) return;
      if (!file.name.toLowerCase().endsWith('.gpx')) {
        notify('Please select a .gpx file.');
        return;
      }
      handleGPXFile(file);
    });
  }

  // import button
  const importBtn = document.getElementById('import-gpx-btn');
  if (importBtn) {
    importBtn.addEventListener('click', importGPX);
  }
}

// Read, parse, and summarise  GPX file
async function handleGPXFile(file) {
  try {
    // Read file text
    const xmlString = await readFileAsText(file);

    // Parse XML (track name + track points)
    const { trackName, trkpts } = parseGPX(xmlString, file.name);

    if (!trkpts.length) {
      notify('No valid track points found in GPX file.');
      return;
    }

    // Compute quick stats for preview
    const analysis = analyzeGPX(trkpts);

    // Store for import
    currentGPXData = {
      filename: file.name,
      trackName,
      analysis
    };

    // Show preview to user
    displayGPXPreview(analysis, file.name);
    notify(`Parsed "${file.name}" with ${trkpts.length} points.`);
  } catch (err) {
    console.error('GPX parse error:', err);
    notify('Failed to parse GPX file.');
  }
}

// read a File object as text
function readFileAsText(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => resolve(e.target.result);
    reader.onerror = () => reject(new Error('File read failed'));
    reader.readAsText(file);
  });
}

// Parse GPX XML to extract a name and an array of track points
function parseGPX(xmlString, filename) {
  const parser = new DOMParser();
  const xml = parser.parseFromString(xmlString, 'text/xml');

  // Parse errors
  if (xml.querySelector('parsererror')) {
    throw new Error('Invalid GPX XML');
  }

  // Prefer track name, then metadata name
  let trackName =
    xml.querySelector('trk name')?.textContent?.trim() ||
    xml.querySelector('metadata name')?.textContent?.trim() ||
    filename.replace(/\.[^.]+$/, '');

  // Collect <trkpt> lat/lon + optional ele/time
  const trkpts = Array.from(xml.getElementsByTagName('trkpt'))
    .map((pt) => {
      const lat = parseFloat(pt.getAttribute('lat'));
      const lon = parseFloat(pt.getAttribute('lon'));
      if (!Number.isFinite(lat) || !Number.isFinite(lon)) return null;

      const eleText = pt.getElementsByTagName('ele')[0]?.textContent?.trim();
      const timeText = pt.getElementsByTagName('time')[0]?.textContent?.trim();

      const ele = Number.isFinite(parseFloat(eleText)) ? parseFloat(eleText) : null;
      const time = timeText || null;

      return { lat, lon, ele, time };
    })
    .filter(Boolean);

  return { trackName, trkpts };
}

// Summarise distance (km), elevation gain (m), duration (h), etc.
function analyzeGPX(points) {
  if (!Array.isArray(points) || points.length < 2) {
    return { distance: 0, elevation: 0, duration: 0, minElevation: 0, maxElevation: 0, totalPoints: points?.length || 0 };
  }

  let totalDistance = 0; // km
  let elevationGain = 0; // m
  let minElevation = Infinity;
  let maxElevation = -Infinity;

  for (let i = 1; i < points.length; i++) {
    const a = points[i - 1];
    const b = points[i];

    // accumulate distance
    totalDistance += haversineDistance(a.lat, a.lon, b.lat, b.lon);

    // accumulate uphill-only elevation gain + track min/max
    if (Number.isFinite(a.ele) && Number.isFinite(b.ele)) {
      if (b.ele > a.ele) elevationGain += (b.ele - a.ele);
      minElevation = Math.min(minElevation, b.ele);
      maxElevation = Math.max(maxElevation, b.ele);
    }
  }

  // duration from first/last timestamps (hours)
  let durationHrs = 0;
  const first = points.find((p) => p.time);
  const last = [...points].reverse().find((p) => p.time);
  if (first?.time && last?.time) {
    const t0 = new Date(first.time);
    const t1 = new Date(last.time);
    if (Number.isFinite(t0.getTime()) && Number.isFinite(t1.getTime())) {
      durationHrs = (t1 - t0) / 3_600_000; // ms -> h
    }
  }

  return {
    distance: totalDistance,
    elevation: elevationGain,
    duration: Math.max(0, durationHrs),
    minElevation: Number.isFinite(minElevation) ? minElevation : 0,
    maxElevation: Number.isFinite(maxElevation) ? maxElevation : 0,
    totalPoints: points.length
  };
}

// Circle distance in kilometers
function haversineDistance(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth radius (km)
  const toRad = (v) => (v * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

// Preview renderer
function displayGPXPreview(analysis, filename) {
  const previewEl = document.getElementById('gpx-preview');
  const detailsEl = document.getElementById('gpx-details');
  if (!previewEl || !detailsEl) return;

  previewEl.classList.remove('is-hidden');
  detailsEl.innerHTML = `
    <div class="box">
      <h6 class="title is-6">${escapeHtml(filename)}</h6>
      <div class="columns is-multiline">
        <div class="column is-6"><strong>Distance:</strong> ${analysis.distance.toFixed(2)} km</div>
        <div class="column is-6"><strong>Elevation Gain:</strong> ${Math.round(analysis.elevation)} m</div>
        <div class="column is-6"><strong>Duration:</strong> ${analysis.duration.toFixed(1)} h</div>
        <div class="column is-6"><strong>Track Points:</strong> ${analysis.totalPoints}</div>
        <div class="column is-6"><strong>Min Elevation:</strong> ${Math.round(analysis.minElevation)} m</div>
        <div class="column is-6"><strong>Max Elevation:</strong> ${Math.round(analysis.maxElevation)} m</div>
      </div>
    </div>
  `;
}

// Plain text into HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Create an activity from the parsed GPX and POST it to API
async function importGPX() {
  if (!currentGPXData) {
    notify('No GPX data to import.');
    return;
  }

  const btn = document.getElementById('import-gpx-btn');
  if (btn) { btn.disabled = true; btn.textContent = 'Importing...'; }

  try {
    const a = currentGPXData.analysis;

    // Minimal activity info; adjust defaults if needed
    const activity = {
      name: currentGPXData.trackName || 'GPX Import',
      date: new Date().toISOString().split('T')[0],
      type: 'Hiking',
      duration: +a.duration.toFixed(1) || 0,
      distance: +a.distance.toFixed(2) || 0,
      elevation: Math.round(a.elevation) || 0,
      nights: 0,
      role: 'Participate',
      category: 'Recreational',
      weather: 'Unknown',
      start_location: 'Unknown',
      end_location: 'Unknown',
      comments: `Imported from GPX: ${currentGPXData.filename}`,
      is_scouting_activity: false,
      source: 'gpx',
      source_id: currentGPXData.filename
    };

    // POST directly to activities endpoint
    const res = await fetch('./api/activities.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(activity)
    });

    const json = await res.json().catch(() => ({}));

    if (!res.ok || !json.id) {
      throw new Error('Activity creation failed.');
    }

    notify(`Imported "${currentGPXData.filename}" as activity #${json.id}.`);

    // Reset basic UI
    currentGPXData = null;
    document.getElementById('gpx-preview')?.classList.add('is-hidden');
    const fileInput = document.getElementById('gpx-file');
    if (fileInput) fileInput.value = '';

    if (typeof window.loadActivities === 'function') {
      await window.loadActivities();
    }
  } catch (err) {
    console.error('Import error:', err);
    notify('Failed to import GPX.');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Import GPX'; }
  }
}

// notification helper 
function notify(msg) {
    if (typeof window.showNotification === 'function') {
    window.showNotification(msg);
  } else {
    console.log('[GPX]', msg);
    alert(msg);
  }
}
