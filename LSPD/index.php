<?php
/**
 * LSPD Department — Main Index (enhanced)
 */
require_once __DIR__ . '/api/config.php';
startSession();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LSPD — Los Santos Police Department</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/theme.css">
<style>
  .dept-hero { background: linear-gradient(180deg,#050810 0%,var(--ink2) 100%);border-bottom:1px solid var(--border);padding:60px 24px;text-align:center;position:relative;overflow:hidden; }
  .dept-hero::before { content:'';position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 38px,rgba(42,58,86,.2) 38px,rgba(42,58,86,.2) 39px); }
  .dept-hero-content { position:relative; }
  .dept-eyebrow { font-family:var(--font-head);font-size:.75rem;font-weight:600;letter-spacing:5px;text-transform:uppercase;color:var(--steel);margin-bottom:12px; }
  .dept-hero h1 { font-family:var(--font-head);font-size:clamp(2rem,6vw,3.5rem);font-weight:700;color:var(--frost);letter-spacing:4px;text-transform:uppercase;line-height:1.05; }
  .dept-hero h1 span { color:var(--steel2); }
  .dept-divider { width:80px;height:2px;background:var(--steel);margin:20px auto; }
  .dept-sub { font-size:.875rem;color:var(--muted);max-width:560px;margin:0 auto;line-height:1.6; }
  .dept-doc-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:32px; }
  .dept-doc-card { background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;transition:all .25s;display:flex;flex-direction:column; }
  .dept-doc-card:hover { border-color:var(--steel);transform:translateY(-4px); }
  .dept-doc-head { background:var(--ink2);border-bottom:2px solid var(--steel);padding:16px 20px;display:flex;align-items:center;gap:12px; }
  .dept-doc-icon { width:40px;height:40px;background:var(--steel);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--ink); }
  .dept-doc-title { font-family:var(--font-head);font-size:.95rem;font-weight:700;color:var(--frost);letter-spacing:1px;text-transform:uppercase; }
  .dept-doc-body { padding:16px 20px;flex:1; }
  .dept-doc-desc { font-size:.82rem;color:var(--muted);line-height:1.6;margin-bottom:14px; }
  .dept-doc-meta { display:flex;flex-direction:column;gap:4px;margin-bottom:14px; }
  .dept-doc-meta-item { display:flex;align-items:center;gap:8px;font-size:.75rem;color:var(--text2); }
  .dept-doc-meta-item span { font-family:var(--font-head);font-weight:700;color:var(--steel2);text-transform:uppercase;letter-spacing:1px;min-width:60px; }
  .dept-doc-btn { display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 16px;background:var(--steel);color:var(--ink);font-family:var(--font-head);font-size:.8rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;text-decoration:none;border-radius:4px;transition:background .2s;margin-top:auto; }
  .dept-doc-btn:hover { background:var(--steel2);color:var(--ink); }
  .dept-info-banner { background:var(--panel);border:1px solid var(--border);border-left:4px solid var(--gold);border-radius:var(--radius-lg);padding:24px;margin-top:32px; }
  .dept-info-title { font-family:var(--font-head);font-weight:700;font-size:.9rem;color:var(--gold2);text-transform:uppercase;letter-spacing:2px;margin-bottom:8px; }
  .dept-info-text { font-size:.82rem;color:var(--text2);line-height:1.7; }
  .dept-quick-links { display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:32px; }
  .dept-quick-link { background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;text-align:center;transition:all .25s;cursor:pointer;text-decoration:none;display:block;color:inherit; }
  .dept-quick-link:hover { border-color:var(--steel);transform:translateY(-2px); }
  .dept-quick-icon { font-size:24px;margin-bottom:8px; }
  .dept-quick-title { font-family:var(--font-head);font-size:.82rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text2); }
  .login-gate { background:var(--panel);border:1px solid var(--border);border-left:4px solid var(--gold);border-radius:var(--radius-lg);padding:32px;text-align:center;margin-top:40px; }
  .login-gate h3 { font-family:var(--font-head);font-size:1.1rem;font-weight:700;color:var(--frost);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px; }
  .login-gate p { font-size:.85rem;color:var(--muted);margin-bottom:20px; }
</style>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a href="index.php" class="header-brand">
      <img src="img/Logo.png" alt="LSPD" width="40" height="40">
      <div class="header-title">
        <h1>Los Santos Police Department</h1>
        <p>Dokumentasi Resmi — Negara Bagian San Andreas</p>
      </div>
    </a>
    <nav class="header-nav">
      <a href="index.php" class="active">Beranda</a>
      <a href="../index.php">Forum</a>
      <a href="members.php">Members</a>
    </nav>
    <div class="header-actions">
      <div id="auth-bar"></div>
    </div>
  </div>
</header>

