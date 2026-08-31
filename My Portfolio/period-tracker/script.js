document.addEventListener('DOMContentLoaded', () => {
    // 1. Load Cycle Data for Home & Tracker Pages
    loadCycleData();

    // 2. Tracker Page: Form Submit
    const cycleForm = document.getElementById('advancedCycleForm');
    if (cycleForm) {
        cycleForm.addEventListener('submit', (e) => {
            e.preventDefault();
            saveCycleSettings();
        });
    }

    // 3. Tracker Page: Preference Toggles
    const notifyPeriod = document.getElementById('notifyPeriod');
    const notifyHydration = document.getElementById('notifyHydration');
    if (notifyPeriod && notifyHydration) {
        loadPreferences();
        notifyPeriod.addEventListener('change', savePreferences);
        notifyHydration.addEventListener('change', savePreferences);
    }

    // 4. Daily Log Page: Form Handling
    const logForm = document.getElementById('dailyLogForm');
    if (logForm) {
        const logDateInput = document.getElementById('logDate');
        if (logDateInput) {
            logDateInput.value = new Date().toISOString().split('T')[0];
        }
        logForm.addEventListener('submit', (e) => {
            e.preventDefault();
            saveDailyLog();
        });
    }

    // 5. Calendar Page Initialization
    if (document.getElementById('calendar')) {
        initCalendar();
    }

    // 6. Analytics Page Initialization (Real-Time Chart & Dynamic Table)
    if (document.getElementById('waterChart') || document.getElementById('historyTableBody')) {
        initChart();
        loadLogs();
    }
});

// ==========================================
// 🗓️ ADVANCED INTERACTIVE CALENDAR LOGIC
// ==========================================

function initCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const events = [];

    // 1. Fetch Cycle Settings and Generate Multi-Month Predictions
    const savedCycle = localStorage.getItem('flowcare_cycle_data');
    if (savedCycle) {
        const { startDate, cycleDays, periodDuration } = JSON.parse(savedCycle);
        let start = new Date(startDate);

        for (let i = -3; i < 9; i++) {
            let cycleStart = new Date(start);
            cycleStart.setDate(start.getDate() + (i * cycleDays));

            let periodEnd = new Date(cycleStart);
            periodEnd.setDate(cycleStart.getDate() + periodDuration);

            // Period Phase Event
            events.push({
                title: '🩸 Period',
                start: cycleStart.toISOString().split('T')[0],
                end: periodEnd.toISOString().split('T')[0],
                backgroundColor: '#ff3366',
                textColor: '#ffffff',
                description: 'Expected Menstrual Flow Days'
            });

            // Ovulation Day
            let ovulation = new Date(cycleStart);
            ovulation.setDate(cycleStart.getDate() + (cycleDays - 14));

            events.push({
                title: '🥚 Ovulation',
                start: ovulation.toISOString().split('T')[0],
                backgroundColor: '#ffb703',
                textColor: '#0f111a',
                description: 'Peak Fertility Day'
            });

            // Fertile Window
            let fertileStart = new Date(ovulation);
            fertileStart.setDate(ovulation.getDate() - 5);
            let fertileEnd = new Date(ovulation);
            fertileEnd.setDate(ovulation.getDate() + 2);

            events.push({
                title: '🌱 Fertile Window',
                start: fertileStart.toISOString().split('T')[0],
                end: fertileEnd.toISOString().split('T')[0],
                backgroundColor: '#00cc88',
                textColor: '#ffffff',
                description: 'High Chance of Conception'
            });
        }
    }

    // 2. Fetch User Daily Logs
    const savedLogs = localStorage.getItem('flowcare_logs');
    if (savedLogs) {
        const logs = JSON.parse(savedLogs);
        logs.forEach(log => {
            events.push({
                title: `📝 ${log.mood}`,
                start: log.date,
                backgroundColor: '#3a86ef',
                textColor: '#ffffff',
                description: `Symptoms: ${log.symptoms ? log.symptoms.join(', ') : 'None'} | Water: ${log.water || 0}L`
            });
        });
    }

    // 3. Render Calendar Instance
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        events: events,
        height: 'auto',
        eventClick: function(info) {
            const desc = info.event.extendedProps.description || 'No additional details.';
            alert(`📌 ${info.event.title}\n📅 Date: ${info.event.start.toDateString()}\nℹ️ Info: ${desc}`);
        }
    });

    calendar.render();
}

