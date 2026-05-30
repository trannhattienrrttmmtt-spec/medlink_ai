<?php
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$labels = [
    'drug' => ['title' => 'Danh sách thuốc', 'icon' => 'bi-capsule-pill', 'endpoint' => 'drug_options', 'empty' => 'Không có thuốc phù hợp'],
    'disease' => ['title' => 'Danh sách bệnh', 'icon' => 'bi-activity', 'endpoint' => 'disease_options', 'empty' => 'Không có bệnh phù hợp'],
    'protein' => ['title' => 'Danh sách protein', 'icon' => 'bi-diagram-3-fill', 'endpoint' => 'protein_options', 'empty' => 'Không có protein phù hợp'],
];
$meta = $labels[$type] ?? $labels['drug'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedLink AI - <?= e($meta['title']) ?></title>
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('ml-theme') || 'light');
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
    <style>
        .catalog-page {
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
        }
        .catalog-page::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 18% 8%, rgba(var(--primary-rgb), .12), transparent 28%),
                radial-gradient(circle at 86% 10%, rgba(236, 72, 153, .10), transparent 24%);
            z-index: -1;
        }
        .catalog-shell { max-width: 1220px; margin: 0 auto; padding: 32px 20px 48px; }
        .catalog-head {
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .catalog-title { display: flex; align-items: center; gap: 14px; }
        .catalog-title .icon {
            width: 54px; height: 54px; border-radius: 18px; display: grid; place-items: center;
            background: linear-gradient(135deg, var(--primary), var(--pink));
            color: #fff; font-size: 24px;
            box-shadow: 0 8px 24px rgba(var(--primary-rgb), .35);
        }
        .catalog-title h1 { margin: 0; font-size: 29px; font-weight: 900; letter-spacing: 0; }
        .catalog-title p { margin: 4px 0 0; color: var(--text-muted); font-weight: 800; }
        .catalog-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .ml-btn.catalog-back {
            background: linear-gradient(135deg, var(--primary), var(--pink));
            border: 0;
            color: #fff;
            box-shadow: 0 10px 26px rgba(var(--primary-rgb), .28);
            opacity: 1;
        }
        .ml-btn.catalog-back:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--pink));
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(var(--primary-rgb), .36);
        }
        .catalog-card {
            overflow: hidden;
            padding: 0;
        }
        .catalog-toolbar {
            display: grid; grid-template-columns: 1fr 180px 150px; gap: 12px;
            padding: 18px; border-bottom: 1px solid var(--line);
            background: linear-gradient(135deg, var(--bg-soft), var(--primary-light));
        }
        .catalog-toolbar input, .catalog-toolbar select {
            width: 100%; border: 1px solid var(--line); background: var(--card); color: var(--text);
            border-radius: 14px; padding: 13px 14px; font-weight: 800; outline: none;
            box-shadow: var(--shadow-sm);
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        .catalog-dataset-pill {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border: 1px solid var(--line); border-radius: 14px;
            background: var(--card); color: var(--primary); padding: 13px 14px;
            font-weight: 900; box-shadow: var(--shadow-sm); white-space: nowrap;
        }
        .catalog-toolbar input:focus, .catalog-toolbar select:focus {
            border-color: rgba(var(--primary-rgb), .45);
            box-shadow: 0 0 0 4px var(--primary-light);
        }
        .catalog-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 12px; padding: 18px;
        }
        .catalog-item {
            min-height: 92px; padding: 14px; border: 1px solid var(--line); border-radius: 16px;
            background: linear-gradient(180deg, var(--primary-light), transparent), var(--card);
            display: flex; gap: 12px; align-items: flex-start; box-shadow: var(--shadow-sm);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .catalog-item:hover {
            transform: translateY(-3px);
            border-color: rgba(var(--primary-rgb), .32);
            box-shadow: var(--shadow);
        }
        .catalog-item .badge {
            width: 38px; height: 38px; border-radius: 13px; display: grid; place-items: center;
            background: var(--primary-light); color: var(--primary); font-weight: 900; flex-shrink: 0;
        }
        .catalog-drug .catalog-item .badge { background: rgba(16,185,129,.14); color: var(--green); }
        .catalog-disease .catalog-item .badge { background: rgba(var(--primary-rgb), .14); color: var(--primary); }
        .catalog-protein .catalog-item .badge { background: rgba(236,72,153,.14); color: var(--pink); }
        .catalog-item b { display: block; font-size: 14px; line-height: 1.35; word-break: break-word; }
        .catalog-item small { display: block; color: var(--text-muted); margin-top: 6px; font-weight: 800; }
        .catalog-smiles {
            display: block; margin-top: 9px; padding: 8px 10px; border-radius: 10px;
            background: var(--bg-soft); border: 1px solid var(--line); color: var(--text);
            font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px; font-weight: 700; line-height: 1.45;
            word-break: break-all;
        }
        .catalog-mol-link {
            display: inline-flex; align-items: center; gap: 6px; margin-top: 8px;
            color: var(--primary); font-size: 12px; font-weight: 900;
        }
        .catalog-mol-link:hover { color: var(--pink); transform: translateX(2px); }
        .catalog-mol-img {
            width: 100%; max-width: 210px; height: 150px; object-fit: contain;
            display: block; margin-top: 10px; border-radius: 14px;
            background: #fff; border: 1px solid var(--line); box-shadow: var(--shadow-sm);
        }
        .protein-preview {
            margin-top: 10px; border-radius: 14px; border: 1px solid rgba(148,163,184,.28);
            background: linear-gradient(180deg, #f8fafc, #eef6ff);
            padding: 10px; overflow: hidden;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.72), var(--shadow-sm);
        }
        .protein-ribbon {
            width: 100%; height: 190px; display: block;
            border-radius: 12px;
            background: #f8fafc;
        }
        .protein-sequence {
            display: block; margin-top: 8px; padding: 8px 10px; border-radius: 10px;
            background: var(--bg-soft); border: 1px solid var(--line);
            font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 10.5px; font-weight: 800; line-height: 1.45; color: var(--text-muted);
            word-break: break-all; max-height: 58px; overflow: hidden;
        }
        .catalog-state { padding: 38px; text-align: center; color: var(--text-muted); font-weight: 900; }
        .catalog-error {
            margin: 18px; padding: 18px; border-radius: 16px;
            border: 1px solid rgba(239,68,68,.28); background: rgba(239,68,68,.08); color: var(--red);
            font-weight: 900;
        }
        @media (max-width: 720px) {
            .catalog-toolbar { grid-template-columns: 1fr; }
            .catalog-title h1 { font-size: 23px; }
        }
    </style>
</head>
<body class="catalog-page catalog-<?= e($type) ?>">
<main class="catalog-shell">
    <div class="catalog-head">
        <div class="catalog-title">
            <div class="icon"><i class="bi <?= e($meta['icon']) ?>"></i></div>
            <div>
                <h1><?= e($meta['title']) ?></h1>
                <p><span id="countText">Đang tải...</span> · <span id="datasetText"><?= e($dataset) ?></span></p>
            </div>
        </div>
        <div class="catalog-actions">
            <a class="ml-btn ghost catalog-back" href="index.php?action=dashboard"><i class="bi bi-arrow-left"></i> Dashboard</a>
        </div>
    </div>

    <section class="ml-card catalog-card">
        <div class="catalog-toolbar">
            <input id="searchInput" placeholder="Tìm theo tên hoặc mã...">
            <input type="hidden" id="datasetSelect" value="<?= e($dataset) ?>">
            <span class="catalog-dataset-pill"><i class="bi bi-database-fill"></i> <span id="catalogDatasetPill"><?= e($dataset) ?></span></span>
            <select id="typeSelect">
                <option value="drug" <?= $type === 'drug' ? 'selected' : '' ?>>Thuốc</option>
                <option value="disease" <?= $type === 'disease' ? 'selected' : '' ?>>Bệnh</option>
                <option value="protein" <?= $type === 'protein' ? 'selected' : '' ?>>Protein</option>
            </select>
        </div>
        <div id="catalogBody" class="catalog-state">Đang tải dữ liệu...</div>
    </section>
</main>

<script>
const API_BASE = 'http://127.0.0.1:5000';
const typeMeta = {
    drug: { title: 'Danh sách thuốc', endpoint: 'drug_options', empty: 'Không có thuốc phù hợp', icon: 'D' },
    disease: { title: 'Danh sách bệnh', endpoint: 'disease_options', empty: 'Không có bệnh phù hợp', icon: 'B' },
    protein: { title: 'Danh sách protein', endpoint: 'protein_options', empty: 'Không có protein phù hợp', icon: 'P' }
};
let allItems = [];

const savedDataset = localStorage.getItem('ml-dataset');
const hasDatasetParam = new URLSearchParams(window.location.search).has('dataset');
if(!hasDatasetParam && ['B-dataset','C-dataset','F-dataset'].includes(savedDataset)){
    document.getElementById('datasetSelect').value = savedDataset;
}

function esc(v){
    return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function molImg(smiles){
    return `${API_BASE}/render_smiles?smi=${encodeURIComponent(smiles)}`;
}

function proteinRibbonSvg(sequence){
    const seq = String(sequence || '');
    let seed = 0;
    for(let i=0;i<seq.length;i++)seed = (seed + seq.charCodeAt(i) * (i + 3)) % 9973;
    const points = [];
    for(let i=0;i<9;i++){
        const x = 12 + i * 34;
        const y = 46 + Math.sin((i + seed % 7) * 1.05) * 24;
        points.push(`${x.toFixed(1)},${y.toFixed(1)}`);
    }
    const circles = points.map((p,i)=>{
        const [x,y] = p.split(',');
        const color = i % 3 === 0 ? '#ec4899' : (i % 3 === 1 ? '#6366f1' : '#10b981');
        return `<circle cx="${x}" cy="${y}" r="${i % 2 ? 5 : 7}" fill="${color}" opacity=".9"/>`;
    }).join('');
    return `<svg class="protein-ribbon" viewBox="0 0 300 92" preserveAspectRatio="none" aria-label="Protein structure preview">
        <path d="M${points.join(' L')}" fill="none" stroke="#ec4899" stroke-width="7" stroke-linecap="round" opacity=".28"/>
        <path d="M${points.join(' L')}" fill="none" stroke="#6366f1" stroke-width="3" stroke-linecap="round"/>
        ${circles}
    </svg>`;
}

function oldProteinViewerId(proteinId){
    return 'catalogProtein3d_' + String(proteinId||'protein').replace(/[^a-zA-Z0-9_-]/g,'_') + '_' + Math.random().toString(36).slice(2,7);
}

function oldProtein3DControls(proteinId){
    if(!proteinId || /^protein_/i.test(proteinId))return '';
    const viewerId=oldProteinViewerId(proteinId);
    return `<button class="catalog-3d-btn" type="button" data-load-protein3d="${esc(proteinId)}" data-viewer-id="${esc(viewerId)}">
        <i class="bi bi-badge-3d-fill"></i> Xem 3D trong MedLink
    </button>
    <div class="catalog-protein-viewer" id="${esc(viewerId)}">
        <div class="catalog-protein-placeholder">Bấm nút 3D để tải cấu trúc AlphaFold/PDB.</div>
    </div>`;
}

async function oldLoadProtein3D(proteinId, viewerId){
    const box=document.getElementById(viewerId);
    if(!box)return;
    if(!window.$3Dmol){
        box.innerHTML='<div class="catalog-protein-placeholder">Thư viện 3Dmol chưa tải được. Refresh trang rồi thử lại.</div>';
        return;
    }
    box.innerHTML='<div class="catalog-protein-placeholder">Đang tải cấu trúc 3D từ AlphaFold...</div>';
    try{
        const url=`${API_BASE}/protein_structure_pdb?protein_id=${encodeURIComponent(proteinId)}`;
        const res=await fetch(url);
        if(!res.ok){
            let msg='AI API không tải được file PDB';
            try{const err=await res.json();msg=err.error||msg;}catch(_){}
            throw new Error(msg);
        }
        const pdb=await res.text();
        box.innerHTML='';
        const viewer=$3Dmol.createViewer(box,{backgroundColor:'#0f172a'});
        viewer.addModel(pdb,'pdb');
        viewer.setStyle({}, {cartoon:{color:'spectrum'}});
        viewer.addSurface($3Dmol.SurfaceType.VDW,{opacity:0.14,color:'#94a3b8'});
        viewer.zoomTo();
        viewer.render();
        viewer.zoom(1.08,700);
    }catch(e){
        box.innerHTML=`<div class="catalog-protein-placeholder">Không tải được cấu trúc 3D cho protein ${esc(proteinId)}.</div>`;
        console.error(e);
    }
}

function proteinRibbonSvg(sequence){
    const seq = String(sequence || '');
    let seed = 0;
    for(let i=0;i<seq.length;i++)seed = (seed + seq.charCodeAt(i) * (i + 3)) % 9973;
    const palette=[
        ['#2563eb','#0891b2','#0f766e','#7c3aed','#d97706'],
        ['#1d4ed8','#0e7490','#047857','#9333ea','#ca8a04'],
        ['#0369a1','#0f766e','#4f46e5','#be123c','#b45309'],
        ['#334155','#2563eb','#059669','#7c3aed','#c2410c']
    ][seed%4];
    const gradId=`catalogProteinClinicalGrad_${seed}`;
    const shadowId=`catalogProteinClinicalShadow_${seed}`;
    const phase=(seed%23)*0.17;
    const helix=[];
    const helixBack=[];
    for(let i=0;i<34;i++){
        const x=26+i*8.1;
        const base=60+Math.sin(i*.19+phase)*7;
        helix.push(`${x.toFixed(1)},${(base+Math.sin(i*.9+phase)*15).toFixed(1)}`);
        helixBack.push(`${x.toFixed(1)},${(base+Math.cos(i*.9+phase)*9).toFixed(1)}`);
    }
    const lane=105+(seed%18);
    const laneEnd=76+(seed%30);
    const sheetA=[46+(seed%18),136-(seed%10),92+(seed%22),121-(seed%8),84+(seed%15),154-(seed%6)];
    const sheetB=[125+(seed%20),42+(seed%13),172+(seed%18),34+(seed%8),158+(seed%18),66+(seed%9)];
    const sheetC=[218-(seed%18),50+(seed%15),282-(seed%14),63+(seed%10),235-(seed%10),85+(seed%12)];
    const tickX=262+(seed%22);
    return `<svg class="protein-ribbon" viewBox="0 0 320 180" preserveAspectRatio="none" aria-label="Protein secondary structure preview">
        <defs>
            <linearGradient id="${gradId}" x1="0" x2="1">
                <stop offset="0%" stop-color="${palette[0]}"/>
                <stop offset="48%" stop-color="${palette[1]}"/>
                <stop offset="100%" stop-color="${palette[3]}"/>
            </linearGradient>
            <filter id="${shadowId}" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="8" stdDeviation="6" flood-color="#64748b" flood-opacity=".20"/>
            </filter>
            <pattern id="catalogProteinGrid_${seed}" width="22" height="22" patternUnits="userSpaceOnUse">
                <path d="M22 0H0V22" fill="none" stroke="#dbeafe" stroke-width="1" opacity=".55"/>
            </pattern>
        </defs>
        <rect x="0" y="0" width="320" height="180" rx="18" fill="#f8fafc"/>
        <rect x="0" y="0" width="320" height="180" rx="18" fill="url(#catalogProteinGrid_${seed})"/>
        <circle cx="${72+(seed%36)}" cy="${45+(seed%18)}" r="${40+(seed%16)}" fill="#dbeafe" opacity=".55"/>
        <circle cx="${235+(seed%28)}" cy="${118+(seed%18)}" r="${54+(seed%14)}" fill="#ccfbf1" opacity=".40"/>
        <path d="M28 ${lane+9} C76 ${lane-30} 110 ${lane-26} 145 ${lane+2} S218 ${lane+42} 292 ${laneEnd+8}" fill="none" stroke="#cbd5e1" stroke-width="20" stroke-linecap="round" opacity=".72"/>
        <path d="M28 ${lane} C76 ${lane-40} 110 ${lane-36} 145 ${lane-8} S218 ${lane+32} 292 ${laneEnd}" fill="none" stroke="url(#${gradId})" stroke-width="10" stroke-linecap="round" filter="url(#${shadowId})"/>
        <polyline points="${helix.join(' ')}" fill="none" stroke="${palette[0]}" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
        <polyline points="${helixBack.join(' ')}" fill="none" stroke="${palette[1]}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity=".68"/>
        <g opacity=".95" filter="url(#${shadowId})">
            <polygon points="${sheetA.join(' ')}" fill="${palette[2]}"/>
            <polygon points="${sheetB.join(' ')}" fill="${palette[4]}"/>
            <polygon points="${sheetC.join(' ')}" fill="${palette[1]}"/>
        </g>
        <g fill="#ffffff" stroke="#64748b" stroke-width="1.3">
            <circle cx="28" cy="${lane}" r="4.6"/><circle cx="145" cy="${lane-8}" r="4.6"/><circle cx="292" cy="${laneEnd}" r="4.6"/>
        </g>
        <g stroke="#94a3b8" stroke-width="1.2" opacity=".75">
            <path d="M${tickX} 24v22M${tickX-11} 35h22"/>
            <path d="M20 24h42M20 31h26"/>
        </g>
        <text x="18" y="160" fill="#475569" font-size="11" font-family="Plus Jakarta Sans, Arial" font-weight="800">SECONDARY STRUCTURE PREVIEW</text>
        <text x="18" y="173" fill="#64748b" font-size="9" font-family="Plus Jakarta Sans, Arial" font-weight="700">Alpha helix / beta sheet / fold motif #${seed%1000}</text>
    </svg>`;
}

function protein3DControls(proteinId){
    if(!proteinId || /^protein_/i.test(proteinId))return '';
    return `<a class="catalog-mol-link" href="https://alphafold.ebi.ac.uk/entry/${encodeURIComponent(proteinId)}" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right"></i> Protein 3D AlphaFold
    </a>`;
}

function normalizeItems(data){
    return data.items || data.options || data.drugs || data.diseases || data.proteins || [];
}

async function loadCatalog(){
    const type = document.getElementById('typeSelect').value;
    const dataset = document.getElementById('datasetSelect').value;
    const meta = typeMeta[type] || typeMeta.drug;
    const body = document.getElementById('catalogBody');
    body.className = 'catalog-state';
    body.textContent = 'Đang tải dữ liệu...';
    document.querySelector('.catalog-title h1').textContent = meta.title;
    document.getElementById('datasetText').textContent = dataset;

    const res = await fetch(`${API_BASE}/${meta.endpoint}?dataset=${encodeURIComponent(dataset)}&limit=10000`);
    if (!res.ok) {
        throw new Error(`AI API trả lỗi ${res.status}`);
    }
    const data = await res.json();
    if (data.ok === false) {
        throw new Error(data.error || 'AI API trả dữ liệu không hợp lệ');
    }
    allItems = normalizeItems(data);
    renderCatalog();
}

function renderCatalog(){
    const type = document.getElementById('typeSelect').value;
    const meta = typeMeta[type] || typeMeta.drug;
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    const rows = allItems.filter(item => {
        const text = `${item.name || item.label || item.value || ''} ${item.id || ''}`.toLowerCase();
        return !q || text.includes(q);
    });
    document.getElementById('countText').textContent = `${rows.length} / ${allItems.length} mục`;
    const body = document.getElementById('catalogBody');
    if(!rows.length){
        body.className = 'catalog-state';
        body.textContent = meta.empty;
        return;
    }
    body.className = 'catalog-grid';
    body.innerHTML = rows.map((item, idx) => `
        <article class="catalog-item">
            <div class="badge">${meta.icon}</div>
            <div>
                <b>${esc(item.name || item.label || item.value || item.id)}</b>
                <small>#${idx + 1}${item.id ? ' · ' + esc(item.id) : ''}</small>
                ${type === 'drug' && item.smiles ? `
                    <img class="catalog-mol-img" src="${molImg(item.smiles)}" alt="Molecular structure" loading="lazy">
                    <code class="catalog-smiles">${esc(item.smiles)}</code>
                    <a class="catalog-mol-link" href="https://molview.org/?smiles=${encodeURIComponent(item.smiles)}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right"></i> Xem cấu trúc 3D
                    </a>
                ` : ''}
                ${type === 'protein' ? `
                    <div class="protein-preview">
                        ${proteinRibbonSvg(item.sequence || item.name || item.id)}
                        <small>Sequence length: ${Number(item.sequence_length || String(item.sequence || '').length || 0).toLocaleString('en-US')} AA${item.node_id ? ' - Node ' + esc(item.node_id) : ''}</small>
                    </div>
                    ${item.sequence ? `<code class="protein-sequence">${esc(item.sequence)}</code>` : ''}
                    ${protein3DControls(item.id)}
                ` : ''}
            </div>
        </article>
    `).join('');
}

function syncUrl(){
    const dataset = document.getElementById('datasetSelect').value;
    localStorage.setItem('ml-dataset', dataset);
    const pill = document.getElementById('catalogDatasetPill');
    if(pill)pill.textContent = dataset;
    const url = new URL(window.location.href);
    url.searchParams.set('action', 'catalog');
    url.searchParams.set('type', document.getElementById('typeSelect').value);
    url.searchParams.set('dataset', dataset);
    window.history.replaceState(null, '', url.toString());
    const back = document.querySelector('.catalog-back');
    if(back)back.href = `index.php?action=dashboard&dataset=${encodeURIComponent(dataset)}`;
}

function showError(err){
    const body = document.getElementById('catalogBody');
    body.className = 'catalog-error';
    body.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${esc(err.message || err)}. Nếu vừa cập nhật code, hãy chạy lại START.bat để AI API nhận route mới.`;
}

document.getElementById('searchInput').addEventListener('input', renderCatalog);
document.getElementById('datasetSelect').addEventListener('change', () => {
    syncUrl();
    loadCatalog().catch(showError);
});
document.getElementById('typeSelect').addEventListener('change', () => {
    syncUrl();
    loadCatalog().catch(showError);
});

syncUrl();
loadCatalog().catch(showError);
</script>
</body>
</html>
