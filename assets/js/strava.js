let stravaActivities = [];      // current page of Strava activities
let stravaImportedIds = [];     // "imported" markers

// ===== STRAVA CONFIG =====
const STRAVA_CONFIG = {
  CLIENT_ID: '172542',
  REDIRECT_URI: window.location.origin + window.location.pathname,
  SCOPE: 'activity:read_all'
};

// backend proxy for strava api
const STRAVA_API_BASE = './api/strava_api.php';

// LocalStorage helpers
const LS = {
  set(tokens) {
    localStorage.setItem('strava_access_token', tokens.access_token);
    localStorage.setItem('strava_refresh_token', tokens.refresh_token);
    localStorage.setItem('strava_expires_at', tokens.expires_at);
    localStorage.setItem('strava_connected', 'true');
  },
  clear() {
    ['strava_connected','strava_access_token','strava_refresh_token','strava_expires_at']
      .forEach(k => localStorage.removeItem(k));
  },
  access() { return localStorage.getItem('strava_access_token'); },
  refresh() { return localStorage.getItem('strava_refresh_token'); },
  expires() { return parseInt(localStorage.getItem('strava_expires_at') || '0', 10); },
  connected() { return localStorage.getItem('strava_connected') === 'true'; }
};

// Load Strava imported IDs from database (this is the key function)
async function loadStravaImportedIds() {
  try {
    const response = await fetch('./api/strava_imports.php');
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const imports = await response.json();
    stravaImportedIds = Array.isArray(imports) ? imports.map(id => String(id)) : [];
    console.log('Loaded Strava imported IDs:', stravaImportedIds);
    return stravaImportedIds;
  } catch (error) {
    console.error('Error loading Strava imported IDs:', error);
    stravaImportedIds = [];
    return [];
  }
}

// Check if a specific Strava activity has been imported
async function isStravaActivityImported(stravaId) {
  try {
    const response = await fetch('./api/strava_imports.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ strava_id: String(stravaId) })
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const result = await response.json();
    return result.exists === true;
  } catch (error) {
    console.error('Error checking import status:', error);
    return false;
  }
}

// Record a Strava import in the database
async function recordStravaImport(stravaId, activityId) {
  try {
    const response = await fetch('./api/strava_imports.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        action: 'insert',
        strava_id: String(stravaId),
        activity_id: parseInt(activityId)
      })
    });
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const result = await response.json();
    if (result.success) {
      // Add to local cache
      stravaImportedIds.push(String(stravaId));
      console.log('Recorded Strava import:', stravaId);
      return true;
    } else {
      console.error('Failed to record import:', result);
      return false;
    }
  } catch (error) {
    console.error('Error recording import:', error);
    return false;
  }
}

