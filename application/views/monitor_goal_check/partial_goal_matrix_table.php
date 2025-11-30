<?php
$day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$subject_abbrs = array_column($subjects, 'abbreviation'); // keep subjects only (no EX1/EX2/EX3)
$date_block_options = ['GV', 'HWS Due✓', 'HW≠Rec✓', 'HWS≠Ret✓', 'HWS≠Sign✓', 'HWSchanges≠Rec✓', 'DTS+Sign✓', 'DTS Due✓', 'DTS≠Ret✓'];
$status_options = ['Edit Scripture', 'A Privilege', 'C Privilege', 'E Privilege', 'Edit Note'];
?>

<div style="overflow-x: auto; white-space: nowrap;">
<table class="table table-bordered table-sm text-center" style="font-size: 9px;">
    <thead>
        <tr>
            <th rowspan="2" style="min-width: 35px;">#Week</th>
            <th rowspan="2" style="min-width: 30px;">Status</th>
            <?php foreach ($day_names as $day): ?>
                <!-- +5 = Date, Att, Dmt, Mrt, TP -->
                <th colspan="<?= count($subject_abbrs) + 5 ?>"><?= $day ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($day_names as $day): ?>
                <th style="min-width: 28px;">Date</th>
                <th style="min-width: 25px;">Att</th>
                <th style="min-width: 25px;">Dmt</th>
                <th style="min-width: 25px;">Mrt</th>
                <th class="tp-col" style="min-width:25px;width:25px;">
                    <div class="text-center"><abbr title="Total Pages">TP</abbr></div>
                </th>
                <?php foreach ($subject_abbrs as $abbr): ?>
                    <th class="sub-col" style="min-width:25px;width:25px;">
                        <span class="abbr" title="<?= htmlspecialchars($abbr, ENT_QUOTES) ?>">
                            <?php
                            $printAbbr = preg_replace('/-/', "-\u{200B}", $abbr);
                            echo htmlspecialchars($printAbbr, ENT_QUOTES);
                            ?>
                        </span>
                    </th>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php for ($w = 1; $w <= 11; $w++): ?>
            <tr>
<td class="week-block"
    style="position: relative;"
    data-week="<?= $w ?>"
    data-note="<?= $saved_week_notes[$w] ?? '' ?>"
    data-scripture="<?= $saved_scripture_notes[$w] ?? '' ?>"
    title="<?= $saved_week_notes[$w] ?? '' ?><?= !empty($saved_scripture_notes[$w]) ? ' | Scripture: ' . $saved_scripture_notes[$w] : '' ?>">
    <?php if (!empty($saved_week_notes[$w]) || !empty($saved_scripture_notes[$w])): ?>
        <span class="orange-corner" style="position:absolute; top:0; right:0; border-top: 10px solid orange; border-left: 10px solid transparent;"></span>
    <?php endif; ?>
    <?php if (!empty($saved_scripture_notes[$w])): ?>
        <span class="green-corner" style="position:absolute; bottom:0; right:0; border-bottom: 10px solid limegreen; border-left: 10px solid transparent;"></span>
    <?php endif; ?>
    <strong><?= $w ?></strong>
</td>

<td class="status-indicator" data-week="<?= $w ?>">
    <span class="status-icon thumbs-up">✅</span>
</td>

<?php for ($d = 0; $d < 5; $d++): ?>
    <?php 
    $index = ($w - 1) * 5 + $d;
    $current_date = isset($week_dates[$index]) ? new DateTime($week_dates[$index]) : new DateTime();
    $date_str = $current_date->format('Y-m-d');
    ?>

<td class="date-block"
    data-date="<?= $date_str ?>"
    data-week="<?= $w ?>"
    data-note="<?= $saved_date_notes[$date_str] ?? '' ?>"
    title="<?= $saved_date_notes[$date_str] ?? '' ?>">
    <?php if (!empty($saved_date_notes[$date_str])): ?>
        <span class="blue-corner" style="position:absolute; top:0; right:0; width: 0; height: 0; border-top: 10px solid blue; border-left: 10px solid transparent;"></span>
    <?php endif; ?>
    <?= $current_date->format('d M') ?>
