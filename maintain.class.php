<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

if (!defined('OPP_OWNER_PROFILE_TABLE')) {
  global $prefixeTable;
  define('OPP_OWNER_PROFILE_TABLE', $prefixeTable . 'owner_profile');
}

class owner_profile_maintain extends \PluginMaintain
{
  private $default_conf = array(
    'migration_completed' => false,
  );


  public function install($plugin_version, &$errors = array())
  {
    global $conf;

    if (empty($conf['owner_profile'])) {
      conf_update_param('owner_profile', serialize($this->default_conf), true);
      $conf['owner_profile'] = $this->default_conf;
    } else {
      $old_conf = safe_unserialize($conf['owner_profile']);
      $new_conf = array_merge($this->default_conf, is_array($old_conf) ? $old_conf : array());
      conf_update_param('owner_profile', serialize($new_conf), true);
      $conf['owner_profile'] = $new_conf;
    }

    $result = pwg_query($this->get_owner_profile_table_schema_sql());
    if (!$result) {
      $errors[] = 'Failed to create Owner Profile table.';
      return;
    }

    $this->migrate_legacy_owner_profile_rows();
  }

  public function activate($plugin_version, &$errors = array())
  {
    $this->install($plugin_version, $errors);
  }

  public function update($old_version, $new_version, &$errors = array())
  {
    $this->install($new_version, $errors);
  }

  public function uninstall()
  {
    conf_delete_param('owner_profile');
    pwg_query('DROP TABLE IF EXISTS ' . OPP_OWNER_PROFILE_TABLE . ';');
  }

  private function get_owner_profile_table_schema_sql()
  {
    return 'CREATE TABLE IF NOT EXISTS ' . OPP_OWNER_PROFILE_TABLE . " (
  id int(11) NOT NULL AUTO_INCREMENT,
  root_album_id int(11) NOT NULL,
  owner_user_id int(11) NOT NULL,
  field_key varchar(64) NOT NULL,
  value_text text DEFAULT NULL,
  tag_id int(11) DEFAULT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY root_field (root_album_id, field_key),
  KEY owner_user_id (owner_user_id),
  KEY tag_id (tag_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;";
  }

  private function migrate_legacy_owner_profile_rows()
  {
    global $conf, $prefixeTable;

    $legacy_table = defined('CPT_OWNER_PROFILE_TABLE') ? CPT_OWNER_PROFILE_TABLE : $prefixeTable . 'cpt_owner_profile';
    $result = pwg_query("SHOW TABLES LIKE '" . pwg_db_real_escape_string($legacy_table) . "'");
    if (!$result || !function_exists('pwg_db_fetch_row') || !pwg_db_fetch_row($result)) {
      return;
    }

    pwg_query(
      'INSERT IGNORE INTO ' . OPP_OWNER_PROFILE_TABLE . ' (root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at) '
      . 'SELECT root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at FROM ' . $legacy_table
    );

    $current_conf = isset($conf['owner_profile']) ? safe_unserialize($conf['owner_profile']) : array();
    if (!is_array($current_conf)) {
      $current_conf = array();
    }
    $current_conf['migration_completed'] = true;
    conf_update_param('owner_profile', serialize($current_conf), true);
    $conf['owner_profile'] = $current_conf;
  }
}