// POST to Strava proxy
async function apiPost(action, body) {
  const res = await fetch(`${STRAVA_API_BASE}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body || {})
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(json.error || `HTTP ${res.status}`);
  return json;
}

//  Initialize Strava module
function initialiseStrava() {
  document.getElementById('strava-auth-btn')?.addEventListener('click', initiateStravaAuth);
  document.getElementById('refresh-strava-btn')?.addEventListener('click', loadStravaActivities);
  document.getElementById('disconnect-strava-btn')?.addEventListener('click', disconnectStrava);

  // Handle OAuth callback
  handleStravaCallback();

  // Load imported IDs first, then check auth status
  loadStravaImportedIds().then(() => {
    checkStravaAuthStatus();
  });
}

// Check Strava authentication status
function checkStravaAuthStatus() {
  const hasAccess = !!LS.access();
  const now = Math.floor(Date.now() / 1000);
  const tokenValid = hasAccess && (!LS.expires() || now < LS.expires());
  const connected = LS.connected() && tokenValid;

  updateStravaAuthUI(connected);
  
  if (connected) {
    loadStravaActivities();
  }
}

// Launch Strava OAuth flow
function initiateStravaAuth() {
  const params = new URLSearchParams({
    client_id: STRAVA_CONFIG.CLIENT_ID,
    redirect_uri: STRAVA_CONFIG.REDIRECT_URI,
    response_type: 'code',
    approval_prompt: 'auto',
    scope: STRAVA_CONFIG.SCOPE
  });
  window.location.href = `https://www.strava.com/oauth/authorize?${params.toString()}`;
}

// Handle OAuth callback
function handleStravaCallback() {
  const code = new URLSearchParams(window.location.search).get('code');
  if (!code) return;
  
  exchangeStravaCode(code);
  // Clean URL
  window.history.replaceState({}, document.title, window.location.pathname);
}

// Exchange code for tokens 
async function exchangeStravaCode(code) {
  notify('Connecting to Strava...');
  try {
    const tokens = await apiPost('exchange_token', { code });
    LS.set(tokens);
    updateStravaAuthUI(true);
    await loadStravaActivities();
    notify('Connected to Strava!');
  } catch (e) {
    notify('Failed to connect: ' + e.message, 'is-danger');
    console.error('Strava auth error:', e);
  }
}

// Token refresh
async function refreshStravaToken() {
  const refresh = LS.refresh();
  if (!refresh) throw new Error('No refresh token');
  const tokens = await apiPost('refresh_token', { refresh_token: refresh });
  LS.set(tokens);
  return tokens.access_token;
}

async function getValidStravaToken() {
  const access = LS.access();
  if (!access) throw new Error('No access token');
  const now = Math.floor(Date.now() / 1000);
  // Refresh if expiring within 5 minutes
  if (LS.expires() && now > (LS.expires() - 300)) {
    return await refreshStravaToken();
  }
  return access;
}

// Load and display Strava activities
// Load and display Strava activities
async function loadStravaActivities() {
  const loadingEl = document.getElementById('strava-loading');
  loadingEl?.classList.remove('is-hidden');

  try {
    const token = await getValidStravaToken();

    // Get activities from Strava API
    const url = `${STRAVA_API_BASE}?action=get_activities&access_token=${encodeURIComponent(token)}&per_page=50&page=1`;
    const res = await fetch(url);
    const data = await res.json();

    if (!res.ok) {
      if (res.status === 401) {
        LS.clear();
        updateStravaAuthUI(false);
        throw new Error('Session expired. Please reconnect.');
      }
      throw new Error(data.error || `HTTP ${res.status}`);
    }

    console.log('Strava API response:', data); // Debug logging

    // Check if data is an array
    if (!Array.isArray(data)) {
      console.error('Expected array from Strava API, got:', typeof data, data);
      
      // Check if it's an error response
      if (data && data.error) {
        throw new Error(data.error);
      }
      
      // If it's not an array and not an error, treat as empty
      stravaActivities = [];
    } else {
      // Filter for outdoor activities
      stravaActivities = data.filter(a =>
        a && a.type && ['Ride','Run','Hike','Walk','Cycling','Running','Hiking'].includes(a.type)
      );
    }

    // Refresh import status
    await loadStravaImportedIds();
    
    // Display activities
    displayStravaActivities();
    notify(`Loaded ${stravaActivities.length} activities.`);
    
  } catch (e) {
    notify('Failed to load activities: ' + e.message, 'is-danger');
    console.error('Strava load error:', e);
  } finally {
    loadingEl?.classList.add('is-hidden');
  }
}

// Show/hide auth interface
function updateStravaAuthUI(connected) {
  const auth = document.getElementById('strava-auth-section');
  const list = document.getElementById('strava-activities-section');
  auth?.classList.toggle('is-hidden', connected);
  list?.classList.toggle('is-hidden', !connected);
}

// Display activities in table
function displayStravaActivities() {
  const tbody = document.getElementById('strava-activities-list');
  if (!tbody) return;
  
  tbody.innerHTML = '';

  if (!stravaActivities.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="has-text-centered has-text-grey">No activities found</td></tr>';
    return;
  }

  stravaActivities.forEach(activity => {
    const stravaId = String(activity.id);
    const isImported = stravaImportedIds.includes(stravaId);

    // Convert units
    const km = activity.distance ? (activity.distance / 1000).toFixed(1) : '0.0';
    const duration = activity.moving_time ? formatDuration(activity.moving_time) : '00:00:00';
    const elevation = activity.total_elevation_gain ? Math.round(activity.total_elevation_gain) : 0;
    const date = formatStravaDate(activity.start_date_local);

    const row = document.createElement('tr');
    row.className = isImported ? 'imported-row' : 'strava-row';
    
    row.innerHTML = `
      <td>${date}</td>
      <td>${activity.type || 'Activity'}</td>
      <td>${escapeHtml(activity.name || 'Untitled')}</td>
      <td>${km} km</td>
      <td>${duration}</td>
      <td>${elevation} m</td>
      <td>
        <button class="button is-small ${isImported ? 'is-success' : 'is-primary'}"
                ${isImported ? 'disabled' : ''}
                data-strava-id="${stravaId}">
          ${isImported ? '✓ Imported' : 'Import'}
        </button>
      </td>
    `;

    // Add import handler
    const button = row.querySelector('button');
    if (button && !isImported) {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        importStravaActivity(stravaId);
      });
    }

    tbody.appendChild(row);
  });
}

