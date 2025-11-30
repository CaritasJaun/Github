<section class="panel">
	<div class="tabs-custom">
		<ul class="nav nav-tabs">
			<li>
				<a href="<?php echo base_url('event/index') ?>">
				  <i class="fas fa-list-ul"></i> <?=translate('event_list')?>
				</a>
			</li>
			<li class="active">
				<a href="#add" data-toggle="tab">
				 <i class="far fa-edit"></i> <?=translate('edit_event')?>
				</a>
			</li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane active" id="add">
<?php
	// build a simple (id => color) map for the current branch to preview type color
	$typesColorMap = [];
	if (!empty($event['branch_id'])) {
		$this->db->where('branch_id', $event['branch_id']);
		$qTypes = $this->db->get('event_types')->result_array();
		foreach ($qTypes as $t) {
			$typesColorMap[$t['id']] = $t['color'];
		}
	}
	$currentTypeColor = (is_numeric($event['type']) && isset($typesColorMap[$event['type']])) ? $typesColorMap[$event['type']] : '';
	$all_day_checked = isset($event['all_day']) ? ((int)$event['all_day'] === 1) : true;
?>
					<?php echo form_open_multipart($this->uri->uri_string(), array('class' => 'form-bordered form-horizontal frm-submit-data'));?>
					<input type="hidden" name="id" value="<?php echo $event['id'] ?>">	
					<?php if (is_superadmin_loggedin()): ?>
						<div class="form-group">
							<label class="control-label col-md-3"><?=translate('branch')?> <span class="required">*</span></label>
							<div class="col-md-6">
								<?php
									$arrayBranch = $this->app_lib->getSelectList('branch');
									echo form_dropdown("branch_id", $arrayBranch, $event['branch_id'], "class='form-control' data-width='100%' id='branch_id'
									data-plugin-selectTwo  data-minimum-results-for-search='Infinity'");
								?>
								<span class="error"></span>
							</div>
						</div>
					<?php endif; ?>

					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('title')?> <span class="required">*</span></label>
						<div class="col-md-6">
							<input type="text" class="form-control" name="title" value="<?php echo html_escape($event['title']); ?>" />
							<span class="error"></span>
						</div>
					</div>

					<div class="form-group">
						<div class="col-md-offset-3">
							<div class="ml-md checkbox-replace">
								<label class="i-checks">
									<input type="checkbox" <?php echo $event['type'] == 'holiday' ? 'checked' : '' ?> name="holiday" id="chk_holiday"><i></i> Holiday
								</label>
							</div>
						</div>
						<div id="typeDiv" <?php echo $event['type'] == 'holiday' ? 'style="display: none;"' : '' ?>>
							<div class="mt-md">
								<label class="col-md-3 control-label"><?=translate('type')?> <span class="required">*</span></label>
								<div class="col-md-6">
									<?php
										$array = $this->app_lib->getSelectByBranch('event_types', $event['branch_id']);
										echo form_dropdown("type_id", $array, $event['type'], "class='form-control' id='type_id'
										data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
									?>
									<span class="error"></span>
								</div>
								<!-- Type color preview (dictated by event type) -->
								<div class="col-md-3">
									<div id="type_color_preview" class="type-color-swatch" title="<?= translate('event_type_color'); ?>" style="margin-top:6px;background: <?= $currentTypeColor ? html_escape($currentTypeColor) : '#dddddd' ?>;"></div>
								</div>
							</div>
						</div>
					</div>

					<div class="form-group" id='auditionDiv' <?php echo $event['type'] == 'holiday' ? 'style="display: none;"' : '' ?>>
						<label class="col-md-3 control-label"><?=translate('audience')?> <span class="required">*</span></label>
						<div class="col-md-6">
							<?php
								$arrayAudition = array(
									"" => translate('select'),
									"1" => translate('everybody'),
									"2" => translate('selected_class'),
									"3" => translate('selected_section'),
								);
								echo form_dropdown("audition", $arrayAudition, $event['audition'], "class='form-control' id='audition'
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
							?>
							<span class="error"></span>
						</div>
					</div>

					<div class="form-group" id="selected_user" <?php echo $event['audition'] == 1 ? 'style="display: none;"' : ''?>>
						<label class="col-md-3 control-label" id="selected_label"> <?=translate('audience')?> <span class="required">*</span> </label>
						<div class="col-md-6">
							<?php
								$placeholder = '{"placeholder": "' . translate('select') . '"}';
								echo form_dropdown("selected_audience[]", [], '', "class='form-control' data-plugin-selectTwo 
								data-plugin-options='$placeholder' data-plugin-selectTwo data-width='100%' id='selected_audience' multiple");
							?>
							<span class="error"></span>
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('date')?> <span class="required">*</span></label>
						<div class="col-md-6">
							<div class="input-group">
								<span class="input-group-addon"><i class="far fa-calendar-alt"></i></span>
								<input type="text" class="form-control" name="daterange" id="daterange" value="<?=set_value('daterange', $event['start_date'] . ' - ' . $event['end_date'])?>" />
							</div>
							<span class="error"></span>
						</div>
					</div>

					<!-- All Day + Time -->
					<div class="form-group">
						<label class="col-md-3 control-label"><?= translate('all_day'); ?></label>
						<div class="col-md-6">
							<label class="switch" style="margin-top:6px">
								<input type="checkbox" id="all_day" name="all_day" value="1" <?php echo $all_day_checked ? 'checked' : ''; ?>>
								<span class="slider round"></span>
							</label>
						</div>
					</div>

					<div class="form-group" id="timeRow" style="display:none;">
						<label class="col-md-3 control-label"><?= translate('time'); ?></label>
						<div class="col-md-3">
							<input type="time" name="start_time" class="form-control" value="<?= html_escape($event['start_time'] ?? '') ?>" placeholder="<?= translate('start_time'); ?>">
						</div>
						<div class="col-md-3">
							<input type="time" name="end_time" class="form-control" value="<?= html_escape($event['end_time'] ?? '') ?>" placeholder="<?= translate('end_time'); ?>">
						</div>
					</div>

					<style>
					/* simple switch & swatch */
					.switch{position:relative;display:inline-block;width:46px;height:24px}
					.switch input{display:none}
					.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.2s;border-radius:24px}
					.slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:white;transition:.2s;border-radius:50%}
					input:checked + .slider{background:#16a34a}
					input:checked + .slider:before{transform:translateX(22px)}
					.type-color-swatch{width:38px;height:24px;border-radius:6px;border:1px solid rgba(0,0,0,.15);display:inline-block}
					</style>

					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('description')?></label>
						<div class="col-md-6">
							<textarea name="remarks" class="summernote"><?php echo html_escape($event['remark']); ?></textarea>
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('show_website')?></label>
						<div class="col-md-6">
							<div class="material-switch ml-xs">
								<input id="aswitch_1" name="show_website" <?php echo $event['show_web'] == 1 ? 'checked' : '' ?> type="checkbox" />
								<label for="aswitch_1" class="label-primary"></label>
							</div>
						</div>
					</div>

					<div class="form-group">
						<label class="col-md-3 control-label"><?=translate('image')?></label>
						<div class="col-md-6">
							<input type="hidden" name="old_event_image" value="<?php echo html_escape($event['image']); ?>">
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
									<i class="fas fa-plus-circle"></i> <?=translate('update')?>
								</button>
							</div>
						</div>
					</footer>
				<?php echo form_close(); ?>
			</div>

		</div>
	</div>
</section>

<script type="text/javascript">
	// initial constants from PHP for color preview
	var TYPE_COLORS = <?php echo json_encode($typesColorMap ?: []); ?>;

	function updateTypeColorPreview() {
		var sel = document.getElementById('type_id');
		var sw  = document.getElementById('type_color_preview');
		if (!sel || !sw) return;
		var color = TYPE_COLORS[sel.value] || '#dddddd';
		sw.style.background = color || '#dddddd';
	}

	function toggleTimeRow() {
		var cb  = document.getElementById('all_day');
		var row = document.getElementById('timeRow');
		if (!cb || !row) return;
		row.style.display = cb.checked ? 'none' : 'block';
	}

    $(document).ready(function() {
        $('#daterange').daterangepicker({
            opens: 'left',
            locale: { format: 'YYYY/MM/DD' }
        });

		// time row visibility on load and change
		toggleTimeRow();
		$('#all_day').on('change', toggleTimeRow);

		// color preview for current selection + on change
		updateTypeColorPreview();
		$('#type_id').on('change', updateTypeColorPreview);

        $('#branch_id').on('change', function() {
            var branchID = $(this).val();
            $.ajax({
                url: "<?=base_url('ajax/getDataByBranch')?>",
                type: 'POST',
                data: { branch_id: branchID, table: 'event_types' },
                success: function(data) {
                    $('#type_id').html(data);
					// reset preview & map (we don't know new colors without a custom endpoint)
					TYPE_COLORS = {};
					updateTypeColorPreview();
                }
            });
            $("#selected_audience").empty();
        });

        $('#audition').on('change', function() {
            var audition = $(this).val();
            var branchID = ($('#branch_id').length ? $('#branch_id').val() : "");
            auditionAjax(audition, branchID);
        });

        auditionAjax("<?php echo (int)$event['audition']; ?>", "<?php echo (int)$event['branch_id']; ?>");
    });

	function auditionAjax(audition = '', branchID = '') {
	    if (audition == "1" || audition == null) {
	        $("#selected_user").hide("slow");
	    } else {
	        if (audition == "2") {
	            $.ajax({
	                url: base_url + 'ajax/getClassByBranch',
	                type: 'POST',
	                data: { branch_id: branchID },
	                success: function(data) {
	                    $('#selected_audience').html(data);
	                }
	            });
	            $("#selected_user").show('slow');
	            $("#selected_label").html("<?=translate('class')?> <span class='required'>*</span>");
	        }
	        if (audition == "3") {
	            $.ajax({
	                url: "<?=base_url('event/getSectionByBranch')?>",
	                type: 'POST',
	                data: { branch_id: branchID },
	                success: function(data) {
	                    $('#selected_audience').html(data);
	                }
	            });
	            $("#selected_user").show('slow');
	            $("#selected_label").html("<?=translate('section')?> <span class='required'>*</span>");
	        }
	        setTimeout(function() {
				// selected_list is already JSON; embed safely without JSON.parse string pitfalls
	            var JSONObject = <?php echo !empty($event['selected_list']) ? $event['selected_list'] : '[]'; ?>;
	            for (var i = 0, l = JSONObject.length; i < l; i++) {
	                $("#selected_audience option[value='" + JSONObject[i] + "']").prop("selected", true);
	            }
	            $('#selected_audience').trigger('change.select2');
	        }, 200);
	    }
	}
</script>
