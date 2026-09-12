const pmData = document.getElementById('pm-data');

const findings = pmData ? JSON.parse(pmData.dataset.findings || '{}') : {};
const spareparts = pmData ? JSON.parse(pmData.dataset.spareparts || '[]') : [];
const bigProblems = pmData ? JSON.parse(pmData.dataset.bigProblems || '[]') : [];

let problemIndex = pmData ? Number(pmData.dataset.problemIndex || 0) : 0;
let sparepartIndex = pmData ? Number(pmData.dataset.sparepartIndex || 0) : 0;
let initialized = false;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function buildProblemRow(index) {
    const options = bigProblems
        .map((problem) => {
            const category = escapeHtml((problem.category || '').trim().toLowerCase());
            const label = escapeHtml(problem.problem || '');

            return `<option value="${escapeHtml(problem.id)}" data-category="${category}">${label}</option>`;
        })
        .join('');

    return `
        <div class="problem-row mb-2 flex gap-2 max-sm:mb-3 max-sm:flex-col max-sm:rounded-xl max-sm:border max-sm:border-gray-200 max-sm:bg-gray-50 max-sm:p-3">
            <select name="problems[${index}][problem]" class="problem-select w-1/2 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full">
                <option value="">-- Select Problem --</option>
                ${options}
            </select>

            <select name="problems[${index}][finding]" class="finding-select flex-1 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full">
                <option value="">-- Finding --</option>
            </select>

            <select name="problems[${index}][severity]" class="w-1/5 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full">
                <option value="">-- Severity --</option>
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
            </select>

            <button type="button" onclick="removeProblem(this)" class="rounded-xl bg-red-500 px-3 py-2 text-white transition hover:bg-red-600 max-sm:w-full max-sm:py-2">
                X
            </button>
        </div>
    `;
}

function buildSparepartRow(index) {
    return `
        <div class="sparepart-row mb-2 flex gap-2 max-sm:mb-3 max-sm:flex-col max-sm:rounded-xl max-sm:border max-sm:border-gray-200 max-sm:bg-gray-50 max-sm:p-3">
            <select name="spareparts[${index}][sparepart_id]" placeholder="Select Sparepart" class="sparepart-select w-2/3 rounded-xl border border-gray-300 p-2 text-sm max-sm:w-full"></select>

            <input type="number" name="spareparts[${index}][qty]" placeholder="Qty" class="w-1/3 rounded-xl border border-gray-300 p-3 text-sm max-sm:w-full">

            <button type="button" onclick="removeSparepart(this)" class="rounded-xl bg-red-500 px-3 py-2 text-white transition hover:bg-red-600 max-sm:w-full max-sm:py-2">
                X
            </button>
        </div>
    `;
}

window.addProblem = function () {
    const wrapper = document.getElementById('problem-wrapper');

    if (!wrapper) {
        return;
    }

    wrapper.insertAdjacentHTML('beforeend', buildProblemRow(problemIndex));
    problemIndex++;
    syncProblemOptions();
};

window.removeProblem = function (button) {
    const rows = document.querySelectorAll('.problem-row');

    if (rows.length > 1 && button?.parentElement) {
        button.parentElement.remove();
    }

    syncProblemOptions();
};

function loadFinding(problemSelect) {
    const option = problemSelect.selectedOptions[0];

    if (!option || !option.dataset.category) {
        return;
    }

    const category = option.dataset.category.trim().toLowerCase();
    const row = problemSelect.closest('.problem-row');
    const findingSelect = row?.querySelector('.finding-select');

    if (!findingSelect) {
        return;
    }

    const oldFinding = findingSelect.dataset.oldFinding;

    findingSelect.innerHTML = '<option value="">-- Finding --</option>';

    if (findings[category]) {
        findings[category].forEach(function (item) {
            const selected = item.id == oldFinding ? 'selected' : '';

            findingSelect.innerHTML += `
                <option value="${item.id}" ${selected}>
                    ${item.finding}
                </option>
            `;
        });
    }
}

function initProblemFindings() {
    document.querySelectorAll('.problem-select').forEach(function (select) {
        if (select.value) {
            loadFinding(select);
        }
    });
}

