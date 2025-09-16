
let stravaActivities = [];      // current page of Strava activities
let stravaImportedIds = [];     // "imported" markers

// ===== STRAVA CONFIG =====
const STRAVA_CONFIG = {
  CLIENT_ID: '172542',
  REDIRECT_URI: window.location.origin + window.location.pathname,
  SCOPE: 'activity:read_all'
};

// backend proxy for starava api
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

//  buttons + auth callback + initial UI 
function initialiseStrava() {
  document.getElementById('strava-auth-btn')?.addEventListener('click', initiateStravaAuth);
  document.getElementById('refresh-strava-btn')?.addEventListener('click', loadStravaActivities);
  document.getElementById('disconnect-strava-btn')?.addEventListener('click', disconnectStrava);

  // finish the exchange then clean the URL.
  handleStravaCallback();

  //  stored state and activitie
  const hasAccess = !!LS.access();
  const now = Math.floor(Date.now() / 1000);
  const tokenValid = hasAccess && (!LS.expires() || now < LS.expires());
  const connected = LS.connected() && tokenValid;

  updateStravaAuthUI(connected);
  if (connected) loadStravaActivities();
}

// launch Strava's authorise flow
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

// handle callback for Strava
function handleStravaCallback() {
  const code = new URLSearchParams(window.location.search).get('code');
  if (!code) return;
  exchangeStravaCode(code);
  window.history.replaceState({}, document.title, window.location.pathname);
}

//exchange code for tokens 
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

// token refresh helpers
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
  // refresh if expiring within 5 minutes
  if (LS.expires() && now > (LS.expires() - 300)) {
    return await refreshStravaToken();
  }
  return access;
}

// Load & render activities 
async function loadStravaActivities() {
  const loadingEl = document.getElementById('strava-loading');
  loadingEl?.classList.remove('is-hidden');

  try {
    const token = await getValidStravaToken();

    // Fetch page 1 (50 per page)
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

    // Keep common outdoorsy types
    stravaActivities = (data || []).filter(a =>
      ['Ride','Run','Hike','Walk','Cycling','Running','Hiking'].includes(a.type)
    );

    displayStravaActivities();
    notify(`Loaded ${stravaActivities.length} activities.`);
  } catch (e) {
    notify('Failed to load: ' + e.message, 'is-danger');
    console.error('Strava load error:', e);
  } finally {
    loadingEl?.classList.add('is-hidden');
  }
}

// show/hide auth vs list
function updateStravaAuthUI(connected) {
  const auth = document.getElementById('strava-auth-section');
  const list = document.getElementById('strava-activities-section');
  auth?.classList.toggle('is-hidden', connected);
  list?.classList.toggle('is-hidden', !connected);
}

// Render table
function displayStravaActivities() {
  const tbody = document.getElementById('strava-activities-list');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (!stravaActivities.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="has-text-centered has-text-grey">No activities found</td></tr>';
    return;
  }

  stravaActivities.forEach(a => {
    const idStr = a.id.toString();
    const isImported = stravaImportedIds.includes(idStr);

    // convert Strava units to display
    const km = a.distance ? (a.distance / 1000).toFixed(1) : '0.0';
    const time = a.moving_time ? formatTime(a.moving_time) : '00:00:00';
    const elev = a.total_elevation_gain ? Math.round(a.total_elevation_gain) : 0;

    const tr = document.createElement('tr');
    tr.className = isImported ? 'imported-row' : 'strava-row';
    tr.innerHTML = `
      <td>${safeFormatDate(a.start_date_local)}</td>
      <td>${a.type || 'Activity'}</td>
      <td>${escapeHtml(a.name || 'Untitled')}</td>
      <td>${km}</td>
      <td>${time}</td>
      <td>${elev}</td>
      <td>
        <button class="button is-small ${isImported ? 'is-success' : 'is-primary'}"
                ${isImported ? 'disabled' : ''}
                data-id="${idStr}">
          ${isImported ? 'Imported' : 'Import'}
        </button>
      </td>
    `;
    // add click to the button
    tr.querySelector('button')?.addEventListener('click', () => importStravaActivity(idStr));
    tbody.appendChild(tr);
  });
}

// Import one activity into your Activities API
async function importStravaActivity(stravaId) {
  const a = stravaActivities.find(x => x.id.toString() === stravaId);
  if (!a) return notify('Activity not found', 'is-danger');

  // Simple type mapping
  const mapType = (t) => ({
    Ride: 'Cycling', Cycling: 'Cycling',
    Run: 'Running', Running: 'Running',
    Hike: 'Hiking', Hiking: 'Hiking',
    Walk: 'Hiking'
  }[t] || 'Hiking');

  // Build card to match activities.php POST
  const activity = {
    name: a.name || 'Imported from Strava',
    date: (a.start_date_local || '').split('T')[0] || new Date().toISOString().split('T')[0],
    type: mapType(a.type),
    duration: +(a.moving_time ? (a.moving_time / 3600).toFixed(1) : 0),
    distance: +(a.distance ? (a.distance / 1000).toFixed(1) : 0),
    elevation: a.total_elevation_gain ? Math.round(a.total_elevation_gain) : 0,
    nights: 0,
    role: 'Participate',
    category: 'Recreational',
    weather: 'Unknown',
    start_location: a.start_latlng ? `${a.start_latlng[0].toFixed(4)}, ${a.start_latlng[1].toFixed(4)}` : 'Strava Import',
    end_location: a.end_latlng ? `${a.end_latlng[0].toFixed(4)}, ${a.end_latlng[1].toFixed(4)}` : 'Strava Import',
    comments: `Imported from Strava. Original ID: ${stravaId}${a.description ? '\n\nOriginal description: ' + a.description : ''}`,
    is_scouting_activity: false,
    source: 'strava',
    source_id: stravaId,
    strava_id: stravaId
  };

  try {
    // POST directly to activities backend
    const res = await fetch('./api/activities.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(activity)
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json.id) throw new Error('Create failed');

    // Mark imported locally
    stravaImportedIds.push(stravaId);

    // Refresh table UI
    displayStravaActivities();

    // Refresh your main list if available
    if (typeof window.loadActivities === 'function') {
      await window.loadActivities();
    }

    notify(`Imported "${activity.name}"`);
  } catch (e) {
    notify('Failed to import: ' + e.message, 'is-danger');
    console.error('Strava import error:', e);
  }
}

// disconnect

function disconnectStrava() {
  if (!confirm('Disconnect from Strava?')) return;
  LS.clear();
  stravaActivities = [];
  updateStravaAuthUI(false);
  const tbody = document.getElementById('strava-activities-list');
  if (tbody) tbody.innerHTML = '';
  notify('Disconnected from Strava');
}

// Helpers
function formatTime(s) {
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  return [h, m, sec].map(n => String(Math.floor(n)).padStart(2, '0')).join(':');
}

function safeFormatDate(isoLike) {
  // Use  formatter
  if (typeof window.formatDate === 'function') return window.formatDate(isoLike);
  // Basic fallback: YYYY-MM-DD
  if (!isoLike) return '';
  try { return (isoLike.split('T')[0]) || ''; } catch { return ''; }
}

// Avoid input being seen as HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text ?? '';
  return div.innerHTML;
}

function notify(msg, type) {
  if (typeof window.showNotification === 'function') {
    window.showNotification(msg, type);
  } else {
    console.log(`[Strava] ${type || 'info'}: ${msg}`);
    alert(msg);
  }
}
