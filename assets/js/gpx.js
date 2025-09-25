let currentGPXData = null; // holds latest parsed GPX summary for import
let gpxImportedIds = []; // track imported GPX files

function initialiseGPX() {
  // Load imported GPX IDs
  loadGPXImportedIds();
  
  // File picker
  const gpxInput = document.getElementById('gpx-file');
  if (gpxInput) {
    gpxInput.addEventListener('change', handleGPXFileSelect);
  }

  // Import button
  const importBtn = document.getElementById('import-gpx-btn');
  if (importBtn) {
    importBtn.addEventListener('click', importGPX);
  }
}

// Load GPX imported IDs from database
async function loadGPXImportedIds() {
  try {
    const response = await fetch('./api/gpx_imports.php');
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const imports = await response.json();
    gpxImportedIds = Array.isArray(imports) ? imports : [];
    console.log('Loaded GPX imported IDs:', gpxImportedIds);
    return gpxImportedIds;
  } catch (error) {
    console.error('Error loading GPX imported IDs:', error);
    gpxImportedIds = [];
    return [];
  }
}

// Check if GPX file has already been imported
async function isGPXImported(filename) {
  try {
    const response = await fetch('./api/gpx_imports.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ gpx_id: filename })
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const result = await response.json();
    return result.exists === true;
  } catch (error) {
    console.error('Error checking GPX import status:', error);
    return false;
  }
}

// Record GPX import in database
async function recordGPXImport(filename, activityId) {
  try {
    const response = await fetch('./api/gpx_imports.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'insert',
        gpx_id: filename,
        activity_id: parseInt(activityId)
      })
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const result = await response.json();
    if (result.success) {
      gpxImportedIds.push(filename);
      console.log('Recorded GPX import:', filename);
      return true;
    } else {
      console.error('Failed to record GPX import:', result);
      return false;
    }
  } catch (error) {
    console.error('Error recording GPX import:', error);
    return false;
  }
}

// Handle file selection
function handleGPXFileSelect(event) {
  const file = event.target.files && event.target.files[0];
  if (!file) return;
  
  if (!file.name.toLowerCase().endsWith('.gpx')) {
    notify('Please select a .gpx file.', 'is-warning');
    return;
  }
  
  handleGPXFile(file);
}

// Process GPX file
async function handleGPXFile(file) {
  try {
    // Check if already imported
    const alreadyImported = await isGPXImported(file.name);
    if (alreadyImported) {
      notify('This GPX file has already been imported.', 'is-warning');
      return;
    }

    notify('Processing GPX file...');

    // Read file content
    const xmlString = await readFileAsText(file);

    // Parse GPX
    const { trackName, trackPoints } = parseGPX(xmlString, file.name);

    if (!trackPoints.length) {
      notify('No valid track points found in GPX file.', 'is-danger');
      return;
    }

    // Analyze GPX data
    const analysis = analyzeGPX(trackPoints);

    // Store for import
    currentGPXData = {
      filename: file.name,
      trackName,
      analysis,
      pointCount: trackPoints.length
    };

    // Show preview
    displayGPXPreview();
    notify(`GPX file processed successfully: ${trackPoints.length} points found.`);
    
  } catch (error) {
    console.error('GPX processing error:', error);
    notify(`Failed to process GPX file: ${error.message}`, 'is-danger');
  }
}

// Read file as text
function readFileAsText(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => resolve(e.target.result);
    reader.onerror = () => reject(new Error('File read failed'));
    reader.readAsText(file);
  });
}

// Parse GPX XML
function parseGPX(xmlString, filename) {
  const parser = new DOMParser();
  const xml = parser.parseFromString(xmlString, 'text/xml');

  // Check for parse errors
  if (xml.querySelector('parsererror')) {
    throw new Error('Invalid GPX XML format');
  }

  // Extract track name
  let trackName =
    xml.querySelector('trk name')?.textContent?.trim() ||
    xml.querySelector('metadata name')?.textContent?.trim() ||
    filename.replace(/\.[^.]+$/, ''); // Remove extension

  // Extract track points
  const trackPoints = Array.from(xml.getElementsByTagName('trkpt'))
    .map((point) => {
      const lat = parseFloat(point.getAttribute('lat'));
      const lon = parseFloat(point.getAttribute('lon'));
      
      if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
        return null;
      }

      const eleElement = point.getElementsByTagName('ele')[0];
      const timeElement = point.getElementsByTagName('time')[0];

      const elevation = eleElement ? parseFloat(eleElement.textContent) : null;
      const time = timeElement ? timeElement.textContent.trim() : null;

      return {
        lat,
        lon,
        elevation: Number.isFinite(elevation) ? elevation : null,
        time
      };
    })
    .filter(Boolean);

  return { trackName, trackPoints };
}

