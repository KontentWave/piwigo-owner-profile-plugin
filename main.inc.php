<?php
/*
Plugin Name: Owner Profile
Version: 1.0.0
Description: Owns public owner profile data, rendering, and 2FA phone candidate helpers.
Author: Marcel Slapak
Author URI: https://cores.sk
Has Settings: false
*/

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

if (basename(dirname(__FILE__)) !== 'owner_profile') {
  add_event_handler('init', 'owner_profile_error');
  function owner_profile_error()
  {
    global $page;
    $page['errors'][] = 'Owner Profile folder name is incorrect, uninstall the plugin and rename it to "owner_profile"';
  }
  return;
}

global $prefixeTable;

define('OWNER_PROFILE_ID', basename(dirname(__FILE__)));
define('OWNER_PROFILE_PATH', PHPWG_PLUGINS_PATH . OWNER_PROFILE_ID . '/');
define('OWNER_PROFILE_PUBLIC', get_root_url() . 'plugins/' . OWNER_PROFILE_ID . '/');
define('OWNER_PROFILE_ADMIN', get_root_url() . 'admin.php?page=plugin-' . OWNER_PROFILE_ID);
if (!defined('OPP_OWNER_PROFILE_TABLE')) {
  define('OPP_OWNER_PROFILE_TABLE', $prefixeTable . 'owner_profile');
}

require_once OWNER_PROFILE_PATH . 'include/functions.inc.php';

add_event_handler('init', 'owner_profile_init');
add_event_handler('loc_begin_profile', 'opp_setup_profile_page');
add_event_handler('loc_begin_index', 'opp_prepare_album_page_assets');
add_event_handler('loc_end_index', 'opp_attach_owner_profile_to_album_page');
add_event_handler('ws_add_methods', 'opp_add_ws_methods');

function owner_profile_init()
{
  global $conf, $page;

  load_language('plugin.lang', OWNER_PROFILE_PATH);
  $conf['owner_profile'] = isset($conf['owner_profile'])
    ? safe_unserialize($conf['owner_profile'])
    : array();

  if (!is_array($conf['owner_profile'])) {
    $conf['owner_profile'] = array();
  }

  if (function_exists('opp_maybe_migrate_legacy_owner_profile_rows')) {
    opp_maybe_migrate_legacy_owner_profile_rows();
  }

  $warning = function_exists('opp_get_admin_warning') ? opp_get_admin_warning() : null;
  if ($warning !== null && !empty($page) && !empty($GLOBALS['user']['is_admin'])) {
    $page['warnings'][] = $warning;
  }
}
