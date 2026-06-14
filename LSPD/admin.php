<?php
session_start();
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
$currentUser = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — LSPD</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ink: #0a0f1a; --ink2: #111827; --ink3: #1a2438;
    --panel: #1e2d45; --border: #2a3a56;
    --steel: #4a7fc1; --ice: #8ab4f8;
    --text: #d6e4f0; --muted: #5a6e8a;
    --white: #eef4ff; --red: #f87171; --green: #4ade80; --gold: #f59e0b;
  }

  body { background: var(--ink); color: var(--text); font-family: 'Inter', sans-serif; font-size: 15px; min-height: 100vh; }

  .site-header {
    background: linear-gradient(135deg, #06090f 0%, var(--ink2) 60%, #0e1829 100%);
    border-bottom: 2px solid var(--steel);
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 4px 24px rgba(0,0,0,.6);
  }

  .header-inner {
    max-width: 1100px; margin: 0 auto;
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 24px;
  }

  .header-left { display: flex; align-items: center; gap: 16px; }

  .header-title h1 {
    font-family: 'Rajdhani', sans-serif;
    font-size: 1.3rem; font-weight: 700; color: var(--white);
    letter-spacing: 2px; text-transform: uppercase;
  }

  .header-title p { font-size: 0.72rem; color: var(--ice); letter-spacing: 3px; text-transform: uppercase; margin-top: 2px; }

  .header-right { display: flex; align-items: center; gap: 16px; }

  .user-info { display: flex; align-items: center; gap: 10px; font-size: 0.82rem; color: var(--muted); }
  .user-info strong { color: var(--ice); font-family: 'Rajdhani', sans-serif; letter-spacing: 1px; }

  .role-badge {
    padding: 3px 10px; border-radius: 4px;
    font-family: 'Rajdhani', sans-serif; font-size: 0.75rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase;
  }

  .role-badge.admin { background: var(--gold); color: var(--ink); }
  .role-badge.lspd { background: var(--steel); color: var(--ink); }
  .role-badge.user { background: var(--muted); color: var(--ink); }

  .logout-btn {
    padding: 8px 16px; background: transparent;
    border: 1px solid var(--red); border-radius: 4px;
    color: var(--red); font-family: 'Rajdhani', sans-serif;
    font-size: 0.8rem; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; cursor: pointer; transition: all .2s;
  }

  .logout-btn:hover { background: var(--red); color: var(--ink); }

  .container { max-width: 1100px; margin: 0 auto; padding: 48px 24px 80px; }

  .page-title { font-family: 'Rajdhani', sans-serif; font-size: 1.8rem; font-weight: 700; color: var(--white); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 6px; }
  .page-sub { font-size: 0.85rem; color: var(--muted); margin-bottom: 40px; letter-spacing: 1px; }

  .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 48px; }

  .stat-card { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 24px; text-align: center; }
  .stat-card h3 { font-family: 'Rajdhani', sans-serif; font-size: 2.2rem; font-weight: 700; color: var(--ice); letter-spacing: 2px; }
  .stat-card p { font-size: 0.78rem; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }

  .section-label { font-family: 'Rajdhani', sans-serif; font-size: 0.75rem; font-weight: 700; letter-spacing: 4px; text-transform: uppercase; color: var(--steel); margin-bottom: 10px; }
  .section-title { font-family: 'Rajdhani', sans-serif; font-size: 1.4rem; font-weight: 700; color: var(--white); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 24px; }

  .users-table-wrap { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 40px; }

  .users-table { width: 100%; border-collapse: collapse; }

  .users-table th {
    background: var(--ink2); padding: 14px 20px;
    font-family: 'Rajdhani', sans-serif; font-size: 0.78rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase; color: var(--steel);
    text-align: left; border-bottom: 2px solid var(--border);
  }

  .users-table td { padding: 14px 20px; font-size: 0.88rem; color: var(--text); border-bottom: 1px solid var(--ink3); }
  .users-table tr:last-child td { border-bottom: none; }
  .users-table tr:hover td { background: var(--ink3); }

  .role-pill { display: inline-block; padding: 3px 10px; border-radius: 4px; font-family: 'Rajdhani', sans-serif; font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
  .role-pill.admin { background: var(--gold); color: var(--ink); }
  .role-pill.lspd { background: var(--steel); color: var(--ink); }
  .role-pill.user { background: var(--muted); color: var(--ink); }

  .delete-btn {
    padding: 6px 12px; background: transparent; border: 1px solid var(--red); border-radius: 4px;
    color: var(--red); font-family: 'Rajdhani', sans-serif; font-size: 0.72rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: all .2s;
  }

  .delete-btn:hover { background: var(--red); color: var(--ink); }

  .add-user-form { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 28px; margin-bottom: 40px; }

  .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 16px; align-items: end; }

  .form-group { display: flex; flex-direction: column; gap: 8px; }

  .form-group label { font-family: 'Rajdhani', sans-serif; font-size: 0.75rem; font-weight: 700; color: var(--ice); letter-spacing: 2px; text-transform: uppercase; }

  .form-group input,
  .form-group select {
    padding: 10px 14px; background: var(--ink2); border: 1px solid var(--border);
    border-radius: 6px; color: var(--white); font-family: 'Inter', sans-serif;
    font-size: 0.9rem; outline: none; transition: border-color .2s;
  }

  .form-group input:focus,
  .form-group select:focus { border-color: var(--steel); }
  .form-group select { cursor: pointer; }

  .submit-btn {
    padding: 10px 24px; background: var(--steel); color: var(--ink);
    font-family: 'Rajdhani', sans-serif; font-size: 0.85rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase; border: none;
    border-radius: 6px; cursor: pointer; transition: background .2s; white-space: nowrap;
  }

  .submit-btn:hover { background: var(--ice); }

  .form-msg { margin-top: 12px; font-size: 0.85rem; padding: 10px 14px; border-radius: 6px; display: none; }
  .form-msg.success { display: block; background: rgba(74,222,128,.1); border: 1px solid var(--green); color: var(--green); }
  .form-msg.error { display: block; background: rgba(248,113,113,.1); border: 1px solid var(--red); color: var(--red); }

  .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--steel); text-decoration: none; font-family: 'Rajdhani', sans-serif; font-size: 0.85rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 32px; }
  .back-link:hover { color: var(--ice); }

  @media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <div class="header-left">
      <img src="img/Logo.png" alt="badge" width="40" height="40" style="object-fit:contain;">
      <div class="header-title">
        <h1>Los Santos Police Department</h1>
        <p>Admin Dashboard</p>
      </div>
    </div>
    <div class="header-right">
      <div class="user-info">
        <strong><?= htmlspecialchars($currentUser['username']) ?></strong>
        <span class="role-badge admin">ADMIN</span>
      </div>
      <button class="logout-btn" id="logoutBtn">Logout</button>
    </div>
  </div>
