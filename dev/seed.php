<?php
// Seed the LOCAL dev DB (wiyule_motors on brew MySQL) with FAKE data.
// Safe: localhost only, synthetic data, for testing the admin panel.
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$c = new mysqli('127.0.0.1', 'root', '', 'wiyule_motors', 3306);
$c->set_charset('utf8mb4');

// wipe (children first)
foreach (['invoice_items','invoices','invoice_counter','bookings','vehicles','insurance_companies','users'] as $t) {
    $c->query("DELETE FROM `$t`");
    $c->query("ALTER TABLE `$t` AUTO_INCREMENT = 1");
}

// ── Insurance companies ──
$insurers = ['Prime Insurance','Old Mutual Malawi','NICO General','Charter Insurance','United General'];
foreach ($insurers as $n) {
    $s = $c->prepare("INSERT INTO insurance_companies (name, is_active) VALUES (?, 1)");
    $s->bind_param('s', $n); $s->execute();
}

// ── Users (1 admin + customers) ──
$ADMIN_PW = 'devadmin123';
$adminHash = password_hash($ADMIN_PW, PASSWORD_BCRYPT);
$s = $c->prepare("INSERT INTO users (full_name,email,password,phone,is_admin,email_verified) VALUES (?,?,?,?,1,1)");
$an='Dev Admin'; $ae='admin@wiyulemotors.com'; $ap='+265999000000';
$s->bind_param('ssss',$an,$ae,$adminHash,$ap); $s->execute();

$customers = [
    ['Thoko Banda','thoko.banda@example.com','+265991234501'],
    ['James Phiri','james.phiri@example.com','+265991234502'],
    ['Grace Mwale','grace.mwale@example.com','+265991234503'],
    ['Peter Gondwe','peter.gondwe@example.com','+265991234504'],
    ['Linda Chirwa','linda.chirwa@example.com','+265991234505'],
    ['Samuel Nkhoma','samuel.nkhoma@example.com','+265991234506'],
];
$custIds = [];
$ph = password_hash('customer123', PASSWORD_BCRYPT);
foreach ($customers as $cu) {
    $s = $c->prepare("INSERT INTO users (full_name,email,password,phone,is_admin,email_verified) VALUES (?,?,?,?,0,1)");
    $s->bind_param('ssss',$cu[0],$cu[1],$ph,$cu[2]); $s->execute();
    $custIds[] = $c->insert_id;
}