// Prevent picking the same problem (e.g. "Capstan 1") in more than one row:
// disable that <option> in every OTHER .problem-select once it's chosen in
// one row. The row that currently holds the value keeps it enabled for
// itself, so pre-existing duplicate data (saved before this rule existed)
// doesn't get silently broken — it just can't be re-picked once changed.
function syncProblemOptions() {
    const selects = Array.from(document.querySelectorAll('.problem-select'));
    const selectedValues = selects
        .map(function (select) { return select.value; })
        .filter(function (value) { return value !== ''; });

    selects.forEach(function (select) {
        Array.from(select.options).forEach(function (option) {
            if (option.value === '') {
                return;
            }

            option.disabled = selectedValues.includes(option.value) && option.value !== select.value;
        });
    });
}

function calculateDuration() {
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');
    const durationInput = document.getElementById('duration');
    const errorText = document.getElementById('duration_error');

    if (!startInput || !endInput || !durationInput || !errorText) {
        return;
    }

    if (!startInput.value || !endInput.value) {
        return;
    }

    const start = startInput.value.split(':');
    const end = endInput.value.split(':');

    const startMinutes = parseInt(start[0], 10) * 60 + parseInt(start[1], 10);
    const endMinutes = parseInt(end[0], 10) * 60 + parseInt(end[1], 10);

    if (endMinutes < startMinutes) {
        durationInput.value = '';
        errorText.classList.remove('hidden');
        endInput.classList.add('border-red-500');
        return;
    }

    errorText.classList.add('hidden');
    endInput.classList.remove('border-red-500');

    const diff = endMinutes - startMinutes;
    const hours = Math.floor(diff / 60);
    const minutes = diff % 60;

    durationInput.value = `${hours} Hours ${minutes} Minutes`;
}

function initTomSelect(element) {
    if (!window.TomSelect) {
        return;
    }

    if (element.tomselect) {
        element.tomselect.destroy();
    }

    new window.TomSelect(element, {
        valueField: 'id',
        labelField: 'material_number',
        searchField: [
            'location',
            'material_number',
            'description',
            'remarks'
        ],
        options: spareparts,
        items: element.dataset.selected ? [element.dataset.selected] : [],
        create: false,
        maxOptions: 100,
        render: {
            option: function (item, escape) {
                return `
                    <div style="padding:8px">
                        <div style="font-size:12px;color:#6b7280">
                            📍 ${escape(item.location ?? '-')}
                        </div>
                        <div style="font-weight:700">
                            ${escape(item.material_number)}
                        </div>
                        <div>
                            ${escape(item.description)}
                        </div>
                        <div style="font-size:12px;color:#6b7280">
                            ${escape(item.remarks ?? '-')}
                        </div>
                    </div>
                `;
            },
            item: function (item, escape) {
                return `
                    <div>
                        ${escape(item.material_number)}
                        -
                        ${escape(item.description)}
                    </div>
                `;
            }
        }
    });
}

function initSpareparts() {
    document.querySelectorAll('.sparepart-select').forEach(function (el) {
        initTomSelect(el);
    });
}

window.addSparepart = function () {
    const wrapper = document.getElementById('sparepart-wrapper');

    if (!wrapper) {
        return;
    }

    wrapper.insertAdjacentHTML('beforeend', buildSparepartRow(sparepartIndex));

    const selects = wrapper.querySelectorAll('.sparepart-select');

    if (selects.length) {
        initTomSelect(selects[selects.length - 1]);
    }

    sparepartIndex++;
};

window.removeSparepart = function (button) {
    button?.closest('.sparepart-row')?.remove();
};