// Import a single Strava activity
async function importStravaActivity(stravaId) {
  console.log('Starting import for Strava ID:', stravaId);
  
  const activity = stravaActivities.find(a => String(a.id) === String(stravaId));
  if (!activity) {
    notify('Activity not found', 'is-danger');
    return;
  }

  // Double-check if already imported
  const alreadyImported = await isStravaActivityImported(stravaId);
  if (alreadyImported) {
    notify('Activity already imported', 'is-warning');
    await loadStravaImportedIds(); // Refresh UI
    displayStravaActivities();
    return;
  }

  // Show loading on button
  const button = document.querySelector(`button[data-strava-id="${stravaId}"]`);
  if (button) {
    button.disabled = true;
    button.innerHTML = '<span class="icon is-small"><i class="fas fa-spinner fa-pulse"></i></span>';
  }

  try {
    // Map activity type
    const typeMapping = {
      'Ride': 'Cycling',
      'Cycling': 'Cycling', 
      'Run': 'Running',
      'Running': 'Running',
      'Hike': 'Hiking',
      'Hiking': 'Hiking',
      'Walk': 'Hiking'
    };

    // Build activity object
    const newActivity = {
      name: activity.name || 'Strava Import',
      date: (activity.start_date_local || '').split('T')[0] || new Date().toISOString().split('T')[0],
      type: typeMapping[activity.type] || 'Hiking',
      duration: activity.moving_time ? parseFloat((activity.moving_time / 3600).toFixed(2)) : 0,
      distance: activity.distance ? parseFloat((activity.distance / 1000).toFixed(2)) : 0,
      elevation: activity.total_elevation_gain ? Math.round(activity.total_elevation_gain) : 0,
      nights: 0,
      role: 'Participate',
      category: 'Recreational',
      weather: 'Unknown',
      start_location: activity.start_latlng ? 
        `${activity.start_latlng[0].toFixed(4)}, ${activity.start_latlng[1].toFixed(4)}` : 
        'Strava Import',
      end_location: activity.end_latlng ? 
        `${activity.end_latlng[0].toFixed(4)}, ${activity.end_latlng[1].toFixed(4)}` : 
        'Strava Import',
      comments: `Imported from Strava (ID: ${stravaId})${activity.description ? `\n\n${activity.description}` : ''}`,
      is_scouting_activity: false,
      source: 'strava',
      source_id: stravaId
    };

    console.log('Importing activity:', newActivity);

    // Create activity
    const response = await fetch('./api/activities.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newActivity)
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const result = await response.json();
    
    if (!result.id) {
      throw new Error('No activity ID returned');
    }

    console.log('Activity created with ID:', result.id);

    // Record the import
    const recorded = await recordStravaImport(stravaId, result.id);
    
    if (!recorded) {
      console.warn('Import recorded in activities but not in strava_imports table');
    }

    // Update UI
    displayStravaActivities();
    
    // Refresh main activities list
    if (typeof window.loadActivities === 'function') {
      await window.loadActivities();
    }

    notify(`Successfully imported "${newActivity.name}"`);

  } catch (error) {
    console.error('Import error:', error);
    notify(`Failed to import activity: ${error.message}`, 'is-danger');
  } finally {
    // Reset button
    if (button) {
      button.disabled = false;
      button.innerHTML = 'Import';
    }
  }
}

// Disconnect from Strava
function disconnectStrava() {
  if (!confirm('Disconnect from Strava? This will clear your authentication.')) return;
  
  LS.clear();
  stravaActivities = [];
  stravaImportedIds = [];
  updateStravaAuthUI(false);
  
  const tbody = document.getElementById('strava-activities-list');
  if (tbody) tbody.innerHTML = '';
  
  notify('Disconnected from Strava');
}

// Utility functions
function formatDuration(seconds) {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  return [hours, minutes, secs]
    .map(n => String(Math.floor(n)).padStart(2, '0'))
    .join(':');
}

function formatStravaDate(dateString) {
  if (!dateString) return '';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  } catch {
    return dateString.split('T')[0] || '';
  }
}

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
    console.log(`[Strava] ${message}`);
  }
}