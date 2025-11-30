<section class="panel">
	<div class="tabs-custom">
		<ul class="nav nav-tabs">
			<li class="active">
                <a href="#list" data-toggle="tab">
                    <i class="fas fa-list-ul"></i> <?=translate('event_list')?>
                </a>
			</li>
<?php
$role_id = (int)$this->session->userdata('loggedin_role_id');
if (get_permission('event', 'is_add') || $role_id === 3): ?>
			<li>
                <a href="#add" data-toggle="tab">
                   <i class="far fa-edit"></i> <?=translate('create_event')?>
                </a>
			</li>
<?php endif; ?>
		</ul>
		<div class="tab-content">
			<div class="tab-pane box active mb-md" id="list">
				<table class="table table-bordered table-hover mb-none tbr-top table-export">
					<thead>
						<tr>
							<th>#</th>
						<?php if (is_superadmin_loggedin()): ?>
							<th><?=translate('branch')?></th>
						<?php endif; ?>
							<th><?=translate('title')?></th>
							<th><?=translate('image')?></th>
							<th><?=translate('type')?></th>
							<th><?=translate('date_of_start')?></th>
							<th><?=translate('date_of_end')?></th>
							<th><?=translate('audience')?></th>
							<th><?=translate('created_by')?></th>
							<th class="no-sort"><?=translate('show_website')?></th>
							<th class="no-sort"><?=translate('publish')?></th>
							<th><?=translate('action')?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$count = 1;
						if (!is_superadmin_loggedin()) {
							$this->db->where('branch_id', get_loggedin_branch_id());
						}
						$this->db->order_by('id', 'asc');
						$events = $this->db->get('event')->result();
						foreach ($events as $event):
						?>
						<tr>
							<td><?php echo $count++; ?></td>
						<?php if (is_superadmin_loggedin()): ?>
							<td><?php echo get_type_name_by_id('branch', $event->branch_id);?></td>
						<?php endif; ?>
							<td><?php echo $event->title; ?></td>
							<td class="center"><img src="<?=base_url('uploads/frontend/events/' . $event->image )?>" height="60" /></td>
							<td><?php
									if($event->type != 'holiday'){
										echo get_type_name_by_id('event_types', $event->type);
									}else{
										echo translate('holiday'); 
									}
								?></td>
							<td><?php echo _d($event->start_date);?></td>
							<td><?php echo _d($event->end_date);?></td>
							<td><?php
								$auditions = array(
									"1" => "everybody",
									"2" => "class",
									"3" => "section",
								);
								$audition = $auditions[$event->audition];
								echo translate($audition);
								if($event->audition != 1){
									if ($event->audition == 2) {
										$selecteds = json_decode($event->selected_list); 
										foreach ($selecteds as $selected) {
											echo "<br> <small> - " . get_type_name_by_id('class', $selected) . '</small>' ;
										}
									} 
									if ($event->audition == 3) {
										$selecteds = json_decode($event->selected_list); 
										foreach ($selecteds as $selected) {
											$selected = explode('-', $selected);
											echo "<br> <small> - " . get_type_name_by_id('class', $selected[0]) . " (" . get_type_name_by_id('section', $selected[1])  . ')</small>' ;
										}
									}
								}
							?></td>
							<td><?php echo get_type_name_by_id('staff', $event->created_by); ?></td>
							<td>
							<?php if (get_permission('event', 'is_edit')) { ?>
								<div class="material-switch ml-xs">
									<input class="event-website" id="websiteswitch_<?=$event->id?>" data-id="<?=$event->id?>" name="evt_switch_website<?=$event->id?>" 
									type="checkbox" <?php echo ($event->show_web == 1 ? 'checked' : ''); ?> />
									<label for="websiteswitch_<?=$event->id?>" class="label-primary"></label>
								</div>
							<?php } ?>
							</td>
							<td>
							<?php if (get_permission('event', 'is_edit')) { ?>
								<div class="material-switch ml-xs">
									<input class="event-switch" id="switch_<?=$event->id?>" data-id="<?=$event->id?>" name="evt_switch<?=$event->id?>" 
									type="checkbox" <?php echo ($event->status == 1 ? 'checked' : ''); ?> />
									<label for="switch_<?=$event->id?>" class="label-primary"></label>
								</div>
							<?php } ?>
							</td>
							<td class="action">
								<!-- view modal link -->
								<a href="javascript:void(0);" class="btn btn-circle btn-default icon" onclick="viewEvent('<?=$event->id?>');">
									<i class="far fa-eye"></i>
								</a>
							<?php if (get_permission('event', 'is_edit')) { ?>
								<!-- edit link -->
								<a href="<?php echo base_url('event/edit/'.$event->id); ?>" class="btn btn-circle btn-default icon"><i class="fas fa-pen-nib"></i></a>
							<?php } ?>
							<?php if (get_permission('event', 'is_delete')) { ?>
								<!-- deletion link -->
								<?php echo btn_delete('event/delete/'.$event->id);?>
							<?php } ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

