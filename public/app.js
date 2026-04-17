const API_URL = '../api/index.php';

// Elements
const dailyForm = document.querySelector('#daily-workers-form');
const dailyDate = document.querySelector('#daily-date');
const dailyTableBody = document.querySelector('#daily-table tbody');
const dailyMessage = document.querySelector('#daily-message');

const rangeForm = document.querySelector('#worker-range-form');
const workerId = document.querySelector('#worker-id');
const rangeFrom = document.querySelector('#range-from');
const rangeTo = document.querySelector('#range-to');
const rangeTableBody = document.querySelector('#range-table tbody');
const rangeMessage = document.querySelector('#range-message');

const availableDates = new Set();
let availableMin = '';
let availableMax = '';

// Init
void initAvailableDates();

// -------------------- INIT --------------------

async function initAvailableDates() {
    const result = await fetchJson({route: 'available-dates'});

    if (!result.ok || !Array.isArray(result.data) || result.data.length === 0) {
        return;
    }

    result.data.forEach(d => availableDates.add(d));

    availableMin = result.data[0];
    availableMax = result.data[result.data.length - 1];

    // Apply limits
    [dailyDate, rangeFrom, rangeTo].forEach(input => {
        input.min = availableMin;
        input.max = availableMax;
    });
}

// -------------------- EVENTS --------------------

rangeFrom.addEventListener('change', () => {
    rangeTo.min = rangeFrom.value || availableMin;
});

rangeTo.addEventListener('change', () => {
    rangeFrom.max = rangeTo.value || availableMax;
});

dailyForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    dailyTableBody.innerHTML = '';
    showMessage(dailyMessage, '');

    if (!dailyDate.value) {
        return showMessage(dailyMessage, 'Date is required.', true);
    }

    if (!isAllowedDate(dailyDate.value)) {
        return showMessage(dailyMessage, 'Selected date is not available in data.', true);
    }

    const result = await fetchJson({
        route: 'daily-workers',
        date: dailyDate.value
    });

    if (!result.ok) {
        return showMessage(dailyMessage, result.error, true);
    }

    if (result.data.length === 0) {
        return showMessage(dailyMessage, 'No rows for selected date.');
    }

    dailyTableBody.innerHTML = toRowsHtml(result.data);
});

rangeForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    rangeTableBody.innerHTML = '';
    showMessage(rangeMessage, '');

    if (!workerId.value.trim()) {
        return showMessage(rangeMessage, 'Worker ID is required.', true);
    }

    if (!rangeFrom.value || !rangeTo.value) {
        return showMessage(rangeMessage, 'Both dates are required.', true);
    }

    if (rangeFrom.value > rangeTo.value) {
        return showMessage(rangeMessage, '"From" date must be before or equal to "To".', true);
    }

    if (!isAllowedDate(rangeFrom.value) || !isAllowedDate(rangeTo.value)) {
        return showMessage(rangeMessage, 'From/To must be selected from available dates.', true);
    }

    const result = await fetchJson({
        route: 'worker-range',
        worker_id: workerId.value.padStart(4, '0'),
        from: rangeFrom.value,
        to: rangeTo.value
    });

    if (!result.ok) {
        return showMessage(rangeMessage, result.error, true);
    }

    if (result.data.length === 0) {
        return showMessage(rangeMessage, 'No rows in selected range.');
    }

    rangeTableBody.innerHTML = toRowsHtml(result.data);
});

// -------------------- HELPERS --------------------

function isAllowedDate(value) {
    if (availableDates.size === 0) return true;
    return availableDates.has(value);
}

async function fetchJson(query) {
    const params = new URLSearchParams(query);

    try {
        const response = await fetch(`${API_URL}?${params}`);
        const payload = await response.json();

        if (!response.ok || !payload.success) {
            return {
                ok: false,
                data: [],
                error: (payload.errors || ['Unknown error']).join(' | ')
            };
        }

        return {ok: true, data: payload.data, error: ''};

    } catch (error) {
        return {
            ok: false,
            data: [],
            error: `Request failed: ${error.message}`
        };
    }
}

function toRowsHtml(rows) {
    return rows.map(row => `
        <tr>
            <td>${row.worker_id}</td>
            <td>${row.worker_name || '-'}</td>
            <td>${row.date}</td>
            <td>${(row.job_ids || []).join(', ') || '-'}</td>
            <td>${row.duration}</td>
        </tr>
    `).join('');
}

function showMessage(el, text, isError = false) {
    el.textContent = text;
    el.classList.toggle('error', isError);
}