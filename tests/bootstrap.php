<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!defined('PHPWG_ROOT_PATH')) {
    define('PHPWG_ROOT_PATH', realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR);
}

if (!defined('PWG_LOCAL_DIR')) { define('PWG_LOCAL_DIR', 'local/'); }
if (!defined('PHPWG_PLUGINS_PATH')) { define('PHPWG_PLUGINS_PATH', PHPWG_ROOT_PATH . 'plugins/'); }

$prefixeTable = 'piwigo_';

if (!defined('CATEGORIES_TABLE')) { define('CATEGORIES_TABLE', $prefixeTable . 'categories'); }
if (!defined('OPP_OWNER_PROFILE_TABLE')) { define('OPP_OWNER_PROFILE_TABLE', $prefixeTable . 'owner_profile'); }
if (!defined('CPT_OWNER_PROFILE_TABLE')) { define('CPT_OWNER_PROFILE_TABLE', $prefixeTable . 'cpt_owner_profile'); }
if (!defined('OWNER_PROFILE_ID')) { define('OWNER_PROFILE_ID', 'owner_profile'); }
if (!defined('OWNER_PROFILE_PATH')) { define('OWNER_PROFILE_PATH', PHPWG_PLUGINS_PATH . OWNER_PROFILE_ID . '/'); }
if (!defined('EVENT_HANDLER_PRIORITY_NEUTRAL')) { define('EVENT_HANDLER_PRIORITY_NEUTRAL', 0); }

global $conf, $user, $page, $template;

$conf = $conf ?? [];
$conf['active_plugins'] = $conf['active_plugins'] ?? ['core_privacy_toggle'];
$conf['guest_id'] = $conf['guest_id'] ?? 2;
$conf['owner_profile'] = $conf['owner_profile'] ?? [];
$user = $user ?? [];
$page = $page ?? ['infos' => [], 'errors' => []];

if (!function_exists('l10n')) { function l10n($key) { return $key; } }
if (!function_exists('safe_unserialize')) {
    function safe_unserialize($value) {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $result = @unserialize($value);
            return is_array($result) ? $result : [];
        }

        return [];
    }
}
if (!function_exists('conf_update_param')) {
    function conf_update_param($key, $value, $update_global = true) {
        global $conf;
        if ($update_global) {
            $conf[$key] = $value;
        }
        return true;
    }
}
if (!function_exists('conf_delete_param')) {
    function conf_delete_param($key) {
        global $conf;
        unset($conf[$key]);
    }
}
if (!function_exists('get_pwg_token')) { function get_pwg_token() { return $GLOBALS['__opp_test_pwg_token'] ?? 'test-token'; } }
if (!function_exists('is_a_guest')) { function is_a_guest() { global $user; return !empty($user['is_guest']); } }
if (!function_exists('add_event_handler')) { function add_event_handler($event, $callback, $priority = 0, $file = null) {} }
if (!function_exists('load_language')) { function load_language($file, $path = '', $options = []) { return true; } }
if (!function_exists('get_root_url')) { function get_root_url() { return '/'; } }
if (!function_exists('get_absolute_root_url')) { function get_absolute_root_url() { return '/'; } }
if (!function_exists('make_index_url')) { function make_index_url($params) { return 'index.php'; } }
if (!function_exists('get_themeconf')) { function get_themeconf($key) { return $key === 'id' ? 'default' : null; } }
if (!class_exists('PwgError')) {
    class PwgError {
        public int $code;
        public string $message;
        public function __construct(int $code, string $message) {
            $this->code = $code;
            $this->message = $message;
        }
    }
}

