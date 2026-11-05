<?php
/**
 * admin/statements/review.php — Statement-import reconciliation queue.
 * Lists every imported invoice that needs attention (missing/placeholder number,
 * estimated date, auto-suffixed duplicate, part-payment, balancing entry) and lets
 * the accountant fix the common cases inline, or jump straight to the invoice editor.
 */
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
if (!isset($_SESSION['admin_id'])) { header("Location: $base/admin/login.php"); exit; }
require_once __DIR__ . '/../../includes/db_connection.php';

// Table may not exist yet (no import run) — fail soft.
$have_table = false;
try { $have_table = (bool)$conn->query("SHOW TABLES LIKE 'import_reviews'")->num_rows; }
catch (\Throwable $e) { $have_table = false; }

$fmt = function ($n) { return 'MWK ' . number_format((float)$n, 2); };

$REASONS = [
    'placeholder_invno' => ['Missing invoice number', 'The statement row had no invoice number.'],
    'dup_suffixed'      => ['Duplicate number', 'This number was already used, so it was auto-suffixed.'],
    'date_fallback'     => ['Estimated date', 'The date was blank/garbled, defaulted to 31 May 2025.'],
    'partial'           => ['Possible part-payment', 'The statement shows an amount already paid.'],
    'balancing_entry'   => ['Balancing entry', 'A single entry stands in for the tab’s stated total.'],
];

$show = ($_GET['show'] ?? 'open') === 'all' ? 'all' : 'open';
$sev  = in_array($_GET['sev'] ?? '', ['high','medium','low'], true) ? $_GET['sev'] : '';

$groups = [];
$counts = ['high'=>0,'medium'=>0,'low'=>0];
$total_open = 0; $total_resolved = 0;

