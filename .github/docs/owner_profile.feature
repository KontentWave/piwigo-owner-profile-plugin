# Filename: owner_profile.feature

Feature: Owner Profile plugin
  As an album owner
  I want to manage my public profile separately from album privacy
  So that profile data can be reused by display, SMS verification, and future search features

  Background:
    Given the CPT plugin is active
    And the Owner Profile plugin is active
    And user "gallery_owner" owns root album "slecna1"

  Scenario: Owner sees My Profile section
    Given I am logged in as "gallery_owner"
    When I open my Piwigo profile page
    Then I should see the "My Profile" section
    And the section should be provided by the Owner Profile plugin

  Scenario: Owner saves profile fields
    Given I am logged in as "gallery_owner"
    And I open the "My Profile" section
    When I enter "24" as "Age"
    And I enter "Bratislava" as "City"
    And I enter "+421 905 000 000" as "Contact number"
    And I enable "Phone calls"
    And I enable "WhatsApp"
    And I save the profile
    Then the profile should be saved for root album "slecna1"
    And the saved contact number should be "+421 905 000 000"

  Scenario: Non-owner cannot edit another owner profile
    Given user "visitor" exists
    And I am logged in as "visitor"
    When I submit a crafted profile save request for "gallery_owner"
    Then the request should be rejected
    And "gallery_owner" profile data should remain unchanged

  Scenario: Public album page displays owner profile
    Given "gallery_owner" has saved public profile fields
    When a visitor opens album "slecna1"
    Then the public profile block should be displayed
    And it should include profile rows
    And it should include enabled contact links
    And it should not display empty fields

  Scenario: Contact phone candidate is read from Owner Profile
    Given "gallery_owner" saved contact number "+421 905 000 000"
    And "gallery_owner" enabled SMS contact
    When Two Factor SMS asks for the contact phone candidate
    Then Owner Profile should return normalized phone "+421905000000"
    And it should mark the source as "owner_profile.contact_number"

  Scenario: Contact flags are not treated as phone numbers
    Given "gallery_owner" saved contact number "+421 905 000 000"
    And "gallery_owner" set "contact_sms" to "Yes"
    When Two Factor SMS asks for the contact phone candidate
    Then the returned phone should come from "contact_number"
    And "contact_sms" should be returned only as a channel flag