if (!class_exists('OppTestTemplate')) {
    class OppTestTemplate {
        private array $vars = [];
        public array $footer_msgs = [];
        public array $head_elements = [];
        public array $combined_css = [];
        public array $combined_script = [];

        public function assign($key, $value) {
            $this->vars[$key] = $value;
        }

        public function get_template_vars($key) {
            return $this->vars[$key] ?? null;
        }

        public function set_filename($handle, $path) {
            $this->vars['__tpl_path__'] = $path;
        }

        public function parse($handle, $return) {
            $templatePath = basename((string) ($this->vars['__tpl_path__'] ?? ''));
            if ($templatePath === 'owner_profile_table.tpl') {
                $rows = $this->vars['OPP_OWNER_PROFILE_ROWS'] ?? [];
                $contacts = $this->vars['OPP_OWNER_PROFILE_CONTACTS'] ?? [];
                $availability = $this->vars['OPP_OWNER_PROFILE_AVAILABILITY'] ?? [];
                $html = '<div class="opp-owner-profile-public">';
                if (!empty($rows)) {
                    $html .= '<table class="opp-owner-profile-table"><tbody>';
                    foreach ($rows as $row) {
                        $html .= '<tr><th scope="row">' . htmlspecialchars((string) ($row['label'] ?? '')) . '</th><td>' . htmlspecialchars((string) ($row['value_text'] ?? '')) . '</td></tr>';
                    }
                    $html .= '</tbody></table>';
                }
                if (!empty($contacts)) {
                    $html .= '<div class="opp-owner-profile-contacts">';
                    foreach ($contacts as $contact) {
                        $html .= '<a class="opp-owner-profile-contact-link" href="' . htmlspecialchars((string) ($contact['href'] ?? '')) . '">' . htmlspecialchars((string) ($contact['label'] ?? '')) . '</a>';
                    }
                    $html .= '<div class="opp-owner-profile-contact-number">' . htmlspecialchars((string) ($contacts[0]['display_value'] ?? '')) . '</div></div>';
                }
                if (!empty($availability)) {
                    $html .= '<div class="opp-owner-profile-availability">';
                    foreach ($availability as $row) {
                        $html .= '<div class="opp-owner-profile-availability-row"><span>' . htmlspecialchars((string) ($row['label'] ?? '')) . '</span><span>' . htmlspecialchars((string) ($row['value_text'] ?? '')) . '</span></div>';
                    }
                    $html .= '</div>';
                }
                return $html . '</div>';
            }

            return '';
        }

        public function append($slot, $value) {
            if (!isset($this->vars[$slot])) {
                $this->vars[$slot] = [];
            }

            if (is_array($this->vars[$slot])) {
                $this->vars[$slot][] = $value;
                return;
            }

            $this->vars[$slot] .= $value;
        }

        public function func_combine_css($config) {
            $this->combined_css[] = $config;
        }

        public function func_combine_script($config) {
            $this->combined_script[] = $config;
        }
    }
}

if (!isset($template)) {
    $template = new OppTestTemplate();
}

$GLOBALS['__opp_db'] = [
    'categories' => [],
    'owner_profile' => [],
    'legacy_owner_profile' => [],
];

function opp_test_reset_db(): void {
    $GLOBALS['__opp_db'] = [
        'categories' => [],
        'owner_profile' => [],
        'legacy_owner_profile' => [],
    ];
}

function opp_test_next_id(string $table): int {
    static $counters = [];
    if (!isset($counters[$table])) {
        $counters[$table] = 1;
    }
    return $counters[$table]++;
}

