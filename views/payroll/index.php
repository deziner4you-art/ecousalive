<?php /* Payroll panel — admin and worker views */ ?>
<div style="width:100%;margin:0;">

<?php if($user['role'] === 'administrator'): ?>

<!-- ── Worker rates & balances ── -->
<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div style="font-size:13px;font-weight:700;color:#93c5fd;letter-spacing:.5px;">💰 WORKER RATES & BALANCES</div>
        <div style="display:flex; align-items:center; gap:5px; margin-right:48px;">
            <span style="color:#94a3b8; font-size:12px; font-weight:600;">Filter Month:</span>
            <select id="worker-balances-month" onchange="handleMonthSelection(this.value)" style="background:#071428; border:1px solid #1e3a5f; color:#e2e8f0; padding:6px 10px; border-radius:6px; font-size:12px; outline:none; cursor:pointer;" title="Select Month to see that month's stats">
                <option value="">-- All Time (Latest) --</option>
                <?php
                $start = new DateTime('2026-05-01');
                $end = new DateTime('first day of next month');
                $interval = DateInterval::createFromDateString('1 month');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

                $options = [];
                foreach ($period as $dt) {
                    $val = $dt->format('Y-m');
                    $label = $dt->format('F Y');
                    if ($val === date('Y-m')) $label = "This Month ($label)";
                    if ($val === date('Y-m', strtotime('-1 month'))) $label = "Last Month ($label)";
                    $options[] = "<option value=\"$val\">$label</option>";
                }
                echo implode("\n                ", array_reverse($options));
                ?>
                <option value="custom">-- Custom Month... --</option>
            </select>
            <input type="month" id="custom-month-picker" onchange="loadWorkerRates(this.value)" style="display:none; background:#071428; border:1px solid #1e3a5f; color:#e2e8f0; padding:4px 8px; border-radius:6px; font-size:12px; outline:none; cursor:pointer;">
            <script>
            function handleMonthSelection(val) {
                var picker = document.getElementById('custom-month-picker');
                if (val === 'custom') {
                    picker.style.display = 'inline-block';
                    if (picker.showPicker) picker.showPicker();
                } else {
                    picker.style.display = 'none';
                    picker.value = '';
                    loadWorkerRates(val);
                }
            }
            </script>
            <div style="margin-left:15px; display:flex; align-items:center; gap:8px;">
                <span id="master-total-label" style="color:#94a3b8; font-size:12px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase;">TOTAL PAYABLE</span>
                <div id="master-total-amount" style="background:#022c22; color:#4ade80; border:1px solid #064e3b; padding:6px 12px; border-radius:6px; font-size:13px; font-weight:bold; min-width:80px; text-align:center;">PKR 0</div>
            </div>
        </div>
    </div>
    <div id="rates-container"><div style="color:#475569;font-size:13px;">Loading...</div></div>
</div>



<?php else: ?>
<div id="ps-current-panel-wrapper">
<div style="display:grid;gap:16px;">
    <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;">
        <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:14px;letter-spacing:.5px;">👷 WORKER PROGRESS</div>
        <div id="ps-worker-progress" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">PENDING</div>
                <div id="ps-stat-pending" style="font-size:20px;font-weight:700;color:#38bdf8;">0</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">WORKING</div>
                <div id="ps-stat-working" style="font-size:20px;font-weight:700;color:#f97316;">0</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">PAUSED</div>
                <div id="ps-stat-paused" style="font-size:20px;font-weight:700;color:#f59e0b;">0</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">IN QA</div>
                <div id="ps-stat-inqa" style="font-size:20px;font-weight:700;color:#818cf8;">0</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">WORK DONE</div>
                <div id="ps-stat-workdone" style="font-size:20px;font-weight:700;color:#4ade80;">0</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">INVOICED</div>
                <div id="ps-stat-invoiced" style="font-size:20px;font-weight:700;color:#fbbf24;">0</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">PAID</div>
                <div id="ps-stat-paid" style="font-size:20px;font-weight:700;color:#22c55e;">0</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;text-align:center;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">PAYSLIP PENDING</div>
                <div id="ps-stat-pending-payslip" style="font-size:20px;font-weight:700;color:#fbbf24;">0</div>
            </div>
        </div>
    </div>

    <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;">
        <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:14px;letter-spacing:.5px;">💼 MY PAYROLL ACCOUNT</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;">
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">TOTAL EARNED</div>
                <div id="ps-worker-earned" style="font-size:18px;font-weight:700;color:#93c5fd;">PKR 0</div>
                <div id="ps-earned-range" style="color:#94a3b8;font-size:11px;margin-top:8px;">Date range not available</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">ADVANCE GIVEN</div>
                <div id="ps-worker-advance" style="font-size:18px;font-weight:700;color:#fbbf24;">PKR 0</div>
                <div id="ps-advance-date" style="color:#94a3b8;font-size:11px;margin-top:8px;">Last advance date</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">PAID OUT</div>
                <div id="ps-worker-paidout" style="font-size:18px;font-weight:700;color:#4ade80;">PKR 0</div>
                <div id="ps-paid-date" style="color:#94a3b8;font-size:11px;margin-top:8px;">Last paid date</div>
            </div>
            <div style="background:#071428;border:1px solid #1e3a5f;border-radius:10px;padding:14px;">
                <div style="color:#64748b;font-size:11px;margin-bottom:6px;">BALANCE</div>
                <div id="ps-worker-balance" style="font-size:18px;font-weight:700;color:#fff;">PKR 0</div>
                <div id="ps-balance-note" style="color:#94a3b8;font-size:11px;margin-top:8px;">Previous balance carries over until paid.</div>
            </div>
        </div>
    </div>
    <div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;">
        <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:14px;letter-spacing:.5px;">📋 PAY SLIPS</div>
        <div style="overflow-x:auto;">
            <div id="ps-list-container"><div style="color:#475569;font-size:13px;">Loading...</div></div>
        </div>
    </div>
</div>
</div>
<?php endif; ?>


<?php if($user['role'] !== 'administrator'): ?>
<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;">
    <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:6px;letter-spacing:.5px;">📘 ACCOUNT HISTORY</div>
    <div style="color:#94a3b8;font-size:11px;margin-bottom:14px;">CURRENT MONTH</div>
    <div id="ps-account-history"><div style="color:#475569;font-size:13px;">Loading...</div></div>
</div>
<?php endif; ?>

<!-- ── Revisions & Penalties ── -->
<div style="background:#0f2035;border:1px solid #1e3a5f;border-radius:10px;padding:18px;margin-bottom:20px;">
    <div style="font-size:13px;font-weight:700;color:#93c5fd;margin-bottom:14px;letter-spacing:.5px;">📊 <?php echo $user['role'] === 'administrator' ? 'REVISIONS & PENALTIES' : 'MY REVISIONS & PENALTIES'; ?></div>
    <?php if($user['role'] === 'administrator'): ?>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
        <select class="inv-select" id="penalty-worker-filter" onchange="loadPenalties()" style="min-width:165px; background:#071428; border:1px solid #1e3a5f; color:#e2e8f0; padding:6px; border-radius:6px;">
            <option value="">-- All Workers --</option>
        </select>
    </div>
    <?php endif; ?>
    <div id="penalties-container"><div style="color:#475569;font-size:13px;">Loading...</div></div>
</div>

<!-- ── Payslip detail ── -->
<div id="ps-detail-box" class="inv-detail-box" style="margin-top:16px;"></div>

</div>
