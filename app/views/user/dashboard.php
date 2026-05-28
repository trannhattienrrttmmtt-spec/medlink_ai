<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
$role = $_SESSION['role'] ?? 'user';
$history = $history ?? $recentHistory ?? [];
if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>
<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MedLink AI — Dashboard</title>
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('ml-theme')||'light')</script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@0.5.24/dist/vanta.net.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/dist/vis-network.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/css/medlink-dashboard.css">
<style>
/* Dashboard Specific Styles */
.theme-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--card);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
    cursor: pointer;
    display: grid;
    place-items: center;
    font-size: 18px;
    z-index: 9999;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.theme-btn:hover {
    transform: scale(1.15) rotate(20deg);
    box-shadow: var(--shadow-glow);
}
.ai-tabs {
    display: flex;
    gap: 6px;
    background: var(--bg-soft);
    padding: 6px;
    border-radius: 14px;
    margin-bottom: 20px;
}
.ai-tab {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    text-align: center;
    cursor: pointer;
    border: none;
    background: transparent;
    color: var(--text-muted);
    transition: all 0.3s ease;
}
.ai-tab:hover {
    color: var(--text);
    background: rgba(99, 102, 241, 0.05);
}
.ai-tab.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-2));
    color: #fff;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}
.ai-panel {
    display: none;
}
.ai-panel.active {
    display: block;
    animation: fadeUp 0.4s ease;
}
.ai-compare {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media(max-width:768px){.ai-compare{grid-template-columns:1fr}}

.ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}
.ai-badge-cur {
    background: var(--green-light);
    color: var(--green);
}
.ai-badge-orig {
    background: var(--amber-light);
    color: var(--amber);
}
.ai-protein-panel {
    margin-top: 20px;
    padding: 16px;
    border-radius: 16px;
    border: 1px solid rgba(129, 140, 248, 0.28);
    background: linear-gradient(135deg, rgba(129, 140, 248, 0.10), rgba(99, 102, 241, 0.05));
}
.ai-protein-head {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text);
    font-weight: 800;
    margin-bottom: 12px;
}
.ai-protein-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.ai-protein-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    border-radius: 12px;
    background: rgba(129, 140, 248, 0.14);
    border: 1px solid rgba(129, 140, 248, 0.28);
    color: var(--text);
    font-size: 12.5px;
    font-weight: 700;
}
.ai-protein-chip i {
    color: #818cf8;
}
.dataset-summary-card {
    margin-bottom: 24px;
    overflow: hidden;
}
.dataset-summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 16px;
}
.dataset-summary-head .left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.dataset-summary-head .icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: var(--primary);
    background: var(--primary-light);
    font-size: 20px;
}
.dataset-summary-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: 16px;
}
.dataset-summary-table {
    width: 100%;
    min-width: 880px;
    border-collapse: collapse;
    background: var(--card);
}
.dataset-summary-table th,
.dataset-summary-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    font-weight: 800;
    white-space: nowrap;
}
.dataset-summary-table th {
    color: var(--text-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .7px;
    background: var(--bg-soft);
}
.dataset-summary-table tr:last-child td {
    border-bottom: 0;
}
.dataset-summary-table .ds-name {
    color: var(--primary);
}
.dataset-summary-table .ds-sparsity {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    color: var(--pink);
}
.dataset-summary-table tbody tr {
    cursor: pointer;
    transition: background .2s ease;
}
.dataset-summary-table tbody tr:hover {
    background: var(--primary-light);
}
.dataset-chart-panel {
    margin-top: 16px;
    padding: 16px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: var(--bg-soft);
}
.dataset-chart-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.dataset-chart-toolbar select {
    min-width: 180px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--card);
    color: var(--text);
    padding: 10px 12px;
    font-weight: 800;
}
.global-dataset-control {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 38px;
    padding: 5px 8px 5px 12px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--card);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.global-dataset-control label {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .7px;
}
.global-dataset-control select {
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text);
    font-weight: 900;
    min-width: 118px;
    cursor: pointer;
}
.global-dataset-control i {
    color: var(--primary);
}
.dataset-sparsity-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 9px 13px;
    background: rgba(236,72,153,.12);
    color: var(--pink);
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-weight: 900;
}
.dataset-chart-box {
    height: 320px;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 12px;
}
.full-network-panel {
    margin-top: 16px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: var(--card);
    overflow: hidden;
}
.full-network-toolbar {
    display: grid;
    grid-template-columns: 1fr 180px 180px 140px;
    gap: 10px;
    align-items: end;
    padding: 14px;
    background: var(--bg-soft);
    border-bottom: 1px solid var(--line);
}
.full-network-toolbar label {
    display: block;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .7px;
    margin-bottom: 6px;
}
.full-network-toolbar select,
.full-network-toolbar input {
    width: 100%;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--card);
    color: var(--text);
    padding: 10px 12px;
    font-weight: 800;
}
.full-network-status {
    padding: 10px 14px;
    color: var(--text-muted);
    font-weight: 800;
    border-bottom: 1px solid var(--line);
}
.full-network-detail {
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    background: var(--primary-light);
    color: var(--text);
    font-weight: 800;
    display: none;
}
.full-network-detail b {
    color: var(--primary);
}
.full-network-detail small {
    display: block;
    margin-top: 4px;
    color: var(--text-muted);
    font-weight: 800;
}
.full-network-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    background: linear-gradient(135deg, var(--card), var(--bg-soft));
}
.full-network-metric {
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 10px 12px;
    background: var(--card);
}
.full-network-metric small {
    display: block;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .7px;
}
.full-network-metric b {
    display: block;
    margin-top: 3px;
    color: var(--text);
    font-size: 15px;
    font-weight: 900;
}
#fullDrugDiseaseNetwork {
    height: 520px;
    background:
        radial-gradient(circle at 20% 15%, rgba(99,102,241,.08), transparent 25%),
        radial-gradient(circle at 80% 20%, rgba(16,185,129,.08), transparent 25%),
        var(--card);
}
@media(max-width:900px){
    .full-network-toolbar { grid-template-columns: 1fr; }
    .full-network-metrics { grid-template-columns: 1fr 1fr; }
    .global-dataset-control { width: 100%; justify-content: space-between; }
    .global-dataset-control select { min-width: 0; }
}
.ai-pct {
    font-weight: 800;
    font-size: 14px;
    color: var(--primary);
}
.ai-bar {
    height: 6px;
    border-radius: 4px;
    background: var(--line-soft);
    width: 90px;
    margin-top: 6px;
    overflow: hidden;
}
.ai-mol {
    width: 110px;
    height: 110px;
    border-radius: 14px;
    border: 1.5px solid var(--line);
    object-fit: contain;
    background: linear-gradient(135deg, #fafbff, #fff);
    padding: 8px;
    cursor: zoom-in;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.ai-mol:hover {
    transform: scale(1.1) translateY(-3px);
    box-shadow: 0 10px 25px rgba(99, 102, 241, 0.2);
    border-color: var(--primary);
}
#aiGraph {
    height: 530px;
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    border: 1px solid rgba(129, 140, 248, 0.28);
    background:
        radial-gradient(circle at 50% 42%, rgba(129, 140, 248, 0.20), transparent 34%),
        radial-gradient(circle at 18% 18%, rgba(16, 185, 129, 0.14), transparent 26%),
        linear-gradient(135deg, #080d1c 0%, #111827 48%, #0b1020 100%);
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04), 0 20px 45px rgba(2, 6, 23, 0.28);
}
.ai-graph-card {
    position: relative;
    overflow: hidden;
    border-color: rgba(129, 140, 248, 0.32) !important;
    background:
        linear-gradient(180deg, rgba(129,140,248,0.06), transparent 34%),
        var(--card) !important;
}
.ai-graph-card::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: linear-gradient(90deg, #6366f1, #22c55e, #f59e0b, #ec4899);
    opacity: 0.95;
}
.ai-graph-card .section-title {
    position: relative;
    z-index: 1;
    padding: 4px 0 10px;
}
.ai-graph-card .section-title .icon {
    background: linear-gradient(135deg, rgba(99,102,241,0.20), rgba(236,72,153,0.16));
    box-shadow: 0 10px 28px rgba(99,102,241,0.18);
}
#aiGraph::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
    background-size: 44px 44px;
    background-position: 0 0;
    mask-image: radial-gradient(circle at center, black, transparent 72%);
    animation: graphGridDrift 14s linear infinite;
}
#aiGraph::after {
    content: "";
    position: absolute;
    inset: 12px;
    pointer-events: none;
    z-index: 2;
    border-radius: 16px;
    border: 1px solid rgba(129, 140, 248, 0.18);
    box-shadow:
        inset 0 0 32px rgba(129, 140, 248, 0.10),
        inset 0 0 0 1px rgba(255,255,255,0.03);
    background:
        radial-gradient(circle at 50% 0%, rgba(236,72,153,0.18), transparent 28%),
        linear-gradient(90deg, rgba(129,140,248,0.42) 0 58px, transparent 58px calc(100% - 58px), rgba(129,140,248,0.42) calc(100% - 58px)) top / 100% 1px no-repeat,
        linear-gradient(90deg, rgba(129,140,248,0.42) 0 58px, transparent 58px calc(100% - 58px), rgba(129,140,248,0.42) calc(100% - 58px)) bottom / 100% 1px no-repeat,
        linear-gradient(180deg, rgba(129,140,248,0.42) 0 58px, transparent 58px calc(100% - 58px), rgba(129,140,248,0.42) calc(100% - 58px)) left / 1px 100% no-repeat,
        linear-gradient(180deg, rgba(129,140,248,0.42) 0 58px, transparent 58px calc(100% - 58px), rgba(129,140,248,0.42) calc(100% - 58px)) right / 1px 100% no-repeat;
    animation: graphFramePulse 3.2s ease-in-out infinite;
}
#aiGraph canvas {
    position: relative;
    z-index: 1;
}
.ai-graph-hud {
    position: absolute;
    top: 16px;
    left: 16px;
    right: 16px;
    z-index: 3;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    pointer-events: none;
}
.ai-graph-title {
    padding: 10px 13px;
    border-radius: 14px;
    background: rgba(15, 23, 42, 0.74);
    border: 1px solid rgba(148, 163, 184, 0.22);
    color: #f8fafc;
    box-shadow: 0 12px 32px rgba(0,0,0,0.24);
    backdrop-filter: blur(12px);
    position: relative;
    overflow: hidden;
}
.ai-graph-title::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #22d3ee, #818cf8, #ec4899);
}
.ai-graph-title b {
    display: block;
    font-size: 13px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.ai-graph-title small {
    display: block;
    margin-top: 3px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 700;
}
.ai-graph-metrics {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.ai-graph-chip {
    min-width: 72px;
    padding: 8px 10px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background: rgba(15, 23, 42, 0.72);
    color: #f8fafc;
    text-align: center;
    box-shadow: 0 12px 32px rgba(0,0,0,0.22);
    backdrop-filter: blur(12px);
    position: relative;
    overflow: hidden;
}
.ai-graph-chip::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0.16;
}
.ai-graph-chip:nth-child(1)::before { background: #ec4899; }
.ai-graph-chip:nth-child(2)::before { background: #10b981; }
.ai-graph-chip:nth-child(3)::before { background: #f59e0b; }
.ai-graph-chip:nth-child(4)::before { background: #818cf8; }
.ai-graph-chip span,
.ai-graph-chip small {
    position: relative;
    z-index: 1;
}
.ai-graph-chip span {
    display: block;
    font-size: 18px;
    line-height: 1;
    font-weight: 900;
}
.ai-graph-chip small {
    color: #94a3b8;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.ai-graph-footer {
    position: absolute;
    left: 18px;
    right: 18px;
    bottom: 16px;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    pointer-events: none;
}
.ai-graph-aurora {
    position: absolute;
    inset: -20%;
    z-index: 2;
    pointer-events: none;
    opacity: 0.42;
    mix-blend-mode: screen;
    background:
        conic-gradient(from 180deg at 50% 50%, transparent 0deg, rgba(34,211,238,0.16) 72deg, transparent 120deg, rgba(236,72,153,0.14) 210deg, transparent 300deg),
        radial-gradient(circle at 34% 62%, rgba(16,185,129,0.13), transparent 26%);
    filter: blur(18px);
    animation: graphAuroraSweep 11s ease-in-out infinite alternate;
}
.ai-graph-status,
.ai-graph-mode {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    background: rgba(15, 23, 42, 0.72);
    color: #cbd5e1;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    box-shadow: 0 12px 32px rgba(0,0,0,0.22);
    backdrop-filter: blur(12px);
}
.ai-live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 14px #22c55e;
    animation: graphLivePulse 1.4s ease-in-out infinite;
}
@keyframes graphGridDrift {
    from { background-position: 0 0; }
    to { background-position: 44px 44px; }
}
@keyframes graphFramePulse {
    0%, 100% { opacity: 0.72; }
    50% { opacity: 1; }
}
@keyframes graphLivePulse {
    0%, 100% { transform: scale(0.85); opacity: 0.65; }
    50% { transform: scale(1.25); opacity: 1; }
}
@keyframes graphAuroraSweep {
    from { transform: translate3d(-4%, -2%, 0) rotate(0deg) scale(1); }
    to { transform: translate3d(4%, 2%, 0) rotate(18deg) scale(1.08); }
}
.ai-legend {
    display: flex;
    gap: 10px;
    align-items: center;
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 15px;
    flex-wrap: wrap;
    font-weight: 700;
}
.ai-legend span {
    padding: 7px 10px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: var(--bg-soft);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.ai-legend span:hover {
    transform: translateY(-2px);
    border-color: rgba(129, 140, 248, 0.42);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.12);
}
.ai-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
    vertical-align: middle;
}
.smiles {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11.5px;
    color: var(--text-muted);
    word-break: break-all;
    max-width: 200px;
    display: inline-block;
}
.skeleton {
    background: linear-gradient(90deg, var(--bg-soft) 25%, var(--line-soft) 50%, var(--bg-soft) 75%);
    background-size: 200% 100%;
    animation: skel 1.6s infinite;
    border-radius: 10px;
}
@keyframes skel {
    0% { background-position: 200% 0 }
    100% { background-position: -200% 0 }
}
.search-box {
    position: relative;
}
.search-box::before {
    content: '\F52A';
    font-family: 'bootstrap-icons';
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-dim);
    font-size: 15px;
    pointer-events: none;
}
.search-box select, .search-box input {
    padding-left: 42px !important;
}
.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}
.section-title h3 {
    font-size: 19px;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.02em;
}
.section-title .icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-light), rgba(236, 72, 153, 0.1));
    display: grid;
    place-items: center;
    color: var(--primary);
    font-size: 20px;
}
.quick-action {
    padding: 16px;
    border-radius: 16px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    text-decoration: none;
    color: inherit;
}
.quick-action:hover {
    background: var(--primary-light);
    border-color: var(--primary);
    transform: translateX(6px) translateY(-2px);
    box-shadow: var(--shadow);
}
.quick-action .qa-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 20px;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}
.qa-icon.purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.qa-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.qa-icon.amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.qa-icon.pink { background: linear-gradient(135deg, #ec4899, #db2777); }

.qa-text {
    flex: 1;
}
.qa-text b {
    display: block;
    font-size: 14px;
    color: var(--text);
    font-weight: 700;
}
.qa-text small {
    font-size: 11.5px;
    color: var(--text-muted);
}
.empty-state {
    padding: 50px;
    text-align: center;
    color: var(--text-muted);
}
.empty-state .ico {
    font-size: 54px;
    color: var(--text-dim);
    margin-bottom: 12px;
}
.history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.history-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    border-radius: 14px;
    background: var(--bg-soft);
    border: 1px solid var(--line-soft);
    transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.history-item:hover {
    background: var(--primary-light);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}
.history-item .h-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--card);
    display: grid;
    place-items: center;
    color: var(--primary);
    font-size: 18px;
    flex-shrink: 0;
    border: 1px solid var(--line);
}
.history-item .h-text {
    flex: 1;
    min-width: 0;
}
.history-item .h-text b {
    display: block;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.history-item .h-text small {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: capitalize;
}
.history-item .h-time {
    font-size: 11.5px;
    color: var(--text-dim);
    white-space: nowrap;
    font-weight: 600;
}
#gSymptomsBox label:hover {
    background: var(--primary-light);
}
#gSymptomsBox input:checked + span {
    color: var(--primary);
    font-weight: 700;
}
.sym-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 10px;
    background: var(--primary-light);
    color: var(--primary);
    font-size: 12px;
    font-weight: 700;
    border: 1px solid rgba(99, 102, 241, 0.25);
    transition: all 0.2s;
}
.sym-tag .sym-x {
    cursor: pointer;
    font-size: 15px;
    opacity: 0.7;
    margin-left: 2px;
}
.sym-tag .sym-x:hover {
    opacity: 1;
    color: var(--red);
}
.fade-up {
    animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<div class="ml-app">
    <aside class="ml-sidebar">
        <div class="ml-brand">
            <div class="ml-logo"><i class="bi bi-capsule"></i></div>
            <div>
                <h1>MedLink AI</h1>
                <p>Drug-Disease Prediction</p>
            </div>
        </div>
        <div class="ml-nav-title">Điều hướng</div>
        <div class="ml-nav">
            <a class="active" href="index.php?action=dashboard"><i class="bi bi-grid-1x2-fill"></i> Tổng quan</a>
            <a href="#ai-section"><i class="bi bi-search"></i> Dự đoán AI</a>
            <a href="#chart-section"><i class="bi bi-bar-chart-fill"></i> Biểu đồ</a>
            <a href="#graph-section"><i class="bi bi-diagram-3-fill"></i> Mạng liên kết</a>
            <a href="#gen-section"><i class="bi bi-magic"></i> Sinh thuốc</a>
            <a href="index.php?action=history"><i class="bi bi-clock-history"></i> Lịch sử</a>
            <a href="index.php?action=logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </div>
        <div class="ml-nav-title">Datasets</div>
        <div class="ml-datasets">
            <span class="ml-pill"><span class="ai-dot" style="background:#6366f1"></span>B-dataset</span>
            <span class="ml-pill"><span class="ai-dot" style="background:#10b981"></span>C-dataset</span>
            <span class="ml-pill"><span class="ai-dot" style="background:#ec4899"></span>F-dataset</span>
        </div>
    </aside>

    <main class="ml-main">
        <div class="ml-topbar">
            <div style="display:flex;gap:14px;align-items:center">
                <button class="ml-mobile-menu" data-menu-btn><i class="bi bi-list"></i></button>
                <div>
                    <h2>Xin chào, <?= e(explode(' ', $fullName)[count(explode(' ', $fullName))-1]) ?> 👋</h2>
                    <p>Hệ thống hỗ trợ nghiên cứu liên kết thuốc - bệnh.</p>
                </div>
            </div>
            <div class="ml-user">
                <div class="global-dataset-control" title="Dataset dùng chung cho toàn bộ dashboard">
                    <i class="bi bi-database-fill"></i>
                    <label for="globalDataset">Dataset</label>
                    <select id="globalDataset">
                        <option>B-dataset</option>
                        <option>C-dataset</option>
                        <option>F-dataset</option>
                    </select>
                </div>
                <button id="themeToggle" style="width:38px;height:38px;border-radius:50%;background:var(--bg-soft);border:1px solid var(--line);display:grid;place-items:center;cursor:pointer;font-size:16px;color:var(--text);transition:all 0.3s;" title="Đổi sáng/tối">
                    <i class="bi bi-moon-fill"></i>
                </button>
                <div class="ml-avatar"><?= e(strtoupper(substr($fullName,0,1))) ?></div>
                <div>
                    <b><?= e($fullName) ?></b><br>
                    <small><?= e($role) ?></small>
                </div>
            </div>
        </div>

        <!-- Hero -->
        <section class="ml-hero fade-up" id="heroSection" style="position:relative">
            <div id="vantaBg" style="position:absolute;inset:0;border-radius:28px;overflow:hidden;z-index:0;opacity:0.4;"></div>
            <span class="ml-badge"><i class="bi bi-stars"></i> AI-Powered Drug Discovery</span>
            <h3>Dự đoán mối liên kết thuốc - bệnh thông minh</h3>
            <p>Hệ thống khai phá tri thức y sinh sử dụng Deep Learning để tìm kiếm các mối tương quan ẩn giữa cấu trúc hoạt chất của thuốc và các chỉ dấu bệnh học di truyền.</p>
            <div class="ml-actions">
                <a href="#ai-section" class="ml-btn primary"><i class="bi bi-play-fill"></i> Bắt đầu dự đoán</a>
                <a href="#gen-section" class="ml-btn ghost"><i class="bi bi-magic"></i> Sinh thuốc mới</a>
            </div>
        </section>

        <!-- Stats -->
        <section class="ml-grid cards ml-stats-grid fade-up" style="animation-delay: 0.1s">
            <a class="ml-card ml-stat-card" id="linkDrugs" href="index.php?action=catalog&type=drug&dataset=B-dataset" style="text-decoration:none;color:inherit"><div class="ml-card-head"><div><small>THUỐC</small><div class="ml-stat-number" id="sDrugs">—</div><small style="color:var(--green); font-weight:700;"><i class="bi bi-check-circle-fill"></i> Hoạt chất</small></div><div class="ml-stat-icon">💊</div></div></a>
            <a class="ml-card ml-stat-card" id="linkDiseases" href="index.php?action=catalog&type=disease&dataset=B-dataset" style="text-decoration:none;color:inherit"><div class="ml-card-head"><div><small>BỆNH</small><div class="ml-stat-number" id="sDiseases">—</div><small style="color:var(--green); font-weight:700;"><i class="bi bi-check-circle-fill"></i> Chỉ dấu</small></div><div class="ml-stat-icon">🧬</div></div></a>
            <a class="ml-card ml-stat-card" id="linkProteins" href="index.php?action=catalog&type=protein&dataset=B-dataset" style="text-decoration:none;color:inherit"><div class="ml-card-head"><div><small>PROTEIN</small><div class="ml-stat-number" id="sProteins">—</div><small style="color:var(--green); font-weight:700;"><i class="bi bi-check-circle-fill"></i> Đích sinh học</small></div><div class="ml-stat-icon">🔬</div></div></a>
            <div class="ml-card ml-stat-card"><div class="ml-card-head"><div><small>MODELS</small><div class="ml-stat-number">2</div><small style="color:var(--text-muted); font-weight:700;">Cải tiến + Gốc AMDGT</small></div><div class="ml-stat-icon">🤖</div></div></div>
        </section>

        <section class="ml-card dataset-summary-card fade-up" style="animation-delay: 0.15s">
            <div class="dataset-summary-head">
                <div class="left">
                    <div class="icon"><i class="bi bi-table"></i></div>
                    <div>
                        <h3 style="margin:0;font-size:20px;font-weight:900;">Thông số benchmark datasets</h3>
                        <small style="color:var(--text-muted);font-weight:800;">Tổng quan B/C/F dataset và độ thưa Drug-Disease</small>
                    </div>
                </div>
                <span class="ai-badge ai-badge-cur"><i class="bi bi-database-check"></i> Dataset summary</span>
            </div>
            <div class="dataset-summary-table-wrap">
                <table class="dataset-summary-table">
                    <thead>
                        <tr>
                            <th>Dataset</th>
                            <th>Drugs</th>
                            <th>Diseases</th>
                            <th>Proteins</th>
                            <th>Drug-Disease associations</th>
                            <th>Drug-Protein associations</th>
                            <th>Disease-Protein associations</th>
                            <th>Sparsity</th>
                        </tr>
                    </thead>
                    <tbody id="datasetSummaryBody">
                        <tr><td colspan="8" style="text-align:center;color:var(--text-muted)">Đang tải thông số dataset...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="dataset-chart-panel">
                <div class="dataset-chart-toolbar">
                    <div>
                        <b style="font-size:15px;">Biểu đồ thông số theo dataset</b>
                        <small style="display:block;color:var(--text-muted);font-weight:800;">Chọn dataset để xem quy mô node và association</small>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <span class="dataset-sparsity-pill"><i class="bi bi-database"></i> Dataset: <span id="benchmarkDatasetLabel">B-dataset</span></span>
                        <span class="dataset-sparsity-pill"><i class="bi bi-percent"></i> Sparsity: <span id="datasetSparsityValue">0.1144</span></span>
                    </div>
                </div>
                <div class="dataset-chart-box">
                    <canvas id="datasetSummaryChart"></canvas>
                </div>
                <div class="full-network-panel">
                    <div class="full-network-toolbar">
                        <div>
                            <b style="font-size:15px;">Mạng toàn bộ Drug-Disease</b>
                            <small style="display:block;color:var(--text-muted);font-weight:800;">Từng thuốc - từng bệnh theo association trong benchmark dataset</small>
                        </div>
                        <div>
                            <label>Loại liên kết</label>
                            <select id="fullNetworkRelation">
                                <option value="drug_disease">Drug-Disease</option>
                                <option value="drug_protein">Drug-Protein</option>
                                <option value="disease_protein">Disease-Protein</option>
                            </select>
                        </div>
                        <div>
                            <label>Model view</label>
                            <select id="fullNetworkModel">
                                <option value="both">Cả 2 mô hình</option>
                                <option value="current">AMDGT cải tiến</option>
                                <option value="original">AMDGT gốc</option>
                            </select>
                        </div>
                        <button class="ml-btn primary" id="btnLoadFullNetwork" type="button" style="height:42px;"><i class="bi bi-diagram-3-fill"></i> Vẽ graph</button>
                    </div>
                    <div class="full-network-status" id="fullNetworkStatus">Chọn dataset phía trên rồi bấm Vẽ graph.</div>
                    <div class="full-network-metrics" id="fullNetworkMetrics">
                        <div class="full-network-metric"><small>Dataset</small><b>—</b></div>
                        <div class="full-network-metric"><small>Drugs</small><b>—</b></div>
                        <div class="full-network-metric"><small>Diseases</small><b>—</b></div>
                        <div class="full-network-metric"><small>Proteins</small><b>—</b></div>
                        <div class="full-network-metric"><small>Drug-Disease</small><b>—</b></div>
                        <div class="full-network-metric"><small>Drug-Protein</small><b>—</b></div>
                        <div class="full-network-metric"><small>Disease-Protein</small><b>—</b></div>
                        <div class="full-network-metric"><small>Sparsity</small><b>—</b></div>
                        <div class="full-network-metric"><small>Graph edges</small><b>—</b></div>
                    </div>
                    <div class="full-network-detail" id="fullNetworkDetail"></div>
                    <div id="fullDrugDiseaseNetwork"></div>
                </div>
            </div>
        </section>

        <!-- Dynamic Content Grid -->
        <div class="ml-grid two fade-up" style="animation-delay: 0.2s; margin-bottom: 24px;">
            <!-- Left Side: Prediction and Generator Forms -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                
                <!-- Main prediction console -->
                <section class="ml-card" id="ai-section">
                    <div class="section-title">
                        <div class="icon"><i class="bi bi-cpu-fill"></i></div>
                        <h3>Bảng điều khiển Dự đoán AI</h3>
                    </div>
                    
                    <!-- Link prediction form (AJAX based) -->
                    <div id="tabPredictLink" class="ai-form-panel active">
                        <div class="ml-form">
                            <div class="ml-row">
                                <div class="ml-field">
                                    <label>Kiểu phân tích</label>
                                    <select id="pType">
                                        <option value="drug">Thuốc → Bệnh</option>
                                        <option value="disease">Bệnh → Thuốc</option>
                                    </select>
                                </div>
                                <div class="ml-field">
                                    <label>Top hiển thị (K)</label>
                                    <input id="pTopK" type="number" min="1" max="50" value="9">
                                    <small style="display:block;margin-top:6px;color:var(--text-muted);font-weight:700;">Tối đa 50 kết quả để tránh graph quá nặng</small>
                                </div>
                            </div>
                            <div class="ml-field search-box">
                                <label>Chọn tên Thuốc hoặc Bệnh học</label>
                                <select id="pKeyword">
                                    <option value="">-- Chọn hoạt chất --</option>
                                </select>
                            </div>
                            <button class="ml-btn primary full" id="btnPredict" style="height: 48px;">
                                <i class="bi bi-play-circle-fill" style="font-size: 16px;"></i> Chạy dự đoán AI
                            </button>
                            <div id="pStatus" style="text-align:center;font-size:13px;font-weight:600;color:var(--text-muted)"></div>
                        </div>
                    </div>
                </section>

                <!-- Molecule generator -->
                <section class="ml-card" id="gen-section">
                    <div class="section-title">
                        <div class="icon" style="color:var(--green); background:rgba(16,185,129,0.1)"><i class="bi bi-magic"></i></div>
                        <h3>Sinh hoạt chất y học mới</h3>
                    </div>
                    <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px">Nhập tên bệnh mới mục tiêu và chọn các triệu chứng tương ứng từ tập dữ liệu. Thuật toán tạo sinh sẽ mô phỏng biến cấu trúc phân tử (SMILES) để tạo ra các ứng viên thuốc tối ưu lý thuyết.</p>
                    <div class="ml-form">
                        <div style="display:grid;grid-template-columns:1fr;gap:12px">
                            <div class="ml-field">
                                <label>Tên bệnh lý chỉ định</label>
                                <input id="gDisease" placeholder="VD: Novel Respiratory disease, Long COVID...">
                            </div>
                        </div>
                        <div class="ml-field">
                            <label>Triệu chứng lâm sàng tương tự (Tick chọn nhiều)</label>
                            <div id="gSelectedTags" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;min-height:30px"></div>
                            <div id="gSymptomsBox" style="max-height:180px;overflow-y:auto;border:1.8px solid var(--line);border-radius:12px;padding:12px;background:var(--card)"></div>
                        </div>
                        <div class="ml-field">
                            <label>Số lượng ứng viên sinh ra</label>
                            <input id="gN" type="number" min="1" max="30" value="10">
                        </div>
                        <button class="ml-btn primary full" id="btnGenerate" style="height: 48px; background:linear-gradient(135deg, var(--green), #059669); box-shadow: 0 6px 20px rgba(16,185,129,0.3)">
                            <i class="bi bi-magic"></i> Bắt đầu tạo sinh thuốc
                        </button>
                    </div>
                    <div id="genResult" style="margin-top:18px"></div>
                </section>

            </div>

            <!-- Right Side: Quick navigation and Recent activities -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                
                <!-- Quick actions navigation panel -->
                <section class="ml-card">
                    <div class="section-title"><div class="icon"><i class="bi bi-lightning-charge-fill"></i></div><h3>Truy cập nhanh</h3></div>
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <a class="quick-action" href="#gen-section"><div class="qa-icon green"><i class="bi bi-magic"></i></div><div class="qa-text"><b>Sinh thuốc mới</b><small>Thiết kế cấu trúc phân tử tự động</small></div></a>
                        <a class="quick-action" href="index.php?action=history"><div class="qa-icon amber"><i class="bi bi-clock-history"></i></div><div class="qa-text"><b>Xem lịch sử</b><small>Quản lý các kết quả nghiên cứu cũ</small></div></a>
                    </div>
                </section>

                <!-- History list widget -->
                <section class="ml-card">
                    <div class="section-title" style="justify-content:space-between">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="icon" style="color:var(--amber); background:rgba(245,158,11,0.1)"><i class="bi bi-clock-history"></i></div>
                            <h3>Hoạt động gần đây</h3>
                        </div>
                        <a class="ml-btn" href="index.php?action=history" style="padding: 6px 12px; font-size: 12px; border-radius: 8px;">Tất cả <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (empty($history)): ?>
                        <div class="empty-state">
                            <div class="ico"><i class="bi bi-folder2-open"></i></div>
                            <div>Chưa có dữ liệu tìm kiếm</div>
                            <small>Các truy vấn gần đây sẽ hiển thị tại đây.</small>
                        </div>
                    <?php else: ?>
                        <div class="history-list">
                        <?php foreach (array_slice($history, 0, 5) as $item): ?>
                            <div class="history-item">
                                <div class="h-icon"><i class="bi bi-<?= ($item['input_type'] ?? '') === 'drug' ? 'capsule' : (($item['input_type'] ?? '') === 'disease_protein' ? 'dna' : 'activity') ?>"></i></div>
                                <div class="h-text">
                                    <b><?= e($item['keyword'] ?? '') ?></b>
                                    <small><?= e($item['input_type'] ?? 'disease') ?> · <?= e($item['dataset'] ?? 'B-dataset') ?></small>
                                </div>
                                <span class="h-time"><?= e(explode(' ', $item['created_at'] ?? '')[1] ?? ($item['created_at'] ?? '')) ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                
            </div>
        </div>

        <!-- Predict Results Section (Fades in dynamically) -->
        <section class="ml-card fade-up" id="resultCard" style="display:none;margin-bottom:24px">
            <div class="ai-tabs" style="margin-bottom: 20px;">
                <button class="ai-tab active" data-tab="tabCompare"><i class="bi bi-columns-gap"></i> So sánh song song</button>
                <button class="ai-tab" data-tab="tabChart" id="chart-section"><i class="bi bi-bar-chart-fill"></i> Đồ thị phân bổ</button>
            </div>
            <div id="tabCompare" class="ai-panel active">
                <div class="ai-compare">
                    <div>
                        <span class="ai-badge ai-badge-cur"><i class="bi bi-cpu"></i> Mô hình cải tiến</span>
                        <div id="boxCur" style="margin-top:14px"></div>
                    </div>
                    <div>
                        <span class="ai-badge ai-badge-orig"><i class="bi bi-robot"></i> Mô hình gốc AMDGT</span>
                        <div id="boxOrig" style="margin-top:14px"></div>
                    </div>
                </div>
                <div id="proteinPanel" class="ai-protein-panel" style="display:none"></div>
            </div>
            <div id="tabChart" class="ai-panel">
                <div style="background:var(--bg-soft); padding: 20px; border-radius: 16px; border: 1px solid var(--line);">
                    <canvas id="chartCanvas" style="max-height:340px; width:100%;"></canvas>
                </div>
            </div>
        </section>

        <!-- 3D Connection Graph Section -->
        <section class="ml-card ai-graph-card fade-up" id="graphCard" style="display:none;margin-bottom:24px">
            <div class="section-title">
                <div class="icon"><i class="bi bi-diagram-3-fill"></i></div>
                <h3 id="graph-section">Mạng tương tác 3D Drug-Disease</h3>
            </div>
            
            <div id="aiGraph" style="position:relative; width: 100%;"></div>
            
            <div class="ai-legend">
                <span><span class="ai-dot" style="background:#ec4899"></span>◆ Chỉ dấu đầu vào</span>
                <span><span class="ai-dot" style="background:#10b981"></span>■ Mô hình cải tiến</span>
                <span><span class="ai-dot" style="background:#f59e0b"></span>◇ Mô hình gốc AMDGT</span>
                <span><span class="ai-dot" style="background:#818cf8"></span>○ Đích Protein</span>
                <span style="color:var(--text-dim); margin-left: auto;">Kéo chuột xoay · Scroll phóng to · Click nút tròn xem chi tiết</span>
            </div>
            <div id="nodeInfo" style="display:none;margin-top:15px;padding:16px;background:var(--primary-light);border: 1px solid rgba(99,102,241,0.2);border-radius:14px;font-size:14px; font-weight: 500;"></div>
        </section>
    </main>
</div>

<script>
const API='http://127.0.0.1:5000';
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const molImg=smi=>smi?`${API}/render_smiles?smi=${encodeURIComponent(smi)}&w=250&h=250`:'';
let chartInst=null;

// Form Navigation tabs
document.querySelectorAll('[data-form-tab]').forEach(tabBtn => {
    tabBtn.onclick = () => {
        document.querySelectorAll('[data-form-tab]').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.ai-form-panel').forEach(p => p.style.display = 'none');
        
        tabBtn.classList.add('active');
        const targetId = tabBtn.dataset.formTab;
        $(targetId).style.display = 'block';
        $(targetId).classList.add('fade-up');
    };
});

// Theme Toggle
const saved=localStorage.getItem('ml-theme')||'light';
document.documentElement.setAttribute('data-theme',saved);
updIcon();
document.getElementById('themeToggle').addEventListener('click',function(){
    const cur=document.documentElement.getAttribute('data-theme');
    const next=cur==='dark'?'light':'dark';
    document.documentElement.setAttribute('data-theme',next);
    localStorage.setItem('ml-theme',next);
    updIcon();
});
function updIcon(){
    const isDark=document.documentElement.getAttribute('data-theme')==='dark';
    document.getElementById('themeToggle').innerHTML=isDark?'<i class="bi bi-sun-fill"></i>':'<i class="bi bi-moon-fill"></i>';
}

// Panel Tabs for Results
document.querySelectorAll('.ai-tab[data-tab]').forEach(b=>b.onclick=()=>{
    document.querySelectorAll('.ai-tab[data-tab]').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.ai-panel').forEach(p=>p.classList.remove('active'));
    b.classList.add('active');$(b.dataset.tab).classList.add('active');
});

// API Get & Post
async function get(p){return(await fetch(API+p)).json()}
async function post(p,d){return(await fetch(API+p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)})).json()}

