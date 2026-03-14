<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id']    = 1;
    $_SESSION['user_name']  = 'Daniel Kumwenda';
    $_SESSION['user_email'] = 'daniel.k@example.com';
    $_SESSION['user_type']  = 'individual';
    $_SESSION['first_name'] = 'Daniel';
    $_SESSION['last_name']  = 'Kumwenda';
}
$userName  = $_SESSION['user_name']  ?? 'Member';
$userEmail = $_SESSION['user_email'] ?? '';
$userType  = $_SESSION['user_type']  ?? 'individual';
$firstName = explode(' ', $userName)[0];


// Read which panel to open from the URL (?panel=bookings etc.)
$allowed_panels = ['overview','bookings','history','reminders','vehicle','health','notifications','settings'];
$initial_panel  = in_array($_GET['panel'] ?? '', $allowed_panels) ? $_GET['panel'] : 'overview';

// ─────────────────────────────────────────────────────────────
//  MOCK DATA — Replace each array with real DB queries / session
// ─────────────────────────────────────────────────────────────
$user = [
    'first_name'      => $_SESSION['first_name']  ?? 'Daniel',
    'last_name'       => $_SESSION['last_name']   ?? 'Kumwenda',
    'email'           => $_SESSION['email']        ?? 'daniel.k@example.com',
    'phone'           => $_SESSION['phone']        ?? '+265 993 575 111',
    'member_since'    => '2023-04-15',
    'avatar_initials' => strtoupper(
                            substr($_SESSION['first_name'] ?? 'D', 0, 1) .
                            substr($_SESSION['last_name']  ?? 'K', 0, 1)
                         ),
    'type'            => 'Individual',
    'city'            => 'Blantyre',
];

$vehicle = [
    'make'                 => 'Toyota',
    'model'                => 'Corolla',
    'year'                 => '2019',
    'registration'         => 'BT 4821',
    'color'                => 'Silver',
    'mileage'              => 87450,
    'last_service_mileage' => 82000,
    'next_service_mileage' => 92000,
    'engine'               => '1.8L 4-Cylinder',
    'transmission'         => 'Automatic',
];

$upcoming_bookings = [
    ['id'=>'WM-2025-041','service'=>'General Maintenance','date'=>'2025-03-08','time'=>'09:00','status'=>'confirmed','tech'=>'Brian Phiri',  'notes'=>'Oil change, filter replacement, full inspection'],
    ['id'=>'WM-2025-047','service'=>'Brake Inspection',   'date'=>'2025-03-22','time'=>'10:30','status'=>'pending',  'tech'=>'TBA',         'notes'=>'Front brake pads making noise'],
];

$service_history = [
    ['id'=>'WM-2024-198','service'=>'Engine Repair',      'date'=>'2024-11-14','cost'=>'MWK 45,000','status'=>'completed','tech'=>'Brian Phiri', 'notes'=>'Replaced timing belt, coolant flush'],
    ['id'=>'WM-2024-156','service'=>'Auto Detailing',     'date'=>'2024-09-03','cost'=>'MWK 18,000','status'=>'completed','tech'=>'James Banda',  'notes'=>'Full interior & exterior detail'],
    ['id'=>'WM-2024-110','service'=>'General Maintenance','date'=>'2024-06-20','cost'=>'MWK 12,500','status'=>'completed','tech'=>'Brian Phiri', 'notes'=>'Oil change, air filter, tyre rotation'],
    ['id'=>'WM-2024-072','service'=>'AC Repair',          'date'=>'2024-04-11','cost'=>'MWK 22,000','status'=>'completed','tech'=>'Moses Chirwa', 'notes'=>'Refrigerant recharge, condenser clean'],
    ['id'=>'WM-2023-214','service'=>'Brake Services',     'date'=>'2023-12-05','cost'=>'MWK 28,000','status'=>'completed','tech'=>'Brian Phiri', 'notes'=>'New front & rear brake pads'],
];

$reminders = [
    ['title'=>'Tyre Rotation',          'due_km'=>90000, 'current_km'=>87450,'urgency'=>'urgent','icon'=>'circle',     'interval'=>'Every 8,000km'],
    ['title'=>'Brake Fluid Flush',      'due_km'=>90000, 'current_km'=>87450,'urgency'=>'urgent','icon'=>'disc',       'interval'=>'Every 2 years'],
    ['title'=>'Engine Oil Change',      'due_km'=>92000, 'current_km'=>87450,'urgency'=>'soon',  'icon'=>'droplet',    'interval'=>'Every 5,000km'],
    ['title'=>'Air Filter Replacement', 'due_km'=>95000, 'current_km'=>87450,'urgency'=>'ok',    'icon'=>'wind',       'interval'=>'Every 12,000km'],
    ['title'=>'Coolant Service',        'due_km'=>100000,'current_km'=>87450,'urgency'=>'ok',    'icon'=>'thermometer','interval'=>'Every 30,000km'],
    ['title'=>'Transmission Service',   'due_km'=>120000,'current_km'=>87450,'urgency'=>'ok',    'icon'=>'settings',   'interval'=>'Every 40,000km'],
];

$notifications = [
    ['type'=>'success','title'=>'Booking Confirmed',     'msg'=>'Your service on 8 Mar at 09:00 is confirmed.', 'time'=>'2 hrs ago', 'read'=>false],
    ['type'=>'warning','title'=>'Tyre Rotation Overdue', 'msg'=>'Your tyres are 2,550 km overdue for rotation.','time'=>'1 day ago', 'read'=>false],
    ['type'=>'info',   'title'=>'Offer: 15% Off Detailing','msg'=>'Member exclusive — valid until 31 March.','time'=>'3 days ago','read'=>true],
    ['type'=>'success','title'=>'Invoice Ready',         'msg'=>'Invoice WM-2024-198 is available to download.','time'=>'4 mo ago', 'read'=>true],
];

$health_items = [
    ['label'=>'Engine',    'score'=>92,'icon'=>'settings'],
    ['label'=>'Brakes',    'score'=>58,'icon'=>'disc'],
    ['label'=>'Tyres',     'score'=>45,'icon'=>'circle'],
    ['label'=>'Oil Level', 'score'=>78,'icon'=>'droplet'],
    ['label'=>'AC System', 'score'=>88,'icon'=>'wind'],
    ['label'=>'Battery',   'score'=>95,'icon'=>'zap'],
];