<?php
$role_id = (int)$this->session->userdata('loggedin_role_id');
if (get_permission('event', 'is_add') || $role_id === 3): ?>
    <div class="tab-pane" id="add">
		<?php echo form_open_multipart($this->uri->uri_string(), array('class' => 'form-bordered form-horizontal frm-submit-data'));?>
		<?php if (is_superadmin_loggedin()): ?>
			<div class="form-group">
				<label class="control-label col-md-3"><?=translate('branch')?> <span class="required">*</span></label>
				<div class="col-md-6">
					<?php
						$arrayBranch = $this->app_lib->getSelectList('branch');
						echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' data-width='100%' id='branch_id'
						data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
					?>
					<span class="error"></span>
				</div>
			</div>
		<?php endif; ?>

		<div class="form-group">
			<label class="col-md-3 control-label"><?=translate('title')?> <span class="required">*</span></label>
			<div class="col-md-6">
				<input type="text" class="form-control" name="title" value="" />
				<span class="error"></span>
			</div>
		</div>

		<div class="form-group">
			<div class="col-md-offset-3">
				<div class="ml-md checkbox-replace">
					<label class="i-checks">
						<input type="checkbox" name="holiday" id="chk_holiday" <?= ($role_id === 3 ? 'disabled' : '') ?>><i></i> Holiday
					</label>
				</div>
			</div>
			<div id="typeDiv">
				<div class="mt-md">
					<label class="col-md-3 control-label"><?=translate('type')?> <span class="required">*</span></label>
					<div class="col-md-6">
<?php
    // Use branch-scoped options passed by the controller; no role filtering.
    $etypeOptions = isset($event_type_options) ? $event_type_options : ['' => translate('select')];
    echo form_dropdown(
        "type_id",
        $etypeOptions,
        set_value('type_id'),
        "class='form-control' id='type_id' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'"
    );
?>
						<span class="error"></span>
					</div>
				</div>
			</div>
		</div>

		<div class="form-group" id='auditionDiv'>
			<label class="col-md-3 control-label"><?=translate('audience')?> <span class="required">*</span></label>
			<div class="col-md-6">
				<?php
					$arrayAudition = array(
						"" => translate('select'),
						"1" => translate('everybody'),
						"2" => translate('selected_class'),
						"3" => translate('selected_section'),
					);
					$audDefault  = ($role_id === 3 ? '2' : set_value('audition'));
					$audDisabled = ($role_id === 3 ? ' disabled' : '');
					echo form_dropdown(
						"audition",
						$arrayAudition,
						$audDefault,
						"class='form-control' id='audition' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'".$audDisabled
					);
				?>
<?php if ($role_id === 3): ?>
	<!-- Disabled selects are not posted; this hidden input carries the value -->
	<input type="hidden" name="audition" value="2">
