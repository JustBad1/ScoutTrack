// Main Application Logic
let activities = [];
let currentActivityId = null;

// API endpoints
const API_CONFIG = {
  activities: './api/activities.php',
  stats: './api/stats.php',
  stravaImports: './api/strava_imports.php',
  awards: './api/awards.php',
  reportSend: './api/report_send.php',
  awardedProcessor: './api/awarded.php'
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

  // Send Report button event
  document.getElementById('send-report-btn').addEventListener('click', sendActivityReport);

  // GPX + Strava setup
  initialiseGPX();
  initialiseStrava();

  // Process awards on page load
  processAwards();
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
  loadAwards(); // Load awards after activities
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
  
  // Process awards after saving activity
  await processAwards();
  
  switchTab('activities');

  // remove loading state
  saveBtn.classList.remove('is-loading');
}

// Process awards by calling the awarded.php endpoint
async function processAwards() {
  try {
    const response = await fetch(API_CONFIG.awardedProcessor, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });
    
    if (response.ok) {
      const text = await response.text();
      console.log('Awards processing result:', text);
      
      // Refresh awards display after processing
      await loadAwards();
    }
  } catch (error) {
    console.error('Error processing awards:', error);
  }
}

// Send activity report via email
async function sendActivityReport() {
  const sendBtn = document.getElementById('send-report-btn');
  const originalContent = sendBtn.innerHTML;
  
  // Show loading state
  sendBtn.disabled = true;
  sendBtn.innerHTML = `
    <span class="icon">
      <i class="fas fa-spinner fa-pulse"></i>
    </span>
    <span>Sending...</span>
  `;

  try {
    const response = await fetch(API_CONFIG.reportSend, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });

    if (response.ok) {
      const result = await response.text();
      console.log('Report send result:', result);
      
      // Show success message
      showNotification('Activity report sent successfully!', 'is-success');
      
      // Temporarily change button to show success
      sendBtn.innerHTML = `
        <span class="icon">
          <i class="fas fa-check"></i>
        </span>
        <span>Sent!</span>
      `;
      
      // Reset button after 3 seconds
      setTimeout(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = originalContent;
      }, 3000);
      
    } else {
      throw new Error(`HTTP ${response.status}`);
    }
  } catch (error) {
    console.error('Error sending report:', error);
    showNotification('Failed to send report. Please try again.', 'is-danger');
    
    // Reset button
    sendBtn.disabled = false;
    sendBtn.innerHTML = originalContent;
  }
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
  
  // Process awards after deleting activity
  await processAwards();
  
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
      
      // Process awards after importing
      await processAwards();
      
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

// Awards functionality
async function loadAwards() {
  try {
    const response = await fetch(API_CONFIG.awards);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const data = await response.json();
    displayHighestAwards(data.highest_awards);
    displayProgressToNextAwards(data.next_awards, data.totals);
    
  } catch (error) {
    console.error('Error loading awards:', error);
    displayAwardsError();
  }
}

// Display highest awards achieved
function displayHighestAwards(awards) {
  const container = document.getElementById('awards-container');
  
  if (!awards || awards.length === 0) {
    container.innerHTML = `
      <div class="has-text-centered has-text-grey">
        <p>No awards earned yet</p>
        <p class="is-size-7">Complete more activities to start earning awards!</p>
      </div>
    `;
    return;
  }
  
  const awardsHtml = awards.map(award => {
    const dateEarned = formatDate(award.date_earned);
    
    return `
      <div class="box mb-4">
        <h6 class="title is-6 mb-2">${award.name}</h6>
        <div class="tags">
          <span class="tag is-success is-small">
            Earned ${dateEarned}
          </span>
          <span class="tag is-light is-small">
            ${award.type === 'camping' ? `${award.value} nights` : `${award.value} km`}
          </span>
        </div>
      </div>
    `;
  }).join('');
  
  container.innerHTML = awardsHtml;
}

// Display progress to next awards
function displayProgressToNextAwards(nextAwards, totals) {
  const container = document.getElementById('progress-container');
  
  if (!nextAwards || nextAwards.length === 0) {
    container.innerHTML = `
      <div class="has-text-centered has-text-success">
        <p><strong>Congratulations!</strong></p>
        <p>You've earned all available awards!</p>
      </div>
    `;
    return;
  }
  
  const progressHtml = nextAwards.map(award => {
    const isDistance = award.type === 'walkabout';
    const currentValue = isDistance ? totals.distance : totals.nights;
    const targetValue = award.value;
    const progress = Math.min((currentValue / targetValue) * 100, 100);
    const remaining = Math.max(targetValue - currentValue, 0);
    const unit = isDistance ? 'km' : 'nights';
    
    return `
      <div class="box mb-4">
        <h6 class="title is-6 mb-2">${award.name}</h6>
        
        <progress class="progress is-info is-small mb-3" value="${progress}" max="100">${progress}%</progress>
        
        <div class="tags">
          <span class="tag is-info is-small">
            ${currentValue.toFixed(1)} / ${targetValue} ${unit}
          </span>
          <span class="tag is-light is-small">
            ${remaining.toFixed(1)} ${unit} remaining
          </span>
        </div>
      </div>
    `;
  }).join('');
  
  container.innerHTML = progressHtml;
}

// Display error message for awards
function displayAwardsError() {
  const awardsContainer = document.getElementById('awards-container');
  const progressContainer = document.getElementById('progress-container');
  
  const errorHtml = `
    <div class="has-text-centered has-text-grey">
      <span class="icon is-large">
        <i class="fas fa-exclamation-triangle"></i>
      </span>
      <p>Unable to load awards</p>
      <p class="is-size-7">Please check your connection and try again</p>
    </div>
  `;
  
  if (awardsContainer) awardsContainer.innerHTML = errorHtml;
  if (progressContainer) progressContainer.innerHTML = errorHtml;
}

// Notification helper - removed functionality
function showNotification(message, type = 'is-success') {
  // Notifications removed - just log to console
  console.log(`${type}: ${message}`);
}

// Make key functions available globally for other modules
window.loadActivities = loadActivities;
window.showImportModal = showImportModal;
window.formatDate = formatDate;
window.showNotification = showNotification;