<section class="dept-hero">
  <div class="dept-hero-content">
    <p class="dept-eyebrow">Portal Resmi</p>
    <h1>Los Santos <span>Police Department</span></h1>
    <div class="dept-divider"></div>
    <p class="dept-sub">Departemen Kepolisian Los Santos — Kumpulan dokumen regulasi, panduan departemen, dan kode penal resmi.</p>
    <div class="dept-quick-links">
      <a href="Penal_Code.html" class="dept-quick-link"><div class="dept-quick-icon">⚖️</div><div class="dept-quick-title">Penal Code</div></a>
      <a href="Officer_Manual.html" class="dept-quick-link"><div class="dept-quick-icon">📖</div><div class="dept-quick-title">Officer Manual</div></a>
      <a href="Departement_Manual.html" class="dept-quick-link"><div class="dept-quick-icon">📋</div><div class="dept-quick-title">Dept. Manual</div></a>
      <a href="../index.php" class="dept-quick-link"><div class="dept-quick-icon">💬</div><div class="dept-quick-title">Forum</div></a>
    </div>
  </div>
</section>

<div class="container" style="padding-top:48px;padding-bottom:60px;">
  <div id="Public-area">
    <p class="section-label" style="font-family:var(--font-head);font-size:.75rem;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:var(--steel);text-align:center;margin-bottom:10px;">Public</p>
    <h2 class="section-title" style="font-family:var(--font-head);font-size:1.4rem;font-weight:700;color:var(--frost);letter-spacing:3px;text-transform:uppercase;text-align:center;margin-bottom:32px;">Informasi Umum</h2>
    <div class="dept-doc-grid">
      <div class="dept-doc-card">
        <div class="dept-doc-head">
          <div class="dept-doc-icon" style="background:var(--gold);"><img src="img/Logo.png" alt="badge" style="width:24px;height:24px;object-fit:contain;"></div>
          <div class="dept-doc-title">LSPD Announcement</div>
        </div>
        <div class="dept-doc-body">
          <p class="dept-doc-desc">Pengumuman resmi dari Departemen Kepolisian Los Santos untuk seluruh personel dan masyarakat.</p>
          <a href="#" class="dept-doc-btn">Buka Dokumen →</a>
        </div>
      </div>
    </div>
  </div>

  <div id="Office-area-wrap"></div>

  <div class="dept-info-banner">
    <div class="dept-info-title">Informasi Penting</div>
    <p class="dept-info-text">Seluruh dokumen di atas adalah dokumentasi resmi dari Departemen Kepolisian Los Santos untuk roleplay di Zonix Community. Penggunaan, reproduksi, atau distribusi di luar konteks roleplay tidak diizinkan tanpa persetujuan dari pihak berwenang.</p>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:40px;">
    <div class="contact-card" style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;text-align:center;">
      <div class="contact-card-title" style="font-family:var(--font-head);font-weight:700;font-size:.9rem;color:var(--steel2);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;">Alamat</div>
      <div class="contact-card-info" style="font-size:.82rem;color:var(--muted);">Mission Row Police Station<br>Los Santos, San Andreas</div>
    </div>
    <div class="contact-card" style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;text-align:center;">
      <div class="contact-card-title" style="font-family:var(--font-head);font-weight:700;font-size:.9rem;color:var(--steel2);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;">Kontak Darurat</div>
      <div class="contact-card-info" style="font-size:.82rem;color:var(--muted);">Darurat: 911<br>LSPD Dispatch: 555</div>
    </div>
    <div class="contact-card" style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;text-align:center;">
      <div class="contact-card-title" style="font-family:var(--font-head);font-weight:700;font-size:.9rem;color:var(--steel2);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px;">Jam Operasional</div>
      <div class="contact-card-info" style="font-size:.82rem;color:var(--muted);">Senin — Jumat: 08.30 — 17.00<br>Weekend: Shifts 24/7</div>
    </div>
  </div>
</div>

<footer class="site-footer">
  <div class="footer-brand">Los Santos Police Department</div>
  <div class="footer-sub">Dokumentasi Resmi — Negara Bagian San Andreas</div>
  <div class="footer-motto">To Protect and Serve</div>
  <div class="footer-sig">Dibuat oleh <strong>Noah Anderson</strong><br><span style="color:var(--muted);font-size:.78rem;">© 2023–2026 Los Santos Police Department</span></div>
</footer>

