<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class OwnerProfileTest extends TestCase
{
    protected function setUp(): void
    {
        opp_test_reset_env();
    }

    public function testOwnerCanSaveAndReloadProfileFields(): void
    {
        $rootAlbumId = opp_test_create_root_album(6, 'slecna1');

        $saved = opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
                'city' => ['tag_id' => 1],
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

        $this->assertTrue(opp_update_owner_profile([
            'root_album_id' => $rootAlbumId,
            'fields' => [
                'age' => ['value_text' => '24'],
                'city' => ['tag_id' => 1],
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
}