function pwg_query($sql) {
    $sqlTrim = trim($sql);
    $GLOBALS['__opp_last_query'] = $sqlTrim;

    if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $sqlTrim, $matches)) {
        $table = stripslashes($matches[1]);
        if ($table === OPP_OWNER_PROFILE_TABLE) {
            return new ArrayIterator(!empty($GLOBALS['__opp_owner_profile_table_exists']) ? [[OPP_OWNER_PROFILE_TABLE]] : []);
        }
        if ($table === CPT_OWNER_PROFILE_TABLE) {
            return new ArrayIterator(!empty($GLOBALS['__opp_legacy_owner_profile_table_exists']) ? [[CPT_OWNER_PROFILE_TABLE]] : []);
        }
        return new ArrayIterator([]);
    }

    if (str_starts_with($sqlTrim, 'CREATE TABLE IF NOT EXISTS ' . OPP_OWNER_PROFILE_TABLE . ' ')) {
        $GLOBALS['__opp_owner_profile_table_exists'] = true;
        return true;
    }

    if (preg_match('/SELECT field_key, value_text, tag_id FROM ' . preg_quote(OPP_OWNER_PROFILE_TABLE, '/') . ' WHERE root_album_id=(\d+) AND owner_user_id=(\d+) ORDER BY field_key ASC/', $sqlTrim, $matches)) {
        $rootAlbumId = (int) $matches[1];
        $ownerUserId = (int) $matches[2];
        $rows = [];
        foreach ($GLOBALS['__opp_db']['owner_profile'] as $row) {
            if ($row['root_album_id'] === $rootAlbumId && $row['owner_user_id'] === $ownerUserId) {
                $rows[] = [
                    'field_key' => $row['field_key'],
                    'value_text' => $row['value_text'],
                    'tag_id' => $row['tag_id'],
                ];
            }
        }
        usort($rows, static fn($left, $right) => strcmp($left['field_key'], $right['field_key']));
        return new ArrayIterator($rows);
    }

    if (preg_match('/DELETE FROM ' . preg_quote(OPP_OWNER_PROFILE_TABLE, '/') . " WHERE root_album_id=(\d+) AND field_key='([^']+)'/", $sqlTrim, $matches)) {
        $rootAlbumId = (int) $matches[1];
        $fieldKey = stripslashes($matches[2]);
        $GLOBALS['__opp_db']['owner_profile'] = array_values(array_filter(
            $GLOBALS['__opp_db']['owner_profile'],
            static fn($row) => !($row['root_album_id'] === $rootAlbumId && $row['field_key'] === $fieldKey)
        ));
        return true;
    }

    if (str_starts_with($sqlTrim, 'INSERT INTO ' . OPP_OWNER_PROFILE_TABLE . ' ')) {
        $prefix = 'INSERT INTO ' . OPP_OWNER_PROFILE_TABLE . ' (root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at) VALUES (';
        $suffix = ', NOW())';
        if (str_starts_with($sqlTrim, $prefix) && str_ends_with($sqlTrim, $suffix)) {
            $inner = substr($sqlTrim, strlen($prefix), -strlen($suffix));
            $parts = str_getcsv($inner, ',', "'", '\\');
            $parts = array_map('trim', $parts);
            if (count($parts) === 5) {
                $GLOBALS['__opp_db']['owner_profile'][] = [
                    'id' => opp_test_next_id('owner_profile'),
                    'root_album_id' => (int) $parts[0],
                    'owner_user_id' => (int) $parts[1],
                    'field_key' => stripslashes($parts[2]),
                    'value_text' => strtoupper($parts[3]) === 'NULL' ? null : stripslashes($parts[3]),
                    'tag_id' => strtoupper($parts[4]) === 'NULL' ? null : (int) $parts[4],
                ];
                return true;
            }
        }
    }

    if (str_starts_with($sqlTrim, 'INSERT IGNORE INTO ' . OPP_OWNER_PROFILE_TABLE . ' ') && str_contains($sqlTrim, 'SELECT root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at FROM ' . CPT_OWNER_PROFILE_TABLE)) {
        foreach ($GLOBALS['__opp_db']['legacy_owner_profile'] as $legacyRow) {
            $duplicate = false;
            foreach ($GLOBALS['__opp_db']['owner_profile'] as $row) {
                if ($row['root_album_id'] === $legacyRow['root_album_id'] && $row['field_key'] === $legacyRow['field_key']) {
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                continue;
            }
            $GLOBALS['__opp_db']['owner_profile'][] = [
                'id' => opp_test_next_id('owner_profile'),
                'root_album_id' => (int) $legacyRow['root_album_id'],
                'owner_user_id' => (int) $legacyRow['owner_user_id'],
                'field_key' => (string) $legacyRow['field_key'],
                'value_text' => $legacyRow['value_text'],
                'tag_id' => $legacyRow['tag_id'],
            ];
        }
        return true;
    }

    return new ArrayIterator([]);
}

function pwg_db_fetch_assoc($iterator) {
    if ($iterator instanceof ArrayIterator) {
        if ($iterator->valid()) {
            $current = $iterator->current();
            $iterator->next();
            return $current;
        }
        return null;
    }

    return null;
}

function pwg_db_fetch_row($iterator) {
    return pwg_db_fetch_assoc($iterator);
}

function pwg_db_real_escape_string($value) {
    return addslashes($value);
}