</header>

<div class="container">
  <a href="index.html" class="back-link">← Kembali ke Beranda</a>

  <h1 class="page-title">Admin Dashboard</h1>
  <p class="page-sub">Kelola user dan pantau sistem LSPD</p>

  <div class="stats-grid">
    <div class="stat-card"><h3 id="statTotal">-</h3><p>Total User</p></div>
    <div class="stat-card"><h3 id="statAdmin">-</h3><p>Admin</p></div>
    <div class="stat-card"><h3 id="statLspd">-</h3><p>LSPD Officer</p></div>
  </div>

  <p class="section-label">Manajemen User</p>
  <h2 class="section-title">Tambah User Baru</h2>

  <div class="add-user-form">
    <div class="form-row">
      <div class="form-group">
        <label>Username</label>
        <input type="text" id="newUsername" placeholder="Masukkan username">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" id="newPassword" placeholder="Masukkan password">
      </div>
      <div class="form-group">
        <label>Role</label>
        <select id="newRole">
          <option value="user">User</option>
          <option value="lspd">LSPD</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <button class="submit-btn" id="addUserBtn">Tambah</button>
    </div>
    <div class="form-msg" id="addUserMsg"></div>
  </div>

  <p class="section-label">Manajemen User</p>
  <h2 class="section-title">Daftar User</h2>

  <div class="users-table-wrap">
    <table class="users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Role</th>
          <th>Dibuat</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="usersTableBody">
        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px;">Memuat...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
async function loadUsers() {
  try {
    const res = await fetch('api/admin/users.php', { credentials: 'same-origin' });
    if (res.status === 401 || res.status === 403) { window.location.href = 'login.php'; return; }
    const data = await res.json();
    renderStats(data.users);
    renderUsers(data.users);
  } catch (e) { console.error(e); }
}

function renderStats(users) {
  document.getElementById('statTotal').textContent = users.length;
  document.getElementById('statAdmin').textContent = users.filter(u => u.role === 'admin').length;
  document.getElementById('statLspd').textContent = users.filter(u => u.role === 'lspd').length;
}

function renderUsers(users) {
  const tbody = document.getElementById('usersTableBody');
  if (!users.length) { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px;">Belum ada user</td></tr>'; return; }
  tbody.innerHTML = users.map(u => `
    <tr>
      <td style="font-family:'JetBrains Mono',monospace;color:var(--muted);">#${u.id}</td>
      <td>${u.username}</td>
      <td><span class="role-pill ${u.role}">${u.role}</span></td>
      <td style="color:var(--muted);font-size:0.8rem;">${new Date(u.created_at).toLocaleString('id-ID')}</td>
      <td><button class="delete-btn" onclick="deleteUser(${u.id})">Hapus</button></td>
    </tr>
  `).join('');
}

async function addUser() {
  const username = document.getElementById('newUsername').value.trim();
  const password = document.getElementById('newPassword').value;
  const role = document.getElementById('newRole').value;
  const msg = document.getElementById('addUserMsg');

  if (!username || !password) { msg.textContent = 'Username dan password wajib diisi'; msg.className = 'form-msg error'; return; }

  const res = await fetch('api/admin/users.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password, role }),
    credentials: 'same-origin'
  });

  const data = await res.json();
  if (res.ok) {
    msg.textContent = `User "${username}" berhasil dibuat!`; msg.className = 'form-msg success';
    document.getElementById('newUsername').value = ''; document.getElementById('newPassword').value = '';
    loadUsers();
  } else { msg.textContent = data.error || 'Gagal'; msg.className = 'form-msg error'; }
}

async function deleteUser(id) {
  if (!confirm('Yakin ingin menghapus user ini?')) return;
  const res = await fetch(`api/admin/users.php?id=${id}`, { method: 'DELETE', credentials: 'same-origin' });
  if (res.ok) loadUsers();
  else { const data = await res.json(); alert(data.error || 'Gagal'); }
}

document.getElementById('addUserBtn').addEventListener('click', addUser);
document.getElementById('logoutBtn').addEventListener('click', async () => {
  await fetch('api/logout.php', { method: 'POST', credentials: 'same-origin' });
  window.location.href = 'login.php';
});

loadUsers();
</script>
</body>
</html>