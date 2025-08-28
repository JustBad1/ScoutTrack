// GPX File Handling
let currentGPXData = null;

function initialiseGPX() {
    // File input handler
    const gpxInput = document.getElementById('gpx-file');
    gpxInput.addEventListener('change', (e) => handleGPXUpload(e.target));

    // Import button
    document.getElementById('import-gpx-btn').addEventListener('click', importGPX);

    // Drag & drop handlers
    const dropZone = document.querySelector('.file-upload-zone');
    
    dropZone.addEventListener('dragover', (e) => { 
        e.preventDefault(); 
        dropZone.classList.add('has-background-light'); 
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('has-background-light');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('has-background-light');
        
        const file = e.dataTransfer.files && e.dataTransfer.files[0];
        if (!file) return;
        
        if (!file.name.endsWith('.gpx')) { 
            showNotification('Please drop a .gpx file', 'is-warning'); 
            return; 
        }
        
        const reader = new FileReader();
        reader.onload = (ev) => parseGPXFile(ev.target.result, file.name);
        reader.readAsText(file);
    });
}

function handleGPXUpload(input) {
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
    try {
        const parser = new DOMParser();
        const xml = parser.parseFromString(xmlString, 'text/xml');
        
        // Check for parsing errors
        const parserError = xml.querySelector('parsererror');
        if (parserError) {
            throw new Error('Invalid GPX file format');
        }

        // Extract track points
        const trkpts = Array.from(xml.getElementsByTagName('trkpt')).map(pt => ({
            lat: parseFloat(pt.getAttribute('lat')),
            lon: parseFloat(pt.getAttribute('lon')),
            ele: pt.getElementsByTagName('ele')[0] ? parseFloat(pt.getElementsByTagName('ele')[0].textContent) : 0,
            time: pt.getElementsByTagName('time')[0] ? new Date(pt.getElementsByTagName('time')[0].textContent) : null
        }));

        // Analyze GPX data
        const analysis = analyzeGPX(trkpts);
        currentGPXData = { filename, analysis };
        displayGPXPreview(analysis, filename);
        
        showNotification(`GPX file "${filename}" parsed successfully`);
        
    } catch (error) {
        showNotification(`Failed to parse GPX file: ${error.message}`, 'is-danger');
        console.error('GPX parsing error:', error);
    }
}

function analyzeGPX(points) {
    if (!points || points.length < 2) {
        return { 
            distance: 0, 
            elevation: 0, 
            duration: 0, 
            minElevation: 0, 
            maxElevation: 0, 
            totalPoints: points ? points.length : 0 
        };
    }

    let totalDistance = 0;
    let elevationGain = 0;
    let minElevation = Infinity;
    let maxElevation = -Infinity;

    // Calculate distance and elevation
    for (let i = 1; i < points.length; i++) {
        const a = points[i-1];
        const b = points[i];
        
        // Distance calculation
        totalDistance += haversineDistance(a.lat, a.lon, b.lat, b.lon);
        
        // Elevation calculations
        if (b.ele && a.ele && b.ele > a.ele) {
            elevationGain += (b.ele - a.ele);
        }
        
        if (b.ele < minElevation) minElevation = b.ele;
        if (b.ele > maxElevation) maxElevation = b.ele;
    }

    // Duration calculation
    let duration = 0;
    const start = points[0].time;
    const end = points[points.length - 1].time;
    if (start && end) {
        duration = (end - start) / (1000 * 60 * 60); // Convert to hours
    }

    return { 
        distance: totalDistance, 
        elevation: elevationGain, 
        duration, 
        minElevation: isFinite(minElevation) ? minElevation : 0, 
        maxElevation: isFinite(maxElevation) ? maxElevation : 0, 
        totalPoints: points.length 
    };
}

function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const toRad = (v) => v * Math.PI / 180;
    
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
              
    return 2 * R * Math.asin(Math.sqrt(a));
}

function displayGPXPreview(analysis, filename) {
    document.getElementById('gpx-preview').classList.remove('is-hidden');
    
    document.getElementById('gpx-details').innerHTML = `
        <div class="box">
          <h6 class="title is-6">${filename}</h6>
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

function importGPX() {
    if (!currentGPXData) {
        showNotification('No GPX data to import', 'is-warning');
        return;
    }
    
    const analysis = currentGPXData.analysis;
    const baseName = currentGPXData.filename.replace(/\.[^.]+$/, '');

    // Pre-fill the activity form
    document.getElementById('activity-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('activity-type').value = 'Hiking';
    document.getElementById('activity-name').value = baseName;
    document.getElementById('distance').value = analysis.distance.toFixed(1);
    document.getElementById('duration').value = analysis.duration.toFixed(1);
    document.getElementById('elevation').value = Math.round(analysis.elevation);
    document.getElementById('nights').value = 0;
    document.getElementById('role').value = 'Participate';
    document.getElementById('category').value = 'Recreational';
    document.getElementById('weather').value = 'Unknown';
    document.getElementById('start-location').value = 'GPX Import';
    document.getElementById('end-location').value = 'GPX Import';
    document.getElementById('scouting-activity').checked = false;

    // Add import comment
    const existing = document.getElementById('comments').value;
    document.getElementById('comments').value = (existing ? existing + '\n' : '') + 
        `Imported from GPX: ${currentGPXData.filename}`;

    // Switch to add activity tab
    switchTab('add-activity');
    showNotification('GPX data loaded into the Add Activity form.');
}