</td>

<td style="padding: 0;">
    <input type="text" class="form-control form-control-sm att-field text-center" 
        data-type="att" 
        data-date="<?= $date_str ?>" 
        data-week="<?= $w ?>" 
        data-student="<?= $student_id ?>" 
        data-term="<?= $term_id ?>" 
        data-day="<?= $day_names[$d] ?>" 
        value="<?= isset($saved_data[$date_str]['__ATT__']) ? $saved_data[$date_str]['__ATT__']['attendance_status'] : '' ?>"
        style="font-size:9px; padding:1px; height: 22px; background: transparent; color: black;">
</td>

<?php
    $dmtNotesArr = isset($saved_data[$date_str]['__DMT__']['notes']) && is_array($saved_data[$date_str]['__DMT__']['notes'])
        ? $saved_data[$date_str]['__DMT__']['notes'] : [];
    $dmtTooltip = '';
    if ($dmtNotesArr) {
        $lines = [];
        foreach ($dmtNotesArr as $i => $txt) { $lines[] = ($i+1) . ') ' . $txt; }
        $dmtTooltip = implode("\n", $lines);
    }
    $dmtHasNote = $dmtTooltip !== '' ? 'has-note' : '';
    $dmtNotesJson = htmlspecialchars(json_encode($dmtNotesArr), ENT_QUOTES);
    $dmtValue = isset($saved_data[$date_str]['__DMT__']['demerit']) ? $saved_data[$date_str]['__DMT__']['demerit'] : '';
?>
<td style="padding: 0;">
    <div class="note-indicator <?= $dmtHasNote ?>" title="<?= htmlspecialchars($dmtTooltip, ENT_QUOTES) ?>"
         data-note="<?= htmlspecialchars($dmtTooltip, ENT_QUOTES) ?>"
         data-notes-json="<?= $dmtNotesJson ?>">
        <input type="text" class="form-control form-control-sm dmt-field text-center"
            data-type="dmt"
            data-date="<?= $date_str ?>"
            data-week="<?= $w ?>"
            data-student="<?= $student_id ?>"
            data-term="<?= $term_id ?>"
            data-day="<?= $day_names[$d] ?>"
            value="<?= $dmtValue ?>"
            style="font-size:9px; padding:1px; height: 22px; background: transparent; color: red;">
    </div>
</td>

<?php
    $mrtNotesArr = isset($saved_data[$date_str]['__MRT__']['notes']) && is_array($saved_data[$date_str]['__MRT__']['notes'])
        ? $saved_data[$date_str]['__MRT__']['notes'] : [];
    $mrtTooltip = '';
    if ($mrtNotesArr) {
        $lines = [];
        foreach ($mrtNotesArr as $i => $txt) { $lines[] = ($i+1) . ') ' . $txt; }
        $mrtTooltip = implode("\n", $lines);
    }
    $mrtHasNote = $mrtTooltip !== '' ? 'has-note' : '';
    $mrtNotesJson = htmlspecialchars(json_encode($mrtNotesArr), ENT_QUOTES);
    $mrtValue = isset($saved_data[$date_str]['__MRT__']['merit']) ? $saved_data[$date_str]['__MRT__']['merit'] : '';
?>
<td style="padding: 0;">
    <div class="note-indicator <?= $mrtHasNote ?>" title="<?= htmlspecialchars($mrtTooltip, ENT_QUOTES) ?>"
         data-note="<?= htmlspecialchars($mrtTooltip, ENT_QUOTES) ?>"
         data-notes-json="<?= $mrtNotesJson ?>">
        <input type="text" class="form-control form-control-sm mrt-field text-center"
            data-type="mrt"
            data-date="<?= $date_str ?>"
            data-week="<?= $w ?>"
            data-student="<?= $student_id ?>"
            data-term="<?= $term_id ?>"
            data-day="<?= $day_names[$d] ?>"
            value="<?= $mrtValue ?>"
            style="font-size:9px; padding:1px; height: 22px; background: transparent; color: green;">
    </div>
</td>

