<div id="analyticsPanel" style="display:none;padding:16px;">
    <div style="max-width:1200px;margin:0 auto; display: flex; flex-direction: column; gap: 24px;">

        <!-- ── Dashboard Header ── -->
        <div style="background: linear-gradient(135deg, #0d1b2e 0%, #162033 100%); border: 1px solid #1e3a5f; border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #e2e8f0; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;">
                    📊 <?= $role === 'administrator' ? 'Administrator' : 'My' ?> Dashboard
                </h2>
                <p style="margin: 4px 0 0; font-size: 13px; color: #94a3b8;">
                    Real-time updates, active project pipelines, and productivity tracking.
                </p>
            </div>
            <button onclick="loadDashboardStats()" class="adminbtn" style="background: #2563eb; display: flex; align-items: center; gap: 6px;">
                🔄 Refresh Stats
            </button>
        </div>

        <!-- ── Date Filter Bar ── -->
        <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-size: 13px; font-weight: 700; color: #93c5fd; letter-spacing: 0.5px;">📅 FILTER PERIOD:</span>
                <select id="db-period" onchange="onDashboardPeriodChange()" style="padding: 7px 12px; border: 1px solid #334155; border-radius: 6px; background: #0a1628; color: #e2e8f0; font-size: 13px; outline: none; cursor: pointer;">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="week">This Week</option>
                    <option value="last_week">Last Week</option>
                    <option value="month" selected>This Month</option>
                    <option value="year">This Year</option>
                    <option value="custom">Custom Date Range</option>
                </select>
                <div id="db-custom-dates" style="display: none; align-items: center; gap: 8px;">
                    <input type="date" id="db-start-date" onchange="loadDashboardStats()" style="padding: 6px 10px; border: 1px solid #334155; border-radius: 6px; background: #0a1628; color: #e2e8f0; font-size: 13px; outline: none;">
                    <span style="color: #64748b; font-size: 12px;">to</span>
                    <input type="date" id="db-end-date" onchange="loadDashboardStats()" style="padding: 6px 10px; border: 1px solid #334155; border-radius: 6px; background: #0a1628; color: #e2e8f0; font-size: 13px; outline: none;">
                </div>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
                Stats calculated according to selected period.
            </div>
        </div>

        <?php if ($role === 'administrator' && current_user()['username'] !== 'ilyaeco'): ?>
        <!-- ── Finance Grid ── -->
        <div>
            <div style="font-size: 12px; font-weight: 600; color: #60a5fa; margin-bottom: 10px; letter-spacing: 1px; text-transform: uppercase;">
                💰 Financial Overview
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                
                <!-- Client Invoices Card -->
                <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 500; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 8px;">CLIENT INVOICES</div>
                        <div id="stat-total-invoiced" style="font-size: 24px; font-weight: 600; color: #f1f5f9;">$0.00</div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 14px; font-size: 12px; border-top: 1px solid #1e3a5f; padding-top: 8px;">
                        <span style="color: #4ade80; font-weight: 500;">Paid: <span id="stat-invoice-paid">$0.00</span></span>
                        <span style="color: #fbbf24; font-weight: 500;">Pending: <span id="stat-invoice-pending">$0.00</span></span>
                    </div>
                </div>

                <!-- Worker Payroll Card -->
                <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 500; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 8px;">WORKER PAYROLL</div>
                        <div id="stat-total-payroll" style="font-size: 24px; font-weight: 600; color: #f1f5f9;">$0.00</div>
                        <div id="stat-total-payroll-pkr" style="font-size: 12px; color: #94a3b8; margin-top: 2px;">Rs. 0.00</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 14px; border-top: 1px solid #1e3a5f; padding-top: 8px; font-size: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #38bdf8; font-weight: 500;">Paid Payouts: <span id="stat-payslip-paid">$0.00</span></span>
                            <span id="stat-payslip-paid-pkr" style="color: #64748b; font-size: 11px;">Rs. 0.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #fb923c; font-weight: 500;">Unpaid generated: <span id="stat-payslip-generated">$0.00</span></span>
                            <span id="stat-payslip-generated-pkr" style="color: #64748b; font-size: 11px;">Rs. 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Outstanding Liability Card -->
                <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 500; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 8px;">WORKER LEDGER BALANCES</div>
                        <div id="stat-worker-balances" style="font-size: 24px; font-weight: 600; color: #f87171;">$0.00</div>
                        <div id="stat-worker-balances-pkr" style="font-size: 12px; color: #94a3b8; margin-top: 2px;">Rs. 0.00</div>
                    </div>
                    <div style="margin-top: 14px; font-size: 11px; color: #64748b; border-top: 1px solid #1e3a5f; padding-top: 8px; font-style: italic;">
                        Net remaining payout balance across all workers.
                    </div>
                </div>

                <!-- Fines & Penalties Card -->
                <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 500; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 8px;">FINES & PENALTIES</div>
                        <div id="stat-total-fines" style="font-size: 24px; font-weight: 600; color: #f1f5f9;">$0.00</div>
                        <div id="stat-total-fines-pkr" style="font-size: 12px; color: #94a3b8; margin-top: 2px;">Rs. 0.00</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 14px; border-top: 1px solid #1e3a5f; padding-top: 8px; font-size: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #f87171; font-weight: 500;">Deducted: <span id="stat-fines-deducted">$0.00</span></span>
                            <span id="stat-fines-deducted-pkr" style="color: #64748b; font-size: 11px;">Rs. 0.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #fbbf24; font-weight: 500;">Pending: <span id="stat-fines-pending">$0.00</span></span>
                            <span id="stat-fines-pending-pkr" style="color: #64748b; font-size: 11px;">Rs. 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Fixed Expenses Card -->
                <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 500; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 8px;">FIXED EXPENSES</div>
                        <div id="stat-total-expenses" style="font-size: 24px; font-weight: 600; color: #f1f5f9;">$0.00</div>
                        <div id="stat-total-expenses-pkr" style="font-size: 12px; color: #94a3b8; margin-top: 2px;">Rs. 0.00</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 14px; border-top: 1px solid #1e3a5f; padding-top: 8px; font-size: 11px; color:#94a3b8;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Office: <span id="stat-expenses-office-pkr" style="color:#e2e8f0;">Rs. 0</span></span>
                            <span>Utility: <span id="stat-expenses-utility-pkr" style="color:#e2e8f0;">Rs. 0</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Other: <span id="stat-expenses-other-pkr" style="color:#e2e8f0;">Rs. 0</span></span>
                        </div>
                    </div>
                </div>

                <!-- Profit & Loss Card -->
                <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between; min-height: 125px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 500; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 8px;">PROFIT & LOSS</div>
                        <div id="stat-net-profit" style="font-size: 24px; font-weight: 600; color: #4ade80;">$0.00</div>
                        <div id="stat-net-profit-pkr" style="font-size: 12px; color: #4ade80; margin-top: 2px;">Rs. 0.00</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 14px; border-top: 1px solid #1e3a5f; padding-top: 8px; font-size: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #94a3b8;">Gross Revenue: <span id="stat-pl-revenue" style="color:#e2e8f0; font-weight:600;">$0.00</span></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #94a3b8;">Total Expenses: <span id="stat-pl-expenses" style="color:#e2e8f0; font-weight:600;">$0.00</span></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php endif; ?>

        <!-- ── Pipeline Grid ── -->
        <div>
            <div style="font-size: 12px; font-weight: 600; color: #60a5fa; margin-bottom: 10px; letter-spacing: 1px; text-transform: uppercase;">
                🛠 Active Projects & Pipeline — Current Status
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px;">
                
                <!-- AI Work -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-aiwork" style="font-size: 20px; font-weight: 600; color: #8b5cf6;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">AI Work</div>
                </div>

                <!-- AI Done -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-aidone" style="font-size: 20px; font-weight: 600; color: #22c55e;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">AI Done</div>
                </div>

                <!-- Working -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-working" style="font-size: 20px; font-weight: 600; color: #3b82f6;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">Working</div>
                </div>

                <!-- Paused -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-paused" style="font-size: 20px; font-weight: 600; color: #f59e0b;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">Paused</div>
                </div>

                <!-- In QA -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-inqa" style="font-size: 20px; font-weight: 600; color: #a855f7;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">In QA</div>
                </div>

                <!-- SEO Review -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-seoreview" style="font-size: 20px; font-weight: 600; color: #06b6d4;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">SEO Review</div>
                </div>

                <!-- Changes in Design -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-changes" style="font-size: 20px; font-weight: 600; color: #d97706;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">Changes Design</div>
                </div>

                <!-- Changes in Content -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-changes-in-content" style="font-size: 20px; font-weight: 600; color: #db2777;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">Changes Content</div>
                </div>

                <!-- Changing -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-changing" style="font-size: 20px; font-weight: 600; color: #ea580c;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">Changing</div>
                </div>

                <!-- Hold -->
                <div style="background: #111827; border: 1px solid #1e3a5f; border-radius: 8px; padding: 12px; text-align: center;">
                    <div id="stat-pipe-hold" style="font-size: 20px; font-weight: 600; color: #6b7280;">0</div>
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 4px; text-transform: uppercase;">Hold</div>
                </div>

            </div>
        </div>

        <!-- ── Tasks Management Stats ── -->
        <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; align-items: center;">
                <div style="font-size: 12px; font-weight: 600; color: #93c5fd; letter-spacing: 0.5px;">📋 PERIOD ACTIVITY — Selected Filter K Mutabiq</div>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px; color: #cbd5e1;">
                    <span>Total Tasks: <strong id="stat-task-total" style="color:#f1f5f9; font-weight: 600;">0</strong></span>
                    <span>Pending: <strong id="stat-task-pending" style="color:#94a3b8; font-weight: 600;">0</strong></span>
                    <span>Approved: <strong id="stat-task-approved" style="color:#10b981; font-weight: 600;">0</strong></span>
                    <span>Work Done: <strong id="stat-task-workdone" style="color:#a855f7; font-weight: 600;">0</strong></span>
                    <span>Published: <strong id="stat-task-published" style="color:#3b82f6; font-weight: 600;">0</strong></span>
                </div>
            </div>
        </div>

        <?php if ($role === 'administrator'): ?>
        <!-- ── Workers Progress Panel ── -->
        <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px;">
            <div style="font-size: 13px; font-weight: 600; color: #93c5fd; margin-bottom: 14px; letter-spacing: .5px;">👥 WORKERS PERFORMANCE & HOURS</div>
            <div style="overflow-x: auto;">
                <table class="inv-table" style="width: 100%; border-collapse: collapse; min-width: 700px;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px;">Worker</th>
                            <th style="text-align: center; padding: 10px;">Role</th>
                            <th style="text-align: center; padding: 10px;">Active Tasks</th>
                            <th style="text-align: center; padding: 10px;">Completed Tasks</th>
                            <th style="text-align: center; padding: 10px;">Working Hours</th>
                            <?php if (current_user()['username'] !== 'ilyaeco'): ?>
                                <th style="text-align: right; padding: 10px;">Current Balance</th>
                                <th style="text-align: center; padding: 10px;">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="dashboard-workers-tbody">
                        <tr>
                            <td colspan="7" style="text-align: center; color: #64748b; padding: 20px; font-size: 13px;">
                                Loading workers statistics...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'administrator' && current_user()['username'] !== 'ilyaeco'): ?>
        <!-- ── Fixed Expenses Manager Panel ── -->
        <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; padding: 18px;">
            <div style="font-size: 13px; font-weight: 600; color: #93c5fd; margin-bottom: 14px; letter-spacing: .5px;">💼 FIXED EXPENSES MANAGER (OFFICE & UTILITIES)</div>
            
            <!-- Add Expense Form -->
            <form id="add-expense-form" onsubmit="addExpenseEntry(event)" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; background: #0a1628; padding: 14px; border: 1px solid #1e3a5f; border-radius: 8px;">
                <div style="flex: 2; min-width: 180px;">
                    <label style="display: block; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .5px; margin-bottom: 5px;">EXPENSE TITLE</label>
                    <input type="text" id="exp-title" placeholder="e.g. Office Rent, Electricity Bill" required style="width: 100%; padding: 8px 10px; border: 1px solid #334155; border-radius: 6px; background: #0f2035; color: #e2e8f0; font-size: 13px; outline: none; box-sizing: border-box;">
                </div>
                <div style="flex: 1; min-width: 120px;">
                    <label style="display: block; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .5px; margin-bottom: 5px;">AMOUNT (PKR)</label>
                    <input type="number" id="exp-amount" placeholder="Amount" min="1" step="any" required style="width: 100%; padding: 8px 10px; border: 1px solid #334155; border-radius: 6px; background: #0f2035; color: #e2e8f0; font-size: 13px; outline: none; box-sizing: border-box;">
                </div>
                <div style="flex: 1; min-width: 130px;">
                    <label style="display: block; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .5px; margin-bottom: 5px;">CATEGORY</label>
                    <select id="exp-category" required style="width: 100%; padding: 8px 10px; border: 1px solid #334155; border-radius: 6px; background: #0f2035; color: #e2e8f0; font-size: 13px; outline: none; box-sizing: border-box; cursor: pointer;">
                        <option value="office">Office Expense</option>
                        <option value="utility">Utility Expense</option>
                        <option value="other">Other Expense</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 130px;">
                    <label style="display: block; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .5px; margin-bottom: 5px;">EXPENSE DATE</label>
                    <input type="date" id="exp-date" required style="width: 100%; padding: 8px 10px; border: 1px solid #334155; border-radius: 6px; background: #0f2035; color: #e2e8f0; font-size: 13px; outline: none; box-sizing: border-box;">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="submit" class="adminbtn" style="background: #2563eb; height: 36px; padding: 0 16px; font-size: 13px; font-weight: 700; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer;">
                        ➕ Add Expense
                    </button>
                </div>
            </form>

            <!-- Expenses List Table -->
            <div style="overflow-x: auto;">
                <table class="inv-table" style="width: 100%; border-collapse: collapse; min-width: 700px;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px;">Date</th>
                            <th style="text-align: left; padding: 10px;">Title</th>
                            <th style="text-align: center; padding: 10px;">Category</th>
                            <th style="text-align: right; padding: 10px;">Amount (PKR)</th>
                            <th style="text-align: right; padding: 10px;">Amount (USD)</th>
                            <th style="text-align: center; padding: 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="dashboard-expenses-tbody">
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 20px; font-size: 13px;">
                                No expenses logged for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Collapsable Logs Accordion ── -->
        <div style="background: #0f2035; border: 1px solid #1e3a5f; border-radius: 10px; overflow: hidden; margin-top: 10px;">
            <div onclick="toggleLogsAccordion()" style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #0a1628; cursor: pointer; user-select: none;">
                <span style="font-size: 13px; font-weight: 700; color: #93c5fd; letter-spacing: 0.5px;">🌐 USER SESSIONS & AUDIT LOGS</span>
                <span id="logs-accordion-arrow" style="font-size: 13px; color: #93c5fd;">▼ Show Audit Logs</span>
            </div>
            
            <div id="logs-accordion-content" style="display: none; padding: 16px; border-top: 1px solid #1e3a5f;">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                    <select id="analytics-role-filter" onchange="renderLoginLogs()" style="padding:7px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                        <option value="">All Roles</option>
                        <option value="administrator">Administrator</option>
                        <option value="eco_client">ECO Client</option>
                        <option value="worker">Worker</option>
                        <option value="qa">QA</option>
                        <option value="ai_work">AI Worker</option>
                        <option value="seo_manager">SEO Manager</option>
                        <option value="eco_listing">ECO Listing</option>
                    </select>
                    <select id="analytics-date-filter" onchange="toggleAnalyticsCustomDate(); renderLoginLogs()" style="padding:7px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="7days">Last 7 Days</option>
                        <option value="custom">Custom Date</option>
                    </select>
                    <input id="analytics-custom-date" type="date" onchange="renderLoginLogs()" style="display:none;padding:6px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                    <input id="analytics-search" type="text" placeholder="Search user / country..." oninput="renderLoginLogs()" style="flex:1;max-width:260px;padding:7px 10px;border:1px solid #334155;border-radius:6px;background:#0a1628;color:#e2e8f0;font-size:13px;outline:none;">
                    <span id="analytics-count" style="font-size:12px;color:#64748b;"></span>
                </div>
                <div id="analytics-container"><div style="color:#94a3b8;font-size:13px;">Loading...</div></div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
