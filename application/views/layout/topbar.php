<?php
// application/views/layout/topbar.php
?>
<header class="header">
	<div class="logo-env">
		<a href="<?php echo base_url('dashboard');?>" class="logo">
			<img src="<?=$this->application_model->getBranchImage(get_loggedin_branch_id(), 'logo-small')?>" height="40">
		</a>

		<div class="visible-xs toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
			<i class="fa fa-bars" aria-label="Toggle sidebar"></i>
		</div>
	</div>

	<div class="header-left hidden-xs">
		<ul class="header-menu">
			<!-- sidebar toggle button -->
			<li>
				<div class="header-menu-icon sidebar-toggle" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
					<i class="fas fa-bars" aria-label="Toggle sidebar"></i>
				</div>
			</li>
			<!-- full screen button -->
			<li>
				<div class="header-menu-icon s-expand">
					<i class="fas fa-expand"></i>
				</div>
			</li>
			<!-- shortcut box -->
            <?php
            if(get_permission('student', 'is_add') ||
            get_permission('salary_payment', 'is_add') ||
            get_permission('leave_manage', 'is_view') ||
            get_permission('live_class', 'is_view') ||
            get_permission('due_invoice', 'is_view') ||
            get_permission('invoice', 'is_view')) {
            ?>
			<li>
				<div class="header-menu-icon dropdown-toggle" data-toggle="dropdown">
					<i class="fas fa-th"></i>
				</div>
				<div class="dropdown-menu header-menubox qk-menu">
					<div class="qk-menu-p">
						<div class="menu-icon-grid">
						<?php if(get_permission('student', 'is_add')){ ?>
							<a href="<?php echo base_url('student/add');?>"><i class="fas fa-users"></i> <?php echo translate('student_admission');?></a>
						<?php } if(get_permission('salary_payment', 'is_add')) { ?>
							<a href="<?php echo base_url('payroll'); ?>"><i class="fas fa-donate"></i> <?php echo translate('salary_payment');?></a>
						<?php } if(get_permission('leave_manage', 'is_view')) { ?>
							<a href="<?php echo base_url('leave');?>"><i class="fas fa-fill-drip"></i> <?php echo translate('leave_application');?></a>
						<?php } if(get_permission('live_class', 'is_view')) { ?>
							<a href="<?php echo base_url('live_class');?>"><i class="fas fa-video"></i> <?php echo translate('live_class_rooms');?></a>
						<?php } if(get_permission('due_invoice', 'is_view')) { ?>
							<a href="<?php echo base_url('fees/due_invoice');?>"><i class="fas fa-hand-holding-usd"></i> <?php echo translate('due_fees_invoice');?></a>
						<?php } if(get_permission('invoice', 'is_view')) { ?>
							<a href="<?php echo base_url('fees/invoice_list');?>"><i class="fab fa-wpforms"></i> <?php echo translate('payments_history');?></a>
						<?php } ?>
						</div>
					</div>
				</div>
			</li>
			<?php } ?>
		</ul>

		<!-- search bar -->
		<?php if (get_permission('student', 'is_view')): ?>
			<span class="separator hidden-sm"></span>
			<?php echo form_open('student/search', array('class' => 'search nav-form'));?>
				<div class="input-group input-search">
					<input type="text" class="form-control" name="search_text" id="search_text" placeholder="<?php echo translate('search');?>">
					<span class="input-group-btn">
						<button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
					</span>
				</div>
			</form>
		<?php endif;?>
	</div>

	<div class="header-right">
		<ul class="header-menu">

<!-- 🔔 Notification Bell -->
<?php
// --- safe URL builder for notification links ---
if (!function_exists('notif_safe_url')) {
    function notif_safe_url($u) {
        $u = trim((string)$u);
        if ($u === '') return 'javascript:void(0)';
        if (preg_match('#^(https?:)?//#i', $u)) return $u; // absolute
        $u = ltrim($u, '/');
        return site_url($u);
    }
}
$CI =& get_instance();
$CI->load->model('notification_model');
$unread_count = $CI->notification_model->count_unread(get_loggedin_user_id());
$notifications = $CI->notification_model->get_notifications_for_user(get_loggedin_user_id(), true, 10);
?>
<li class="dropdown">
    <a href="#" class="dropdown-toggle header-menu-icon" data-toggle="dropdown">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="badge badge-danger badge-notification-count"><?= $unread_count ?></span>
        <?php endif; ?>
    </a>
	<div class="dropdown-menu header-menubox mh-oh">
		<div class="notification-title">
			<i class="fas fa-bell"></i> New Notifications
			<button type="button" id="clear-notifications" class="btn btn-link btn-xs pull-right">Clear all</button>
		</div>
		<div class="content hbox lb-pr">
			<div class="scrollable visible-slider dh-tf" data-plugin-scrollable>
				<div class="scrollable-content">
					<ul id="notif-list">
						<?php foreach ($notifications as $note): ?>
							<li>
								<a class="notif-item"
								   data-id="<?= (int)($note['id'] ?? 0); ?>"
								   href="<?= html_escape(notif_safe_url($note['url'] ?? '#')); ?>">
									<?= html_escape($note['message'] ?? '') ?>
									<small class="text-muted d-block"><?= isset($note['created_at']) ? date('d M H:i', strtotime($note['created_at'])) : '' ?></small>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</li>