<td class="tp-col" style="padding:0; min-width:25px; width:25px;">
    <input type="number" class="form-control form-control-sm tp-field text-center"
        data-type="goal"
        data-subject="__TP__"
        data-date="<?= $date_str ?>"
        data-week="<?= $w ?>"
        data-student="<?= $student_id ?>"
        data-term="<?= $term_id ?>"
        data-day="<?= $day_names[$d] ?>"
        value="<?= isset($saved_data[$date_str]['__TP__']) ? $saved_data[$date_str]['__TP__']['goal'] : '' ?>"
        min="0" step="1" inputmode="numeric"
        style="font-size:9px; padding:1px; height:22px; background:transparent; color:black;">
</td>

<?php foreach ($subject_abbrs as $abbr): ?>
    <?php
        $goalVal = isset($saved_data[$date_str][$abbr]['goal']) ? $saved_data[$date_str][$abbr]['goal'] : '';
        $noteVal = isset($saved_data[$date_str][$abbr]['note']) ? $saved_data[$date_str][$abbr]['note'] : '';
        $hasNote = $noteVal !== '' ? 'has-note' : '';
        $escapedNote = htmlspecialchars($noteVal, ENT_QUOTES);
    ?>
    <td style="padding: 0;">
        <div class="note-indicator <?= $hasNote ?>" title="<?= $escapedNote ?>" data-note="<?= $escapedNote ?>">
            <input type="text" class="form-control form-control-sm goal-field text-center" 
                data-type="goal" 
                data-date="<?= $date_str ?>" 
                data-subject="<?= $abbr ?>" 
                data-week="<?= $w ?>" 
                data-student="<?= $student_id ?>" 
                data-term="<?= $term_id ?>" 
                data-day="<?= $day_names[$d] ?>" 
                value="<?= $goalVal ?>"
                data-note="<?= $escapedNote ?>"
                style="font-size:9px; padding:1px; height: 22px; background: transparent; color: black;">
        </div>
    </td>
<?php endforeach; ?>

<?php endfor; // day loop ?>
            </tr>
        <?php endfor; // week loop ?>
    </tbody>
</table>
</div>

<!-- ============ Day Notes Bottom Panel (shows everything for the selected date) ============ -->
<div id="day-notes-panel" class="day-notes-panel" style="display:none;">
  <div class="dn-head"><strong>Day Note</strong> for <span id="dn-date"></span> (Week <span id="dn-week"></span>)</div>
  <!-- READ-ONLY SUMMARY compiled from week/date/scripture/goal/dmt/mrt notes -->
  <div id="dn-summary" class="dn-summary"></div>
  <!-- EDITABLE Date Note (only this is saved on “Save”) -->
  <textarea id="dn-text" class="form-control" rows="3" placeholder="Click a date to load or type a note..."></textarea>
  <div class="text-right" style="margin-top:6px;">
    <button id="dn-save" type="button" class="btn btn-xs btn-primary">Save</button>
  </div>
</div>

<div id="custom-context-menu" class="custom-context-menu" 
     style="display:none; position:absolute; z-index:10000; 
     background:#fff; border:1px solid #ccc; box-shadow:2px 2px 6px rgba(0,0,0,0.2); 
     font-size:10px; min-width:120px;">
</div>

