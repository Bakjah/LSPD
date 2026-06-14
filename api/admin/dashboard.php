<?php
/**
 * Admin Dashboard API — Statistics and overview
 */
require_once __DIR__ . '/config.php';

$db = getDB();
$user = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$stats = [
    'total_users'    => (int) $db->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
    'active_users'   => (int) $db->query("SELECT COUNT(*) as c FROM users WHERE is_active=1 AND is_banned=0")->fetch_assoc()['c'],
    'banned_users'   => (int) $db->query("SELECT COUNT(*) as c FROM users WHERE is_banned=1")->fetch_assoc()['c'],
    'suspended_users'=> (int) $db->query("SELECT COUNT(*) as c FROM users WHERE is_suspended=1")->fetch_assoc()['c'],
    'total_threads'  => (int) $db->query("SELECT COUNT(*) as c FROM threads WHERE is_deleted=0")->fetch_assoc()['c'],
    'total_posts'    => (int) $db->query("SELECT COUNT(*) as c FROM posts WHERE is_deleted=0")->fetch_assoc()['c'],
    'total_forums'   => (int) $db->query("SELECT COUNT(*) as c FROM forums WHERE is_active=1")->fetch_assoc()['c'],
    'total_categories'=> (int) $db->query("SELECT COUNT(*) as c FROM categories WHERE is_active=1")->fetch_assoc()['c'],
    'unread_notifs'  => (int) $db->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=" . $user['id'] . " AND is_read=0")->fetch_assoc()['c'],
];

// Department stats
$deptStats = [];
$deptResult = $db->query("SELECT d.name, d.code, COUNT(u.id) as user_count FROM departments d LEFT JOIN users u ON d.id = u.department_id AND u.is_banned=0 GROUP BY d.id ORDER BY user_count DESC");
while ($d = $deptResult->fetch_assoc()) {
    $deptStats[] = ['name' => $d['name'], 'code' => $d['code'], 'user_count' => (int) $d['user_count']];
}

// Recent registrations
$recentUsers = $db->query("SELECT id, username, join_date, is_banned FROM users ORDER BY join_date DESC LIMIT 10");
$recentUsersList = [];
while ($u = $recentUsers->fetch_assoc()) {
    $recentUsersList[] = ['id' => (int) $u['id'], 'username' => $u['username'], 'join_date' => $u['join_date'], 'is_banned' => (bool) $u['is_banned']];
}

// Recent activity
$recentActivity = $db->query("SELECT a.*, u.username FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 20");
$recentActivityList = [];
while ($a = $recentActivity->fetch_assoc()) {
    $recentActivityList[] = [
        'id' => (int) $a['id'],
        'username' => $a['username'] ?? 'System',
        'action' => $a['action'],
        'details' => $a['details'],
        'ip_address' => $a['ip_address'],
        'created_at' => $a['created_at'],
    ];
}

// Login stats
$loginStats = [
    'today'    => (int) $db->query("SELECT COUNT(*) as c FROM login_logs WHERE DATE(created_at)=CURDATE() AND status='success'")->fetch_assoc()['c'],
    'this_week'=> (int) $db->query("SELECT COUNT(*) as c FROM login_logs WHERE YEARWEEK(created_at)=YEARWEEK(NOW()) AND status='success'")->fetch_assoc()['c'],
    'failed_today'=> (int) $db->query("SELECT COUNT(*) as c FROM login_logs WHERE DATE(created_at)=CURDATE() AND status='failed'")->fetch_assoc()['c'],
];

jsonResponse([
    'stats'          => $stats,
    'dept_stats'     => $deptStats,
    'recent_users'   => $recentUsersList,
    'recent_activity'=> $recentActivityList,
    'login_stats'    => $loginStats,
]);
