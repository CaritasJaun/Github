<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Pace_orders_model extends CI_Model
{
    public function __construct() { parent::__construct(); }

    /* ---------- READ ---------- */
public function get_batches()
{
    // Branch filter (only if the column exists)
    $ordersTbl = 'pace_orders';
    $itemsTbl  = 'pace_order_items';

    $branchId = (int)(function_exists('get_loggedin_branch_id') ? get_loggedin_branch_id() : 0);

    // created column (created_at | created)
    $createdCol = $this->db->field_exists('created_at', $ordersTbl) ? 'created_at'
                 : ($this->db->field_exists('created', $ordersTbl) ? 'created' : 'created_at');

    // base select
   $extraCols = [];
        if ($this->db->field_exists('is_paid',   $ordersTbl)) $extraCols[] = 'is_paid';
        if ($this->db->field_exists('is_issued', $ordersTbl)) $extraCols[] = 'is_issued';
        $extra = $extraCols ? ', '.implode(',', $extraCols) : '';
        
        $this->db->select("id, branch_id, student_id, {$createdCol} AS created_at, status, is_checked, invoice_id{$extra}", false)
                 ->from($ordersTbl);

    if ($branchId > 0 && $this->db->field_exists('branch_id', $ordersTbl)) {
        $this->db->where('branch_id', $branchId);
    }

    $orders = $this->db->order_by($createdCol,'DESC')->get()->result_array();
    if (!$orders) return [];

    $ids = array_map('intval', array_column($orders, 'id'));

    // ---- Items summary (qty column may be qty or quantity)
    $qtyCol = $this->db->field_exists('qty', $itemsTbl) ? 'qty'
            : ($this->db->field_exists('quantity', $itemsTbl) ? 'quantity' : 'qty');

    $sumMap = [];
    if ($ids) {
        $sums = $this->db->select("order_id, COUNT(*) AS total_lines, COALESCE(SUM({$qtyCol}),0) AS total_qty", false)
                         ->from($itemsTbl)
                         ->where_in('order_id', $ids)
                         ->group_by('order_id')
                         ->get()->result_array();
        foreach ($sums as $s) {
            $sumMap[(int)$s['order_id']] = [
                'total'   => (int)$s['total_lines'],
                'ordered' => (int)$s['total_qty'],
            ];
        }
    }

    // ---- Invoice status lookup (INT or VARCHAR)
    $invIds = array_values(array_filter(array_map('intval', array_column($orders,'invoice_id'))));
    $invMap = [];
    if ($invIds) {
        $invTable = $this->db->table_exists('hs_academy_invoices') ? 'hs_academy_invoices'
                   : ($this->db->table_exists('invoices') ? 'invoices' : null);
        if ($invTable) {
            $invRows = $this->db->select('id, status')->from($invTable)->where_in('id', $invIds)->get()->result_array();
            foreach ($invRows as $r) $invMap[(int)$r['id']] = $r['status']; // may be INT or string
        }
    }

    // INT → label map (kept consistent with your other code)
    $statusMap = ['draft','paid','issued','billed','redo'];

    $out = [];
    foreach ($orders as $o) {
        $summary = $sumMap[(int)$o['id']] ?? ['total'=>0,'ordered'=>0];
        $ordered = (int)$summary['ordered'];

        // "Billed" means the order has been checked/billed and linked to an invoice
        $hasInvoice = !empty($o['invoice_id']);
        $billed     = $hasInvoice ? $ordered : 0;

        // Normalize invoice status
        $invStatusRaw = $invMap[(int)($o['invoice_id'] ?? 0)] ?? null;
        if (is_numeric($invStatusRaw)) {
            $invLabel = $statusMap[(int)$invStatusRaw] ?? 'draft';
        } else {
            $invLabel = strtolower((string)$invStatusRaw);
        }

       // Strict precedence: order flags (if present) → else invoice label
                $paidFlag   = array_key_exists('is_paid', $o)   ? (int)$o['is_paid']   : 0;
                $issuedFlag = array_key_exists('is_issued', $o) ? (int)$o['is_issued'] : 0;
                
                if ($paidFlag === 1) {
                    $paid = $ordered;
                } elseif ($invLabel === 'paid') {
                    $paid = $ordered;
                } else {
                    $paid = 0;
                }
                
                if ($issuedFlag === 1) {
                    $issued = $ordered;
                } elseif ($invLabel === 'issued') {
                    $issued = $ordered;
                } else {
                    $issued = 0;
                }

        $out[] = [
            'id'         => (int)$o['id'],
            'created'    => $o['created_at'],
            'student'    => $this->student_display_name((int)$o['student_id']),
            'total'      => (int)$summary['total'],
            'ordered'    => $ordered,
            'billed'     => $billed,               // new column
            'paid'       => $paid,
            'issued'     => $issued,
            'is_checked' => (int)($o['is_checked'] ?? 0),
            'status'     => $o['status'] ?? 'draft',
            'invoice_id' => (int)($o['invoice_id'] ?? 0),
        ];
    }

    return $out;
}



    /* --- helper: find a readable student name, or fallback to "#ID" --- */
    private function student_display_name(int $studentId): string
    {
        // Try common tables/columns, but never error if they don't exist.
        if ($this->db->table_exists('student')) {
            $q = $this->db->select('*')->get_where('student', ['id' => $studentId], 1)->row_array();
            if ($q) {
                $first = $q['first_name'] ?? $q['firstname'] ?? $q['name'] ?? '';
                $last  = $q['last_name']  ?? $q['lastname']  ?? $q['surname'] ?? '';
                $full  = trim($first.' '.$last);
                if ($full !== '') return $full;
            }
        }
        if ($this->db->table_exists('enroll')) {
            $q = $this->db->select('*')->get_where('enroll', ['student_id' => $studentId], 1)->row_array();
            if ($q && isset($q['student_name']) && $q['student_name'] !== '') {
                return $q['student_name'];
            }
        }
        return '#'.$studentId;
    }

    public function get_order($id)
    {
        $id = (int)$id;

        // Build a robust student name expression
        $nameExpr = "TRIM(CONCAT(COALESCE(st.first_name,''),' ',COALESCE(st.last_name,'')))";
        if ($this->db->field_exists('name', 'student')) {
            $nameExpr = 'st.name';
        } elseif ($this->db->field_exists('full_name', 'student')) {
            $nameExpr = 'st.full_name';
        }

        return $this->db->select("po.*, {$nameExpr} AS student_name", false)
            ->from('pace_orders AS po')
            ->join('student AS st', 'st.id = po.student_id', 'left')
            ->where('po.id', $id)
            ->get()->row_array();
    }

    /** Resolve unit price from product table (with branch fallback). */
    private function resolve_unit_price(int $order_id, int $subject_id, int $pace_number): float
    {
        $branch_id = (int)$this->db->select('branch_id')
            ->from('pace_orders')->where('id', $order_id)->get()->row('branch_id');

        // Subject column may be subject_id or category_id
        $subjectCol = $this->db->field_exists('subject_id', 'product') ? 'subject_id'
                   : ($this->db->field_exists('category_id', 'product') ? 'category_id' : null);

        if (!$this->db->table_exists('product') || !$subjectCol ||
            !$this->db->field_exists('pace_number', 'product') ||
            !$this->db->field_exists('sales_price', 'product')) {
            return 0.0;
        }

        // Try branch specific
        $row = null;
        if ($this->db->field_exists('branch_id', 'product')) {
            $row = $this->db->select('sales_price')->from('product')
                ->where('pace_number', $pace_number)
                ->where($subjectCol, $subject_id)
                ->where('branch_id', $branch_id)
                ->order_by('id', 'DESC')->limit(1)->get()->row_array();
        }
        // Fallback any branch
        if (!$row) {
            $row = $this->db->select('sales_price')->from('product')
                ->where('pace_number', $pace_number)
                ->where($subjectCol, $subject_id)
                ->order_by('id', 'DESC')->limit(1)->get()->row_array();
        }

        if ($row && $row['sales_price'] !== '' && $row['sales_price'] !== null) {
            return (float)$row['sales_price'];
        }

        // Last fallback: global default
        if ($this->db->table_exists('global_settings') &&
            $this->db->field_exists('pace_default_price', 'global_settings')) {
            $gs = $this->db->limit(1)->get('global_settings')->row_array();
            return (float)($gs['pace_default_price'] ?? 0);
        }
        return 0.0;
    }

    public function get_order_items($order_id)
    {
        $order_id = (int)$order_id;
        if ($order_id <= 0) return [];

        // Resolve a subject "abbrev" column safely
        $subjectCodeCol = "''";
        if ($this->db->field_exists('code', 'subject')) {
            $subjectCodeCol = 's.code';
        } elseif ($this->db->field_exists('subject_code', 'subject')) {
            $subjectCodeCol = 's.subject_code';
        } elseif ($this->db->field_exists('abbr', 'subject')) {
            $subjectCodeCol = 's.abbr';
        } elseif ($this->db->field_exists('short_name', 'subject')) {
            $subjectCodeCol = 's.short_name';
        }

        $this->db->select("
            poi.*,
            s.name AS subject,
            {$subjectCodeCol} AS subject_abbrev,
            sap.student_id,
            COALESCE(poi.pace_number, sap.pace_number) AS pace_number
        ", false)
        ->from('pace_order_items AS poi')
        ->join('student_assign_paces AS sap', 'sap.id = poi.sap_id', 'left')
        ->join('subject AS s', 's.id = IFNULL(poi.subject_id, sap.subject_id)', 'left', false)
        ->where('poi.order_id', $order_id)
        ->order_by('poi.id', 'ASC');

        $q = $this->db->get();
        if ($q === false) {
            log_message('error', 'get_order_items() failed. SQL: '.$this->db->last_query().' | err: '.print_r($this->db->error(), true));
            return [];
        }

        $rows = $q->result_array();

        // Ensure subject and price/total are present
        foreach ($rows as &$r) {
            if (empty($r['subject']) && !empty($r['subject_name'])) {
                $r['subject'] = $r['subject_name'];
            }
            // If price is empty/zero, resolve from product now (for display)
            $u = isset($r['unit_price']) ? (float)$r['unit_price'] : 0.0;
            if ($u <= 0.0) {
                $u = $this->resolve_unit_price($order_id, (int)$r['subject_id'], (int)$r['pace_number']);
                $r['unit_price'] = $u;
            }
            // Line total snapshot for the view
            $qty = max(0, (int)($r['qty'] ?? 1));
            $r['line_total'] = $qty * $u;
        }
        return $rows;
    }

    /* ---------- WRITE ---------- */
    public function can_edit(array $order)
    {
        return ((int)$order['is_checked'] === 0 && empty($order['invoice_id']));
    }

    public function update_items($order_id, array $items, $editor_user_id)
    {
        $order_id = (int)$order_id;

        foreach ($items as $row) {
            $item_id = (int)$row['item_id'];
            $qty     = max(0, (int)$row['qty']);
            $redo    = isset($row['is_redo']) ? (int)$row['is_redo'] : 0;
            $desc    = isset($row['description']) ? trim($row['description']) : null;

            // Get immutable fields we need to price correctly
            $base = $this->db->select('subject_id, COALESCE(pace_number, pace_no) AS pace_number', false)
                ->from('pace_order_items')->where(['id' => $item_id, 'order_id' => $order_id])
                ->get()->row_array();
            if (!$base) continue;

            $unit_price = $this->resolve_unit_price($order_id, (int)$base['subject_id'], (int)$base['pace_number']);

            $this->db->where(['id' => $item_id, 'order_id' => $order_id])
                     ->update('pace_order_items', [
                        'qty'            => $qty,
                        'unit_price'     => $unit_price,               // <-- ignore posted price
                        'line_total'     => $qty * $unit_price,
                        'is_redo'        => $redo,
                        'description'    => $desc,
                        'last_edited_by' => (int)$editor_user_id,
                        'last_edited_at' => date('Y-m-d H:i:s'),
                     ]);
        }
    }

    public function link_invoice($order_id, $invoice_id)
    {
        $this->db->where('id', (int)$order_id)->update('pace_orders', [
            'invoice_id' => (int)$invoice_id,
            'status'     => 'invoiced',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affected_rows() > 0;
    }

    public function get_batches_pre(): array
    {
        $branchId = (int) get_loggedin_branch_id();

        // --- Figure out which student table/columns exist ---
        $studentTbl = $this->db->table_exists('student') ? 'student'
                     : ($this->db->table_exists('students') ? 'students' : null);

        $nameExpr = "NULL";
        if ($studentTbl) {
            // Pick sensible first/last name columns if they exist
            $first = null;
            foreach (['first_name','firstname','given_name','name'] as $c) {
                if ($this->db->field_exists($c, $studentTbl)) { $first = "s.`{$c}`"; break; }
            }
            $last = null;
            foreach (['last_name','lastname','surname'] as $c) {
                if ($this->db->field_exists($c, $studentTbl)) { $last = "s.`{$c}`"; break; }
            }
            if ($first && $last)      $nameExpr = "TRIM(CONCAT_WS(' ', {$first}, {$last}))";
            elseif ($first)           $nameExpr = $first;
            elseif ($last)            $nameExpr = $last;
        }

        // --- Build the query ---
        $this->db->select("
                o.id,
                o.created_at,
                o.student_id,
                o.invoice_id,
                o.is_checked,
                o.status,
                COALESCE(SUM(i.line_total),0) AS total,
                COUNT(i.id) AS ordered" . ($studentTbl ? ", {$nameExpr} AS student_name" : "") , false)
            ->from('pace_orders AS o')
            ->join('pace_order_items AS i', 'i.order_id = o.id', 'left');

        if ($studentTbl) {
            $this->db->join("$studentTbl AS s", 's.id = o.student_id', 'left');
        }

        $q = $this->db
            ->where('o.branch_id', $branchId)
            ->where('o.status', 'draft')      // only open pre-invoice batches
            ->group_by('o.id')
            ->order_by('o.created_at', 'DESC')
            ->get();

        if ($q === false) {
            log_message('error', 'get_batches_pre() failed: '.$this->db->last_query().' | '.print_r($this->db->error(), true));
            return [];
        }

        $rows = $q->result_array();
        foreach ($rows as &$r) { $r['paid'] = 0; $r['issued'] = 0; }
        return $rows;
    }

    public function resolve_price($subject_id, $pace_no, $branch_id = null)
    {
        $branch_id = $branch_id ?: $this->application_model->get_branch_id();
        $sql = "
            SELECT p.sales_price
            FROM subject_pace sp
            JOIN product p ON p.id = sp.product_id
            WHERE sp.subject_id = ?
              AND sp.pace_number = ?
              AND (p.branch_id = ? OR p.branch_id IS NULL OR p.branch_id = 0)
            ORDER BY (p.branch_id = ?) DESC, p.id DESC
            LIMIT 1
        ";
        $row = $this->db->query($sql, [(int)$subject_id, (int)$pace_no, (int)$branch_id, (int)$branch_id])->row_array();
        return (float)($row['sales_price'] ?? 0);
    }

    // Pre-invoice batches with computed totals (qty * resolved price)
    public function get_pre_batches($branch_id = null)
    {
        $branch_id = $branch_id ?: $this->application_model->get_branch_id();

        $sql = "
            SELECT
                o.id,
                o.created_at,
                o.created,                      -- keep both; some schemas use created, others created_at
                o.student_id,
                CONCAT(st.first_name, ' ', st.last_name) AS student_name,
                COALESCE(SUM(i.qty), 0) AS ordered_qty,
                /* Sum over items: item.unit_price if set, else product via subject_pace, else product by pace_number (branch-aware) */
                CAST(
                    COALESCE(
                        SUM(
                            (i.qty) * COALESCE(
                                NULLIF(NULLIF(TRIM(i.unit_price), ''), 0),
                                p_sp.sales_price,
                                p_pace.sales_price,
                                0
                            )
                        ),
                        0
                    ) AS DECIMAL(10,2)
                ) AS grand_total,
                MAX(COALESCE(o.is_checked, 0)) AS is_checked
            FROM pace_orders o
            LEFT JOIN student st
                   ON st.id = o.student_id
            LEFT JOIN pace_order_items i
                   ON i.order_id = o.id

            /* subject + pace -> subject_pace -> product */
            LEFT JOIN subject_pace sp
                   ON sp.subject_id = i.subject_id
                  AND sp.pace_number = COALESCE(NULLIF(i.pace_no, ''), NULLIF(i.pace_number, ''), 0)
            LEFT JOIN product p_sp
                   ON p_sp.id = sp.product_id

            /* fallback: product by pace_number; prefer same-branch product */
            LEFT JOIN product p_pace
                   ON p_pace.pace_number = COALESCE(NULLIF(i.pace_no, ''), NULLIF(i.pace_number, ''), 0)
                  AND (p_pace.branch_id = o.branch_id OR p_pace.branch_id IS NULL OR p_pace.branch_id = 0)

            WHERE o.branch_id = ?
            GROUP BY o.id
            ORDER BY o.id DESC
        ";

        return $this->db->query($sql, [(int)$branch_id])->result_array();
    }

    // Update one line in pace_order_items (qty/redo/description)
    public function save_item_qty($order_id, $item_id, $qty, $is_redo = 0, $description = '')
    {
        $data = [
            'qty'         => (int)$qty,
            'is_redo'     => (int)$is_redo,
            'description' => $description,
        ];
        $this->db->where('id', (int)$item_id)
                 ->where('order_id', (int)$order_id)
                 ->update('pace_order_items', $data);
    }

    public function delete_item($order_id, $item_id)
    {
        $this->db->where('id', (int)$item_id)
                 ->where('order_id', (int)$order_id)
                 ->delete('pace_order_items');
    }

    public function mark_checked($order_id, $note = '')
    {
        $order_id = (int)$order_id;
        $data = ['is_checked' => 1];
        if ($this->db->field_exists('checker_note', 'pace_orders')) $data['checker_note'] = $note;
        if ($this->db->field_exists('checked_at', 'pace_orders'))   $data['checked_at']   = date('Y-m-d H:i:s');
        $this->db->where('id', $order_id)->update('pace_orders', $data);
    }
public function create_invoice_from_order($order_id)
{
    $order_id = (int)$order_id;
    if ($order_id <= 0) return false;

    // --- helpers -----------------------------------------------------------
    $pickTable = function(array $candidates) {
        foreach ($candidates as $t) if ($this->db->table_exists($t)) return $t;
        return null;
    };
    $pickField = function(array $fields, array $options) {
        foreach ($options as $opt) if (in_array($opt, $fields, true)) return $opt;
        return null;
    };
    $now = date('Y-m-d H:i:s');

    // --- resolve tables (prefer hs_* schema used by the UI) ----------------
    $ordersHeaderTable = $pickTable(['pace_orders','orders_batches','pace_orders_batches','order_batches']);
    $orderItemsTable   = $pickTable(['pace_order_items','order_paces']);
    $invTable          = $pickTable(['hs_academy_invoices','invoices','invoice']);
    $invItemsTable     = $pickTable(['hs_academy_invoice_items','invoice_items']);

    if (!$ordersHeaderTable || !$orderItemsTable || !$invTable || !$invItemsTable) {
        log_message('error','create_invoice_from_order: required table missing');
        return false;
    }

    // --- ensure every order line has a non-zero price BEFORE we convert ----
    $CI = &get_instance();
    $CI->load->model('Pace_order_workflow_model', 'pow');
    if (method_exists($CI->pow, 'hydrate_order_prices')) {
        $CI->pow->hydrate_order_prices($order_id);
    }

    // --- read batch header -------------------------------------------------
    $batch = $this->db->get_where($ordersHeaderTable, ['id' => $order_id])->row_array();
    if (!$batch) return false;

    // --- read items from whichever items table we have ---------------------
    $oif = $this->db->list_fields($orderItemsTable);
    $orderIdKey = $pickField($oif, ['order_id','batch_id']) ?: 'order_id';
    $items = $this->db->get_where($orderItemsTable, [$orderIdKey => $order_id])->result_array();
    if (!$items) $items = []; // still create header

    // --- create invoice header (status = billed AT INSERT) -----------------
    $invFields = $this->db->list_fields($invTable);

    $invoice = [
        'branch_id'  => (int)($batch['branch_id']  ?? get_loggedin_branch_id()),
        'student_id' => (int)($batch['student_id'] ?? 0),
        'session_id' => (int)($batch['session_id'] ?? get_session_id()),
        'created_at' => in_array('created_at', $invFields, true) ? $now : null,
        'updated_at' => in_array('updated_at', $invFields, true) ? $now : null,
        'total'      => in_array('total',      $invFields, true) ? 0   : null,
        'status'     => in_array('status',     $invFields, true) ? $this->inv_status_value('billed') : null,
    ];
    if (in_array('paid_at',   $invFields, true)) $invoice['paid_at']   = null;
    if (in_array('issued_at', $invFields, true)) $invoice['issued_at'] = null;
    if (in_array('is_redo',   $invFields, true)) $invoice['is_redo']   = 0;

    // strip null keys and insert
    $clean = [];
    foreach ($invoice as $k => $v) if ($v !== null) $clean[$k] = $v;

    $this->db->insert($invTable, $clean);
    $err = $this->db->error();
    if ($err['code']) {
        log_message('error','create_invoice_from_order header: '.$err['message']);
        return false;
    }
    $invoice_id = (int)$this->db->insert_id();

    // --- link invoice_id back to the order header --------------------------
    $bf = $this->db->list_fields($ordersHeaderTable);
    if (in_array('invoice_id',$bf,true)) {
        $upd = ['invoice_id' => $invoice_id, 'updated_at' => $now];
        if (in_array('status',$bf,true)) {
            // keep order visible as invoiced/billed in your batches
            $upd['status'] = 'invoiced';
        }
        $this->db->where('id', $order_id)->update($ordersHeaderTable, $upd);
    }

    // --- insert invoice items ----------------------------------------------
    $iif = $this->db->list_fields($invItemsTable);

    $paceKeyOnItem = $pickField($oif, ['pace_number','pace_no','book_number']);
    $qtyKeyOnItem  = $pickField($oif, ['qty','quantity']);
    $priceKeyItem  = $pickField($oif, ['unit_price','price']);
    $lineKeyItem   = $pickField($oif, ['line_total','total']);

    $paceKeyOnInv  = $pickField($iif, ['pace_number','pace_no','book_number']);
    $qtyKeyOnInv   = $pickField($iif, ['qty','quantity']);
    $priceKeyInv   = $pickField($iif, ['unit_price','price']);
    $lineKeyInv    = $pickField($iif, ['line_total','total']);

    $sum = 0.0;

    foreach ($items as $it) {
        $paceVal = $paceKeyOnItem && isset($it[$paceKeyOnItem]) ? (int)$it[$paceKeyOnItem] : 0;
        $qtyVal  = $qtyKeyOnItem  && isset($it[$qtyKeyOnItem])  ? (int)$it[$qtyKeyOnItem]  : 1;

        // price: prefer item.unit_price; else resolve from product
        $u = ($priceKeyItem && isset($it[$priceKeyItem])) ? (float)$it[$priceKeyItem] : 0.0;
        if ($u <= 0.0) {
            $u = $this->resolve_unit_price($order_id, (int)($it['subject_id'] ?? 0), $paceVal);
        }
        $line = $qtyVal * $u;

        $row = [
            'invoice_id' => $invoice_id,
            'sap_id'     => isset($it['sap_id'])     ? (int)$it['sap_id']     : null,
            'subject_id' => isset($it['subject_id']) ? (int)$it['subject_id'] : null,
            'created_at' => in_array('created_at',$iif,true) ? $now : null,
        ];
        if ($paceKeyOnInv) $row[$paceKeyOnInv] = $paceVal;
        if ($qtyKeyOnInv)  $row[$qtyKeyOnInv]  = $qtyVal;
        if ($priceKeyInv)  $row[$priceKeyInv]  = $u;
        if ($lineKeyInv)   $row[$lineKeyInv]   = $line;
        if (in_array('is_redo',$iif,true)) $row['is_redo'] = isset($it['is_redo']) ? (int)$it['is_redo'] : 0;

        // description if available
        $descCol = $pickField($iif, ['description','notes','note','label','item_name','title']);
        if ($descCol && empty($row[$descCol])) {
            $subjectName = '';
            if (!empty($row['subject_id'])) {
                $subjectName = (string)$this->db->select('name')
                    ->get_where('subject',['id'=>$row['subject_id']])->row('name');
            }
            $row[$descCol] = trim(($subjectName ?: 'Subject').' PACE '.($paceVal ?: ''));
        }

        $payload = [];
        foreach ($row as $k=>$v) if ($v !== null) $payload[$k] = $v;

        $this->db->insert($invItemsTable, $payload);
        $err = $this->db->error();
        if ($err['code']) {
            log_message('error','create_invoice_from_order item: '.$err['message']);
        } else {
            $sum += $line;
        }
    }

    // --- update invoice total ---------------------------------------------
    if (in_array('total',$invFields,true)) {
        $this->db->where('id',$invoice_id)->update($invTable, ['total'=>$sum, 'updated_at'=>$now]);
    }

    return $invoice_id;
}



    /* ====== INVOICE → STATUS ====== */

    // inside class Pace_orders_model

    private const INV_DRAFT  = 0;
    private const INV_PAID   = 1;
    private const INV_ISSUED = 2;
    private const INV_BILLED = 3; 

public function invoice_mark_paid(int $invoice_id): bool
{
    $now = date('Y-m-d H:i:s');
    $this->db->trans_start();

    // 1) Header → PAID
    $this->db->where('id', $invoice_id)->update('hs_academy_invoices', [
        'status'     => self::INV_PAID,   // 1
        'updated_at' => $now,
    ]);

    // 2) Lines → stamp SAP.paid_at (don’t change SAP.status here)
    $sap_ids = array_column(
        $this->db->select('sap_id')->from('hs_academy_invoice_items')
                 ->where('invoice_id', $invoice_id)->get()->result_array(),
        'sap_id'
    );
    if ($sap_ids) {
        $this->db->where_in('id', array_map('intval', $sap_ids))
                 ->where('paid_at IS NULL', null, false)
                 ->update('student_assign_paces', ['paid_at' => $now]);
    }

    // 3) Linked pre-invoice order (if any) → mark paid
    $this->db->where('invoice_id', $invoice_id)
             ->update('pace_orders', ['is_paid' => 1, 'paid_at' => $now]);

    $this->db->trans_complete();
    return $this->db->trans_status();
}



    public function invoice_mark_issued($invoice_id)
{
    $invoice_id = (int)$invoice_id;
    $now = date('Y-m-d H:i:s');

    // invoice
    $data = ['status' => self::INV_ISSUED, 'updated_at' => $now];
    if ($this->db->field_exists('issued_at', 'hs_academy_invoices')) $data['issued_at'] = $now;
    $this->db->where('id', $invoice_id)->update('hs_academy_invoices', $data);

    // order mirror
    if ($this->db->field_exists('invoice_id','pace_orders')) {
        $ord = $this->db->get_where('pace_orders', ['invoice_id' => $invoice_id])->row_array();
        if ($ord) {
            $ou = [];
            if ($this->db->field_exists('status','pace_orders'))     $ou['status']    = 'issued';
            if ($this->db->field_exists('is_issued','pace_orders'))  $ou['is_issued'] = 1;
            if ($this->db->field_exists('issued_at','pace_orders'))  $ou['issued_at'] = $now;
            if ($ou) $this->db->where('id', (int)$ord['id'])->update('pace_orders', $ou);
        }
    }

// ---- SAP update (strong 3-step fallback) ----
$sapIds = $this->sap_ids_for_invoice_full($invoice_id);
if (!empty($sapIds) && $this->db->table_exists('student_assign_paces')) {
    $upd = $this->sap_status_update_array('issued', $now);
    if (!empty($upd)) {
        $this->db->where_in('id', $sapIds)->update('student_assign_paces', $upd);
    }
}

    return true;
}

// add this private helper inside Pace_orders_model
private function sap_ids_for_invoice(int $invoice_id): array
{
    $ids = [];

    if ($this->db->table_exists('hs_academy_invoice_items')
        && $this->db->field_exists('sap_id','hs_academy_invoice_items')) {
        $rows = $this->db->select('sap_id')
            ->from('hs_academy_invoice_items')
            ->where('invoice_id', $invoice_id)
            ->where('sap_id IS NOT NULL', null, false)
            ->get()->result_array();
        if ($rows) $ids = array_map('intval', array_column($rows, 'sap_id'));
    }

    // Fallback via the original order if invoice items didn’t carry sap_id
    if (empty($ids) && $this->db->field_exists('invoice_id','pace_orders')) {
        $o = $this->db->get_where('pace_orders', ['invoice_id' => $invoice_id])->row_array();
        if ($o && $this->db->table_exists('pace_order_items')
            && $this->db->field_exists('sap_id','pace_order_items')) {

            $rows = $this->db->select('sap_id')
                ->from('pace_order_items')
                ->where('order_id', (int)$o['id'])
                ->where('sap_id IS NOT NULL', null, false)
                ->get()->result_array();

            if ($rows) $ids = array_map('intval', array_column($rows, 'sap_id'));
        }
    }

    // unique, non-zero
    return array_values(array_filter(array_unique($ids)));
}


    public function order_mark_issued($order_id)
{
    $order_id = (int)$order_id;
    $now = date('Y-m-d H:i:s');

    // Load order header (we need student_id and invoice_id later)
    $ord = $this->db->get_where('pace_orders', ['id' => $order_id])->row_array();

    // 1) Mark the order as issued
    $ou = [];
    if ($this->db->field_exists('status',    'pace_orders')) $ou['status']    = 'issued';
    if ($this->db->field_exists('is_issued', 'pace_orders')) $ou['is_issued'] = 1;
    if ($this->db->field_exists('issued_at', 'pace_orders')) $ou['issued_at'] = $now;
    if (!empty($ou)) $this->db->where('id', $order_id)->update('pace_orders', $ou);

    // 2) Mirror to invoice if linked (numeric status + timestamp)
    if (!empty($ord['invoice_id'])) {
        $invUpd = ['status' => self::INV_ISSUED, 'updated_at' => $now];
        if ($this->db->field_exists('issued_at', 'hs_academy_invoices')) {
            $invUpd['issued_at'] = $now;
        }
        $this->db->where('id', (int)$ord['invoice_id'])->update('hs_academy_invoices', $invUpd);
    }

    // 3) ALSO mark the related student_assign_paces as issued
    // 3a) Collect SAP IDs from order items (prefer direct sap_id)
    $sapIds = [];
    $items = $this->db->select('sap_id, subject_id, COALESCE(pace_number, pace_no) AS pace_no', false)
        ->from('pace_order_items')
        ->where('order_id', $order_id)
        ->get()->result_array();

    if ($items) {
        // direct sap_id link if present
        $sapIds = array_values(array_filter(array_map('intval', array_column($items, 'sap_id'))));

        // fallback: match by student + subject_id + pace number in SAP if no sap_id links
        if (empty($sapIds) && !empty($ord['student_id']) && $this->db->table_exists('student_assign_paces')) {
            // detect which pace column SAP uses
            $paceCol = $this->db->field_exists('pace_number', 'student_assign_paces') ? 'pace_number'
                     : ($this->db->field_exists('pace_no', 'student_assign_paces')   ? 'pace_no'
                     : ($this->db->field_exists('book_number', 'student_assign_paces') ? 'book_number' : 'pace_number'));

            $this->db->select('id')
                ->from('student_assign_paces')
                ->where('student_id', (int)$ord['student_id']);

            // OR chain for (subject_id + pace)
            $this->db->group_start();
            foreach ($items as $it) {
                $sid = (int)($it['subject_id'] ?? 0);
                $pn  = (int)($it['pace_no'] ?? 0);
                if ($sid > 0 && $pn > 0) {
                    $this->db->or_group_start()
                             ->where('subject_id', $sid)
                             ->where($paceCol, $pn)
                             ->group_end();
                }
            }
            $this->db->group_end();

            $rows = $this->db->get()->result_array();
            if ($rows) $sapIds = array_values(array_filter(array_map('intval', array_column($rows, 'id'))));
        }
    }

    if (!empty($sapIds) && $this->db->table_exists('student_assign_paces')) {
        // Build update payload that works for either varchar or int status columns
        $upd = [];
        if ($this->db->field_exists('issued_at', 'student_assign_paces')) $upd['issued_at'] = $now;
        if ($this->db->field_exists('is_issued', 'student_assign_paces')) $upd['is_issued'] = 1;

        // status / pace_status: detect type (int vs varchar) and set accordingly
        foreach (['status', 'pace_status'] as $col) {
            if ($this->db->field_exists($col, 'student_assign_paces')) {
                $colInfo = $this->db->query(
                    "SHOW COLUMNS FROM student_assign_paces LIKE " . $this->db->escape($col)
                )->row_array();
                $isInt = $colInfo && preg_match('/int/i', (string)$colInfo['Type']);
                $upd[$col] = $isInt ? 2 : 'issued'; // 0=ordered, 1=paid, 2=issued
            }
        }

        if (!empty($upd)) {
            $this->db->where_in('id', $sapIds)->update('student_assign_paces', $upd);
        }
    }

    return true;
}
    
    /** Find SAP IDs for an invoice even when sap_id is not saved on invoice/order items. */
private function collect_sap_ids_from_invoice(int $invoice_id): array
{
    $invoice_id = (int)$invoice_id;

    // get header (student/session/branch)
    $inv = $this->db->select('*')
        ->from('hs_academy_invoices')
        ->where('id', $invoice_id)
        ->limit(1)->get()->row_array();
    if (!$inv) return [];

    $student_id = (int)($inv['student_id'] ?? 0);
    if ($student_id <= 0) return [];

    $session_id = $inv['session_id'] ?? null;
    $branch_id  = $inv['branch_id']  ?? null;

    // get items with subject + pace
    $items = $this->db->select('subject_id, COALESCE(pace_number, pace_no) AS pace_no', false)
        ->from('hs_academy_invoice_items')
        ->where('invoice_id', $invoice_id)
        ->get()->result_array();
    if (!$items) return [];

    // build an OR chain of (subject_id, pace_no)
    $this->db->select('id')->from('student_assign_paces');
    $this->db->where('student_id', $student_id);

    // optional filters if columns exist
    if ($session_id !== null && $this->db->field_exists('session_id', 'student_assign_paces')) {
        $this->db->where('session_id', (int)$session_id);
    }
    if ($branch_id !== null && $this->db->field_exists('branch_id', 'student_assign_paces')) {
        $this->db->where('branch_id', (int)$branch_id);
    }

    // status filter: only rows we expect to move forward from
    if ($this->db->field_exists('status', 'student_assign_paces')) {
        $this->db->where_in('status', ['ordered', 'paid', 'issued']); // safe superset
    }

    // group OR (subject_id + pace_number)
    $this->db->group_start();
    foreach ($items as $it) {
        $sid = (int)($it['subject_id'] ?? 0);
        $pn  = (int)($it['pace_no'] ?? 0);
        if ($sid > 0 && $pn > 0) {
            $this->db->or_group_start()
                     ->where('subject_id', $sid)
                     ->where('pace_number', $pn)
                     ->group_end();
        }
    }
    $this->db->group_end();

    $rows = $this->db->get()->result_array();
    return array_map('intval', array_column($rows, 'id'));
}

/** Pick the correct pace column in student_assign_paces (pace_number | pace_no | book_number). */
private function sap_pace_col(): string
{
    foreach (['pace_number','pace_no','book_number'] as $c) {
        if ($this->db->field_exists($c, 'student_assign_paces')) return $c;
    }
    return 'pace_number';
}

/** Build the correct UPDATE payload for SAP rows regardless of schema (int vs string status, etc.). */
private function sap_status_update_array(string $to, string $now): array
{
    $upd = [];

    // timestamps
    if ($to === 'paid'   && $this->db->field_exists('paid_at',   'student_assign_paces')) $upd['paid_at']   = $now;
    if ($to === 'issued' && $this->db->field_exists('issued_at', 'student_assign_paces')) $upd['issued_at'] = $now;
    if ($this->db->field_exists('updated_at', 'student_assign_paces')) $upd['updated_at'] = $now;

    // booleans if present
    if ($to === 'paid'   && $this->db->field_exists('is_paid',   'student_assign_paces')) $upd['is_paid']   = 1;
    if ($to === 'issued' && $this->db->field_exists('is_issued', 'student_assign_paces')) $upd['is_issued'] = 1;

    // status / pace_status (supports int or varchar)
    foreach (['status','pace_status'] as $col) {
        if (!$this->db->field_exists($col, 'student_assign_paces')) continue;
        $colInfo = $this->db->query("SHOW COLUMNS FROM student_assign_paces LIKE ".$this->db->escape($col))->row_array();
        $isInt   = $colInfo && preg_match('/int/i', (string)$colInfo['Type']);
        $upd[$col] = $isInt ? (($to === 'paid') ? 1 : 2) : $to; // 0=ordered,1=paid,2=issued
    }
    return $upd;
}
// Map label -> DB value (works for INT or VARCHAR status columns)
private function inv_status_value(string $label)
{
    // map for installs that use INT status
    static $map = ['draft'=>0,'paid'=>1,'issued'=>2,'billed'=>3,'redo'=>4];

    // detect whether the column is int just once
    static $is_int = null;
    if ($is_int === null) {
        $tbl = $this->db->table_exists('hs_academy_invoices') ? 'hs_academy_invoices' : 'invoices';
        $row = $this->db->query("SHOW COLUMNS FROM {$tbl} LIKE 'status'")->row_array();
        $is_int = $row && stripos((string)$row['Type'], 'int') !== false;
    }

    return $is_int ? ($map[$label] ?? 0) : $label;
}

public function check_and_bill(int $order_id, int $user_id, string $note = '')
{
    $order = $this->db->get_where('pace_orders', ['id' => $order_id])->row_array();
    if (!$order) return [false, 'Order not found'];
    if (!empty($order['invoice_id'])) return [true, (int)$order['invoice_id']]; // already billed

    // 1) mark order as checked
    $now = date('Y-m-d H:i:s');
    $this->db->where('id', $order_id)->update('pace_orders', [
        'is_checked' => 1,
        'checked_by' => $user_id,
        'checked_at' => $now,
        'status'     => 'checked',
    ]);

    // 2) create invoice from order
    $invoice_id = $this->create_invoice_from_order($order_id);
    if (!$invoice_id) return [false, 'Failed to create invoice'];

    // 3) immediately mark the invoice as BILLED (handle INT/VARCHAR)
    $invTable = $this->db->table_exists('hs_academy_invoices') ? 'hs_academy_invoices' : 'invoices';
    $this->db->where('id', $invoice_id)->update($invTable, [
        'status'     => $this->inv_status_value('billed'), // <-- key change
        'updated_at' => $now,
    ]);

    // 4) link invoice back to order and mirror status
    $this->db->where('id', $order_id)->update('pace_orders', [
        'invoice_id' => $invoice_id,
        'status'     => 'billed',
        'updated_at' => $now,
    ]);

    return [true, $invoice_id];
}



}