<style>
.note-indicator { position: relative; }
.note-indicator.has-note::after {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 0; height: 0;
    border-top: 10px solid red;
    border-left: 10px solid transparent;
}
.custom-context-menu li { padding: 2px 4px !important; font-size: 9px; line-height: 1.1; }
.date-block { position: relative; cursor: pointer; }
.date-block.blue-arrow::before {
    content: ""; position: absolute; top: 0; right: 0;
    border-top: 10px solid blue; border-left: 10px solid transparent;
}
.status-icon { font-size: 14px; display: inline-block; }
.status-icon.orange { color: orange; font-weight: bold; }
.status-icon.red { color: red; animation: flash 1s infinite; }
@keyframes flash { 0%,100%{opacity:1;} 50%{opacity:0;} }
.green-corner {
    position: absolute; bottom: 0; right: 0;
    width: 0; height: 0;
    border-bottom: 10px solid limegreen;
    border-left: 10px solid transparent;
}
th.tp-col, td.tp-col { min-width:25px; width:25px; }
th.sub-col { min-width:25px; width:25px; }
th.sub-col .abbr{
    display:block;
    max-width:25px;
    white-space:normal;
    overflow-wrap:anywhere;
    word-break:break-word;
    line-height:10px;
    margin:0 auto;
    text-align:center;
}
/* Bottom sticky panel */
.day-notes-panel{
    position: sticky;
    bottom: 0;
    background:#fff;
    border-top:1px solid #ddd;
    padding:8px;
    z-index: 5;
}
.day-notes-panel .dn-head{ margin-bottom:4px; font-size:11px; }
/* Summary block */
.dn-summary{
    border:1px dashed #cbd5e1;
    background:#f8fafc;
    padding:6px;
    margin-bottom:6px;
    font-size:11px;
    line-height:14px;
    white-space:pre-wrap;
}
</style>

<script>
const goalOptions = [
    "H", "ST", "PT", "IG", "?", "P≠O", "X≠O", "S/S Inc", "C/U", "C/U≠Xref",
    "Inc.G/", "Compl.G≠/", "(P)", "X-WP", "G Chg",
    "➕ Add/Edit Note", "❌ Remove Note", "Clear"
];
const attOptions = ["P", "A", "L", "FT", "S", "❌ Remove Note", "Clear"];
const dmtOptions = ["Add Demerit", "Subtract Demerit", "➕ Add/Edit Note", "❌ Remove Note", "Clear"];
const mrtOptions = ["Add Merit", "Subtract Merit", "➕ Add/Edit Note", "❌ Remove Note", "Clear"];
const dateOptions = ["GV", "HWS Due✓", "HW≠Rec✓", "HWS≠Ret✓", "HWS≠Sign✓", "HWSchanges≠Rec✓", "DTS+Sign✓", "DTS Due✓", "DTS≠Ret✓"];
const statusOptions = ["Edit Scripture", "A Privilege", "C Privilege", "E Privilege", "Edit Note"];

const STUDENT_ID = <?= json_encode($student_id) ?>;
const TERM_ID    = <?= json_encode($term_id) ?>;

/* ---------- Show Day Notes toggle + panel control ---------- */
function labelTextForCheckbox(el) {
  const $el = $(el);
  const id = $el.attr('id');
  let txt = '';
  if (id) { txt = $('label[for="'+id+'"]').text() || txt; }
  if (!txt) { txt = $el.closest('label').text() || txt; }
  if (!txt) { txt = ($el.next().text() || '') + ' ' + ($el.parent().text() || ''); }
  return (txt || '').toLowerCase().trim();
}
function isShowDayNotesCheckbox(el) {
  return labelTextForCheckbox(el).indexOf('show day notes') !== -1;
}
function anyShowNotesChecked(){
  let checked = false;
  $('input[type="checkbox"]').each(function(){
    if (isShowDayNotesCheckbox(this)) { checked = this.checked; return false; }
  });
  return checked;
}
function toggleDayPanel(force){
  const show = (typeof force === 'boolean') ? force : anyShowNotesChecked();
  $('#day-notes-panel').toggle(!!show);
}
$(document).on('change.mgc-dn', 'input[type="checkbox"]', function(){
  if (isShowDayNotesCheckbox(this)) toggleDayPanel(this.checked);
});
$(function(){ toggleDayPanel(); });

/* ---------- Helpers for numbered notes (arrays) ---------- */
function parseNotes($input) {
  const $wrap = $input.closest('.note-indicator');
  try { return JSON.parse($wrap.attr('data-notes-json') || '[]'); } catch(e) { return []; }
}
function setNotes($input, arr) {
  const $wrap = $input.closest('.note-indicator');
  const nums = arr.map((t, i) => (i+1) + ') ' + t);
  const tooltip = nums.join('\n');
  $wrap.attr('data-notes-json', JSON.stringify(arr || []));
  $wrap.attr('data-note', tooltip);
  if (arr && arr.length) { $wrap.addClass('has-note').attr('title', tooltip); }
  else { $wrap.removeClass('has-note').attr('title', ''); }
}