// Analyze GPX track points
function analyzeGPX(points) {
  if (!Array.isArray(points) || points.length < 2) {
    return {
      distance: 0,
      elevationGain: 0,
      duration: 0,
      minElevation: 0,
      maxElevation: 0,
      totalPoints: points?.length || 0
    };
  }

  let totalDistance = 0; // km
  let elevationGain = 0; // m
  let minElevation = Infinity;
  let maxElevation = -Infinity;

  // Calculate distance and elevation
  for (let i = 1; i < points.length; i++) {
    const prevPoint = points[i - 1];
    const currPoint = points[i];

    // Add distance
    totalDistance += calculateDistance(
      prevPoint.lat, prevPoint.lon,
      currPoint.lat, currPoint.lon
    );

    // Track elevation changes
    if (Number.isFinite(prevPoint.elevation) && Number.isFinite(currPoint.elevation)) {
      // Only count uphill for elevation gain
      if (currPoint.elevation > prevPoint.elevation) {
        elevationGain += (currPoint.elevation - prevPoint.elevation);
      }
      
      minElevation = Math.min(minElevation, currPoint.elevation);
      maxElevation = Math.max(maxElevation, currPoint.elevation);
    }
  }

  // Calculate duration from timestamps
  let duration = 0;
  const firstPoint = points.find(p => p.time);
  const lastPoint = [...points].reverse().find(p => p.time);
  
  if (firstPoint?.time && lastPoint?.time) {
    try {
      const startTime = new Date(firstPoint.time);
      const endTime = new Date(lastPoint.time);
      
      if (Number.isFinite(startTime.getTime()) && Number.isFinite(endTime.getTime())) {
        duration = (endTime - startTime) / 3600000; // Convert to hours
      }
    } catch (error) {
      console.warn('Error parsing timestamps:', error);
    }
  }

  return {
    distance: totalDistance,
    elevationGain,
    duration: Math.max(0, duration),
    minElevation: Number.isFinite(minElevation) ? minElevation : 0,
    maxElevation: Number.isFinite(maxElevation) ? maxElevation : 0,
    totalPoints: points.length
  };
}

// Calculate distance between two points using Haversine formula
function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth's radius in km
  const toRadians = (deg) => (deg * Math.PI) / 180;
  
  const dLat = toRadians(lat2 - lat1);
  const dLon = toRadians(lon2 - lon1);
  
  const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
           Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) *
           Math.sin(dLon / 2) * Math.sin(dLon / 2);
  
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  
  return R * c;
}

// Display GPX preview
function displayGPXPreview() {
  const previewEl = document.getElementById('gpx-preview');
  const detailsEl = document.getElementById('gpx-details');
  
  if (!previewEl || !detailsEl || !currentGPXData) return;

  const { analysis, filename, pointCount } = currentGPXData;

  previewEl.classList.remove('is-hidden');
  detailsEl.innerHTML = `
    <div class="box">
      <h6 class="title is-6">${escapeHtml(filename)}</h6>
      <div class="columns is-multiline">
        <div class="column is-6">
          <strong>Distance:</strong> ${analysis.distance.toFixed(2)} km
        </div>
        <div class="column is-6">
          <strong>Elevation Gain:</strong> ${Math.round(analysis.elevationGain)} m
        </div>
        <div class="column is-6">
          <strong>Duration:</strong> ${analysis.duration.toFixed(1)} h
        </div>
        <div class="column is-6">
          <strong>Track Points:</strong> ${pointCount}
        </div>
        <div class="column is-6">
          <strong>Min Elevation:</strong> ${Math.round(analysis.minElevation)} m
        </div>
        <div class="column is-6">
          <strong>Max Elevation:</strong> ${Math.round(analysis.maxElevation)} m
        </div>
      </div>
    </div>
  `;
}

// Import GPX as activity
async function importGPX() {
  if (!currentGPXData) {
    notify('No GPX data to import.', 'is-warning');
    return;
  }

  // Double-check import status
  const alreadyImported = await isGPXImported(currentGPXData.filename);
  if (alreadyImported) {
    notify('This GPX file has already been imported.', 'is-warning');
    return;
  }

  const importBtn = document.getElementById('import-gpx-btn');
  if (importBtn) {
    importBtn.disabled = true;
    importBtn.innerHTML = '<span class="icon is-small"><i class="fas fa-spinner fa-pulse"></i></span> Importing...';
  }

  try {
    const { analysis, trackName, filename } = currentGPXData;

    // Build activity object
    const activity = {
      name: trackName || 'GPX Import',
      date: new Date().toISOString().split('T')[0], // Today's date
      type: 'Hiking', // Default type
      duration: parseFloat(analysis.duration.toFixed(2)) || 0,
      distance: parseFloat(analysis.distance.toFixed(2)) || 0,
      elevation: Math.round(analysis.elevationGain) || 0,
      nights: 0,
      role: 'Participate',
      category: 'Recreational',
      weather: 'Unknown',
      start_location: 'GPX Import',
      end_location: 'GPX Import',
      comments: `Imported from GPX file: ${filename}\nTrack points: ${analysis.totalPoints}`,
      is_scouting_activity: false,
      source: 'gpx',
      source_id: filename
    };

    console.log('Importing GPX activity:', activity);

    // Create activity
    const response = await fetch('./api/activities.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(activity)
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const result = await response.json();
    
    if (!result.id) {
      throw new Error('No activity ID returned');
    }

    console.log('GPX activity created with ID:', result.id);

    // Record the import
    const recorded = await recordGPXImport(filename, result.id);
    
    if (!recorded) {
      console.warn('Activity created but not recorded in gpx_imports table');
    }

    // Clear the preview
    currentGPXData = null;
    document.getElementById('gpx-preview')?.classList.add('is-hidden');
    
    // Reset file input
    const fileInput = document.getElementById('gpx-file');
    if (fileInput) fileInput.value = '';

    // Refresh main activities list
    if (typeof window.loadActivities === 'function') {
      await window.loadActivities();
    }

    notify(`Successfully imported "${activity.name}" from GPX file`);

  } catch (error) {
    console.error('GPX import error:', error);
    notify(`Failed to import GPX: ${error.message}`, 'is-danger');
  } finally {
    // Reset button
    if (importBtn) {
      importBtn.disabled = false;
      importBtn.innerHTML = 'Import GPX';
    }
  }
}

// Utility functions
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function notify(message, type = 'is-success') {
  if (typeof window.showNotification === 'function') {
    window.showNotification(message, type);
  } else {
    console.log(`[GPX] ${message}`);
  }
}