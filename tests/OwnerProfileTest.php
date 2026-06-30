<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class OwnerProfileTest extends TestCase
{
    protected function setUp(): void
    {
        opp_test_reset_env();
        $GLOBALS['__opp_test_theme_id'] = 'default';
    }

    private function getControlledOptionId(string $fieldKey, string $label): int
    {
        $optionId = opp_resolve_owner_profile_option_id_from_value(
            opp_get_owner_profile_controlled_options($fieldKey),
            $label
        );

        $this->assertGreaterThan(0, $optionId, sprintf('Missing %s option: %s', $fieldKey, $label));

        return $optionId;
    }

    public function testOwnerCanSaveAndReloadProfileFields(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $cityOptionId = $this->getControlledOptionId('city', 'Bratislava');

        $saved = opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
                'city' => ['tag_id' => $cityOptionId],
                'contact_number' => ['value_text' => '+421 905 000 000'],
            ],
        ], 6);

        $this->assertTrue($saved);

        $rows = opp_fetch_owner_profile_rows($rootAlbumId, 6);
        $this->assertSame('24', $rows['age']['value_text']);
        $this->assertSame('Bratislava', $rows['city']['value_text']);
        $this->assertSame('+421 905 000 000', $rows['contact_number']['value_text']);
    }

    public function testNonOwnerCannotSaveAnotherOwnersProfile(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');

        $saved = opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
            ],
        ], 7);

        $this->assertFalse($saved);
        $this->assertSame([], opp_fetch_owner_profile_rows($rootAlbumId, 6));
    }

    public function testInvalidSlovakPhoneRejectsSaveWithSpecificError(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');

        $saved = opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'contact_number' => ['value_text' => '+420 905 000 000'],
            ],
        ], 6);

        $this->assertFalse($saved);
        $this->assertSame('Please add a valid contact phone number in My Profile first.', opp_get_last_error());
        $this->assertSame([], opp_fetch_owner_profile_rows($rootAlbumId, 6));
    }

    public function testPublicProfileDataIncludesRowsContactsAndAvailabilityOnlyOnRootAlbum(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $childAlbumId = opp_test_create_child_album($rootAlbumId, 'slecna1_album1');
        $cityOptionId = $this->getControlledOptionId('city', 'Bratislava');

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
                'city' => ['tag_id' => $cityOptionId],
                'contact_number' => ['value_text' => '903223183'],
                'contact_phone' => ['tag_id' => 1],
                'contact_sms' => ['tag_id' => 1],
                'contact_whatsapp' => ['tag_id' => 1],
                'availability_monday' => ['from_value' => '10:00', 'to_value' => '20:00'],
                'measurements' => ['value_text' => '   '],
            ],
        ], 6));

        $rootProfile = opp_get_owner_profile_public_data_for_album($rootAlbumId);
        $childProfile = opp_get_owner_profile_public_data_for_album($childAlbumId);

        $this->assertNotNull($rootProfile);
        $this->assertNull($childProfile);
        $this->assertCount(2, $rootProfile['rows']);
        $this->assertSame('City', $rootProfile['rows'][0]['label']);
        $this->assertSame('Age', $rootProfile['rows'][1]['label']);
        $this->assertCount(3, $rootProfile['contacts']);
        $this->assertSame('tel:+421903223183', $rootProfile['contacts'][0]['href']);
        $this->assertSame('sms:+421903223183', $rootProfile['contacts'][1]['href']);
        $this->assertSame('https://wa.me/421903223183', $rootProfile['contacts'][2]['href']);
        $this->assertCount(1, $rootProfile['availability']);
        $this->assertSame('10:00 - 20:00', $rootProfile['availability'][0]['value_text']);
    }

    public function testCityOptionsAreLoadedLocallyWithContinuationRows(): void
    {
        $options = opp_get_owner_profile_controlled_options('city');

        $this->assertGreaterThan(2000, count($options));
        $this->assertContains('Bratislava II', $options);
        $this->assertContains('Bratislava III', $options);
        $this->assertContains('Košice IV', $options);
        $this->assertNotSame([1 => 'Bratislava', 2 => 'Kosice'], $options);
    }

    public function testEditorRemapsLegacyCityTagIdFromSavedValue(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $resolvedCityId = $this->getControlledOptionId('city', 'Bratislava III');

        $GLOBALS['__opp_db']['owner_profile'][] = [
            'id' => opp_test_next_id('owner_profile'),
            'root_album_id' => $rootAlbumId,
            'owner_user_id' => 6,
            'field_key' => 'city',
            'value_text' => 'Bratislava III',
            'tag_id' => 1,
        ];

        $editorData = opp_get_owner_profile_editor_data(6);
        $cityField = null;
        foreach ($editorData['fields'] as $field) {
            if (($field['key'] ?? null) === 'city') {
                $cityField = $field;
                break;
            }
        }

        $this->assertIsArray($cityField);
        $this->assertSame($resolvedCityId, $cityField['tag_id']);
        $this->assertSame('Bratislava III', $cityField['options'][$cityField['tag_id']]);
    }

    public function testAttachOwnerProfileToAlbumPageAssignsRenderedTable(): void
    {
        global $page, $template;

        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $page['section'] = 'categories';
        $page['category'] = ['id' => $rootAlbumId, 'status' => 'public'];

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
                'contact_number' => ['value_text' => '+421 905 000 000'],
                'contact_phone' => ['tag_id' => 1],
            ],
        ], 6));

        opp_attach_owner_profile_to_album_page();

        $rendered = $template->get_template_vars('OPP_OWNER_PROFILE_TABLE');
        $pluginSlot = $template->get_template_vars('PLUGIN_INDEX_CONTENT_BEGIN');

        $this->assertIsString($rendered);
        $this->assertStringContainsString('opp-owner-profile-public', $rendered);
        $this->assertStringContainsString('Age', $rendered);
        $this->assertStringContainsString('tel:+421905000000', $rendered);
        $this->assertStringContainsString('opp-owner-profile-public', $pluginSlot);
    }

    public function testAttachOwnerProfileToAlbumPageKeepsBootstrapDarkroomPlacementInSmartyVariable(): void
    {
        global $page, $template;

        $GLOBALS['__opp_test_theme_id'] = 'bootstrap_darkroom';

        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $page['section'] = 'categories';
        $page['category'] = ['id' => $rootAlbumId, 'status' => 'public'];

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
                'contact_number' => ['value_text' => '+421 905 000 000'],
                'contact_phone' => ['tag_id' => 1],
            ],
        ], 6));

        opp_attach_owner_profile_to_album_page();

        $rendered = $template->get_template_vars('OPP_OWNER_PROFILE_TABLE');
        $pluginSlot = $template->get_template_vars('PLUGIN_INDEX_CONTENT_BEGIN');

        $this->assertIsString($rendered);
        $this->assertStringContainsString('opp-owner-profile-public', $rendered);
        $this->assertNull($pluginSlot);
        $this->assertSame([], $template->footer_msgs);
    }

    public function testPrepareAlbumPageAssetsSkipsBootstrapDarkroomPlacementScript(): void
    {
        global $page, $template;

        $GLOBALS['__opp_test_theme_id'] = 'bootstrap_darkroom';

        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $page['section'] = 'categories';
        $page['category'] = ['id' => $rootAlbumId, 'status' => 'public'];

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
            ],
        ], 6));

        opp_prepare_album_page_assets();

        $this->assertIsString($template->get_template_vars('OPP_OWNER_PROFILE_TABLE'));
        $this->assertCount(1, $template->combined_css);
        $this->assertSame([], $template->combined_script);
    }

    public function testProfilePageHookAttachesReplacementMyProfileBlockIndependentlyOfLegacyCptVariable(): void
    {
        global $template, $user;

        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $user = ['id' => 6, 'is_guest' => false, 'status' => 'normal'];

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
            ],
        ], 6));

        $template->assign('UCP_OWNER_PROFILE', ['fields' => [['key' => 'legacy']]]);

        opp_setup_profile_page();

        $blockData = $template->get_template_vars('PLUGINS_PROFILE');
        $editorData = $template->get_template_vars('OPP_UCP_OWNER_PROFILE');

        $this->assertIsArray($blockData);
        $this->assertNotEmpty($blockData);
        $this->assertSame($rootAlbumId, $editorData['root_album_id']);
        $this->assertSame('24', $editorData['fields'][2]['value_text']);

        $templates = array_map(static fn($block) => $block['template'] ?? null, $blockData);
        $this->assertContains(realpath(OWNER_PROFILE_PATH . 'template/ucp_owner_profile.tpl'), $templates);
        $this->assertSame(['fields' => [['key' => 'legacy']]], $template->get_template_vars('UCP_OWNER_PROFILE'));
    }

    public function testProfilePageHookDoesNotDependOnActivePluginsArrayShape(): void
    {
        global $template, $user, $conf;

        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');
        $user = ['id' => 6, 'is_guest' => false, 'status' => 'normal'];
        $conf['active_plugins'] = ['core_privacy_toggle' => true, 'owner_profile' => true];

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
            ],
        ], 6));

        opp_setup_profile_page();

        $editorData = $template->get_template_vars('OPP_UCP_OWNER_PROFILE');
        $this->assertIsArray($editorData);
        $this->assertSame($rootAlbumId, $editorData['root_album_id']);
        $this->assertSame('24', $editorData['fields'][2]['value_text']);
    }

    public function testCptAlbumVisibilityChangesDoNotModifyOwnerProfileRows(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
                'contact_number' => ['value_text' => '+421 905 000 000'],
                'contact_phone' => ['tag_id' => 1],
            ],
        ], 6));

        $before = opp_fetch_owner_profile_rows($rootAlbumId, 6);
        opp_test_update_album_visibility($rootAlbumId, 'shared', [9, 10]);
        $after = opp_fetch_owner_profile_rows($rootAlbumId, 6);

        $this->assertSame('shared', cpt_get_album_visibility_mode($rootAlbumId));
        $this->assertSame([9, 10], cpt_get_album_shared_user_ids($rootAlbumId));
        $this->assertSame($before, $after);
    }

    public function testOwnerProfileStillResolvesEffectiveRootAlbumWhileCptVisibilityHelpersRemainUsable(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1', 'shared');
        opp_test_update_album_visibility($rootAlbumId, 'shared', [9, 10]);
        $childAlbumId = opp_test_create_child_album($rootAlbumId, 'slecna1_album1');

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
            ],
        ], 6));

        $editorData = opp_get_owner_profile_editor_data(6);
        $rootProfile = opp_get_owner_profile_public_data_for_album($rootAlbumId);
        $childProfile = opp_get_owner_profile_public_data_for_album($childAlbumId);
        $rootData = cpt_get_effective_owner_root_album_data(6);

        $this->assertSame($rootAlbumId, cpt_get_effective_owner_root_album_id_for_album($childAlbumId));
        $this->assertSame($rootAlbumId, $editorData['root_album_id']);
        $this->assertSame($rootAlbumId, $rootData['id']);
        $this->assertSame('shared', cpt_get_album_visibility_mode($rootAlbumId));
        $this->assertSame([9, 10], cpt_get_album_shared_user_ids($rootAlbumId));
        $this->assertNotNull($rootProfile);
        $this->assertNull($childProfile);
    }
}