/* ---------- Build the full summary for a given date ---------- */
function buildDaySummary(dateStr, weekNo){
  const $dateCell = $('.date-block[data-date="'+dateStr+'"]');
  const dateNote  = $dateCell.attr('data-note') || '';
  const weekNote  = $('.week-block[data-week="'+weekNo+'"]').attr('data-note') || '';
  const scripture = $('.week-block[data-week="'+weekNo+'"]').attr('data-scripture') || '';
  const attVal    = $('input.att-field[data-date="'+dateStr+'"]').val() || '';

  // Demerit & Merit notes (arrays)
  const dmtArr = (function(){
    const txt = $('input.dmt-field[data-date="'+dateStr+'"]').closest('.note-indicator').attr('data-notes-json') || '[]';
    try { return JSON.parse(txt); } catch(e){ return []; }
  })();
  const mrtArr = (function(){
    const txt = $('input.mrt-field[data-date="'+dateStr+'"]').closest('.note-indicator').attr('data-notes-json') || '[]';
    try { return JSON.parse(txt); } catch(e){ return []; }
  })();

  // Subject notes
  const subjLines = [];
  $('input.goal-field[data-date="'+dateStr+'"]').each(function(){
    const note = $(this).closest('.note-indicator').attr('data-note') || '';
    if (note) {
      const subj = $(this).data('subject') || '';
      subjLines.push(String(subj).toUpperCase()+': '+note);
    }
  });

  // Format multi-section summary
  const lines = [];
  if (weekNote)   lines.push('• Week: ' + weekNote);
  if (scripture)  lines.push('• Scripture: ' + scripture);
  if (dateNote)   lines.push('• Date: ' + dateNote);
  if (attVal)     lines.push('• Attendance: ' + attVal);

  if (dmtArr.length){
    lines.push('• Demerits ('+dmtArr.length+'):');
    dmtArr.forEach((t,i)=>lines.push('   ' + (i+1) + ') ' + t));
  }
  if (mrtArr.length){
    lines.push('• Merits ('+mrtArr.length+'):');
    mrtArr.forEach((t,i)=>lines.push('   ' + (i+1) + ') ' + t));
  }
  if (subjLines.length){
    lines.push('• Subject Notes:');
    subjLines.forEach(s=>lines.push('   - ' + s));
  }

  return lines.join('\n');
}

/* ---------- Date click -> load panel + summary ---------- */
let DN_CTX = { date:null, week:null };
$(document).on('click', '.date-block', function(){
  DN_CTX.date = $(this).data('date');
  DN_CTX.week = $(this).data('week');

  const nice = $(this).text();
  const dateNote = $(this).attr('data-note') || '';
  $('#dn-date').text(nice + ' (' + DN_CTX.date + ')');
  $('#dn-week').text(DN_CTX.week || '');
  $('#dn-text').val(dateNote);

  // Build + render full summary from all sources
  $('#dn-summary').text(buildDaySummary(DN_CTX.date, DN_CTX.week));

  if (anyShowNotesChecked()) toggleDayPanel(true);
});

/* ---------- Save button (writes only Date Note) ---------- */
$('#dn-save').on('click', function(){
  if (!DN_CTX.date || !DN_CTX.week) return;
  const value = $('#dn-text').val() || '';
  const $cell = $('.date-block[data-date="'+DN_CTX.date+'"]');
  $cell.attr('data-note', value).attr('title', value);
  if (value && !$cell.find('.blue-corner').length) {
    $cell.css("position","relative")
         .append('<span class="blue-corner" style="position:absolute; top:0; right:0; width: 0; height: 0; border-top: 10px solid blue; border-left: 10px solid transparent;"></span>');
  }
  saveMetaNote(STUDENT_ID, TERM_ID, DN_CTX.week, DN_CTX.date, 'date', value);
  // refresh summary to reflect new Date note
  $('#dn-summary').text(buildDaySummary(DN_CTX.date, DN_CTX.week));
});