// ==========================================
// 🩸 CYCLE CALCULATIONS & TRACKER LOGIC
// ==========================================

function saveCycleSettings() {
    const startDate = document.getElementById('homeStartDate').value;
    const cycleDays = parseInt(document.getElementById('homeCycleDays').value);
    const periodDuration = parseInt(document.getElementById('periodDuration').value);
    const isIrregular = document.getElementById('irregularToggle') ? document.getElementById('irregularToggle').checked : false;

    if (!startDate) {
        alert('Please select your last period start date!');
        return;
    }

    const cycleData = { startDate, cycleDays, periodDuration, isIrregular };
    localStorage.setItem('flowcare_cycle_data', JSON.stringify(cycleData));
    loadCycleData();
    alert('Cycle settings updated successfully! 🌸');
}

function loadCycleData() {
    const savedData = localStorage.getItem('flowcare_cycle_data');
    if (!savedData) return;

    const { startDate, cycleDays, periodDuration } = JSON.parse(savedData);

    if (document.getElementById('homeStartDate')) {
        document.getElementById('homeStartDate').value = startDate;
        document.getElementById('homeCycleDays').value = cycleDays;
        document.getElementById('periodDuration').value = periodDuration;
    }

    const lastPeriod = new Date(startDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const tempLastPeriod = new Date(lastPeriod);
    tempLastPeriod.setHours(0, 0, 0, 0);

    const nextPeriod = new Date(tempLastPeriod);
    nextPeriod.setDate(tempLastPeriod.getDate() + cycleDays);

    const diffTime = nextPeriod - today;
    const daysLeft = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    const ovulationDate = new Date(nextPeriod);
    ovulationDate.setDate(nextPeriod.getDate() - 14);

    const fertileStart = new Date(ovulationDate);
    fertileStart.setDate(ovulationDate.getDate() - 5);

    const fertileEnd = new Date(ovulationDate);
    fertileEnd.setDate(ovulationDate.getDate() + 1);

    const dayDiff = Math.floor((today - tempLastPeriod) / (1000 * 60 * 60 * 24)) + 1;
    let currentPhase = "Follicular Phase";

    if (dayDiff > 0 && dayDiff <= periodDuration) {
        currentPhase = "Menstrual Phase";
    } else if (dayDiff >= (cycleDays - 16) && dayDiff <= (cycleDays - 12)) {
        currentPhase = "Ovulation Phase";
    } else if (dayDiff > (cycleDays - 12)) {
        currentPhase = "Luteal Phase";
    }

    if (document.getElementById('homeDaysLeft')) {
        document.getElementById('homeDaysLeft').innerText = daysLeft >= 0 ? `${daysLeft} Days` : 'Due Today / Late';
    }
    if (document.getElementById('homeNextDate')) {
        document.getElementById('homeNextDate').innerText = nextPeriod.toDateString();
    }
    if (document.getElementById('homeOvulationDate')) {
        document.getElementById('homeOvulationDate').innerText = ovulationDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    if (document.getElementById('fertileWindowDisplay')) {
        document.getElementById('fertileWindowDisplay').innerText = `Fertile: ${fertileStart.getDate()} - ${fertileEnd.getDate()} ${fertileEnd.toLocaleDateString('en-US', { month: 'short' })}`;
    }
    if (document.getElementById('currentPhaseDisplay')) {
        document.getElementById('currentPhaseDisplay').innerText = currentPhase;
    }
    if (document.getElementById('cycleDayDisplay')) {
        document.getElementById('cycleDayDisplay').innerText = `Day ${dayDiff > 0 ? dayDiff : 1} of ${cycleDays}`;
    }
}

// ==========================================
// ⚙️ DATA MANAGEMENT & PREFERENCES
// ==========================================

function savePreferences() {
    const prefs = {
        notifyPeriod: document.getElementById('notifyPeriod') ? document.getElementById('notifyPeriod').checked : false,
        notifyHydration: document.getElementById('notifyHydration') ? document.getElementById('notifyHydration').checked : false
    };
    localStorage.setItem('flowcare_prefs', JSON.stringify(prefs));
}

function loadPreferences() {
    const savedPrefs = localStorage.getItem('flowcare_prefs');
    if (!savedPrefs) return;

    const prefs = JSON.parse(savedPrefs);
    if (document.getElementById('notifyPeriod')) {
        document.getElementById('notifyPeriod').checked = prefs.notifyPeriod;
    }
    if (document.getElementById('notifyHydration')) {
        document.getElementById('notifyHydration').checked = prefs.notifyHydration;
    }
}

function exportData() {
    const allData = {
        cycleData: JSON.parse(localStorage.getItem('flowcare_cycle_data') || '{}'),
        logs: JSON.parse(localStorage.getItem('flowcare_logs') || '[]'),
        preferences: JSON.parse(localStorage.getItem('flowcare_prefs') || '{}')
    };

    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(allData, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", `FlowCare_Backup_${new Date().toISOString().split('T')[0]}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
}

function clearAllData() {
    if (confirm("Are you sure you want to reset all saved cycle data and logs?")) {
        localStorage.clear();
        alert("All local data has been reset.");
        location.reload();
    }
}

// ==========================================
// 📝 DAILY LOG FUNCTIONALITY
// ==========================================

function saveDailyLog() {
    const date = document.getElementById('logDate').value;
    const mood = document.getElementById('logMood') ? document.getElementById('logMood').value : '😊 Happy';
    const water = document.getElementById('logWater') ? parseFloat(document.getElementById('logWater').value) || 0 : 0;
    
    // Checked Symptoms Extraction
    const symptomElements = document.querySelectorAll('.symptom:checked');
    const symptoms = Array.from(symptomElements).map(el => el.value);

    if (!date) {
        alert('Please select a date!');
        return;
    }

    const logEntry = { date, mood, symptoms, water };
    let logs = JSON.parse(localStorage.getItem('flowcare_logs') || '[]');

    logs = logs.filter(item => item.date !== date);
    logs.push(logEntry);

    localStorage.setItem('flowcare_logs', JSON.stringify(logs));
    alert('Daily log saved successfully! ✨');
}

// ==========================================
// 📊 REAL-TIME ANALYTICS & HISTORY LOGIC
// ==========================================

let waterChartInstance = null;

function initChart() {
    const ctx = document.getElementById('waterChart');
    if (!ctx) return;

    const savedLogs = JSON.parse(localStorage.getItem('flowcare_logs') || '[]');
    
    // Sort logs by date ascending
    savedLogs.sort((a, b) => new Date(a.date) - new Date(b.date));

    const labels = savedLogs.map(log => log.date);
    const dataValues = savedLogs.map(log => log.water || 0);

    // Destroy existing instance to prevent re-render overlaps
    if (waterChartInstance) {
        waterChartInstance.destroy();
    }

    const chartContext = ctx.getContext('2d');
    
    // Radiant Neon Gradient Accent
    const fillGradient = chartContext.createLinearGradient(0, 0, 0, 300);
    fillGradient.addColorStop(0, 'rgba(58, 134, 239, 0.4)');
    fillGradient.addColorStop(1, 'rgba(58, 134, 239, 0.0)');

    waterChartInstance = new Chart(chartContext, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['No Data Recorded'],
            datasets: [{
                label: 'Water Intake (Liters)',
                data: dataValues.length ? dataValues : [0],
                borderColor: '#3a86ef',
                backgroundColor: fillGradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ff3366',
                pointBorderColor: '#ffffff',
                pointRadius: 5,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.08)' },
                    ticks: { color: '#a0a5b5' }
                },
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.08)' },
                    ticks: { color: '#a0a5b5' }
                }
            },
            plugins: {
                legend: {
                    labels: { color: '#ffffff', font: { family: 'Inter', size: 13 } }
                }
            }
        }
    });
}

function loadLogs() {
    const historyTableBody = document.getElementById('historyTableBody');
    if (!historyTableBody) return;

    const savedLogs = JSON.parse(localStorage.getItem('flowcare_logs') || '[]');
    
    // Sort logs by date descending for recent entries first
    savedLogs.sort((a, b) => new Date(b.date) - new Date(a.date));

    historyTableBody.innerHTML = '';

    if (savedLogs.length === 0) {
        historyTableBody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; color: #a0a5b5; padding: 20px;">
                    No daily logs found. Start logging from the Daily Log page! ✨
                </td>
            </tr>
        `;
        return;
    }

    savedLogs.forEach(log => {
        const row = document.createElement('tr');
        const symptomDisplay = log.symptoms && log.symptoms.length > 0 ? log.symptoms.join(', ') : 'None';
        
        row.innerHTML = `
            <td>${log.date}</td>
            <td>${log.mood || 'N/A'}</td>
            <td>${symptomDisplay}</td>
            <td><strong>${log.water || 0} L</strong></td>
        `;
        historyTableBody.appendChild(row);
    });
}