<?php
defined('OWNER_PROFILE_PATH') or die('Hacking attempt!');

function opp_plugin_id(): string
{
  return defined('OWNER_PROFILE_ID') ? OWNER_PROFILE_ID : basename(dirname(__DIR__));
}

function opp_plugin_path(): string
{
  return defined('OWNER_PROFILE_PATH') ? OWNER_PROFILE_PATH : dirname(__DIR__) . '/';
}

function opp_owner_profile_table_name(): string
{
  global $prefixeTable;

  return defined('OPP_OWNER_PROFILE_TABLE') ? OPP_OWNER_PROFILE_TABLE : $prefixeTable . 'owner_profile';
}

function opp_clear_last_error(): void
{
  $GLOBALS['__opp_last_error'] = null;
}

function opp_set_last_error(string $message): void
{
  $GLOBALS['__opp_last_error'] = $message;
}

function opp_get_last_error(): ?string
{
  $error = $GLOBALS['__opp_last_error'] ?? null;
  return is_string($error) && $error !== '' ? $error : null;
}

function opp_get_plugin_config(): array
{
  global $conf;

  $raw_config = $conf['owner_profile'] ?? array();
  if (is_array($raw_config)) {
    return $raw_config;
  }

  $config = safe_unserialize($raw_config);
  return is_array($config) ? $config : array();
}

function opp_update_plugin_config(array $config): void
{
  global $conf;

  conf_update_param('owner_profile', serialize($config), true);
  $conf['owner_profile'] = $config;
}

function opp_legacy_owner_profile_table_name(): string
{
  global $prefixeTable;

  return defined('CPT_OWNER_PROFILE_TABLE') ? CPT_OWNER_PROFILE_TABLE : $prefixeTable . 'cpt_owner_profile';
}

function opp_legacy_owner_profile_table_exists(): bool
{
  if (array_key_exists('__opp_legacy_owner_profile_table_exists', $GLOBALS)) {
    return (bool) $GLOBALS['__opp_legacy_owner_profile_table_exists'];
  }

  $result = pwg_query("SHOW TABLES LIKE '" . pwg_db_real_escape_string(opp_legacy_owner_profile_table_name()) . "'");
  $exists = (bool) ($result && function_exists('pwg_db_fetch_row') && pwg_db_fetch_row($result));
  $GLOBALS['__opp_legacy_owner_profile_table_exists'] = $exists;

  return $exists;
}

function opp_maybe_migrate_legacy_owner_profile_rows(): void
{
  $config = opp_get_plugin_config();
  if (!empty($config['migration_completed'])) {
    return;
  }

  if (!opp_ensure_owner_profile_table() || !opp_legacy_owner_profile_table_exists()) {
    return;
  }

  $result = pwg_query(
    'INSERT IGNORE INTO ' . opp_owner_profile_table_name() . ' (root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at) '
    . 'SELECT root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at FROM ' . opp_legacy_owner_profile_table_name()
  );
  if (!$result) {
    return;
  }

  $config['migration_completed'] = true;
  opp_update_plugin_config($config);
}

function opp_setup_profile_page(): void
{
  global $user, $template;

  if (empty($user['id'])) {
    return;
  }

  $editor_data = opp_get_owner_profile_editor_data((int) $user['id']);
  if (empty($editor_data['fields'])) {
    return;
  }

  $template->assign('OPP_UCP_OWNER_PROFILE', $editor_data);
  opp_attach_profile_block('template/ucp_owner_profile.tpl', l10n('My Profile'));
  opp_register_profile_assets();
}

function opp_prepare_album_page_assets(): void
{
  global $template;

  $html = opp_get_rendered_owner_profile_table_for_current_album();
  if ($html === null) {
    return;
  }

  $css_path = opp_plugin_path() . 'template/style.css';
  if (file_exists($css_path) && method_exists($template, 'func_combine_css')) {
    $template->func_combine_css(array(
      'id' => 'owner-profile-public',
      'path' => 'plugins/' . opp_plugin_id() . '/template/style.css',
      'version' => filemtime($css_path),
      'order' => 20,
    ));
  }

  if (opp_theme_uses_album_page_js_profile_placement()) {
    return;
  }

  $script_path = opp_plugin_path() . 'js/owner_profile.js';
  if (file_exists($script_path) && method_exists($template, 'func_combine_script')) {
    $template->func_combine_script(array(
      'id' => 'owner-profile-public',
      'path' => 'plugins/' . opp_plugin_id() . '/js/owner_profile.js',
      'load' => 'footer',
      'version' => filemtime($script_path),
    ));
  }
}

