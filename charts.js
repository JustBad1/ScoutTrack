// Chart Functionality
let countChart = null;
let distanceChart = null;
let typeChart = null;
let elevationChart = null;
let currentDateRange = 6; // Default to 6 months

function initialiseCharts() {
    initialiseCountChart();
    initialiseDistanceChart();
    initialiseTypeChart();
    initialiseElevationChart();
    
    // Set up date range selector
    document.getElementById('update-charts-btn').addEventListener('click', updateDateRange);
    document.getElementById('date-range-selector').addEventListener('change', updateDateRange);
}

function updateDateRange() {
    currentDateRange = parseInt(document.getElementById('date-range-selector').value);
    updateCharts();
}

function initialiseCountChart() {
    const ctx = document.getElementById('countChart').getContext('2d');
    countChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Activity Count',
                data: [],
                borderColor: '#3273dc',
                backgroundColor: 'rgba(50, 115, 220, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3273dc',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true,
                    title: { display: true, text: 'Number of Activities' },
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function initialiseDistanceChart() {
    const ctx = document.getElementById('distanceChart').getContext('2d');
    distanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Distance (km)',
                data: [],
                backgroundColor: 'rgba(72, 187, 120, 0.7)',
                borderColor: '#48bb78',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true,
                    title: { display: true, text: 'Distance (km)' }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function initialiseTypeChart() {
    const ctx = document.getElementById('typeChart').getContext('2d');
    typeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [
                    '#3273dc',
                    '#48bb78',
                    '#ed64a6',
                    '#f56500',
                    '#805ad5',
                    '#38b2ac',
                    '#e53e3e'
                ],
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

function initialiseElevationChart() {
    const ctx = document.getElementById('elevationChart').getContext('2d');
    elevationChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Total Elevation (m)',
                data: [],
                borderColor: '#805ad5',
                backgroundColor: 'rgba(128, 90, 213, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#805ad5',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                yAxisID: 'y'
            }, {
                label: 'Total Duration (h)',
                data: [],
                borderColor: '#f56500',
                backgroundColor: 'rgba(245, 101, 0, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: '#f56500',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: { display: true, text: 'Elevation (m)' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    title: { display: true, text: 'Duration (h)' },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
    });
}

function updateCharts() {
    if (!activities || activities.length === 0) {
        clearAllCharts();
        return;
    }

    updateCountChart();
    updateDistanceChart();
    updateTypeChart();
    updateElevationChart();
}

function updateCountChart() {
    const monthlyData = calculateMonthlyStats(activities, currentDateRange);
    countChart.data.labels = monthlyData.labels;
    countChart.data.datasets[0].data = monthlyData.counts;
    countChart.update();
}

function updateDistanceChart() {
    const monthlyData = calculateMonthlyStats(activities, currentDateRange);
    distanceChart.data.labels = monthlyData.labels;
    distanceChart.data.datasets[0].data = monthlyData.distances;
    distanceChart.update();
}

function updateTypeChart() {
    // Filter activities by date range for type chart
    const filteredActivities = filterActivitiesByDateRange(activities, currentDateRange);
    const typeCounts = calculateTypeDistribution(filteredActivities);
    typeChart.data.labels = Object.keys(typeCounts);
    typeChart.data.datasets[0].data = Object.values(typeCounts);
    typeChart.update();
}

function updateElevationChart() {
    const monthlyData = calculateMonthlyStats(activities, currentDateRange);
    elevationChart.data.labels = monthlyData.labels;
    elevationChart.data.datasets[0].data = monthlyData.elevations;
    elevationChart.data.datasets[1].data = monthlyData.durations;
    elevationChart.update();
}

function calculateMonthlyStats(activities, months) {
    const labels = [];
    const counts = [];
    const distances = [];
    const elevations = [];
    const durations = [];
    const now = new Date();

    for (let i = months - 1; i >= 0; i--) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const monthStr = date.toLocaleString('en-US', { 
            month: 'long', 
            year: 'numeric' 
        });
        labels.push(monthStr);

        const monthActivities = activities.filter(activity => {
            const activityDate = new Date(activity.date);
            return activityDate.getFullYear() === date.getFullYear() && 
                   activityDate.getMonth() === date.getMonth();
        });

        counts.push(monthActivities.length);
        
        const totalDistance = monthActivities.reduce((sum, activity) => {
            return sum + (parseFloat(activity.distance) || 0);
        }, 0);
        distances.push(parseFloat(totalDistance.toFixed(1)));

        const totalElevation = monthActivities.reduce((sum, activity) => {
            return sum + (parseInt(activity.elevation) || 0);
        }, 0);
        elevations.push(totalElevation);

        const totalDuration = monthActivities.reduce((sum, activity) => {
            return sum + (parseFloat(activity.duration) || 0);
        }, 0);
        durations.push(parseFloat(totalDuration.toFixed(1)));
    }

    return { labels, counts, distances, elevations, durations };
}

function filterActivitiesByDateRange(activities, months) {
    const cutoffDate = new Date();
    cutoffDate.setMonth(cutoffDate.getMonth() - months);
    
    return activities.filter(activity => {
        const activityDate = new Date(activity.date);
        return activityDate >= cutoffDate;
    });
}

function calculateTypeDistribution(activities) {
    const typeCounts = {};
    activities.forEach(activity => {
        const type = activity.type || 'Unknown';
        typeCounts[type] = (typeCounts[type] || 0) + 1;
    });
    return typeCounts;
}

function clearAllCharts() {
    [countChart, distanceChart, typeChart, elevationChart].forEach(chart => {
        if (chart) {
            chart.data.labels = [];
            chart.data.datasets.forEach(dataset => {
                dataset.data = [];
            });
            chart.update();
        }
    });
}