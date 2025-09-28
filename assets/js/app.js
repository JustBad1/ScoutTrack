// Main Application Logic
let activities = [];
let currentActivityId = null;

// API endpoints
const API_CONFIG = {
  activities: './api/activities.php',
  stats: './api/stats.php',
  stravaImports: './api/strava_imports.php'
};

document.addEventListener('DOMContentLoaded', () => {
  // default date = today
  document.getElementById('activity-date').valueAsDate = new Date();

  // setup UI + data
  initialiseTabs();
  initialiseCharts();
  loadActivities();

  // form + search events
  document.getElementById('activity-form').addEventListener('submit', saveActivity);
  document.getElementById('search').addEventListener('input', handleSearch);
  document.getElementById('reset-btn').addEventListener('click', resetForm);

  // GPX + Strava setup
  initialiseGPX();
  initialiseStrava();
});

//  handles GET/POST/PUT/DELETE requests and return JSON
async function apiRequest(endpoint, method = 'GET', data = null) {
  const url = endpoint + (method === 'GET' && data ? `?${new URLSearchParams(data)}` : '');
  const options = { method, headers: { 'Content-Type': 'application/json' } };
  if (data && method !== 'GET') options.body = JSON.stringify(data);

  const res = await fetch(url, options);
  return res.json();
}

//tabs
function initialiseTabs() {
  document.querySelectorAll('.tabs li').forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.getAttribute('data-tab')));
  });
}

function switchTab(tabName) {
  // hide everything
  document.querySelectorAll('.tab-content').forEach(el => el.classList.add('is-hidden'));
  document.querySelectorAll('.tabs li').forEach(tab => tab.classList.remove('is-active'));

  // show selected
  document.getElementById(`${tabName}-content`).classList.remove('is-hidden');
  document.querySelector(`[data-tab="${tabName}"]`).classList.add('is-active');

  // tab-specific actions
  if (tabName === 'dashboard') setTimeout(updateCharts, 50);
}

async function loadActivities() {
  // fetch all activities then update UI
  activities = await apiRequest(API_CONFIG.activities);
  updateDashboard();
  displayActivities();
  updateCharts();
}

async function saveActivity(e) {
  e.preventDefault();

  // required fields check
  const requiredIds = [
    'activity-date','activity-type','activity-name','duration','distance','elevation',
    'nights','role','category','weather','start-location','end-location','comments'
  ];
  for (const id of requiredIds) {
    const el = document.getElementById(id);
    if (el && !String(el.value).trim()) {
      return;
    }
  }

  // build activity
  const activity = {
    name: document.getElementById('activity-name').value.trim(),
    date: document.getElementById('activity-date').value,
    type: document.getElementById('activity-type').value,
    duration: parseFloat(document.getElementById('duration').value) || 0,
    distance: parseFloat(document.getElementById('distance').value) || 0,
    elevation: parseInt(document.getElementById('elevation').value, 10) || 0,
    nights: parseInt(document.getElementById('nights').value || 0, 10),
    role: document.getElementById('role').value,
    category: document.getElementById('category').value,
    weather: document.getElementById('weather').value.trim(),
    start_location: document.getElementById('start-location').value.trim(),
    end_location: document.getElementById('end-location').value.trim(),
    comments: document.getElementById('comments').value.trim(),
    is_scouting_activity: document.getElementById('scouting-activity').checked
  };

  // show loading state
  const saveBtn = document.getElementById('save-btn');
  saveBtn.classList.add('is-loading');

  // create or update via API
  if (currentActivityId) {
    await apiRequest(`${API_CONFIG.activities}?id=${currentActivityId}`, 'PUT', activity);
  } else {
    await apiRequest(API_CONFIG.activities, 'POST', activity);
  }

  // refresh UI
  resetForm();
  await loadActivities();
  switchTab('activities');

  // remove loading state
  saveBtn.classList.remove('is-loading');
}

function resetForm() {
  currentActivityId = null;
  document.getElementById('save-btn-text').textContent = 'Save Activity';
  document.getElementById('activity-form').reset();
  document.getElementById('activity-date').valueAsDate = new Date();
}

//search list
function handleSearch(e) {
  const term = e.target.value.toLowerCase();
  const filtered = activities.filter(a =>
    (a.name && a.name.toLowerCase().includes(term)) ||
    (a.type && a.type.toLowerCase().includes(term)) ||
    (a.role && a.role.toLowerCase().includes(term)) ||
    (a.category && a.category.toLowerCase().includes(term)) ||
    (a.weather && a.weather.toLowerCase().includes(term)) ||
    (a.comments && a.comments.toLowerCase().includes(term))
  );
  displayFilteredActivities(filtered);
}

