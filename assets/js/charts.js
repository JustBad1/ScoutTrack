// Charts
let countChart = null;
let distanceChart = null;
let typeChart = null;
let elevationChart = null;
let currentDateRange = 6; // default 6 months

const byId = (id) => document.getElementById(id);
const setChart = (chart, labels, data, i = 0) => {
  chart.data.labels = labels;
  chart.data.datasets[i].data = data;
  chart.update();
};
const monthLabel = (d) => d.toLocaleString('en-US', { month: 'long', year: 'numeric' });

function initialiseCharts() {
  initialiseCountChart();
  initialiseDistanceChart();
  initialiseTypeChart();
  initialiseElevationChart();

  // range controls
  byId('update-charts-btn')?.addEventListener('click', updateDateRange);
  byId('date-range-selector')?.addEventListener('change', updateDateRange);
}

function updateDateRange() {
  currentDateRange = parseInt(byId('date-range-selector').value);
  updateCharts();
}

function initialiseCountChart() {
  const ctx = byId('countChart').getContext('2d');
  countChart = new Chart(ctx, {
    type: 'line',
    data: { labels: [], datasets: [{
      label: 'Activity Count',
      data: [],
      borderColor: '#3273dc',
      backgroundColor: 'rgba(50,115,220,0.1)',
      borderWidth: 3, fill: true, tension: 0.4,
      pointBackgroundColor: '#3273dc', pointBorderColor: '#fff',
      pointBorderWidth: 2, pointRadius: 4
    }]},
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Activities' }, ticks: { stepSize: 1 } } },
      plugins: { legend: { display: false } }
    }
  });
}

function initialiseDistanceChart() {
  const ctx = byId('distanceChart').getContext('2d');
  distanceChart = new Chart(ctx, {
    type: 'bar',
    data: { labels: [], datasets: [{
      label: 'Distance (km)',
      data: [],
      backgroundColor: 'rgba(72,187,120,0.7)',
      borderColor: '#48bb78',
      borderWidth: 2, borderRadius: 4
    }]},
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: { y: { beginAtZero: true, title: { display: true, text: 'Distance (km)' } } },
      plugins: { legend: { display: false } }
    }
  });
}

function initialiseTypeChart() {
  const ctx = byId('typeChart').getContext('2d');
  typeChart = new Chart(ctx, {
    type: 'doughnut',
    data: { labels: [], datasets: [{
      data: [],
      backgroundColor: ['#3273dc','#48bb78','#ed64a6','#f56500','#805ad5','#38b2ac','#e53e3e'],
      borderWidth: 3, borderColor: '#ffffff'
    }]},
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, font: { size: 12 } } } }
    }
  });
}

function initialiseElevationChart() {
  const ctx = byId('elevationChart').getContext('2d');
  elevationChart = new Chart(ctx, {
    type: 'line',
    data: { labels: [], datasets: [{
      label: 'Total Elevation (m)',
      data: [],
      borderColor: '#805ad5',
      backgroundColor: 'rgba(128,90,213,0.1)',
      borderWidth: 3, fill: true, tension: 0.4,
      pointBackgroundColor: '#805ad5', pointBorderColor: '#fff',
      pointBorderWidth: 2, pointRadius: 4, yAxisID: 'y'
    }, {
      label: 'Total Duration (h)',
      data: [],
      borderColor: '#f56500',
      backgroundColor: 'rgba(245,101,0,0.1)',
      borderWidth: 3, fill: false, tension: 0.4,
      pointBackgroundColor: '#f56500', pointBorderColor: '#fff',
      pointBorderWidth: 2, pointRadius: 4, yAxisID: 'y1'
    }]},
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: {
        y:  { type: 'linear', display: true, position: 'left',  beginAtZero: true, title: { display: true, text: 'Elevation (m)' } },
        y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, title: { display: true, text: 'Duration (h)' }, grid: { drawOnChartArea: false } }
      },
      plugins: { legend: { display: true, position: 'top', labels: { usePointStyle: true, padding: 20 } } }
    }
  });
}

function updateCharts() {
  if (!activities || !activities.length) return clearAllCharts();

  // compute once
  const monthly = calculateMonthlyStats(activities, currentDateRange);

  // update charts
  setChart(countChart, monthly.labels, monthly.counts);
  setChart(distanceChart, monthly.labels, monthly.distances);
  updateTypeChart();
  elevationChart.data.labels = monthly.labels;
  elevationChart.data.datasets[0].data = monthly.elevations;
  elevationChart.data.datasets[1].data = monthly.durations;
  elevationChart.update();
}

function updateCountChart() {
  const m = calculateMonthlyStats(activities, currentDateRange);
  setChart(countChart, m.labels, m.counts);
}

function updateDistanceChart() {
  const m = calculateMonthlyStats(activities, currentDateRange);
  setChart(distanceChart, m.labels, m.distances);
}

function updateTypeChart() {
  // pie by type within range
  const filtered = filterActivitiesByDateRange(activities, currentDateRange);
  const counts = calculateTypeDistribution(filtered);
  typeChart.data.labels = Object.keys(counts);
  typeChart.data.datasets[0].data = Object.values(counts);
  typeChart.update();
}

function updateElevationChart() {
  const m = calculateMonthlyStats(activities, currentDateRange);
  elevationChart.data.labels = m.labels;
  elevationChart.data.datasets[0].data = m.elevations;
  elevationChart.data.datasets[1].data = m.durations;
  elevationChart.update();
}

function calculateMonthlyStats(activities, months) {
  const labels = [], counts = [], distances = [], elevations = [], durations = [];
  const now = new Date();

  for (let i = months - 1; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    labels.push(monthLabel(d));

    const monthActs = activities.filter(a => {
      const ad = new Date(a.date);
      return ad.getFullYear() === d.getFullYear() && ad.getMonth() === d.getMonth();
    });

    counts.push(monthActs.length);

    const dist = monthActs.reduce((s, a) => s + (parseFloat(a.distance) || 0), 0);
    distances.push(+dist.toFixed(1));

    const elev = monthActs.reduce((s, a) => s + (parseInt(a.elevation) || 0), 0);
    elevations.push(elev);

    const dur = monthActs.reduce((s, a) => s + (parseFloat(a.duration) || 0), 0);
    durations.push(+dur.toFixed(1));
  }

  return { labels, counts, distances, elevations, durations };
}

function filterActivitiesByDateRange(activities, months) {
  const cutoff = new Date();
  cutoff.setMonth(cutoff.getMonth() - months);
  return activities.filter(a => new Date(a.date) >= cutoff);
}

function calculateTypeDistribution(acts) {
  const acc = {};
  acts.forEach(a => {
    const t = a.type || 'Unknown';
    acc[t] = (acc[t] || 0) + 1;
  });
  return acc;
}

function clearAllCharts() {
  [countChart, distanceChart, typeChart, elevationChart].forEach(chart => {
    if (!chart) return;
    chart.data.labels = [];
    chart.data.datasets.forEach(d => d.data = []);
    chart.update();
  });
}
