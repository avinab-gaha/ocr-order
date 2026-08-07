<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Document Extraction - {{ config('app.name', 'Laravel') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --accent-h: 210; --accent-s: 80%; --accent-l: 50%;
            --accent: hsl(var(--accent-h), var(--accent-s), var(--accent-l));
            --low-conf: #f59e0b;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .app-header {
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .card-split {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
            overflow: hidden;
            background: #fff;
        }
        .card-split .left-pane {
            background: linear-gradient(180deg, #fafbfc, #f0f2f5);
            padding: 2rem;
            border-right: 1px solid rgba(0,0,0,.05);
        }
        .card-split .right-pane {
            padding: 2rem;
        }
        .drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 1rem;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all .25s ease;
            background: rgba(255,255,255,.6);
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(255,255,255,.9);
            box-shadow: 0 0 0 4px hsla(var(--accent-h), var(--accent-s), var(--accent-l), .15);
        }
        .drop-zone .icon {
            font-size: 3rem;
            color: #94a3b8;
            margin-bottom: .75rem;
        }
        .preview-container img, .preview-container embed {
            max-width: 100%;
            max-height: 400px;
            border-radius: .75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
        }
        .tab-content {
            padding-top: 1.25rem;
        }
        .json-viewer {
            background: #1e293b;
            color: #e2e8f0;
            border-radius: .75rem;
            padding: 1.25rem;
            font-family: 'JetBrains Mono', 'Cascadia Code', 'Fira Code', monospace;
            font-size: .85rem;
            line-height: 1.6;
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .missing-field {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: .6rem 1rem;
            border-radius: .5rem;
            margin-bottom: .5rem;
            font-size: .9rem;
        }
        .stat-card {
            border-radius: .75rem;
            padding: 1rem 1.25rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .stat-card .label {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
        }
        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
        }
        .nav-tabs .nav-link {
            color: #475569;
            font-weight: 500;
            border: none;
            padding: .6rem 1rem;
        }
        .nav-tabs .nav-link.active {
            color: var(--accent);
            border-bottom: 2px solid var(--accent);
            background: transparent;
        }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
        }
        .btn-primary:hover {
            filter: brightness(1.08);
        }
        .field-low-confidence {
            border: 2px solid var(--low-conf);
            background: #fffbeb;
        }
        .field-low-confidence:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
            border-color: var(--low-conf);
        }
        .conf-note {
            font-size: .75rem;
            color: var(--low-conf);
            font-weight: 500;
            margin-top: .15rem;
        }
        #progress-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,.7);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        #progress-overlay.show {
            display: flex;
        }
        .spinner-lg {
            width: 3rem; height: 3rem;
            border-width: .35rem;
        }
        .overview-section h6 {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: .75rem;
        }
        .overview-section {
            margin-bottom: 1.5rem;
        }
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: .75rem;
        }
        .overview-grid .mb-2 {
            margin-bottom: 0 !important;
        }
        .overview-grid input {
            font-size: .875rem;
        }
        .item-table input, .item-table .conf-note {
            font-size: .85rem;
        }
        .item-table th {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
        }
        .confirm-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #dcfce7;
            color: #166534;
            padding: .5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: .9rem;
        }
        .confirm-badge a {
            color: #166534;
            text-decoration: underline;
        }
        .saved-id-display {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: .75rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div id="progress-overlay">
    <div class="spinner-border spinner-lg text-primary mb-3" role="status"></div>
    <p class="text-muted fw-semibold">Processing document...</p>
</div>

<header class="app-header">
    <div class="container d-flex align-items-center justify-content-between">
        <h4 class="mb-0 fw-bold">
            <span style="color:var(--accent)">&#9632;</span> Document Extraction
        </h4>
        <small class="text-muted">OCR + LLM Pipeline</small>
    </div>
</header>

<main class="container pb-5">
    <div class="card card-split">
        <div class="row g-0">
            <div class="col-lg-5 left-pane">
                <h5 class="fw-semibold mb-3">Upload Document</h5>
                <div class="drop-zone" id="dropZone">
                    <div class="icon">&#128196;</div>
                    <p class="mb-1 fw-medium">Drag & drop or click to browse</p>
                    <p class="text-muted small mb-0">JPG, JPEG, PNG or PDF &middot; max 10 MB</p>
                    <input type="file" id="fileInput" accept=".jpg,.jpeg,.png,.pdf" class="d-none">
                </div>

                <div class="mt-3">
                    <label class="form-label fw-medium small text-muted text-uppercase">LLM Provider</label>
                    <select id="llmProvider" class="form-select form-select-sm">
                        <option value="">Default ({{ config('services.llm.default', 'openai') }})</option>
                        <option value="openai">OpenAI</option>
                        <option value="gemini">Gemini</option>
                        <option value="ollama">Ollama</option>
                    </select>
                </div>

                <div id="previewContainer" class="preview-container mt-4 d-none">
                    <h6 class="fw-semibold mb-2 small text-muted text-uppercase">Preview</h6>
                    <div id="previewInner"></div>
                </div>
            </div>

            <div class="col-lg-7 right-pane">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-semibold mb-0">Results</h5>
                    <span id="statusBadge" class="badge bg-secondary">Awaiting Input</span>
                </div>

                <div id="statsRow" class="row g-2 mb-3 d-none">
                    <div class="col-3">
                        <div class="stat-card text-center">
                            <div class="label">Items</div>
                            <div class="value" id="statItems">0</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-card text-center">
                            <div class="label">Fields</div>
                            <div class="value" id="statFields">0</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-card text-center">
                            <div class="label">Missing</div>
                            <div class="value" id="statMissing">0</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-card text-center">
                            <div class="label">Flagged</div>
                            <div class="value" id="statFlagged">0</div>
                        </div>
                    </div>
                </div>

                <div id="llmWarning" class="alert alert-warning d-none mt-3" role="alert"></div>
                <div id="missingFieldsContainer" class="d-none mb-3"></div>

                <ul class="nav nav-tabs" id="resultTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview">Overview</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-json">Pretty JSON</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-text">OCR Text</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-overview">
                        <div id="overviewContent">
                            <p class="text-muted">Upload a document to review extracted data.</p>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-json">
                        <div class="d-flex justify-content-end mb-2">
                            <button id="copyBtn" class="btn btn-sm btn-outline-secondary">&#128203; Copy</button>
                        </div>
                        <div id="jsonViewer" class="json-viewer text-muted">Upload a document to see extracted data.</div>
                    </div>
                    <div class="tab-pane fade" id="tab-text">
                        <pre id="ocrTextViewer" class="json-viewer text-muted" style="white-space:pre-wrap">Upload a document to see OCR text.</pre>
                    </div>
                </div>

                <div id="confirmSection" class="d-none mt-3 pt-3 border-top">
                    <button id="confirmBtn" class="btn btn-success btn-lg w-100">
                        &#10003; Confirm & Save
                    </button>
                </div>

                <div id="savedSection" class="d-none mt-3"></div>

                <div id="errorAlert" class="alert alert-danger d-none mt-3" role="alert"></div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('previewContainer');
    const previewInner = document.getElementById('previewInner');
    const progressOverlay = document.getElementById('progress-overlay');
    const statusBadge = document.getElementById('statusBadge');
    const statsRow = document.getElementById('statsRow');
    const missingFieldsContainer = document.getElementById('missingFieldsContainer');
    const jsonViewer = document.getElementById('jsonViewer');
    const ocrTextViewer = document.getElementById('ocrTextViewer');
    const errorAlert = document.getElementById('errorAlert');
    const copyBtn = document.getElementById('copyBtn');
    const llmProvider = document.getElementById('llmProvider');
    const overviewContent = document.getElementById('overviewContent');
    const confirmSection = document.getElementById('confirmSection');
    const confirmBtn = document.getElementById('confirmBtn');
    const savedSection = document.getElementById('savedSection');

    let selectedFile = null;
    let currentData = null;
    let savedOrderUrl = null;

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) handleFile(fileInput.files[0]);
    });

    function handleFile(file) {
        const valid = ['image/jpeg','image/png','image/jpg','application/pdf'];
        if (!valid.includes(file.type) && !file.name.match(/\.(jpg|jpeg|png|pdf)$/i)) {
            showError('Invalid file type. Accepted: JPG, JPEG, PNG, PDF.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            showError('File exceeds 10 MB limit.');
            return;
        }
        selectedFile = file;
        errorAlert.classList.add('d-none');
        confirmSection.classList.add('d-none');
        savedSection.classList.add('d-none');
        savedOrderUrl = null;
        previewFile(file);
        submitExtraction(file);
    }

    function previewFile(file) {
        previewContainer.classList.remove('d-none');
        if (file.type === 'application/pdf') {
            const url = URL.createObjectURL(file);
            previewInner.innerHTML = '<embed src="' + url + '" type="application/pdf" style="width:100%;height:400px;">';
        } else {
            const url = URL.createObjectURL(file);
            previewInner.innerHTML = '<img src="' + url + '" alt="Preview">';
        }
    }

    function submitExtraction(file) {
        progressOverlay.classList.add('show');
        statusBadge.className = 'badge bg-info';
        statusBadge.textContent = 'Processing...';

        const formData = new FormData();
        formData.append('file', file);
        if (llmProvider.value) formData.append('llm_provider', llmProvider.value);

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '/extract',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                progressOverlay.classList.remove('show');
                currentData = res;
                renderResults(res);
            },
            error: function(xhr) {
                progressOverlay.classList.remove('show');
                let msg = 'An unexpected error occurred.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                showError(msg);
                statusBadge.className = 'badge bg-danger';
                statusBadge.textContent = 'Error';
            }
        });
    }

    function renderResults(data) {
        const extracted = data.extracted_data;
        const llmError = data.llm_error || null;
        const fieldConfidence = data.field_confidence || {};
        const lowConfFields = data.low_confidence_fields || [];
        let savedId = null;

        if (savedOrderUrl) {
            savedSection.classList.remove('d-none');
            savedSection.innerHTML = renderSavedBanner(savedOrderUrl);
            return;
        }

        if (llmError) {
            document.getElementById('llmWarning').innerHTML =
                '<strong>LLM Extraction Unavailable:</strong> ' + $('<span>').text(llmError).html();
            document.getElementById('llmWarning').classList.remove('d-none');
            statusBadge.className = 'badge bg-warning text-dark';
            statusBadge.textContent = 'OCR Only';
        } else {
            document.getElementById('llmWarning').classList.add('d-none');
        }

        if (extracted) {
            const master = extracted.master || {};
            const items = extracted.items || [];
            const missing = data.missing_fields || [];

            statsRow.classList.remove('d-none');
            document.getElementById('statItems').textContent = items.length;
            document.getElementById('statFields').textContent = Object.keys(master).length;
            document.getElementById('statMissing').textContent = missing.length;
            document.getElementById('statFlagged').textContent = lowConfFields.length;

            if (missing.length) {
                missingFieldsContainer.classList.remove('d-none');
                missingFieldsContainer.innerHTML = '<h6 class="fw-semibold mb-2 small text-danger text-uppercase">Missing Fields</h6>' +
                    missing.map(f => '<div class="missing-field">' + $('<span>').text(f).html() + '</div>').join('');
            } else {
                missingFieldsContainer.classList.add('d-none');
            }

            overviewContent.innerHTML = renderOverview(master, items, fieldConfidence, lowConfFields);

            jsonViewer.textContent = JSON.stringify(extracted, null, 2);
            jsonViewer.classList.remove('text-muted');

            confirmSection.classList.remove('d-none');
        } else {
            statsRow.classList.add('d-none');
            missingFieldsContainer.classList.add('d-none');
            overviewContent.innerHTML = '<p class="text-muted">(LLM extraction unavailable — only OCR text was captured)</p>';
            jsonViewer.textContent = '(LLM extraction unavailable — only OCR text was captured)';
            jsonViewer.classList.remove('text-muted');
            confirmSection.classList.add('d-none');
        }

        ocrTextViewer.textContent = data.raw_ocr_text || '(no text extracted)';
        ocrTextViewer.classList.remove('text-muted');

        if (lowConfFields.length) {
            statusBadge.className = 'badge bg-warning text-dark';
            statusBadge.textContent = 'Needs Review';
        } else {
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = 'Completed';
        }

        errorAlert.classList.add('d-none');
    }

    function renderOverview(master, items, fieldConfidence, lowConfFields) {
        const lowSet = new Set(lowConfFields);

        let masterHtml = Object.keys(master).map(function(key) {
            const dotPath = 'master.' + key;
            const isLow = lowSet.has(dotPath);
            const val = master[key] !== null && master[key] !== undefined ? master[key] : '';
            const label = humanLabel(key);
            return renderField(dotPath, label, val, isLow, false, guessInputType(key, val));
        }).join('');

        let itemsHtml = '';
        if (items.length) {
            itemsHtml = '<div class="overview-section"><h6>Line Items</h6>';
            itemsHtml += '<div class="table-responsive"><table class="table table-sm item-table align-middle mb-0">';
            itemsHtml += '<thead><tr><th>#</th>';
            const allItemKeys = collectAllKeys(items);
            allItemKeys.forEach(function(key) {
                itemsHtml += '<th>' + escHtml(humanLabel(key)) + '</th>';
            });
            itemsHtml += '</tr></thead><tbody>';
            items.forEach(function(item, idx) {
                itemsHtml += '<tr>';
                itemsHtml += '<td class="text-muted">' + (idx + 1) + '</td>';
                allItemKeys.forEach(function(key) {
                    const dotPath = 'items.' + idx + '.' + key;
                    const isLow = lowSet.has(dotPath);
                    const val = item[key] !== null && item[key] !== undefined ? item[key] : '';
                    itemsHtml += '<td>' + renderField(dotPath, '', val, isLow, true, guessInputType(key, val)) + '</td>';
                });
                itemsHtml += '</tr>';
            });
            itemsHtml += '</tbody></table></div></div>';
        }

        return '<div class="overview-section"><h6>Master Fields</h6><div class="overview-grid">' + masterHtml + '</div></div>' + itemsHtml;
    }

    function collectAllKeys(items) {
        const keySet = {};
        items.forEach(function(item) {
            Object.keys(item).forEach(function(k) { keySet[k] = true; });
        });
        return Object.keys(keySet);
    }

    function humanLabel(key) {
        return key
            .replace(/_/g, ' ')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2')
            .replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    function guessInputType(key, val) {
        const k = key.toLowerCase();
        if (k === 'date' || k === 'created_at' || k === 'deleted_at' || k === 'updated_at') return 'date';
        if (k === 'start_time' || k === 'end_time' || k === 'duration' || k.endsWith('_time')) return 'text';
        if (['total_amount', 'unit_price', 'quantity', 'price', 'qty', 'amount', 'base_cost', 'base_unit_cost', 'gross_profit', 'gross_profit_rate', 'minutes', 'line_number', 'order_id', 'product_code', 'purchase_calculation_basis', 'purchase_minutes', 'sales_calculation_basis', 'sales_minutes', 'tax_rate', 'quotation_number', 'minutes'].indexOf(k) !== -1) return 'number';
        if (k === 'auto_generated') return 'text';
        if (k === 'branch_number' || k === 'quotation_branch_number' || k === 'service_masters_id') return 'text';
        if (k.endsWith('_code') || k.endsWith('_class')) return 'text';
        if (typeof val === 'number' || (!isNaN(parseFloat(val)) && isFinite(val) && val.toString().indexOf(':') === -1 && val !== '' && val !== null && val !== 'true' && val !== 'false')) return 'number';
        return 'text';
    }

    function renderField(name, label, value, isLow, compact, inputType) {
        inputType = inputType || 'text';
        const cls = isLow ? 'field-low-confidence form-control form-control-sm' : 'form-control form-control-sm';
        const note = isLow ? '<div class="conf-note">&#9888; AI wasn\'t sure about this</div>' : '';
        const fieldId = 'field-' + name.replace(/[^a-zA-Z0-9\-]/g, '_');

        let attrs = 'type="' + inputType + '" class="' + cls + '" name="' + name + '" data-field="' + name + '" id="' + fieldId + '"';
        if (inputType === 'number') {
            attrs += ' step="any" lang="en"';
            if (value !== '' && value !== null) attrs += ' value="' + value + '"';
        } else if (inputType === 'date') {
            if (value !== '' && value !== null) attrs += ' value="' + String(value).substring(0, 10) + '"';
        } else {
            attrs += ' value="' + escHtml(String(value !== null && value !== undefined ? value : '')) + '"';
        }
        const input = '<input ' + attrs + '>';
        if (compact) {
            return '<div>' + input + note + '</div>';
        }
        return '<div class="mb-2">' +
            '<label class="form-label small text-muted mb-1" for="' + fieldId + '">' + label + '</label>' +
            input + note +
            '</div>';
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function showError(msg) {
        errorAlert.textContent = msg;
        errorAlert.classList.remove('d-none');
    }

    copyBtn.addEventListener('click', function() {
        const text = jsonViewer.textContent;
        navigator.clipboard.writeText(text).then(() => {
            const orig = copyBtn.innerHTML;
            copyBtn.innerHTML = '&#10003; Copied!';
            setTimeout(() => copyBtn.innerHTML = orig, 1800);
        });
    });

    confirmBtn.addEventListener('click', function() {
        if (!currentData || !currentData.extracted_data) {
            showError('No extraction data to confirm. Please upload a document first.');
            return;
        }

        if (savedOrderUrl) {
            showError('This extraction has already been saved.');
            return;
        }

        const inputs = document.querySelectorAll('#overviewContent input[data-field]');
        const master = {};
        const items = [];
        const fieldConf = currentData.field_confidence || {};
        const extracted = currentData.extracted_data;
        const numericKeys = ['total_amount', 'unit_price', 'quantity', 'price', 'qty'];

        inputs.forEach(function(inp) {
            const field = inp.getAttribute('data-field');
            const type = inp.getAttribute('type');
            const raw = inp.value;

            const val = (type === 'number' && raw !== '')
                ? parseFloat(raw)
                : (raw !== '' ? raw : null);

            if (field.startsWith('master.')) {
                const key = field.substring(7);
                master[key] = val;
            } else if (field.startsWith('items.')) {
                const parts = field.split('.');
                const idx = parseInt(parts[1], 10);
                const key = parts.slice(2).join('.');
                if (!items[idx]) items[idx] = {};
                items[idx][key] = (val === null && numericKeys.indexOf(key) !== -1) ? 0 : val;
            }
        });

        const payload = {
            master: master,
            items: items.filter(function(i) { return i && i.item_name; }),
            field_confidence: fieldConf,
        };

        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '/extract/confirm',
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            processData: false,
            success: function(res) {
                const id = res.data && res.data.id;
                const orderUrl = '/api/orders/' + id;
                savedOrderUrl = orderUrl;
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '&#10003; Confirm & Save';

                confirmSection.classList.add('d-none');

                savedSection.classList.remove('d-none');
                savedSection.innerHTML = renderSavedBanner(orderUrl);

                statusBadge.className = 'badge bg-success';
                statusBadge.textContent = 'Confirmed';
            },
            error: function(xhr) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '&#10003; Confirm & Save';
                let msg = 'Failed to save. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                showError(msg);
            }
        });
    });

    function renderSavedBanner(orderUrl) {
        return '<div class="saved-id-display">' +
            '<div class="confirm-badge">&#10003; Saved Successfully</div>' +
            '<p class="mb-0 mt-2">The reviewed order has been persisted to the database. ' +
            'View it at <a href="' + orderUrl + '" target="_blank">' + orderUrl + '</a></p>' +
            '</div>';
    }
})();
</script>
</body>
</html>
