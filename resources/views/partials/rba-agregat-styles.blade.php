<style>
/* ==========================================================================
   AGGREGATE MATRIX VIEW & KPI CARDS (Inline Blade Partial for cPanel Sync)
   ========================================================================== */
.rba-agregat-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    margin-bottom: 16px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--bs-primary-bg-subtle, #eef2ff) 0%, var(--bs-tertiary-bg, #f8fafc) 100%);
    border: 1px solid var(--bs-primary-border-subtle, #c7d2fe);
}

.rba-agregat-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--bs-emphasis-color, #1e293b);
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rba-agregat-subtitle {
    font-size: .8125rem;
    color: var(--bs-secondary-color, #64748b);
    margin-bottom: 0;
}

.rba-agregat-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.rba-agregat-search {
    position: relative;
    min-width: 240px;
}

.rba-agregat-search > i {
    position: absolute;
    top: 50%;
    left: 11px;
    transform: translateY(-50%);
    color: var(--bs-secondary-color, #64748b);
    font-size: .95rem;
    pointer-events: none;
}

.rba-agregat-search .form-control {
    padding-left: 34px;
    border-radius: 8px;
    font-size: .825rem;
    background-color: var(--bs-body-bg, #fff);
}

.rba-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.rba-kpi-card {
    padding: 15px 18px;
    border-radius: 10px;
    border: 1px solid var(--bs-border-color, #e2e8f0);
    background: var(--bs-body-bg, #ffffff);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .03);
    transition: transform .15s ease, box-shadow .15s ease;
}

.rba-kpi-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, .06);
}

.rba-kpi-card--highlight {
    background: var(--bs-success-bg-subtle, #ecfdf5);
    border-color: var(--bs-success-border-subtle, #a7f3d0);
}

.rba-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}

.rba-kpi-code {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 5px;
    font-family: var(--bs-font-monospace, monospace);
    font-size: .73rem;
    font-weight: 700;
    background: var(--bs-primary-bg-subtle, #eef2ff);
    color: var(--bs-primary-text-emphasis, #3730a3);
}

.rba-kpi-card--highlight .rba-kpi-code {
    background: var(--bs-success, #198754);
    color: #fff;
}

.rba-kpi-label {
    font-size: .76rem;
    font-weight: 600;
    color: var(--bs-secondary-color, #64748b);
    line-height: 1.35;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: .02em;
}

.rba-kpi-card--highlight .rba-kpi-label {
    color: var(--bs-success-text-emphasis, #065f46);
}

.rba-kpi-value {
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--bs-emphasis-color, #0f172a);
    font-variant-numeric: tabular-nums;
    margin-bottom: 0;
    white-space: nowrap;
}

.rba-kpi-card--highlight .rba-kpi-value {
    color: var(--bs-success-text-emphasis, #065f46);
    font-size: 1.15rem;
}

.rba-budget .rba-table.rba-table--agregat,
.rba-pendapatan .pendapatan-table.pendapatan-table--agregat {
    table-layout: auto !important;
    min-width: 1120px !important;
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
}

.rba-budget .rba-table.rba-table--agregat-compact {
    min-width: 860px !important;
}

.rba-budget .rba-table.rba-table--agregat > :not(caption) > * > *,
.rba-pendapatan .pendapatan-table.pendapatan-table--agregat > :not(caption) > * > * {
    padding: 11px 14px !important;
    overflow-wrap: normal !important;
    word-break: normal !important;
    vertical-align: middle !important;
    border-right: 1px solid var(--bs-border-color, #e2e8f0) !important;
    border-bottom: 1px solid var(--bs-border-color, #e2e8f0) !important;
}

.rba-budget .rba-table.rba-table--agregat > :not(caption) > * > *:last-child,
.rba-pendapatan .pendapatan-table.pendapatan-table--agregat > :not(caption) > * > *:last-child {
    border-right: 0 !important;
}

.rba-budget .rba-table.rba-table--agregat > thead > tr > th,
.rba-pendapatan .pendapatan-table.pendapatan-table--agregat > thead > tr > th {
    padding: 12px 14px !important;
    background: var(--bs-tertiary-bg, #f8fafc) !important;
    border-bottom: 2px solid var(--bs-border-color, #cbd5e1) !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
}

.rba-budget .rba-table.rba-table--agregat > tbody > tr:nth-of-type(even) > *,
.rba-pendapatan .pendapatan-table.pendapatan-table--agregat > tbody > tr:nth-of-type(even) > * {
    --bs-table-bg: rgba(248, 250, 252, .65);
}

.rba-budget .rba-table.rba-table--agregat > tbody > tr:hover > *,
.rba-pendapatan .pendapatan-table.pendapatan-table--agregat > tbody > tr:hover > * {
    --bs-table-bg: var(--bs-primary-bg-subtle, #eef2ff) !important;
}

.agregat-col-header {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    line-height: 1.25;
}

.agregat-col-header--start {
    align-items: flex-start;
}

.agregat-col-code {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-family: var(--bs-font-monospace, monospace);
    font-size: .72rem;
    font-weight: 700;
    background: var(--bs-primary-bg-subtle, #eef2ff);
    color: var(--bs-primary-text-emphasis, #3730a3);
    border: 1px solid var(--bs-primary-border-subtle, #c7d2fe);
    white-space: nowrap;
}

.agregat-col-code--total {
    background: var(--bs-success-bg-subtle, #ecfdf5);
    color: var(--bs-success-text-emphasis, #065f46);
    border-color: var(--bs-success-border-subtle, #a7f3d0);
}

.agregat-col-label {
    font-size: .74rem;
    font-weight: 700;
    color: var(--bs-emphasis-color, #1e293b);
    text-transform: uppercase;
    letter-spacing: .01em;
    white-space: nowrap;
}

.agregat-unit-cell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-width: 220px;
}

.agregat-unit-link {
    font-weight: 600;
    color: var(--bs-emphasis-color, #1e293b);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    transition: color .15s ease;
}

.agregat-unit-link:hover {
    color: var(--bs-primary, #4f46e5);
}

.agregat-unit-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: var(--bs-primary-bg-subtle, #eef2ff);
    color: var(--bs-primary-text-emphasis, #3730a3);
    font-size: .9rem;
    flex-shrink: 0;
}

.agregat-unit-badge {
    font-size: .7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    background: var(--bs-tertiary-bg, #f1f5f9);
    color: var(--bs-secondary-color, #64748b);
    border: 1px solid var(--bs-border-color, #e2e8f0);
    white-space: nowrap;
    flex-shrink: 0;
}

.agregat-val-zero {
    color: var(--bs-secondary-color, #94a3b8) !important;
    opacity: .55;
    font-weight: 400 !important;
}

.agregat-val-positive {
    color: var(--bs-emphasis-color, #0f172a) !important;
    font-weight: 600 !important;
}

.agregat-total-col {
    background-color: rgba(25, 135, 84, .05) !important;
}

.rba-table--agregat .btn,
.pendapatan-table--agregat .btn {
    white-space: nowrap !important;
}

.rba-unit-banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    margin-bottom: 16px;
    border-radius: 10px;
    background: var(--bs-info-bg-subtle, #e0f2fe);
    border: 1px solid var(--bs-info-border-subtle, #bae6fd);
    color: var(--bs-info-text-emphasis, #075985);
}
</style>
