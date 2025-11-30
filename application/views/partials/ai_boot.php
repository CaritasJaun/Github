<?php if (defined('AI_BOOT_INCLUDED')) return; define('AI_BOOT_INCLUDED', true); ?>
<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<!-- AI boot meta (used by ea_ai_fab.js) -->
<meta name="csrf-name" content="<?= $this->security->get_csrf_token_name(); ?>">
<meta name="csrf-hash" content="<?= $this->security->get_csrf_hash(); ?>">
<meta name="ea-context" content="<?= strtolower(isset($ea_context) ? $ea_context : ($this->router->class . '/' . $this->router->method)); ?>">

<!-- Expose to JS (safe globals) -->
<script>
  window.base_url  = "<?= base_url(); ?>";
  window.EA_BOOT   = {
    csrfName : "<?= $this->security->get_csrf_token_name(); ?>",
    csrfHash : "<?= $this->security->get_csrf_hash(); ?>",
    context  : "<?= strtolower(isset($ea_context) ? $ea_context : ($this->router->class . '/' . $this->router->method)); ?>"
  };
</script>

<!-- (optional) CSS if you have one -->
<?php if (file_exists(FCPATH . 'assets/css/ea_ai_fab.css')): ?>
<link rel="stylesheet" href="<?= base_url('assets/css/ea_ai_fab.css?v=3'); ?>">
<?php endif; ?>

<!-- Widget script (deferred) -->
<script src="<?= base_url('assets/js/ea_ai_fab.js?v=3'); ?>" defer></script>

<!-- Safe auto-init (only if the script didn’t auto-init itself) -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.EA_AIFab && !window.EA_AIFab.initialized && typeof window.EA_AIFab.init === 'function') {
      window.EA_AIFab.init();
    }
  });
</script>