function opp_test_get_category(int $albumId): ?array {
    foreach ($GLOBALS['__opp_db']['categories'] as $category) {
        if ($category['id'] === $albumId) {
            return $category;
        }
    }
    return null;
}

function opp_test_get_explicit_owner_id(int $albumId): ?int {
    $category = opp_test_get_category($albumId);
    if (!$category) {
        return null;
    }
    return isset($category['community_user']) ? (int) $category['community_user'] : null;
}

function cpt_get_album_effective_owner_id($albumId) {
    $category = opp_test_get_category((int) $albumId);
    if (!$category) {
        return null;
    }

    $explicitOwnerId = opp_test_get_explicit_owner_id((int) $albumId);
    if ($explicitOwnerId !== null) {
        return $explicitOwnerId;
    }

    if (!empty($category['id_uppercat'])) {
        return cpt_get_album_effective_owner_id((int) $category['id_uppercat']);
    }

    return null;
}

function cpt_get_effective_owner_root_album_id_for_album($albumId) {
    $category = opp_test_get_category((int) $albumId);
    if (!$category) {
        return null;
    }

    $effectiveOwnerId = cpt_get_album_effective_owner_id((int) $albumId);
    if ($effectiveOwnerId === null) {
        return null;
    }

    $current = $category;
    while (!empty($current['id_uppercat'])) {
        $parent = opp_test_get_category((int) $current['id_uppercat']);
        if (!$parent) {
            break;
        }

        $parentOwnerId = cpt_get_album_effective_owner_id((int) $parent['id']);
        if ($parentOwnerId !== $effectiveOwnerId) {
            break;
        }

        $current = $parent;
    }

    return (int) $current['id'];
}

function cpt_get_effective_owner_root_album_id_for_user($userId) {
    $roots = [];
    foreach ($GLOBALS['__opp_db']['categories'] as $category) {
        $albumId = (int) $category['id'];
        if (cpt_get_album_effective_owner_id($albumId) !== (int) $userId) {
            continue;
        }

        $rootAlbumId = cpt_get_effective_owner_root_album_id_for_album($albumId);
        if ($rootAlbumId !== null) {
            $roots[$rootAlbumId] = $rootAlbumId;
        }
    }

    if (empty($roots)) {
        return null;
    }

    sort($roots);
    return $roots[0];
}

function cpt_get_owner_profile_city_options(): array {
    return [1 => 'Bratislava', 2 => 'Kosice'];
}

require_once dirname(__DIR__) . '/include/functions.inc.php';

function opp_test_create_root_album(int $ownerUserId, string $name = 'Album', string $status = 'public'): int {
    $id = opp_test_next_id('categories');
    $GLOBALS['__opp_db']['categories'][] = [
        'id' => $id,
        'name' => $name,
        'status' => $status,
        'community_user' => $ownerUserId,
        'id_uppercat' => null,
        'uppercats' => (string) $id,
    ];
    return $id;
}

function opp_test_create_child_album(int $parentId, string $name = 'Album', string $status = 'public', array $extra = []): int {
    $id = opp_test_next_id('categories');
    $parent = opp_test_get_category($parentId);
    $uppercats = isset($parent['uppercats']) && $parent['uppercats'] !== ''
        ? $parent['uppercats'] . ',' . $id
        : $parentId . ',' . $id;

    $GLOBALS['__opp_db']['categories'][] = array_merge([
        'id' => $id,
        'name' => $name,
        'status' => $status,
        'id_uppercat' => $parentId,
        'uppercats' => $uppercats,
    ], $extra);

    return $id;
}

function opp_test_reset_env(): void {
    global $conf, $page, $template, $user;

    opp_test_reset_db();
    $conf = [
        'active_plugins' => ['core_privacy_toggle'],
        'guest_id' => 2,
        'owner_profile' => [],
    ];
    $page = ['infos' => [], 'errors' => []];
    $template = new OppTestTemplate();
    $user = ['id' => 0, 'is_guest' => true, 'status' => 'guest'];
    $GLOBALS['__opp_test_pwg_token'] = 'test-token';
    $GLOBALS['__opp_owner_profile_table_exists'] = true;
    $GLOBALS['__opp_legacy_owner_profile_table_exists'] = false;
    $GLOBALS['__opp_last_error'] = null;
  }