function loadTomSelectScript() {
    if (window.TomSelect) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function initPage() {
    if (initialized) {
        return;
    }

    initialized = true;

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('problem-select')) {
            loadFinding(event.target);
            syncProblemOptions();
        }

        // sessions change handlers
        if (event.target.classList.contains('session-start') || event.target.classList.contains('session-end')) {
            const row = event.target.closest('.session-row');
            if (row) {
                calculateSessionDuration(row);
                updateAllSessionDurations();
            }
        }
    });

    initProblemFindings();
    syncProblemOptions();

    // migrate single start/end inputs if present (legacy); keep compatibility but not required for multi-day
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');

    startInput?.addEventListener('change', calculateDuration);
    endInput?.addEventListener('change', calculateDuration);

    initSpareparts();

    // sessions add/remove handlers
    document.getElementById('add-session')?.addEventListener('click', addSession);
    document.querySelectorAll('.remove-session').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = btn.closest('.session-row');
            row?.remove();
            updateAllSessionDurations();
        });
    });

    // initial calculation for existing rows
    document.querySelectorAll('.session-row').forEach(function (row) {
        calculateSessionDuration(row);
    });
    updateAllSessionDurations();
}

// --- session helpers ---
function parseTimeToMinutes(value) {
    if (!value) return null;
    const parts = value.split(':');
    if (parts.length !== 2) return null;
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
}

function calculateSessionDuration(row) {
    const start = row.querySelector('.session-start')?.value || null;
    const end = row.querySelector('.session-end')?.value || null;
    const display = row.querySelector('.session-duration');

    if (!start || !end) {
        if (display) display.textContent = '-';
        row.dataset.duration = '';
        return;
    }

    const s = parseTimeToMinutes(start);
    const e = parseTimeToMinutes(end);

    if (s === null || e === null) {
        if (display) display.textContent = '-';
        row.dataset.duration = '';
        return;
    }

    let diff = e - s;
    if (diff < 0) {
        diff += 24 * 60; // assume next day
    }

    const hours = Math.floor(diff / 60);
    const minutes = diff % 60;

    if (display) display.textContent = `${hours} Hours ${minutes} Minutes`;
    row.dataset.duration = diff.toString();
}

function updateAllSessionDurations() {
    const rows = document.querySelectorAll('.session-row');
    let total = 0;
    rows.forEach(function (row) {
        const d = parseInt(row.dataset.duration || '0', 10);
        if (!isNaN(d)) total += d;
    });

    const totalEl = document.getElementById('total-duration');
    if (totalEl) {
        const hours = Math.floor(total / 60);
        const minutes = total % 60;
        if (total === 0) {
            totalEl.textContent = totalEl.dataset.initial || '';
        } else {
            totalEl.textContent = `${hours} Hours ${minutes} Minutes`;
        }
    }
}

function addSession() {
    const container = document.getElementById('work-sessions');
    if (!container) return;

    const index = container.querySelectorAll('.session-row').length;

    const html = `
        <div class="session-row rounded-lg border p-3" data-index="${index}">
            <input type="hidden" name="sessions[${index}][id]" value="">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="text-xs">Date</label>
                    <input type="date" name="sessions[${index}][actual_date]" value="" class="w-full border rounded-2xl p-2">
                </div>
                <div>
                    <label class="text-xs">Start</label>
                    <input type="time" name="sessions[${index}][start_time]" value="" class="w-full border rounded-2xl p-2 session-start">
                </div>
                <div>
                    <label class="text-xs">End</label>
                    <input type="time" name="sessions[${index}][end_time]" value="" class="w-full border rounded-2xl p-2 session-end">
                </div>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <div class="text-sm text-slate-600">Duration: <span class="session-duration">-</span></div>
                <div>
                    <button type="button" class="remove-session inline-flex items-center rounded-lg bg-red-500 px-3 py-1 text-white">Remove Day</button>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', html);

    const newRow = container.querySelector('.session-row[data-index="' + index + '"]');
    if (newRow) {
        newRow.querySelector('.remove-session').addEventListener('click', function () {
            newRow.remove();
            updateAllSessionDurations();
        });
        newRow.querySelector('.session-start')?.addEventListener('change', function () { calculateSessionDuration(newRow); updateAllSessionDurations(); });
        newRow.querySelector('.session-end')?.addEventListener('change', function () { calculateSessionDuration(newRow); updateAllSessionDurations(); });
    }
}


if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        loadTomSelectScript().then(initPage).catch(() => {
            initPage();
        });
    });
} else {
    loadTomSelectScript().then(initPage).catch(() => {
        initPage();
    });
}