const datasetSummaryFallback=[
    {dataset:'B-dataset',drugs:269,diseases:598,proteins:1021,drug_disease_associations:18416,drug_protein_associations:3110,disease_protein_associations:5898,sparsity:0.1144},
    {dataset:'C-dataset',drugs:663,diseases:409,proteins:993,drug_disease_associations:2532,drug_protein_associations:3773,disease_protein_associations:10734,sparsity:0.0093},
    {dataset:'F-dataset',drugs:593,diseases:313,proteins:2741,drug_disease_associations:1933,drug_protein_associations:3243,disease_protein_associations:54265,sparsity:0.0104}
];
let datasetSummaryRows=[...datasetSummaryFallback];
let datasetSummaryChartInst=null;
let fullNetworkInst=null;
function fmtNum(v){return Number(v||0).toLocaleString('en-US')}
function validDataset(v){
    return ['B-dataset','C-dataset','F-dataset'].includes(v);
}
function initGlobalDataset(){
    const select=$('globalDataset');
    if(!select)return;
    const params=new URLSearchParams(window.location.search);
    const fromUrl=params.get('dataset');
    const saved=localStorage.getItem('ml-dataset');
    const dataset=validDataset(fromUrl)?fromUrl:(validDataset(saved)?saved:'B-dataset');
    select.value=dataset;
}
function currentDataset(){
    return $('globalDataset')?.value || 'B-dataset';
}
function setCurrentDataset(dataset){
    const select=$('globalDataset');
    if(select)select.value=dataset;
    localStorage.setItem('ml-dataset',dataset);
    refreshDatasetViews(true);
}
function resetFullNetworkPrompt(){
    if(fullNetworkInst){fullNetworkInst.destroy();fullNetworkInst=null;}
    const box=$('fullDrugDiseaseNetwork');
    const detail=$('fullNetworkDetail');
    const status=$('fullNetworkStatus');
    if(box)box.innerHTML='';
    if(detail){detail.style.display='none';detail.innerHTML='';}
    if(status)status.innerHTML=`Dataset hiện tại: <b>${esc(currentDataset())}</b>. Bấm Vẽ graph để tải mạng đầy đủ.`;
}
function refreshDatasetViews(reloadOptions=true){
    const dataset=currentDataset();
    localStorage.setItem('ml-dataset',dataset);
    if($('benchmarkDatasetLabel'))$('benchmarkDatasetLabel').textContent=dataset;
    renderDatasetSummaryChart(dataset);
    resetFullNetworkPrompt();
    if(reloadOptions)loadOpts();
}
function renderDatasetSummary(rows){
    const body=$('datasetSummaryBody');
    if(!body)return;
    datasetSummaryRows=(rows&&rows.length?rows:datasetSummaryFallback);
    body.innerHTML=datasetSummaryRows.map(r=>`
        <tr data-summary-dataset="${esc(r.dataset)}">
            <td class="ds-name">${esc(r.dataset)}</td>
            <td>${fmtNum(r.drugs)}</td>
            <td>${fmtNum(r.diseases)}</td>
            <td>${fmtNum(r.proteins)}</td>
            <td>${fmtNum(r.drug_disease_associations)}</td>
            <td>${fmtNum(r.drug_protein_associations)}</td>
            <td>${fmtNum(r.disease_protein_associations)}</td>
            <td class="ds-sparsity">${Number(r.sparsity||0).toFixed(4)}</td>
        </tr>
    `).join('');
    body.querySelectorAll('[data-summary-dataset]').forEach(row=>{
        row.onclick=()=>{
            setCurrentDataset(row.dataset.summaryDataset);
        };
    });
    renderDatasetSummaryChart(currentDataset());
}
function renderDatasetSummaryChart(datasetName){
    const canvas=$('datasetSummaryChart');
    if(!canvas||!window.Chart)return;
    const row=datasetSummaryRows.find(r=>r.dataset===datasetName)||datasetSummaryRows[0]||datasetSummaryFallback[0];
    if($('benchmarkDatasetLabel'))$('benchmarkDatasetLabel').textContent=row.dataset;
    if($('datasetSparsityValue'))$('datasetSparsityValue').textContent=Number(row.sparsity||0).toFixed(4);
    if(datasetSummaryChartInst)datasetSummaryChartInst.destroy();
    datasetSummaryChartInst=new Chart(canvas,{
        type:'bar',
        data:{
            labels:['Drugs','Diseases','Proteins','Drug-Disease','Drug-Protein','Disease-Protein'],
            datasets:[{
                label:row.dataset,
                data:[
                    row.drugs,
                    row.diseases,
                    row.proteins,
                    row.drug_disease_associations,
                    row.drug_protein_associations,
                    row.disease_protein_associations
                ],
                backgroundColor:['#6366f1','#10b981','#ec4899','#f59e0b','#14b8a6','#8b5cf6'],
                borderRadius:8,
                borderSkipped:false
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            indexAxis:'y',
            plugins:{
                legend:{display:false},
                tooltip:{callbacks:{label:ctx=>`${ctx.label}: ${fmtNum(ctx.raw)}`}},
                title:{display:true,text:`Benchmark profile - ${row.dataset}`,color:getComputedStyle(document.documentElement).getPropertyValue('--text').trim()}
            },
            scales:{
                x:{
                    type:'logarithmic',
                    min:1,
                    ticks:{callback:v=>fmtNum(v),color:getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim()},
                    grid:{color:getComputedStyle(document.documentElement).getPropertyValue('--line').trim()}
                },
                y:{
                    ticks:{color:getComputedStyle(document.documentElement).getPropertyValue('--text').trim(),font:{weight:'800'}},
                    grid:{display:false}
                }
            }
        }
    });
}
async function loadFullDrugDiseaseNetwork(){
    const box=$('fullDrugDiseaseNetwork');
    const status=$('fullNetworkStatus');
    const detail=$('fullNetworkDetail');
    if(!box||!status||!window.vis)return;
    const dataset=currentDataset();
    const relation=$('fullNetworkRelation')?.value||'drug_disease';
    const model=$('fullNetworkModel')?.value||'both';
    status.innerHTML=`<i class="bi bi-hourglass-split"></i> Đang tải mạng ${esc(dataset)}...`;
    if(detail){detail.style.display='none';detail.innerHTML='';}
    try{
        const data=await get(`/dataset_drug_disease_network?dataset=${encodeURIComponent(dataset)}&relation=${encodeURIComponent(relation)}&model=${encodeURIComponent(model)}&limit=0`);
        if(!data.ok)throw new Error(data.error||'Không tải được graph');
        const nodes=new vis.DataSet(data.nodes||[]);
        const edges=new vis.DataSet((data.edges||[]).map(e=>({
            ...e,
            smooth:{type:'dynamic'},
            arrows:{to:{enabled:false}}
        })));
        const options={
            nodes:{
                font:{size:11,face:'Plus Jakarta Sans',color:getComputedStyle(document.documentElement).getPropertyValue('--text').trim()},
                borderWidth:2,
                shadow:true
            },
            edges:{
                selectionWidth:2,
                smooth:{enabled:true,type:'dynamic'},
                color:{inherit:false},
                shadow:false
            },
            groups:{
                drug:{color:{background:'#6366f1',border:'#4338ca'},shape:'dot'},
                disease:{color:{background:'#10b981',border:'#047857'},shape:'dot'},
                protein:{color:{background:'#ec4899',border:'#be185d'},shape:'dot'}
            },
            physics:{
                enabled:true,
                stabilization:{iterations:160,fit:true},
                barnesHut:{gravitationalConstant:-9000,centralGravity:.18,springLength:120,springConstant:.035,damping:.25}
            },
            interaction:{hover:true,tooltipDelay:120,navigationButtons:true,keyboard:false}
        };
        if(fullNetworkInst)fullNetworkInst.destroy();
        fullNetworkInst=new vis.Network(box,{nodes,edges},options);
        fullNetworkInst.on('click',params=>{
            if(!detail)return;
            if(params.nodes?.length){
                const id=params.nodes[0];
                const node=nodes.get(id);
                const connectedEdges=fullNetworkInst.getConnectedEdges(id)||[];
                const groupLabel=node.group==='drug'?'Thuốc':(node.group==='disease'?'Bệnh':(node.group==='protein'?'Protein':'Node'));
                detail.style.display='block';
                detail.innerHTML=`
                    <b>${esc(groupLabel)}: ${esc(node.label)}</b>
                    <small>ID node: ${esc(node.id)} · Số liên kết đang hiển thị: ${fmtNum(connectedEdges.length)}</small>
                `;
                return;
            }
            if(params.edges?.length){
                const id=params.edges[0];
                const edge=edges.get(id);
                const from=nodes.get(edge.from);
                const to=nodes.get(edge.to);
                detail.style.display='block';
                detail.innerHTML=`
                    <b>Liên kết: ${esc(from?.label||edge.from)} ↔ ${esc(to?.label||edge.to)}</b>
                    <small>Loại: ${esc(data.relation_label||relation)} · Model view: ${esc(data.model_view)} · Edge ID: ${esc(edge.id)}</small>
                `;
                return;
            }
            detail.style.display='none';
            detail.innerHTML='';
        });
        const metrics=$('fullNetworkMetrics');
        if(metrics){
            const summary=datasetSummaryRows.find(r=>r.dataset===dataset)||datasetSummaryFallback.find(r=>r.dataset===dataset)||datasetSummaryFallback[0];
            metrics.innerHTML=`
                <div class="full-network-metric"><small>Dataset</small><b>${esc(dataset)}</b></div>
                <div class="full-network-metric"><small>Drugs</small><b>${fmtNum(summary.drugs)}</b></div>
                <div class="full-network-metric"><small>Diseases</small><b>${fmtNum(summary.diseases)}</b></div>
                <div class="full-network-metric"><small>Proteins</small><b>${fmtNum(summary.proteins)}</b></div>
                <div class="full-network-metric"><small>Drug-Disease</small><b>${fmtNum(summary.drug_disease_associations)}</b></div>
                <div class="full-network-metric"><small>Drug-Protein</small><b>${fmtNum(summary.drug_protein_associations)}</b></div>
                <div class="full-network-metric"><small>Disease-Protein</small><b>${fmtNum(summary.disease_protein_associations)}</b></div>
                <div class="full-network-metric"><small>Sparsity</small><b>${Number(summary.sparsity||0).toFixed(4)}</b></div>
                <div class="full-network-metric"><small>Graph edges</small><b>${fmtNum(data.rendered_edges)} / ${fmtNum(data.total_edges)}</b></div>
            `;
        }
        status.innerHTML=`<i class="bi bi-check-circle-fill" style="color:var(--green)"></i> ${esc(dataset)} · ${esc(data.relation_label||relation)} · ${fmtNum(data.rendered_nodes)} nodes · ${fmtNum(data.rendered_edges)} / ${fmtNum(data.total_edges)} edges · ${esc(data.model_view)}`;
    }catch(e){
        status.innerHTML=`<i class="bi bi-exclamation-triangle-fill" style="color:var(--red)"></i> Không tải được graph. Hãy restart AI API nếu vừa cập nhật code.`;
        console.error(e);
    }
}
async function loadDatasetSummary(){
    try{
        const data=await get('/dataset_summary');
        renderDatasetSummary(data.ok?(data.items||[]):datasetSummaryFallback);
    }catch(e){
        renderDatasetSummary(datasetSummaryFallback);
    }
}

// Skeleton loader helper
function skeleton(rows=3){return `<div style="display:flex;flex-direction:column;gap:10px">${Array(rows).fill('<div class="skeleton" style="height:52px"></div>').join('')}</div>`}

// Load selection lists on load or change
async function loadOpts(){
    const ds=currentDataset(),type=$('pType').value;
    $('linkDrugs').href=`index.php?action=catalog&type=drug&dataset=${encodeURIComponent(ds)}`;
    $('linkDiseases').href=`index.php?action=catalog&type=disease&dataset=${encodeURIComponent(ds)}`;
    $('linkProteins').href=`index.php?action=catalog&type=protein&dataset=${encodeURIComponent(ds)}`;
    $('pKeyword').innerHTML='<option value="">Đang tải dữ liệu...</option>';
    const ep=type==='drug'?'/drug_options':'/disease_options';
    try{
        const data=await get(`${ep}?dataset=${encodeURIComponent(ds)}&limit=700`);
        const items=data.items||data.options||data.drugs||data.diseases||[];
        $('pKeyword').innerHTML='<option value="">-- Chọn hoạt chất --</option>'+items.map(i=>`<option value="${esc(i.name||i.id)}">${esc(i.name||i.id)}</option>`).join('');
        if(type==='drug'){
            $('sDrugs').textContent=items.length;
            const d2=await get(`/disease_options?dataset=${encodeURIComponent(ds)}&limit=700`);
            $('sDiseases').textContent=(d2.items||d2.options||d2.diseases||[]).length||'—';
        } else {
            $('sDiseases').textContent=items.length;
            const d2=await get(`/drug_options?dataset=${encodeURIComponent(ds)}&limit=700`);
            $('sDrugs').textContent=(d2.items||d2.options||d2.drugs||[]).length||'—';
        }
        // Load target proteins count
        try{const pInfo=await get(`/protein_count?dataset=${encodeURIComponent(ds)}`);$('sProteins').textContent=pInfo.count||'—'}catch(e){$('sProteins').textContent='—'}
        
        // Load symptoms based on generation dataset selection
        const dis=await get(`/disease_options?dataset=${encodeURIComponent(ds)}&limit=700`);
        const disList=dis.items||dis.options||dis.diseases||[];
        
        // Save current checked symptoms
        const checkedBefore=new Set([...document.querySelectorAll('.gsym-check:checked')].map(c=>c.value));
        const allNames=new Set([...checkedBefore,...disList.map(d=>d.name)]);
        const sorted=[...allNames].sort();
        
        $('gSymptomsBox').innerHTML=sorted.map(name=>{
            const checked=checkedBefore.has(name)?'checked':'';
            const fromOther=!disList.some(d=>d.name===name);
            const badge=fromOther?` <span style="font-size:10px;color:var(--amber); font-weight:700;">(ngoại lai)</span>`:'';
            return`<label style="display:flex;align-items:center;gap:10px;padding:6px 8px;border-radius:8px;cursor:pointer;font-size:13px;transition:background 0.15s"><input type="checkbox" class="gsym-check" value="${esc(name)}" ${checked} style="accent-color:var(--primary);width:16px;height:16px;cursor:pointer"><span>${esc(name)}${badge}</span></label>`;
        }).join('');
    }catch(e){console.error(e)}
}

// Render Result Table
function renderTbl(id,pack){
    const box=$(id);
    if(!pack||!pack.ok){box.innerHTML=`<div class="ml-alert"><i class="bi bi-exclamation-circle-fill"></i> <span>${esc(pack?.error||'Lỗi xử lý kết quả')}</span></div>`;return}
    const rows=pack.results||[];
    if(!rows.length){box.innerHTML='<div class="empty-state"><div class="ico"><i class="bi bi-clipboard2-minus"></i></div><div>Không có kết quả phân tích</div></div>';return}
    
    const allSameDrug=rows.every(r=>(r.drug_name||'')===(rows[0].drug_name||''));
    const getName=allSameDrug?(r=>r.disease_name||r.name||''):(r=>r.drug_name||r.name||'');
    const showImg=!allSameDrug;
    
    box.innerHTML=`<table class="ai-table">
    <thead>
        <tr>
            <th style="width: 50px;">#</th>
            <th>Tên đối tượng</th>
            <th>Mức tin cậy</th>
            ${showImg?'<th style="width: 140px;">Cấu trúc phân tử</th>':''}
        </tr>
    </thead>
    <tbody>
        ${rows.map((r,i)=>{
            const name=getName(r),smi=showImg?(r.smiles||''):'',pct=Math.round((r.score||0)*100);
            return`<tr class="fade-up" style="animation-delay:${i*40}ms">
                <td style="font-weight:800;color:var(--primary)">${i+1}</td>
                <td>
                    <b style="font-size:14px; color:var(--text);">${esc(name)}</b>
                    ${smi?`<div class="smiles" style="display:block; margin-top:4px;">${esc(smi)}</div>`:''}
                </td>
                <td>
                    <span class="ai-pct">${pct}%</span>
                    <div class="ai-bar"><div class="ai-bar-fill" style="width:${pct}%"></div></div>
                </td>
                ${showImg?`<td>${smi?`<a href="https://molview.org/?smiles=${encodeURIComponent(smi)}" target="_blank" title="Xem cấu trúc 3D trên MolView"><img class="ai-mol" src="${molImg(smi)}" onerror="this.outerHTML='<small style=\'color:var(--text-dim)\'>Không hỗ trợ ảnh</small>'"></a>`:'—'}</td>`:''}
            </tr>`
        }).join('')}
    </tbody>
    </table>`;
}

function renderProteinPanel(graph){
    const panel=$('proteinPanel');
    if(!panel)return;

    const nodes=(graph?.nodes||[])
        .filter(n=>n.model_type==='protein'||n.type==='protein');
    const unique=[];
    const seen=new Set();

    nodes.forEach(n=>{
        const key=n.id||n.label;
        if(!key||seen.has(key))return;
        seen.add(key);
        unique.push(n);
    });

    if(!unique.length){
        panel.style.display='none';
        panel.innerHTML='';
        return;
    }

    panel.style.display='block';
    panel.innerHTML=`
        <div class="ai-protein-head">
            <i class="bi bi-diagram-2-fill"></i>
            <span>Protein liên quan</span>
            <small style="color:var(--text-muted);font-weight:700;">${unique.length} protein được lấy từ mạng liên kết</small>
        </div>
        <div class="ai-protein-grid">
            ${unique.map((n,i)=>`
                <span class="ai-protein-chip" title="${esc(n.id||'')}">
                    <i class="bi bi-record-circle-fill"></i>
                    ${i+1}. ${esc(n.label||n.name||n.id)}
                </span>
            `).join('')}
        </div>
    `;
}

// Chart rendering
function renderChart(cur,orig){
    if(chartInst)chartInst.destroy();
    const rows=cur||[];
    const allSameDrug=rows.length>0&&rows.every(r=>(r.drug_name||'')===(rows[0].drug_name||''));
    const getName=allSameDrug?(r=>r.disease_name||r.name||''):(r=>r.drug_name||r.name||'');
    const isDark=document.documentElement.getAttribute('data-theme')==='dark';
    const inputName=$('pKeyword')?.value||'';
    const inputType=$('pType')?.value||'drug';
    const targetLabel=inputType==='drug'?'bệnh được dự đoán':'thuốc được dự đoán';
    
    chartInst=new Chart($('chartCanvas'),{
        type:'bar',
        data:{
            labels:rows.slice(0,10).map((r,i)=>getName(r)||`#${i+1}`),
            datasets:[
                {label:'Mô hình cải tiến',data:(cur||[]).slice(0,10).map(r=>Math.round((r.score||0)*100)),backgroundColor:'rgba(99,102,241,0.85)',borderRadius:8,borderWidth:0},
                {label:'Mô hình gốc AMDGT',data:(orig||[]).slice(0,10).map(r=>Math.round((r.score||0)*100)),backgroundColor:'rgba(245,158,11,0.75)',borderRadius:8,borderWidth:0}
            ]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{
                title:{
                    display:!!inputName,
                    text:inputName?`${inputName} → Top ${targetLabel}`:'',
                    color:isDark?'#f8fafc':'#0f172a',
                    font:{size:15,weight:'800',family:'Plus Jakarta Sans'},
                    padding:{bottom:4}
                },
                subtitle:{
                    display:!!inputName,
                    text:inputType==='drug'?'Tên thuốc là đầu vào, trục X là các bệnh mô hình dự đoán.':'Tên bệnh là đầu vào, trục X là các thuốc mô hình dự đoán.',
                    color:isDark?'#94a3b8':'#64748b',
                    font:{size:11,weight:'700',family:'Plus Jakarta Sans'},
                    padding:{bottom:12}
                },
                legend:{labels:{color:isDark?'#e2e8f0':'#0f172a',font:{weight:'700',family:'Plus Jakarta Sans'}}}
            },
            scales:{
                x:{ticks:{color:isDark?'#94a3b8':'#475569',maxRotation:35,font:{size:11,weight:'600'}},grid:{display:false}},
                y:{ticks:{color:isDark?'#94a3b8':'#475569',callback:v=>v+'%'},grid:{color:isDark?'#242f4c':'#e2e8f0'}}
            }
        }
    });
}

// 3D Graph logic override (defined below)
let drawGraph = function(graph) {};

// Run Link Prediction (AJAX)
$('btnPredict').onclick=async()=>{
    const kw=$('pKeyword').value;
    if(!kw){
        $('pStatus').innerHTML='<span style="color:var(--red)"><i class="bi bi-x-circle-fill"></i> Vui lòng chọn một hoạt chất hoặc bệnh học.</span>';
        return;
    }
    $('pStatus').innerHTML='<span class="pulse"><i class="bi bi-hourglass-split"></i> Đang tải kết quả và phân tích cấu trúc liên kết...</span>';
    $('resultCard').style.display='block';
    $('graphCard').style.display='block';
    $('boxCur').innerHTML=skeleton(4);
    $('boxOrig').innerHTML=skeleton(4);
    renderProteinPanel(null);
    
    // Smooth scroll to results
    setTimeout(() => {
        $('resultCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);

    try{
        const topK=Math.max(1,Math.min(50,+$('pTopK').value||9));
        $('pTopK').value=topK;
        const data=await post('/predict_compare',{dataset:currentDataset(),input_type:$('pType').value,keyword:kw,top_k:topK});
        renderTbl('boxCur',data.current);
        renderTbl('boxOrig',data.original);
        renderProteinPanel(data.graph);
        renderChart(data.current?.results,data.original?.results);
        drawGraph(data.graph);
        
        if(data.ok){
            $('pStatus').innerHTML='<span style="color:var(--green)"><i class="bi bi-check-circle-fill"></i> Hoàn tất dự đoán liên kết.</span>';
            // Save search to local PHP history DB silently
            fetch('index.php?action=save_history&input_type='+encodeURIComponent($('pType').value)+'&keyword='+encodeURIComponent(kw)+'&dataset='+encodeURIComponent(currentDataset()));
        } else {
            $('pStatus').innerHTML='<span style="color:var(--red)"><i class="bi bi-exclamation-circle-fill"></i> Lỗi mô hình AI.</span>';
        }
    }catch(e){
        $('pStatus').innerHTML='<span style="color:var(--red)"><i class="bi bi-wifi-off"></i> Lỗi kết nối đến máy chủ AI.</span>';
        console.error(e);
    }
};

// Run Drug Generation (AJAX)
$('btnGenerate').onclick=async()=>{
    const dis=$('gDisease').value.trim();
    const symptoms=[...document.querySelectorAll('.gsym-check:checked')].map(c=>c.value);
    if(!dis && !symptoms.length){
        $('genResult').innerHTML='<div class="ml-alert"><i class="bi bi-exclamation-circle-fill"></i> Vui lòng nhập tên bệnh hoặc chọn ít nhất một triệu chứng lâm sàng.</div>';
        return;
    }
    $('genResult').innerHTML=skeleton(3);
    try{
        const data=await post('/generate_drug',{dataset:currentDataset(),disease:dis,symptoms,n:+$('gN').value||10});
        if(!data.ok||!(data.results||[]).length){
            $('genResult').innerHTML='<div class="ml-alert"><i class="bi bi-x-circle-fill"></i> Không sinh được ứng viên thuốc. Vui lòng chọn các triệu chứng lâm sàng khác.</div>';
            return;
        }
        const rows=data.results;
        $('genResult').innerHTML=`<div class="ml-alert success" style="margin-top: 15px;"><i class="bi bi-check-circle-fill"></i> Đã tạo thành công <b>${rows.length}</b> ứng viên hoạt chất cho bệnh lý: <b>${esc(dis||'triệu chứng lâm sàng')}</b>.</div>
        <table class="ai-table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Tên gợi ý</th>
                <th>Phát triển từ hoạt chất gốc</th>
                <th>Cấu trúc chuỗi SMILES</th>
                <th style="width: 140px;">Cấu trúc y học</th>
            </tr>
        </thead>
        <tbody>
            ${rows.map((r,i)=>{
                const smi=r.smiles||'';
                return`<tr class="fade-up" style="animation-delay:${i*40}ms">
                    <td style="font-weight:800;color:var(--green)">${i+1}</td>
                    <td><b style="color:var(--text);">${esc(dis||'Drug')}-${i+1}</b></td>
                    <td><small style="color:var(--text-muted); font-weight:600;"><i class="bi bi-arrow-return-left"></i> ${esc(r.base_drug||'—')}</small></td>
                    <td class="smiles">${esc(smi)}</td>
                    <td>${smi?`<a href="https://molview.org/?smiles=${encodeURIComponent(smi)}" target="_blank" title="Xem cấu trúc 3D"><img class="ai-mol" src="${molImg(smi)}" onerror="this.outerHTML='<small>N/A</small>'"></a>`:'—'}</td>
                </tr>`
            }).join('')}
        </tbody>
        </table>`;
    }catch(e){
        $('genResult').innerHTML='<div class="ml-alert"><i class="bi bi-wifi-off"></i> Lỗi kết nối đến dịch vụ tạo sinh.</div>';
    }
};

// Mobile menu handler
document.querySelector('[data-menu-btn]')?.addEventListener('click',()=>document.querySelector('.ml-sidebar').classList.toggle('open'));

// Selected symptoms display updates
function updateSymTags(){
    const checked=[...document.querySelectorAll('.gsym-check:checked')].map(c=>c.value);
    $('gSelectedTags').innerHTML=checked.length?checked.map(name=>`<span class="sym-tag">${esc(name)}<span class="sym-x" data-sym="${esc(name)}">&times;</span></span>`).join(''):'<span style="font-size:12px;color:var(--text-dim)">Chưa chọn triệu chứng lâm sàng nào</span>';
    
    // Bind tags click to remove check
    document.querySelectorAll('.sym-x').forEach(x=>x.onclick=()=>{
        const val=x.dataset.sym;
        const cb=document.querySelector(`.gsym-check[value="${val}"]`);
        if(cb){cb.checked=false}
        updateSymTags();
    });
}
document.addEventListener('change',e=>{if(e.target.classList.contains('gsym-check'))updateSymTags()});

// Selection bindings
$('globalDataset')?.addEventListener('change',()=>refreshDatasetViews(true));
$('pType').onchange=loadOpts;
$('btnLoadFullNetwork')?.addEventListener('click',loadFullDrugDiseaseNetwork);
initGlobalDataset();
loadDatasetSummary();
loadOpts();

// === 3D VANTA BACKGROUND ===
try{
    if(window.VANTA)VANTA.NET({el:'#vantaBg',mouseControls:true,touchControls:true,minHeight:200,scale:1,color:0x818cf8,backgroundColor:0x00000000,points:11,maxDistance:18,spacing:16,showDots:true});
}catch(e){}

// === 3D Graph with Three.js ===
drawGraph = function(graph){
    const container=$('aiGraph');
    if(!graph||!graph.nodes?.length){
        container.innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted); font-weight: 500;"><i class="bi bi-diagram-3" style="font-size:2rem;margin-right:8px"></i>Chạy dự đoán để kết xuất sơ đồ 3D</div>';
        return;
    }
    container.innerHTML='';

    const width=container.clientWidth||700, height=container.clientHeight||500;
    const scene=new THREE.Scene();
    scene.background=new THREE.Color(0x0b1020);
    
    const camera=new THREE.PerspectiveCamera(48,width/height,0.1,1000);
    camera.position.set(0,30,72);

    const renderer=new THREE.WebGLRenderer({antialias:true,alpha:false});
    renderer.setSize(width,height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
    renderer.setClearColor(0x0b1020,1);
    container.appendChild(renderer.domElement);
    renderer.domElement.style.borderRadius='22px';
    renderer.domElement.style.cursor='grab';
    const counts={
        input:graph.nodes.filter(n=>n.model_type==='input').length,
        current:graph.nodes.filter(n=>n.model_type==='current').length,
        original:graph.nodes.filter(n=>n.model_type==='original').length,
        protein:graph.nodes.filter(n=>n.model_type==='protein').length
    };
    const hud=document.createElement('div');
    hud.className='ai-graph-hud';
    hud.innerHTML=`
        <div class="ai-graph-title">
            <b>Biomedical link map</b>
            <small>${graph.edges?.length||0} connections rendered in 3D</small>
        </div>
        <div class="ai-graph-metrics">
            <div class="ai-graph-chip"><span>${counts.input}</span><small>Input</small></div>
            <div class="ai-graph-chip"><span>${counts.current}</span><small>AI</small></div>
            <div class="ai-graph-chip"><span>${counts.original}</span><small>AMDGT</small></div>
            <div class="ai-graph-chip"><span>${counts.protein}</span><small>Protein</small></div>
        </div>`;
    container.appendChild(hud);
    const footer=document.createElement('div');
    footer.className='ai-graph-footer';
    footer.innerHTML=`
        <div class="ai-graph-status"><span class="ai-live-dot"></span> Live topology stream</div>
        <div class="ai-graph-mode">Drag rotate · Scroll zoom · Click node</div>`;
    container.appendChild(footer);
    const aurora=document.createElement('div');
    aurora.className='ai-graph-aurora';
    container.appendChild(aurora);

    const controls=new THREE.OrbitControls(camera,renderer.domElement);
    controls.enableDamping=true;
    controls.dampingFactor=0.05;
    controls.autoRotate=true;
    controls.autoRotateSpeed=0.45;
    controls.minDistance=48;
    controls.maxDistance=115;
    controls.target.set(0,0,0);

    const colorMap={input:0xec4899,current:0x10b981,original:0xf59e0b,protein:0x818cf8};
    const nodeMeshes=[];
    const nodePos={};
    const edgePulses=[];
    const floorY=-14;
    const orbitTotal=Math.max(graph.nodes.filter(n=>n.model_type!=='input').length,1);
    let orbitIndex=0;

    function labelColor(n,isInput,isCurrent,isProtein){
        if(isInput)return '#f9a8d4';
        if(isCurrent)return '#6ee7b7';
        if(isProtein)return '#c4b5fd';
        return '#fcd34d';
    }

    function roundRect(ctx,x,y,w,h,r){
        ctx.beginPath();
        ctx.moveTo(x+r,y);
        ctx.lineTo(x+w-r,y);
        ctx.quadraticCurveTo(x+w,y,x+w,y+r);
        ctx.lineTo(x+w,y+h-r);
        ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);
        ctx.lineTo(x+r,y+h);
        ctx.quadraticCurveTo(x,y+h,x,y+h-r);
        ctx.lineTo(x,y+r);
        ctx.quadraticCurveTo(x,y,x+r,y);
        ctx.closePath();
    }

    function makeBadgeTexture(n,isInput,isCurrent,isProtein,col){
        const canvas=document.createElement('canvas');
        canvas.width=256;
        canvas.height=256;
        const ctx=canvas.getContext('2d');
        const color='#'+col.toString(16).padStart(6,'0');
        const label=isInput?'IN':(isCurrent?'AI':(isProtein?'PR':'OG'));

        ctx.clearRect(0,0,256,256);
        ctx.shadowColor=color;
        ctx.shadowBlur=32;
        ctx.fillStyle=color;
        ctx.beginPath();
        for(let i=0;i<6;i++){
            const angle=Math.PI/6+i*Math.PI/3;
            const x=128+Math.cos(angle)*74;
            const y=128+Math.sin(angle)*74;
            if(i===0)ctx.moveTo(x,y);else ctx.lineTo(x,y);
        }
        ctx.closePath();
        ctx.fill();

        const grad=ctx.createLinearGradient(72,62,184,194);
        grad.addColorStop(0,'rgba(255,255,255,0.42)');
        grad.addColorStop(0.45,'rgba(255,255,255,0.14)');
        grad.addColorStop(1,'rgba(15,23,42,0.16)');
        ctx.shadowBlur=0;
        ctx.fillStyle=grad;
        ctx.fill();

        ctx.strokeStyle='rgba(255,255,255,0.68)';
        ctx.lineWidth=7;
        ctx.stroke();

        ctx.fillStyle='rgba(15,23,42,0.22)';
        roundRect(ctx,84,99,88,58,16);
        ctx.fill();

        ctx.fillStyle='#ffffff';
        ctx.font='900 36px Inter, sans-serif';
        ctx.textAlign='center';
        ctx.textBaseline='middle';
        ctx.fillText(label,128,129);

        const tex=new THREE.CanvasTexture(canvas);
        tex.needsUpdate=true;
        return tex;
    }

    function makeHaloTexture(col){
        const canvas=document.createElement('canvas');
        canvas.width=256;
        canvas.height=256;
        const ctx=canvas.getContext('2d');
        const color='#'+col.toString(16).padStart(6,'0');
        const grad=ctx.createRadialGradient(128,128,18,128,128,116);
        grad.addColorStop(0,color);
        grad.addColorStop(0.42,'rgba(255,255,255,0.14)');
        grad.addColorStop(1,'rgba(255,255,255,0)');
        ctx.fillStyle=grad;
        ctx.fillRect(0,0,256,256);
        const tex=new THREE.CanvasTexture(canvas);
        tex.needsUpdate=true;
        return tex;
    }

    const orbitRings=[];
    [10,16,23].forEach((radius,idx)=>{
        const ringGeo=new THREE.TorusGeometry(radius,0.035,8,120);
        const ringMat=new THREE.MeshBasicMaterial({
            color:idx===0?0xec4899:(idx===1?0x818cf8:0x10b981),
            transparent:true,
            opacity:0.20-idx*0.035
        });
        const ring=new THREE.Mesh(ringGeo,ringMat);
        ring.rotation.x=Math.PI/2;
        ring.rotation.z=idx*0.45;
        scene.add(ring);
        orbitRings.push(ring);
    });
    const grid=new THREE.GridHelper(76,32,0x334155,0x1e293b);
    grid.position.y=floorY;
    grid.material.transparent=true;
    grid.material.opacity=0.22;
    scene.add(grid);
    const floorGlowGeo=new THREE.CircleGeometry(32,96);
    const floorGlowMat=new THREE.MeshBasicMaterial({
        color:0x818cf8,
        transparent:true,
        opacity:0.055,
        blending:THREE.AdditiveBlending,
        depthWrite:false,
        side:THREE.DoubleSide
    });
    const floorGlow=new THREE.Mesh(floorGlowGeo,floorGlowMat);
    floorGlow.rotation.x=-Math.PI/2;
    floorGlow.position.y=floorY+0.02;
    scene.add(floorGlow);

    // Render nodes - clean badge sprites
    graph.nodes.forEach((n,i)=>{
        const isInput=n.model_type==='input';
        const isCurrent=n.model_type==='current';
        const isProtein=n.model_type==='protein';
        const col=colorMap[n.model_type]||0x94a3b8;
        const mat=new THREE.SpriteMaterial({
            map:makeBadgeTexture(n,isInput,isCurrent,isProtein,col),
            transparent:true,
            depthWrite:false
        });
        const mesh=new THREE.Sprite(mat);
        const badgeSize=isInput?9.5:7.2;
        mesh.scale.set(badgeSize,badgeSize,1);

        if(isInput){
            mesh.position.set(0,0,0);
        }else{
            const angle=(orbitIndex/orbitTotal)*Math.PI*2;
            const radius=22+(orbitIndex%3)*4;
            const y=((orbitIndex%5)-2)*4.2;
            orbitIndex++;
            mesh.position.set(Math.cos(angle)*radius, y, Math.sin(angle)*radius);
        }

        nodePos[n.id]=mesh.position.clone();
        const halo=new THREE.Sprite(new THREE.SpriteMaterial({
            map:makeHaloTexture(col),
            transparent:true,
            blending:THREE.AdditiveBlending,
            depthWrite:false,
            opacity:isInput?0.72:0.48
        }));
        halo.position.copy(mesh.position);
        halo.scale.set(badgeSize*2.4,badgeSize*2.4,1);
        scene.add(halo);
        scene.add(mesh);
        const platformGeo=new THREE.TorusGeometry(isInput?4.8:3.4,0.06,8,60);
        const platformMat=new THREE.MeshBasicMaterial({
            color:col,
            transparent:true,
            opacity:isInput?0.44:0.28,
            blending:THREE.AdditiveBlending,
            depthWrite:false
        });
        const platform=new THREE.Mesh(platformGeo,platformMat);
        platform.rotation.x=Math.PI/2;
        platform.position.set(mesh.position.x,floorY+0.18,mesh.position.z);
        scene.add(platform);
        const beaconGeo=new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(mesh.position.x,floorY+0.3,mesh.position.z),
            new THREE.Vector3(mesh.position.x,mesh.position.y-1.4,mesh.position.z)
        ]);
        const beaconMat=new THREE.LineBasicMaterial({
            color:col,
            transparent:true,
            opacity:isInput?0.36:0.20,
            blending:THREE.AdditiveBlending,
            depthWrite:false
        });
        const beacon=new THREE.Line(beaconGeo,beaconMat);
        scene.add(beacon);
        nodeMeshes.push({mesh,halo,platform,beacon,badgeSize,data:n});

        // Glow ring for inputs
        if(isInput){
            const ringGeo=new THREE.RingGeometry(5.5,6.5,32);
            const ringMat=new THREE.MeshBasicMaterial({color:0xec4899,transparent:true,opacity:0.35,side:THREE.DoubleSide});
            const ring=new THREE.Mesh(ringGeo,ringMat);
            ring.position.copy(mesh.position);
            scene.add(ring);
        }

        // Text sprite tags
        const canvas=document.createElement('canvas');
        canvas.width=640;canvas.height=112;
        const ctx=canvas.getContext('2d');
        ctx.textAlign='center';
        ctx.textBaseline='middle';
        const lbl=(n.label||'').length>24?(n.label||'').slice(0,22)+'...':(n.label||'');
        const scoreText=n.score?` (${Math.round(n.score*100)}%)`:'';
        const typeText=isInput?'[INPUT]':(isCurrent?'[MÔ HÌNH CẢI TIẾN]':(isProtein?'[PROTEIN]':'[MÔ HÌNH GỐC]'));
        ctx.fillText(lbl+scoreText,256,32);
        ctx.font='bold 13px Inter';
        ctx.fillStyle=isInput?'#f9a8d4':(isCurrent?'#6ee7b7':'#fcd34d');
        ctx.fillText(typeText,256,58);
        ctx.clearRect(0,0,canvas.width,canvas.height);
        const cleanTypeText=isInput?'INPUT':(isCurrent?'MODEL MOI':(isProtein?'PROTEIN':'AMDGT GOC'));
        ctx.shadowColor='rgba(0,0,0,0.45)';
        ctx.shadowBlur=18;
        ctx.fillStyle='rgba(15,23,42,0.78)';
        roundRect(ctx,64,12,512,78,18);
        ctx.fill();
        ctx.shadowBlur=0;
        ctx.strokeStyle='rgba(148,163,184,0.25)';
        ctx.lineWidth=2;
        roundRect(ctx,64,12,512,78,18);
        ctx.stroke();
        ctx.fillStyle='#f8fafc';
        ctx.font='800 23px Inter, sans-serif';
        ctx.fillText(lbl+scoreText,320,38);
        ctx.font='800 13px Inter, sans-serif';
        ctx.fillStyle=labelColor(n,isInput,isCurrent,isProtein);
        ctx.fillText(cleanTypeText,320,67);
        if(n.score){
            const pct=Math.max(0,Math.min(1,n.score));
            const barGrad=ctx.createLinearGradient(190,92,450,92);
            barGrad.addColorStop(0,labelColor(n,isInput,isCurrent,isProtein));
            barGrad.addColorStop(1,'#ffffff');
            ctx.fillStyle='rgba(148,163,184,0.22)';
            roundRect(ctx,190,88,260,8,4);
            ctx.fill();
            ctx.fillStyle=barGrad;
            roundRect(ctx,190,88,260*pct,8,4);
            ctx.fill();
        }else{
            ctx.fillStyle='rgba(148,163,184,0.70)';
            ctx.font='700 11px Inter, sans-serif';
            ctx.fillText('TOPOLOGY ANCHOR',320,92);
        }
        
        const tex=new THREE.CanvasTexture(canvas);
        const spMat=new THREE.SpriteMaterial({map:tex,transparent:true,depthWrite:false});
        const sprite=new THREE.Sprite(spMat);
        sprite.position.copy(mesh.position);
        sprite.position.y+=6.2;
        sprite.scale.set(23,4,1);
        scene.add(sprite);
        nodeMeshes[nodeMeshes.length-1].label=sprite;
    });

    // Edges
    graph.edges.forEach(e=>{
        const from=nodePos[e.from], to=nodePos[e.to];
        if(!from||!to)return;
        
        const length=from.distanceTo(to);
        const mid=new THREE.Vector3().addVectors(from,to).multiplyScalar(0.5);
        const col=e.model==='current'?0x10b981:(e.model==='protein'?0x818cf8:0xf59e0b);

        const control=mid.clone();
        control.y+=Math.min(14,6+length*0.08);
        const curve=new THREE.QuadraticBezierCurve3(from,control,to);
        const glowGeo=new THREE.TubeGeometry(curve,32,0.22,10,false);
        const glowMat=new THREE.MeshBasicMaterial({
            color:col,
            transparent:true,
            opacity:0.16,
            blending:THREE.AdditiveBlending,
            depthWrite:false
        });
        const glowMesh=new THREE.Mesh(glowGeo,glowMat);
        scene.add(glowMesh);

        const tubeGeo=new THREE.TubeGeometry(curve,32,0.075,8,false);
        const tubeMat=new THREE.MeshBasicMaterial({color:col,transparent:true,opacity:0.78});
        const tubeMesh=new THREE.Mesh(tubeGeo,tubeMat);
        scene.add(tubeMesh);

        const arrowPos=curve.getPoint(0.72);
        const arrowGeo=new THREE.SphereGeometry(0.55,10,10);
        const arrowMat=new THREE.MeshBasicMaterial({color:col});
        const arrow=new THREE.Mesh(arrowGeo,arrowMat);
        arrow.position.copy(arrowPos);
        scene.add(arrow);

        const packetGeo=new THREE.SphereGeometry(0.42,16,16);
        const packetMat=new THREE.MeshBasicMaterial({
            color:0xffffff,
            transparent:true,
            opacity:0.92,
            blending:THREE.AdditiveBlending,
            depthWrite:false
        });
        const packet=new THREE.Mesh(packetGeo,packetMat);
        packet.position.copy(curve.getPoint(0.18));
        scene.add(packet);
        edgePulses.push({
            curve,
            packet,
            glow:glowMesh,
            tube:tubeMesh,
            speed:0.11+edgePulses.length*0.012,
            offset:(edgePulses.length*0.19)%1
        });
    });

    // Ambient floating particles
    const particleGeo=new THREE.BufferGeometry();
    const pCount=90;
    const positions=new Float32Array(pCount*3);
    for(let i=0;i<pCount*3;i++)positions[i]=(Math.random()-0.5)*105;
    particleGeo.setAttribute('position',new THREE.BufferAttribute(positions,3));
    const pMat=new THREE.PointsMaterial({color:0x818cf8,size:0.28,transparent:true,opacity:0.22});
    scene.add(new THREE.Points(particleGeo,pMat));

    // Lights
    scene.add(new THREE.AmbientLight(0xffffff,0.72));
    const dl=new THREE.DirectionalLight(0xffffff,0.9);
    dl.position.set(30,60,30);
    scene.add(dl);
    const pl=new THREE.PointLight(0x818cf8,1.2,135);
    pl.position.set(0,25,0);
    scene.add(pl);
    const coreGeo=new THREE.SphereGeometry(3.6,32,32);
    const coreMat=new THREE.MeshBasicMaterial({
        color:0xec4899,
        transparent:true,
        opacity:0.18,
        blending:THREE.AdditiveBlending,
        depthWrite:false
    });
    const coreGlow=new THREE.Mesh(coreGeo,coreMat);
    scene.add(coreGlow);
    const scanGeo=new THREE.TorusGeometry(34,0.045,8,160);
    const scanMat=new THREE.MeshBasicMaterial({
        color:0x22d3ee,
        transparent:true,
        opacity:0.18,
        blending:THREE.AdditiveBlending,
        depthWrite:false
    });
    const scanRing=new THREE.Mesh(scanGeo,scanMat);
    scanRing.rotation.x=Math.PI/2;
    scanRing.position.y=floorY+0.5;
    scene.add(scanRing);

    let hovered=null;

    // Dynamic rotation & render
    const clock=new THREE.Clock();
    function animate(){
        requestAnimationFrame(animate);
        const t=clock.getElapsedTime();
        orbitRings.forEach((ring,idx)=>{
            ring.rotation.z+=0.0025*(idx+1);
            ring.material.opacity=(0.13+Math.sin(t*1.4+idx)*0.035);
        });
        nodeMeshes.forEach((nm,idx)=>{
            const pulse=1+Math.sin(t*1.9+idx*0.7)*0.08;
            const active=nm===hovered;
            const boost=active?1.24:1;
            nm.mesh.scale.set(nm.badgeSize*boost,nm.badgeSize*boost,1);
            nm.halo.scale.set(nm.badgeSize*2.4*pulse*boost,nm.badgeSize*2.4*pulse*boost,1);
            nm.halo.material.opacity=active?0.86:(idx===0?0.68:0.44);
            if(nm.label)nm.label.scale.set(active?25.5:23,active?4.45:4,1);
            nm.mesh.position.y=nm.halo.position.y+Math.sin(t*1.2+idx)*0.12;
            nm.platform.rotation.z+=0.004+(idx%3)*0.001;
            nm.platform.material.opacity=(active?0.58:(idx===0?0.38:0.22))+Math.sin(t*1.6+idx)*0.06;
            nm.beacon.material.opacity=(active?0.48:(idx===0?0.30:0.17))+Math.sin(t*1.4+idx)*0.045;
        });
        edgePulses.forEach((ep,idx)=>{
            const p=(ep.offset+t*ep.speed)%1;
            ep.packet.position.copy(ep.curve.getPoint(p));
            const size=0.85+Math.sin(t*5+idx)*0.22;
            ep.packet.scale.set(size,size,size);
            ep.packet.material.opacity=0.62+Math.sin(t*4.2+idx)*0.22;
            ep.glow.material.opacity=0.12+Math.sin(t*2+idx)*0.05;
        });
        floorGlow.material.opacity=0.045+Math.sin(t*1.1)*0.018;
        scanRing.rotation.z+=0.012;
        scanRing.scale.setScalar(0.86+(t*0.08%0.34));
        scanRing.material.opacity=0.24-(scanRing.scale.x-0.86)*0.45;
        coreGlow.scale.setScalar(1.0+Math.sin(t*2.1)*0.16);
        coreGlow.material.opacity=0.14+Math.sin(t*2.1)*0.05;
        controls.update();
        renderer.render(scene,camera);
    }
    animate();

    // Click trigger details
    const raycaster=new THREE.Raycaster();
    const mouse=new THREE.Vector2();
    function updateMouse(ev){
        const rect=renderer.domElement.getBoundingClientRect();
        mouse.x=((ev.clientX-rect.left)/width)*2-1;
        mouse.y=-((ev.clientY-rect.top)/height)*2+1;
        raycaster.setFromCamera(mouse,camera);
        const meshes=nodeMeshes.map(nm=>nm.mesh);
        const hits=raycaster.intersectObjects(meshes);
        hovered=hits.length?nodeMeshes[meshes.indexOf(hits[0].object)]:null;
        renderer.domElement.style.cursor=hovered?'pointer':'grab';
    }
    renderer.domElement.addEventListener('mousemove',updateMouse);
    renderer.domElement.addEventListener('mouseleave',()=>{
        hovered=null;
        renderer.domElement.style.cursor='grab';
    });
    renderer.domElement.addEventListener('click',ev=>{
        const rect=renderer.domElement.getBoundingClientRect();
        mouse.x=((ev.clientX-rect.left)/width)*2-1;
        mouse.y=-((ev.clientY-rect.top)/height)*2+1;
        raycaster.setFromCamera(mouse,camera);
        const meshes=nodeMeshes.map(nm=>nm.mesh);
        const hits=raycaster.intersectObjects(meshes);
        if(hits.length>0){
            const idx=meshes.indexOf(hits[0].object);
            if(idx>=0){
                const n=nodeMeshes[idx].data;
                const mTypeLabel = n.model_type==='input'?'Đầu vào':(n.model_type==='current'?'Mô hình cải tiến':(n.model_type==='protein'?'Đích Protein':'Mô hình gốc'));
                $('nodeInfo').style.display='block';
                $('nodeInfo').innerHTML=`<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div><strong>Tên:</strong> ${esc(n.label)} <span class="ai-badge ${n.model_type==='current'?'ai-badge-cur':'ai-badge-orig'}" style="margin-left:6px;">${esc(mTypeLabel)}</span></div>
                    ${n.score?`<div><strong>Độ tin cậy:</strong> <span class="ai-pct" style="font-size:15px;">${Math.round(n.score*100)}%</span></div>`:''}
                </div>
                ${n.smiles?`<div style="margin-top:8px;"><strong>Chuỗi SMILES:</strong> <code class="smiles" style="max-width:100%; display:block; padding:6px; background:var(--card); border-radius:8px; margin-top:4px;">${esc(n.smiles)}</code></div>`:''}`;
            }
        }else{
            $('nodeInfo').style.display='none';
        }
    });
};
</script>
</body>
</html>
