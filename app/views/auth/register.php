<?php if (session_status() === PHP_SESSION_NONE) session_start(); if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } } ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - MedLink AI</title>
    <script>document.documentElement.setAttribute('data-theme',localStorage.getItem('ml-theme')||'light')</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
    <style>
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 16px;
        }
        .input-group input {
            padding-left: 42px !important;
        }
        .brand-title {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #0f172a 0%, #4f46e5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2px;
        }
        
        body.ml-auth-body {
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 65%, #f8fafc 100%);
        }
        
        .ml-auth-container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            height: min(680px, 90vh);
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(148, 163, 184, 0.22);
            backdrop-filter: blur(24px);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.12);
            z-index: 5;
            position: relative;
        }
        .ml-auth-3d {
            width: 50%;
            height: 100%;
            position: relative;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(236, 72, 153, 0.06) 100%);
            border-right: 1px solid rgba(148, 163, 184, 0.18);
            overflow: hidden;
        }
        .ml-auth-form-wrapper {
            width: 50%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            overflow-y: auto;
        }
        .ml-auth-card-override {
            width: 100%;
            max-width: 380px;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(16px);
            padding: 40px;
            border-radius: 28px;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ml-brand p,
        .ml-footer-note {
            color: #475569;
        }
        .ml-field label {
            color: #334155;
            font-weight: 600;
        }
        .input-group input {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            color: #0f172a;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .input-group input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }
        .ml-btn.primary.full {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(99, 102, 241, 0.22);
        }
        
        @media (max-width: 900px) {
            .ml-auth-container {
                flex-direction: column;
                height: auto;
                max-width: 440px;
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(148, 163, 184, 0.22);
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.12);
            }
            .ml-auth-3d {
                position: fixed;
                inset: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
                opacity: 0.7;
                border-right: none;
            }
            .ml-auth-form-wrapper {
                width: 100%;
                padding: 10px;
                z-index: 1;
            }
            .ml-auth-card-override {
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid rgba(148, 163, 184, 0.22);
                backdrop-filter: blur(20px);
                padding: 36px;
                border-radius: 24px;
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.1);
            }
        }
    </style>
</head>
<body class="ml-auth-body">
<div class="ml-auth-container">
    <!-- Left panel with 3D DNA -->
    <div class="ml-auth-3d" id="dnaCanvas"></div>
    
    <!-- Right panel with form -->
    <div class="ml-auth-form-wrapper">
        <div class="ml-auth-card-override">
            <div class="ml-brand center" style="flex-direction: column; text-align: center; gap: 8px; margin-bottom: 24px;">
                <div class="ml-logo" style="width: 52px; height: 52px; font-size: 24px; margin: 0 auto 4px;"><i class="bi bi-capsule"></i></div>
                <div>
                    <h1 class="brand-title">MedLink AI</h1>
                    <p style="color: #94a3b8; font-size: 12.5px; font-weight: 500;">Tạo tài khoản nghiên cứu mới</p>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="ml-alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form class="ml-form" method="POST" action="index.php?action=do_register">
                <div class="ml-field">
                    <label>Tên đăng nhập (Username)</label>
                    <div class="input-group">
                        <i class="bi bi-person-fill"></i>
                        <input name="username" required placeholder="Nhập tên đăng nhập">
                    </div>
                </div>
                
                <div class="ml-field">
                    <label>Họ tên (Full name)</label>
                    <div class="input-group">
                        <i class="bi bi-card-text"></i>
                        <input name="full_name" placeholder="Nhập họ tên của bạn">
                    </div>
                </div>
                
                <div class="ml-field">
                    <label>Địa chỉ Email</label>
                    <div class="input-group">
                        <i class="bi bi-envelope-fill"></i>
                        <input type="email" name="email" placeholder="example@domain.com">
                    </div>
                </div>
                
                <div class="ml-field">
                    <label>Mật khẩu</label>
                    <div class="input-group">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" name="password" required placeholder="Tối thiểu 6 ký tự">
                    </div>
                </div>
                
                <button class="ml-btn primary full" type="submit" style="margin-top: 8px; height: 46px;">
                    Đăng ký tài khoản <i class="bi bi-person-plus-fill" style="font-size: 16px;"></i>
                </button>
            </form>
            
            <p class="ml-footer-note" style="color: #94a3b8;">Đã có tài khoản? <a href="index.php?action=login">Đăng nhập</a></p>
        </div>
    </div>
</div>