function displayActivities() {
  displayFilteredActivities(activities);
}

function displayFilteredActivities(list) {
  const container = document.getElementById('activities-list');

  if (!list || list.length === 0) {
    container.innerHTML = '<p class="has-text-grey has-text-centered">No activities found.</p>';
    return;
  }

  // newest first
  const sorted = [...list].sort((a, b) => new Date(b.date) - new Date(a.date));

  // render cards
  container.innerHTML = sorted.map(a => `
    <div class="box" onclick="showActivityDetails(${a.id})" style="cursor: pointer;">
      <div class="columns is-mobile">
        <div class="column">
          <h5 class="title is-5">${a.name || a.type || 'Activity'}</h5>
          <p class="subtitle is-6 has-text-grey">${formatDate(a.date)}${a.weather ? ' • ' + a.weather : ''}</p>
          <div class="tags">
            <span class="tag is-link">${a.role}</span>
            <span class="tag is-info">${a.category}</span>
            ${a.is_scouting_activity ? '<span class="tag is-success">Scouting</span>' : ''}
            ${a.source && a.source !== 'manual' ? `<span class="tag is-warning">${a.source}</span>` : ''}
          </div>
        </div>
        <div class="column is-narrow has-text-right">
          ${a.distance ? `<p><i class="fas fa-route"></i> ${Number(a.distance).toFixed(1)} km</p>` : ''}
          ${a.duration ? `<p><i class="fas fa-clock"></i> ${Number(a.duration).toFixed(1)} h</p>` : ''}
          ${a.nights > 0 ? `<p><i class="fas fa-moon"></i> ${a.nights} nights</p>` : ''}
        </div>
      </div>
    </div>
  `).join('');
}

//activity modals

async function showActivityDetails(id) {
  // fetch single activity then open modal
  const a = await apiRequest(`${API_CONFIG.activities}?id=${id}`);
  currentActivityId = id;

  const titleEl = document.querySelector('#activity-modal .modal-card-title');
  if (titleEl) titleEl.textContent = a.name || a.type || 'Activity Details';

  const body = document.getElementById('activity-modal-body');
  body.innerHTML = `
    <div class="content">
      <p><strong>Name:</strong> ${a.name || '-'}</p>
      <p><strong>Date:</strong> ${formatDate(a.date)}</p>
      <p><strong>Type:</strong> ${a.type || '-'}</p>
      <p><strong>Duration:</strong> ${a.duration || 0} h</p>
      <p><strong>Distance:</strong> ${a.distance || 0} km</p>
      <p><strong>Elevation Gain:</strong> ${a.elevation || 0} m</p>
      <p><strong>Nights Camped:</strong> ${a.nights || 0}</p>
      <p><strong>Role:</strong> ${a.role || '-'}</p>
      <p><strong>Category:</strong> ${a.category || '-'}</p>
      <p><strong>Weather:</strong> ${a.weather || '-'}</p>
      <p><strong>Start:</strong> ${a.start_location || '-'}</p>
      <p><strong>End:</strong> ${a.end_location || '-'}</p>
      <p><strong>Scouting Activity:</strong> ${a.is_scouting_activity ? 'Yes' : 'No'}</p>
      <p><strong>Source:</strong> ${a.source || 'Manual'}</p>
      <p><strong>Comments:</strong><br>${a.comments ? a.comments.replace(/\n/g,'<br>') : '-'}</p>
    </div>
  `;

  document.getElementById('activity-modal').classList.add('is-active');
  document.getElementById('edit-activity-btn').onclick = () => editActivityFromModal(a);
  document.getElementById('delete-activity-btn').onclick = deleteActivityFromModal;
}

function closeModal() {
  const activityModal = document.getElementById('activity-modal');
  const importModal = document.getElementById('import-modal');
  
  if (activityModal) {
    activityModal.classList.remove('is-active');
  }
  if (importModal) {
    importModal.classList.remove('is-active');
  }
}

function editActivityFromModal(a) {
  // fill form with existing values
  document.getElementById('activity-date').value = a.date;
  document.getElementById('activity-type').value = ['Hiking','Camping','Cycling','Running'].includes(a.type) ? a.type : '';
  document.getElementById('activity-name').value = a.name || '';
  document.getElementById('duration').value = a.duration || '';
  document.getElementById('distance').value = a.distance || '';
  document.getElementById('elevation').value = a.elevation || '';
  document.getElementById('nights').value = a.nights || 0;
  document.getElementById('role').value = a.role || 'Participate';
  document.getElementById('category').value = ['Recreational','Coaching'].includes(a.category) ? a.category : 'Recreational';
  document.getElementById('weather').value = a.weather || '';
  document.getElementById('start-location').value = a.start_location || '';
  document.getElementById('end-location').value = a.end_location || '';
  document.getElementById('comments').value = a.comments || '';
  document.getElementById('scouting-activity').checked = !!a.is_scouting_activity;
  document.getElementById('save-btn-text').textContent = 'Update Activity';

  closeModal();
  switchTab('add-activity');
}