<?php 
if (is_student_loggedin()):
	$this->db->select('enroll.id,class.name as class_name,section.name as section_name');
	$this->db->from('enroll');
	$this->db->join('class', 'class.id = enroll.class_id', 'inner');
	$this->db->join('section', 'section.id = enroll.section_id', 'inner');
	$this->db->where('enroll.student_id', get_loggedin_user_id());
	$this->db->where('enroll.session_id', get_session_id());
	$this->db->order_by('enroll.id', 'asc');
	$multiClass = $this->db->get()->result();
	if (count($multiClass) > 1) {
	?>
			<!-- switch class -->
			<li>
				<a href="#" class="dropdown-toggle header-menu-icon" data-toggle="dropdown">
					<i class="fa-solid fa-retweet"></i>
				</a>
				<div class="dropdown-menu header-menubox mh-oh">
					<div class="notification-title">
						<i class="fa-solid fa-retweet"></i> <?php echo translate('switch_class');?>
					</div>
					<div class="content hbox lb-pr">
						<div class="scrollable visible-slider dh-tf" data-plugin-scrollable>
							<div class="scrollable-content">
								<ul>
<?php
if ($this->session->has_userdata('enrollID')) {
	$currentClass = $this->session->userdata('enrollID');
} else {
	$currentClass = get_global_setting('translation');
}
foreach ($multiClass as $key => $class):
		?>
	<li>
		<a href="<?php echo base_url('userrole/switchClass/' . $class->id);?>">
			<?php echo $class->class_name . " (" . $class->section_name . ")";  ?>  <?php echo $class->id == $currentClass ? '<i class="fas fa-check"></i>' : ''; ?>
		</a>
	</li>
<?php endforeach;?>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</li>
<?php  } 
	endif;
	?>

		<?php 
		$showwebURL = false;
		$webURL = "";
		if (is_superadmin_loggedin()) {
			$showwebURL = true;
			$webURL = base_url();
		} else {
			$cmsRow = $this->db->select('cms_active,url_alias')
			->where('branch_id', get_loggedin_branch_id())
			->get('front_cms_setting')->row_array();
			if (isset($cmsRow['cms_active']) && $cmsRow['cms_active'] == 1) {
				$showwebURL = true;
			}
			$webURL = base_url((isset($cmsRow['url_alias']) ? $cmsRow['url_alias'] : '') );
		}
		if ($showwebURL) {
		?>
			<!-- website link -->
			<li>
				<a href="<?php echo $webURL; ?>" target="_blank" class="header-menu-icon" data-toggle="tooltip" data-placement="bottom"
				data-original-title="<?php echo translate('visit_home_page'); ?>">
					<i class="fas fa-globe"></i>
				</a>
			</li>
		<?php } ?>
<?php 
if (is_student_loggedin() || is_parent_loggedin()) {
	$student_id = $this->session->userdata('student_id');
	if (!empty($student_id)) {
	    $this->db->select('schoolyear.*')->from('schoolyear');
	    $this->db->join('enroll', 'schoolyear.id = enroll.session_id');
	    $this->db->where('enroll.student_id', $student_id);
	    $this->db->group_by('enroll.session_id');
	    $this->db->order_by('schoolyear.id');
	    $query = $this->db->get();
	    $get_session = $query->result();
	}
} else {
	$get_session = $this->db->get('schoolyear')->result();
}
if (!empty($get_session)) {
	?>
			<!-- session switcher box -->
			<li>
				<a href="#" class="dropdown-toggle header-menu-icon" data-toggle="dropdown">
					<i class="far fa-calendar-alt"></i>
				</a>
				<div class="dropdown-menu header-menubox mh-oh">
					<div class="notification-title">
						<i class="far fa-calendar-alt"></i> <?php echo translate('academic_session');?>
					</div>
					<div class="content hbox pr-none">
						<div class="scrollable visible-slider dh-tf" data-plugin-scrollable>
							<div class="scrollable-content">
								<ul>
<?php foreach ($get_session as $session) {?><li>
										<a href="<?php echo base_url('sessions/set_academic/' . $session->id);?>"><?php echo $session->school_year;?> <?php echo get_session_id() == $session->id ? '<i class="fas fa-check"></i>' : ''; ?></a>
									</li>
<?php } ?>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</li>
<?php } ?>
			<!-- languages switcher box -->
			<li>
				<a href="#" class="dropdown-toggle header-menu-icon" data-toggle="dropdown">
					<i class="far fa-flag"></i>
				</a>
				<div class="dropdown-menu header-menubox mh-oh">
					<div class="notification-title">
						<i class="far fa-flag"></i> <?php echo translate('language');?>
					</div>
					<div class="content hbox lb-pr">
						<div class="scrollable visible-slider dh-tf" data-plugin-scrollable>
							<div class="scrollable-content">
								<ul>