<?php endif; ?>
				<span class="error"></span>
			</div>
		</div>

		<div class="form-group" id="selected_user" style="display: none;">
			<label class="col-md-3 control-label" id="selected_label"> <?=translate('audience')?> <span class="required">*</span> </label>
			<div class="col-md-6">
				<?php if ($role_id === 3): ?>
					<?php
						// Prefer a controller-provided array of classes: $teacher_classes = [id => name, ...]
						$tClasses = (isset($teacher_classes) && is_array($teacher_classes)) ? $teacher_classes : [];
						// Backward-compatible single class fallback
						if (empty($tClasses) && !empty($teacher_class_id)) {
							$tClasses = [(int)$teacher_class_id => get_type_name_by_id('class', $teacher_class_id)];
						}
					?>
					<select name="selected_audience[]" id="selected_audience" class="form-control" data-plugin-selectTwo data-width="100%" multiple disabled>
						<?php if (!empty($tClasses)): ?>
							<?php foreach ($tClasses as $cid => $cname): ?>
								<option value="<?= (int)$cid ?>" selected><?= html_escape($cname) ?></option>
							<?php endforeach; ?>
						<?php else: ?>
							<option value=""><?= translate('not_assigned') ?></option>
						<?php endif; ?>
					</select>
					<?php if (!empty($tClasses)): ?>
						<!-- Mirror disabled multiselect so values are submitted -->
						<?php foreach ($tClasses as $cid => $ignore): ?>
							<input type="hidden" name="selected_audience[]" value="<?= (int)$cid ?>">
						<?php endforeach; ?>
					<?php endif; ?>
					<span class="error"></span>
				<?php else: ?>
					<?php
						$placeholder = '{"placeholder": "' . translate('select') . '"}';
						// start empty; filled via AJAX when audience is chosen
						echo form_dropdown(
							"selected_audience[]",
							array(),
							set_value('selected_audience'),
							"class='form-control' id='selected_audience' multiple data-plugin-selectTwo data-plugin-options='$placeholder' data-width='100%'"
						);
					?>
					<span class="error"></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="form-group">
			<label class="col-md-3 control-label"><?=translate('date')?> <span class="required">*</span></label>
			<div class="col-md-6">
				<div class="input-group">
					<span class="input-group-addon"><i class="far fa-calendar-alt"></i></span>
					<input type="text" class="form-control" name="daterange" id="daterange" 
					value="<?=set_value('daterange', date("Y/m/d") . ' - ' . date("Y/m/d", strtotime("+2 day")))?>" />
				</div>
				<span class="error"></span>
			</div>
		</div>

		<!-- All Day + Time (minimal, aligned to form layout) -->
		<div class="form-group">
			<label class="col-md-3 control-label"><?= translate('all_day'); ?></label>
			<div class="col-md-6">
				<label class="switch" style="margin-top:6px">
					<input type="checkbox" id="all_day" name="all_day" value="1" checked>
					<span class="slider round"></span>
				</label>
			</div>
		</div>

		<div class="form-group" id="timeRow" style="display:none;">
			<label class="col-md-3 control-label"><?= translate('time'); ?></label>
			<div class="col-md-3">
				<input type="time" name="start_time" class="form-control" placeholder="<?= translate('start_time'); ?>">
			</div>
			<div class="col-md-3">
				<input type="time" name="end_time" class="form-control" placeholder="<?= translate('end_time'); ?>">
			</div>
		</div>

		<style>
		/* simple switch style; remove if you already have a switch component */
		.switch{position:relative;display:inline-block;width:46px;height:24px}
		.switch input{display:none}
		.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.2s;border-radius:24px}
		.slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:white;transition:.2s;border-radius:50%}
		input:checked + .slider{background:#16a34a}
		input:checked + .slider:before{transform:translateX(22px)}
		</style>

		<div class="form-group">
			<label class="col-md-3 control-label"><?=translate('description')?></label>
			<div class="col-md-6">
				<textarea name="remarks" class="summernote"></textarea>
			</div>
		</div>

		<div class="form-group">
			<label class="col-md-3 control-label"><?=translate('show_website')?></label>
			<div class="col-md-6">
				<div class="material-switch ml-xs">
					<input id="aswitch_1" name="show_website" type="checkbox" />
					<label for="aswitch_1" class="label-primary"></label>
				</div>
			</div>
		</div>

		<div class="form-group">
			<label class="col-md-3 control-label"><?=translate('image')?></label>
			<div class="col-md-6">
				<div class="fileupload fileupload-new" data-provides="fileupload">
					<div class="input-append">
						<div class="uneditable-input">
							<i class="fas fa-file fileupload-exists"></i>
							<span class="fileupload-preview"></span>
						</div>
						<span class="btn btn-default btn-file">
							<span class="fileupload-exists">Change</span>
							<span class="fileupload-new">Select file</span>
							<input type="file" name="user_photo" />
						</span>
						<a href="#" class="btn btn-default fileupload-exists" data-dismiss="fileupload">Remove</a>
					</div>
				</div>
				<span class="error"></span>
			</div>
		</div>

		<footer class="panel-footer">
			<div class="row">
				<div class="col-md-offset-3 col-md-2">
					<button type="submit" class="btn btn-default btn-block" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
						<i class="fas fa-plus-circle"></i> <?=translate('save')?>
					</button>
				</div>
			</div>
		</footer>
		<?php echo form_close(); ?>
	</div>
<?php endif; ?>
		</div>
	</div>
</section>

<div class="zoom-anim-dialog modal-block modal-block-primary mfp-hide" id="modal">
	<section class="panel">
		<header class="panel-heading">
			<div class="panel-btn">
				<button onclick="fn_printElem('printResult')" class="btn btn-default btn-circle icon" ><i class="fas fa-print"></i></button>
			</div>
			<h4 class="panel-title"><i class="fas fa-info-circle"></i> <?=translate('event_details')?></h4>
		</header>
		<div class="panel-body">
			<div id="printResult" class="pt-sm pb-sm">
				<div class="table-responsive">						
					<table class="table table-bordered table-condensed text-dark tbr-top" id="ev_table"></table>
				</div>
			</div>
		</div>
		<footer class="panel-footer">
			<div class="row">
				<div class="col-md-12 text-right">
					<button class="btn btn-default modal-dismiss">
						<?=translate('close')?>
					</button>
				</div>
			</div>
		</footer>
	</section>
</div>

<script type="text/javascript">
	(function(){
		// toggle time row (no jQuery dependency)
		var cb  = document.getElementById('all_day');
		var row = document.getElementById('timeRow');
		function toggle(){ row.style.display = (cb && cb.checked) ? 'none' : 'block'; }
		if (cb){ cb.addEventListener('change', toggle); toggle(); }
	})();
</script>

<script type="text/javascript">
$(document).ready(function () {
    var ROLE_ID = <?= (int)$this->session->userdata('loggedin_role_id'); ?>;
    var TEACHER_CLASS_ID = <?= isset($teacher_class_id) ? (int)$teacher_class_id : 0; ?>;

    // date range
    $('#daterange').daterangepicker({
        opens: 'left',
        locale: { format: 'YYYY/MM/DD' }
    });

    // helper to (re)load event types for a given branch depending on role
    function loadTypesForRole(branchID) {
        var url, postData;
        if (ROLE_ID === 3) {
            // TEACHER => use our controller endpoint (branch + global types)
            url = "<?= base_url('event/getTypesByBranch') ?>";
            postData = { branch_id: branchID };
        } else {
            // EVERYONE ELSE => old endpoint (keeps previous behaviour)
            url = "<?= base_url('ajax/getDataByBranch') ?>";
            postData = { branch_id: branchID, table: 'event_types' };
        }
        $.ajax({
            url: url,
            type: 'POST',
            data: postData,
            success: function (html) {
                if ($('#type_id').length) {
                    $('#type_id').html(html).trigger('change');
                }
            }
        });
    }

    // branch change (superadmin only has this selector)
    $('#branch_id').on('change', function () {
        var branchID = $(this).val();
        loadTypesForRole(branchID);
        $("#selected_audience").empty();
    });

    // audition handling
    $('#audition').on('change', function () {
        var audition = $(this).val();
        var branchID = ($('#branch_id').length ? $('#branch_id').val() : "<?= (int)$branch_id ?>");

        if (audition == "1" || audition == null) {
            $("#selected_user").hide("slow");
        }
        if (audition == "2") {
            if (ROLE_ID !== 3) {
                $.ajax({
                    url: base_url + 'ajax/getClassByBranch',
                    type: 'POST',
                    data: { branch_id: branchID },
                    success: function (data) {
                        $('#selected_audience').html(data);
                    }
                });
            }
            $("#selected_user").show('slow');
            $("#selected_label").html("<?= translate('class') ?> <span class='required'>*</span>");
        }
        if (audition == "3") {
            $.ajax({
                url: "<?= base_url('event/getSectionByBranch') ?>",
                type: 'POST',
                data: { branch_id: branchID },
                success: function (data) {
                    $('#selected_audience').html(data);
                }
            });
            $("#selected_user").show('slow');
            $("#selected_label").html("<?= translate('section') ?> <span class='required'>*</span>");
        }
    });

    // Teachers don’t have a branch selector; load their types on first paint
    if (ROLE_ID === 3) {
        var initialBranch = ($('#branch_id').length ? $('#branch_id').val() : "<?= (int)$branch_id ?>");
        loadTypesForRole(initialBranch);
    }

    // Lock UI for teachers (audience limited to their class(es))
    if (ROLE_ID === 3) {
        $('#chk_holiday').prop('checked', false).prop('disabled', true);
        $('#audition').val('2').trigger('change').prop('disabled', true);
        $('#selected_user').show('slow');
        $('#selected_label').html("<?= translate('class') ?> <span class='required'>*</span>");

        if (TEACHER_CLASS_ID && $('#selected_audience option').length === 0) {
            $('#selected_audience').append(
                $('<option>', {
                    value: TEACHER_CLASS_ID,
                    text: '<?= isset($teacher_class_id) && $teacher_class_id ? get_type_name_by_id('class', $teacher_class_id) : '' ?>',
                    selected: true
                })
            );
        }
        $('#selected_audience').prop('disabled', true);
    }
});
</script>