function opp_get_rendered_owner_profile_table_for_current_album(): ?string
{
  global $template;

  $existing = method_exists($template, 'get_template_vars')
    ? $template->get_template_vars('OPP_OWNER_PROFILE_TABLE')
    : null;
  if (is_string($existing) && $existing !== '') {
    return $existing;
  }

  $category = opp_get_current_album_page_category();
  if ($category === null) {
    return null;
  }

  $profile = opp_get_owner_profile_public_data_for_album((int) $category['id']);
  if ($profile === null) {
    return null;
  }

  $template->assign('OPP_OWNER_PROFILE_ROWS', $profile['rows']);
  $template->assign('OPP_OWNER_PROFILE_CONTACTS', $profile['contacts'] ?? array());
  $template->assign('OPP_OWNER_PROFILE_AVAILABILITY', $profile['availability'] ?? array());
  $template->set_filename('opp_owner_profile_table', realpath(opp_plugin_path() . 'template/owner_profile_table.tpl'));
  $html = $template->parse('opp_owner_profile_table', true);
  $template->assign('OPP_OWNER_PROFILE_TABLE', $html);

  return $html;
}

function opp_attach_owner_profile_to_album_page(): void
{
  $html = opp_get_rendered_owner_profile_table_for_current_album();
  if ($html === null) {
    return;
  }

  if (!opp_theme_uses_album_page_js_profile_placement()) {
    opp_append_index_content_begin($html);
    opp_inject_album_page_assets($html);
  }
}

function opp_add_ws_methods($arr): void
{
  $service = &$arr[0];
  $service->addMethod(
    'owner_profile.update',
    'opp_ws_update_owner_profile',
    array(
      'payload' => array(),
      'pwg_token' => array(),
    ),
    'Update the owner public profile from AJAX-driven profile pages.'
  );
}

function opp_ws_update_owner_profile($params, &$service)
{
  global $user;

  opp_clear_last_error();

  if (get_pwg_token() !== ($params['pwg_token'] ?? null)) {
    return new \PwgError(403, 'Invalid security token');
  }

  if (empty($user['id']) || is_a_guest()) {
    return new \PwgError(401, 'Access denied');
  }

  $payload = json_decode(stripslashes((string) ($params['payload'] ?? '')), true);
  if (!is_array($payload)) {
    return new \PwgError(400, 'Invalid owner profile payload');
  }

  if (!opp_update_owner_profile($payload, (int) $user['id'])) {
    return new \PwgError(400, opp_get_last_error() ?? 'No owner profile changes were applied');
  }

  return l10n('Your public profile has been saved.');
}

function opp_get_owner_profile_field_schema(): array
{
  return array(
    'nationality' => array('label' => l10n('Nationality'), 'type' => 'controlled'),
    'city' => array('label' => l10n('City'), 'type' => 'controlled'),
    'age' => array('label' => l10n('Age'), 'type' => 'text', 'max_length' => 120),
    'measurements' => array('label' => l10n('Measures'), 'type' => 'text', 'max_length' => 255),
    'breasts' => array('label' => l10n('Breasts'), 'type' => 'controlled'),
    'eyes' => array('label' => l10n('Eyes'), 'type' => 'text', 'max_length' => 120),
    'hair' => array('label' => l10n('Hair'), 'type' => 'text', 'max_length' => 120),
    'private_parts' => array('label' => l10n('Private parts'), 'type' => 'controlled'),
    'tattoo' => array('label' => l10n('Tattoo'), 'type' => 'controlled'),
    'piercing' => array('label' => l10n('Piercing'), 'type' => 'controlled'),
    'experience' => array('label' => l10n('Experience'), 'type' => 'controlled'),
    'i_offer' => array('label' => l10n('I offer'), 'type' => 'controlled_multi'),
    'other_girls' => array('label' => l10n('Other girls'), 'type' => 'controlled'),
    'services_for' => array('label' => l10n('Services for'), 'type' => 'controlled_multi'),
    'i_speak' => array('label' => l10n('I speak'), 'type' => 'controlled_multi'),
    'contact_number' => array('label' => l10n('Contact number'), 'type' => 'text', 'max_length' => 64),
    'contact_phone' => array('label' => l10n('Phone calls'), 'type' => 'controlled'),
    'contact_sms' => array('label' => l10n('SMS'), 'type' => 'controlled'),
    'contact_whatsapp' => array('label' => l10n('WhatsApp'), 'type' => 'controlled'),
    'availability_monday' => array('label' => l10n('Monday'), 'type' => 'availability_range'),
    'availability_tuesday' => array('label' => l10n('Tuesday'), 'type' => 'availability_range'),
    'availability_wednesday' => array('label' => l10n('Wednesday'), 'type' => 'availability_range'),
    'availability_thursday' => array('label' => l10n('Thursday'), 'type' => 'availability_range'),
    'availability_friday' => array('label' => l10n('Friday'), 'type' => 'availability_range'),
    'availability_saturday' => array('label' => l10n('Saturday'), 'type' => 'availability_range'),
    'availability_sunday' => array('label' => l10n('Sunday'), 'type' => 'availability_range'),
  );
}

