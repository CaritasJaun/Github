<head>
	<meta charset="UTF-8">
	<meta name="keywords" content="<?php echo isset($global_config['institute_name']) ? $global_config['institute_name'] : ''; ?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="description" content="<?php echo isset($global_config['institute_name']) ? $global_config['institute_name'] : ''; ?>">
	<meta name="author" content="<?php echo isset($global_config['institute_name']) ? $global_config['institute_name'] : ''; ?>">
	<title><?php echo isset($title) ? $title : 'Untitled'; ?></title>
    <link rel="shortcut icon" href="<?php echo base_url('assets/images/favicon.png'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

	<!-- include stylesheet -->
	<?php include 'stylesheet.php';?>

	<?php
	if (isset($headerelements)) {
		foreach ($headerelements as $type => $element) {
			if ($type === 'css' && is_array($element)) {
				foreach ($element as $css) {
					echo '<link rel="stylesheet" href="' . base_url('assets/' . $css) . '">' . "\n";
				}
			} elseif ($type === 'js' && is_array($element)) {
				foreach ($element as $js) {
					echo '<script type="text/javascript" src="' . base_url('assets/' . $js) . '"></script>' . "\n";
				}
			}
		}
	}
	?>

	<!-- ramom css -->
	<link rel="stylesheet" href="<?php echo base_url('assets/css/ramom.css?v=' . version_combine()); ?>">
	<?php if (isset($theme_config["border_mode"]) && $theme_config["border_mode"] === 'false'): ?>
		<link rel="stylesheet" href="<?php echo base_url('assets/css/skins/square-borders.css?v=' . version_combine()); ?>">
	<?php endif; ?>

	<script type="text/javascript">
		var base_url = '<?php echo base_url(); ?>';
		var isRTLenabled = '<?php echo $this->app_lib->isRTLenabled(); ?>';
		var csrfData = <?php echo json_encode(csrf_jquery_token()); ?>;
		$(function($) {
			$.ajaxSetup({
				cache: false,
				data: csrfData
			});
		});
	</script>
</head>
