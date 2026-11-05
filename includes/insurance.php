<?php
/**
 * Wiyule Motors — Insurance Companies & Statements Helpers
 *
 * All helpers expect a $conn (mysqli) connection in scope, fetched
 * via includes/db_connection.php in the calling file.
 *
 * Conventions:
 *   - "Insurance invoices" have insurance_company_id IS NOT NULL
 *   - "Others" bucket = invoices with insurance_company_id IS NULL
 *   - Outstanding = total - amount_paid for invoices where payment_status IN ('unpaid', 'partial')
 *   - Overdue = unpaid/partial AND issued_at older than 21 days
 */

if (!function_exists('wm_ins_companies_all')) {

    /**
     * Fetch all active insurance companies, alphabetical.
     * @return array<array{id:int, name:string, is_active:int}>
     */
    function wm_ins_companies_all(mysqli $conn, bool $active_only = true): array {
        $sql = "SELECT id, name, is_active FROM insurance_companies";
        if ($active_only) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY name ASC";
        $r = $conn->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Add a new insurance company (case-insensitive duplicate check).
     * Returns the new ID, or the existing ID if it already exists.
     */
    function wm_ins_company_add(mysqli $conn, string $name): int {
        $name = trim($name);
        if ($name === '') return 0;

        // Check if exists (case-insensitive)
        $stmt = $conn->prepare("SELECT id FROM insurance_companies WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) return (int)$existing['id'];

        // Insert
        $stmt = $conn->prepare("INSERT INTO insurance_companies (name) VALUES (?)");
        $stmt->bind_param('s', $name);
        $ok = $stmt->execute();
        $id = $ok ? $conn->insert_id : 0;
        $stmt->close();
        return (int)$id;
    }

    /**
     * Soft-delete (deactivate) an insurance company.
     * We do NOT hard delete because existing invoices reference the id.
     */
    function wm_ins_company_deactivate(mysqli $conn, int $id): bool {
        $stmt = $conn->prepare("UPDATE insurance_companies SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Reactivate a soft-deleted company.
     */
    function wm_ins_company_reactivate(mysqli $conn, int $id): bool {
        $stmt = $conn->prepare("UPDATE insurance_companies SET is_active = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Compute statement totals for ALL companies + the "Others" bucket.
     * Optional year filter. Excludes quotations (only invoice type counts).
     *
     * @return array<array{
     *    id: int|null,        // null for Others bucket
     *    name: string,        // company name or 'Others'
     *    invoice_count: int,
     *    paid_count: int,
     *    unpaid_count: int,
     *    overdue_count: int,
     *    total_billed: float,
     *    total_paid: float,
     *    outstanding: float,
     *    overdue_amount: float
     * }>
     */
    function wm_ins_statements_summary(mysqli $conn, ?int $year = null): array {
        // Base WHERE: only invoices (not quotations), exclude void
        $where = "type = 'invoice' AND payment_status != 'void'";
        $params = [];
        $types  = '';
        if ($year) {
            $where .= " AND YEAR(issued_at) = ?";
            $params[] = $year;
            $types  .= 'i';
        }

        // Per-company aggregates (joins to insurance_companies for the name)
        $sql_companies = "
            SELECT
                c.id AS id,
                c.name AS name,
                COUNT(i.id) AS invoice_count,
                SUM(CASE WHEN i.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN i.payment_status IN ('unpaid','partial') THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN i.payment_status IN ('unpaid','partial')
                          AND i.issued_at < (CURDATE() - INTERVAL 21 DAY) THEN 1 ELSE 0 END) AS overdue_count,
                COALESCE(SUM(i.total), 0) AS total_billed,
                COALESCE(SUM(i.amount_paid), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN i.payment_status IN ('unpaid','partial')
                                  THEN (i.total - i.amount_paid) ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN i.payment_status IN ('unpaid','partial')
                                  AND i.issued_at < (CURDATE() - INTERVAL 21 DAY)
                                  THEN (i.total - i.amount_paid) ELSE 0 END), 0) AS overdue_amount
            FROM insurance_companies c
            LEFT JOIN invoices i ON i.insurance_company_id = c.id AND $where
            GROUP BY c.id, c.name
        ";
        $stmt = $conn->prepare($sql_companies);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // The "Others" bucket — invoices with NULL insurance_company_id
        $sql_others = "
            SELECT
                COUNT(*) AS invoice_count,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN payment_status IN ('unpaid','partial') THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN payment_status IN ('unpaid','partial')
                          AND issued_at < (CURDATE() - INTERVAL 21 DAY) THEN 1 ELSE 0 END) AS overdue_count,
                COALESCE(SUM(total), 0) AS total_billed,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN payment_status IN ('unpaid','partial')
                                  THEN (total - amount_paid) ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN payment_status IN ('unpaid','partial')
                                  AND issued_at < (CURDATE() - INTERVAL 21 DAY)
                                  THEN (total - amount_paid) ELSE 0 END), 0) AS overdue_amount
            FROM invoices
            WHERE insurance_company_id IS NULL AND $where
        ";
        $stmt = $conn->prepare($sql_others);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $others = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Normalize: ensure numeric defaults if no invoices yet
        $normalize = function ($r) {
            return [
                'id'             => $r['id'] ?? null,
                'name'           => $r['name'] ?? 'Others',
                'invoice_count'  => (int)($r['invoice_count']  ?? 0),
                'paid_count'     => (int)($r['paid_count']     ?? 0),
                'unpaid_count'   => (int)($r['unpaid_count']   ?? 0),
                'overdue_count'  => (int)($r['overdue_count']  ?? 0),
                'total_billed'   => (float)($r['total_billed']  ?? 0),
                'total_paid'     => (float)($r['total_paid']    ?? 0),
                'outstanding'    => (float)($r['outstanding']   ?? 0),
                'overdue_amount' => (float)($r['overdue_amount']?? 0),
            ];
        };
        $companies = array_map($normalize, $rows);
        $others_row = $normalize(array_merge(['id' => null, 'name' => 'Others'], $others ?: []));
        $companies[] = $others_row;
        return $companies;
    }

    /**
     * Fetch all invoices for a given insurer (or NULL for "Others" bucket).
     * Optional filters: year, status ('all'|'unpaid'|'paid').
     */
    function wm_ins_invoices_for_company(
        mysqli $conn,
        ?int $company_id,
        ?int $year = null,
        string $status_filter = 'all'
    ): array {
        $sql = "SELECT id, invoice_number, recipient_name, customer_name, claim_reference,
                       vehicle_info, total, amount_paid, payment_status, issued_at, due_date,
                       DATEDIFF(CURDATE(), issued_at) AS age_days
                FROM invoices
                WHERE type = 'invoice' AND payment_status != 'void' AND ";
        $sql .= ($company_id === null) ? "insurance_company_id IS NULL" : "insurance_company_id = ?";

        $params = [];
        $types  = '';
        if ($company_id !== null) {
            $params[] = $company_id;
            $types .= 'i';
        }
        if ($year) {
            $sql .= " AND YEAR(issued_at) = ?";
            $params[] = $year;
            $types  .= 'i';
        }
        if ($status_filter === 'unpaid') {
            $sql .= " AND payment_status IN ('unpaid','partial')";
        } elseif ($status_filter === 'paid') {
            $sql .= " AND payment_status = 'paid'";
        }

        $sql .= " ORDER BY issued_at DESC";

        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Get one company by ID. Returns null if not found.
     */
    function wm_ins_company_get(mysqli $conn, int $id): ?array {
        $stmt = $conn->prepare("SELECT id, name, is_active FROM insurance_companies WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Get distinct years in which insurance invoices exist (for year selector).
     */
    function wm_ins_distinct_years(mysqli $conn): array {
        $r = $conn->query("
            SELECT DISTINCT YEAR(issued_at) AS y
            FROM invoices
            WHERE type = 'invoice' AND issued_at IS NOT NULL
            ORDER BY y DESC
        ");
        if (!$r) return [(int)date('Y')];
        $years = [];
        while ($row = $r->fetch_assoc()) {
            $years[] = (int)$row['y'];
        }
        if (!$years) $years[] = (int)date('Y');
        return $years;
    }

    /**
     * Aging breakdown for outstanding invoices for a company.
     * Buckets: 0-30 / 31-60 / 61-90 / Over 90 days (based on age of issued_at).
     *
     * Pass null for $company_id to compute the "Others" bucket.
     * Always applies the unpaid/partial filter (the whole point of aging is what's owed).
     *
     * Returns:
     *   [
     *     'current'    => ['count' => int, 'amount' => float],   // 0-30 days
     *     'days_31_60' => ['count' => int, 'amount' => float],
     *     'days_61_90' => ['count' => int, 'amount' => float],
     *     'over_90'    => ['count' => int, 'amount' => float],
     *     'total'      => ['count' => int, 'amount' => float],
     *   ]
     */
    function wm_ins_aging_breakdown(mysqli $conn, ?int $company_id, ?int $year = null): array {
        $where  = "type = 'invoice' AND payment_status IN ('unpaid','partial')";
        $params = [];
        $types  = '';

        if ($company_id === null) {
            $where .= " AND insurance_company_id IS NULL";
        } else {
            $where .= " AND insurance_company_id = ?";
            $params[] = $company_id;
            $types   .= 'i';
        }
        if ($year) {
            $where .= " AND YEAR(issued_at) = ?";
            $params[] = $year;
            $types   .= 'i';
        }

        $sql = "
            SELECT
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) <= 30 THEN 1 ELSE 0 END) AS c_current,
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) <= 30 THEN (total - amount_paid) ELSE 0 END) AS a_current,
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS c_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) BETWEEN 31 AND 60 THEN (total - amount_paid) ELSE 0 END) AS a_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) BETWEEN 61 AND 90 THEN 1 ELSE 0 END) AS c_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) BETWEEN 61 AND 90 THEN (total - amount_paid) ELSE 0 END) AS a_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) > 90 THEN 1 ELSE 0 END) AS c_over_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), issued_at) > 90 THEN (total - amount_paid) ELSE 0 END) AS a_over_90,
                COUNT(*) AS c_total,
                COALESCE(SUM(total - amount_paid), 0) AS a_total
            FROM invoices
            WHERE $where
        ";

        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'current'    => ['count' => (int)($row['c_current']  ?? 0), 'amount' => (float)($row['a_current']  ?? 0)],
            'days_31_60' => ['count' => (int)($row['c_31_60']    ?? 0), 'amount' => (float)($row['a_31_60']    ?? 0)],
            'days_61_90' => ['count' => (int)($row['c_61_90']    ?? 0), 'amount' => (float)($row['a_61_90']    ?? 0)],
            'over_90'    => ['count' => (int)($row['c_over_90']  ?? 0), 'amount' => (float)($row['a_over_90']  ?? 0)],
            'total'      => ['count' => (int)($row['c_total']    ?? 0), 'amount' => (float)($row['a_total']    ?? 0)],
        ];
    }

    /**
     * Count unresolved statement-import review items.
     * Returns 0 if the import_reviews table doesn't exist yet (no import run).
     */
    function wm_import_reviews_open_count(mysqli $conn): int {
        try {
            $r = $conn->query("SELECT COUNT(*) FROM import_reviews WHERE resolved = 0");
            return $r ? (int)$r->fetch_row()[0] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
