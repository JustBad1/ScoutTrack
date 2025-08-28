// Strava Integration
let stravaActivities = [];
let stravaImportedIds = [];

// Strava configuration
const STRAVA_CONFIG = {
    CLIENT_ID: '172542',
    REDIRECT_URI: window.location.origin + window.location.pathname,
    SCOPE: 'activity:read_all'
};

const STRAVA_API_BASE = './api/strava_api.php';

function initialiseStrava() {
    // Event listeners
    document.getElementById('strava-auth-btn').addEventListener('click', initiateStravaAuth);
    document.getElementById('refresh-strava-btn').addEventListener('click', loadStravaActivities);
    document.getElementById('disconnect-strava-btn').addEventListener('click', disconnectStrava);

    // Check if returning from Strava auth
    handleStravaCallback();
}

async function loadStravaImportedIds() {
    try {
        stravaImportedIds = await apiRequest(API_CONFIG.stravaImports);
    } catch (error) {
        console.error('Failed to load Strava imports:', error);
    }
}

function initiateStravaAuth() {
    const authUrl = `https://www.strava.com/oauth/authorize?` +
        `client_id=${STRAVA_CONFIG.CLIENT_ID}&` +
        `redirect_uri=${encodeURIComponent(STRAVA_CONFIG.REDIRECT_URI)}&` +
        `response_type=code&` +
        `approval_prompt=auto&` +
        `scope=${STRAVA_CONFIG.SCOPE}`;
    
    window.location.href = authUrl;
}

function handleStravaCallback() {
    const urlParams = new URLSearchParams(window.location.search);
    const code = urlParams.get('code');
    
    if (code) {
        exchangeStravaCode(code);
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

async function exchangeStravaCode(code) {
    showNotification('Connecting to Strava...', 'is-info');
    
    try {
        const response = await fetch(`${STRAVA_API_BASE}?action=exchange_token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ code: code })
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || `HTTP ${response.status}`);
        }
        
        // Store tokens securely in localStorage
        localStorage.setItem('strava_access_token', result.access_token);
        localStorage.setItem('strava_refresh_token', result.refresh_token);
        localStorage.setItem('strava_expires_at', result.expires_at);
        localStorage.setItem('strava_connected', 'true');
        
        showNotification('Successfully connected to Strava!');
        updateStravaAuthUI(true);
        loadStravaActivities();
        
        // Auto-switch to Strava tab
        switchTab('strava');
    } catch (error) {
        showNotification('Failed to connect to Strava: ' + error.message, 'is-danger');
        console.error('Strava auth error:', error);
    }
}

async function refreshStravaToken() {
    const refreshToken = localStorage.getItem('strava_refresh_token');
    if (!refreshToken) {
        throw new Error('No refresh token available');
    }
    
    try {
        const response = await fetch(`${STRAVA_API_BASE}?action=refresh_token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ refresh_token: refreshToken })
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || `Failed to refresh token: ${response.status}`);
        }
        
        // Update stored tokens
        localStorage.setItem('strava_access_token', result.access_token);
        localStorage.setItem('strava_refresh_token', result.refresh_token);
        localStorage.setItem('strava_expires_at', result.expires_at);
        
        return result.access_token;
    } catch (error) {
        // Clear tokens on refresh failure
        localStorage.removeItem('strava_connected');
        localStorage.removeItem('strava_access_token');
        localStorage.removeItem('strava_refresh_token');
        localStorage.removeItem('strava_expires_at');
        updateStravaAuthUI(false);
        throw error;
    }
}

async function getValidStravaToken() {
    const accessToken = localStorage.getItem('strava_access_token');
    const expiresAt = localStorage.getItem('strava_expires_at');
    
    if (!accessToken) {
        throw new Error('No access token available');
    }
    
    // Check if token is expired (with 5-minute buffer)
    const now = Math.floor(Date.now() / 1000);
    if (expiresAt && now > (parseInt(expiresAt) - 300)) {
        console.log('Token expired, refreshing...');
        return await refreshStravaToken();
    }
    
    return accessToken;
}

async function loadStravaActivities() {
    const loadingEl = document.getElementById('strava-loading');
    loadingEl.classList.remove('is-hidden');
    
    try {
        const accessToken = await getValidStravaToken();
        
        const response = await fetch(
            `${STRAVA_API_BASE}?action=get_activities&access_token=${encodeURIComponent(accessToken)}&per_page=50&page=1`
        );
        
        const result = await response.json();
        
        if (!response.ok) {
            if (response.status === 401) {
                // Token invalid, clear and prompt re-auth
                localStorage.removeItem('strava_connected');
                localStorage.removeItem('strava_access_token');
                localStorage.removeItem('strava_refresh_token');
                localStorage.removeItem('strava_expires_at');
                updateStravaAuthUI(false);
                throw new Error('Authentication expired. Please reconnect to Strava.');
            }
            throw new Error(result.error || `HTTP ${response.status}`);
        }
        
        stravaActivities = result;
        
        // Filter to relevant activity types
        stravaActivities = stravaActivities.filter(activity => 
            ['Ride', 'Run', 'Hike', 'Walk', 'Cycling', 'Running', 'Hiking'].includes(activity.type)
        );
        
        displayStravaActivities();
        showNotification(`Loaded ${stravaActivities.length} Strava activities`);
    } catch (error) {
        showNotification('Failed to load Strava activities: ' + error.message, 'is-danger');
        console.error('Load activities error:', error);
    } finally {
        loadingEl.classList.add('is-hidden');
    }
}

