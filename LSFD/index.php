<?php
/**
 * LSFD — Los Santos Fire Department
 */
require_once __DIR__ . '/../api/config.php';
startSession();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LSFD — Los Santos Fire Department</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/theme.css">
<style>
  .dept-hero { background: linear-gradient(180deg,#1a0505 0%,#2d0a0a 100%);border-bottom:1px solid #991B1B;padding:60px 24px;text-align:center;position:relative;overflow:hidden; }
  .dept-hero::before { content:'';position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 38px,rgba(153,27,27,.15) 38px,rgba(153,27,27,.15) 39px); }
  .dept-hero-content { position:relative; }
  .dept-eyebrow { font-family:var(--font-head);font-size:.75rem;font-weight:600;letter-spacing:5px;text-transform:uppercase;color:#EF4444;margin-bottom:12px; }
  .dept-hero h1 { font-family:var(--font-head);font-size:clamp(2rem,6vw,3.5rem);font-weight:700;color:#FEE2E2;letter-spacing:4px;text-transform:uppercase;line-height:1.05; }
  .dept-hero h1 span { color:#FCA5A5; }
  .dept-divider { width:80px;height:2px;background:#991B1B;margin:20px auto; }
  .dept-sub { font-size:.875rem;color:#A78B8B;max-width:560px;margin:0 auto;line-height:1.6; }
  .dept-doc-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:32px; }
  .dept-doc-card { background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;transition:all .25s;display:flex;flex-direction:column; }
  .dept-doc-card:hover { border-color:#991B1B;transform:translateY(-4px); }
  .dept-doc-head { background:var(--ink2);border-bottom:2px solid #991B1B;padding:16px 20px;display:flex;align-items:center;gap:12px; }
  .dept-doc-icon { width:40px;height:40px;background:#991B1B;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff; }
  .dept-doc-title { font-family:var(--font-head);font-size:.95rem;font-weight:700;color:#FEE2E2;letter-spacing:1px;text-transform:uppercase; }
  .dept-doc-body { padding:16px 20px;flex:1; }
  .dept-doc-desc { font-size:.82rem;color:var(--muted);line-height:1.6;margin-bottom:14px; }
  .dept-doc-btn { display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 16px;background:#991B1B;color:#fff;font-family:var(--font-head);font-size:.8rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;text-decoration:none;border-radius:4px;transition:background .2s;margin-top:auto; }
  .dept-doc-btn:hover { background:#B91C1C;color:#fff; }
  .dept-quick-links { display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:32px; }
  .dept-quick-link { background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;text-align:center;transition:all .25s;cursor:pointer;text-decoration:none;display:block;color:inherit; }
  .dept-quick-link:hover { border-color:#991B1B;transform:translateY(-2px); }
  .dept-quick-icon { font-size:24px;margin-bottom:8px; }
  .dept-quick-title { font-family:var(--font-head);font-size:.82rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text2); }
</style>
</head>
<body>

<header class="site-header" style="border-bottom-color:#991B1B;">
  <div class="header-inner">
    <a href="index.php" class="header-brand">
      <img src="img/Logo.png" alt="LSFD" width="40" height="40">
      <div class="header-title">
        <h1>Los Santos Fire Department</h1>
        <p>Official Department Portal</p>
      </div>
    </a>
    <nav class="header-nav">
      <a href="index.php" class="active">Beranda</a>
      <a href="../index.php">Forum</a>
    </nav>
    <div class="header-actions">
      <div id="auth-bar"></div>
    </div>
  </div>
</header>

<section class="dept-hero">
  <div class="dept-hero-content">
    <p class="dept-eyebrow">Portal Resmi</p>
    <h1>Los Santos <span>Fire Department</span></h1>
    <div class="dept-divider"></div>
    <p class="dept-sub">Departemen Pemadam Kebakaran Los Santos — Serving the community with courage and dedication.</p>
    <div class="dept-quick-links">
      <a href="../category.php?id=14" class="dept-quick-link"><div class="dept-quick-icon">📢</div><div class="dept-quick-title">News</div></a>
      <a href="../category.php?id=15" class="dept-quick-link"><div class="dept-quick-icon">📋</div><div class="dept-quick-title">Recruitment</div></a>
      <a href="../category.php?id=16" class="dept-quick-link"><div class="dept-quick-icon">🎓</div><div class="dept-quick-title">Training</div></a>
      <a href="../index.php" class="dept-quick-link"><div class="dept-quick-icon">💬</div><div class="dept-quick-title">Forum</div></a>
    </div>
  </div>
</section>

<div class="container" style="padding-top:48px;padding-bottom:60px;">
  <p class="section-label" style="font-family:var(--font-head);font-size:.75rem;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#EF4444;text-align:center;margin-bottom:10px;">Department</p>
  <h2 class="section-title" style="font-family:var(--font-head);font-size:1.4rem;font-weight:700;color:#FEE2E2;letter-spacing:3px;text-transform:uppercase;text-align:center;margin-bottom:32px;">Dokumentasi & Forum</h2>

  <div class="dept-doc-grid">
    <div class="dept-doc-card">
      <div class="dept-doc-head">
        <div class="dept-doc-icon">📢</div>
        <div class="dept-doc-title">Fire Department News</div>
      </div>
      <div class="dept-doc-body">
        <p class="dept-doc-desc">Pengumuman dan berita terbaru dari Departemen Pemadam Kebakaran Los Santos.</p>
        <a href="../category.php?id=14" class="dept-doc-btn">Buka Forum →</a>
      </div>
    </div>
    <div class="dept-doc-card">
      <div class="dept-doc-head">
        <div class="dept-doc-icon">📋</div>
        <div class="dept-doc-title">Recruitment</div>
      </div>
      <div class="dept-doc-body">
        <p class="dept-doc-desc">Informasi rekrutmen dan persyaratan untuk bergabung dengan LSFD.</p>
        <a href="../category.php?id=15" class="dept-doc-btn">Buka Forum →</a>
      </div>
    </div>
    <div class="dept-doc-card">
      <div class="dept-doc-head">
        <div class="dept-doc-icon">🎓</div>
        <div class="dept-doc-title">Training</div>
      </div>
      <div class="dept-doc-body">
        <p class="dept-doc-desc">Program pelatihan dan latihan untuk seluruh personel LSFD.</p>
        <a href="../category.php?id=16" class="dept-doc-btn">Buka Forum →</a>
      </div>
    </div>
  </div>
</div>

<footer class="site-footer" style="border-top-color:#991B1B;">
  <div class="footer-brand" style="color:#EF4444;">Los Santos Fire Department</div>
  <div class="footer-sub">Official Department Portal — Negara Bagian San Andreas</div>
  <div class="footer-motto" style="color:#EF4444;">Bravery, Sacrifice, Service</div>
  <div class="footer-sig">© 2026 Los Santos Fire Department</div>
</footer>

<script src="../assets/js/core.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  LSPD.renderAuthBar('auth-bar');
});
</script>
</body>
</html>