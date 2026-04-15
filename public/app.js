const API_URL = '../api/index.php';

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

dailyForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  dailyTableBody.innerHTML = '';
  showMessage(dailyMessage, '');

  if (!dailyDate.value) {
    showMessage(dailyMessage, 'Date is required.', true);
    return;
  }

  const result = await fetchJson({
    route: 'daily-workers',
    date: dailyDate.value
  });
  if (!result.ok) {
    showMessage(dailyMessage, result.error, true);
    return;
  }

  if (result.data.length === 0) {
    showMessage(dailyMessage, 'No rows for selected date.');
    return;
  }

  dailyTableBody.innerHTML = toRowsHtml(result.data);
});

rangeForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  rangeTableBody.innerHTML = '';
  showMessage(rangeMessage, '');

  if (!workerId.value || Number(workerId.value) <= 0) {
    showMessage(rangeMessage, 'Worker ID must be a positive number.', true);
    return;
  }
  if (!rangeFrom.value || !rangeTo.value) {
    showMessage(rangeMessage, 'Both dates are required.', true);
    return;
  }
  if (rangeFrom.value > rangeTo.value) {
    showMessage(rangeMessage, '"From" date must be before or equal to "To".', true);
    return;
  }

  const result = await fetchJson({
    route: 'worker-range',
    worker_id: workerId.value,
    from: rangeFrom.value,
    to: rangeTo.value
  });
  if (!result.ok) {
    showMessage(rangeMessage, result.error, true);
    return;
  }

  if (result.data.length === 0) {
    showMessage(rangeMessage, 'No rows in selected range.');
    return;
  }

  rangeTableBody.innerHTML = toRowsHtml(result.data);
});

async function fetchJson(query) {
  const params = new URLSearchParams(query);
  try {
    const httpResponse = await fetch(`${API_URL}?${params.toString()}`);
    const payload = await httpResponse.json();

    if (!httpResponse.ok || !payload.success) {
      return {
        ok: false,
        data: [],
        error: (payload.errors || ['Unknown error']).join(' | ')
      };
    }

    return { ok: true, data: payload.data, error: '' };
  } catch (error) {
    return { ok: false, data: [], error: `Request failed: ${error.message}` };
  }
}

function toRowsHtml(rows) {
  return rows
    .map((row) => `
      <tr>
        <td>${row.worker_id}</td>
        <td>${row.worker_name || '-'}</td>
        <td>${row.date}</td>
        <td>${(row.job_ids || []).join(', ') || '-'}</td>
        <td>${row.duration}</td>
      </tr>
    `)
    .join('');
}

function showMessage(el, text, isError = false) {
  el.textContent = text;
  el.classList.toggle('error', isError);
}