/* ---------- Context menu + existing logic (unchanged behaviour) ---------- */
function showContextMenu(e, options, callback) {
    e.preventDefault();
    $(".custom-context-menu").remove();
    const menu = $('<ul class="custom-context-menu list-group"></ul>');
    options.forEach(function(opt) {
        const $item = $('<li class="list-group-item p-1 m-0"></li>').text(opt);
        $item.on("click", function () { callback(opt); menu.remove(); });
        menu.append($item);
    });
    $("body").append(menu);
    menu.css({ top: e.pageY + "px", left: e.pageX + "px", position: "absolute", "z-index": 9999,
               "background-color": "#fff", border: "1px solid #ccc", padding: "0px", "font-size": "9px" });
}

$(document).on("contextmenu", "input[data-type]", function(e) {
    const $input = $(this);
    const type = $input.data("type");
    const subj = ($input.data("subject") || "").toString();
    if (type === "goal" && subj === "__TP__") return;

    let options = [];
    if (type === "goal") options = goalOptions;
    else if (type === "att") options = attOptions;
    else if (type === "dmt") options = dmtOptions;
    else if (type === "mrt") options = mrtOptions;
    else return;

    showContextMenu(e, options, (opt) => {
        if (type === 'dmt') {
            if (opt === "Add Demerit") {
                const v = (parseInt($input.val() || '0', 10) + 1);
                $input.val(v);
                const arr = parseNotes($input);
                const note = prompt("Enter note:", "");
                if (note && note.trim() !== "") arr.push(note.trim());
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "Subtract Demerit") {
                const v = Math.max(0, parseInt($input.val() || '0', 10) - 1);
                $input.val(v);
                const arr = parseNotes($input);
                if (arr.length) arr.pop();
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "➕ Add/Edit Note") {
                const arr = parseNotes($input);
                const note = prompt("Enter note:", "");
                if (note && note.trim() !== "") arr.push(note.trim());
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "❌ Remove Note") {
                const arr = parseNotes($input);
                if (arr.length) arr.pop();
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "Clear") {
                $input.val('');
                setNotes($input, []);
                $input.trigger("change");
            }
            // if current date selected, refresh summary
            const d = $input.data('date'); if (DN_CTX.date === d) $('#dn-summary').text(buildDaySummary(DN_CTX.date, DN_CTX.week));
            return;
        }

        if (type === 'mrt') {
            if (opt === "Add Merit") {
                const v = (parseInt($input.val() || '0', 10) + 1);
                $input.val(v);
                const arr = parseNotes($input);
                const note = prompt("Enter note:", "");
                if (note && note.trim() !== "") arr.push(note.trim());
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "Subtract Merit") {
                const v = Math.max(0, parseInt($input.val() || '0', 10) - 1);
                $input.val(v);
                const arr = parseNotes($input);
                if (arr.length) arr.pop();
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "➕ Add/Edit Note") {
                const arr = parseNotes($input);
                const note = prompt("Enter note:", "");
                if (note && note.trim() !== "") arr.push(note.trim());
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "❌ Remove Note") {
                const arr = parseNotes($input);
                if (arr.length) arr.pop();
                setNotes($input, arr);
                $input.trigger("change");
            } else if (opt === "Clear") {
                $input.val('');
                setNotes($input, []);
                $input.trigger("change");
            }
            const d = $input.data('date'); if (DN_CTX.date === d) $('#dn-summary').text(buildDaySummary(DN_CTX.date, DN_CTX.week));
            return;
        }

        // Goals / Attendance
        if (opt === "➕ Add/Edit Note") {
            const current = $input.closest(".note-indicator").attr("data-note") || '';
            const newNote = prompt("Enter note:", current);
            if (newNote !== null) {
                $input.closest(".note-indicator").attr("data-note", newNote).attr("title", newNote).addClass("has-note");
                $input.attr("data-note", newNote).attr("title", newNote);
                setTimeout(() => $input.trigger("change"), 50);
            }
        } else if (opt === "❌ Remove Note") {
            $input.closest(".note-indicator").removeAttr("data-note").removeAttr("title").removeClass("has-note");
            $input.removeAttr("data-note").removeAttr("title");
            setTimeout(() => $input.trigger("change"), 50);
        } else if (opt === "Clear") {
            $input.val('').removeClass("filled").trigger("change");
        } else {
            $input.val(opt).addClass("filled").trigger("change");
        }
        const d = $input.data('date'); if (DN_CTX.date === d) $('#dn-summary').text(buildDaySummary(DN_CTX.date, DN_CTX.week));
    });
});