async function deleteActivityFromModal() {
  if (!confirm('Delete this activity?')) return;

  await apiRequest(`${API_CONFIG.activities}?id=${currentActivityId}`, 'DELETE');
  await loadActivities();
  closeModal();
}

//dashboard titles 

function updateDashboard() {
  const totalActivities = activities.length;
  const totalDistance = activities.reduce((s, a) => s + (parseFloat(a.distance) || 0), 0);
  const totalDuration = activities.reduce((s, a) => s + (parseFloat(a.duration) || 0), 0);
  const totalNights   = activities.reduce((s, a) => s + (parseInt(a.nights) || 0), 0);

  document.getElementById('total-activities').textContent = totalActivities;
  document.getElementById('total-distance').textContent   = totalDistance.toFixed(1);
  document.getElementById('total-duration').textContent   = totalDuration.toFixed(1);
  document.getElementById('total-nights').textContent     = totalNights;
}

//utilities

function formatDate(dateString) {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleDateString('en-AU', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

// Show import modal with prefilled data
function showImportModal(data) {
  const modal = document.getElementById('import-modal');
  const form = document.getElementById('import-activity-form');
  
  // Fill in the locked/prefilled fields
  document.getElementById('import-name').value = data.name || '';
  document.getElementById('import-date').value = data.date || new Date().toISOString().split('T')[0];
  document.getElementById('import-type').value = data.type || 'Hiking';
  document.getElementById('import-duration').value = data.duration || '';
  document.getElementById('import-distance').value = data.distance || '';
  document.getElementById('import-elevation').value = data.elevation || '';
  
  // Clear the editable fields
  document.getElementById('import-weather').value = '';
  document.getElementById('import-start-location').value = '';
  document.getElementById('import-end-location').value = '';
  document.getElementById('import-comments').value = data.comments || '';
  document.getElementById('import-nights').value = 0;
  document.getElementById('import-role').value = 'Participate';
  document.getElementById('import-category').value = 'Recreational';
  document.getElementById('import-scouting').checked = false;
  
  // Store the source data for submission
  form.dataset.sourceData = JSON.stringify(data);
  
  modal.classList.add('is-active');
}

// Handle import form submission
async function submitImportForm(e) {
  e.preventDefault();
  
  const form = e.target;
  const sourceData = JSON.parse(form.dataset.sourceData);
  
  const activity = {
    name: document.getElementById('import-name').value.trim(),
    date: document.getElementById('import-date').value,
    type: document.getElementById('import-type').value,
    duration: parseFloat(document.getElementById('import-duration').value) || 0,
    distance: parseFloat(document.getElementById('import-distance').value) || 0,
    elevation: parseInt(document.getElementById('import-elevation').value, 10) || 0,
    nights: parseInt(document.getElementById('import-nights').value || 0, 10),
    role: document.getElementById('import-role').value,
    category: document.getElementById('import-category').value,
    weather: document.getElementById('import-weather').value.trim() || 'Unknown',
    start_location: document.getElementById('import-start-location').value.trim() || 'Import',
    end_location: document.getElementById('import-end-location').value.trim() || 'Import',
    comments: document.getElementById('import-comments').value.trim(),
    is_scouting_activity: document.getElementById('import-scouting').checked,
    source: sourceData.source,
    source_id: sourceData.source_id
  };

  const saveBtn = form.querySelector('button[type="submit"]');
  saveBtn.classList.add('is-loading');

  try {
    const result = await apiRequest(API_CONFIG.activities, 'POST', activity);
    
    if (result.id) {
      // Record the import in the appropriate table
      if (sourceData.source === 'gpx') {
        await recordGPXImport(sourceData.source_id, result.id);
      } else if (sourceData.source === 'strava') {
        await recordStravaImport(sourceData.source_id, result.id);
      }
      
      await loadActivities();
      closeModal();
      
      // Refresh the respective import lists
      if (sourceData.source === 'gpx') {
        await loadGPXImportedIds();
      } else if (sourceData.source === 'strava') {
        await loadStravaImportedIds();
        displayStravaActivities();
      }
    }
  } catch (error) {
    console.error('Import error:', error);
  } finally {
    saveBtn.classList.remove('is-loading');
  }
}

// Make key functions available globally for other modules
window.loadActivities = loadActivities;
window.showImportModal = showImportModal;
window.formatDate = formatDate;