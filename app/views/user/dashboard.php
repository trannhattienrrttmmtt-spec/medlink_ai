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
    height: 440px;
    border-radius: var(--radius);
    border: 1px solid var(--line);
    background: var(--card);
    box-shadow: var(--shadow);
}
.ai-legend {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 15px;
    flex-wrap: wrap;
    font-weight: 500;
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
            <div class="ml-card ml-stat-card"><div class="ml-card-head"><div><small>THUỐC</small><div class="ml-stat-number" id="sDrugs">—</div><small style="color:var(--green); font-weight:700;"><i class="bi bi-check-circle-fill"></i> Hoạt chất</small></div><div class="ml-stat-icon">💊</div></div></div>
            <div class="ml-card ml-stat-card"><div class="ml-card-head"><div><small>BỆNH</small><div class="ml-stat-number" id="sDiseases">—</div><small style="color:var(--green); font-weight:700;"><i class="bi bi-check-circle-fill"></i> Chỉ dấu</small></div><div class="ml-stat-icon">🧬</div></div></div>
            <div class="ml-card ml-stat-card"><div class="ml-card-head"><div><small>PROTEIN</small><div class="ml-stat-number" id="sProteins">—</div><small style="color:var(--green); font-weight:700;"><i class="bi bi-check-circle-fill"></i> Đích sinh học</small></div><div class="ml-stat-icon">🔬</div></div></div>
            <div class="ml-card ml-stat-card"><div class="ml-card-head"><div><small>MODELS</small><div class="ml-stat-number">2</div><small style="color:var(--text-muted); font-weight:700;">Cải tiến + Gốc AMDGT</small></div><div class="ml-stat-icon">🤖</div></div></div>
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
                                    <label>Dataset</label>
                                    <select id="pDataset">
                                        <option>B-dataset</option>
                                        <option>C-dataset</option>
                                        <option>F-dataset</option>
                                    </select>
                                </div>
                                <div class="ml-field">
                                    <label>Kiểu phân tích</label>
                                    <select id="pType">
                                        <option value="drug">Thuốc → Bệnh</option>
                                        <option value="disease">Bệnh → Thuốc</option>
                                    </select>
                                </div>
                                <div class="ml-field">
                                    <label>Top hiển thị (K)</label>
                                    <input id="pTopK" type="number" min="1" max="20" value="9">
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
                        <div style="display:grid;grid-template-columns:1.2fr 0.8fr;gap:12px">
                            <div class="ml-field">
                                <label>Tên bệnh lý chỉ định</label>
                                <input id="gDisease" placeholder="VD: Novel Respiratory disease, Long COVID...">
                            </div>
                            <div class="ml-field">
                                <label>Dataset nguồn</label>
                                <select id="gDataset">
                                    <option>B-dataset</option>
                                    <option>C-dataset</option>
                                    <option>F-dataset</option>
                                </select>
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
        <section class="ml-card fade-up" id="graphCard" style="display:none;margin-bottom:24px">
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

// Skeleton loader helper
function skeleton(rows=3){return `<div style="display:flex;flex-direction:column;gap:10px">${Array(rows).fill('<div class="skeleton" style="height:52px"></div>').join('')}</div>`}

// Load selection lists on load or change
async function loadOpts(){
    const ds=$('pDataset').value,type=$('pType').value;
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
        const gDs=$('gDataset').value;
        const dis=await get(`/disease_options?dataset=${encodeURIComponent(gDs)}&limit=700`);
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
        const data=await post('/predict_compare',{dataset:$('pDataset').value,input_type:$('pType').value,keyword:kw,top_k:+$('pTopK').value||9});
        renderTbl('boxCur',data.current);
        renderTbl('boxOrig',data.original);
        renderProteinPanel(data.graph);
        renderChart(data.current?.results,data.original?.results);
        drawGraph(data.graph);
        
        if(data.ok){
            $('pStatus').innerHTML='<span style="color:var(--green)"><i class="bi bi-check-circle-fill"></i> Hoàn tất dự đoán liên kết.</span>';
            // Save search to local PHP history DB silently
            fetch('index.php?action=save_history&input_type='+encodeURIComponent($('pType').value)+'&keyword='+encodeURIComponent(kw)+'&dataset='+encodeURIComponent($('pDataset').value));
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
        const data=await post('/generate_drug',{dataset:$('gDataset').value,disease:dis,symptoms,n:+$('gN').value||10});
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
$('pDataset').onchange=loadOpts;
$('pType').onchange=loadOpts;
$('gDataset').onchange=loadOpts;
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

    const width=container.clientWidth||700, height=440;
    const scene=new THREE.Scene();
    
    const camera=new THREE.PerspectiveCamera(55,width/height,0.1,1000);
    camera.position.set(0,25,85);

    const renderer=new THREE.WebGLRenderer({antialias:true,alpha:true});
    renderer.setSize(width,height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
    container.appendChild(renderer.domElement);
    renderer.domElement.style.borderRadius='20px';
    renderer.domElement.style.cursor='grab';

    const controls=new THREE.OrbitControls(camera,renderer.domElement);
    controls.enableDamping=true;
    controls.dampingFactor=0.05;
    controls.autoRotate=true;
    controls.autoRotateSpeed=0.6;

    const colorMap={input:0xec4899,current:0x10b981,original:0xf59e0b,protein:0x818cf8};
    const nodeMeshes=[];
    const nodePos={};

    // Render nodes - custom meshes
    graph.nodes.forEach((n,i)=>{
        const isInput=n.model_type==='input';
        const isCurrent=n.model_type==='current';
        const isProtein=n.model_type==='protein';
        const col=colorMap[n.model_type]||0x94a3b8;
        let geo;

        if(isInput){
            geo=new THREE.IcosahedronGeometry(4.5,1);
        }else if(isCurrent){
            geo=new THREE.BoxGeometry(3.6,3.6,3.6);
        }else if(isProtein){
            geo=new THREE.TorusGeometry(2.2,0.7,12,24);
        }else{
            geo=new THREE.OctahedronGeometry(2.8);
        }

        const mat=new THREE.MeshPhongMaterial({color:col,emissive:col,emissiveIntensity:0.35,shininess:80,transparent:true,opacity:0.9});
        const mesh=new THREE.Mesh(geo,mat);

        if(isInput){
            mesh.position.set(0,0,0);
        }else{
            const angle=(i/(graph.nodes.length-1))*Math.PI*2;
            const radius=28+Math.random()*8;
            const y=(Math.random()-0.5)*18;
            mesh.position.set(Math.cos(angle)*radius, y, Math.sin(angle)*radius);
        }

        nodePos[n.id]=mesh.position.clone();
        scene.add(mesh);
        nodeMeshes.push({mesh,data:n});

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
        canvas.width=512;canvas.height=90;
        const ctx=canvas.getContext('2d');
        ctx.fillStyle='#ffffff';
        ctx.font='bold 20px Inter, sans-serif';
        ctx.textAlign='center';
        const lbl=(n.label||'').length>22?(n.label||'').slice(0,20)+'...':(n.label||'');
        const scoreText=n.score?` (${Math.round(n.score*100)}%)`:'';
        const typeText=isInput?'[INPUT]':(isCurrent?'[MÔ HÌNH CẢI TIẾN]':(isProtein?'[PROTEIN]':'[MÔ HÌNH GỐC]'));
        ctx.fillText(lbl+scoreText,256,32);
        ctx.font='bold 13px Inter';
        ctx.fillStyle=isInput?'#f9a8d4':(isCurrent?'#6ee7b7':'#fcd34d');
        ctx.fillText(typeText,256,58);
        
        const tex=new THREE.CanvasTexture(canvas);
        const spMat=new THREE.SpriteMaterial({map:tex,transparent:true,depthWrite:false});
        const sprite=new THREE.Sprite(spMat);
        sprite.position.copy(mesh.position);
        sprite.position.y+=5.5;
        sprite.scale.set(18,3.2,1);
        scene.add(sprite);
    });

    // Edges
    graph.edges.forEach(e=>{
        const from=nodePos[e.from], to=nodePos[e.to];
        if(!from||!to)return;
        
        const direction=new THREE.Vector3().subVectors(to,from);
        const length=direction.length();
        const mid=new THREE.Vector3().addVectors(from,to).multiplyScalar(0.5);
        const col=e.model==='current'?0x10b981:(e.model==='protein'?0x818cf8:0xf59e0b);
        
        const cylGeo=new THREE.CylinderGeometry(0.16,0.16,length,6);
        const cylMat=new THREE.MeshBasicMaterial({color:col,transparent:true,opacity:0.8});
        const cyl=new THREE.Mesh(cylGeo,cylMat);
        cyl.position.copy(mid);
        cyl.lookAt(to);
        cyl.rotateX(Math.PI/2);
        scene.add(cyl);

        const arrowPos=new THREE.Vector3().lerpVectors(from,to,0.72);
        const arrowGeo=new THREE.SphereGeometry(0.65,8,8);
        const arrowMat=new THREE.MeshBasicMaterial({color:col});
        const arrow=new THREE.Mesh(arrowGeo,arrowMat);
        arrow.position.copy(arrowPos);
        scene.add(arrow);
    });

    // Ambient floating particles
    const particleGeo=new THREE.BufferGeometry();
    const pCount=250;
    const positions=new Float32Array(pCount*3);
    for(let i=0;i<pCount*3;i++)positions[i]=(Math.random()-0.5)*130;
    particleGeo.setAttribute('position',new THREE.BufferAttribute(positions,3));
    const pMat=new THREE.PointsMaterial({color:0x818cf8,size:0.35,transparent:true,opacity:0.5});
    scene.add(new THREE.Points(particleGeo,pMat));

    // Lights
    scene.add(new THREE.AmbientLight(0xffffff,0.6));
    const dl=new THREE.DirectionalLight(0xffffff,0.9);
    dl.position.set(30,60,30);
    scene.add(dl);
    const pl=new THREE.PointLight(0x818cf8,0.6,120);
    pl.position.set(0,25,0);
    scene.add(pl);

    // Dynamic rotation & render
    function animate(){
        requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene,camera);
    }
    animate();

    // Click trigger details
    const raycaster=new THREE.Raycaster();
    const mouse=new THREE.Vector2();
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