<script>
// === Three.js 3D DNA Helix Animation ===
(function(){
    const container = document.getElementById('dnaCanvas');
    if (!container) return;
    
    let width = container.clientWidth || 400;
    let height = container.clientHeight || 600;
    
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 1000);
    camera.position.z = 45;
    
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);
    
    // Group containing the DNA model
    const dnaGroup = new THREE.Group();
    
    const numPoints = 26;
    const radius = 7.5;
    const helixHeight = 28;
    const color1 = 0x6366f1; // Indigo
    const color2 = 0xec4899; // Pink
    
    const sphereGeo = new THREE.SphereGeometry(0.7, 16, 16);
    const strand1Mat = new THREE.MeshPhongMaterial({ color: color1, emissive: color1, emissiveIntensity: 0.45, shininess: 80 });
    const strand2Mat = new THREE.MeshPhongMaterial({ color: color2, emissive: color2, emissiveIntensity: 0.45, shininess: 80 });
    const bridgeMat = new THREE.MeshPhongMaterial({ color: 0x818cf8, emissive: 0x4f46e5, emissiveIntensity: 0.2, transparent: true, opacity: 0.75 });
    
    for (let i = 0; i < numPoints; i++) {
        const t = (i / numPoints) * Math.PI * 3.5; // Helix spiral amount
        const y = (i / numPoints) * helixHeight - (helixHeight / 2);
        
        // Strand 1 Position
        const x1 = Math.cos(t) * radius;
        const z1 = Math.sin(t) * radius;
        
        // Strand 2 Position (offset by PI)
        const x2 = Math.cos(t + Math.PI) * radius;
        const z2 = Math.sin(t + Math.PI) * radius;
        
        // Add Strand 1 Sphere
        const sphere1 = new THREE.Mesh(sphereGeo, strand1Mat);
        sphere1.position.set(x1, y, z1);
        dnaGroup.add(sphere1);
        
        // Add Strand 2 Sphere
        const sphere2 = new THREE.Mesh(sphereGeo, strand2Mat);
        sphere2.position.set(x2, y, z2);
        dnaGroup.add(sphere2);
        
        // Add Connecting Bridge (Cylinder)
        const bridgeLength = radius * 2;
        const bridgeGeo = new THREE.CylinderGeometry(0.12, 0.12, bridgeLength, 8);
        const bridge = new THREE.Mesh(bridgeGeo, bridgeMat);
        bridge.position.set(0, y, 0);
        bridge.lookAt(new THREE.Vector3(x1, y, z1));
        bridge.rotateX(Math.PI / 2);
        
        dnaGroup.add(bridge);
    }
    
    scene.add(dnaGroup);
    
    // Add floating ambient particles in background
    const pGeo = new THREE.BufferGeometry();
    const pCount = 80;
    const pPositions = new Float32Array(pCount * 3);
    for (let i = 0; i < pCount * 3; i++) {
        pPositions[i] = (Math.random() - 0.5) * 75;
    }
    pGeo.setAttribute('position', new THREE.BufferAttribute(pPositions, 3));
    const pMat = new THREE.PointsMaterial({ color: 0x818cf8, size: 0.35, transparent: true, opacity: 0.55 });
    const particles = new THREE.Points(pGeo, pMat);
    scene.add(particles);
    
    // Lighting
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.55);
    scene.add(ambientLight);
    
    const directLight1 = new THREE.DirectionalLight(0xffffff, 0.8);
    directLight1.position.set(10, 20, 15);
    scene.add(directLight1);
    
    const directLight2 = new THREE.DirectionalLight(0x818cf8, 0.5);
    directLight2.position.set(-10, -20, -15);
    scene.add(directLight2);
    
    // Animation Loop
    let clock = new THREE.Clock();
    function animate() {
        requestAnimationFrame(animate);
        
        const elapsedTime = clock.getElapsedTime();
        
        // Rotate DNA Helix
        dnaGroup.rotation.y = elapsedTime * 0.45;
        dnaGroup.rotation.x = Math.sin(elapsedTime * 0.2) * 0.15;
        
        // Gently sway background particles
        particles.rotation.y = -elapsedTime * 0.05;
        particles.rotation.x = Math.cos(elapsedTime * 0.05) * 0.05;
        
        renderer.render(scene, camera);
    }
    animate();
    
    // Window Resize Handler
    window.addEventListener('resize', () => {
        width = container.clientWidth;
        height = container.clientHeight;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
    });
})();
</script>
</body>
</html>
