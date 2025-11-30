<?php
defined('BASEPATH') or exit('No direct script access allowed');

/* -------------------- view shims / defaults -------------------- */
if (!isset($week_no)) { $week_no = isset($week) ? (int)$week : 1; }
$week_no        = (int)$week_no;
$term           = isset($term) ? (int)$term : 1;
$per_page       = isset($per_page) ? (int)$per_page : 6;   // show 6 students per page
$page           = isset($page) ? max(1,(int)$page) : 1;
$total_students = isset($total_students) ? (int)$total_students : (isset($students_page)?count($students_page):0);

if (!function_exists('esc')) { function esc($s){ return html_escape($s); } }

/* -------------------- normalize trait groups -------------------- */
$trait_groups = [];
if (is_array($traits_def)) {
    $looksLikeLabeled = true;
    foreach ($traits_def as $g) {
        if (!is_array($g) || !array_key_exists('items',$g)) { $looksLikeLabeled = false; break; }
    }
    if ($looksLikeLabeled) {
        foreach ($traits_def as $groupKey => $g) {
            $label = isset($g['label']) ? (string)$g['label'] : ucfirst((string)$groupKey);
            $items = isset($g['items']) && is_array($g['items']) ? $g['items'] : [];
            if ($items) $trait_groups[$label] = $items;
        }
    } else {
        foreach ($traits_def as $k=>$v) {
            if (is_array($v)) { $trait_groups[(string)$k] = $v; }
        }
        if (!$trait_groups) { $trait_groups = ['Traits' => []]; }
    }
}
if (!$trait_groups) $trait_groups = ['Traits' => []];

/* -------------------- map scores: [sid][trait_key] => score -------------------- */
$scoreMap = [];
if (!empty($scores) && is_array($scores)) {
    foreach ($scores as $sid => $rows) {
        if (!is_array($rows)) continue;
        foreach ($rows as $k=>$v) {
            if (is_array($v) && array_key_exists('trait_key',$v)) {
                $key = $v['trait_key'];
                $val = $v['score'] ?? null;
            } else {
                $key = is_string($k) ? $k : (is_array($v) ? ($v['key'] ?? null) : null);
                $val = is_array($v) ? ($v['score'] ?? $v['value'] ?? $v['val'] ?? null) : $v;
            }
            if ($key !== null) $scoreMap[(int)$sid][$key] = $val;
        }
    }
}

/* -------------------- paging / header -------------------- */
$base     = base_url('weekly_traits');
$max_page = max(1, (int)ceil($total_students / max(1,$per_page)));
$prev     = max(1, $page-1);
$next     = min($max_page, $page+1);
$prevUrl  = $base.'?'.http_build_query(['term'=>$term,'week_no'=>$week_no,'p'=>$prev]);
$nextUrl  = $base.'?'.http_build_query(['term'=>$term,'week_no'=>$week_no,'p'=>$next]);
$showFrom = $total_students ? (($page-1)*$per_page + 1) : 0;
$showTo   = min($page*$per_page, $total_students);

/* -------------------- CSRF -------------------- */
$csrf_name = $this->security->get_csrf_token_name();
$csrf_hash = $this->security->get_csrf_hash();

/* -------------------- student columns (prefer names) -------------------- */
$cols = [];
foreach ($students_page as $stu) {
    $sid = (int)($stu['student_id'] ?? $stu['id'] ?? 0);
    $candidates = [
        $stu['fullname']      ?? null,
        $stu['full_name']     ?? null,
        $stu['student_name']  ?? null,
        $stu['name']          ?? null,
        trim(($stu['first_name'] ?? '').' '.($stu['last_name'] ?? '')),
    ];
    $name = '';
    foreach ($candidates as $c) { $c = trim((string)$c); if ($c !== '') { $name = $c; break; } }
    if ($name === '') { $name = (string)($stu['register_no'] ?? $stu['admission_no'] ?? ('Student #'.$sid)); }
    $cols[] = ['id' => $sid, 'name' => $name];
}
$colsCount = max(1, count($cols)); // used for width calc
$studentColWidth = 60 / $colsCount; // selectors together 60% of table width
?>
<div class="card">
  <div class="card-body pb-2">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <h5 class="m-0">Weekly Traits</h5>

      <div class="ms-auto d-flex align-items-center gap-2">
        <form method="get" action="<?= esc($base) ?>" class="d-flex align-items-center gap-2">
          <label class="mb-0">Term</label>
          <select name="term" class="form-control form-control-sm" style="width:80px">
            <?php for ($t=1;$t<=4;$t++): ?>
              <option value="<?= $t ?>" <?= ($term===$t?'selected':'') ?>>T<?= $t ?></option>
            <?php endfor; ?>
          </select>

          <label class="mb-0">Week</label>
          <select name="week_no" class="form-control form-control-sm" style="width:110px">
            <?php for ($w=1;$w<=11;$w++): ?>
              <option value="<?= $w ?>" <?= ($week_no===$w?'selected':'') ?>>Week <?= $w ?></option>
            <?php endfor; ?>
          </select>

          <input type="hidden" name="p" value="<?= (int)$page ?>">
          <button class="btn btn-sm btn-primary">Go</button>
        </form>

        <div class="text-muted small">
          Showing <strong><?= $showFrom ?>–<?= $showTo ?></strong> of <strong><?= $total_students ?></strong>
        </div>

        <div class="btn-group">
          <a class="btn btn-sm btn-outline-secondary <?= ($page<=1?'disabled':'') ?>" href="<?= $prevUrl ?>">« Prev <?= (int)$per_page ?></a>
          <a class="btn btn-sm btn-outline-secondary <?= ($page>=$max_page?'disabled':'') ?>" href="<?= $nextUrl ?>">Next <?= (int)$per_page ?> »</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (empty($students_page)): ?>
  <div class="alert alert-warning mt-3">No students found for this selection.</div>
