<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pace_order_workflow_model extends CI_Model
{
    /* ========================= Tables ========================= */
    private $tbl_sap           = 'student_assign_paces';
    private $tbl_subject       = 'subject';
    private $tbl_notifications = 'notifications';
    private $tbl_login_cred    = 'login_credential';
    private $tbl_staff         = 'staff';

    private $tbl_pace_stock    = 'pace_stock';   // branch_id, subject_id, <pace#>, <qty>
    private $tbl_inv           = 'invoices';     // normalized in constructor
    private $tbl_inv_items     = 'invoice_items';
    private $tbl_ho_req        = 'head_office_requisitions';
    private $tbl_ho_req_items  = 'head_office_requisition_items';   

    /* --------- NEW: pre-invoice order tables (additive) --------- */
    private $tbl_po            = 'pace_orders';
    private $tbl_poi           = 'pace_order_items';
    private $use_preinvoice    = false; // auto-enabled in constructor if both tables exist

    /* ========================= Roles ========================== */
    private $role_super_admin  = 1;
    private $role_admin        = 2;
    private $role_teacher      = 3;
    private $role_reception    = 8;

    public function __construct()
    {
        parent::__construct();
        if (!isset($this->db)) { $this->load->database(); }

        // Header table: prefer campus schema first
        if ($this->db->table_exists('hs_academy_invoices')) {
            $this->tbl_inv = 'hs_academy_invoices';
        } elseif ($this->db->table_exists('invoice')) {
            $this->tbl_inv = 'invoice';
        } elseif ($this->db->table_exists('invoices')) {
            $this->tbl_inv = 'invoices';
        }

        // Item table: prefer new; fallback to legacy
        if ($this->db->table_exists('invoice_items')) {
            $this->tbl_inv_items = 'invoice_items';
        } elseif ($this->db->table_exists('hs_academy_invoice_items')) {
            $this->tbl_inv_items = 'hs_academy_invoice_items';
        }

        // Enable pre-invoice flow automatically if both tables exist
        if ($this->db->table_exists($this->tbl_po) && $this->db->table_exists($this->tbl_poi)) {
            $this->use_preinvoice = true;
        }
    }

    /* ===================== Schema helpers ===================== */

    /** Return first existing column from $candidates on $table, or null. */
    private function resolve_column($table, array $candidates)
    {
        if (!$this->db->table_exists($table)) return null;
        $fields = array_map('strtolower', $this->db->list_fields($table));
        foreach ($candidates as $c) {
            if (in_array(strtolower($c), $fields, true)) return $c;
        }
        return null;
    }

    /** Quick “does this field exist?” check. */
    private function has_col($table, $col)
    {
        if (!$this->db->table_exists($table)) return false;
        return in_array($col, $this->db->list_fields($table), true);
    }

    /** Accept $data but only keep keys that actually exist in $table. */
    private function safe_insert($table, array $data)
    {
        $fields  = $this->db->list_fields($table);
        $payload = array_intersect_key($data, array_flip($fields));
        $this->db->insert($table, $payload);
        return $this->db->insert_id();
    }

    /* ======================== Entry point ===================== */

    /** Call this after each `student_assign_paces` row is created as 'ordered'. */
    public function on_ordered($sap_id)
    {
        $sap = $this->db->get_where($this->tbl_sap, ['id' => (int)$sap_id])->row_array();
        if (!$sap) return;

        $branch_id   = (int)($sap['branch_id']  ?? 0);
        $student_id  = (int)($sap['student_id'] ?? 0);
        $subject_id  = (int)($sap['subject_id'] ?? 0);
        $pace_number = (int)($sap['pace_number']?? 0);
        $session_id  = (int)($sap['session_id'] ?? 0);

        // Subject label (still used for email content)
        $subject       = $this->db->select('name')->get_where($this->tbl_subject, ['id' => $subject_id])->row_array();
        $subject_label = $subject['name'] ?? ('Subject#' . $subject_id);

        // Recipients (Finance/Admin/Reception for the same branch)
        $recipients = $this->get_recipients($branch_id);

        /* ====== NEW: if pre-invoice tables exist, build a reviewable order ====== */
        if ($this->use_preinvoice) {
            $order_id = (int)$this->append_to_preinvoice_order(
                $branch_id, $student_id, $session_id, (int)$sap_id, $subject_id, $pace_number
            );

            // Notify Finance/Admin to review (de-duped per receiver + order)
            if ($order_id > 0 && !empty($recipients) && $this->db->table_exists($this->tbl_notifications)) {
                foreach ($recipients as $r) {
                    $this->ensure_order_notice_for((int)$r['user_id'], $order_id, $student_id, $branch_id);
                }
            }

        } else {
            /* ====== Legacy path (unchanged): append straight to an invoice ====== */
            $invoice_id = (int)$this->append_to_hs_invoice($branch_id, $student_id, $session_id, $sap_id, $subject_id, $pace_number);

            // ONE notification per INVOICE (de-dup by receiver + invoice_id in URL/payload)
            if ($invoice_id > 0 && !empty($recipients) && $this->db->table_exists($this->tbl_notifications)) {
                foreach ($recipients as $r) {
                    $this->ensure_invoice_notice_for((int)$r['user_id'], $invoice_id, $student_id, $branch_id);
                }
            }
        }

        // ===== Stock + head office requisition if short (unchanged) =====
        $this->stock_check_and_requisition($branch_id, $subject_id, $pace_number, $sap_id);

        // Emails — still per PACE (adjust if you want batching)
        $this->send_emails($recipients, $sap_id, $student_id, $subject_label, $pace_number);
    }

    /* =================== Recipients / comms =================== */

    private function get_recipients($branch_id)
    {
        $rows = $this->db->select('lc.user_id, COALESCE(s.email, lc.username) AS email', false)
            ->from($this->tbl_login_cred . ' lc')
            ->join($this->tbl_staff . ' s', 's.id = lc.user_id', 'left')
            ->where_in('lc.role', [$this->role_super_admin, $this->role_admin, $this->role_reception])
            ->where('lc.active', 1)
            ->where('s.branch_id', (int)$branch_id)
            ->get()->result_array();

        $map = [];
        foreach ($rows as $r) {
            if (!empty($r['email'])) {
                $map[(int)$r['user_id']] = [
                    'user_id' => (int)$r['user_id'],
                    'email'   => $r['email'],
                ];
            }
        }
        return array_values($map);
    }

    /** (Legacy; kept for compatibility) */
    private function create_notifications(array $recipients, $sap_id, $student_id, $subject_label, $pace_number, $branch_id)
    {
        if (empty($recipients) || !$this->db->table_exists($this->tbl_notifications)) return;

        $title = 'PACE Order';
        $msg   = "Student #{$student_id} ordered {$subject_label} PACE {$pace_number}.";
        $url   = site_url('pace/order?focus_sap=' . (int)$sap_id);
        $now   = date('Y-m-d H:i:s');

        foreach ($recipients as $r) {
            $this->safe_insert($this->tbl_notifications, [
                'receiver_id' => (int)$r['user_id'],
                'title'       => $title,
                'message'     => $msg,
                'url'         => $url,
                'branch_id'   => (int)$branch_id,
                'created_at'  => $now,
                'is_read'     => 0,
            ]);
        }
    }

    /** Read school display name + sensible From-email without relying on specific columns. */
    private function get_school_and_from_()
    {
        $host   = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
        $school = 'EduAssist';
        $from   = 'noreply@' . $host;

        if ($this->db->table_exists('global_settings')) {
            $row = $this->db->limit(1)->get('global_settings')->row_array(); // fetch * to avoid missing-column errors
            if (is_array($row)) {
                $school = $row['school_name']
                       ?? $row['institute_name']
                       ?? $row['system_name']
                       ?? $row['school']
                       ?? $school;

                $from   = $row['email']
                       ?? $row['system_email']
                       ?? $row['smtp_user']
                       ?? $from;
            }
        }
        return [$school, $from];
    }

    private function get_school_display_name()
    {
        $school = null;
        if ($this->db->table_exists('global_settings')) {
            $row = $this->db->limit(1)->get('global_settings')->row_array(); // no column list → safe
            if ($row) {
                $school = $row['school_name']
                       ?? $row['institute_name']
                       ?? $row['system_name']
                       ?? $row['school']
                       ?? null;
            }
        }
        if ($school) return $school;

        $campus = $this->db->select('name')
            ->from('school')
            ->where('id', (int)get_loggedin_branch_id())
            ->get()->row_array();

        return $campus['name'] ?? 'School';
    }

    /** Send emails; silently skip on dev or when SMTP is not configured. */
    private function send_emails(array $recipients, $sap_id, $student_id, $subject_label, $pace_number)
    {
        if (empty($recipients)) return;

        // Skip completely on non-production or when SMTP host missing
        $cfg = $this->db->get_where('email_config', ['id' => 1])->row_array();
        if (strtolower(ENVIRONMENT) !== 'production' || empty($cfg['smtp_host'])) {
            log_message('debug', 'PACE order email skipped (dev / no SMTP). SAP='.$sap_id);
            return;
        }

        list($school, $from) = $this->get_school_and_from_();

        $this->load->library('email');
        $this->email->initialize([
            'protocol'    => 'smtp',
            'smtp_host'   => $cfg['smtp_host'],
            'smtp_user'   => $cfg['smtp_username'] ?? '',
            'smtp_pass'   => $cfg['smtp_password'] ?? '',
            'smtp_port'   => (int)($cfg['smtp_port'] ?? 587),
            'smtp_crypto' => ($cfg['encryption'] ?? 'tls'),
            'mailtype'    => 'html',
            'newline'     => "\r\n",
            'crlf'        => "\r\n",
        ]);

        $subject = '[' . $school . '] New PACE Order';
        $body    = nl2br(
            "A new PACE order was placed.\n\n" .
            "Student ID: {$student_id}\n" .
            "PACE: {$subject_label} {$pace_number}\n" .
            "Time: " . date('Y-m-d H:i') . "\n\n" .
            "View: " . site_url('pace/order?focus_sap=' . (int)$sap_id) . "\n"
        );

        foreach ($recipients as $r) {
            if (empty($r['email'])) continue;
            $this->email->clear(true);
            $this->email->from($from, $school);
            $this->email->to($r['email']);
            $this->email->subject($subject);
            $this->email->message($body);
            if (!$this->email->send(false)) {
                // Log only; never echo (prevents breaking the order flow)
                log_message('error', 'PACE order mail failed (SAP '.$sap_id.') — '.$this->email->print_debugger(['headers']));
            }
        }
    }

    /* ======================== Invoice handling ======================== */

    private function append_to_hs_invoice($branch_id, $student_id, $session_id, $sap_id, $subject_id, $pace_number)
    {
        // Is this SAP a REDO row?
        $sapRow  = $this->db->get_where($this->tbl_sap, ['id' => (int)$sap_id])->row_array();
        $is_redo = !empty($sapRow['is_redo']) ? 1 : 0;
        $now     = date('Y-m-d H:i:s');

        // If this SAP is already on any invoice, return that invoice id (idempotent)
        if ($this->has_col($this->tbl_inv_items, 'sap_id')) {
            $already = $this->db->select('invoice_id')
                ->get_where($this->tbl_inv_items, ['sap_id' => (int)$sap_id], 1)
                ->row_array();
            if ($already) return (int)$already['invoice_id'];
        }

        // ── Pick / create the invoice header ─────────────────────────────────
        $inv_id = 0;

        if ($is_redo) {
            // REDO: always make a dedicated invoice so it never mixes with normal orders
            $invData = [
                'branch_id'  => (int)$branch_id,
                'student_id' => (int)$student_id,
                'session_id' => (int)$session_id,
                'status'     => 'redo',      // key difference
                'total'      => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($this->has_col($this->tbl_inv, 'is_redo')) {
                $invData['is_redo'] = 1;
            }
            $inv_id = (int)$this->safe_insert($this->tbl_inv, $invData);

        } else {
            // NORMAL order: reuse latest *draft* invoice (but never a redo one)
            $this->db->from($this->tbl_inv)
                ->where('branch_id',  (int)$branch_id)
                ->where('student_id', (int)$student_id)
                ->where('session_id', (int)$session_id)
                ->where('status',     'draft');

            if ($this->has_col($this->tbl_inv, 'is_redo')) {
                $this->db->group_start()
                         ->where('is_redo', 0)
                         ->or_where('is_redo IS NULL', null, false)
                         ->group_end();
            }

            $inv = $this->db->order_by('id','DESC')->limit(1)->get()->row_array();
            if ($inv) {
                $inv_id = (int)$inv['id'];
            } else {
                $inv_id = (int)$this->safe_insert($this->tbl_inv, [
                    'branch_id'  => (int)$branch_id,
                    'student_id' => (int)$student_id,
                    'session_id' => (int)$session_id,
                    'status'     => 'draft',
                    'total'      => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'is_redo'    => $this->has_col($this->tbl_inv, 'is_redo') ? 0 : null,
                ]);
            }
        }

        if ($inv_id <= 0) return 0;

        // ── Add the line item ────────────────────────────────────────────────
        $paceCol = $this->resolve_column($this->tbl_inv_items, ['pace_number','pace_no','item_no','book_number','number','pace']) ?: 'pace_number';
        $qtyCol  = $this->resolve_column($this->tbl_inv_items, ['qty','quantity','qty_ordered']) ?: 'qty';
        $upCol   = $this->resolve_column($this->tbl_inv_items, ['unit_price','price','unit']) ?: 'unit_price';
        $ltCol   = $this->resolve_column($this->tbl_inv_items, ['line_total','total','line']) ?: 'line_total';
        $noteCol = $this->resolve_column($this->tbl_inv_items, ['description','notes','note','label','item_name','title']);

        $unit_price = $this->guess_price($subject_id, $pace_number, $branch_id, $session_id);
        $line_total = $unit_price * 1;

        $payload = [
            'invoice_id' => $inv_id,
            'sap_id'     => (int)$sap_id,
            'subject_id' => (int)$subject_id,
            $paceCol     => (int)$pace_number,
            $qtyCol      => 1,
            $upCol       => $unit_price,
            $ltCol       => $line_total,
            'is_redo'    => $is_redo, // harmless if column doesn't exist
        ];
        if ($is_redo && $noteCol) {
            $subject = $this->db->select('name')->get_where($this->tbl_subject, ['id' => (int)$subject_id])->row('name');
            $payload[$noteCol] = trim(($subject ?: 'Subject') . " PACE {$pace_number} [REDO]");
        }

        $inv_item_id = (int)$this->safe_insert($this->tbl_inv_items, $payload);
        if ($inv_item_id <= 0) {
            log_message('error', 'PACE order: failed to insert invoice item for SAP ID '.$sap_id);
            return $inv_id; // bail instead of pretending it worked
        }

        // ── Update invoice total ─────────────────────────────────────────────
        $ltCol = $this->db->field_exists('line_total', $this->tbl_inv_items) ? 'line_total'
               : ($this->db->field_exists('total',      $this->tbl_inv_items) ? 'total'
               : ($this->db->field_exists('amount',     $this->tbl_inv_items) ? 'amount'
               : 'line_total')); // fallback

        $sumRow = $this->db->select_sum($ltCol, 't')
            ->get_where($this->tbl_inv_items, ['invoice_id' => $inv_id])
            ->row_array();

        $this->db->where('id', $inv_id)->update($this->tbl_inv, [
            'total'      => (float)($sumRow['t'] ?? 0),
            'updated_at' => $now,
        ]);

        return (int)$inv_id;
    }

    /* ================== Stock / HO requisitions ================== */

    private function stock_check_and_requisition($branch_id, $subject_id, $pace_number, $sap_id)
    {
        // Column discovery on pace_stock
        $paceColStock = $this->resolve_column($this->tbl_pace_stock, ['pace_number','pace_no','book_number','number','item_no','pace']);
        $qtyColStock  = $this->resolve_column($this->tbl_pace_stock, ['qty','quantity','stock_qty']);

        if (!$this->db->table_exists($this->tbl_pace_stock) || !$paceColStock) return;

        $row = $this->db->get_where($this->tbl_pace_stock, [
            'branch_id'   => (int)$branch_id,
            'subject_id'  => (int)$subject_id,
            $paceColStock => (int)$pace_number,
        ])->row_array();

        $have = (int)($row[$qtyColStock ?? 'qty'] ?? 0);
        $need = 1;

        if ($have >= $need) return; // enough stock

        // Find or create today's pending requisition
        $today = date('Y-m-d');
        $req = $this->db->select('id')
            ->from($this->tbl_ho_req)
            ->where('branch_id', (int)$branch_id)
            ->where('status', 'pending')
            ->like('created_at', $today, 'after')
            ->get()->row_array();

        $req_id = $req ? (int)$req['id'] : $this->safe_insert($this->tbl_ho_req, [
            'order_id'   => null,
            'branch_id'  => (int)$branch_id,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Column discovery on requisition items
        $paceColReq = $this->resolve_column($this->tbl_ho_req_items, ['pace_number','pace_no','book_number','number','item_no','pace']);
        $qtyColReq  = $this->resolve_column($this->tbl_ho_req_items, ['qty','quantity','qty_ordered']);
        if (!$this->db->table_exists($this->tbl_ho_req_items) || !$paceColReq) return;

        $short = max(0, $need - $have);

        $payload = [
            'requisition_id' => $req_id,
            'subject_id'     => (int)$subject_id,
            $paceColReq      => (int)$pace_number,
            ($qtyColReq ?: 'qty') => (int)$short,
        ];
        if ($this->has_col($this->tbl_ho_req_items, 'sap_id')) {
            $payload['sap_id'] = (int)$sap_id;
        }

        $this->safe_insert($this->tbl_ho_req_items, $payload);
    }

/* ===================== Price resolver ====================== */

private function product_price_lookup(int $subject_id, int $pace_number, ?int $branch_id): float
{
    if (!$this->db->table_exists('product')) return 0.0;

    // Subject column can be either subject_id or category_id.
    $subjectCol = $this->db->field_exists('subject_id', 'product') ? 'subject_id'
               : ($this->db->field_exists('category_id', 'product') ? 'category_id' : null);
    if (!$subjectCol || !$this->db->field_exists('pace_number', 'product') ||
        !$this->db->field_exists('sales_price', 'product')) {
        return 0.0;
    }

    // 1) Try branch-specific (if table has branch_id)
    $row = null;
    if ($branch_id !== null && $this->db->field_exists('branch_id', 'product')) {
        $row = $this->db->select('sales_price')
            ->from('product')
            ->where('pace_number', $pace_number)
            ->where($subjectCol, $subject_id)
            ->where('branch_id', (int)$branch_id)
            ->order_by('id', 'DESC')->limit(1)->get()->row_array();
    }

    // 2) Fallback: ignore branch_id (use the most recent matching product)
    if (!$row) {
        $row = $this->db->select('sales_price')
            ->from('product')
            ->where('pace_number', $pace_number)
            ->where($subjectCol, $subject_id)
            ->order_by('id', 'DESC')->limit(1)->get()->row_array();
    }

    return ($row && $row['sales_price'] !== '' && $row['sales_price'] !== null)
        ? (float)$row['sales_price'] : 0.0;
}

private function guess_price($subject_id, $pace_number, $branch_id, $session_id)
{
    // Prefer product price (with branch fallback)
    $p = $this->product_price_lookup((int)$subject_id, (int)$pace_number, (int)$branch_id);
    if ($p > 0) return $p;

    // Fallback = global default price if configured
    if ($this->db->table_exists('global_settings') &&
        $this->db->field_exists('pace_default_price', 'global_settings')) {
        $gs = $this->db->limit(1)->get('global_settings')->row_array();
        if ($gs && $gs['pace_default_price'] !== '') {
            return (float)$gs['pace_default_price'];
        }
    }
    return 0.00;
}

private function price_from_product($order_id, $subject_id, $pace_number)
{
    $branch_id = (int)$this->db->select('branch_id')
        ->from('pace_orders')->where('id', (int)$order_id)->get()->row('branch_id');

    $p = $this->product_price_lookup((int)$subject_id, (int)$pace_number, $branch_id);
    if ($p > 0) return $p;

    // Fallback to global default
    if ($this->db->table_exists('global_settings') &&
        $this->db->field_exists('pace_default_price', 'global_settings')) {
        $gs = $this->db->limit(1)->get('global_settings')->row_array();
        return (float)($gs['pace_default_price'] ?? 0);
    }
    return 0.0;
}

    /* ===================== Notification helpers ====================== */

    /** Ensure exactly one unread notification per receiver *and* invoice. */
    private function ensure_invoice_notice_for(int $receiver_id, int $invoice_id, int $student_id, int $branch_id): void
    {
        if (!$this->db->table_exists($this->tbl_notifications)) return;

        $now   = date('Y-m-d H:i:s');
        $count = $this->count_invoice_items($invoice_id);

        $title = 'PACE Order Batch';
        $msg   = "Student #{$student_id} ordered {$count} PACE(s) in this invoice.";
        $url   = site_url('pace/order?invoice_id=' . $invoice_id);

        // Try find existing (unread) by receiver + url match (using invoice_id)
        $existing = $this->db
            ->where('receiver_id', $receiver_id)
            ->like('url', 'invoice_id='.$invoice_id, 'both')
            ->where('is_read', 0)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get($this->tbl_notifications)
            ->row_array();

        if ($existing) {
            $upd = ['message' => $msg];
            if ($this->has_col($this->tbl_notifications, 'updated_at')) {
                $upd['updated_at'] = $now;
            }
            $this->db->where('id', (int)$existing['id'])->update($this->tbl_notifications, $upd);
            return;
        }

        // Insert a new one
        $payload = [
            'receiver_id' => $receiver_id,
            'title'       => $title,
            'message'     => $msg,
            'url'         => $url,
            'branch_id'   => $branch_id,
            'created_at'  => $now,
            'is_read'     => 0,
        ];
        $this->safe_insert($this->tbl_notifications, $payload);
    }

    /** NEW: one unread notification per receiver *and* order needing review. */
    private function ensure_order_notice_for(int $receiver_id, int $order_id, int $student_id, int $branch_id): void
    {
        if (!$this->db->table_exists($this->tbl_notifications)) return;

        $now   = date('Y-m-d H:i:s');
        $title = 'PACE Order Review';
        $msg   = "Student #{$student_id} submitted a PACE order that needs checking.";
        $url   = site_url('pace/orders_batch_edit/' . $order_id);

        // dedupe by receiver + url + unread
        $existing = $this->db
            ->where('receiver_id', $receiver_id)
            ->like('url', 'orders_batch_edit/'.$order_id, 'both')
            ->where('is_read', 0)
            ->limit(1)
            ->get($this->tbl_notifications)
            ->row_array();

        if ($existing) {
            $upd = ['message' => $msg];
            if ($this->has_col($this->tbl_notifications, 'updated_at')) {
                $upd['updated_at'] = $now;
            }
            $this->db->where('id', (int)$existing['id'])->update($this->tbl_notifications, $upd);
            return;
        }

        $this->safe_insert($this->tbl_notifications, [
            'receiver_id' => $receiver_id,
            'title'       => $title,
            'message'     => $msg,
            'url'         => $url,
            'branch_id'   => $branch_id,
            'created_at'  => $now,
            'is_read'     => 0,
        ]);
    }

    /** Count lines on an invoice (used for the summary text). */
    private function count_invoice_items(int $invoice_id): int
    {
        if (!$this->db->table_exists($this->tbl_inv_items)) return 0;
        return (int)$this->db->where('invoice_id', $invoice_id)->count_all_results($this->tbl_inv_items);
    }

    /** Mark an invoice item as assigned to a specific SAP row (no-ops if column missing). */
    public function mark_invoice_item_assigned($invoice_item_id, $sap_id)
    {
        if (!$this->has_col($this->tbl_inv_items, 'assigned_id')) return false;
        return $this->db->where('id', (int)$invoice_item_id)
                        ->update($this->tbl_inv_items, ['assigned_id' => (int)$sap_id]);
    }

    /* ===================== NEW: pre-invoice builder ====================== */
    
    private function inv_status_value(string $label)
{
    // map label -> code
    static $map = ['draft'=>0,'paid'=>1,'issued'=>2,'billed'=>3,'redo'=>4];

    // detect column type once
    static $is_int = null;
    if ($is_int === null) {
        $row = $this->db->query("SHOW COLUMNS FROM {$this->tbl_inv} LIKE 'status'")->row_array();
        $is_int = $row && stripos($row['Type'] ?? '', 'int') !== false;
    }

    return $is_int ? ($map[$label] ?? 0) : $label;
}


    private function append_to_preinvoice_order($branch_id, $student_id, $session_id, $sap_id, $subject_id, $pace_number)
    {
        if (!$this->use_preinvoice) return 0;

        $now = date('Y-m-d H:i:s');

        // If this SAP is already in an order, return that order_id (idempotent)
        $exists = $this->db->select('order_id')
            ->get_where($this->tbl_poi, ['sap_id' => (int)$sap_id], 1)
            ->row_array();
        if (!empty($exists['order_id'])) {
            return (int)$exists['order_id'];
        }

        // Find an OPEN header: draft + unchecked + not invoiced
        $this->db->where([
            'branch_id'  => (int)$branch_id,
            'student_id' => (int)$student_id,
            'session_id' => (int)$session_id,
            'is_checked' => 0,
            'status'     => 'draft',
        ]);
        if ($this->has_col($this->tbl_po, 'invoice_id')) {
            $this->db->where('invoice_id IS NULL', null, false);
        }

        $open = $this->db->order_by('id', 'DESC')->limit(1)->get($this->tbl_po)->row_array();

        if ($open) {
            $order_id = (int)$open['id'];
        } else {
            $creator  = function_exists('get_loggedin_user_id') ? (int)get_loggedin_user_id() : 0;
            $order_id = (int)$this->safe_insert($this->tbl_po, [
                'branch_id'   => (int)$branch_id,
                'student_id'  => (int)$student_id,
                'session_id'  => (int)$session_id,
                'status'      => 'draft',     // important: list page expects draft
                'is_checked'  => 0,
                'created_by'  => $creator,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
        if ($order_id <= 0) return 0;

        // Price + redo
        $unit_price = $this->guess_price($subject_id, $pace_number, $branch_id, $session_id);
        $sapRow     = $this->db->get_where($this->tbl_sap, ['id' => (int)$sap_id])->row_array();
        $is_redo    = ($sapRow && !empty($sapRow['is_redo'])) ? 1 : 0;

        // Insert order line
        $this->safe_insert($this->tbl_poi, [
            'order_id'       => $order_id,
            'sap_id'         => (int)$sap_id,
            'subject_id'     => (int)$subject_id,
            'pace_number'    => (int)$pace_number,
            'pace_no'        => (int)$pace_number, // harmless if both exist
            'qty'            => 1,
            'unit_price'     => (float)$unit_price,
            'line_total'     => (float)$unit_price,
            'is_redo'        => (int)$is_redo,
            'description'    => null,
            'created_at'     => $now,
            'last_edited_by' => null,
            'last_edited_at' => null,
        ]);

        return $order_id;
    }
    
    // --- NEW: resolve price via subject_pace → product ---------------------------
public function get_product_price_by_subject_pace($subject_id, $pace_no, $branch_id = null)
{
    $branch_id = $branch_id ?: $this->application_model->get_branch_id();

    $sql = "
        SELECT COALESCE(p.sales_price, 0) AS price
        FROM subject_pace sp
        JOIN product p ON p.id = sp.product_id
        WHERE sp.subject_id = ?
          AND sp.pace_number = ?
          AND (p.branch_id = ? OR p.branch_id IS NULL OR p.branch_id = 0)
        ORDER BY (p.branch_id = ?) DESC, p.id DESC
        LIMIT 1
    ";
    $row = $this->db->query($sql, [$subject_id, $pace_no, $branch_id, $branch_id])->row_array();
    return (float)($row['price'] ?? 0);
}

/**
 * --- NEW: use this to load lines for the batch edit grid with hydrated prices ---
 * Returns: id, order_id, subject_id, pace_no, qty, unit_price, line_total
 */
public function get_order_items($order_id)
{
    $sql = "
        SELECT 
            i.id,
            i.order_id,
            i.subject_id,
            COALESCE(NULLIF(i.pace_no, ''), NULLIF(i.pace_number, ''), 0) AS pace_no,
            COALESCE(i.qty, 0) AS qty,

            /* Priority: item.unit_price -> product via subject_pace -> product by pace_number (branch-aware) */
            CAST(
                COALESCE(
                    NULLIF(NULLIF(TRIM(i.unit_price), ''), 0),   -- treat '' and 0 as NULL
                    p_sp.sales_price,
                    p_pace.sales_price,
                    0
                ) AS DECIMAL(10,2)
            ) AS unit_price,

            /* legacy alias some views may read */
            CAST(
                COALESCE(
                    NULLIF(NULLIF(TRIM(i.unit_price), ''), 0),
                    p_sp.sales_price,
                    p_pace.sales_price,
                    0
                ) AS DECIMAL(10,2)
            ) AS price

        FROM pace_order_items i
        JOIN pace_orders o
              ON o.id = i.order_id

        /* map subject + pace -> subject_pace -> product */
        LEFT JOIN subject_pace sp
               ON sp.subject_id = i.subject_id
              AND sp.pace_number = COALESCE(NULLIF(i.pace_no, ''), NULLIF(i.pace_number, ''), 0)

        LEFT JOIN product p_sp
               ON p_sp.id = sp.product_id

        /* fallback: find product by pace_number, prefer same-branch product */
        LEFT JOIN product p_pace
               ON p_pace.pace_number = COALESCE(NULLIF(i.pace_no, ''), NULLIF(i.pace_number, ''), 0)
              AND (p_pace.branch_id = o.branch_id OR p_pace.branch_id IS NULL OR p_pace.branch_id = 0)

        WHERE i.order_id = ?
        ORDER BY i.id ASC
    ";

    $rows = $this->db->query($sql, [(int)$order_id])->result_array();

    foreach ($rows as &$r) {
        $qty   = (float)($r['qty'] ?? 0);
        $price = (float)($r['unit_price'] ?? 0);
        $total = round($qty * $price, 2);
        $r['line_total']  = number_format($total, 2, '.', '');
        $r['sales_price'] = $r['unit_price']; // extra alias if any view expects it
    }
    unset($r);

    return $rows;
}

// mark one pre-invoice order as paid
public function mark_order_paid(int $orderId, int $userId): bool
{
    // ensure we only touch current branch records
    $branchId = $this->application_model->get_branch_id();

    $data = [
        'is_paid'  => 1,
        'paid_at'  => date('Y-m-d H:i:s'),
        'paid_by'  => $userId,
    ];

    $this->db->where('id', $orderId)
             ->where('branch_id', $branchId)
             ->where('(is_paid = 0 OR is_paid IS NULL)', null, false)
             ->update($this->tbl_po, $data);

    return $this->db->affected_rows() > 0;
}

public function mark_invoice_paid(int $invoiceId, int $branchId, int $userId): bool
{
    $now = date('Y-m-d H:i:s');

    // update pre-invoice order header
    $this->db->where(['invoice_id' => $invoiceId, 'branch_id' => $branchId])
             ->update($this->tbl_po, [
                 'is_paid' => 1,
                 'paid_at' => $now,
                 'paid_by' => $userId,
             ]);

    // cascade to SAP
    $sql = "
        UPDATE {$this->tbl_sap} sap
        JOIN {$this->tbl_poi} poi ON poi.sap_id = sap.id
        JOIN {$this->tbl_po}  po  ON po.id = poi.order_id
        SET sap.paid_at = ?
        WHERE po.invoice_id = ? AND po.branch_id = ?
    ";
    $this->db->query($sql, [$now, $invoiceId, $branchId]);

    // NEW: invoice header -> PAID
    $this->db->where('id', $invoiceId)->update($this->tbl_inv, [
        'status'     => $this->inv_status_value('paid'),
        'updated_at' => $now,
    ]);

    return $this->db->affected_rows() > 0;
}


public function mark_invoice_issued(int $invoiceId, int $branchId, int $userId): bool
{
    $now = date('Y-m-d H:i:s');

    $this->db->trans_start();

    // 1) update the pre-invoice order header (pace_orders) - mirror issued flags ONLY
    $this->db->where(['invoice_id' => $invoiceId, 'branch_id' => $branchId])
             ->update($this->tbl_po, [
                 'is_issued' => 1,
                 'issued_at' => $now,
                 'issued_by' => $userId,
             ]);

    // 2) cascade to SAP rows via order items: stamp issued and set status=issued
    //    IMPORTANT: do NOT touch paid_at here.
    $sql = "
        UPDATE {$this->tbl_sap} sap
        JOIN {$this->tbl_poi} poi ON poi.sap_id = sap.id
        JOIN {$this->tbl_po}  po  ON po.id = poi.order_id
        SET
            sap.issued_at = ?,
            sap.status    = 'issued'
        WHERE po.invoice_id = ?
          AND po.branch_id  = ?
          AND sap.status IN ('ordered','paid','redo')
    ";
    $this->db->query($sql, [$now, $invoiceId, $branchId]);

    // 3) invoice header -> ISSUED (keep numeric/varchar compatible)
    $this->db->where('id', $invoiceId)->update($this->tbl_inv, [
        'status'     => $this->inv_status_value('issued'),
        'updated_at' => $now,
        // optional: if the table has issued_at, set it
    ]);
    if ($this->has_col($this->tbl_inv, 'issued_at')) {
        $this->db->where('id', $invoiceId)->update($this->tbl_inv, ['issued_at' => $now]);
    }

    $this->db->trans_complete();
    return $this->db->trans_status();
}


// === BEGIN: Redo helpers (ADD these to the class) =============================

/**
 * Resolve a unit price for a Subject + PACE# for a given branch.
 * Adjust the query to your canonical price source if needed.
 */
private function get_pace_price($branch_id, $subject_id, $pace_no)
{
    // Unify pricing through the model’s own resolvers (handles product.sales_price,
    // subject_pace→product, and global default).
    $session_id = (int)(get_session_id() ?: 0);
    return (float)$this->guess_price((int)$subject_id, (int)$pace_no, (int)$branch_id, $session_id);
}

/**
 * Create (or reuse an open) Redo order and insert a single line for this SAP row.
 * Returns [true, order_id] on success; [false, error] on failure.
 */
public function create_redo_from_sap($sap_id, $auto_check_and_invoice = false)
{
    $sap = $this->db->get_where($this->tbl_sap, ['id' => (int)$sap_id])->row_array();
    if (!$sap) return [false, 'SAP row not found'];

    $branch_id  = (int)($sap['branch_id']  ?? 0);
    $student_id = (int)($sap['student_id'] ?? 0);
    $subject_id = (int)($sap['subject_id'] ?? 0);
    $session_id = (int)($sap['session_id'] ?? 0);
    // pace number: prefer pace_number, then pace_no, then book_number
    $pace_no    = (int)($sap['pace_number'] ?? $sap['pace_no'] ?? $sap['book_number'] ?? 0);

    $this->db->trans_start();

    // ---------------------------------------------------------------------
    // 1) Reuse an OPEN header for this student (add only filters that exist)
    // ---------------------------------------------------------------------
    $this->db->from($this->tbl_po)
             ->where('branch_id',  $branch_id)
             ->where('student_id', $student_id);

    if ($this->has_col($this->tbl_po, 'status'))     $this->db->where('status', 'draft');
    if ($this->has_col($this->tbl_po, 'is_checked')) $this->db->where('is_checked', 0);
    if ($this->has_col($this->tbl_po, 'invoice_id')) $this->db->where('invoice_id IS NULL', null, false);
    if ($this->has_col($this->tbl_po, 'is_redo'))    $this->db->where('is_redo', 1);

    $open = $this->db->order_by('id', 'DESC')->limit(1)->get()->row_array();

    if ($open) {
        $order_id = (int)$open['id'];
    } else {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'branch_id'  => $branch_id,
            'student_id' => $student_id,
            'session_id' => $session_id,
            'status'     => 'draft',
            'is_checked' => 0,
            'created_at' => $now,
            'created_by' => (function_exists('get_loggedin_user_id') ? (int)get_loggedin_user_id() : 0),
            'updated_at' => $now,
        ];
        if ($this->has_col($this->tbl_po, 'is_redo')) $payload['is_redo'] = 1;

        $order_id = (int)$this->safe_insert($this->tbl_po, $payload);
    }

    // ---------------------------------------------------------------------
    // 2) Insert the redo line if it doesn't already exist for this order
    // ---------------------------------------------------------------------
    $existsWhere = [
        'order_id'   => $order_id,
        'subject_id' => $subject_id,
    ];
    if ($this->has_col($this->tbl_poi, 'pace_no'))         $existsWhere['pace_no']      = $pace_no;
    elseif ($this->has_col($this->tbl_poi, 'pace_number')) $existsWhere['pace_number'] = $pace_no;
    if ($this->has_col($this->tbl_poi, 'is_redo'))         $existsWhere['is_redo']      = 1;

    $exists = $this->db->get_where($this->tbl_poi, $existsWhere)->num_rows() > 0;

    if (!$exists) {
        $unit_price = $this->get_pace_price($branch_id, $subject_id, $pace_no);
        $item = [
            'order_id'    => $order_id,
            'subject_id'  => $subject_id,
            'qty'         => 1,
            'unit_price'  => $unit_price,
            'line_total'  => $unit_price,
            'description' => 'Redo PACE #'.$pace_no,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        // write whichever pace column exists
        if ($this->has_col($this->tbl_poi, 'pace_no'))      $item['pace_no']      = $pace_no;
        if ($this->has_col($this->tbl_poi, 'pace_number'))  $item['pace_number']  = $pace_no;
        if ($this->has_col($this->tbl_poi, 'is_redo'))      $item['is_redo']      = 1;
        // link back to SAP on the item for paid/issued cascade
        if ($this->has_col($this->tbl_poi, 'sap_id'))       $item['sap_id']       = (int)$sap_id;

        $this->safe_insert($this->tbl_poi, $item);
    }

    // ---------------------------------------------------------------------
    // 3) Link the order to the SAP row (optional column)
    // ---------------------------------------------------------------------
    if ($this->has_col($this->tbl_sap, 'redo_order_id')) {
        $this->db->where('id', (int)$sap_id)->update($this->tbl_sap, ['redo_order_id' => $order_id]);
    }

    // ---------------------------------------------------------------------
    // 4) Optional: auto create invoice now (hydrating prices first)
    // ---------------------------------------------------------------------
    if ($auto_check_and_invoice === true) {
        // ensure prices are present on items if you have a hydrator
        if (method_exists($this, 'hydrate_order_prices')) {
            $this->hydrate_order_prices($order_id);
        }
        // convert order -> invoice using the correct model
        $this->load->model('Pace_orders_model', 'pom');
        if (method_exists($this->pom, 'create_invoice_from_order')) {
            $invoice_id = $this->pom->create_invoice_from_order($order_id);
            // mark order as billed + link invoice (if columns exist)
            $upd = ['updated_at' => date('Y-m-d H:i:s')];
            if ($this->has_col($this->tbl_po, 'status'))     $upd['status']     = 'billed';
            if ($this->has_col($this->tbl_po, 'invoice_id')) $upd['invoice_id'] = (int)$invoice_id;
            $this->db->where('id', $order_id)->update($this->tbl_po, $upd);
        }
    }

    $this->db->trans_complete();
    if (!$this->db->trans_status()) return [false, 'DB error creating redo order'];

    return [true, $order_id];
}

/**
 * Returns TRUE if the redo order linked to this SAP is already issued.
 */
public function is_redo_issued($sap_id)
{
    $sap = $this->db->get_where($this->tbl_sap, ['id' => (int)$sap_id])->row_array();
    if (!$sap || empty($sap['redo_order_id'])) return false;

    $order_id = (int)$sap['redo_order_id'];
    $order    = $this->db->get_where($this->tbl_po, ['id' => $order_id])->row_array();
    if (!$order) return false;

    // 1) explicit flags on order header
    if ($this->has_col($this->tbl_po, 'is_issued') && !empty($order['is_issued'])) return true;

    // 2) textual status on order header
    if (!empty($order['status']) && strtolower($order['status']) === 'issued') return true;

    // 3) any item flagged as issued (if column exists)
    if ($this->has_col($this->tbl_poi, 'issued')) {
        $cnt = $this->db->where(['order_id' => $order_id, 'issued' => 1])->count_all_results($this->tbl_poi);
        if ($cnt > 0) return true;
    }

    return false;
}
// Fill missing/zero prices on order items from product/subject_pace/global default.
public function hydrate_order_prices(int $order_id): int
{
    $order = $this->db->get_where($this->tbl_po, ['id' => (int)$order_id])->row_array();
    if (!$order) return 0;

    $branch_id  = (int)($order['branch_id'] ?? 0);
    $session_id = (int)($order['session_id'] ?? 0);

    $paceCol = $this->resolve_column($this->tbl_poi, ['pace_no','pace_number']) ?: 'pace_no';

    $items = $this->db->select("id, subject_id, {$paceCol} AS pace_no, COALESCE(unit_price,0) AS unit_price", false)
        ->from($this->tbl_poi)
        ->where('order_id', (int)$order_id)
        ->get()->result_array();

    $fixed = 0;
    foreach ($items as $it) {
        $price = (float)$it['unit_price'];
        if ($price > 0) continue;

        $resolved = (float)$this->guess_price((int)$it['subject_id'], (int)$it['pace_no'], $branch_id, $session_id);
        $this->db->where('id', (int)$it['id'])->update($this->tbl_poi, [
            'unit_price' => $resolved,
            'line_total' => $resolved, // qty = 1
        ]);
        if ($this->db->affected_rows() > 0) $fixed++;
    }
    return $fixed;
}

// --- BEGIN NEW METHOD ---
public function list_batches_per_student($branch_id)
{
    // tables set in constructor (keeps multi-tenant normalization intact)
    $tbl_s   = 'student';
    $tbl_po  = $this->tbl_po;         // pace_orders
    $tbl_poi = $this->tbl_poi;        // pace_order_items
    $tbl_iv  = $this->tbl_inv;        // invoices (normalized to your HS table in __construct if applicable)
    $tbl_ivi = $this->tbl_inv_items;  // invoice_items
    $tbl_sap = $this->tbl_sap;        // student_assign_paces

    $sql = "
        SELECT
            s.id                              AS student_id,
            CONCAT_WS(' ', s.first_name, s.last_name) AS student_name,

            /* Ordered (incl. redo) = order items per student */
            IFNULL((
                SELECT COUNT(poi.id)
                FROM {$tbl_po} po
                JOIN {$tbl_poi} poi ON poi.order_id = po.id
                WHERE po.branch_id = ?
                  AND po.student_id = s.id
            ), 0) AS ordered_cnt,

            /* Billed/Checked = invoice items per student */
            IFNULL((
                SELECT COUNT(ivi.id)
                FROM {$tbl_iv} iv
                JOIN {$tbl_ivi} ivi ON ivi.invoice_id = iv.id
                WHERE iv.branch_id = ?
                  AND iv.student_id = s.id
            ), 0) AS billed_cnt,

            /* Paid = invoice items where invoice is paid (status=1) */
            IFNULL((
                SELECT COUNT(ivi2.id)
                FROM {$tbl_iv} iv2
                JOIN {$tbl_ivi} ivi2 ON ivi2.invoice_id = iv2.id
                WHERE iv2.branch_id = ?
                  AND iv2.student_id = s.id
                  AND (iv2.status = 1 OR LOWER(iv2.status) = 'paid')
            ), 0) AS paid_cnt,

            /* Issued = SAP rows flagged issued (supports either is_issued=1 OR status='issued') */
            IFNULL((
                SELECT COUNT(sap.id)
                FROM {$tbl_sap} sap
                WHERE sap.branch_id = ?
                  AND sap.student_id = s.id
                  AND (
                        (sap.is_issued IS NOT NULL AND sap.is_issued = 1)
                     OR (sap.status IS NOT NULL AND LOWER(sap.status) = 'issued')
                  )
            ), 0) AS issued_cnt

        FROM {$tbl_s} s
        WHERE s.branch_id = ?
        /* Only show students that have any activity */
        HAVING (ordered_cnt + billed_cnt + paid_cnt + issued_cnt) > 0
        ORDER BY s.first_name, s.last_name
    ";

    $params = [$branch_id, $branch_id, $branch_id, $branch_id, $branch_id];
    return $this->db->query($sql, $params)->result_array();
}
// === NEW: Check & Invoice that only bills (no auto paid / issued) ==============
public function check_and_invoice(int $order_id)
{
    // 0) sanity
    $order = $this->db->get_where($this->tbl_po, ['id' => (int)$order_id])->row_array();
    if (!$order) return 0;

    // 1) hydrate empty prices so invoice totals are correct
    if (method_exists($this, 'hydrate_order_prices')) {
        $this->hydrate_order_prices($order_id);
    }

    // 2) create invoice via Pace_orders_model
    $this->load->model('Pace_orders_model', 'pom');
    if (!method_exists($this->pom, 'create_invoice_from_order')) return 0;

    $invoice_id = (int)$this->pom->create_invoice_from_order($order_id);
    if ($invoice_id <= 0) return 0;

    // 3) mark ORDER as checked/billed and link invoice (do NOT mark paid/issued)
    $upd_po = [
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($this->has_col($this->tbl_po, 'is_checked'))  $upd_po['is_checked']  = 1;
    if ($this->has_col($this->tbl_po, 'checked_at'))  $upd_po['checked_at']  = date('Y-m-d H:i:s');
    if ($this->has_col($this->tbl_po, 'checked_by'))  $upd_po['checked_by']  = (int)($this->session->userdata('loggedin_id') ?? 0);
    if ($this->has_col($this->tbl_po, 'status'))      $upd_po['status']      = 'billed';
    if ($this->has_col($this->tbl_po, 'invoice_id'))  $upd_po['invoice_id']  = $invoice_id;

    $this->db->where('id', (int)$order_id)->update($this->tbl_po, $upd_po);

    // 4) set INVOICE header status to "billed" ONLY (never paid/issued here)
    $this->db->where('id', $invoice_id)->update($this->tbl_inv, [
        'status'     => $this->inv_status_value('billed'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return $invoice_id;
}



}
