<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GPX Import</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.4/css/bulma.min.css">
  <style>
    .file-upload-zone { border: 2px dashed #3273dc; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; background: #fafafa; }
    .is-hidden { display: none !important; }
  </style>
</head>
<body>
  <div class="container mt-6">
    <!-- Import Card -->
    <div class="card">
      <header class="card-header">
        <p class="card-header-title">Import GPX</p>
      </header>
      <div class="card-content">
        <div class="file-upload-zone" onclick="document.getElementById('gpx-file').click()">
          <p><strong>Click to upload</strong> or drag and drop GPX files here</p>
          <p class="has-text-grey">Supported formats: .gpx</p>
        </div>
        <input type="file" id="gpx-file" accept=".gpx" style="display:none" />

        <!-- Preview -->
        <div id="gpx-preview" class="mt-4 is-hidden">
          <h4 class="title is-5">GPX File Preview</h4>
          <div id="gpx-details"></div>
          <button class="button is-primary mt-3" id="save-gpx-btn">Save to LocalStorage</button>
        </div>
      </div>
    </div>

    <!-- Table of saved GPX imports -->
    <div class="card mt-5">
      <header class="card-header">
        <p class="card-header-title">Saved GPX Imports</p>
      </header>
      <div class="card-content">
        <table class="table is-fullwidth is-striped" id="gpx-table">
          <thead>
            <tr>
              <th>Filename</th>
              <th>Distance (km)</th>
              <th>Elevation (m)</th>
              <th>Duration (h)</th>
              <th>Track Points</th>
              <th></th> <!-- Remove button -->
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div id="notification" class="notification is-success is-hidden"></div>
  </div>

  <script>
    const GPX_KEY = 'gpx_imports'; // the key we’ll use for saving stuff in localStorage
    let currentFileData = null;    // this will hold the GPX data we’re currently working on

    document.addEventListener('DOMContentLoaded', () => {
      // when someone picks a file using the hidden input
      document.getElementById('gpx-file').addEventListener('change', (e) => startUpload(e.target));

      // --- Drag & drop stuff ---
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
        if (!file || !file.name.endsWith('.gpx')) { 
          showMessage('Please drop a .gpx file', 'is-warning'); 
          return; 
        }
        const reader = new FileReader();
        reader.onload = (ev) => readGPX(ev.target.result, file.name);
        reader.readAsText(file);
      });

      // save button does the saving
      document.getElementById('save-gpx-btn').addEventListener('click', saveFile);

      // load any previously saved files into the table
      refreshTable();
    });

    //  --- GPX Upload ---
    function startUpload(input) {
      // check file validity
      const file = input.files[0];
      if (!file || !file.name.endsWith('.gpx')) {
        showMessage('Please select a valid .gpx file', 'is-warning');
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => readGPX(e.target.result, file.name);
      reader.readAsText(file);
    }

    function readGPX(xmlString, filename) {
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
      const analysis = crunchNumbers(trkpts);

      // save current file data to global var
      currentFileData = { filename, analysis };

      // show a preview box
      showPreview(analysis, filename);
    }

    function crunchNumbers(points) {
      // if no points return empty stats
      if (!points || points.length < 2) return { distance: 0, elevation: 0, duration: 0, totalPoints: points ? points.length : 0 };
      
      let totalDistance = 0, elevationGain = 0;

      // loop through all the points and calculate distance/elevation
      for (let i = 1; i < points.length; i++) {
        const a = points[i-1], b = points[i];
        totalDistance += getDistance(a.lat, a.lon, b.lat, b.lon);
        if (b.ele && a.ele && b.ele > a.ele) elevationGain += (b.ele - a.ele);
      }

      // figure out how long it took
      let duration = 0;
      if (points[0].time && points[points.length-1].time) {
        duration = (points[points.length-1].time - points[0].time) / 36e5; // hours
      }

      return { distance: totalDistance, elevation: elevationGain, duration, totalPoints: points.length };
    }

    function getDistance(lat1, lon1, lat2, lon2) {
      // haversine formula to get distance between two lat/lon points
      const R = 6371; // radius of earth in km
      const toRad = (v) => v * Math.PI / 180;
      const dLat = toRad(lat2 - lat1);
      const dLon = toRad(lon2 - lon1);
      const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2)**2;
      return 2 * R * Math.asin(Math.sqrt(a));
    }

    function showPreview(analysis, filename) {
      // unhide the preview area
      document.getElementById('gpx-preview').classList.remove('is-hidden');
      // fill in the details
      document.getElementById('gpx-details').innerHTML = `
        <div class="box">
          <h6 class="title is-6">${filename}</h6>
          <p><strong>Distance:</strong> ${analysis.distance.toFixed(2)} km</p>
          <p><strong>Elevation Gain:</strong> ${Math.round(analysis.elevation)} m</p>
          <p><strong>Duration:</strong> ${analysis.duration.toFixed(1)} h</p>
          <p><strong>Track Points:</strong> ${analysis.totalPoints}</p>
        </div>
      `;
    }

    //--- Save & Table ---
    function saveFile() {
      // if nothing to save, stop
      if (!currentFileData) return;

      // get what’s already stored
      const stored = JSON.parse(localStorage.getItem(GPX_KEY) || "[]");
      // add the new one
      stored.push(currentFileData);
      // save it back
      localStorage.setItem(GPX_KEY, JSON.stringify(stored));

      showMessage("GPX data saved.");
      refreshTable();
    }

    function refreshTable() {
      // grab the saved stuff
      const stored = JSON.parse(localStorage.getItem(GPX_KEY) || "[]");
      const tbody = document.querySelector("#gpx-table tbody");

      if (!stored.length) {
        // no saved files
        tbody.innerHTML = `<tr><td colspan="6" class="has-text-centered has-text-grey">No GPX imports yet.</td></tr>`;
        return;
      }

      // build rows for each saved file
      tbody.innerHTML = stored.map((item, i) => `
        <tr>
          <td>${item.filename}</td>
          <td>${Number(item.analysis.distance).toFixed(2)}</td>
          <td>${Math.round(item.analysis.elevation)}</td>
          <td>${Number(item.analysis.duration).toFixed(1)}</td>
          <td>${item.analysis.totalPoints}</td>
          <td><button class="button is-small is-danger" onclick="deleteFile(${i})">Remove</button></td>
        </tr>
      `).join('');
    }

    function deleteFile(index) {
      // get the stored files
      const stored = JSON.parse(localStorage.getItem(GPX_KEY) || "[]");
      // remove the one clicked
      stored.splice(index, 1);
      // save back
      localStorage.setItem(GPX_KEY, JSON.stringify(stored));

      showMessage("GPX entry removed.", "is-info");
      refreshTable();
    }

    // --- Notifcations ---
    function showMessage(msg, type = 'is-success') {
      const n = document.getElementById('notification');
      n.textContent = msg;
      n.className = `notification ${type}`;
      n.classList.remove('is-hidden');
      setTimeout(() => n.classList.add('is-hidden'), 2500);
    }
  </script>
</body>
</html>
