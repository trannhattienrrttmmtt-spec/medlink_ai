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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MedLink AI — Dashboard</title>
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('ml-theme')||'light')</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/dist/vis-network.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/css/medlink-dashboard.css">
<style>
/* Extra dashboard-specific styles */
.theme-btn{position:fixed;top:20px;right:20px;width:42px;height:42px;border-radius:50%;background:var(--card);border:1px solid var(--line);box-shadow:var(--shadow);cursor:pointer;display:grid;place-items:center;font-size:18px;z-index:9999;transition:all .2s}
.theme-btn:hover{transform:scale(1.1) rotate(15deg)}
.ai-tabs{display:flex;gap:4px;background:var(--bg-soft);padding:4px;border-radius:12px;margin-bottom:16px}
.ai-tab{flex:1;padding:10px;border-radius:10px;font-size:13px;font-weight:600;text-align:center;cursor:pointer;border:none;background:transparent;color:var(--text-muted);transition:all .2s}
.ai-tab:hover{color:var(--text)}
.ai-tab.active{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.ai-panel{display:none}.ai-panel.active{display:block;animation:fadeUp .3s ease}
.ai-compare{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:768px){.ai-compare{grid-template-columns:1fr}}
.ai-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:8px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.ai-badge-cur{background:var(--green-light);color:var(--green)}.ai-badge-orig{background:var(--amber-light);color:var(--amber)}
.ai-pct{font-weight:700;font-size:13px;color:var(--primary)}
.ai-bar{height:5px;border-radius:3px;background:var(--line-soft);width:80px;margin-top:4px;overflow:hidden}
.ai-mol{width:100px;height:100px;border-radius:12px;border:1px solid var(--line);object-fit:contain;background:linear-gradient(135deg,#fafbff,#fff);padding:6px;cursor:zoom-in;transition:all .25s}
.ai-mol:hover{transform:scale(1.08);box-shadow:0 8px 24px rgba(99,102,241,.25);border-color:var(--primary)}
#aiGraph{height:420px;border-radius:16px;border:1px solid var(--line);background:var(--card)}
.ai-legend{display:flex;gap:14px;font-size:12px;color:var(--text-muted);margin-top:10px;flex-wrap:wrap}
.ai-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:5px;vertical-align:middle}
.smiles{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-muted);word-break:break-all;max-width:180px;display:inline-block}
.skeleton{background:linear-gradient(90deg,var(--bg-soft) 25%,var(--line-soft) 50%,var(--bg-soft) 75%);background-size:200% 100%;animation:skel 1.5s infinite;border-radius:8px}
@keyframes skel{0%{background-position:200% 0}100%{background-position:-200% 0}}
.search-box{position:relative}
.search-box::before{content:'\F52A';font-family:'bootstrap-icons';position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:14px;pointer-events:none}
.search-box select,.search-box input{padding-left:36px!important}
.section-title{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.section-title h3{font-size:18px;font-weight:700;color:var(--text)}
.section-title .icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--primary-light),rgba(236,72,153,.1));display:grid;place-items:center;color:var(--primary);font-size:18px}
.quick-action{padding:14px;border-radius:14px;background:var(--bg-soft);border:1px solid var(--line);display:flex;align-items:center;gap:12px;cursor:pointer;transition:all .2s;text-decoration:none;color:inherit}
.quick-action:hover{background:var(--primary-light);border-color:var(--primary);transform:translateX(4px)}
.quick-action .qa-icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;color:#fff;font-size:18px;flex-shrink:0}
.qa-icon.purple{background:linear-gradient(135deg,#6366f1,#8b5cf6)}
.qa-icon.green{background:linear-gradient(135deg,#10b981,#059669)}
.qa-icon.amber{background:linear-gradient(135deg,#f59e0b,#d97706)}
.qa-icon.pink{background:linear-gradient(135deg,#ec4899,#db2777)}
.qa-text{flex:1}.qa-text b{display:block;font-size:13px;color:var(--text);font-weight:600}.qa-text small{font-size:11px;color:var(--text-muted)}
.empty-state{padding:40px;text-align:center;color:var(--text-muted)}
.empty-state .ico{font-size:48px;color:var(--text-dim);margin-bottom:8px}
.history-list{display:flex;flex-direction:column;gap:8px}
.history-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;background:var(--bg-soft);border:1px solid var(--line-soft);transition:all .15s}
.history-item:hover{background:var(--primary-light);border-color:var(--primary)}
.history-item .h-icon{width:36px;height:36px;border-radius:10px;background:var(--card);display:grid;place-items:center;color:var(--primary);font-size:16px;flex-shrink:0}
.history-item .h-text{flex:1;min-width:0}
.history-item .h-text b{display:block;font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.history-item .h-text small{font-size:11px;color:var(--text-muted)}
.history-item .h-time{font-size:11px;color:var(--text-dim);white-space:nowrap}
#gSymptomsBox label:hover{background:var(--primary-light)}
#gSymptomsBox input:checked+span{color:var(--primary);font-weight:600}
.sym-tag{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:8px;background:var(--primary-light);color:var(--primary);font-size:11px;font-weight:600;border:1px solid rgba(99,102,241,.2)}
.sym-tag .sym-x{cursor:pointer;font-size:14px;opacity:.6;margin-left:2px}.sym-tag .sym-x:hover{opacity:1}
</style>
</head>
<body>

<div class="ml-app">
    <aside class="ml-sidebar">
        <div class="ml-brand">
            <div class="ml-logo"><i class="bi bi-capsule"></i></div>
            <div><h1>MedLink AI</h1><p>Drug-Disease Prediction</p></div>
        </div>
        <div class="ml-nav-title">Điều hướng</div>
        <div class="ml-nav">
        <a class="active" href="index.php?action=dashboard"><i class="bi bi-grid-1x2-fill"></i> Tổng quan</a>
        <a href="#ai-section"><i class="bi bi-search"></i> Dự đoán AI</a>
        <a href="#chart-section"><i class="bi bi-bar-chart-fill"></i> Biểu đồ</a>
        <a href="#graph-section"><i class="bi bi-diagram-3-fill"></i> Mạng liên kết</a>
        <a href="#gen-section"><i class="bi bi-magic"></i> Sinh thuốc</a>
        <a href="index.php?action=history"><i class="bi bi-clock-history"></i> Lịch sử</a>
        <?php /* Admin removed */ ?>
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
            <div style="display:flex;gap:12px;align-items:center">
                <button class="ml-mobile-menu" data-menu-btn><i class="bi bi-list"></i></button>
                <div><h2>Xin chào, <?= e(explode(' ', $fullName)[count(explode(' ', $fullName))-1]) ?> 👋</h2><p>Chào mừng quay trở lại với MedLink AI</p></div>
            </div>
            <div class="ml-user"><button id="themeToggle" style="width:36px;height:36px;border-radius:50%;background:var(--bg-soft);border:1px solid var(--line);display:grid;place-items:center;cursor:pointer;font-size:16px;transition:all .2s" title="Đổi sáng/tối"><i class="bi bi-moon-fill"></i></button><div class="ml-avatar"><?= e(strtoupper(substr($fullName,0,1))) ?></div><div><b><?= e($fullName) ?></b><br><small><?= e($role) ?></small></div></div>
        </div>

        <!-- Hero -->
        <section class="ml-hero fade-up">
            <span class="ml-badge"><i class="bi bi-stars"></i> AI-Powered Drug Discovery</span>
            <h3>Dự đoán mối liên kết thuốc - bệnh thông minh</h3>
           
            <div class="ml-actions">
                <a href="#ai-section" class="ml-btn"><i class="bi bi-play-fill"></i> Bắt đầu dự đoán</a>
                <a href="#gen-section" class="ml-btn ghost"><i class="bi bi-magic"></i> Sinh thuốc mới</a>
            </div>
        </section>

        <!-- Stats -->
        <section class="ml-grid cards fade-up">
            <div class="ml-card"><div class="ml-card-head"><div><small>THUỐC</small><div class="ml-stat-number" id="sDrugs">—</div><small style="color:var(--green)"><i class="bi bi-graph-up"></i> Trong dataset</small></div><div class="ml-stat-icon">💊</div></div></div>
            <div class="ml-card"><div class="ml-card-head"><div><small>BỆNH</small><div class="ml-stat-number" id="sDiseases">—</div><small style="color:var(--green)"><i class="bi bi-graph-up"></i> Trong dataset</small></div><div class="ml-stat-icon">🧬</div></div></div>
            <div class="ml-card"><div class="ml-card-head"><div><small>MODELS</small><div class="ml-stat-number">2</div><small style="color:var(--text-muted)">Cải tiến + Gốc</small></div><div class="ml-stat-icon">🤖</div></div></div>
            <div class="ml-card"><div class="ml-card-head"><div><small>TOP SCORE</small><div class="ml-stat-number" id="sTop">—</div><small style="color:var(--text-muted)">Độ tin cậy</small></div><div class="ml-stat-icon">⚡</div></div></div>
        </section>

        <!-- Quick Actions -->
        <section class="ml-card fade-up" style="margin-bottom:20px">
            <div class="section-title"><div class="icon"><i class="bi bi-lightning-charge-fill"></i></div><h3>Truy cập nhanh</h3></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                <a class="quick-action" href="#ai-section"><div class="qa-icon purple"><i class="bi bi-search"></i></div><div class="qa-text"><b>Dự đoán mới</b><small>Thuốc → Bệnh hoặc ngược lại</small></div></a>
                <a class="quick-action" href="#gen-section"><div class="qa-icon green"><i class="bi bi-magic"></i></div><div class="qa-text"><b>Sinh thuốc mới</b><small>AI tạo SMILES theo triệu chứng</small></div></a>
                <a class="quick-action" href="#graph-section"><div class="qa-icon pink"><i class="bi bi-diagram-3-fill"></i></div><div class="qa-text"><b>Mạng liên kết</b><small>Visualize Drug-Disease graph</small></div></a>
                <a class="quick-action" href="index.php?action=history"><div class="qa-icon amber"><i class="bi bi-clock-history"></i></div><div class="qa-text"><b>Lịch sử</b><small>Các dự đoán trước đây</small></div></a>
            </div>
        </section>

        <!-- AI Predict -->
        <section class="ml-card fade-up" id="ai-section" style="margin-bottom:20px">
            <div class="section-title"><div class="icon"><i class="bi bi-search"></i></div><h3>Dự đoán Drug-Disease</h3></div>
            <div class="ml-form">
                <div class="ml-row">
                    <div class="ml-field"><label>Dataset</label><select id="pDataset"><option>B-dataset</option><option>C-dataset</option><option>F-dataset</option></select></div>
                    <div class="ml-field"><label>Kiểu</label><select id="pType"><option value="drug">Thuốc → Bệnh</option><option value="disease">Bệnh → Thuốc</option></select></div>
                    <div class="ml-field"><label>Top K</label><input id="pTopK" type="number" min="1" max="20" value="9"></div>
                </div>
                <div class="ml-field search-box"><label>Chọn thuốc / bệnh</label><select id="pKeyword"><option value="">-- Chọn --</option></select></div>
                <button class="ml-btn primary full" id="btnPredict"><i class="bi bi-play-fill"></i> Chạy dự đoán</button>
                <div id="pStatus" style="text-align:center;font-size:12px;color:var(--text-muted)"></div>
            </div>
        </section>

        <!-- Results -->
        <section class="ml-card fade-up" id="resultCard" style="display:none;margin-bottom:20px">
            <div class="ai-tabs">
                <button class="ai-tab active" data-tab="tabCompare"><i class="bi bi-columns-gap"></i> So sánh</button>
                <button class="ai-tab" data-tab="tabChart"><i class="bi bi-bar-chart-fill"></i> Biểu đồ</button>
            </div>
            <div id="tabCompare" class="ai-panel active">
                <div class="ai-compare">
                    <div><span class="ai-badge ai-badge-cur"><i class="bi bi-circle-fill"></i> Model cải tiến</span><div id="boxCur" style="margin-top:12px"></div></div>
                    <div><span class="ai-badge ai-badge-orig"><i class="bi bi-circle-fill"></i> Model gốc AMDGT</span><div id="boxOrig" style="margin-top:12px"></div></div>
                </div>
            </div>
            <div id="tabChart" class="ai-panel" id="chart-section"><canvas id="chartCanvas" style="max-height:300px"></canvas></div>
        </section>

        <!-- Graph -->
        <section class="ml-card fade-up" id="graphCard" style="display:none;margin-bottom:20px">
            <div class="section-title"><div class="icon"><i class="bi bi-diagram-3-fill"></i></div><h3 id="graph-section">Mạng liên kết Drug-Disease</h3></div>
            <div id="aiGraph"></div>
            <div class="ai-legend">
                <span><span class="ai-dot" style="background:#ec4899"></span>Input</span>
                <span><span class="ai-dot" style="background:#10b981"></span>Model cải tiến</span>
                <span><span class="ai-dot" style="background:#f59e0b"></span>Model gốc</span>
                <span style="color:var(--text-dim)">— nét liền: cải tiến · - - nét đứt: gốc</span>
            </div>
            <div id="nodeInfo" style="display:none;margin-top:12px;padding:14px;background:var(--primary-light);border-radius:12px;font-size:13px"></div>
        </section>

        <!-- Generate -->
        <section class="ml-card fade-up" id="gen-section" style="margin-bottom:20px">
            <div class="section-title"><div class="icon"><i class="bi bi-magic"></i></div><h3>Sinh thuốc mới</h3></div>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px">Nhập tên bệnh mới và chọn các triệu chứng tương tự đã có trong dataset. AI sẽ tìm các thuốc đã biết trị triệu chứng đó và biến đổi cấu trúc để sinh ra thuốc mới có cơ sở dược lý.</p>
            <div class="ml-form">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="ml-field"><label>Tên bệnh mới</label><input id="gDisease" placeholder="VD: Long COVID, Monkeypox, novel disease..."></div>
                <div class="ml-field"><label>Dataset (sinh thuốc)</label><select id="gDataset"><option>B-dataset</option><option>C-dataset</option><option>F-dataset</option></select></div>
                </div>
                <div class="ml-field"><label>Triệu chứng tương tự (tick chọn nhiều)</label>
                <div id="gSelectedTags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:24px"></div>
                <div id="gSymptomsBox" style="max-height:180px;overflow-y:auto;border:1px solid var(--line);border-radius:11px;padding:8px;background:var(--card)"></div></div>
                <div class="ml-field"><label>Số lượng</label><input id="gN" type="number" min="1" max="30" value="10"></div>
                <button class="ml-btn primary full" id="btnGenerate"><i class="bi bi-magic"></i> Sinh thuốc</button>
            </div>
            <div id="genResult" style="margin-top:16px"></div>
        </section>

        <!-- History -->
        <section class="ml-card fade-up">
            <div class="section-title" style="justify-content:space-between"><div style="display:flex;align-items:center;gap:10px"><div class="icon"><i class="bi bi-clock-history"></i></div><h3>Hoạt động gần đây</h3></div><a class="ml-btn" href="index.php?action=history">Xem tất cả <i class="bi bi-arrow-right"></i></a></div>
            <?php if (empty($history)): ?>
                <div class="empty-state"><div class="ico"><i class="bi bi-inbox"></i></div><div>Chưa có hoạt động nào</div><small>Bắt đầu bằng cách chạy dự đoán đầu tiên</small></div>
            <?php else: ?>
                <div class="history-list">
                <?php foreach (array_slice($history, 0, 5) as $item): ?>
                    <div class="history-item">
                        <div class="h-icon"><i class="bi bi-<?= ($item['input_type'] ?? '') === 'drug' ? 'capsule' : 'activity' ?>"></i></div>
                        <div class="h-text"><b><?= e($item['keyword'] ?? '') ?></b><small><?= e($item['input_type'] ?? '') ?> · <?= e($item['dataset'] ?? 'B-dataset') ?></small></div>
                        <span class="h-time"><?= e($item['created_at'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
const API='http://127.0.0.1:5000';
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const molImg=smi=>smi?`${API}/render_smiles?smi=${encodeURIComponent(smi)}&w=250&h=250`:'';
let chartInst=null;

// Theme
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

// Tabs
document.querySelectorAll('.ai-tab').forEach(b=>b.onclick=()=>{
    document.querySelectorAll('.ai-tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.ai-panel').forEach(p=>p.classList.remove('active'));
    b.classList.add('active');$(b.dataset.tab).classList.add('active');
});

// API
async function get(p){return(await fetch(API+p)).json()}
async function post(p,d){return(await fetch(API+p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)})).json()}

// Skeleton loader
function skeleton(rows=3){return `<div style="display:flex;flex-direction:column;gap:8px">${Array(rows).fill('<div class="skeleton" style="height:48px"></div>').join('')}</div>`}

// Load options
async function loadOpts(){
    const ds=$('pDataset').value,type=$('pType').value;
    $('pKeyword').innerHTML='<option value="">Đang tải...</option>';
    const ep=type==='drug'?'/drug_options':'/disease_options';
    try{
        const data=await get(`${ep}?dataset=${encodeURIComponent(ds)}&limit=700`);
        const items=data.items||data.options||data.drugs||data.diseases||[];
        $('pKeyword').innerHTML='<option value="">-- Chọn --</option>'+items.map(i=>`<option value="${esc(i.name||i.id)}">${esc(i.name||i.id)}</option>`).join('');
        if(type==='drug'){
            $('sDrugs').textContent=items.length;
            const d2=await get(`/disease_options?dataset=${encodeURIComponent(ds)}&limit=700`);
            $('sDiseases').textContent=(d2.items||d2.options||d2.diseases||[]).length||'—';
        } else {
            $('sDiseases').textContent=items.length;
            const d2=await get(`/drug_options?dataset=${encodeURIComponent(ds)}&limit=700`);
            $('sDrugs').textContent=(d2.items||d2.options||d2.drugs||[]).length||'—';
        }
        // Load diseases theo dataset sinh thuốc — giữ tick cũ khi chuyển dataset
        const gDs=$('gDataset').value;
        const dis=await get(`/disease_options?dataset=${encodeURIComponent(gDs)}&limit=700`);
        const disList=dis.items||dis.options||dis.diseases||[];
        // Lưu các tick hiện tại
        const checkedBefore=new Set([...document.querySelectorAll('.gsym-check:checked')].map(c=>c.value));
        // Gộp danh sách mới + giữ checked cũ
        const allNames=new Set([...checkedBefore,...disList.map(d=>d.name)]);
        const sorted=[...allNames].sort();
        $('gSymptomsBox').innerHTML=sorted.map(name=>{
            const checked=checkedBefore.has(name)?'checked':'';
            const fromOther=!disList.some(d=>d.name===name);
            const badge=fromOther?` <span style="font-size:10px;color:var(--amber)">(khác)</span>`:'';
            return`<label style="display:flex;align-items:center;gap:8px;padding:4px 6px;border-radius:6px;cursor:pointer;font-size:12px;transition:background .15s"><input type="checkbox" class="gsym-check" value="${esc(name)}" ${checked} style="accent-color:var(--primary);width:16px;height:16px;cursor:pointer"><span>${esc(name)}${badge}</span></label>`;
        }).join('');
    }catch(e){console.error(e)}
}

// Render table
function renderTbl(id,pack){
    const box=$(id);
    if(!pack||!pack.ok){box.innerHTML=`<div class="ml-alert">${esc(pack?.error||'Lỗi')}</div>`;return}
    const rows=pack.results||[];
    if(!rows.length){box.innerHTML='<div class="empty-state"><div class="ico"><i class="bi bi-inbox"></i></div><div>Không có kết quả</div></div>';return}
    const allSameDrug=rows.every(r=>(r.drug_name||'')===(rows[0].drug_name||''));
    const getName=allSameDrug?(r=>r.disease_name||r.name||''):(r=>r.drug_name||r.name||'');
    const showImg=!allSameDrug;
    box.innerHTML=`<table class="ai-table"><thead><tr><th>#</th><th>Kết quả</th><th>Tin cậy</th>${showImg?'<th>Cấu trúc</th>':''}</tr></thead><tbody>${rows.map((r,i)=>{
        const name=getName(r),smi=showImg?(r.smiles||''):'',pct=Math.round((r.score||0)*100);
        return`<tr class="fade-up" style="animation-delay:${i*40}ms"><td style="font-weight:700;color:var(--primary)">${i+1}</td><td><b>${esc(name)}</b>${smi?`<div class="smiles">${esc(smi)}</div>`:''}</td><td><span class="ai-pct">${pct}%</span><div class="ai-bar"><div class="ai-bar-fill" style="width:${pct}%"></div></div></td>${showImg?`<td>${smi?`<a href="https://molview.org/?smiles=${encodeURIComponent(smi)}" target="_blank"><img class="ai-mol" src="${molImg(smi)}" onerror="this.outerHTML='<small>N/A</small>'"></a>`:'—'}</td>`:''}</tr>`}).join('')}</tbody></table>`;
}

// Chart
function renderChart(cur,orig){
    if(chartInst)chartInst.destroy();
    const rows=cur||[];
    const allSameDrug=rows.length>0&&rows.every(r=>(r.drug_name||'')===(rows[0].drug_name||''));
    const getName=allSameDrug?(r=>r.disease_name||r.name||''):(r=>r.drug_name||r.name||'');
    const isDark=document.documentElement.getAttribute('data-theme')==='dark';
    chartInst=new Chart($('chartCanvas'),{type:'bar',data:{labels:rows.slice(0,10).map((r,i)=>getName(r)||`#${i+1}`),datasets:[
        {label:'Cải tiến',data:(cur||[]).slice(0,10).map(r=>Math.round((r.score||0)*100)),backgroundColor:'rgba(99,102,241,.8)',borderRadius:8,borderWidth:0},
        {label:'Gốc',data:(orig||[]).slice(0,10).map(r=>Math.round((r.score||0)*100)),backgroundColor:'rgba(245,158,11,.7)',borderRadius:8,borderWidth:0}
    ]},options:{responsive:true,plugins:{legend:{labels:{color:isDark?'#e2e8f0':'#0f172a',font:{weight:'600',family:'Inter'}}}},scales:{x:{ticks:{color:isDark?'#94a3b8':'#64748b',maxRotation:45,font:{size:10}},grid:{display:false}},y:{ticks:{color:isDark?'#94a3b8':'#64748b',callback:v=>v+'%'},grid:{color:isDark?'#262d4a':'#f1f5f9'}}}}});
}

// Graph
function drawGraph(graph){
    const c=$('aiGraph');
    if(!graph||!graph.nodes?.length){c.innerHTML='<div class="empty-state"><div class="ico"><i class="bi bi-diagram-3"></i></div><div>Chạy dự đoán để xem mạng liên kết</div></div>';return}
    c.innerHTML='';
    const dk=document.documentElement.getAttribute('data-theme')==='dark';
    const nc={input:{background:'#ec4899'},current:{background:'#10b981'},original:{background:'#f59e0b'}};
    const nodes=new vis.DataSet(graph.nodes.map(n=>({id:n.id,label:n.label+(n.score?`\n${Math.round(n.score*100)}%`:''),shape:n.type==='drug'?'box':'ellipse',color:nc[n.model_type]||{background:'#94a3b8'},font:{color:n.model_type==='input'?'#fff':(dk?'#e2e8f0':'#0f172a'),size:11,bold:n.model_type==='input'},size:n.model_type==='input'?28:18,shadow:true,borderWidth:n.model_type==='input'?3:0})));
    const edges=new vis.DataSet(graph.edges.map((e,i)=>({from:e.from,to:e.to,arrows:'to',color:{color:e.model==='current'?'#10b981':'#f59e0b'},width:2,dashes:e.model==='original'?[6,4]:false,smooth:{type:'curvedCW',roundness:.08+i*.012}})));
    const net=new vis.Network(c,{nodes,edges},{physics:{barnesHut:{gravitationalConstant:-3000,springLength:150,damping:.3},stabilization:{iterations:120}},interaction:{hover:true}});
    net.on('click',p=>{if(p.nodes.length){const n=graph.nodes.find(x=>x.id===p.nodes[0]);if(n){$('nodeInfo').style.display='block';$('nodeInfo').innerHTML=`<b>${esc(n.label)}</b> <span class="ai-badge ${n.model_type==='current'?'ai-badge-cur':'ai-badge-orig'}">${esc(n.model_type)}</span> ${n.score?`<span style="margin-left:8px;color:var(--primary)">${Math.round(n.score*100)}%</span>`:''} ${n.smiles?`<br><code class="smiles" style="margin-top:6px;display:block">${esc(n.smiles)}</code>`:''}`}}else{$('nodeInfo').style.display='none'}});
}

// Predict
$('btnPredict').onclick=async()=>{
    const kw=$('pKeyword').value;if(!kw){$('pStatus').innerHTML='⚠️ Chọn thuốc/bệnh trước';return}
    $('pStatus').innerHTML='<span class="pulse"><i class="bi bi-hourglass-split"></i> Đang xử lý...</span>';
    $('resultCard').style.display='block';$('graphCard').style.display='block';
    $('boxCur').innerHTML=skeleton(5);$('boxOrig').innerHTML=skeleton(5);
    try{
        const data=await post('/predict_compare',{dataset:$('pDataset').value,input_type:$('pType').value,keyword:kw,top_k:+$('pTopK').value||9});
        renderTbl('boxCur',data.current);renderTbl('boxOrig',data.original);
        renderChart(data.current?.results,data.original?.results);drawGraph(data.graph);
        const top=Math.max(...(data.current?.results||[]).map(r=>r.score||0),...(data.original?.results||[]).map(r=>r.score||0));
        $('sTop').textContent=top>0?Math.round(top*100)+'%':'—';
        $('pStatus').innerHTML=data.ok?'✅ Hoàn thành':'❌ Lỗi';
        if(data.ok)fetch('index.php?action=save_history&input_type='+encodeURIComponent($('pType').value)+'&keyword='+encodeURIComponent(kw)+'&dataset='+encodeURIComponent($('pDataset').value));
    }catch(e){$('pStatus').innerHTML='❌ Lỗi kết nối';console.error(e)}
};

// Generate
$('btnGenerate').onclick=async()=>{
    const dis=$('gDisease').value.trim();
    const symptoms=[...document.querySelectorAll('.gsym-check:checked')].map(c=>c.value);
    if(!dis&&!symptoms.length){$('genResult').innerHTML='<div class="ml-alert">Nhập tên bệnh hoặc chọn triệu chứng</div>';return}
    $('genResult').innerHTML=skeleton(3);
    try{
        const data=await post('/generate_drug',{dataset:$('gDataset').value,disease:dis,symptoms,n:+$('gN').value||10});
        if(!data.ok||!(data.results||[]).length){$('genResult').innerHTML='<div class="ml-alert">Không sinh được. Thử triệu chứng khác.</div>';return}
        const rows=data.results;
        $('genResult').innerHTML=`<div class="ml-alert success"><i class="bi bi-check-circle-fill"></i> Đã sinh ${rows.length} thuốc mới cho <b>${esc(dis||'triệu chứng đã chọn')}</b>${symptoms.length?` (dựa trên: ${symptoms.slice(0,3).join(', ')}${symptoms.length>3?'...':''})`:''}</div>
        <table class="ai-table"><thead><tr><th>#</th><th>Tên gợi ý</th><th>Biến đổi từ</th><th>SMILES</th><th>Cấu trúc</th></tr></thead><tbody>${rows.map((r,i)=>{
            const smi=r.smiles||'';return`<tr class="fade-up" style="animation-delay:${i*40}ms"><td style="font-weight:700;color:var(--primary)">${i+1}</td><td><b>${esc(dis||'New')}-${i+1}</b></td><td><small style="color:var(--text-muted)"><i class="bi bi-arrow-return-left"></i> ${esc(r.base_drug||'—')}</small></td><td class="smiles">${esc(smi)}</td><td>${smi?`<a href="https://molview.org/?smiles=${encodeURIComponent(smi)}" target="_blank"><img class="ai-mol" src="${molImg(smi)}" onerror="this.outerHTML='<small>N/A</small>'"></a>`:'—'}</td></tr>`}).join('')}</tbody></table>`;
    }catch(e){$('genResult').innerHTML='<div class="ml-alert">Lỗi kết nối</div>'}
};

// Mobile menu
document.querySelector('[data-menu-btn]')?.addEventListener('click',()=>document.querySelector('.ml-sidebar').classList.toggle('open'));

// Symptom tags display
function updateSymTags(){
    const checked=[...document.querySelectorAll('.gsym-check:checked')].map(c=>c.value);
    $('gSelectedTags').innerHTML=checked.length?checked.map(name=>`<span class="sym-tag">${esc(name)}<span class="sym-x" data-sym="${esc(name)}">&times;</span></span>`).join(''):'<span style="font-size:11px;color:var(--text-dim)">Chưa chọn triệu chứng nào</span>';
    // Bind remove click
    document.querySelectorAll('.sym-x').forEach(x=>x.onclick=()=>{
        const val=x.dataset.sym;
        const cb=document.querySelector(`.gsym-check[value="${val}"]`);
        if(cb){cb.checked=false}
        updateSymTags();
    });
}
document.addEventListener('change',e=>{if(e.target.classList.contains('gsym-check'))updateSymTags()});


// Events
$('pDataset').onchange=loadOpts;$('pType').onchange=loadOpts;
$('gDataset').onchange=loadOpts;
loadOpts();
</script>
</body>
</html>