<?php endif; ?>

<?php foreach ($trait_groups as $groupLabel => $traits): ?>
  <div class="card mt-3">
    <div class="card-header py-2">
      <strong><?= esc($groupLabel) ?></strong>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-sm traits-matrix mb-0">
        <thead class="table-light">
          <tr>
            <!-- Traits column = 30% -->
            <th class="trait-col">Traits</th>
            <!-- Student columns share 60% equally -->
            <?php foreach ($cols as $c): ?>
              <th class="student-col text-center"
                  style="width: <?= number_format($studentColWidth,2,'.','') ?>%;"
                  title="<?= esc($c['name']) ?>">
                <div class="student-name-2line"><?= esc($c['name']) ?></div>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($traits as $key => $label): ?>
            <tr>
              <td class="trait-label"><?= esc($label) ?></td>
              <?php foreach ($cols as $c):
                    $sid     = $c['id'];
                    $current = $scoreMap[$sid][$key] ?? '';
              ?>
                <td class="text-center">
                  <select
                    class="form-control form-control-sm trait-score w-100"
                    data-student="<?= $sid ?>"
                    data-trait="<?= esc($key) ?>"
                    data-category="<?= esc($groupLabel) ?>"
                    data-term="<?= (int)$term ?>"
                    data-week-no="<?= (int)$week_no ?>"
                  >
                    <option value="">—</option>
                    <option value="1" <?= ($current==='1'||$current===1)?'selected':'' ?>>1</option>
                    <option value="2" <?= ($current==='2'||$current===2)?'selected':'' ?>>2</option>
                    <option value="3" <?= ($current==='3'||$current===3)?'selected':'' ?>>3</option>
                    <option value="4" <?= ($current==='4'||$current===4)?'selected':'' ?>>4</option>
                  </select>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>

<style>
  .traits-matrix { table-layout: fixed; width: 100%; }
  .traits-matrix th, .traits-matrix td { vertical-align: middle; }

  /* Bigger: Traits 30%, selectors together 60% */
  .trait-col { width: 30%; }
  .trait-label { white-space: normal; word-break: break-word; }

  .student-col { width: 1%; } /* overridden per-th via inline style above */
  .student-name-2line {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.1;
      max-height: 2.2em;
      white-space: normal;
  }

  /* Make selects fill their column and feel larger */
  .traits-matrix select.trait-score {
      width: 100%;
      max-width: none;
      min-height: 32px;
      padding-top: 2px; padding-bottom: 2px;
      font-size: 13px;
    }

  .saving { outline: 2px solid #ffca28 !important; }
  .saved  { outline: 2px solid #2e7d32 !important; }
  .error  { outline: 2px solid #c62828 !important; }
</style>

<script>
(function(){
  const csrfName = <?= json_encode($csrf_name) ?>;
  const csrfHash = <?= json_encode($csrf_hash) ?>;

  function postForm(url, data){
    data[csrfName] = csrfHash;
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With':'XMLHttpRequest' // <-- needed for CI3 is_ajax_request()
      },
      body: new URLSearchParams(data)
    }).then(r => r.json());
  }

  document.addEventListener('change', function(e){
    const sel = e.target.closest('.trait-score');
    if(!sel) return;

    sel.classList.remove('saved','error');
    sel.classList.add('saving');

    const payload = {
      student_id: sel.getAttribute('data-student'),
      trait_key : sel.getAttribute('data-trait'),
      category  : sel.getAttribute('data-category'),
      term      : sel.getAttribute('data-term'),
      week_no   : sel.getAttribute('data-week-no'),
      score     : sel.value
    };

    postForm(<?= json_encode(base_url('weekly_traits/save')) ?>, payload)
      .then(res => {
        sel.classList.remove('saving');
        if(res && (res.status === 'success' || res.ok)){
          sel.classList.add('saved');
          setTimeout(()=>sel.classList.remove('saved'), 800);
        } else {
          sel.classList.add('error');
        }
      })
      .catch(() => {
        sel.classList.remove('saving');
        sel.classList.add('error');
      });
  });
})();
</script>
