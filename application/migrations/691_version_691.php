<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_691 extends CI_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
		if (!$this->db->field_exists('cache_store', 'global_settings'))
			$this->db->query("ALTER TABLE `global_settings` ADD `cache_store` TINYINT(1) NOT NULL DEFAULT '0' AFTER `cms_default_branch`;");
		if (!$this->db->field_exists('bkash_app_key', 'payment_config'))
			$this->db->query("ALTER TABLE `payment_config` ADD `bkash_app_key` VARCHAR(255) NULL DEFAULT NULL AFTER `nepalste_status`, ADD `bkash_app_secret` VARCHAR(255) NULL DEFAULT NULL AFTER `bkash_app_key`, ADD `bkash_username` VARCHAR(255) NULL DEFAULT NULL AFTER `bkash_app_secret`, ADD `bkash_password` VARCHAR(255) NULL DEFAULT NULL AFTER `bkash_username`, ADD `bkash_sandbox` TINYINT(1) NOT NULL DEFAULT '0' AFTER `bkash_password`, ADD `bkash_status` TINYINT(1) NOT NULL DEFAULT '0' AFTER `bkash_sandbox`;");
		$row = $this->db->where('name', 'bKash')->get('payment_types')->row();
		if (empty($row))
			$this->db->query("INSERT INTO `payment_types` (`id`, `name`, `branch_id`, `timestamp`) VALUES (NULL, 'bKash', '0', '2025-02-27 18:19:08');");
		$this->db->query("UPDATE `permission` SET `show_add` = '1' WHERE `permission`.`prefix` = 'purchase_payment';");
		if (!$this->db->field_exists('active', 'student'))
			$this->db->query("ALTER TABLE `student` ADD `active` TINYINT(1) NOT NULL DEFAULT '1' AFTER `photo`;");
    }
}