// ── Vehicles ──
$vehicles = [
    ['Toyota','Hilux','2018','BT 4521','White',85000,'2.8L D4D','Manual'],
    ['Nissan','Navara','2020','BW 9087','Silver',42000,'2.3L dCi','Automatic'],
    ['Toyota','Land Cruiser','2016','BZ 1123','Black',132000,'4.5L V8','Automatic'],
    ['Ford','Ranger','2019','MC 7788','Blue',60000,'3.2L TDCi','Automatic'],
    ['Mercedes-Benz','Sprinter','2015','BT 3344','White',190000,'2.1L CDI','Manual'],
    ['Toyota','Corolla','2021','BW 2211','Grey',28000,'1.8L','CVT'],
];
foreach ($vehicles as $i => $v) {
    $uid = $custIds[$i % count($custIds)];
    $s = $c->prepare("INSERT INTO vehicles (user_id,make,model,year,registration,color,mileage,engine,transmission) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->bind_param('issssssss',$uid,$v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7]);
    // mileage is int; bind as string is fine for mysqli
    $s->execute();
}

// ── Bookings (varied statuses + dates) ──
$services = ['Full Service','Brake Replacement','Oil Change','Diagnostics','Panel Beating','Wheel Alignment','Engine Overhaul','Detailing'];
$statuses = ['pending','confirmed','completed','cancelled','pending','confirmed','completed','confirmed'];
$sources  = ['website','whatsapp','walk-in','phone'];
for ($i=0;$i<14;$i++) {
    $cu = $customers[$i % count($customers)];
    $uid = $custIds[$i % count($custIds)];
    $v = $vehicles[$i % count($vehicles)];
    $svc = $services[$i % count($services)];
    $st  = $statuses[$i % count($statuses)];
    $src = $sources[$i % count($sources)];
    $date = date('Y-m-d', strtotime(($i<7? '+':'-').abs(7-$i).' days'));
    $time = ['08:30','09:00','10:30','13:00','14:30'][$i%5];
    $reg = $v[3]; $mk=$v[0]; $mo=$v[1]; $yr=$v[2]; $nm=$cu[0]; $phn=$cu[2]; $em=$cu[1];
    $notes = 'Auto-seeded test booking #'.($i+1);
    $s = $c->prepare("INSERT INTO bookings (user_id,full_name,phone,email,vehicle_make,vehicle_model,vehicle_year,registration,services,booking_date,booking_time,notes,source,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param('isssssssssssss',$uid,$nm,$phn,$em,$mk,$mo,$yr,$reg,$svc,$date,$time,$notes,$src,$st);
    $s->execute();
}

// ── Invoices + items ──
require __DIR__ . '/../includes/invoices.php';
$c->query("INSERT INTO invoice_counter (year,last_number) VALUES (".((int)date('Y')).",0)");

function add_invoice($c,$num,$type,$cust,$phone,$email,$vehicle,$status,$items,$opts=[]) {
    $subtotal = 0; foreach ($items as $it) $subtotal += $it[2]*$it[3];
    $disc_type = $opts['discount_type'] ?? 'none';
    $disc_val  = $opts['discount_value'] ?? 0;
    $vat_en    = $opts['vat_enabled'] ?? 1;
    $excess    = $opts['less_excess'] ?? 0;
    $calc = wm_invoice_calc($subtotal,$disc_type,$disc_val,(bool)$vat_en,WM_VAT_RATE,$excess);
    $insurer = $opts['insurer'] ?? null;
    $claim   = $opts['claim'] ?? null;
    $paid    = $opts['amount_paid'] ?? ($status==='paid'?$calc['total']:($status==='partial'?round($calc['total']*0.5,2):0));
    $pm      = $opts['payment_method'] ?? ($paid>0?'cash':'');
    $pdate   = $paid>0? date('Y-m-d') : null;
    $issued  = $opts['issued_at'] ?? date('Y-m-d H:i:s', strtotime('-'.rand(1,40).' days'));
    $vrate   = WM_VAT_RATE;
    $s = $c->prepare("INSERT INTO invoices
      (invoice_number,type,customer_name,customer_phone,customer_email,vehicle_info,insurance_company_id,claim_reference,
       subtotal,discount_type,discount_value,discount_amount,vat_enabled,vat_rate,vat_amount,less_excess,total,
       payment_status,amount_paid,payment_method,payment_date,issued_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param('ssssssisdsddiddddsdsss',
      $num,$type,$cust,$phone,$email,$vehicle,$insurer,$claim,
      $calc['subtotal'],$disc_type,$disc_val,$calc['discount_amount'],$vat_en,$vrate,$calc['vat_amount'],$calc['less_excess'],$calc['total'],
      $status,$paid,$pm,$pdate,$issued);
    $s->execute();
    $iid = $c->insert_id;
    $so=0;
    foreach ($items as $it) {
        $lt = $it[2]*$it[3]; $so++;
        $s2 = $c->prepare("INSERT INTO invoice_items (invoice_id,item_type,description,quantity,unit_price,line_total,sort_order) VALUES (?,?,?,?,?,?,?)");
        $s2->bind_param('issdddi',$iid,$it[0],$it[1],$it[3],$it[2],$lt,$so);
        $s2->execute();
    }
    return $iid;
}

$Y = date('Y');
add_invoice($c,"WM-$Y-001",'invoice','Thoko Banda','+265991234501','thoko.banda@example.com','Toyota Hilux 2018 (BT 4521)','paid',
    [['part','Brake pads (front set)',45000,1],['labour','Brake fitting labour',15000,1],['part','Brake disc',38000,2]]);
add_invoice($c,"WM-$Y-002",'invoice','James Phiri','+265991234502','james.phiri@example.com','Nissan Navara 2020 (BW 9087)','partial',
    [['service','Full service',85000,1],['part','Oil filter',8000,1],['part','Engine oil 5L',32000,1]],['discount_type'=>'percent','discount_value'=>10]);
add_invoice($c,"WM-$Y-003",'invoice','Grace Mwale','+265991234503','grace.mwale@example.com','Toyota Land Cruiser 2016 (BZ 1123)','unpaid',
    [['labour','Engine diagnostics',25000,1],['part','Spark plugs',6000,8]]);
add_invoice($c,"WM-$Y-004",'invoice','Prime Insurance (claim)','+265991234504','peter.gondwe@example.com','Ford Ranger 2019 (MC 7788)','unpaid',
    [['part','Front bumper',120000,1],['labour','Panel beating',90000,1],['part','Headlight assembly',75000,2],['other','Paint & consumables',60000,1]],
    ['insurer'=>1,'claim'=>'PRM-2026-0098','less_excess'=>50000]);
add_invoice($c,"WM-$Y-005",'invoice','Linda Chirwa','+265991234505','linda.chirwa@example.com','Mercedes-Benz Sprinter 2015 (BT 3344)','void',
    [['service','Detailing',40000,1]]);
add_invoice($c,"WM-$Y-006",'quotation','Samuel Nkhoma','+265991234506','samuel.nkhoma@example.com','Toyota Corolla 2021 (BW 2211)','unpaid',
    [['service','Wheel alignment',18000,1],['part','Tyres 195/65 R15',65000,4]],['vat_enabled'=>1]);
add_invoice($c,"WM-$Y-007",'quotation','Thoko Banda','+265991234501','thoko.banda@example.com','Toyota Hilux 2018 (BT 4521)','unpaid',
    [['labour','Clutch replacement labour',70000,1],['part','Clutch kit',145000,1]]);

$c->query("UPDATE invoice_counter SET last_number=7 WHERE year=$Y");

// report
$out = [];
foreach (['users','bookings','vehicles','insurance_companies','invoices','invoice_items'] as $t) {
    $out[$t] = $c->query("SELECT COUNT(*) n FROM `$t`")->fetch_assoc()['n'];
}
echo "SEED OK: ".json_encode($out)."\n";
echo "ADMIN LOGIN -> email: admin@wiyulemotors.com  password: $ADMIN_PW\n";
$c->close();