// ── Helpers ──────────────────────────────────────────────────
function healthColor(int $s): string { return $s>=80 ? '#16a34a' : ($s>=55 ? '#f59e0b' : '#dc2626'); }
function healthBg(int $s): string    { return $s>=80 ? 'bg-green-50 text-green-700' : ($s>=55 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600'); }
function healthLabel(int $s): string { return $s>=80 ? 'Good' : ($s>=55 ? 'Fair' : 'Check'); }

function statusPill(string $s): string {
    return match($s) {
        'confirmed' => '<span class="status-pill bg-green-100 text-green-700">✓ Confirmed</span>',
        'pending'   => '<span class="status-pill bg-amber-100 text-amber-700">⏳ Pending</span>',
        'completed' => '<span class="status-pill bg-gray-100 text-gray-500">✔ Done</span>',
        default     => ''
    };
}

function urgencyClasses(string $u): string {
    return match($u) {
        'urgent' => 'border-red-200 bg-red-50',
        'soon'   => 'border-amber-200 bg-amber-50',
        default  => 'border-gray-200 bg-white',
    };
}
function urgencyBadge(string $u): string {
    return match($u) {
        'urgent' => '<span class="reminder-badge bg-red-600 text-white">Overdue</span>',
        'soon'   => '<span class="reminder-badge bg-amber-500 text-white">Due Soon</span>',
        default  => '<span class="reminder-badge bg-green-600 text-white">OK</span>',
    };
}
function notifIcon(string $t): string {
    return match($t) {
        'success' => '<div class="notif-icon bg-green-100 text-green-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>',
        'warning' => '<div class="notif-icon bg-amber-100 text-amber-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>',
        default   => '<div class="notif-icon bg-blue-100 text-blue-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>',
    };
}

$unread_count   = count(array_filter($notifications, fn($n) => !$n['read']));
$urgent_count   = count(array_filter($reminders, fn($r) => $r['urgency'] === 'urgent'));
$overall_health = (int)(array_sum(array_column($health_items,'score')) / count($health_items));

// Mileage progress to next service
$miles_done = $vehicle['mileage'] - $vehicle['last_service_mileage'];
$miles_span = $vehicle['next_service_mileage'] - $vehicle['last_service_mileage'];
$service_pct = min(100, round($miles_done / $miles_span * 100));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>My Dashboard — Wiyule Motors</title>

    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

    <style>
        /* ── CSS Variables ───────────────────────────────── */
        :root {
            --red:       #dc2626;
            --red-dark:  #b91c1c;
            --red-light: #fef2f2;
            --sidebar-w: 260px;
            --sidebar-collapsed: 72px;
            --header-h: 64px;
            --radius:   16px;
            --shadow:   0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.06);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ─────────────────────────────────────── */
        #sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: #0f172a;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0; top: 0; bottom: 0;
            z-index: 100;
            transition: width 0.3s cubic-bezier(0.4,0,0.2,1),
                        transform 0.3s cubic-bezier(0.4,0,0.2,1);
            overflow: hidden;
        }
        #sidebar.collapsed { width: var(--sidebar-collapsed); }

        .sidebar-logo {
            display: flex; align-items: center; gap: 12px;
            padding: 20px 18px 18px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-decoration: none;
            min-height: 72px;
            overflow: hidden;
        }
        .sidebar-logo-img {
            width: 36px; height: 36px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .sidebar-logo-text {
            font-size: 15px; font-weight: 800;
            color: #fff;
            white-space: nowrap;
            transition: opacity 0.2s;
        }
        #sidebar.collapsed .sidebar-logo-text { opacity: 0; pointer-events: none; }

        /* Nav sections */
        .sidebar-section {
            padding: 20px 10px 4px;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar-section::-webkit-scrollbar { width: 4px; }
        .sidebar-section::-webkit-scrollbar-track { background: transparent; }
        .sidebar-section::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

        .nav-group-label {
            font-size: 10px; font-weight: 700;
            color: #475569;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 8px 10px 6px;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.2s;
        }
        #sidebar.collapsed .nav-group-label { opacity: 0; }

        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 10px;
            border-radius: 12px;
            color: #94a3b8;
            font-size: 14px; font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            position: relative;
            text-decoration: none;
            margin-bottom: 2px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.06); color: #e2e8f0; }
        .nav-item.active {
            background: linear-gradient(135deg, var(--red) 0%, var(--red-dark) 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(220,38,38,0.35);
        }
        .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
        .nav-item span { transition: opacity 0.2s; }
        #sidebar.collapsed .nav-item span { opacity: 0; }

        /* Badge on nav */
        .nav-badge {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: 10px; font-weight: 800;
            width: 18px; height: 18px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: opacity 0.2s;
        }
        #sidebar.collapsed .nav-badge { opacity: 0; }

        /* Sidebar user card */
        .sidebar-user {
            padding: 14px 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; gap: 10px;
            cursor: pointer;
            transition: background 0.2s;
            overflow: hidden;
        }
        .sidebar-user:hover { background: rgba(255,255,255,0.04); }
        .sidebar-avatar {
            width: 38px; height: 38px; border-radius: 12px;
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800;
            flex-shrink: 0;
        }
        .sidebar-user-info { overflow: hidden; transition: opacity 0.2s; }
        .sidebar-user-name { font-size: 13px; font-weight: 700; color: #f8fafc; white-space: nowrap; }
        .sidebar-user-role { font-size: 11px; color: #64748b; white-space: nowrap; }
        #sidebar.collapsed .sidebar-user-info { opacity: 0; }

        /* Collapse toggle */
        #collapseBtn {
            position: absolute;
            top: 20px; right: -14px;
            width: 28px; height: 28px;
            background: #1e293b;
            border: 2px solid #334155;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: #94a3b8;
            z-index: 101;
            transition: transform 0.3s, color 0.2s;
        }
        #collapseBtn:hover { color: #fff; }
        #sidebar.collapsed #collapseBtn { transform: rotate(180deg); }

        /* ── Main content ─────────────────────────────────── */
        #main {
            margin-left: var(--sidebar-w);
            flex: 1;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        #main.expanded { margin-left: var(--sidebar-collapsed); }

        /* ── Top Header ───────────────────────────────────── */
        .top-header {
            height: var(--header-h);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center;
            padding: 0 28px;
            gap: 16px;
            position: sticky;
            top: 0; z-index: 50;
        }
        .header-search {
            flex: 1; max-width: 400px;
            display: flex; align-items: center;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 14px;
            gap: 10px;
            transition: border-color 0.2s;
        }
        .header-search:focus-within { border-color: var(--red); }
        .header-search input {
            background: none; border: none; outline: none;
            font-size: 14px; font-family: 'Inter', sans-serif;
            color: #1e293b; width: 100%;
        }
        .header-search input::placeholder { color: #94a3b8; }

        .header-actions { display: flex; align-items: center; gap: 12px; margin-left: auto; }

        .icon-btn {
            width: 40px; height: 40px;
            border-radius: 12px;
            border: none; background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #64748b;
            transition: all 0.2s; position: relative;
        }
        .icon-btn:hover { background: var(--red-light); color: var(--red); }
        .icon-btn .badge {
            position: absolute;
            top: 6px; right: 6px;
            width: 8px; height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .header-user {
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; padding: 6px 12px;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .header-user:hover { background: #f8fafc; }
        .header-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800;
        }

        /* ── Page body ────────────────────────────────────── */
        .page-body { padding: 28px; display: flex; flex-direction: column; gap: 24px; }

        /* ── Cards ────────────────────────────────────────── */
        .card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 22px 0;
            margin-bottom: 18px;
        }
        .card-title {
            font-size: 15px; font-weight: 700; color: #0f172a;
            display: flex; align-items: center; gap: 8px;
        }
        .card-title svg { width: 17px; height: 17px; color: var(--red); }
        .card-action {
            font-size: 13px; font-weight: 600; color: var(--red);
            text-decoration: none; cursor: pointer;
            padding: 5px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .card-action:hover { background: var(--red-light); }
        .card-body { padding: 0 22px 22px; }

        /* ── Stat cards ───────────────────────────────────── */
        .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
        .stat-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px 22px;
            display: flex; flex-direction: column; gap: 14px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }
        .stat-card.red::before    { background: var(--red); }
        .stat-card.amber::before  { background: #f59e0b; }
        .stat-card.blue::before   { background: #3b82f6; }
        .stat-card.green::before  { background: #16a34a; }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .stat-icon.red   { background:#fef2f2; color: var(--red); }
        .stat-icon.amber { background:#fffbeb; color: #d97706; }
        .stat-icon.blue  { background:#eff6ff; color: #2563eb; }
        .stat-icon.green { background:#f0fdf4; color: #15803d; }
        .stat-icon svg   { width: 22px; height: 22px; }

        .stat-value { font-size: 28px; font-weight: 900; color: #0f172a; line-height: 1; }
        .stat-label { font-size: 13px; font-weight: 600; color: #64748b; }
        .stat-change { font-size: 12px; color: #94a3b8; }

        /* ── Welcome banner ───────────────────────────────── */
        .welcome-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: relative; overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .welcome-banner::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 50%, rgba(220,38,38,0.15) 0%, transparent 60%),
                radial-gradient(circle at 10% 80%, rgba(220,38,38,0.08) 0%, transparent 50%);
        }
        .welcome-grid {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .welcome-text { position: relative; z-index: 1; }
        .welcome-text h2 { font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 6px; }
        .welcome-text p  { font-size: 14px; color: #94a3b8; margin-bottom: 18px; }
        .welcome-cta {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--red);
            color: #fff;
            padding: 11px 22px;
            border-radius: 12px;
            font-size: 14px; font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(220,38,38,0.4);
        }
        .welcome-cta:hover { background: var(--red-dark); transform: translateY(-1px); }

        /* Next booking badge */
        .next-booking-card {
            position: relative; z-index: 1;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 18px 22px;
            min-width: 240px;
            backdrop-filter: blur(8px);
        }
        .next-booking-label { font-size: 10px; font-weight: 700; color: #ef4444; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
        .next-booking-service { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 4px; }
        .next-booking-info { font-size: 13px; color: #94a3b8; }
        .booking-countdown {
            margin-top: 10px;
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(220,38,38,0.2);
            border: 1px solid rgba(220,38,38,0.3);
            color: #fca5a5;
            font-size: 12px; font-weight: 600;
            padding: 4px 10px; border-radius: 99px;
        }

        /* ── Vehicle health ring ──────────────────────────── */
        .health-ring-wrap { display: flex; justify-content: center; padding: 10px 0 6px; }
        .health-ring { position: relative; width: 140px; height: 140px; }
        .health-ring svg { transform: rotate(-90deg); }
        .health-ring-inner {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .health-pct { font-size: 32px; font-weight: 900; line-height: 1; }
        .health-sublabel { font-size: 11px; font-weight: 600; color: #94a3b8; margin-top: 2px; }

        .health-items { display: flex; flex-direction: column; gap: 10px; }
        .health-row {
            display: flex; align-items: center; gap: 12px;
        }
        .health-icon {
            width: 32px; height: 32px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .health-icon svg { width: 15px; height: 15px; }
        .health-bar-wrap { flex: 1; }
        .health-bar-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px; }
        .health-bar-track { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
        .health-bar-fill  { height: 100%; border-radius: 99px; transition: width 1s cubic-bezier(0.4,0,0.2,1); }

        /* ── Bookings table ───────────────────────────────── */
        .booking-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
            cursor: pointer;
            border-radius: 10px;
            padding-left: 8px; padding-right: 8px;
            margin: 0 -8px;
        }
        .booking-row:last-child { border-bottom: none; }
        .booking-row:hover { background: #f8fafc; }
        .booking-service-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: var(--red-light);
            display: flex; align-items: center; justify-content: center;
            color: var(--red); flex-shrink: 0;
        }
        .booking-service-icon svg { width: 20px; height: 20px; }
        .booking-name { font-size: 14px; font-weight: 700; color: #0f172a; }
        .booking-meta { font-size: 12px; color: #64748b; margin-top: 2px; }
        .booking-id   { font-size: 12px; font-weight: 600; color: #94a3b8; font-family: monospace; }

        .status-pill { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; }

        /* ── Service history timeline ─────────────────────── */
        .timeline { position: relative; padding-left: 28px; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 9px; top: 8px; bottom: 8px;
            width: 2px;
            background: linear-gradient(to bottom, var(--red), #e2e8f0);
        }
        .timeline-item {
            position: relative;
            padding-bottom: 22px;
        }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-dot {
            position: absolute;
            left: -22px; top: 4px;
            width: 14px; height: 14px;
            border-radius: 50%;
            background: var(--red);
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px var(--red);
        }
        .timeline-item:not(:first-child) .timeline-dot { background: #cbd5e1; box-shadow: 0 0 0 2px #cbd5e1; }
        .timeline-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .timeline-card:hover { border-color: var(--red); background: var(--red-light); }
        .timeline-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .timeline-service { font-size: 14px; font-weight: 700; color: #0f172a; }
        .timeline-cost { font-size: 13px; font-weight: 800; color: var(--red); }
        .timeline-meta { font-size: 12px; color: #64748b; }
        .timeline-notes { font-size: 12px; color: #94a3b8; margin-top: 4px; font-style: italic; }

        /* ── Reminders ────────────────────────────────────── */
        .reminder-item {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .reminder-item:last-child { margin-bottom: 0; }
        .reminder-item:hover { transform: translateX(3px); }
        .reminder-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .reminder-icon svg { width: 20px; height: 20px; }
        .reminder-body { flex: 1; }
        .reminder-title { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .reminder-sub   { font-size: 12px; color: #64748b; }
        .reminder-badge { font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 99px; flex-shrink: 0; }
        .km-bar-wrap { margin-top: 5px; }
        .km-bar { height: 4px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .km-fill { height: 100%; border-radius: 99px; }

        /* ── Notifications ────────────────────────────────── */
        .notif-item {
            display: flex; gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notif-body { flex: 1; }
        .notif-title { font-size: 13px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px; }
        .notif-unread { width: 7px; height: 7px; border-radius: 50%; background: var(--red); flex-shrink: 0; }
        .notif-msg { font-size: 12px; color: #64748b; margin-top: 2px; }
        .notif-time { font-size: 11px; color: #94a3b8; white-space: nowrap; }

        /* ── Vehicle card ─────────────────────────────────── */
        .vehicle-card {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border-radius: var(--radius);
            padding: 24px;
            color: #fff;
            position: relative; overflow: hidden;
        }
        .vehicle-card::after {
            content: '🚗';
            position: absolute;
            right: -10px; bottom: -20px;
            font-size: 110px;
            opacity: 0.07;
            line-height: 1;
        }
        .vehicle-make { font-size: 11px; font-weight: 700; color: #ef4444; text-transform: uppercase; letter-spacing: 0.1em; }
        .vehicle-name { font-size: 22px; font-weight: 900; color: #fff; margin: 4px 0 2px; }
        .vehicle-year { font-size: 14px; color: #94a3b8; margin-bottom: 16px; }
        .vehicle-reg  {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 5px 14px; border-radius: 8px;
            font-size: 14px; font-weight: 800;
            letter-spacing: 2px; color: #e2e8f0;
            margin-bottom: 16px;
        }
        .vehicle-spec { display: flex; gap: 0; flex-wrap: wrap; }
        .vehicle-spec-item {
            flex: 1; min-width: 100px;
            padding: 10px 0;
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .vehicle-spec-item:last-child { border-right: none; }
        .vehicle-spec-label { font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
        .vehicle-spec-value { font-size: 14px; font-weight: 700; color: #e2e8f0; }

        /* Mileage progress */
        .mileage-section { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.06); }
        .mileage-label { display: flex; justify-content: space-between; font-size: 12px; color: #94a3b8; margin-bottom: 8px; }
        .mileage-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 99px; overflow: hidden; }
        .mileage-fill { height: 100%; background: linear-gradient(90deg, var(--red), #f97316); border-radius: 99px; transition: width 1.2s cubic-bezier(0.4,0,0.2,1); }

        /* ── Quick actions ────────────────────────────────── */
        .quick-actions { display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; }
        .quick-btn {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            padding: 16px 12px;
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            text-decoration: none;
        }
        .quick-btn:hover { border-color: var(--red); background: var(--red-light); transform: translateY(-2px); }
        .quick-btn-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .quick-btn-icon svg { width: 22px; height: 22px; }
        .quick-btn-label { font-size: 12px; font-weight: 700; color: #374151; text-align: center; }

        /* ── Offer banner ─────────────────────────────────── */
        .offer-banner {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 14px;
            padding: 16px 18px;
            display: flex; align-items: center; gap: 14px;
            position: relative; overflow: hidden;
        }
        .offer-banner::after { content: '🎉'; position: absolute; right: 12px; font-size: 36px; opacity: 0.35; }
        .offer-icon { width: 42px; height: 42px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .offer-text-title { font-size: 13px; font-weight: 800; color: #fff; }
        .offer-text-sub   { font-size: 12px; color: #bbf7d0; margin-top: 1px; }

        /* ── Modals ───────────────────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 200; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff; border-radius: 20px;
            padding: 32px 28px; max-width: 520px; width: 100%;
            box-shadow: 0 32px 80px rgba(0,0,0,0.2);
            animation: popIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes popIn {
            from { transform: scale(0.88); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .modal-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .modal-sub   { font-size: 13px; color: #64748b; margin-bottom: 20px; }
        .modal-field { margin-bottom: 16px; }
        .modal-field label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 5px; }
        .modal-field input,
        .modal-field select,
        .modal-field textarea {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 14px; font-family: 'Inter', sans-serif; color: #0f172a;
            outline: none; transition: border-color 0.2s;
            background: #fff;
        }
        .modal-field input:focus,
        .modal-field select:focus,
        .modal-field textarea:focus { border-color: var(--red); }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-modal-primary {
            flex: 1; background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff; border: none; padding: 13px; border-radius: 12px;
            font-size: 14px; font-weight: 700; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-modal-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(220,38,38,0.3); }
        .btn-modal-ghost {
            flex: 0; padding: 13px 20px;
            border: 1.5px solid #e2e8f0; border-radius: 12px;
            font-size: 14px; font-weight: 600; font-family: 'Inter', sans-serif;
            background: none; color: #374151; cursor: pointer; transition: all 0.2s;
        }
        .btn-modal-ghost:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 1280px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
        }
        @media (max-width: 1024px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.mobile-open { transform: translateX(0); }
            #main { margin-left: 0 !important; }
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 16px; }
            .next-booking-card { min-width: unset; width: 100%; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .page-body { padding: 16px; gap: 16px; }
            .welcome-banner { padding: 22px 20px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }

        /* ── Page fade-in ─────────────────────────────────── */
        .fade-in { animation: fadeInUp 0.5s ease both; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .delay-1 { animation-delay: 0.08s; }
        .delay-2 { animation-delay: 0.16s; }
        .delay-3 { animation-delay: 0.24s; }
        .delay-4 { animation-delay: 0.32s; }
        .delay-5 { animation-delay: 0.40s; }

        /* Pulse dot for live */
        .live-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
            display: inline-block;
            animation: livePulse 2s infinite;
        }
        @keyframes livePulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
            50%      { box-shadow: 0 0 0 5px rgba(34,197,94,0); }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════ SIDEBAR ══════════════════ -->
<nav id="sidebar">
    <button id="collapseBtn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    </button>

    <!-- Logo -->
    <a href="/" class="sidebar-logo">
        <img class="sidebar-logo-img"
             src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQiVbOHrYXKB55eoDs80oh_qeIFhGlcupYTQg&s"
             alt="Wiyule Motors">
        <span class="sidebar-logo-text">Wiyule Motors</span>
    </a>

    <!-- Navigation -->
    <div class="sidebar-section">
        <!-- Main -->
        <div class="nav-group-label">Main</div>
        <a class="nav-item active" onclick="showPanel('overview')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <span>Overview</span>
        </a>
        <a class="nav-item" onclick="showPanel('bookings')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>My Bookings</span>
            <?php if(count($upcoming_bookings)): ?><span class="nav-badge"><?= count($upcoming_bookings) ?></span><?php endif; ?>
        </a>
        <a class="nav-item" onclick="showPanel('history')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 0 .5-4.5"/><polyline points="3 2 3 8 9 8"/></svg>
            <span>Service History</span>
        </a>
        <a class="nav-item" onclick="showPanel('reminders')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Reminders</span>
            <?php if($urgent_count): ?><span class="nav-badge"><?= $urgent_count ?></span><?php endif; ?>
        </a>

        <!-- Vehicle -->
        <div class="nav-group-label" style="margin-top:12px">Vehicle</div>
        <a class="nav-item" onclick="showPanel('vehicle')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <span>My Vehicle</span>
        </a>
        <a class="nav-item" onclick="showPanel('health')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <span>Vehicle Health</span>
        </a>

        <!-- Account -->
        <div class="nav-group-label" style="margin-top:12px">Account</div>
        <a class="nav-item" onclick="showPanel('notifications')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Notifications</span>
            <?php if($unread_count): ?><span class="nav-badge"><?= $unread_count ?></span><?php endif; ?>
        </a>
        <a class="nav-item" onclick="showPanel('settings')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Settings</span>
        </a>

        <a href="/" class="nav-item" style="margin-top:8px">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Back to Site</span>
        </a>
    </div>

    <!-- User card -->
    <div class="sidebar-user" onclick="showPanel('settings')">
        <div class="sidebar-avatar"><?= htmlspecialchars($user['avatar_initials']) ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($user['type']) ?> Member</div>
        </div>
    </div>
</nav>

<!-- Mobile overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-[99] hidden lg:hidden" onclick="closeSidebar()"></div>

<!-- ═══════════════════════════════ MAIN ════════════════════ -->
<div id="main">

    <!-- Top Header -->
    <header class="top-header">
        <!-- Mobile menu button -->
        <button class="icon-btn lg:hidden" onclick="openSidebar()" aria-label="Open menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <!-- Search -->
        <div class="header-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search bookings, services, invoices…">
        </div>

        <div class="header-actions">
            <!-- Live status -->
            <div class="hidden md:flex items-center gap-2 text-xs font-semibold text-gray-500">
                <span class="live-dot"></span> Open today · 08:00–17:00
            </div>

            <!-- Notifications -->
            <button class="icon-btn" onclick="showPanel('notifications')" aria-label="Notifications">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if($unread_count): ?><span class="badge"></span><?php endif; ?>
            </button>

            <!-- Book now shortcut -->
            <button class="hidden sm:flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-4 py-2 rounded-xl hover:bg-red-700 transition border-none cursor-pointer" onclick="openBookModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Book Service
            </button>

            <!-- User -->
            <div class="header-user">
                <div class="header-avatar"><?= htmlspecialchars($user['avatar_initials']) ?></div>
                <div class="hidden md:block">
                    <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars($user['first_name']) ?></div>
                    <div class="text-xs text-gray-500"><?= htmlspecialchars($user['type']) ?></div>
                </div>
            </div>
        </div>
    </header>

    <!-- ═══ PAGE PANELS ══════════════════════════════════════ -->

    <!-- ─── OVERVIEW ─── -->
    <div id="panel-overview" class="page-body">

        <!-- Welcome banner -->
        <div class="welcome-banner fade-in">
            <div class="welcome-grid"></div>
            <div class="welcome-text">
                <h2>Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars($user['first_name']) ?> 👋</h2>
                <p>Here's what's happening with your <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?> today.</p>
                <a href="#" class="welcome-cta" onclick="openBookModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Book a Service
                </a>
            </div>
            <?php if(!empty($upcoming_bookings)): $b = $upcoming_bookings[0]; ?>
            <div class="next-booking-card">
                <div class="next-booking-label">📅 Next Appointment</div>
                <div class="next-booking-service"><?= htmlspecialchars($b['service']) ?></div>
                <div class="next-booking-info">
                    <?= date('D, d M Y', strtotime($b['date'])) ?> at <?= date('g:i A', strtotime($b['time'])) ?>
                </div>
                <div class="next-booking-info" style="margin-top:4px">Technician: <?= htmlspecialchars($b['tech']) ?></div>
                <div class="booking-countdown">
                    <span class="live-dot" style="background:#ef4444"></span>
                    <?= htmlspecialchars($b['id']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="stats-grid fade-in delay-1">
            <?php foreach ([
                ['label'=>'Total Services',   'value'=>count($service_history), 'icon'=>'tool',         'color'=>'red',   'change'=>'All time'],
                ['label'=>'Upcoming Bookings','value'=>count($upcoming_bookings),'icon'=>'calendar',     'color'=>'blue',  'change'=>'Scheduled'],
                ['label'=>'km to Next Service','value'=>number_format($vehicle['next_service_mileage'] - $vehicle['mileage']), 'icon'=>'alert-circle','color'=>'amber','change'=>'Est. June 2025'],
                ['label'=>'Savings Earned',   'value'=>'MWK 8K', 'icon'=>'tag',          'color'=>'green', 'change'=>'Member discounts'],
            ] as $s): ?>
            <div class="stat-card <?= $s['color'] ?>">
                <div class="stat-icon <?= $s['color'] ?>">
                    <i data-feather="<?= $s['icon'] ?>"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $s['value'] ?></div>
                    <div class="stat-label"><?= htmlspecialchars($s['label']) ?></div>
                    <div class="stat-change"><?= htmlspecialchars($s['change']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Main grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left col: bookings + history -->
            <div class="lg:col-span-2 flex flex-col gap-6">

                <!-- Upcoming bookings -->
                <div class="card fade-in delay-2">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Upcoming Bookings
                        </div>
                        <a class="card-action" onclick="openBookModal()">+ New booking</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($upcoming_bookings)): ?>
                        <div class="text-center py-10">
                            <div style="font-size:42px;opacity:0.25">📅</div>
                            <p class="text-sm text-gray-400 mt-2">No upcoming bookings</p>
                            <button onclick="openBookModal()" class="mt-4 text-sm font-bold text-red-600 hover:underline border-none background-none cursor-pointer">Book your first service →</button>
                        </div>
                        <?php else: ?>
                        <?php foreach ($upcoming_bookings as $b): ?>
                        <div class="booking-row">
                            <div class="booking-service-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            </div>
                            <div>
                                <div class="booking-name"><?= htmlspecialchars($b['service']) ?></div>
                                <div class="booking-meta">
                                    <?= date('D, d M Y', strtotime($b['date'])) ?> · <?= date('g:i A', strtotime($b['time'])) ?> · <?= htmlspecialchars($b['tech']) ?>
                                </div>
                            </div>
                            <div class="booking-id"><?= htmlspecialchars($b['id']) ?></div>
                            <div><?= statusPill($b['status']) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Service history -->
                <div class="card fade-in delay-3">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 0 .5-4.5"/><polyline points="3 2 3 8 9 8"/></svg>
                            Service History
                        </div>
                        <a class="card-action" onclick="showPanel('history')">View all</a>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach (array_slice($service_history,0,4) as $h): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-card">
                                    <div class="timeline-card-header">
                                        <div class="timeline-service"><?= htmlspecialchars($h['service']) ?></div>
                                        <div class="timeline-cost"><?= htmlspecialchars($h['cost']) ?></div>
                                    </div>
                                    <div class="timeline-meta">
                                        <?= date('d M Y', strtotime($h['date'])) ?> · <?= htmlspecialchars($h['tech']) ?> · <?= htmlspecialchars($h['id']) ?>
                                    </div>
                                    <div class="timeline-notes"><?= htmlspecialchars($h['notes']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /left col -->

            <!-- Right col -->
            <div class="flex flex-col gap-6">

                <!-- Vehicle card -->
                <div class="vehicle-card fade-in delay-2">
                    <div class="vehicle-make"><?= htmlspecialchars($vehicle['make']) ?></div>
                    <div class="vehicle-name"><?= htmlspecialchars($vehicle['model']) ?></div>
                    <div class="vehicle-year"><?= htmlspecialchars($vehicle['year'].' · '.$vehicle['color']) ?></div>
                    <div class="vehicle-reg"><?= htmlspecialchars($vehicle['registration']) ?></div>
                    <div class="vehicle-spec">
                        <div class="vehicle-spec-item">
                            <div class="vehicle-spec-label">Mileage</div>
                            <div class="vehicle-spec-value"><?= number_format($vehicle['mileage']) ?> km</div>
                        </div>
                        <div class="vehicle-spec-item" style="padding-left:12px">
                            <div class="vehicle-spec-label">Engine</div>
                            <div class="vehicle-spec-value"><?= htmlspecialchars($vehicle['engine']) ?></div>
                        </div>
                    </div>
                    <div class="mileage-section">
                        <div class="mileage-label">
                            <span>Service interval progress</span>
                            <span><?= $service_pct ?>% used</span>
                        </div>
                        <div class="mileage-bar">
                            <div class="mileage-fill" id="mileageFill" style="width:0%" data-target="<?= $service_pct ?>"></div>
                        </div>
                        <div style="font-size:11px;color:#64748b;margin-top:6px">
                            <?= number_format($vehicle['next_service_mileage'] - $vehicle['mileage']) ?> km remaining until next service
                        </div>
                    </div>
                </div>

                <!-- Vehicle health ring -->
                <div class="card fade-in delay-3">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            Vehicle Health
                        </div>
                        <a class="card-action" onclick="showPanel('health')">Details</a>
                    </div>
                    <div class="card-body">
                        <div class="health-ring-wrap">
                            <div class="health-ring">
                                <svg width="140" height="140" viewBox="0 0 140 140">
                                    <circle cx="70" cy="70" r="56" fill="none" stroke="#f1f5f9" stroke-width="10"/>
                                    <circle cx="70" cy="70" r="56" fill="none"
                                            stroke="<?= healthColor($overall_health) ?>"
                                            stroke-width="10"
                                            stroke-linecap="round"
                                            stroke-dasharray="<?= round(2*pi()*56) ?>"
                                            stroke-dashoffset="<?= round(2*pi()*56 * (1 - $overall_health/100)) ?>"
                                            id="healthArc"
                                            style="transition:stroke-dashoffset 1.2s cubic-bezier(0.4,0,0.2,1)"/>
                                </svg>
                                <div class="health-ring-inner">
                                    <div class="health-pct" style="color:<?= healthColor($overall_health) ?>"><?= $overall_health ?>%</div>
                                    <div class="health-sublabel"><?= healthLabel($overall_health) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="health-items" style="margin-top:12px">
                            <?php foreach ($health_items as $h): ?>
                            <div class="health-row">
                                <div class="health-icon <?= healthBg($h['score']) ?>">
                                    <i data-feather="<?= $h['icon'] ?>"></i>
                                </div>
                                <div class="health-bar-wrap">
                                    <div class="health-bar-label">
                                        <span><?= htmlspecialchars($h['label']) ?></span>
                                        <span style="color:<?= healthColor($h['score']) ?>"><?= healthLabel($h['score']) ?></span>
                                    </div>
                                    <div class="health-bar-track">
                                        <div class="health-bar-fill"
                                             data-target="<?= $h['score'] ?>"
                                             style="width:0%; background:<?= healthColor($h['score']) ?>"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="card fade-in delay-4">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            Quick Actions
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="#" class="quick-btn" onclick="openBookModal()">
                                <div class="quick-btn-icon bg-red-50 text-red-600"><i data-feather="calendar"></i></div>
                                <span class="quick-btn-label">Book Service</span>
                            </a>
                            <a href="https://wa.me/265993575111" target="_blank" class="quick-btn">
                                <div class="quick-btn-icon bg-green-50 text-green-600"><i data-feather="message-circle"></i></div>
                                <span class="quick-btn-label">WhatsApp Us</span>
                            </a>
                            <a href="tel:+265993575111" class="quick-btn">
                                <div class="quick-btn-icon bg-blue-50 text-blue-600"><i data-feather="phone"></i></div>
                                <span class="quick-btn-label">Call Now</span>
                            </a>
                            <a href="#" class="quick-btn" onclick="showPanel('history')">
                                <div class="quick-btn-icon bg-amber-50 text-amber-600"><i data-feather="file-text"></i></div>
                                <span class="quick-btn-label">Invoices</span>
                            </a>
                        </div>

                        <!-- Offer banner -->
                        <div class="offer-banner" style="margin-top:14px">
                            <div class="offer-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <div>
                                <div class="offer-text-title">15% Off Auto Detailing</div>
                                <div class="offer-text-sub">Member exclusive · Ends 31 March</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /right col -->
        </div><!-- /main grid -->

        <!-- Reminders preview row -->
        <div class="card fade-in delay-5">
            <div class="card-header">
                <div class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Maintenance Reminders
                    <?php if($urgent_count): ?><span class="status-pill bg-red-100 text-red-600"><?= $urgent_count ?> urgent</span><?php endif; ?>
                </div>
                <a class="card-action" onclick="showPanel('reminders')">View all</a>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($reminders as $r):
                        $kms_left = max(0, $r['due_km'] - $r['current_km']);
                        $pct = min(100, round(($r['current_km'] - ($r['due_km'] - 8000)) / 8000 * 100));
                        $pct = max(0, $pct);
                    ?>
                    <div class="reminder-item <?= urgencyClasses($r['urgency']) ?>">
                        <div class="reminder-icon <?= $r['urgency']==='urgent' ? 'bg-red-100 text-red-600' : ($r['urgency']==='soon' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500') ?>">
                            <i data-feather="<?= $r['icon'] ?>"></i>
                        </div>
                        <div class="reminder-body">
                            <div class="reminder-title"><?= htmlspecialchars($r['title']) ?></div>
                            <div class="reminder-sub">
                                <?= $kms_left > 0 ? number_format($kms_left).' km left' : 'Overdue' ?>
                                · <?= htmlspecialchars($r['interval']) ?>
                            </div>
                            <div class="km-bar-wrap">
                                <div class="km-bar" style="margin-top:6px">
                                    <div class="km-fill" style="width:<?= $pct ?>%; background:<?= $r['urgency']==='urgent' ? '#dc2626' : ($r['urgency']==='soon' ? '#f59e0b' : '#16a34a') ?>"></div>
                                </div>
                            </div>
                        </div>
                        <?= urgencyBadge($r['urgency']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div><!-- /panel-overview -->

    <!-- ─── BOOKINGS PANEL ─── -->
    <div id="panel-bookings" class="page-body" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px" class="fade-in">
            <div>
                <h1 style="font-size:22px;font-weight:900;color:#0f172a">My Bookings</h1>
                <p style="font-size:13px;color:#64748b;margin-top:2px">Manage all your upcoming and past service appointments</p>
            </div>
            <button onclick="openBookModal()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:11px 20px;border-radius:12px;font-size:14px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Booking
            </button>
        </div>

        <?php foreach ([['Upcoming', $upcoming_bookings], ['Past Services', $service_history]] as [$section, $items]): ?>
        <div class="card fade-in delay-1">
            <div class="card-header">
                <div class="card-title"><?= $section ?> <span style="font-size:12px;background:#f1f5f9;color:#64748b;padding:3px 8px;border-radius:99px;font-weight:600"><?= count($items) ?></span></div>
            </div>
            <div class="card-body">
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="border-bottom:2px solid #f1f5f9">
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Service</th>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Date & Time</th>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Technician</th>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Ref</th>
                                <?php if($section==='Past Services'): ?><th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Cost</th><?php endif; ?>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i): ?>
                            <tr style="border-bottom:1px solid #f8fafc;transition:background 0.15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                <td style="padding:12px;font-weight:600;color:#0f172a"><?= htmlspecialchars($i['service']) ?></td>
                                <td style="padding:12px;color:#64748b"><?= date('d M Y', strtotime($i['date'])) ?><?= isset($i['time']) ? ' · '.date('g:i A', strtotime($i['time'])) : '' ?></td>
                                <td style="padding:12px;color:#64748b"><?= htmlspecialchars($i['tech']) ?></td>
                                <td style="padding:12px;font-family:monospace;color:#94a3b8;font-size:12px"><?= htmlspecialchars($i['id']) ?></td>
                                <?php if($section==='Past Services'): ?><td style="padding:12px;font-weight:700;color:#dc2626"><?= htmlspecialchars($i['cost']) ?></td><?php endif; ?>
                                <td style="padding:12px"><?= statusPill($i['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ─── HISTORY PANEL ─── -->
    <div id="panel-history" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Service History</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Complete record of all services performed on your <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?></p>
        </div>

        <!-- Total spend stat -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 fade-in delay-1">
            <?php
            $total_services = count($service_history);
            $most_recent = $service_history[0]['date'];
            ?>
            <div class="stat-card red"><div class="stat-icon red"><i data-feather="tool"></i></div><div><div class="stat-value"><?= $total_services ?></div><div class="stat-label">Total Services</div></div></div>
            <div class="stat-card green"><div class="stat-icon green"><i data-feather="dollar-sign"></i></div><div><div class="stat-value" style="font-size:20px">MWK 125.5K</div><div class="stat-label">Total Invested</div></div></div>
            <div class="stat-card blue"><div class="stat-icon blue"><i data-feather="calendar"></i></div><div><div class="stat-value" style="font-size:18px"><?= date('M Y', strtotime($most_recent)) ?></div><div class="stat-label">Last Service</div></div></div>
            <div class="stat-card amber"><div class="stat-icon amber"><i data-feather="star"></i></div><div><div class="stat-value">5.0</div><div class="stat-label">Avg. Rating</div></div></div>
        </div>

        <div class="card fade-in delay-2">
            <div class="card-header"><div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 0 .5-4.5"/><polyline points="3 2 3 8 9 8"/></svg>Full Timeline</div></div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($service_history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-card">
                            <div class="timeline-card-header">
                                <div class="timeline-service"><?= htmlspecialchars($h['service']) ?></div>
                                <div class="timeline-cost"><?= htmlspecialchars($h['cost']) ?></div>
                            </div>
                            <div class="timeline-meta">
                                <?= date('d M Y', strtotime($h['date'])) ?> · <?= htmlspecialchars($h['tech']) ?> · <span style="font-family:monospace"><?= htmlspecialchars($h['id']) ?></span>
                            </div>
                            <div class="timeline-notes"><?= htmlspecialchars($h['notes']) ?></div>
                            <div style="margin-top:10px;display:flex;gap:8px">
                                <button style="font-size:12px;font-weight:600;color:#dc2626;background:none;border:1px solid #fecaca;padding:4px 12px;border-radius:8px;cursor:pointer;font-family:'Inter',sans-serif">Download Invoice</button>
                                <button style="font-size:12px;font-weight:600;color:#64748b;background:none;border:1px solid #e2e8f0;padding:4px 12px;border-radius:8px;cursor:pointer;font-family:'Inter',sans-serif">Leave Review</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── REMINDERS PANEL ─── -->
    <div id="panel-reminders" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Maintenance Reminders</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Stay on top of your <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?>'s maintenance schedule</p>
        </div>

        <?php if($urgent_count): ?>
        <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:12px" class="fade-in delay-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <div style="font-size:14px;font-weight:800;color:#dc2626"><?= $urgent_count ?> item<?= $urgent_count>1?'s':'' ?> need immediate attention</div>
                <div style="font-size:13px;color:#ef4444;margin-top:2px">Book a service now to prevent damage and keep your warranty valid.</div>
            </div>
            <button onclick="openBookModal()" style="margin-left:auto;background:#dc2626;color:#fff;border:none;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer;white-space:nowrap">Book Now</button>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 fade-in delay-2">
            <?php foreach ($reminders as $r):
                $kms_left = max(0, $r['due_km'] - $r['current_km']);
                $pct = min(100, round(($r['current_km'] - ($r['due_km'] - 10000)) / 10000 * 100));
                $pct = max(0, $pct);
                $bar_color = $r['urgency']==='urgent' ? '#dc2626' : ($r['urgency']==='soon' ? '#f59e0b' : '#16a34a');
            ?>
            <div class="card" style="<?= $r['urgency']==='urgent' ? 'border:1.5px solid #fecaca;' : ($r['urgency']==='soon' ? 'border:1.5px solid #fde68a;' : '') ?>">
                <div class="card-body" style="padding:20px">
                    <div style="display:flex;align-items:flex-start;gap:14px">
                        <div style="width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?= $r['urgency']==='urgent' ? '#fef2f2' : ($r['urgency']==='soon' ? '#fffbeb' : '#f0fdf4') ?>;color:<?= $bar_color ?>">
                            <i data-feather="<?= $r['icon'] ?>"></i>
                        </div>
                        <div style="flex:1">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                                <div style="font-size:15px;font-weight:800;color:#0f172a"><?= htmlspecialchars($r['title']) ?></div>
                                <?= urgencyBadge($r['urgency']) ?>
                            </div>
                            <div style="font-size:12px;color:#64748b;margin-bottom:10px">
                                <?= htmlspecialchars($r['interval']) ?> ·
                                <?= $kms_left > 0 ? '<strong style="color:'.htmlspecialchars($bar_color).'">'.number_format($kms_left).' km remaining</strong>' : '<strong style="color:#dc2626">Overdue</strong>' ?>
                            </div>
                            <div style="background:#f1f5f9;border-radius:99px;height:8px;overflow:hidden;margin-bottom:6px">
                                <div style="width:<?= $pct ?>%;height:100%;background:<?= htmlspecialchars($bar_color) ?>;border-radius:99px;transition:width 1.2s"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:11px;color:#94a3b8">
                                <span>Last: <?= number_format($r['due_km'] - 8000) ?> km</span>
                                <span>Due: <?= number_format($r['due_km']) ?> km</span>
                            </div>
                        </div>
                    </div>
                    <?php if($r['urgency'] !== 'ok'): ?>
                    <button onclick="openBookModal()" style="width:100%;margin-top:14px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:10px;border-radius:10px;font-size:13px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer">
                        Schedule Now →
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ─── VEHICLE PANEL ─── -->
    <div id="panel-vehicle" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">My Vehicle</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">All details about your registered vehicle</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="vehicle-card fade-in delay-1" style="border-radius:16px">
                <div class="vehicle-make"><?= htmlspecialchars($vehicle['make']) ?></div>
                <div class="vehicle-name"><?= htmlspecialchars($vehicle['model']) ?></div>
                <div class="vehicle-year"><?= htmlspecialchars($vehicle['year'].' · '.$vehicle['color']) ?></div>
                <div class="vehicle-reg"><?= htmlspecialchars($vehicle['registration']) ?></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px">
                    <?php foreach ([
                        ['Engine',       $vehicle['engine']],
                        ['Transmission', $vehicle['transmission']],
                        ['Mileage',      number_format($vehicle['mileage']).' km'],
                        ['Last Service', number_format($vehicle['last_service_mileage']).' km'],
                        ['Next Service', number_format($vehicle['next_service_mileage']).' km'],
                        ['Registration', $vehicle['registration']],
                    ] as [$lbl,$val]): ?>
                    <div>
                        <div class="vehicle-spec-label"><?= $lbl ?></div>
                        <div class="vehicle-spec-value"><?= htmlspecialchars($val) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mileage-section">
                    <div class="mileage-label"><span>Service interval</span><span><?= $service_pct ?>%</span></div>
                    <div class="mileage-bar">
                        <div class="mileage-fill" style="width:<?= $service_pct ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="card fade-in delay-2">
                    <div class="card-header"><div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Update Vehicle</div></div>
                    <div class="card-body">
                        <p style="font-size:13px;color:#64748b;margin-bottom:14px">Keep your vehicle information up to date for accurate service recommendations.</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                            <?php foreach (['Make'=>$vehicle['make'],'Model'=>$vehicle['model'],'Year'=>$vehicle['year'],'Mileage'=>$vehicle['mileage']] as $k=>$v): ?>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:4px"><?= $k ?></label>
                                <input type="text" value="<?= htmlspecialchars($v) ?>" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-family:'Inter',sans-serif;outline:none;color:#0f172a">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button style="margin-top:14px;width:100%;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px;border-radius:10px;font-size:14px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer">Save Changes</button>
                    </div>
                </div>

                <div class="card fade-in delay-3">
                    <div class="card-body" style="padding:20px">
                        <div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:12px">Add Another Vehicle</div>
                        <p style="font-size:12px;color:#64748b;margin-bottom:12px">Business account? Add up to 5 vehicles for fleet management.</p>
                        <button style="width:100%;border:1.5px dashed #e2e8f0;background:none;padding:14px;border-radius:12px;font-size:13px;font-weight:600;color:#94a3b8;cursor:pointer;font-family:'Inter',sans-serif;transition:all 0.2s" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#94a3b8'">
                            + Add Vehicle
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── HEALTH PANEL ─── -->
    <div id="panel-health" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Vehicle Health Report</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Based on your last inspection on <?= date('d M Y', strtotime($service_history[0]['date'])) ?></p>
        </div>

        <!-- Overall score -->
        <div class="card fade-in delay-1" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff">
            <div class="card-body" style="padding:28px;display:flex;align-items:center;gap:28px;flex-wrap:wrap">
                <div class="health-ring" style="flex-shrink:0">
                    <svg width="140" height="140" viewBox="0 0 140 140">
                        <circle cx="70" cy="70" r="56" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="12"/>
                        <circle cx="70" cy="70" r="56" fill="none" stroke="<?= healthColor($overall_health) ?>" stroke-width="12" stroke-linecap="round"
                                stroke-dasharray="<?= round(2*pi()*56) ?>"
                                stroke-dashoffset="<?= round(2*pi()*56 * (1 - $overall_health/100)) ?>"
                                style="transform:rotate(-90deg);transform-origin:70px 70px;transition:stroke-dashoffset 1.2s"/>
                    </svg>
                    <div class="health-ring-inner">
                        <div class="health-pct" style="color:<?= healthColor($overall_health) ?>"><?= $overall_health ?>%</div>
                        <div class="health-sublabel" style="color:#94a3b8">Overall</div>
                    </div>
                </div>
                <div>
                    <div style="font-size:28px;font-weight:900;color:#fff;margin-bottom:6px">
                        <?= $overall_health >= 80 ? '✅ Your car is in good shape' : ($overall_health >= 55 ? '⚠️ Some items need attention' : '🚨 Immediate service recommended') ?>
                    </div>
                    <div style="font-size:14px;color:#94a3b8;margin-bottom:16px">
                        <?= $overall_health >= 80 ? 'Keep up with routine maintenance to stay in top condition.' : 'We recommend booking a service to address flagged items.' ?>
                    </div>
                    <button onclick="openBookModal()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.4)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Book Full Inspection
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 fade-in delay-2">
            <?php foreach ($health_items as $h): ?>
            <div class="card">
                <div class="card-body" style="padding:20px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:<?= $h['score']>=80 ? '#f0fdf4' : ($h['score']>=55 ? '#fffbeb' : '#fef2f2') ?>;color:<?= healthColor($h['score']) ?>">
                                <i data-feather="<?= $h['icon'] ?>"></i>
                            </div>
                            <div style="font-size:15px;font-weight:800;color:#0f172a"><?= htmlspecialchars($h['label']) ?></div>
                        </div>
                        <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;background:<?= $h['score']>=80 ? '#f0fdf4' : ($h['score']>=55 ? '#fffbeb' : '#fef2f2') ?>;color:<?= healthColor($h['score']) ?>">
                            <?= healthLabel($h['score']) ?>
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <div style="font-size:36px;font-weight:900;color:<?= healthColor($h['score']) ?>;line-height:1"><?= $h['score'] ?>%</div>
                    </div>
                    <div style="background:#f1f5f9;border-radius:99px;height:8px;overflow:hidden">
                        <div style="width:<?= $h['score'] ?>%;height:100%;background:<?= healthColor($h['score']) ?>;border-radius:99px;transition:width 1.2s cubic-bezier(0.4,0,0.2,1)"></div>
                    </div>
                    <?php if($h['score'] < 70): ?>
                    <button onclick="openBookModal()" style="width:100%;margin-top:12px;background:none;border:1.5px solid #fecaca;color:#dc2626;padding:8px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif">Book Service →</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ─── NOTIFICATIONS PANEL ─── -->
    <div id="panel-notifications" class="page-body" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between" class="fade-in">
            <div>
                <h1 style="font-size:22px;font-weight:900;color:#0f172a">Notifications</h1>
                <p style="font-size:13px;color:#64748b;margin-top:2px"><?= $unread_count ?> unread notification<?= $unread_count!==1?'s':'' ?></p>
            </div>
            <button style="font-size:13px;font-weight:600;color:#dc2626;background:none;border:1px solid #fecaca;padding:8px 16px;border-radius:10px;cursor:pointer;font-family:'Inter',sans-serif">Mark all read</button>
        </div>

        <div class="card fade-in delay-1">
            <div class="card-body">
                <?php foreach ($notifications as $n): ?>
                <div class="notif-item" style="<?= !$n['read'] ? 'background:linear-gradient(90deg,rgba(220,38,38,0.03),transparent);' : 'opacity:0.7' ?>">
                    <?= notifIcon($n['type']) ?>
                    <div class="notif-body">
                        <div class="notif-title">
                            <?= htmlspecialchars($n['title']) ?>
                            <?php if(!$n['read']): ?><span class="notif-unread"></span><?php endif; ?>
                        </div>
                        <div class="notif-msg"><?= htmlspecialchars($n['msg']) ?></div>
                    </div>
                    <div class="notif-time"><?= htmlspecialchars($n['time']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Notification preferences -->
        <div class="card fade-in delay-2">
            <div class="card-header"><div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Preferences</div></div>
            <div class="card-body">
                <p style="font-size:13px;color:#64748b;margin-bottom:16px">Choose how you'd like to be notified.</p>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <?php foreach ([
                        ['SMS reminders & booking confirmations', true],
                        ['Email updates and service reports', true],
                        ['Maintenance due alerts', true],
                        ['Promotional offers and discounts', false],
                    ] as [$label, $checked]): ?>
                    <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:12px">
                        <span style="font-size:14px;font-weight:500;color:#374151"><?= htmlspecialchars($label) ?></span>
                        <input type="checkbox" <?= $checked ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#dc2626;cursor:pointer">
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── SETTINGS PANEL ─── -->
    <div id="panel-settings" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Account Settings</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Manage your profile, security, and preferences</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Profile -->
            <div class="lg:col-span-2 flex flex-col gap-6">
                <div class="card fade-in delay-1">
                    <div class="card-header"><div class="card-title"><i data-feather="user" style="width:17px;height:17px;color:#dc2626;margin-right:4px"></i>Profile Information</div></div>
                    <div class="card-body">
                        <!-- Avatar row -->
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px;padding-bottom:22px;border-bottom:1px solid #f1f5f9">
                            <div style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;flex-shrink:0"><?= htmlspecialchars($user['avatar_initials']) ?></div>
                            <div>
                                <div style="font-size:18px;font-weight:800;color:#0f172a"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
                                <div style="font-size:13px;color:#64748b"><?= htmlspecialchars($user['email']) ?></div>
                                <div style="margin-top:8px;display:flex;gap:8px">
                                    <button style="font-size:12px;font-weight:600;background:none;border:1px solid #e2e8f0;padding:5px 12px;border-radius:8px;cursor:pointer;color:#374151;font-family:'Inter',sans-serif">Change Photo</button>
                                    <span style="background:#f0fdf4;color:#15803d;font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;display:flex;align-items:center;gap:4px">
                                        ✓ Verified Member
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                            <?php foreach ([
                                ['First Name','text',$user['first_name']],
                                ['Last Name','text',$user['last_name']],
                                ['Email Address','email',$user['email']],
                                ['Phone Number','tel',$user['phone']],
                                ['City','text',$user['city']],
                                ['Member Type','text',$user['type']],
                            ] as [$label,$type,$value]): ?>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px"><?= $label ?></label>
                                <input type="<?= $type ?>" value="<?= htmlspecialchars($value) ?>" style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:'Inter',sans-serif;color:#0f172a;outline:none;transition:border-color 0.2s" onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e2e8f0'">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:16px;display:flex;gap:10px">
                            <button style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 24px;border-radius:11px;font-size:14px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer">Save Profile</button>
                            <button style="background:none;border:1.5px solid #e2e8f0;padding:12px 24px;border-radius:11px;font-size:14px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;color:#374151">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card fade-in delay-2">
                    <div class="card-header"><div class="card-title"><i data-feather="lock" style="width:17px;height:17px;color:#dc2626;margin-right:4px"></i>Change Password</div></div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:1fr;gap:12px;max-width:400px">
                            <?php foreach (['Current Password','New Password','Confirm New Password'] as $f): ?>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px"><?= $f ?></label>
                                <input type="password" placeholder="••••••••" style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:'Inter',sans-serif;color:#0f172a;outline:none" onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e2e8f0'">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button style="margin-top:14px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:11px 22px;border-radius:11px;font-size:14px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer">Update Password</button>
                    </div>
                </div>
            </div>

            <!-- Right col: member card + danger -->
            <div class="flex flex-col gap-4">
                <!-- Member card -->
                <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:18px;padding:24px;color:#fff;position:relative;overflow:hidden" class="fade-in delay-1">
                    <div style="position:absolute;right:-20px;bottom:-20px;font-size:100px;opacity:0.06">🏆</div>
                    <div style="font-size:10px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:0.12em;margin-bottom:10px">Member Card</div>
                    <div style="font-size:20px;font-weight:900;color:#fff;margin-bottom:2px"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
                    <div style="font-size:12px;color:#64748b;margin-bottom:16px"><?= htmlspecialchars($user['type']) ?></div>
                    <div style="font-size:11px;color:#475569;margin-bottom:4px">MEMBER SINCE</div>
                    <div style="font-size:15px;font-weight:700;color:#e2e8f0"><?= date('d M Y', strtotime($user['member_since'])) ?></div>
                    <div style="margin-top:14px;display:flex;align-items:center;gap:6px">
                        <span class="live-dot"></span>
                        <span style="font-size:11px;color:#22c55e;font-weight:600">Active Member</span>
                    </div>
                </div>

                <!-- Stats -->
                <div class="card fade-in delay-2">
                    <div class="card-body" style="padding:20px">
                        <div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:14px">Account Summary</div>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <?php foreach ([
                                ['Services Booked', count($service_history)],
                                ['Member Points',   '1,200 pts'],
                                ['Savings Earned',  'MWK 8,000'],
                            ] as [$k,$v]): ?>
                            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9">
                                <span style="font-size:13px;color:#64748b"><?= $k ?></span>
                                <span style="font-size:13px;font-weight:800;color:#0f172a"><?= $v ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Danger zone -->
                <div class="card fade-in delay-3" style="border:1.5px solid #fecaca">
                    <div class="card-body" style="padding:20px">
                        <div style="font-size:13px;font-weight:700;color:#dc2626;margin-bottom:8px">⚠ Danger Zone</div>
                        <p style="font-size:12px;color:#64748b;margin-bottom:12px">These actions are irreversible. Please be careful.</p>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <button style="width:100%;background:none;border:1.5px solid #fecaca;color:#dc2626;padding:10px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif">Deactivate Account</button>
                            <a href="/pages/logout.php" style="display:block;text-align:center;background:none;border:1.5px solid #e2e8f0;color:#64748b;padding:10px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /main -->

<!-- ═══ Book Service Modal ═══ -->
<div class="modal-overlay" id="bookModal">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div class="modal-title">Book a Service</div>
            <button onclick="closeBookModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-sub">Schedule an appointment with our expert team.</div>

        <div class="modal-field">
            <label>Service Required *</label>
            <select>
                <option>General Maintenance</option>
                <option>Engine Repair</option>
                <option>Auto Detailing</option>
                <option>Brake Services</option>
                <option>AC Repair</option>
                <option>Parts Supply</option>
                <option>Tyre Services</option>
                <option>Battery Services</option>
                <option>Other</option>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="modal-field">
                <label>Preferred Date *</label>
                <input type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="modal-field">
                <label>Preferred Time *</label>
                <select>
                    <option>08:00 AM</option><option>09:00 AM</option><option>10:00 AM</option>
                    <option>11:00 AM</option><option>12:00 PM</option><option>01:00 PM</option>
                    <option>02:00 PM</option><option>03:00 PM</option><option>04:00 PM</option>
                </select>
            </div>
        </div>
        <div class="modal-field">
            <label>Additional Notes</label>
            <textarea rows="3" placeholder="Describe any specific issues or concerns…"></textarea>
        </div>

        <!-- Pre-filled vehicle info -->
        <div style="background:#f8fafc;border-radius:12px;padding:12px 14px;font-size:13px;color:#64748b;margin-bottom:4px">
            <strong style="color:#0f172a">Vehicle:</strong> <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model'].' ('.$vehicle['registration'].')') ?>
        </div>

        <div class="modal-actions">
            <button class="btn-modal-ghost" onclick="closeBookModal()">Cancel</button>
            <button class="btn-modal-primary" onclick="submitBooking()">
                Confirm Booking
            </button>
        </div>
    </div>
</div>

<script>
// ── Sidebar ──────────────────────────────────────────────────
let sidebarCollapsed = false;

function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const main = document.getElementById('main');
    sidebarCollapsed = !sidebarCollapsed;
    sb.classList.toggle('collapsed', sidebarCollapsed);
    main.classList.toggle('expanded', sidebarCollapsed);
}

function openSidebar() {
    document.getElementById('sidebar').classList.add('mobile-open');
    document.getElementById('sidebarOverlay').classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('sidebarOverlay').classList.add('hidden');
}

// ── Panel navigation ─────────────────────────────────────────
function showPanel(name) {
    // Hide all panels
    document.querySelectorAll('[id^="panel-"]').forEach(p => p.style.display = 'none');
    // Show target
    const panel = document.getElementById('panel-' + name);
    if (panel) panel.style.display = 'flex';

    // Update nav active state
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const target = Array.from(document.querySelectorAll('.nav-item'))
        .find(el => el.getAttribute('onclick') && el.getAttribute('onclick').includes("'"+name+"'"));
    if (target) target.classList.add('active');

    // On mobile, close sidebar
    if (window.innerWidth < 1024) closeSidebar();

    // Re-initialise feather icons and bars in the new panel
    feather.replace();
    initBars();
}

// ── Animate bars & fills ─────────────────────────────────────
function initBars() {
    // Health bars
    document.querySelectorAll('.health-bar-fill[data-target]').forEach(el => {
        setTimeout(() => { el.style.width = el.dataset.target + '%'; }, 100);
    });
    // Mileage fill
    document.querySelectorAll('.mileage-fill[data-target]').forEach(el => {
        setTimeout(() => { el.style.width = el.dataset.target + '%'; }, 100);
    });
}

// ── Book modal ───────────────────────────────────────────────
function openBookModal()  { document.getElementById('bookModal').classList.add('active'); }
function closeBookModal() { document.getElementById('bookModal').classList.remove('active'); }
document.getElementById('bookModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookModal();
});
function submitBooking() {
    closeBookModal();
    // Show a toast
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#16a34a;color:#fff;padding:14px 24px;border-radius:14px;font-size:14px;font-weight:700;font-family:Inter,sans-serif;z-index:999;box-shadow:0 8px 24px rgba(0,0,0,0.2);display:flex;align-items:center;gap:10px;animation:fadeInUp 0.4s ease';
    toast.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Booking request submitted! We\'ll confirm shortly.';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();

    // Open the panel requested via ?panel= URL param (PHP-injected)
    const startPanel = <?= json_encode($initial_panel) ?>;
    showPanel(startPanel);

    // Animate mileage fill on load (only present in overview/vehicle panels)
    const fill = document.getElementById('mileageFill');
    if (fill) setTimeout(() => { fill.style.width = (fill.dataset.target || 0) + '%'; }, 400);
});
</script>
</body>
</html>