function opp_get_owner_profile_field_definition(string $field_key): ?array
{
  $schema = opp_get_owner_profile_field_schema();
  return $schema[$field_key] ?? null;
}

function opp_get_owner_profile_controlled_options(string $field_key): array
{
  switch ($field_key) {
    case 'nationality':
      return array(1 => l10n('Slovak'), 2 => l10n('Czech'), 3 => l10n('Ukrainian'), 4 => l10n('Hungarian'), 5 => l10n('Polish'), 6 => l10n('German'), 7 => l10n('Austrian'), 8 => l10n('Romanian'));
    case 'city':
      if (function_exists('cpt_get_owner_profile_city_options')) {
        return cpt_get_owner_profile_city_options();
      }
      return array();
    case 'breasts':
      return array(1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7');
    case 'private_parts':
      return array(1 => l10n('Shaved'), 2 => l10n('Partially shaved'), 3 => l10n('Not shaved'));
    case 'tattoo':
    case 'piercing':
    case 'contact_phone':
    case 'contact_sms':
    case 'contact_whatsapp':
      return array(1 => l10n('Yes'), 2 => l10n('No'));
    case 'experience':
      return array(1 => l10n('Experienced'), 2 => l10n('Not experienced'));
    case 'i_offer':
      return array(1 => l10n('Private flat'), 2 => l10n('Escort'));
    case 'other_girls':
      return array(1 => l10n('Alone'), 2 => l10n('Not alone'));
    case 'services_for':
      return array(1 => l10n('Men'), 2 => l10n('Women'), 3 => l10n('Couples'));
    case 'i_speak':
      return array(1 => l10n('Slovak'), 2 => l10n('Czech'), 3 => l10n('English'), 4 => l10n('German'), 5 => l10n('Hungarian'), 6 => l10n('Polish'), 7 => l10n('Ukrainian'), 8 => l10n('Russian'));
  }

  return array();
}

function opp_get_owner_profile_availability_time_options(): array
{
  static $options = null;
  if ($options !== null) {
    return $options;
  }

  $options = array('unavailable' => l10n('Unavailable'));
  for ($hour = 0; $hour < 24; $hour++) {
    $label = sprintf('%02d:00', $hour);
    $options[$label] = $label;
  }

  return $options;
}

function opp_is_owner_profile_availability_field(string $field_key): bool
{
  return str_starts_with($field_key, 'availability_');
}

function opp_owner_profile_table_exists(): bool
{
  if (array_key_exists('__opp_owner_profile_table_exists', $GLOBALS)) {
    return (bool) $GLOBALS['__opp_owner_profile_table_exists'];
  }

  $result = pwg_query("SHOW TABLES LIKE '" . pwg_db_real_escape_string(opp_owner_profile_table_name()) . "'");
  $exists = (bool) ($result && function_exists('pwg_db_fetch_row') && pwg_db_fetch_row($result));
  $GLOBALS['__opp_owner_profile_table_exists'] = $exists;

  return $exists;
}

function opp_ensure_owner_profile_table(): bool
{
  if (opp_owner_profile_table_exists()) {
    return true;
  }

  $schema_sql = 'CREATE TABLE IF NOT EXISTS ' . opp_owner_profile_table_name() . " (
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
  $result = pwg_query($schema_sql);
  if (!$result) {
    return false;
  }

  $GLOBALS['__opp_owner_profile_table_exists'] = true;
  return true;
}

function opp_fetch_owner_profile_rows(int $root_album_id, int $owner_user_id): array
{
  $rows = array();
  if ($root_album_id <= 0 || $owner_user_id <= 0 || !opp_ensure_owner_profile_table()) {
    return $rows;
  }

  $result = pwg_query('SELECT field_key, value_text, tag_id FROM ' . opp_owner_profile_table_name() . ' WHERE root_album_id=' . (int) $root_album_id . ' AND owner_user_id=' . (int) $owner_user_id . ' ORDER BY field_key ASC');
  if (!$result) {
    return $rows;
  }

  while ($row = pwg_db_fetch_assoc($result)) {
    $field_key = (string) ($row['field_key'] ?? '');
    if ($field_key === '') {
      continue;
    }
    $rows[$field_key] = array(
      'field_key' => $field_key,
      'value_text' => isset($row['value_text']) ? (string) $row['value_text'] : null,
      'tag_id' => isset($row['tag_id']) && $row['tag_id'] !== null && $row['tag_id'] !== '' ? (int) $row['tag_id'] : null,
    );
  }

  return $rows;
}

function opp_get_owner_profile_editor_data(int $user_id): ?array
{
  if ($user_id <= 0 || !opp_dependency_ready() || !opp_ensure_owner_profile_table()) {
    return null;
  }

  $root_album_id = cpt_get_effective_owner_root_album_id_for_user($user_id);
  if ($root_album_id === null) {
    return null;
  }

  $rows = opp_fetch_owner_profile_rows((int) $root_album_id, $user_id);
  $fields = array();
  foreach (opp_get_owner_profile_field_schema() as $field_key => $definition) {
    $field_type = (string) ($definition['type'] ?? 'text');
    $field_row = $rows[$field_key] ?? null;
    $field = array(
      'key' => (string) $field_key,
      'label' => (string) ($definition['label'] ?? $field_key),
      'type' => $field_type,
      'max_length' => isset($definition['max_length']) ? (int) $definition['max_length'] : null,
      'value_text' => is_array($field_row) ? (string) ($field_row['value_text'] ?? '') : '',
      'tag_id' => is_array($field_row) && !empty($field_row['tag_id']) ? (int) $field_row['tag_id'] : 0,
      'selected_tag_ids' => array(),
      'options' => array(),
      'from_value' => '',
      'to_value' => '',
    );

    if ($field_type === 'controlled' || $field_type === 'controlled_multi') {
      $options = opp_get_owner_profile_controlled_options((string) $field_key);
      if (empty($options)) {
        continue;
      }

      $field['options'] = $options;
      if ($field_type === 'controlled') {
        if ($field['tag_id'] <= 0 && $field['value_text'] !== '') {
          $field['tag_id'] = opp_resolve_owner_profile_option_id_from_value($options, $field['value_text']);
        }
      } else {
        $field['selected_tag_ids'] = opp_resolve_owner_profile_multi_option_ids_from_value($options, $field['value_text']);
      }
    } elseif ($field_type === 'availability_range') {
      $field['options'] = opp_get_owner_profile_availability_time_options();
      list($field['from_value'], $field['to_value']) = opp_parse_owner_profile_availability_range($field['value_text']);
    }

    $fields[] = $field;
  }

  if (empty($fields)) {
    return null;
  }

  return array(
    'root_album_id' => (int) $root_album_id,
    'fields' => $fields,
  );
}

function opp_get_owner_profile_public_data_for_album(int $album_id): ?array
{
  if (!opp_dependency_ready() || !opp_should_display_owner_profile_for_album($album_id) || !opp_ensure_owner_profile_table()) {
    return null;
  }

  $root_album_id = cpt_get_effective_owner_root_album_id_for_album($album_id);
  $owner_user_id = $root_album_id !== null ? cpt_get_album_effective_owner_id((int) $root_album_id) : null;
  if ($root_album_id === null || $owner_user_id === null) {
    return null;
  }

  $saved_rows = opp_fetch_owner_profile_rows((int) $root_album_id, (int) $owner_user_id);
  if (empty($saved_rows)) {
    return null;
  }

  $rows = array();
  $contacts = array();
  $availability = array();
  $contact_number = trim((string) ($saved_rows['contact_number']['value_text'] ?? ''));
  $normalized_contact_number = opp_normalize_public_contact_number($contact_number);

  foreach (opp_get_owner_profile_field_schema() as $field_key => $definition) {
    if (str_starts_with((string) $field_key, 'contact_')) {
      continue;
    }
    if (empty($saved_rows[$field_key]['value_text'])) {
      continue;
    }

    if (opp_is_owner_profile_availability_field((string) $field_key)) {
      $availability[] = array(
        'key' => (string) $field_key,
        'label' => (string) ($definition['label'] ?? $field_key),
        'value_text' => (string) $saved_rows[$field_key]['value_text'],
      );
      continue;
    }

    $rows[] = array(
      'key' => (string) $field_key,
      'label' => (string) ($definition['label'] ?? $field_key),
      'value_text' => (string) $saved_rows[$field_key]['value_text'],
    );
  }

  if ($contact_number !== '' && $normalized_contact_number !== '') {
    if (opp_is_public_contact_enabled($saved_rows, 'contact_phone')) {
      $contacts[] = array('key' => 'phone', 'label' => l10n('Phone calls'), 'display_value' => $contact_number, 'href' => 'tel:' . $normalized_contact_number);
    }
    if (opp_is_public_contact_enabled($saved_rows, 'contact_sms')) {
      $contacts[] = array('key' => 'sms', 'label' => l10n('SMS'), 'display_value' => $contact_number, 'href' => 'sms:' . $normalized_contact_number);
    }
    $whatsapp_number = opp_normalize_public_whatsapp_number($contact_number);
    if ($whatsapp_number !== '' && opp_is_public_contact_enabled($saved_rows, 'contact_whatsapp')) {
      $contacts[] = array('key' => 'whatsapp', 'label' => l10n('WhatsApp'), 'display_value' => $contact_number, 'href' => 'https://wa.me/' . $whatsapp_number);
    }
  }

  if (empty($rows) && empty($contacts) && empty($availability)) {
    return null;
  }

  return array(
    'root_album_id' => (int) $root_album_id,
    'owner_user_id' => (int) $owner_user_id,
    'rows' => $rows,
    'contacts' => $contacts,
    'availability' => $availability,
  );
}

function opp_update_owner_profile(array $payload, int $user_id): bool
{
  $validated = opp_validate_owner_profile_payload($payload, $user_id);
  if (empty($validated['fields']) || empty($validated['root_album_id']) || empty($validated['owner_user_id'])) {
    return false;
  }

  return opp_save_owner_profile((int) $validated['root_album_id'], (int) $validated['owner_user_id'], $validated['fields']);
}

function opp_get_contact_rows(int $user_id): array
{
  if ($user_id <= 0 || !opp_dependency_ready()) {
    return array();
  }

  $root_album_id = cpt_get_effective_owner_root_album_id_for_user($user_id);
  if ($root_album_id === null) {
    return array();
  }

  $rows = opp_fetch_owner_profile_rows((int) $root_album_id, $user_id);
  return array_intersect_key($rows, array_flip(array('contact_number', 'contact_phone', 'contact_sms', 'contact_whatsapp')));
}

function opp_get_contact_phone_candidate(int $user_id): array
{
  $rows = opp_get_contact_rows($user_id);
  $flags = array(
    'contact_phone' => opp_get_contact_flag_value($rows['contact_phone'] ?? null),
    'contact_sms' => opp_get_contact_flag_value($rows['contact_sms'] ?? null),
    'contact_whatsapp' => opp_get_contact_flag_value($rows['contact_whatsapp'] ?? null),
  );

  $candidate = array(
    'available' => false,
    'raw_phone' => null,
    'normalized_phone' => null,
    'masked_phone' => null,
    'source' => null,
    'flags' => $flags,
    'error' => null,
  );

  $raw_phone = trim((string) ($rows['contact_number']['value_text'] ?? ''));
  if ($raw_phone === '') {
    $candidate['error'] = l10n('Please add a valid contact phone number in My Profile first.');
    return $candidate;
  }

  $normalized_phone = opp_normalize_slovak_phone_number($raw_phone);
  if ($normalized_phone === null) {
    $candidate['error'] = l10n('Please add a valid contact phone number in My Profile first.');
    return $candidate;
  }

  $candidate['available'] = true;
  $candidate['raw_phone'] = $raw_phone;
  $candidate['normalized_phone'] = $normalized_phone;
  $candidate['masked_phone'] = opp_mask_phone_number($normalized_phone);
  $candidate['source'] = 'owner_profile.contact_number';

  return $candidate;
}

function opp_should_display_owner_profile_for_album(int $album_id): bool
{
  if ($album_id <= 0 || !opp_dependency_ready()) {
    return false;
  }

  $root_album_id = cpt_get_effective_owner_root_album_id_for_album($album_id);
  return $root_album_id !== null && $album_id === (int) $root_album_id;
}

function opp_get_current_album_page_category(): ?array
{
  global $page;

  if (($page['section'] ?? null) !== 'categories') {
    return null;
  }
  if (empty($page['category']) || !is_array($page['category']) || empty($page['category']['id'])) {
    return null;
  }
  if (!empty($page['combined_categories'])) {
    return null;
  }

  return $page['category'];
}

function opp_theme_uses_album_page_js_profile_placement(): bool
{
  return function_exists('get_themeconf') && get_themeconf('id') === 'bootstrap_darkroom';
}

function opp_inject_album_page_assets(string $html_partial): void
{
  global $template;

  $json = json_encode($html_partial, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
  $inline = 'window.OPP_ALBUM_PAGE_HTML = (typeof window.OPP_ALBUM_PAGE_HTML === "string" ? window.OPP_ALBUM_PAGE_HTML : "") + ' . $json . ';window.OPP_ALBUM_PAGE_ASSETS_READY=1;';
  if (is_object($template) && property_exists($template, 'scriptLoader') && $template->scriptLoader) {
    $template->scriptLoader->add_inline($inline, array('owner-profile-public'));
    return;
  }
  if (method_exists($template, 'append')) {
    $template->append('footer_msgs', '<script>' . $inline . '</script>');
  }
}

function opp_append_index_content_begin(string $html): void
{
  global $template;

  $existing = method_exists($template, 'get_template_vars') ? $template->get_template_vars('PLUGIN_INDEX_CONTENT_BEGIN') : null;
  $template->assign('PLUGIN_INDEX_CONTENT_BEGIN', (is_string($existing) ? $existing : '') . $html);
}

function opp_attach_profile_block(string $relative_template_path, string $block_name): void
{
  global $template;

  $template_path = realpath(opp_plugin_path() . $relative_template_path);
  if ($template_path === false) {
    return;
  }

  $existing_blocks = method_exists($template, 'get_template_vars') ? $template->get_template_vars('PLUGINS_PROFILE') : null;
  if (is_array($existing_blocks)) {
    foreach ($existing_blocks as $block) {
      if (is_array($block) && ($block['template'] ?? null) === $template_path) {
        return;
      }
    }
  }

  $block = array(
    'name' => $block_name,
    'desc' => '',
    'standard_show_save' => false,
    'template' => $template_path,
  );

  if (method_exists($template, 'append')) {
    $template->append('PLUGINS_PROFILE', $block);
    return;
  }

  $blocks = is_array($existing_blocks) ? $existing_blocks : array();
  $blocks[] = $block;
  $template->assign('PLUGINS_PROFILE', $blocks);
}

function opp_register_profile_assets(): void
{
  global $template;

  $css_path = opp_plugin_path() . 'template/style.css';
  if (file_exists($css_path) && method_exists($template, 'func_combine_css')) {
    $template->func_combine_css(array(
      'id' => 'owner-profile-ucp',
      'path' => 'plugins/' . opp_plugin_id() . '/template/style.css',
      'version' => filemtime($css_path),
      'order' => 20,
    ));
  }

  $script_path = opp_plugin_path() . 'js/owner_profile.js';
  if (file_exists($script_path) && method_exists($template, 'func_combine_script')) {
    $template->func_combine_script(array(
      'id' => 'owner-profile-ucp',
      'path' => 'plugins/' . opp_plugin_id() . '/js/owner_profile.js',
      'load' => 'footer',
      'version' => filemtime($script_path),
    ));
  }
}

function opp_parse_owner_profile_availability_range(string $value): array
{
  $value = trim($value);
  if ($value === l10n('Unavailable')) {
    return array('unavailable', '');
  }
  if ($value === '' || !str_contains($value, ' - ')) {
    return array('', '');
  }

  list($from, $to) = explode(' - ', $value, 2);
  return array(trim($from), trim($to));
}

function opp_resolve_owner_profile_option_id_from_value(array $options, string $value_text): int
{
  foreach ($options as $option_id => $option_label) {
    if ((string) $option_label === $value_text) {
      return (int) $option_id;
    }
  }
  return 0;
}

function opp_resolve_owner_profile_multi_option_ids_from_value(array $options, string $value_text): array
{
  if ($value_text === '') {
    return array();
  }

  $selected_labels = array_filter(array_map('trim', explode(',', $value_text)), static function (string $value): bool {
    return $value !== '';
  });
  if (empty($selected_labels)) {
    return array();
  }

  $selected_ids = array();
  foreach ($options as $option_id => $option_label) {
    if (in_array((string) $option_label, $selected_labels, true)) {
      $selected_ids[] = (int) $option_id;
    }
  }

  return $selected_ids;
}

function opp_normalize_owner_profile_text(string $field_key, string $value): string
{
  $definition = opp_get_owner_profile_field_definition($field_key);
  $normalized = trim($value);
  $max_length = isset($definition['max_length']) ? (int) $definition['max_length'] : 255;
  if ($max_length <= 0) {
    return $normalized;
  }

  return function_exists('mb_substr') ? mb_substr($normalized, 0, $max_length) : substr($normalized, 0, $max_length);
}

function opp_validate_owner_profile_field(string $field_key, array $field_payload): ?array
{
  $definition = opp_get_owner_profile_field_definition($field_key);
  if ($definition === null) {
    return null;
  }

  $field_type = (string) ($definition['type'] ?? 'text');
  if ($field_type === 'controlled') {
    $tag_id = isset($field_payload['tag_id']) ? (int) $field_payload['tag_id'] : 0;
    if ($tag_id <= 0) {
      return array('delete' => true);
    }

    $options = opp_get_owner_profile_controlled_options($field_key);
    if (!isset($options[$tag_id])) {
      return null;
    }

    return array('delete' => false, 'tag_id' => $tag_id, 'value_text' => (string) $options[$tag_id]);
  }

  if ($field_type === 'controlled_multi') {
    $tag_ids = $field_payload['tag_ids'] ?? array();
    if (!is_array($tag_ids)) {
      $tag_ids = array($tag_ids);
    }

    $options = opp_get_owner_profile_controlled_options($field_key);
    $labels = array();
    foreach ($tag_ids as $tag_id) {
      $tag_id = (int) $tag_id;
      if ($tag_id > 0 && isset($options[$tag_id])) {
        $labels[] = (string) $options[$tag_id];
      }
    }

    $labels = array_values(array_unique($labels));
    if (empty($labels)) {
      return array('delete' => true);
    }

    return array('delete' => false, 'tag_id' => null, 'value_text' => implode(', ', $labels));
  }

  if ($field_type === 'availability_range') {
    $from_value = trim((string) ($field_payload['from_value'] ?? ''));
    $to_value = trim((string) ($field_payload['to_value'] ?? ''));
    if ($from_value === '' && $to_value === '') {
      return array('delete' => true);
    }

    $options = opp_get_owner_profile_availability_time_options();
    if ($from_value === 'unavailable') {
      return array('delete' => false, 'tag_id' => null, 'value_text' => l10n('Unavailable'));
    }
    if ($from_value === '' || $to_value === '' || !isset($options[$from_value]) || !isset($options[$to_value]) || $to_value === 'unavailable') {
      return null;
    }

    return array('delete' => false, 'tag_id' => null, 'value_text' => $from_value . ' - ' . $to_value);
  }

  $value = opp_normalize_owner_profile_text($field_key, (string) ($field_payload['value_text'] ?? ''));
  if ($field_key === 'contact_number') {
    $normalized_phone = opp_normalize_slovak_phone_number($value);
    if ($normalized_phone === null) {
      if ($value === '') {
        return array('delete' => true);
      }

      opp_set_last_error(l10n('Please add a valid contact phone number in My Profile first.'));
      return null;
    }
  }
  if ($value === '') {
    return array('delete' => true);
  }

  return array('delete' => false, 'tag_id' => null, 'value_text' => $value);
}

function opp_validate_owner_profile_payload(array $payload, int $user_id): array
{
  $root_album_id = (int) ($payload['root_album_id'] ?? 0);
  if ($root_album_id <= 0 || !opp_dependency_ready()) {
    return array();
  }
  if (cpt_get_effective_owner_root_album_id_for_album($root_album_id) !== $root_album_id) {
    return array();
  }
  if (cpt_get_album_effective_owner_id($root_album_id) !== (int) $user_id) {
    return array();
  }

  $fields_payload = $payload['fields'] ?? null;
  if (!is_array($fields_payload)) {
    return array();
  }

  $validated_fields = array();
  foreach ($fields_payload as $field_key => $field_payload) {
    if (!is_array($field_payload)) {
      $field_payload = array('value_text' => (string) $field_payload);
    }

    $validated = opp_validate_owner_profile_field((string) $field_key, $field_payload);
    if ($validated === null) {
      if (opp_get_last_error() !== null) {
        return array();
      }
      continue;
    }
    $validated_fields[(string) $field_key] = $validated;
  }
    opp_clear_last_error();


  return array(
    'root_album_id' => $root_album_id,
    'owner_user_id' => (int) $user_id,
    'fields' => $validated_fields,
  );
}

function opp_save_owner_profile(int $root_album_id, int $owner_user_id, array $fields): bool
{
  if ($root_album_id <= 0 || $owner_user_id <= 0 || empty($fields) || !opp_dependency_ready() || !opp_ensure_owner_profile_table()) {
    return false;
  }
  if (cpt_get_effective_owner_root_album_id_for_album($root_album_id) !== $root_album_id) {
    return false;
  }
  if (cpt_get_album_effective_owner_id($root_album_id) !== (int) $owner_user_id) {
    return false;
  }

  $updated_any = false;
  foreach ($fields as $field_key => $field_data) {
    if (opp_get_owner_profile_field_definition((string) $field_key) === null) {
      continue;
    }

    pwg_query("DELETE FROM " . opp_owner_profile_table_name() . " WHERE root_album_id=" . (int) $root_album_id . " AND field_key='" . pwg_db_real_escape_string((string) $field_key) . "'");
    $updated_any = true;

    if (!empty($field_data['delete'])) {
      continue;
    }

    $value_sql = isset($field_data['value_text']) && $field_data['value_text'] !== null ? "'" . pwg_db_real_escape_string((string) $field_data['value_text']) . "'" : 'NULL';
    $tag_sql = isset($field_data['tag_id']) && $field_data['tag_id'] !== null ? (string) (int) $field_data['tag_id'] : 'NULL';
    $sql = sprintf(
      "INSERT INTO %s (root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at) VALUES (%d, %d, '%s', %s, %s, NOW())",
      opp_owner_profile_table_name(),
      (int) $root_album_id,
      (int) $owner_user_id,
      pwg_db_real_escape_string((string) $field_key),
      $value_sql,
      $tag_sql
    );
    $result = pwg_query($sql);
    $updated_any = $updated_any || (bool) $result;
  }

  return $updated_any;
}

function opp_is_public_contact_enabled(array $saved_rows, string $field_key): bool
{
  if (empty($saved_rows[$field_key]) || !is_array($saved_rows[$field_key])) {
    return false;
  }

  $row = $saved_rows[$field_key];
  if (isset($row['tag_id']) && $row['tag_id'] !== null) {
    return (int) $row['tag_id'] === 1;
  }

  $value_text = trim((string) ($row['value_text'] ?? ''));
  if ($value_text === '') {
    return false;
  }

  $options = opp_get_owner_profile_controlled_options($field_key);
  if (!empty($options)) {
    return opp_resolve_owner_profile_option_id_from_value($options, $value_text) === 1;
  }

  return $value_text === l10n('Yes');
}

function opp_get_contact_flag_value($row)
{
  if (!is_array($row) || empty($row)) {
    return null;
  }
  if (isset($row['tag_id']) && $row['tag_id'] !== null) {
    if ((int) $row['tag_id'] === 1) {
      return true;
    }
    if ((int) $row['tag_id'] === 2) {
      return false;
    }
  }

  $value = strtolower(trim((string) ($row['value_text'] ?? '')));
  if (in_array($value, array('1', 'yes', 'true'), true)) {
    return true;
  }
  if (in_array($value, array('0', 'no', 'false'), true)) {
    return false;
  }

  return null;
}

function opp_normalize_public_contact_number(string $value): string
{
  $normalized = opp_normalize_slovak_phone_number($value);
  return $normalized ?? '';
}

function opp_normalize_public_whatsapp_number(string $value): string
{
  $normalized = opp_normalize_slovak_phone_number($value);
  return $normalized === null ? '' : ltrim($normalized, '+');
}

function opp_normalize_slovak_phone_number(string $value): ?string
{
  $trimmed = trim($value);
  if ($trimmed === '') {
    return null;
  }

  $digits = preg_replace('/[^0-9+]/', '', $trimmed) ?? '';
  if ($digits === '') {
    return null;
  }

  if (str_starts_with($digits, '+')) {
    $plain = '+' . preg_replace('/[^0-9]/', '', substr($digits, 1));
    return preg_match('/^\+421\d{9}$/', $plain) ? $plain : null;
  }

  $plain_digits = preg_replace('/[^0-9]/', '', $digits) ?? '';
  if (preg_match('/^421\d{9}$/', $plain_digits)) {
    return '+' . $plain_digits;
  }
  if (preg_match('/^0\d{9}$/', $plain_digits)) {
    return '+421' . substr($plain_digits, 1);
  }
  if (preg_match('/^9\d{8}$/', $plain_digits)) {
    return '+421' . $plain_digits;
  }

  return null;
}

function opp_mask_phone_number(string $normalized_phone): string
{
  if (strlen($normalized_phone) <= 7) {
    return $normalized_phone;
  }
  return substr($normalized_phone, 0, 7) . '***' . substr($normalized_phone, -3);
}

function opp_is_plugin_active(string $plugin_id): bool
{
  global $conf;
  return !empty($conf['active_plugins']) && in_array($plugin_id, $conf['active_plugins'], true);
}

function opp_dependency_ready(): bool
{
  return opp_is_plugin_active('core_privacy_toggle')
    && function_exists('cpt_get_effective_owner_root_album_id_for_album')
    && function_exists('cpt_get_effective_owner_root_album_id_for_user')
    && function_exists('cpt_get_album_effective_owner_id');
}

function opp_get_admin_warning(): ?string
{
  if (opp_dependency_ready()) {
    return null;
  }
  return 'Owner Profile requires Core Privacy Toggle for owner/root album resolution.';
}