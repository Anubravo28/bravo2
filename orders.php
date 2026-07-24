<?php
// Start session tracking to verify if user is authorized
session_start();

// SECURITY HANDSHAKE: If session flag is missing, kick the user back to the login gate immediately
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// LOGOUT TRIGGER: Allows admin to exit securely
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel — Orders Ledger</title>
    <!-- Include Supabase JS SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f4f6f9; color: #333; padding: 40px 20px; }
        .container { max-width: 1350px; margin: 0 auto; }
        
        /* Header Base Layout Configuration */
        .admin-header { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 20px 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .admin-header h1 { font-size: 1.8rem; }
        .stat-badge { display: inline-block; background: #edf2f7; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-right: 10px; }
        
        .header-controls { display: flex; align-items: center; gap: 12px; }
        .btn-clear-data { background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-logout { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 14px; transition: background 0.2s; }
        .btn-logout:hover { background: #e2e8f0; }
        
        /* Volume Control */
        .volume-control { display: flex; align-items: center; gap: 8px; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 8px; }
        .volume-control label { font-size: 13px; font-weight: 600; color: #475569; white-space: nowrap; }
        .volume-control input[type=range] { width: 90px; accent-color: #16a34a; cursor: pointer; }
        
        /* Table Structure Rules */
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        th { background: #f8fafc; color: #718096; font-size: 13px; font-weight: 700; text-transform: uppercase; padding: 16px 24px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        td { padding: 16px 24px; border-bottom: 1px solid #edf2f7; font-size: 15px; vertical-align: middle; text-align: center; }
        
        .table-badge { font-weight: 700; padding: 4px 10px; border-radius: 6px; font-size: 13px; display: inline-block; }
        .badge-qr { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-takeaway { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
        .note-pill { font-size: 13px; color: #b45309; background: #fef3c7; padding: 4px 8px; border-radius: 6px; border: 1px solid #fde68a; }
        
        .done-chk { width: 20px; height: 20px; cursor: pointer; display: inline-block; vertical-align: middle; }
        
        tr:has(input.done-chk:checked) td { text-decoration: line-through; color: #a0aec0; opacity: 0.6; }
        tr.status-canceled { text-decoration: line-through; color: #cbd5e1; opacity: 0.5; background-color: #fafafa; }
        tr.status-canceled td { text-decoration: line-through !important; color: #94a3b8 !important; }
        .badge-canceled-text { background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; font-weight: 700; padding: 4px 10px; border-radius: 6px; font-size: 12px; display: block; margin: 4px auto 0; width: max-content; }
        .btn-cancel { background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; }

        .table-assign-dropdown {
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            display: inline-block;
            cursor: pointer;
            outline: none;
            text-align: center;
            border: 1px solid transparent;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        /* Banner Notification */
        #newOrderBanner {
            display: none;
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            background: #16a34a;
            color: #fff;
            padding: 16px 32px;
            border-radius: 14px;
            font-size: 18px;
            font-weight: 700;
            box-shadow: 0 8px 32px rgba(22,163,74,0.35);
            align-items: center;
            gap: 12px;
            animation: bannerSlideIn 0.05s ease;
            white-space: nowrap;
        }
        #newOrderBanner.visible { display: flex; }
        @keyframes bannerSlideIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-8px); }
            to   { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        /* Paid / Unpaid Toggle */
        .paid-toggle-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            margin-top: 6px;
        }
        .paid-toggle-wrap input[type=checkbox] { display: none; }
        .paid-label {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            border: 1.5px solid transparent;
            user-select: none;
        }
        .paid-label.paid   { background: #dcfce7; color: #16a34a; border-color: #86efac; }
        .paid-label.unpaid { background: #fff7ed; color: #c2410c; border-color: #fdba74; }
        .paid-label:hover  { transform: scale(1.06); }

        /* Modal Layout */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            width: 92%;
            max-width: 720px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
            border-radius: 16px 16px 0 0;
        }
        .modal-header h2 { font-size: 1.2rem; color: #0f172a; margin: 0; }
        .modal-close {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-toolbar {
            padding: 12px 24px;
            display: flex;
            gap: 10px;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }
        .modal-filter-btn {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            transition: all 0.15s;
        }
        .modal-filter-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
        .modal-filter-btn.unpaid-btn.active { background: #c2410c; border-color: #c2410c; }
        .modal-print-btn {
            margin-left: auto;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            background: #1d4ed8;
            color: #fff;
            border: none;
        }
        .modal-body { padding: 16px 24px 24px; }
        .modal-summary {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .modal-stat {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .modal-stat span { color: #0369a1; font-size: 15px; }
        .modal-stat.unpaid-stat span { color: #c2410c; }
        .modal-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .modal-table th { background: #f8fafc; font-size: 12px; text-transform: uppercase; font-weight: 700; color: #64748b; padding: 10px 12px; border-bottom: 2px solid #e2e8f0; text-align: left; }
        .modal-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .modal-table tr:last-child td { border-bottom: none; }
        .modal-empty { text-align: center; padding: 32px; color: #94a3b8; font-size: 15px; }
        .mini-paid   { background: #dcfce7; color: #16a34a; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
        .mini-unpaid { background: #fff7ed; color: #c2410c; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
        .mini-canceled { background: #f1f5f9; color: #94a3b8; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 700; }

        .btn-table-history {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-table-history:hover { background: #dbeafe; }

        .table-select-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table-picker {
            padding: 7px 12px;
            border-radius: 8px;
            border: 1.5px solid #cbd5e1;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            background: #f8fafc;
            cursor: pointer;
        }

        @media (min-width: 1024px) {
            body { padding: 45px 35px; }
            .admin-header { padding: 25px 35px; margin-bottom: 30px; }
            .admin-header h1 { font-size: 2.15rem; } 
            .stat-badge { font-size: 16px; padding: 10px 20px; border-radius: 9px; margin-right: 12px; }
            .btn-clear-data, .btn-logout { font-size: 16px; padding: 10px 20px; border-radius: 9px; }
            th { font-size: 14.5px; padding: 19px 26px; }
            td { font-size: 18px; padding: 21px 26px; } 
            .item-name { font-size: 19px; }
            .table-badge { font-size: 14.5px; padding: 5px 12px; border-radius: 7px; }
            .note-pill { font-size: 15.5px; padding: 5px 10px; border-radius: 7px; }
            .badge-canceled-text { font-size: 13.5px; padding: 5px 10px; border-radius: 7px; }
            .done-chk { width: 24px; height: 24px; } 
            .btn-cancel { font-size: 15.5px; padding: 8px 16px; border-radius: 7px; }
            .table-assign-dropdown { font-size: 14.5px; padding: 5px 12px; border-radius: 7px; }
        }
    </style>
</head>
<body>

<div id="newOrderBanner">
    🔔 New Order Received!
</div>

<div class="container">
    <div class="admin-header">
        <h1>Orders Dashboard</h1>
        <div class="header-controls">
            <div class="volume-control">
                <label for="chimeVolume">🔔</label>
                <input type="range" id="chimeVolume" min="0" max="1" step="0.05" value="1">
            </div>
            <div class="table-select-row">
                <select class="table-picker" id="tablePickerSelect">
                    <option value="Takeaway">🥡 Takeaway</option>
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <option value="<?php echo $i; ?>">🪑 Table <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn-table-history" onclick="openTableHistory()">📋 Table History</button>
            </div>
            <button class="btn-logout" onclick="generateDailyReportPDF()" style="background: #edf2f7; color: #2d3748; border-color: #cbd5e1; cursor: pointer;">📄 Export PDF</button>

            <span class="stat-badge" id="badgeTotalOrders">Total: 0</span>
            <span class="stat-badge" id="badgeTotalRevenue" style="color:#1d9e75; background:#e6fffa;">Active Earnings: Rs. 0.00</span>
            
            <button type="button" class="btn-clear-data" onclick="clearAllOrders()">Clear Table</button>
            <a href="orders.php?action=logout" class="btn-logout">🔒 Logout</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Location / Table</th>
                <th>Reassign Table</th>
                <th>Menu Item</th>
                <th>Notes</th>
                <th>Price</th>
                <th style="text-align: center;">Done</th>
                <th style="text-align: center;">Cancel</th>
            </tr>
        </thead>
        <tbody id="liveOrdersTableBody">
        </tbody>
    </table>
</div>

<!-- TABLE HISTORY MODAL -->
<div class="modal-overlay" id="tableHistoryModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2 id="modalTitle">📋 Table History</h2>
            <button class="modal-close" onclick="closeTableHistory()">✕</button>
        </div>
        <div class="modal-toolbar">
            <button class="modal-filter-btn active" id="filterAllBtn" onclick="setFilter('all')">All Orders</button>
            <button class="modal-filter-btn unpaid-btn" id="filterUnpaidBtn" onclick="setFilter('unpaid')">💳 Unpaid Only</button>
            <button class="modal-print-btn" onclick="printTableBill()">🖨️ Print Bill</button>
        </div>
        <div class="modal-body">
            <div class="modal-summary" id="modalSummary"></div>
            <div id="modalContent"></div>
        </div>
    </div>
</div>

<script>
    // ── SUPABASE INITIALIZATION ──
    const SUPABASE_URL = 'YOUR_SUPABASE_URL';
    const SUPABASE_ANON_KEY = 'YOUR_SUPABASE_ANON_KEY';
    const supabase = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

    let _knownOrderCount = null;

    function _playNewOrderSound() {
        try {
            const vol = parseFloat(document.getElementById('chimeVolume').value);
            if (vol === 0) return;
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [[523, 0], [659, 0.12], [784, 0.24]].forEach(([freq, when]) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, ctx.currentTime + when);
                gain.gain.setValueAtTime(vol, ctx.currentTime + when);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + when + 0.55);
                osc.start(ctx.currentTime + when);
                osc.stop(ctx.currentTime + when + 0.6);
            });
        } catch(e) {}
    }

    let _bannerTimer = null;
    function _showNewOrderBanner() {
        const banner = document.getElementById('newOrderBanner');
        banner.classList.remove('visible');
        void banner.offsetWidth;
        banner.classList.add('visible');
        if (_bannerTimer) clearTimeout(_bannerTimer);
        _bannerTimer = setTimeout(() => banner.classList.remove('visible'), 8000);
    }

    function _checkForNewOrders(incomingTotal) {
        if (_knownOrderCount === null) {
            _knownOrderCount = incomingTotal;
            return;
        }
        if (incomingTotal > _knownOrderCount) {
            _playNewOrderSound();
            _showNewOrderBanner();
        }
        _knownOrderCount = incomingTotal;
    }

    const CHECKBOX_KEY = 'cafe_done_checks';

    function _loadCheckedStates() {
        try { return JSON.parse(localStorage.getItem(CHECKBOX_KEY)) || {}; }
        catch(e) { return {}; }
    }
    function _saveCheckedStates(states) {
        try { localStorage.setItem(CHECKBOX_KEY, JSON.stringify(states)); }
        catch(e) {}
    }

    let checkedStateHistory = _loadCheckedStates();
    let activeDropdownSelections = {}; 

    function updateDropdownClass(selectEl) {
        if (selectEl.value === 'Takeaway') {
            selectEl.className = 'table-assign-dropdown badge-takeaway';
        } else {
            selectEl.className = 'table-assign-dropdown badge-qr';
        }
    }

    function captureCheckedStates() {
        document.querySelectorAll('#liveOrdersTableBody tr').forEach(row => {
            const id = row.getAttribute('data-order-id');
            const chk = row.querySelector('.done-chk');
            if (id && chk) {
                checkedStateHistory[id] = chk.checked;
            }
        });
        _saveCheckedStates(checkedStateHistory);
    }

    function captureDropdownStates() {
        document.querySelectorAll('.table-assign-dropdown').forEach(select => {
            const orderId = select.getAttribute('data-order-id');
            if (orderId) {
                activeDropdownSelections[orderId] = select.value;
            }
        });
    }

    // Fetch and sync data directly from Supabase
    async function syncLiveDataGridEngine() {
        captureCheckedStates();
        captureDropdownStates();

        const { data: rows, error } = await supabase
            .from('orders')
            .select('*')
            .order('id', { ascending: false });

        if (error) {
            console.error('Error fetching live orders:', error.message);
            return;
        }

        let total_orders = 0;
        let total_revenue = 0;
        let rows_html = '';

        if (rows && rows.length > 0) {
            rows.forEach(row => {
                const price = Number(row.price || row.total_price || 0);
                if (!row.status || row.status === 'active') {
                    total_revenue += price;
                }
                total_orders++;

                const isCanceled = (row.status === 'canceled');
                const canceled_class = isCanceled ? 'status-canceled' : '';
                const disabled_attr = isCanceled ? 'disabled' : '';
                const delete_btn_attr = isCanceled ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : '';

                const location_badge = (row.table_number && row.table_number !== 'Takeaway') 
                    ? `<span class="table-badge badge-qr">🪑 Table ${_esc(row.table_number)}</span>`
                    : '<span class="table-badge badge-takeaway">🥡 Takeaway</span>';

                const note_badge = row.notes 
                    ? `<span class="note-pill">💡 ${_esc(row.notes)}</span>` 
                    : '—';

                const canceled_tag = isCanceled ? ' <span class="badge-canceled-text">⚠️ CANCELED BY CUSTOMER</span>' : '';
                const formatted_price = price.toFixed(2);

                const current_val = row.table_number;
                let dropdown_options = '';

                const selected_tk = (current_val === 'Takeaway') ? 'selected' : '';
                dropdown_options += `<option value='Takeaway' ${selected_tk}>🥡 Takeaway</option>`;

                for (let i = 1; i <= 9; i++) {
                    const selected_tbl = (current_val == i) ? 'selected' : '';
                    dropdown_options += `<option value='${i}' ${selected_tbl}>🪑 Table ${i}</option>`;
                }

                const is_takeaway = (current_val === 'Takeaway');
                const dropdown_style_class = is_takeaway ? 'table-assign-dropdown badge-takeaway' : 'table-assign-dropdown badge-qr';

                const dropdown_html = `<select class='${dropdown_style_class}' data-order-id='${row.id}' ${disabled_attr} onchange='updateDropdownClass(this)'>
                    ${dropdown_options}
                </select>`;

                const is_paid = !empty(row.paid) && row.paid == 1;
                const paid_checked = is_paid ? 'checked' : '';
                const paid_label_class = is_paid ? 'paid-label paid' : 'paid-label unpaid';
                const paid_label_text = is_paid ? '✅ Paid' : '💳 Unpaid';
                const paid_toggle = isCanceled ? '' : `
                    <label class='paid-toggle-wrap'>
                        <input type='checkbox' class='paid-chk' data-order-id='${row.id}' ${paid_checked}>
                        <span class='${paid_label_class}'>${paid_label_text}</span>
                    </label>`;

                rows_html += `<tr class='${canceled_class}' data-order-id='${row.id}'>
                    <td>${location_badge}</td>
                    <td>${dropdown_html}</td>
                    <td><strong class='item-name'>${_esc(row.item || 'Order #' + row.id)}</strong>${canceled_tag}</td>
                    <td>${note_badge}</td>
                    <td>Rs. ${formatted_price}</td>
                    <td>
                        <input type='checkbox' ${disabled_attr} class='done-chk'>
                        ${paid_toggle}
                    </td>
                    <td>
                        <button type='button' class='btn-cancel' data-order-id='${row.id}' ${delete_btn_attr}>Delete</button>
                    </td>
                </tr>`;
            });
        }

        _checkForNewOrders(total_orders);
        document.getElementById('badgeTotalOrders').textContent = "Total: " + total_orders;
        document.getElementById('badgeTotalRevenue').textContent = "Active Earnings: Rs. " + total_revenue.toFixed(2);
        
        const tbody = document.getElementById('liveOrdersTableBody');
        tbody.innerHTML = rows_html;

        tbody.querySelectorAll('tr').forEach(row => {
            const id = row.getAttribute('data-order-id');
            
            const chk = row.querySelector('.done-chk');
            if (id && chk && checkedStateHistory[id] !== undefined) {
                chk.checked = checkedStateHistory[id];
            }

            const select = row.querySelector('.table-assign-dropdown');
            if (id && select && activeDropdownSelections[id] !== undefined) {
                select.value = activeDropdownSelections[id];
                updateDropdownClass(select);
            }
        });

        bindDeleteButtons();
        bindTableChangeEvents();
        bindCheckboxEvents();
        bindPaidCheckboxEvents();
    }

    function empty(val) {
        return val === undefined || val === null || val === '' || val === 0 || val === '0' || val === false;
    }

    function bindCheckboxEvents() {
        document.querySelectorAll('#liveOrdersTableBody .done-chk').forEach(chk => {
            chk.addEventListener('change', function() {
                const id = this.closest('tr').getAttribute('data-order-id');
                if (id) {
                    checkedStateHistory[id] = this.checked;
                    _saveCheckedStates(checkedStateHistory);
                }
            });
        });
    }

    let paidStateCache = {};

    function bindPaidCheckboxEvents() {
        document.querySelectorAll('#liveOrdersTableBody .paid-chk').forEach(chk => {
            const orderId = chk.getAttribute('data-order-id');
            if (orderId && paidStateCache[orderId] !== undefined) {
                chk.checked = paidStateCache[orderId];
                _applyPaidLabel(chk);
            }

            chk.addEventListener('change', async function() {
                const id = this.getAttribute('data-order-id');
                const paid = this.checked ? 1 : 0;
                paidStateCache[id] = this.checked;
                _applyPaidLabel(this);

                const { error } = await supabase
                    .from('orders')
                    .update({ paid: paid })
                    .eq('id', id);

                if (error) {
                    console.error('Payment toggle error:', error.message);
                }
            });
        });
    }

    function _applyPaidLabel(chk) {
        const label = chk.nextElementSibling;
        if (!label) return;
        if (chk.checked) {
            label.className = 'paid-label paid';
            label.textContent = '✅ Paid';
        } else {
            label.className = 'paid-label unpaid';
            label.textContent = '💳 Unpaid';
        }
    }

    function bindDeleteButtons() {
        document.querySelectorAll('.btn-cancel').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm("Are you sure you want to completely delete this record from history?")) return;
                
                const id = this.getAttribute('data-order-id');
                const { error } = await supabase
                    .from('orders')
                    .delete()
                    .eq('id', id);

                if (error) {
                    alert("Error deleting record: " + error.message);
                } else {
                    syncLiveDataGridEngine();
                }
            });
        });
    }

    function bindTableChangeEvents() {
        document.querySelectorAll('.table-assign-dropdown').forEach(select => {
            select.addEventListener('change', async function() {
                const orderId = this.getAttribute('data-order-id');
                const newTableValue = this.value;

                activeDropdownSelections[orderId] = newTableValue;
                updateDropdownClass(this);

                const { error } = await supabase
                    .from('orders')
                    .update({ table_number: newTableValue })
                    .eq('id', orderId);

                if (error) {
                    alert("Error updating table: " + error.message);
                } else {
                    syncLiveDataGridEngine();
                }
            });
        });
    }

    async function clearAllOrders() {
        if (!confirm('Clear all orders?')) return;
        localStorage.removeItem('cafe_done_checks');

        const { error } = await supabase
            .from('orders')
            .delete()
            .neq('id', 0); // Deletes all rows

        if (error) {
            alert('Error clearing table: ' + error.message);
        } else {
            syncLiveDataGridEngine();
        }
    }

    // Modal Table History with Supabase query
    let _currentTableFilter = 'all';
    let _currentTableValue  = '';
    let _lastHistoryData    = null;

    function openTableHistory() {
        _currentTableValue  = document.getElementById('tablePickerSelect').value;
        _currentTableFilter = 'all';
        document.getElementById('filterAllBtn').classList.add('active');
        document.getElementById('filterUnpaidBtn').classList.remove('active');
        document.getElementById('tableHistoryModal').classList.add('open');
        _loadTableHistory();
    }

    function closeTableHistory() {
        document.getElementById('tableHistoryModal').classList.remove('open');
    }

    function setFilter(f) {
        _currentTableFilter = f;
        document.getElementById('filterAllBtn').classList.toggle('active', f === 'all');
        document.getElementById('filterUnpaidBtn').classList.toggle('active', f === 'unpaid');
        _loadTableHistory();
    }

    async function _loadTableHistory() {
        const label = _currentTableValue === 'Takeaway'
            ? '🥡 Takeaway'
            : '🪑 Table ' + _currentTableValue;
        document.getElementById('modalTitle').textContent = '📋 History — ' + label;
        document.getElementById('modalContent').innerHTML = '<p style="text-align:center;padding:24px;color:#94a3b8;">Loading…</p>';

        let query = supabase
            .from('orders')
            .select('*')
            .eq('table_number', _currentTableValue)
            .order('id', { ascending: false });

        if (_currentTableFilter === 'unpaid') {
            query = query.or('paid.eq.0,paid.is.null').neq('status', 'canceled');
        }

        const { data: rows, error } = await query;

        if (error) {
            document.getElementById('modalContent').innerHTML = '<p style="text-align:center;padding:24px;color:#e11d48;">Failed to load data.</p>';
            return;
        }

        let total = 0;
        let unpaid_total = 0;

        (rows || []).forEach(r => {
            const price = Number(r.price || r.total_price || 0);
            if (r.status !== 'canceled') {
                total += price;
                if (empty(r.paid)) unpaid_total += price;
            }
        });

        _lastHistoryData = { rows: rows || [], total, unpaid_total };
        _renderModalRows(_lastHistoryData);
    }

    function _renderModalRows(data) {
        const summary = document.getElementById('modalSummary');
        const content = document.getElementById('modalContent');

        summary.innerHTML = `
            <div class="modal-stat">Total Bill: <span>Rs. ${Number(data.total).toFixed(2)}</span></div>
            <div class="modal-stat unpaid-stat">Unpaid: <span>Rs. ${Number(data.unpaid_total).toFixed(2)}</span></div>
            <div class="modal-stat">Orders: <span>${data.rows.length}</span></div>`;

        if (!data.rows.length) {
            content.innerHTML = '<div class="modal-empty">No orders found for the selected filter.</div>';
            return;
        }

        let html = `<table class="modal-table">
            <thead><tr>
                <th>#</th><th>Item</th><th>Note</th><th>Price</th><th>Status</th><th>Payment</th>
            </tr></thead><tbody>`;

        data.rows.forEach((r, i) => {
            const isCanceled = r.status === 'canceled';
            const isPaid     = r.paid == 1;
            const price      = Number(r.price || r.total_price || 0);
            const statusBadge = isCanceled
                ? '<span class="mini-canceled">Canceled</span>'
                : '<span style="color:#16a34a;font-weight:700;">Active</span>';
            const payBadge = isCanceled
                ? '<span class="mini-canceled">—</span>'
                : (isPaid ? '<span class="mini-paid">✅ Paid</span>' : '<span class="mini-unpaid">💳 Unpaid</span>');
            const note = r.notes ? `<span class="note-pill">💡 ${_esc(r.notes)}</span>` : '—';
            const formattedPrice = isCanceled
                ? `<span style="text-decoration:line-through;color:#94a3b8;">Rs. ${price.toFixed(2)}</span>`
                : `Rs. ${price.toFixed(2)}`;

            html += `<tr style="${isCanceled ? 'opacity:0.5' : ''}">
                <td style="color:#94a3b8;font-size:12px;">${i+1}</td>
                <td style="font-weight:600;">${_esc(r.item || 'Order #' + r.id)}</td>
                <td>${note}</td>
                <td style="font-weight:600;">${formattedPrice}</td>
                <td>${statusBadge}</td>
                <td>${payBadge}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        content.innerHTML = html;
    }

    function _esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function printTableBill() {
        if (!_lastHistoryData) return;
        const label = _currentTableValue === 'Takeaway'
            ? '🥡 Takeaway'
            : 'Table ' + _currentTableValue;
        const dateStr = new Date().toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
        const filterNote = _currentTableFilter === 'unpaid' ? ' (Unpaid Only)' : '';

        let itemRows = '';
        let grandTotal = 0;
        _lastHistoryData.rows.forEach(r => {
            if (r.status === 'canceled') return;
            const price = Number(r.price || r.total_price || 0);
            grandTotal += price;
            const payTag = r.paid == 1
                ? '<span style="font-weight:900;font-size:13px;">[PAID]</span>'
                : '<span style="font-weight:900;font-size:13px;">[UNPAID]</span>';
            const note = r.notes ? `<br><small style="font-size:12px;font-weight:700;">${_esc(r.notes)}</small>` : '';
            itemRows += `<tr>
                <td>${_esc(r.item || 'Order #' + r.id)}${note}</td>
                <td style="text-align:right;">Rs. ${price.toFixed(2)}</td>
                <td style="text-align:center;">${payTag}</td>
            </tr>`;
        });

        if (!itemRows) {
            itemRows = `<tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">No active orders.</td></tr>`;
        }

        const htmlContent = `<!DOCTYPE html><html><head><title>Bill — ${label}</title>
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            @page { size: auto; margin: 8mm 10mm; }
            html, body { width: 100%; height: auto; background: #fff; }
            body { font-family: 'Courier New', Courier, monospace; color: #000; font-size: 15px; padding: 0; margin: 0; }
            .print-btn { display: block; margin: 12px auto; background: #000; color: #fff; border: none; padding: 10px 28px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; }
            @media print { .print-btn { display: none !important; } html, body { height: auto !important; margin: 0 !important; padding: 0 !important; } }
            .cafe-name { text-align:center; font-size:26px; font-weight:900; letter-spacing:2px; margin-bottom:4px; text-transform:uppercase; }
            .cafe-sub  { text-align:center; font-size:13px; font-weight:700; margin-bottom:6px; letter-spacing:1px; text-transform:uppercase; }
            hr.thick { border:none; border-top:3px solid #000; margin:10px 0; }
            hr.thin  { border:none; border-top:1px solid #000; margin:10px 0; }
            .bill-header { display:flex; justify-content:space-between; font-size:13px; font-weight:700; margin:10px 0; }
            table { width:100%; border-collapse:collapse; margin-bottom:4px; }
            th { font-size:13px; text-transform:uppercase; font-weight:900; padding:7px 6px; border-top:2px solid #000; border-bottom:2px solid #000; text-align:left; }
            td { padding:8px 6px; font-size:14px; font-weight:600; border-bottom:1px solid #000; }
            .total-row td  { border-top:3px solid #000; border-bottom:3px solid #000; font-size:16px; font-weight:900; padding:10px 6px; }
            .unpaid-row td { border-bottom:2px solid #000; font-size:14px; font-weight:800; padding:8px 6px; }
            .footer { text-align:center; font-size:13px; font-weight:700; margin-top:16px; padding-top:12px; letter-spacing:0.5px; border-top:1px solid #000; }
        </style>
        </head><body>
        <button class="print-btn" onclick="window.print()">🖨️ Print / Save PDF</button>
        <div id="receiptRoot">
            <div class="cafe-name">White Angel Cafe</div>
            <div class="cafe-sub">Order Bill${filterNote}</div>
            <hr class="thick">
            <div class="bill-header">
                <span>${label}</span>
                <span>${dateStr}</span>
            </div>
            <hr class="thin">
            <table>
                <thead><tr>
                    <th style="width:50%;">Item</th>
                    <th style="text-align:right; width:25%;">Price</th>
                    <th style="text-align:center; width:25%;">Status</th>
                </tr></thead>
                <tbody>${itemRows}</tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td style="text-align:right;">Rs. ${grandTotal.toFixed(2)}</td>
                        <td></td>
                    </tr>
                    <tr class="unpaid-row">
                        <td>UNPAID AMOUNT</td>
                        <td style="text-align:right;">Rs. ${Number(_lastHistoryData.unpaid_total).toFixed(2)}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <hr class="thick">
            <div class="footer">
                Thank you for dining with us!<br>
                White Angel Cafe &copy; ${new Date().getFullYear()}
            </div>
        </div>
        </body></html>`;

        const blobUrl = URL.createObjectURL(new Blob([htmlContent], { type: 'text/html' }));
        const win = window.open(blobUrl, '_blank', 'width=794');
        if (win) {
            win.addEventListener('unload', function () { URL.revokeObjectURL(blobUrl); });
        }
    }

    document.getElementById('tableHistoryModal').addEventListener('click', function(e) {
        if (e.target === this) closeTableHistory();
    });

    function generateDailyReportPDF() {
        const dateString = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const totalOrders = document.getElementById('badgeTotalOrders').textContent.replace('Total:', '').trim();
        const rawRevenue = document.getElementById('badgeTotalRevenue').textContent.replace('Active Earnings:', '').trim();

        const itemCounts = {};
        document.querySelectorAll('#liveOrdersTableBody tr').forEach(row => {
            if (row.classList.contains('status-canceled')) return;
            const itemEl = row.querySelector('.item-name');
            if (itemEl) {
                const text = itemEl.textContent.trim();
                text.split(',').forEach(part => {
                    let name = part.trim();
                    let qty = 1;
                    const match = name.match(/\(x(\d+)\)/);
                    if (match) {
                        qty = parseInt(match[1], 10);
                        name = name.replace(/\(x\d+\)/, '').trim();
                    }
                    itemCounts[name] = (itemCounts[name] || 0) + qty;
                });
            }
        });

        let mostSellingItemName = "N/A";
        let maxQty = 0;
        for (const [item, qty] of Object.entries(itemCounts)) {
            if (qty > maxQty) {
                maxQty = qty;
                mostSellingItemName = item;
            }
        }
        const mostSellingDisplay = maxQty > 0 ? `${mostSellingItemName} (${maxQty} Units Sold)` : "No Active Sales";

        let ordersListHtml = "";
        document.querySelectorAll('#liveOrdersTableBody tr').forEach(row => {
            if (row.classList.contains('status-canceled')) return;
            
            const locText = row.querySelector('.table-badge') ? row.querySelector('.table-badge').textContent.trim() : "Takeaway";
            const itemName = row.querySelector('.item-name') ? row.querySelector('.item-name').textContent.trim() : "Unknown Item";
            
            const cols = row.querySelectorAll('td');
            if (cols.length < 5) return;
            
            const notes = cols[3] ? cols[3].textContent.trim() : "—";
            const price = cols[4] ? cols[4].textContent.trim() : "Rs. 0.00";

            ordersListHtml += `
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px;">${locText}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; font-weight: 600;">${itemName}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; color: #b45309;">${notes}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; text-align: right; font-weight: 600;">${price}</td>
                </tr>`;
        });

        if (!ordersListHtml) {
            ordersListHtml = `<tr><td colspan="4" style="text-align: center; padding: 20px; color: #a0aec0;">No active orders on record.</td></tr>`;
        }

        const reportHtml = `
            <html>
            <head>
                <title>Daily Sales Report - ${dateString}</title>
                <style>
                    body { font-family: 'Segoe UI', system-ui, sans-serif; color: #1e293b; padding: 40px; margin: 0; background: #ffffff; }
                    .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #f1f5f9; padding-bottom: 25px; }
                    .header h1 { font-size: 28px; color: #0f172a; margin: 0 0 8px; text-transform: uppercase; letter-spacing: 1px; }
                    .header p { margin: 0; font-size: 15px; color: #64748b; font-weight: 500; }
                    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
                    .card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; text-align: center; }
                    .card h3 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
                    .card p { margin: 0; font-size: 20px; font-weight: 700; color: #0f172a; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
                    th { background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 12px 10px; border-bottom: 2px solid #cbd5e1; text-align: left; }
                    .footer { text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 40px; }
                    @media print {
                        body { padding: 0; }
                        button { display: none; }
                        html, body { height: auto !important; }
                        table { page-break-inside: auto; }
                        tr, td, th { page-break-inside: avoid; }
                        thead { display: table-header-group; }
                        .card, .footer, .header { page-break-inside: avoid; page-break-before: avoid; }
                    }
                </style>
            </head>
            <body>
                <div style="max-width: 800px; margin: 0 auto;">
                    <div style="text-align: right; margin-bottom: 20px;">
                        <button onclick="window.print();" style="background: #0f172a; color: #ffffff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">Print / Save to PDF</button>
                    </div>
                    
                    <div class="header">
                        <h1>Daily Sales Summary</h1>
                        <p>${dateString}</p>
                    </div>
                    
                    <div class="grid">
                        <div class="card">
                            <h3>Total Orders</h3>
                            <p>${totalOrders}</p>
                        </div>
                        <div class="card" style="border-color: #bbf7d0; background: #f0fdf4;">
                            <h3>Total Active Earnings</h3>
                            <p style="color: #15803d;">${rawRevenue}</p>
                        </div>
                        <div class="card" style="border-color: #fed7aa; background: #fff7ed;">
                            <h3>Most Selling Item</h3>
                            <p style="font-size: 16px; color: #c2410c;">${mostSellingDisplay}</p>
                        </div>
                    </div>
                    
                    <h2 style="font-size: 18px; color: #0f172a; margin-bottom: 15px;">Live Active Orders Summary</h2>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 25%;">Origin Location</th>
                                <th style="width: 40%;">Ordered Items</th>
                                <th style="width: 20%;">Special Notes</th>
                                <th style="width: 15%; text-align: right;">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${ordersListHtml}
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <p>Generated automatically via Orders Dashboard Administration Ledger &copy; 2026</p>
                    </div>
                </div>
            </body>
            </html>
        `;

        const reportBlobUrl = URL.createObjectURL(new Blob([reportHtml], { type: 'text/html' }));
        const printWindow = window.open(reportBlobUrl, '_blank', 'width=900,height=800');
        if (printWindow) {
            printWindow.addEventListener('unload', function () { URL.revokeObjectURL(reportBlobUrl); });
        }
    }

    // Initialize initial render and subscribe to real-time events
    syncLiveDataGridEngine();

    supabase
        .channel('public:orders')
        .on('postgres_changes', { event: '*', schema: 'public', table: 'orders' }, () => {
            syncLiveDataGridEngine();
        })
        .subscribe();
</script>
</body>
</html>