function checkStravaAuthStatus() {
    const connected = localStorage.getItem('strava_connected') === 'true';
    const accessToken = localStorage.getItem('strava_access_token');
    const expiresAt = localStorage.getItem('strava_expires_at');
    
    // Check if token exists and is not expired
    const now = Math.floor(Date.now() / 1000);
    const isTokenValid = accessToken && (!expiresAt || now < parseInt(expiresAt));
    
    const isActuallyConnected = connected && isTokenValid;
    
    updateStravaAuthUI(isActuallyConnected);
    
    if (isActuallyConnected && stravaActivities.length === 0) {
        loadStravaActivities();
    } else if (!isActuallyConnected && connected) {
        localStorage.removeItem('strava_connected');
    }
}

function updateStravaAuthUI(connected) {
    const authSection = document.getElementById('strava-auth-section');
    const activitiesSection = document.getElementById('strava-activities-section');
    
    if (connected) {
        authSection.classList.add('is-hidden');
        activitiesSection.classList.remove('is-hidden');
    } else {
        authSection.classList.remove('is-hidden');
        activitiesSection.classList.add('is-hidden');
    }
}

function displayStravaActivities() {
    const tbody = document.getElementById('strava-activities-list');
    tbody.innerHTML = '';
    
    if (!stravaActivities || stravaActivities.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="has-text-centered has-text-grey">No activities found</td></tr>';
        return;
    }
    
    stravaActivities.forEach(activity => {
        const isImported = stravaImportedIds.includes(activity.id.toString());
        const tr = document.createElement('tr');
        
        if (isImported) {
            tr.className = 'imported-row';
        } else {
            tr.className = 'strava-row';
        }
        
        const distance = activity.distance ? (activity.distance / 1000).toFixed(1) : '0';
        const duration = activity.moving_time ? formatTime(activity.moving_time) : '0:00:00';
        const elevation = activity.total_elevation_gain ? Math.round(activity.total_elevation_gain) : '0';
        
        tr.innerHTML = `
          <td>${formatDate(activity.start_date_local)}</td>
          <td>${activity.type || 'Activity'}</td>
          <td>${activity.name || 'Untitled'}</td>
          <td>${distance}</td>
          <td>${duration}</td>
          <td>${elevation}</td>
          <td>
            <button class="button is-small ${isImported ? 'is-success' : 'is-primary'}" 
                    onclick="importStravaActivity('${activity.id}')"
                    ${isImported ? 'disabled' : ''}>
              ${isImported ? 'Imported' : 'Import'}
            </button>
          </td>
        `;
        
        tbody.appendChild(tr);
    });
}

async function importStravaActivity(stravaId) {
    const stravaActivity = stravaActivities.find(a => a.id.toString() === stravaId.toString());
    if (!stravaActivity) {
        showNotification('Activity not found', 'is-danger');
        return;
    }
    
    // Map Strava activity types
    const typeMapping = {
        'Ride': 'Cycling',
        'Cycling': 'Cycling',
        'Run': 'Running',
        'Running': 'Running',
        'Hike': 'Hiking',
        'Hiking': 'Hiking',
        'Walk': 'Hiking'
    };
    
    const activity = {
        name: stravaActivity.name || 'Imported from Strava',
        date: stravaActivity.start_date_local.split('T')[0],
        type: typeMapping[stravaActivity.type] || 'Hiking',
        duration: parseFloat(stravaActivity.moving_time ? (stravaActivity.moving_time / 3600).toFixed(1) : 0),
        distance: parseFloat(stravaActivity.distance ? (stravaActivity.distance / 1000).toFixed(1) : 0),
        elevation: stravaActivity.total_elevation_gain ? Math.round(stravaActivity.total_elevation_gain) : 0,
        nights: 0,
        role: 'Participate',
        category: 'Recreational',
        weather: 'Unknown',
        start_location: stravaActivity.start_latlng ? 
            `${stravaActivity.start_latlng[0].toFixed(4)}, ${stravaActivity.start_latlng[1].toFixed(4)}` : 
            'Strava Import',
        end_location: stravaActivity.end_latlng ? 
            `${stravaActivity.end_latlng[0].toFixed(4)}, ${stravaActivity.end_latlng[1].toFixed(4)}` : 
            'Strava Import',
        comments: `Imported from Strava. Original ID: ${stravaId}${stravaActivity.description ? '\n\nOriginal description: ' + stravaActivity.description : ''}`,
        is_scouting_activity: false,
        source: 'strava',
        source_id: stravaId.toString(),
        strava_id: stravaId.toString()
    };
    
    try {
        await apiRequest(API_CONFIG.activities, 'POST', activity);
        
        stravaImportedIds.push(stravaId.toString());
        
        await loadActivities();
        displayStravaActivities();
        
        showNotification(`Imported "${activity.name}" from Strava`);
    } catch (error) {
        showNotification('Failed to import Strava activity: ' + error.message, 'is-danger');
        console.error('Import error:', error);
    }
}

function disconnectStrava() {
    if (!confirm('Are you sure you want to disconnect from Strava?')) return;
    
    localStorage.removeItem('strava_connected');
    localStorage.removeItem('strava_access_token');
    localStorage.removeItem('strava_refresh_token');
    localStorage.removeItem('strava_expires_at');
    stravaActivities = [];
    updateStravaAuthUI(false);
    showNotification('Disconnected from Strava');
}

function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return [hours, minutes, secs].map(n => String(Math.floor(n)).padStart(2, '0')).join(':');
}