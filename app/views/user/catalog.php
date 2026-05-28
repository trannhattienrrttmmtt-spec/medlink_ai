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
        .catalog-toolbar input:focus, .catalog-toolbar select:focus {
            border-color: rgba(var(--primary-rgb), .45);
            box-shadow: 0 0 0 4px var(--primary-light);
        }
        .catalog-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; padding: 18px;
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
            <select id="datasetSelect">
                <option <?= $dataset === 'B-dataset' ? 'selected' : '' ?>>B-dataset</option>
                <option <?= $dataset === 'C-dataset' ? 'selected' : '' ?>>C-dataset</option>
                <option <?= $dataset === 'F-dataset' ? 'selected' : '' ?>>F-dataset</option>
            </select>
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

function esc(v){
    return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
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
                    <code class="catalog-smiles">${esc(item.smiles)}</code>
                    <a class="catalog-mol-link" href="https://molview.org/?smiles=${encodeURIComponent(item.smiles)}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right"></i> Xem cấu trúc 3D
                    </a>
                ` : ''}
            </div>
        </article>
    `).join('');
}

function syncUrl(){
    const url = new URL(window.location.href);
    url.searchParams.set('action', 'catalog');
    url.searchParams.set('type', document.getElementById('typeSelect').value);
    url.searchParams.set('dataset', document.getElementById('datasetSelect').value);
    window.history.replaceState(null, '', url.toString());
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

loadCatalog().catch(showError);
</script>
</body>
</html>