/* Right-click date (kept) */
$(document).on("contextmenu", ".date-block", function(e) {
    const $block = $(this);
    const week = $block.data("week");
    const date = $block.data("date");

    showContextMenu(e, dateOptions, (opt) => {
        $block.attr("data-note", opt).attr("title", opt);
        if (!$block.find('.blue-corner').length) {
            $block.css("position", "relative").append('<span class="blue-corner" style="position:absolute; top:0; right:0; width: 0; height: 0; border-top: 10px solid blue; border-left: 10px solid transparent;"></span>');
        }
        saveMetaNote(STUDENT_ID, TERM_ID, week, date, 'date', opt);
        if (DN_CTX.date === date) { $('#dn-text').val(opt); $('#dn-summary').text(buildDaySummary(DN_CTX.date, DN_CTX.week)); }
    });
});

/* Save on change (existing) */
$(document).on("change", "input[data-type]", function () { saveGoalEntry($(this)); });

function saveGoalEntry($input) {
    const type = $input.data('type');
    const week = $input.data('week');
    const day = $input.data('day');
    const date = $input.data('date');
    const subjectCode = $input.data('subject') || '';

    const attVal = $('input[data-type="att"][data-date="' + date + '"]').val() || '';
    const dmtVal = $('input[data-type="dmt"][data-date="' + date + '"]').val() || '0';
    const mrtVal = $('input[data-type="mrt"][data-date="' + date + '"]').val() || '0';

    const data = {
        student_id: STUDENT_ID,
        term_id: TERM_ID,
        week: week,
        day: day,
        date: date,
        subject_code: subjectCode,
        goal: null,
        attendance_status: attVal,
        demerit: parseInt(dmtVal) || 0,
        merit: parseInt(mrtVal) || 0
    };

    if (type === 'dmt') { data.dmt_notes = JSON.stringify(parseNotes($input) || []); }
    else if (type === 'mrt') { data.mrt_notes = JSON.stringify(parseNotes($input) || []); }
    else {
        const note = $input.closest('.note-indicator').attr('data-note');
        if (note !== undefined) data.notes = note;
    }

    switch (type) {
        case 'goal': data.goal = $input.val(); break;
        case 'att': data.attendance_status = $input.val(); break;
        case 'dmt': data.demerit = parseInt($input.val()) || 0; break;
        case 'mrt': data.merit = parseInt($input.val()) || 0; break;
    }

    $.ajax({
        url: '<?= base_url("monitor_goal_check/save_entry") ?>',
        method: 'POST',
        data: data,
        success: function (res) { console.log("Saved:", res); },
        error: function (xhr) { console.error("SAVE ERROR:", xhr.responseText); }
    });
}

function saveMetaNote(studentId, termId, week, date, type, value) {
    $.post('<?= base_url("monitor_goal_check/save_meta_note") ?>', {
        student_id: studentId,
        term_id: termId,
        week_no: week,
        date: date,
        note_type: type,
        note_value: value
    }).done(res => console.log("Meta saved:", res));
}

$(document).on("click", () => $(".custom-context-menu").remove());

$(document).on("change", ".dmt-field", function () {
    const val = parseInt($(this).val()) || 0;
    const week = $(this).data("week");
    const $icon = $('.status-indicator[data-week="' + week + '"] .status-icon');
    if (val >= 5) $icon.text("❗").removeClass().addClass("status-icon red");
    else if (val >= 3) $icon.text("⚠️").removeClass().addClass("status-icon orange");
    else $icon.text("✅").removeClass().addClass("status-icon green");
});
</script>