if ($have_table) {
    $cr = $conn->query("SELECT severity, COUNT(*) n FROM import_reviews WHERE resolved=0 GROUP BY severity");
    while ($row = $cr->fetch_assoc()) { $counts[$row['severity']] = (int)$row['n']; }
    $total_open = $counts['high'] + $counts['medium'] + $counts['low'];
    $rr = $conn->query("SELECT COUNT(*) n FROM import_reviews WHERE resolved=1");
    $total_resolved = (int)$rr->fetch_assoc()['n'];

    $where = $show === 'open' ? "r.resolved = 0" : "1=1";
    if ($sev) $where .= " AND r.severity = '" . $sev . "'";   // $sev whitelisted above
    $sql = "SELECT r.id review_id, r.invoice_id, r.reason, r.severity, r.detail, r.resolved,
                   i.invoice_number, i.customer_name, i.vehicle_info, i.total, i.issued_at, i.payment_status,
                   c.name company
            FROM import_reviews r
            JOIN invoices i ON i.id = r.invoice_id
            LEFT JOIN insurance_companies c ON c.id = i.insurance_company_id
            WHERE $where
            ORDER BY FIELD(r.severity,'high','medium','low'), c.name, i.invoice_number, r.id";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $iid = (int)$row['invoice_id'];
        if (!isset($groups[$iid])) {
            $groups[$iid] = [
                'invoice_id'   => $iid,
                'invoice_number'=> $row['invoice_number'],
                'company'      => $row['company'] ?: 'Others',
                'customer_name'=> $row['customer_name'],
                'vehicle_info' => $row['vehicle_info'],
                'total'        => $row['total'],
                'issued_at'    => $row['issued_at'],
                'items'        => [],
            ];
        }
        $groups[$iid]['items'][] = [
            'review_id'=>(int)$row['review_id'], 'reason'=>$row['reason'],
            'severity'=>$row['severity'], 'detail'=>$row['detail'], 'resolved'=>(int)$row['resolved'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reconcile imports · Wiyule Admin</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sora', system-ui, sans-serif; background: #f8fafc; color: #0f172a; line-height: 1.5; }
    .topbar { background: #0f172a; color: #fff; padding: 14px 28px; display: flex; align-items: center; gap: 16px; position: sticky; top: 0; z-index: 20; }
    .topbar a { color: #fff; text-decoration: none; opacity: 0.8; font-size: 13px; }
    .topbar a:hover { opacity: 1; }
    .topbar h1 { font-size: 16px; font-weight: 700; margin-right: auto; }
    .container { max-width: 1120px; margin: 0 auto; padding: 26px; }
    .page-head h2 { font-size: 23px; font-weight: 800; }
    .page-head .sub { font-size: 13px; color: #64748b; margin-top: 4px; max-width: 70ch; }

    .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 22px 0; }
    .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; border-top: 3px solid #e2e8f0; }
    .stat.high { border-top-color: #dc2626; } .stat.med { border-top-color: #d97706; }
    .stat.low { border-top-color: #64748b; } .stat.done { border-top-color: #16a34a; }
    .stat .k { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; }
    .stat .v { font-size: 26px; font-weight: 800; margin-top: 4px; font-variant-numeric: tabular-nums; }
    .stat.done .v { color: #16a34a; }

    .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .pill { padding: 6px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 600; background: #f1f5f9; color: #64748b; text-decoration: none; border: 1.5px solid transparent; }
    .pill:hover { background: #e2e8f0; color: #0f172a; }
    .pill.active { background: #0f172a; color: #fff; }
    .toolbar .spacer { margin-left: auto; }

    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.05); margin-bottom: 12px; display: grid; grid-template-columns: 1.3fr 1.4fr auto; gap: 18px; align-items: start; }
    .card.gone { opacity: 0; transform: translateY(-6px); transition: opacity .3s, transform .3s; }
    .ci-name { font-weight: 700; font-size: 14.5px; }
    .ci-num { font-family: ui-monospace, Menlo, monospace; font-size: 12px; color: #475569; margin-top: 2px; }
    .ci-meta { font-size: 12px; color: #94a3b8; margin-top: 6px; line-height: 1.45; }
    .ci-amt { font-weight: 700; font-variant-numeric: tabular-nums; margin-top: 6px; }

    .issues { display: flex; flex-direction: column; gap: 8px; }
    .issue { display: flex; gap: 8px; align-items: flex-start; }
    .badge { flex: none; font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
    .badge.high { background: #fee2e2; color: #991b1b; } .badge.medium { background: #fef3c7; color: #92400e; } .badge.low { background: #e2e8f0; color: #475569; }
    .issue .txt { font-size: 12.5px; color: #475569; }

    .fixbox { display: flex; flex-direction: column; gap: 8px; min-width: 210px; }
    .fixrow { display: flex; gap: 6px; }
    .fixrow input { flex: 1; padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-family: 'Sora', sans-serif; font-size: 13px; outline: none; }
    .fixrow input:focus { border-color: #dc2626; }
    .btn { padding: 8px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; font-family: 'Sora', sans-serif; border: 1.5px solid transparent; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; justify-content: center; }
    .btn.dark { background: #0f172a; color: #fff; } .btn.dark:hover { background: #1e293b; }
    .btn.ghost { background: #fff; color: #334155; border-color: #e2e8f0; } .btn.ghost:hover { border-color: #cbd5e1; }
    .btn.ok { background: #16a34a; color: #fff; } .btn.ok:hover { background: #15803d; }
    .fixrow .btn { flex: none; }
    .fix-actions { display: flex; gap: 6px; }
    .fix-actions .btn { flex: 1; }

    .empty { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 48px 24px; text-align: center; color: #64748b; }
    .empty .big { font-size: 20px; font-weight: 700; color: #16a34a; margin-bottom: 6px; }

    #toast { position: fixed; bottom: 22px; left: 50%; transform: translateX(-50%) translateY(20px); background: #0f172a; color: #fff; padding: 11px 20px; border-radius: 999px; font-size: 13px; font-weight: 600; opacity: 0; transition: opacity .25s, transform .25s; z-index: 50; box-shadow: 0 8px 24px rgba(0,0,0,.2); }
    #toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast.err { background: #b91c1c; }

    @media (max-width: 820px) {
        .card { grid-template-columns: 1fr; }
        .stat-row { grid-template-columns: repeat(2, 1fr); }
    }
</style>
</head>
<body>
<div class="topbar">
    <a href="<?= $base ?>/admin/statements/">← Statements</a>
    <h1>Reconcile imported statements</h1>
</div>
<div class="container">
    <div class="page-head">
        <h2>Reconcile imported statements</h2>
        <div class="sub">Items below were brought in from the May 2025 statement file but need a human eye — a missing invoice number, an estimated date, a duplicate, or a part-payment. Fix the quick ones right here, or open the full invoice to edit everything.</div>
    </div>

    <div class="stat-row">
        <div class="stat high"><div class="k">Needs attention</div><div class="v"><?= (int)$counts['high'] ?></div></div>
        <div class="stat med"><div class="k">Worth a check</div><div class="v"><?= (int)$counts['medium'] ?></div></div>
        <div class="stat low"><div class="k">Auto-handled</div><div class="v"><?= (int)$counts['low'] ?></div></div>
        <div class="stat done"><div class="k">Reconciled</div><div class="v" id="doneCount"><?= (int)$total_resolved ?></div></div>
    </div>

    <?php if (!$have_table): ?>
        <div class="empty"><div class="big">Nothing to reconcile</div>No statement import has been loaded yet.</div>
    <?php else: ?>
    <div class="toolbar">
        <?php
        $mk = function($label, $k, $v) use ($base, $show, $sev) {
            $q = ['show'=>$show, 'sev'=>$sev]; $q[$k] = $v;
            $q = array_filter($q, fn($x)=>$x!=='' && $x!==null);
            $active = ($k==='sev' ? ($sev===$v) : ($show===$v));
            if ($k==='sev' && $v==='' && $sev==='') $active = true;
            return '<a class="pill '.($active?'active':'').'" href="'.$base.'/admin/statements/review.php?'.http_build_query($q).'">'.$label.'</a>';
        };
        echo $mk('All issues','sev','');
        echo $mk('Needs attention','sev','high');
        echo $mk('Worth a check','sev','medium');
        echo $mk('Auto-handled','sev','low');
        ?>
        <span class="spacer"></span>
        <?= $mk($show==='open'?'Showing: open':'Showing: all','show',$show==='open'?'all':'open') ?>
    </div>

    <div id="list">
    <?php if (empty($groups)): ?>
        <div class="empty"><div class="big">✓ All clear</div>Nothing matches this filter — everything here has been reconciled.</div>
    <?php else: foreach ($groups as $g):
        $reasons = array_column($g['items'], 'reason');
        $need_num  = array_intersect(['placeholder_invno','dup_suffixed'], $reasons);
        $need_date = in_array('date_fallback', $reasons, true);
        $issued_d  = $g['issued_at'] ? date('Y-m-d', strtotime($g['issued_at'])) : '';
    ?>
        <div class="card" id="card-<?= $g['invoice_id'] ?>" data-id="<?= $g['invoice_id'] ?>">
            <div>
                <div class="ci-name"><?= htmlspecialchars($g['company']) ?></div>
                <div class="ci-num">#<?= htmlspecialchars($g['invoice_number']) ?></div>
                <div class="ci-meta"><?= htmlspecialchars($g['customer_name'] ?: '—') ?><?= $g['vehicle_info'] ? ' · '.htmlspecialchars($g['vehicle_info']) : '' ?></div>
                <div class="ci-amt"><?= $fmt($g['total']) ?></div>
            </div>
            <div class="issues">
                <?php foreach ($g['items'] as $it): [$lbl,] = $REASONS[$it['reason']] ?? [$it['reason'],'']; ?>
                    <div class="issue">
                        <span class="badge <?= htmlspecialchars($it['severity']) ?>"><?= htmlspecialchars($lbl) ?></span>
                        <span class="txt"><?= htmlspecialchars($it['detail']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="fixbox">
                <?php if ($need_num): ?>
                <div class="fixrow">
                    <input type="text" maxlength="20" value="<?= htmlspecialchars($g['invoice_number']) ?>" id="num-<?= $g['invoice_id'] ?>" aria-label="Invoice number">
                    <button class="btn dark" onclick="saveNumber(<?= $g['invoice_id'] ?>)">Save #</button>
                </div>
                <?php endif; ?>
                <?php if ($need_date): ?>
                <div class="fixrow">
                    <input type="date" value="<?= htmlspecialchars($issued_d) ?>" id="date-<?= $g['invoice_id'] ?>" aria-label="Invoice date">
                    <button class="btn dark" onclick="saveDate(<?= $g['invoice_id'] ?>)">Save date</button>
                </div>
                <?php endif; ?>
                <div class="fix-actions">
                    <a class="btn ghost" href="<?= $base ?>/admin/invoices/edit.php?id=<?= $g['invoice_id'] ?>" target="_blank">Open invoice ↗</a>
                    <button class="btn ok" onclick="resolveInvoice(<?= $g['invoice_id'] ?>)">Mark reconciled</button>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>
    <?php endif; ?>
</div>

<div id="toast"></div>
<script>
const BASE = <?= json_encode($base) ?>;
let toastT;
function toast(msg, isErr) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'show' + (isErr ? ' err' : '');
    clearTimeout(toastT); toastT = setTimeout(() => t.className = '', 2600);
}
async function post(data) {
    const r = await fetch(BASE + '/admin/statements/review-api.php', {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    });
    return r.json();
}
function updateDone(res) {
    if (res && typeof res.resolved_count === 'number') {
        const dc = document.getElementById('doneCount');
        if (dc) dc.textContent = res.resolved_count;
    }
}
// Replace a just-saved fix control with a green "Saved" marker (keeps any other control in place).
function markSaved(rowEl) {
    if (!rowEl) return;
    rowEl.innerHTML = '<span class="btn ok" style="cursor:default;width:100%">✓ Saved</span>';
}
function afterFix(id, rowEl, res) {
    updateDone(res);
    markSaved(rowEl);
    if (res.invoice_open === 0) dropCard(id);   // nothing left on this invoice → clear it
}
async function saveNumber(id) {
    const input = document.getElementById('num-' + id);
    const v = input.value.trim();
    const res = await post({action: 'set_number', invoice_id: id, invoice_number: v});
    if (res.success) { toast(res.message); afterFix(id, input.closest('.fixrow'), res); }
    else { toast(res.message, true); }
}
async function saveDate(id) {
    const input = document.getElementById('date-' + id);
    const res = await post({action: 'set_date', invoice_id: id, issued_at: input.value});
    if (res.success) { toast(res.message); afterFix(id, input.closest('.fixrow'), res); }
    else { toast(res.message, true); }
}
async function resolveInvoice(id) {
    const res = await post({action: 'resolve', invoice_id: id});
    if (res.success) { toast(res.message); updateDone(res); dropCard(id); }
    else { toast(res.message, true); }
}
function dropCard(id) {
    const c = document.getElementById('card-' + id);
    if (c) { c.classList.add('gone'); setTimeout(() => { c.remove(); checkEmpty(); }, 320); }
}
function checkEmpty() {
    const list = document.getElementById('list');
    if (list && !list.querySelector('.card')) {
        list.innerHTML = '<div class="empty"><div class="big">✓ All clear</div>Everything in this view has been reconciled.</div>';
    }
}
</script>
</body>
</html>