<?php
if ($this->session->has_userdata('set_lang')) {
	$set_lang = $this->session->userdata('set_lang');
} else {
	$set_lang = get_global_setting('translation');
}
$languages = $this->db->select('id,lang_field,name')->where('status', 1)->get('language_list')->result();
foreach($languages as $lang) :
?>
	<li>
		<a href="<?php echo base_url('translations/set_language/' . html_escape($lang->lang_field));?>">
		<img class="ln-img" src="<?php echo $this->application_model->getLangImage($lang->id);?>" 
		alt="<?php echo $lang->lang_field;?>"> <?php echo ucfirst(html_escape($lang->name));?> <?php echo ($set_lang == $lang->lang_field ? '<i class="fas fa-check"></i>' : ''); ?>
		</a>
	</li>
<?php endforeach;?>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</li>
			<!-- message alert box -->
			<li>
				<a href="#" class="dropdown-toggle header-menu-icon" data-toggle="dropdown">
					<i class="far fa-bell"></i>
					<?php 
						$unreadMessage	= $this->application_model->unread_message_alert();
						if (count($unreadMessage) > 0) {
							echo '<span class="badge">' . count($unreadMessage) . '</span>';
						} 
					?>
				</a>
				<div class="dropdown-menu header-menubox qmsg-box-mw">
					<div class="notification-title">
						<i class="far fa-bell"></i> <?php echo translate('message');?>
					</div>
					<div class="content">
						<ul>
							<?php
								if (count($unreadMessage) > 0) {
									foreach ($unreadMessage as $message):
										?>
								<li>
									<a href="<?php echo base_url('communication/mailbox/read?type='.$message['msg_type'].'&id='.$message['id']);?>" class="clearfix">
										<!-- preview of sender image -->
										<figure class="image pull-right">
											<img src="<?php echo $message['message_details']['imgPath']; ?>" height="40px" width="40px" class="img-circle">
										</figure>
										<!-- preview of sender name and date -->
										<span class="title line"><strong><?php echo $message['message_details']['userName']; ?></strong>
										<small>- <?php echo get_nicetime($message['created_at']);?></small>  </span>
										<!-- preview of the last unread message sub-string -->
										<span class="message"><?php echo mb_strimwidth(strip_tags($message['body']), 0, 35, "..."); ?></span>
									</a>
								</li>
							<?php
									endforeach; 
								}else{
									echo '<li class="text-center">You do not have any new messages</li>';
								}
							?>
						</ul>
					</div>
					<div class="notification-footer">
						<div class="text-right">
							<a href="<?php echo base_url('communication/mailbox/inbox');?>" class="view-more">All Messages</a>
						</div>
					</div>
				</div>
			</li>
		</ul>

		<!-- user profile box -->
		<span class="separator"></span>
		<div id="userbox" class="userbox">
			<a href="#" data-toggle="dropdown">
				<figure class="profile-picture">
					<img src="<?php echo get_image_url(get_loggedin_user_type(), $this->session->userdata('logger_photo'));?>" alt="user-image" class="img-circle" height="35">
				</figure>
			</a>
			<div class="dropdown-menu">
				<ul class="dropdown-user list-unstyled">
					<li class="user-p-box">
						<div class="dw-user-box">
							<div class="u-img">
								<img src="<?php echo get_image_url(get_loggedin_user_type(), $this->session->userdata('logger_photo'));?>" alt="user">
							</div>
							<div class="u-text">
								<h4><?php echo $this->session->userdata('name');?></h4>
								<p class="text-muted"><?php echo ucfirst(loggedin_role_name());?></p>
								<a href="<?php echo base_url('authentication/logout'); ?>" class="btn btn-danger btn-xs"><i class="fas fa-sign-out-alt"></i> <?php echo translate('logout');?></a>
							</div>
						</div>
					</li>
					<li role="separator" class="divider"></li>