<script src="../assets/js/core.js"></script>
<script>
(function() {
  var API_ME = '../api/me.php';
  var API_LOGIN = '../login.php';
  var API_LOGOUT = '../api/logout.php';

  function getUser() {
    return JSON.parse(sessionStorage.getItem('lspd_user') || 'null');
  }

  function renderAuthBar(user) {
    var bar = document.getElementById('auth-bar');
    if (!user) {
      bar.innerHTML = '<a href="' + API_LOGIN + '" class="btn btn-primary btn-sm">Login</a>';
    } else {
      var pill = user.role === 'admin' ? 'admin' : user.role === 'lspd' ? 'lspd' : 'user';
      var adminLink = user.role === 'admin' ? '<a href="../admin.php" class="btn btn-gold btn-sm">Admin</a>' : '';
      bar.innerHTML =
        '<div class="auth-user">Halo, <strong>' + user.username + '</strong> <span class="role-pill ' + pill + '">' + (user.role_badge || user.role) + '</span></div>' +
        adminLink +
        '<button class="btn btn-danger btn-sm" id="logoutBtn">Logout</button>';
      document.getElementById('logoutBtn').addEventListener('click', logout);
    }
  }

  function applyRoleVisibility(user) {
    var officeWrap = document.getElementById('Office-area-wrap');

    if (!user) {
      officeWrap.innerHTML =
        '<div class="login-gate">' +
          '<h3>Akses Terbatas</h3>' +
          '<p>Hanya LSPD Officer dan Admin yang bisa mengakses dokumentasi resmi.</p>' +
          '<a href="' + API_LOGIN + '" class="btn btn-primary">Login</a>' +
        '</div>';
    } else if (user.role === 'user') {
      officeWrap.innerHTML =
        '<div class="login-gate">' +
          '<h3>Akses Terbatas</h3>' +
          '<p>Kamu hanya bisa melihat informasi umum. Hubungi admin untuk akses LSPD.</p>' +
        '</div>';
    } else {
      officeWrap.innerHTML = `
        <p class="section-label" style="font-family:var(--font-head);font-size:.75rem;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:var(--steel);text-align:center;margin-bottom:10px;margin-top:32px;">Office</p>
        <h2 class="section-title" style="font-family:var(--font-head);font-size:1.4rem;font-weight:700;color:var(--frost);letter-spacing:3px;text-transform:uppercase;text-align:center;margin-bottom:32px;">Dokumentasi Resmi</h2>
        <div class="dept-doc-grid">
          <div class="dept-doc-card">
            <div class="dept-doc-head">
              <div class="dept-doc-icon" style="background:var(--gold);"><img src="img/Logo.png" alt="badge" style="width:24px;height:24px;object-fit:contain;"></div>
              <div class="dept-doc-title">San Andreas<br>Penal Code 2026</div>
            </div>
            <div class="dept-doc-body">
              <p class="dept-doc-desc">Kode penal resmi Negara Bagian San Andreas yang berisi seluruh regulasi hukum untuk penegakan hukum.</p>
              <div class="dept-doc-meta">
                <div class="dept-doc-meta-item"><span>Tahun</span> 2026</div>
                <div class="dept-doc-meta-item"><span>Sections</span> 10 Bagian</div>
                <div class="dept-doc-meta-item"><span>Bahasa</span> Indonesia</div>
              </div>
              <a href="Penal_Code.html" class="dept-doc-btn">Buka Dokumen →</a>
            </div>
          </div>
          <div class="dept-doc-card">
            <div class="dept-doc-head">
              <div class="dept-doc-icon">☰</div>
              <div class="dept-doc-title">LSPD Officer<br>Manual 2023</div>
            </div>
            <div class="dept-doc-body">
              <p class="dept-doc-desc">Buku panduan resmi Departemen Kepolisian Los Santos yang berisi kebijakan, prosedur, dan regulasi operasional.</p>
              <div class="dept-doc-meta">
                <div class="dept-doc-meta-item"><span>Tahun</span> 2023</div>
                <div class="dept-doc-meta-item"><span>Volume</span> 5 Volume</div>
                <div class="dept-doc-meta-item"><span>Bahasa</span> English</div>
              </div>
              <a href="Officer_Manual.html" class="dept-doc-btn">Buka Dokumen →</a>
            </div>
          </div>
          <div class="dept-doc-card">
            <div class="dept-doc-head">
              <div class="dept-doc-icon" style="background:var(--green);">☰</div>
              <div class="dept-doc-title">Buku Panduan<br>Departemen</div>
            </div>
            <div class="dept-doc-body">
              <p class="dept-doc-desc">Versi bahasa Indonesia dari buku panduan resmi Departemen Kepolisian Los Santos.</p>
              <div class="dept-doc-meta">
                <div class="dept-doc-meta-item"><span>Tahun</span> 2023</div>
                <div class="dept-doc-meta-item"><span>Volume</span> 5 Volume</div>
                <div class="dept-doc-meta-item"><span>Bahasa</span> Indonesia</div>
              </div>
              <a href="Departement_Manual.html" class="dept-doc-btn">Buka Dokumen →</a>
            </div>
          </div>
        </div>
      `;
    }
  }

  function logout() {
    fetch(API_LOGOUT, { method: 'POST', credentials: 'same-origin' })
      .finally(function() {
        sessionStorage.removeItem('lspd_user');
        window.location.reload();
      });
  }

  function init() {
    fetch(API_ME, { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.json() : null; })
      .then(function(data) {
        var user = data ? data.user : null;
        if (user) sessionStorage.setItem('lspd_user', JSON.stringify(user));
        renderAuthBar(user);
        applyRoleVisibility(user);
      })
      .catch(function() {
        renderAuthBar(null);
        applyRoleVisibility(null);
      });
  }

  init();
})();
</script>
</body>
</html>