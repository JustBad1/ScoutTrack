<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Outdoor Activity Logbook</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.4/css/bulma.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

  <style>
    .hero { background: #f5f5f5; }
    .custom-card { border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
    .chart-container { position: relative; height: 320px; }
    .file-upload-zone { border: 2px dashed #3273dc; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; background: #fafafa; }
    .tab-content { margin-top: 1rem; }
    .is-hidden { display: none !important; }
    .strava-row { cursor: pointer; }
    .strava-row:hover { background-color: #f5f5f5; }
    .imported-row { background-color: #e8f5e8 !important; }
    .strava-auth-box { text-align: center; padding: 3rem; }
    .loading { opacity: 0.7; pointer-events: none; }
    #notification { position: fixed; top: 20px; right: 20px; z-index: 1000; max-width: 400px; }
  </style>
</head>
<body>
  <!-- Header -->
  <section class="hero">
    <div class="hero-body">
      <div class="container has-text-centered">
        <h1 class="title is-2">🏔️ Outdoor Activity Logbook</h1>
        <p class="subtitle">Track your outdoor adventures, import from Strava, and analyze your progress</p>
      </div>
    </div>
  </section>

  <div class="container mt-4 mb-6">
    <!-- Tabs for swapping views -->
    <div class="card custom-card">
      <div class="card-content">
        <div class="tabs is-boxed is-centered is-large">
          <ul>
            <li class="is-active" data-tab="dashboard"><a><span>📊 Dashboard</span></a></li>
            <li data-tab="add-activity"><a><span>➕ Add Activity</span></a></li>
            <li data-tab="activities"><a><span>📋 View Activities</span></a></li>
            <li data-tab="import"><a><span>📁 Import GPX</span></a></li>
            <li data-tab="strava"><a><span>🔶 Strava Import</span></a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Dashboard -->
    <div id="dashboard-content" class="tab-content">
      <div class="columns is-multiline">
        <!-- Statistics -->
        <div class="column is-3">
          <div class="card custom-card">
            <div class="card-content has-text-centered">
              <p class="title is-2" id="total-activities">0</p>
              <p class="subtitle is-6">Total Activities</p>
            </div>
          </div>
        </div>
        <div class="column is-3">
          <div class="card custom-card">
            <div class="card-content has-text-centered">
              <p class="title is-2" id="total-distance">0</p>
              <p class="subtitle is-6">Total Distance (km)</p>
            </div>
          </div>
        </div>
        <div class="column is-3">
          <div class="card custom-card">
            <div class="card-content has-text-centered">
              <p class="title is-2" id="total-duration">0</p>
              <p class="subtitle is-6">Total Duration (h)</p>
            </div>
          </div>
        </div>
        <div class="column is-3">
          <div class="card custom-card">
            <div class="card-content has-text-centered">
              <p class="title is-2" id="total-nights">0</p>
              <p class="subtitle is-6">Nights Camped</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity by month chart (count + distance) -->
      <div class="card custom-card">
        <header class="card-header">
          <p class="card-header-title">📈 Activity by Month</p>
        </header>
        <div class="card-content">
          <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
        </div>
      </div>

      <!-- Recent list (last 5) -->
      <div class="card custom-card mt-5">
        <header class="card-header">
          <p class="card-header-title"><i class="fas fa-clock"></i> Recent Activities</p>
        </header>
        <div class="card-content">
          <div id="recent-activities" class="content has-text-grey">No activities yet.</div>
        </div>
      </div>
    </div>

    <!-- Add Activity -->
    <div id="add-activity-content" class="tab-content is-hidden">
      <div class="card custom-card">
        <header class="card-header">
          <p class="card-header-title">➕ Add New Activity</p>
        </header>
        <div class="card-content">
          <!-- Form for creating/updating a single activity -->
          <form id="activity-form">
            <div class="columns is-multiline">
              <div class="column is-4">
                <div class="field">
                  <label class="label">Date</label>
                  <div class="control"><input class="input" type="date" id="activity-date" required></div>
                </div>
              </div>
              <div class="column is-4">
                <div class="field">
                  <label class="label">Activity Type</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="activity-type" required>
                        <option value="">Select Activity</option>
                        <option value="Hiking">Hiking</option>
                        <option value="Camping">Camping</option>
                        <option value="Cycling">Cycling</option>
                        <option value="Running">Running</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="column is-4">
                <div class="field">
                  <label class="label">Activity Name</label>
                  <div class="control"><input class="input" type="text" id="activity-name" placeholder="e.g., Waterfall Hike" required></div>
                </div>
              </div>
            </div>

            <div class="columns is-multiline">
              <div class="column is-3">
                <div class="field">
                  <label class="label">Duration (hours)</label>
                  <div class="control"><input class="input" type="number" step="0.1" min="0" id="duration" required></div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label class="label">Distance (km)</label>
                  <div class="control"><input class="input" type="number" step="0.1" min="0" id="distance" required></div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label class="label">Elevation Gain (m)</label>
                  <div class="control"><input class="input" type="number" min="0" id="elevation" required></div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label class="label">Nights Camped</label>
                  <div class="control"><input class="input" type="number" min="0" value="0" id="nights" required></div>
                </div>
              </div>
            </div>

            <div class="columns is-multiline">
              <div class="column is-3">
                <div class="field">
                  <label class="label">Role</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="role" required>
                        <option value="Participate">Participate</option>
                        <option value="Assist">Assist</option>
                        <option value="Lead">Lead</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label class="label">Category</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="category" required>
                        <option value="Recreational">Recreational</option>
                        <option value="Coaching">Coaching</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label class="label">Weather</label>
                  <div class="control"><input class="input" type="text" id="weather" placeholder="Sunny, Rain, etc." required></div>
                </div>
              </div>
              <div class="column is-3">
                <div class="field">
                  <label class="label">Scouting Activity?</label>
                  <div class="control">
                    <label class="checkbox"><input type="checkbox" id="scouting-activity" checked> Yes</label>
                  </div>
                </div>
              </div>
            </div>

            <div class="columns">
              <div class="column is-6">
                <div class="field">
                  <label class="label">Start Location</label>
                  <div class="control"><input class="input" type="text" id="start-location" placeholder="Starting point" required></div>
                </div>
              </div>
              <div class="column is-6">
                <div class="field">
                  <label class="label">End Location</label>
                  <div class="control"><input class="input" type="text" id="end-location" placeholder="End point" required></div>
                </div>
              </div>
            </div>

            <div class="field">
              <label class="label">Comments</label>
              <div class="control"><textarea class="textarea" id="comments" placeholder="Notes, incidents, etc." required></textarea></div>
            </div>

            <div class="field is-grouped">
              <div class="control"><button class="button is-primary" id="save-btn" type="submit"><span id="save-btn-text">Save Activity</span></button></div>
              <div class="control"><button class="button is-light" type="reset" id="reset-btn">Reset</button></div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Activities -->
    <div id="activities-content" class="tab-content is-hidden">
      <div class="card custom-card">
        <header class="card-header">
          <p class="card-header-title">📋 Activities</p>
        </header>
        <div class="card-content">
          <div class="field">
            <div class="control has-icons-left">
              <input class="input" type="text" id="search" placeholder="Search by name, type, role, category, weather, comments...">
              <span class="icon is-small is-left">
                <i class="fas fa-search"></i>
              </span>
            </div>
          </div>
          <div id="activities-list"></div>
        </div>
      </div>
    </div>

    <!-- Import GPX -->
    <div id="import-content" class="tab-content is-hidden">
      <div class="card custom-card">
        <header class="card-header">
          <p class="card-header-title">📁 Import GPX</p>
        </header>
        <div class="card-content">
          <!-- Click to upload + drag and drop -->
          <div class="file-upload-zone" onclick="document.getElementById('gpx-file').click()">
            <p class="mt-3"><strong>Click to upload</strong> or drag and drop GPX files here</p>
            <p class="has-text-grey">Supported formats: .gpx</p>
          </div>
          <input type="file" id="gpx-file" accept=".gpx" style="display:none" />

          <!-- Preview box (once parsed the GPX) -->
          <div id="gpx-preview" class="mt-4 is-hidden">
            <h4 class="title is-5">GPX File Preview</h4>
            <div id="gpx-details"></div>
            <button class="button is-primary mt-3" id="import-gpx-btn">Import Activity</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Strava Import -->
    <div id="strava-content" class="tab-content is-hidden">
      <div class="card custom-card">
        <header class="card-header">
          <p class="card-header-title">🔶 Import from Strava</p>
        </header>
        <div class="card-content">
          
          <!-- Auth status / button -->
          <div id="strava-auth-section">
            <div class="strava-auth-box">
              <h4 class="title is-4">Connect to Strava</h4>
              <p class="subtitle">Connect your Strava account to import your activities</p>
              <button class="button is-primary is-large" id="strava-auth-btn">
                <span class="icon"><i class="fab fa-strava"></i></span>
                <span>Connect with Strava</span>
              </button>
            </div>
          </div>

          <!-- Strava activities table (hidden until connected) -->
          <div id="strava-activities-section" class="is-hidden">
            <div class="level">
              <div class="level-left">
                <div class="level-item">
                  <h4 class="title is-5">Your Strava Activities</h4>
                </div>
              </div>
              <div class="level-right">
                <div class="level-item">
                  <button class="button is-small" id="refresh-strava-btn">
                    <span class="icon"><i class="fas fa-sync"></i></span>
                    <span>Refresh</span>
                  </button>
                  <button class="button is-small is-danger ml-2" id="disconnect-strava-btn">
                    <span class="icon"><i class="fas fa-unlink"></i></span>
                    <span>Disconnect</span>
                  </button>
                </div>
              </div>
            </div>

            <div class="table-container">
              <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Distance (km)</th>
                    <th>Duration</th>
                    <th>Elevation (m)</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="strava-activities-list"></tbody>
              </table>
            </div>

            <div id="strava-loading" class="has-text-centered mt-4 is-hidden">
              <span class="icon is-large"><i class="fas fa-spinner fa-pulse"></i></span>
              <p>Loading Strava activities...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Activity Modal (view/edit/delete one activity) -->
    <div class="modal" id="activity-modal">
      <div class="modal-background" onclick="closeModal()"></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title">Activity Details</p>
          <button class="delete" aria-label="close" onclick="closeModal()"></button>
        </header>
        <section class="modal-card-body" id="activity-modal-body"></section>
        <footer class="modal-card-foot">
          <button class="button is-warning" id="edit-activity-btn">Edit</button>
          <button class="button is-danger" id="delete-activity-btn">Delete</button>
          <button class="button" onclick="closeModal()">Close</button>
        </footer>
      </div>
    </div>

    <div id="notification" class="notification is-success is-hidden"></div>
  </div>

  <script>
    // State 
    const API_BASE = './api.php'; 
    const STRAVA_API_BASE = './strava_api.php';
    let activities = [];
    let stravaActivities = [];
    let stravaImportedIds = [];
    let currentActivityId = null;
    let currentGPXData = null;
    let activityChart = null;

    // Strava config 
    const STRAVA_CONFIG = {
      CLIENT_ID: '172542', 
      REDIRECT_URI: window.location.origin + window.location.pathname,
      SCOPE: 'activity:read_all'
    };

    document.addEventListener('DOMContentLoaded', () => {
      // prefill the date with "today"
      document.getElementById('activity-date').valueAsDate = new Date();

      // initialise tabs / charts / load data from database
      initialiseTabs();
      initialiseCharts();
      loadActivities();
      loadStravaImportedIds();

      // form + search handlers
      document.getElementById('activity-form').addEventListener('submit', saveActivity);
      document.getElementById('search').addEventListener('input', handleSearch);

      // GPX input (when someone picks a file using the hidden input)
      const gpxInput = document.getElementById('gpx-file');
      gpxInput.addEventListener('change', (e) => handleGPXUpload(e.target));

      // import button drops GPX stats into the Add Activity form
      document.getElementById('import-gpx-btn').addEventListener('click', importGPX);

      // Strava handlers
      document.getElementById('strava-auth-btn').addEventListener('click', initiateStravaAuth);
      document.getElementById('refresh-strava-btn').addEventListener('click', loadStravaActivities);
      document.getElementById('disconnect-strava-btn').addEventListener('click', disconnectStrava);

      // check if we're returning from Strava auth
      handleStravaCallback();

      // Drag & drop stuff 
      const dropZone = document.querySelector('.file-upload-zone');
      dropZone.addEventListener('dragover', (e) => { 
        e.preventDefault(); 
        dropZone.classList.add('has-background-light'); 
      });
      dropZone.addEventListener('dragleave', () => dropZone.classList.remove('has-background-light'));
      dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('has-background-light');
        const file = e.dataTransfer.files && e.dataTransfer.files[0];
        if (!file) return;
        if (!file.name.endsWith('.gpx')) { showNotification('Please drop a .gpx file', 'is-warning'); return; }
        const reader = new FileReader();
        reader.onload = (ev) => parseGPXFile(ev.target.result, file.name);
        reader.readAsText(file);
      });

      // reset clears edit state + button text
      document.getElementById('reset-btn').addEventListener('click', () => {
        currentActivityId = null;
        document.getElementById('save-btn-text').textContent = 'Save Activity';
      });
    });

    // --- API Functions ---
    async function apiRequest(endpoint, method = 'GET', data = null) {
      const url = `${API_BASE}?endpoint=${endpoint}`;
      const options = {
        method,
        headers: {
          'Content-Type': 'application/json',
        }
      };
      
      if (data) {
        options.body = JSON.stringify(data);
      }
      
      try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
          throw new Error(result.error || `HTTP ${response.status}`);
        }
        
        return result;
      } catch (error) {
        console.error('API Error:', error);
        showNotification(error.message, 'is-danger');
        throw error;
      }
    }

    function initialiseTabs() {
      // click a tab to swap visible content
      document.querySelectorAll('.tabs li').forEach(tab => {
        tab.addEventListener('click', function() {
          switchTab(this.getAttribute('data-tab'));
        });
      });
    }

    function switchTab(tabName) {
      // hide all, then show the requested tab
      document.querySelectorAll('.tab-content').forEach(el => el.classList.add('is-hidden'));
      document.querySelectorAll('.tabs li').forEach(tab => tab.classList.remove('is-active'));
      document.getElementById(tabName + '-content').classList.remove('is-hidden');
      document.querySelector(`[data-tab="${tabName}"]`).classList.add('is-active');

      if (tabName === 'dashboard') setTimeout(updateCharts, 50);
      if (tabName === 'strava') checkStravaAuthStatus();
    }

    // --- Database Operations ---
    async function loadActivities() {
      try {
        activities = await apiRequest('activities');
        updateDashboard();
        displayActivities();
        updateCharts();
      } catch (error) {
        showNotification('Failed to load activities', 'is-danger');
      }
    }

    async function loadStravaImportedIds() {
      try {
        stravaImportedIds = await apiRequest('strava-imports');
      } catch (error) {
        console.error('Failed to load Strava imports:', error);
      }
    }

    // --- Add / Update ---
    async function saveActivity(e) {
      e.preventDefault();

      // make sure all fields are filled in
      const reqIds = ["activity-date","activity-type","activity-name","duration","distance","elevation","nights","role","category","weather","start-location","end-location","comments"];
      for (const id of reqIds) {
        const el = document.getElementById(id);
        if (el && !el.value.trim()) { 
          showNotification("Please complete all fields before saving.", "is-danger"); 
          return; 
        }
      }

      // package the form into an activity object with proper data types
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

      try {
        // show loading state
        const saveBtn = document.getElementById('save-btn');
        saveBtn.classList.add('is-loading');

        if (currentActivityId) {
          // update existing activity
          await apiRequest(`activity&id=${currentActivityId}`, 'PUT', activity);
          showNotification('Activity updated.');
        } else {
          // create new activity
          await apiRequest('activities', 'POST', activity);
          showNotification('Activity saved.');
        }

        // reset edit state + button text
        currentActivityId = null;
        document.getElementById('save-btn-text').textContent = 'Save Activity';

        // refresh data from database
        await loadActivities();

        // clear the form and set date back to today
        e.target.reset();
        document.getElementById('activity-date').valueAsDate = new Date();

        // switch to list so you can see the new/updated item
        switchTab('activities');

      } catch (error) {
        showNotification('Failed to save activity', 'is-danger');
      } finally {
        document.getElementById('save-btn').classList.remove('is-loading');
      }
    }

    // --- List / Search ---
    function handleSearch(e) {
      // client-side search across fields
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

      // empty state
      if (!list || list.length === 0) {
        container.innerHTML = '<p class="has-text-grey has-text-centered">No activities found.</p>';
        return;
      }

      // newest first
      const sorted = [...list].sort((a,b) => new Date(b.date) - new Date(a.date));

      // cards with tags + quick stats
      container.innerHTML = sorted.map(a => `
        <div class="box" onclick="showActivityDetails(${a.id})" style="cursor: pointer;">
          <div class="columns is-mobile">
            <div class="column">
              <h5 class="title is-5">${a.name ? a.name : (a.type || 'Activity')}</h5>
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

    // --- Modal ---
    async function showActivityDetails(id) {
      try {
        // fetch fresh activity data from database
        const a = await apiRequest(`activity&id=${id}`);
        currentActivityId = id;

        // title prefers name, then type, then a fallback
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

        // show the modal and buttons
        document.getElementById('activity-modal').classList.add('is-active');
        document.getElementById('edit-activity-btn').onclick = () => editActivityFromModal(a);
        document.getElementById('delete-activity-btn').onclick = deleteActivityFromModal;

      } catch (error) {
        showNotification('Failed to load activity details', 'is-danger');
      }
    }

    function closeModal() {
      document.getElementById('activity-modal').classList.remove('is-active');
    }

    function editActivityFromModal(a) {
      // preload the form with the selected activity so you can edit then save
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

      // hide the modal and swap to the form
      closeModal();
      switchTab('add-activity');
    }

    async function deleteActivityFromModal() {
      // simple confirm then delete then refresh UI including all stats
      if (!confirm('Delete this activity?')) return;

      try {
        await apiRequest(`activity&id=${currentActivityId}`, 'DELETE');
        await loadActivities(); // refresh from database
        closeModal();
        showNotification('Activity deleted.');
      } catch (error) {
        showNotification('Failed to delete activity', 'is-danger');
      }
    }

    // --- Dashboard ---
    function updateDashboard() {
      // calculate totals across all activities
      const totalActivities = activities.length;
      const totalDistance = activities.reduce((s,a) => s + (parseFloat(a.distance) || 0), 0);
      const totalDuration = activities.reduce((s,a) => s + (parseFloat(a.duration) || 0), 0);
      const totalNights = activities.reduce((s,a) => s + (parseInt(a.nights) || 0), 0);

      // dump into stats cards
      document.getElementById('total-activities').textContent = totalActivities;
      document.getElementById('total-distance').textContent = totalDistance.toFixed(1);
      document.getElementById('total-duration').textContent = totalDuration.toFixed(1);
      document.getElementById('total-nights').textContent = totalNights;

      // update the "recent" list
      updateRecentActivities();
    }

    function updateRecentActivities() {
      // take last 5 by date (desc) and build a quick list
      const recent = [...activities].sort((a,b) => new Date(b.date) - new Date(a.date)).slice(0,5);
      const container = document.getElementById('recent-activities');
      if (recent.length === 0) {
        container.innerHTML = '<p class="has-text-grey">No activities yet.</p>';
        return;
      }
      container.innerHTML = '<ul>' + recent.map(a =>
        `<li>${formatDate(a.date)} — <strong>${a.name || a.type || 'Activity'}</strong>${a.distance ? ` (${Number(a.distance).toFixed(1)} km)` : ''}${a.duration ? `, ${Number(a.duration).toFixed(1)}h` : ''}</li>`
      ).join('') + '</ul>';
    }

    // --- Charts ---
    function initialiseCharts() {
      // setup a bar/line graph (count per month + distance per month)
      const ctx = document.getElementById('monthlyChart').getContext('2d');
      activityChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: [],
          datasets: [{
            label: 'Activities',
            data: [],
            backgroundColor: 'rgba(50, 115, 220, 0.3)',
            borderColor: '#3273dc',
            borderWidth: 2
          },{
            label: 'Distance (km)',
            data: [],
            backgroundColor: 'rgba(54, 54, 54, 0.15)',
            borderColor: '#363636',
            borderWidth: 2,
            type: 'line',
            yAxisID: 'y1'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Activities' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Distance (km)' } }
          }
        }
      });
    }

    function updateCharts() {
      if (!activityChart) return;

      // calculate last 12 months' counts and total distance
      const stats = monthlyStats(activities, 12);
      activityChart.data.labels = stats.labels;
      activityChart.data.datasets[0].data = stats.counts;
      activityChart.data.datasets[1].data = stats.distances;
      activityChart.update();
    }

    function monthlyStats(list, months) {
      // last number of months including current
      const labels = [];
      const counts = [];
      const distances = [];
      const now = new Date();

      // build month titles from oldest to newest for clean axis labels
      for (let i = months - 1; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        labels.push(d.toLocaleString('en-US', { month: 'short', year: 'numeric' }));

        // collect activities in that month
        const filtered = list.filter(a => {
          const ad = new Date(a.date);
          return ad.getFullYear() === d.getFullYear() && ad.getMonth() === d.getMonth();
        });

        // push counts and total distance (convert to numbers for proper calculation)
        counts.push(filtered.length);
        const totalDistance = filtered.reduce((s,a) => s + (parseFloat(a.distance) || 0), 0);
        distances.push(totalDistance.toFixed(1));
      }

      return { labels, counts, distances };
    }

    // --- GPX ---
    function handleGPXUpload(input) {
      // check file validity
      const file = input.files[0];
      if (!file || !file.name.endsWith('.gpx')) {
        showNotification('Please select a valid .gpx file', 'is-warning');
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => parseGPXFile(e.target.result, file.name);
      reader.readAsText(file);
    }

    function parseGPXFile(xmlString, filename) {
      // turn the text into XML
      const parser = new DOMParser();
      const xml = parser.parseFromString(xmlString, 'text/xml');

      // get all the <trkpt> (track points)
      const trkpts = Array.from(xml.getElementsByTagName('trkpt')).map(pt => ({
        lat: parseFloat(pt.getAttribute('lat')),
        lon: parseFloat(pt.getAttribute('lon')),
        ele: pt.getElementsByTagName('ele')[0] ? parseFloat(pt.getElementsByTagName('ele')[0].textContent) : 0,
        time: pt.getElementsByTagName('time')[0] ? new Date(pt.getElementsByTagName('time')[0].textContent) : null
      }));

      // run a quick analysis (distance, elevation, duration, etc.)
      const analysis = analyseGPX(trkpts);

      // save filename + stats for the import button
      currentGPXData = { filename, analysis };

      // show a preview box
      displayGPXPreview(analysis, filename);
    }

    function analyseGPX(points) {
      // if no points return empty stats
      if (!points || points.length < 2) 
        return { distance: 0, elevation: 0, duration: 0, minElevation: 0, maxElevation: 0, totalPoints: points ? points.length : 0 };

      let totalDistance = 0, elevationGain = 0, minElevation = Infinity, maxElevation = -Infinity;

      // loop through the points and calculate distance/elevation
      for (let i = 1; i < points.length; i++) {
        const a = points[i-1], b = points[i];
        totalDistance += haversine(a.lat, a.lon, b.lat, b.lon);
        if (b.ele && a.ele && b.ele > a.ele) elevationGain += (b.ele - a.ele);
        if (b.ele < minElevation) minElevation = b.ele;
        if (b.ele > maxElevation) maxElevation = b.ele;
      }

      // figure out how long the activity took
      let duration = 0;
      const start = points[0].time, end = points[points.length-1].time;
      if (start && end) duration = (end - start) / 36e5; // hours

      return { distance: totalDistance, elevation: elevationGain, duration, minElevation, maxElevation, totalPoints: points.length };
    }

    function haversine(lat1, lon1, lat2, lon2) {
      // haversine formula to get distance between two lat/lon points (km)
      const R = 6371; 
      const toRad = (v) => v * Math.PI / 180;
      const dLat = toRad(lat2 - lat1);
      const dLon = toRad(lon2 - lon1);
      const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2)**2;
      return 2 * R * Math.asin(Math.sqrt(a));
    }

    function displayGPXPreview(analysis, filename) {
      // unhide the preview area and fill in the details
      document.getElementById('gpx-preview').classList.remove('is-hidden');
      document.getElementById('gpx-details').innerHTML = `
        <div class="box">
          <h6 class="title is-6">${filename}</h6>
          <div class="columns is-multiline">
            <div class="column is-6"><strong>Distance:</strong> ${analysis.distance.toFixed(2)} km</div>
            <div class="column is-6"><strong>Elevation Gain:</strong> ${Math.round(analysis.elevation)} m</div>
            <div class="column is-6"><strong>Duration:</strong> ${analysis.duration.toFixed(1)} h</div>
            <div class="column is-6"><strong>Track Points:</strong> ${analysis.totalPoints}</div>
            <div class="column is-6"><strong>Min Elevation:</strong> ${isFinite(analysis.minElevation) ? Math.round(analysis.minElevation) : 0} m</div>
            <div class="column is-6"><strong>Max Elevation:</strong> ${isFinite(analysis.maxElevation) ? Math.round(analysis.maxElevation) : 0} m</div>
          </div>
        </div>
      `;
    }

    function importGPX() {
      // copy GPX summary numbers into the Add Activity form for a quick save
      if (!currentGPXData) return;
      const a = currentGPXData.analysis;

      // prefill add form with proper data types
      document.getElementById('activity-date').value = new Date().toISOString().split('T')[0];
      document.getElementById('activity-type').value = 'Hiking';

      // set a default name from the filename 
      const baseName = (currentGPXData.filename || 'Imported Activity').replace(/\.[^.]+$/,'');
      document.getElementById('activity-name').value = baseName;

      // ensure numeric values are properly formatted
      document.getElementById('distance').value = a.distance.toFixed(1);
      document.getElementById('duration').value = a.duration.toFixed(1);
      document.getElementById('elevation').value = Math.round(a.elevation);
      document.getElementById('nights').value = 0;

      // set default values for required fields
      document.getElementById('role').value = 'Participate';
      document.getElementById('category').value = 'Recreational';
      document.getElementById('weather').value = 'Unknown';
      document.getElementById('start-location').value = 'GPX Import';
      document.getElementById('end-location').value = 'GPX Import';
      document.getElementById('scouting-activity').checked = false;

      // add comments about import
      const existing = document.getElementById('comments').value;
      document.getElementById('comments').value = (existing ? existing + '\n' : '') + `Imported from GPX: ${currentGPXData.filename}`;

      // jump to the form so you can change and save
      switchTab('add-activity');
      showNotification('GPX data loaded into the Add Activity form.');
    }

    // ---------- Strava Integration ----------
    function initiateStravaAuth() {
      // redirect to Strava OAuth
      const authUrl = `https://www.strava.com/oauth/authorize?` +
        `client_id=${STRAVA_CONFIG.CLIENT_ID}&` +
        `redirect_uri=${encodeURIComponent(STRAVA_CONFIG.REDIRECT_URI)}&` +
        `response_type=code&` +
        `approval_prompt=auto&` +
        `scope=${STRAVA_CONFIG.SCOPE}`;
      
      window.location.href = authUrl;
    }

    function handleStravaCallback() {
      // check if we're returning from Strava auth
      const urlParams = new URLSearchParams(window.location.search);
      const code = urlParams.get('code');
      
      if (code) {
        // exchange code for tokens via our backend
        exchangeStravaCode(code);
        // clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    }

    async function exchangeStravaCode(code) {
      showNotification('Connecting to Strava...', 'is-info');
      
      try {
        // Exchange authorization code for access token via our backend
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
        
        // Store tokens securely
        localStorage.setItem('strava_access_token', result.access_token);
        localStorage.setItem('strava_refresh_token', result.refresh_token);
        localStorage.setItem('strava_expires_at', result.expires_at);
        localStorage.setItem('strava_connected', 'true');
        
        showNotification('Successfully connected to Strava!');
        updateStravaAuthUI(true);
        loadStravaActivities();
        
        // Auto-switch to Strava tab after successful connection
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
        // If refresh fails, clear all tokens and force re-auth
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
      
      // Check if token is expired 
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
        
        // Fetch activities from Strava API via our backend
        const response = await fetch(`${STRAVA_API_BASE}?action=get_activities&access_token=${encodeURIComponent(accessToken)}&per_page=50&page=1`);
        
        const result = await response.json();
        
        if (!response.ok) {
          if (response.status === 401) {
            // Token is invalid, clear stored data and prompt re-auth
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
        
        // Filter to relevant activity types (optional)
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
      // check if we're already connected and token is valid
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
        // Clear invalid connection state
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
        
        // Apply imported styling consistently
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
      // find the strava activity and convert it to our format
      const stravaActivity = stravaActivities.find(a => a.id.toString() === stravaId.toString());
      if (!stravaActivity) {
        showNotification('Activity not found', 'is-danger');
        return;
      }
      
      // Map strava activity type to our types
      const typeMapping = {
        'Ride': 'Cycling',
        'Cycling': 'Cycling',
        'Run': 'Running', 
        'Running': 'Running',
        'Hike': 'Hiking',
        'Hiking': 'Hiking',
        'Walk': 'Hiking'
      };
      
      // Create activity object in our format
      const activity = {
        name: stravaActivity.name || 'Imported from Strava',
        date: stravaActivity.start_date_local.split('T')[0], // Extract date part
        type: typeMapping[stravaActivity.type] || 'Hiking',
        duration: parseFloat(stravaActivity.moving_time ? (stravaActivity.moving_time / 3600).toFixed(1) : 0),
        distance: parseFloat(stravaActivity.distance ? (stravaActivity.distance / 1000).toFixed(1) : 0),
        elevation: stravaActivity.total_elevation_gain ? Math.round(stravaActivity.total_elevation_gain) : 0,
        nights: 0, // Strava doesn't track camping
        role: 'Participate',
        category: 'Recreational',
        weather: 'Unknown', // Strava doesn't track weather
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
        strava_id: stravaId.toString() // for tracking imports
      };
      
      try {
        // Save to database
        await apiRequest('activities', 'POST', activity);
        
        // Add to imported list
        stravaImportedIds.push(stravaId.toString());
        
        // Refresh data and UI
        await loadActivities();
        displayStravaActivities(); // Refresh to show "Imported" status
        
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
      // convert seconds to HH:MM:SS format
      const hours = Math.floor(seconds / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      const secs = seconds % 60;
      return [hours, minutes, secs].map(n => String(Math.floor(n)).padStart(2, '0')).join(':');
    }

    function formatDate(dateString) {
      if (!dateString) return '-';
      return new Date(dateString).toLocaleDateString('en-AU', { year:'numeric', month:'short', day:'numeric' });
    }

    function showNotification(msg, type = 'is-success') {
      // box shows success/error/info messages
      const n = document.getElementById('notification');
      n.textContent = msg;
      n.className = `notification ${type}`;
      n.classList.remove('is-hidden');
      setTimeout(() => n.classList.add('is-hidden'), 3000);
    }
  </script>
</body>
</html>