<?php
// ---------- Robust profile links with auto-fallbacks (all roles default to /profile) ----------
if (is_parent_loggedin()) {
    // Parents also use the same /profile controller
    $profileCandidates  = ['profile', 'parents/profile']; // keep legacy as fallback if you ever add it
    $passwordCandidates = ['profile/password', 'parents/password'];
} elseif (is_student_loggedin()) {
    $profileCandidates  = ['profile'];
    $passwordCandidates = ['profile/password'];
} else {
    // Staff-like (includes Principal)
    $profileCandidates  = ['profile', 'employee/profile', 'user/profile', 'staff/profile', 'principal/profile'];
    $passwordCandidates = ['profile/password', 'employee/password', 'user/password', 'staff/password', 'principal/password'];
}
$toAbs  = function($list){ return array_map(function($p){ return site_url($p); }, $list); }; // use site_url for consistency
$profAbs = $toAbs($profileCandidates);
$passAbs = $toAbs($passwordCandidates);
?>

<li><a id="topbar-profile-link"  href="<?= $profAbs[0] ?>" data-fallback="<?= html_escape(implode(',', array_slice($profAbs, 1))) ?>">
    <i class="fas fa-user-shield"></i> <?= translate('profile') ?></a></li>
<li><a id="topbar-password-link" href="<?= $passAbs[0] ?>" data-fallback="<?= html_escape(implode(',', array_slice($passAbs, 1))) ?>">
    <i class="fas fa-mars-stroke-h"></i> <?= translate('reset_password') ?></a></li>

					<li><a href="<?php echo base_url('communication/mailbox/inbox');?>"><i class="far fa-envelope"></i> <?php echo translate('mailbox');?></a></li>
					<?php 
					$u2FAExisting = $this->app_lib->isExistingAddon('two_fa');
					if ($u2FAExisting) {
						include "tfa_topbar_menu.php";
					} ?>
					<?php if(get_permission('global_settings', 'is_view')):?>
						<li role="separator" class="divider"></li>
						<li><a href="<?php echo base_url('settings/universal');?>"><i class="fas fa-toolbox"></i> <?php echo translate('global_settings');?></a></li>
					<?php endif; ?>
					<?php if(get_permission('school_settings', 'is_view') && !is_superadmin_loggedin()):?>
						<li role="separator" class="divider"></li>
						<li><a href="<?php echo base_url('school_settings');?>"><i class="fas fa-school"></i> <?php echo translate('school_settings');?></a></li>
					<?php endif; ?>
					<li role="separator" class="divider"></li>
					<li><a href="<?php echo base_url('authentication/logout');?>"><i class="fas fa-sign-out-alt"></i> <?php echo translate('logout');?></a></li>
				</ul>
			</div>
		</div>
	</div>
</header>

<!-- minimal JS: notifications + robust profile/password fallbacks -->
<script>
(function(){
  /* ... keep existing code above ... */

  // ---- Robust link fallbacks (handles principal/staff route differences) ----
  function withFallback(id){
    var a = document.getElementById(id);
    if(!a) return;

    a.addEventListener('click', function(e){
      e.preventDefault();

      var first = this.href;
      var fb = (this.getAttribute('data-fallback')||'')
                 .split(',').map(function(s){return s.trim();}).filter(Boolean);
      var list = [first].concat(fb);

      (function tryNext(i){
        if(i >= list.length){ window.location.href = first; return; }

        var url = list[i];
        fetch(url, { method:'GET', credentials:'same-origin', cache:'no-store', redirect:'follow' })
          .then(function(r){
            if(!r.ok) return Promise.resolve({ok:false});
            var finalUrl = r.url || url;
            // If we got bounced to dashboard, treat as failure and try next.
            try {
              var path = new URL(finalUrl, window.location.origin).pathname.replace(/\/+$/,'');
              if (/\/dashboard$/i.test(path)) return {ok:false};
            } catch(e){ /* ignore URL parse errors */ }

            // Peek at the body to detect "Access Denied" style pages that still return 200.
            return r.text().then(function(txt){
              var denied = /\b(access\s*denied|not\s*authorized|no\s*permission)\b/i.test(txt);
              return {ok: !denied};
            });
          })
          .then(function(res){
            if(res && res.ok){
              window.location.href = url;
            } else {
              tryNext(i+1);
            }
          })
          .catch(function(){ tryNext(i+1); });
      })(0);
    });
  }

  withFallback('topbar-profile-link');
  withFallback('topbar-password-link');
})();

<?php $this->load->view('partials/ai_fab'); ?>
</script>

