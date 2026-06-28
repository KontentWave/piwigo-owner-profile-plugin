(function () {
  "use strict";

  function setStatusMessage(root, message, isError) {
    var statusBox = root.querySelector(".opp-status");
    if (!statusBox) {
      return;
    }

    statusBox.hidden = !message;
    statusBox.textContent = message || "";
    statusBox.className =
      "opp-status alert " + (isError ? "alert-danger" : "alert-success");
  }

  function collectOwnerProfilePayload(profile) {
    if (!profile) {
      return null;
    }

    var rootAlbumId = profile.getAttribute("data-root-album-id");
    if (!rootAlbumId) {
      return null;
    }

    var fields = profile.querySelectorAll(
      ".opp-owner-profile-field[data-field-key]",
    );
    var payload = {
      root_album_id: parseInt(rootAlbumId, 10),
      fields: {},
    };

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      var fieldKey = field.getAttribute("data-field-key");
      var fieldType = field.getAttribute("data-field-type") || "text";
      if (!fieldKey) {
        continue;
      }

      if (fieldType === "controlled") {
        var select = field.querySelector("select");
        payload.fields[fieldKey] = {
          tag_id: select && select.value ? parseInt(select.value, 10) : 0,
        };
        continue;
      }

      if (fieldType === "controlled_multi") {
        var multiSelect = field.querySelector("select");
        payload.fields[fieldKey] = {
          tag_ids: multiSelect
            ? Array.prototype.slice
                .call(multiSelect.options)
                .filter(function (option) {
                  return option.selected;
                })
                .map(function (option) {
                  return parseInt(option.value, 10);
                })
                .filter(function (value) {
                  return !isNaN(value) && value > 0;
                })
            : [],
        };
        continue;
      }

      if (fieldType === "availability_range") {
        var fromSelect = field.querySelector('select[data-role="from"]');
        var toSelect = field.querySelector('select[data-role="to"]');
        payload.fields[fieldKey] = {
          from_value: fromSelect ? fromSelect.value : "",
          to_value: toSelect ? toSelect.value : "",
        };
        continue;
      }

      var input = field.querySelector("input, textarea");
      payload.fields[fieldKey] = {
        value_text: input ? input.value : "",
      };
    }

    return payload;
  }

  function getContactNumberField(profile) {
    if (!profile) {
      return null;
    }

    return profile.querySelector(
      '.opp-owner-profile-field[data-field-key="contact_number"] input',
    );
  }

  function normalizeSlovakPhoneNumber(value) {
    var trimmed = String(value || "").trim();
    if (!trimmed) {
      return null;
    }

    var digits = trimmed.replace(/[^0-9+]/g, "");
    if (!digits) {
      return null;
    }

    if (digits.charAt(0) === "+") {
      var plusNormalized = "+" + digits.slice(1).replace(/[^0-9]/g, "");
      return /^\+421\d{9}$/.test(plusNormalized) ? plusNormalized : null;
    }

    var plainDigits = digits.replace(/[^0-9]/g, "");
    if (/^421\d{9}$/.test(plainDigits)) {
      return "+" + plainDigits;
    }
    if (/^0\d{9}$/.test(plainDigits)) {
      return "+421" + plainDigits.slice(1);
    }
    if (/^9\d{8}$/.test(plainDigits)) {
      return "+421" + plainDigits;
    }

    return null;
  }

  function validateOwnerProfilePayload(profile, payload) {
    if (!profile || !payload || !payload.fields) {
      return null;
    }

    var contactField = getContactNumberField(profile);
    if (!contactField) {
      return null;
    }

    var rawPhone = contactField.value || "";
    if (!rawPhone.trim()) {
      return null;
    }

    if (!normalizeSlovakPhoneNumber(rawPhone)) {
      var invalidMessage =
        contactField.getAttribute("data-invalid-phone-message") ||
        "Please add a valid contact phone number in My Profile first.";
      contactField.setCustomValidity(invalidMessage);
      if (typeof contactField.reportValidity === "function") {
        contactField.reportValidity();
      }
      contactField.focus();
      return invalidMessage;
    }

    contactField.setCustomValidity("");

    return null;
  }

  function submitWsRequest(method, token, payload) {
    var params = new URLSearchParams();
    params.set("pwg_token", token);
    params.set("payload", JSON.stringify(payload));

    return fetch("ws.php?format=json&method=" + encodeURIComponent(method), {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: params.toString(),
      credentials: "same-origin",
    }).then(function (response) {
      return response.json();
    });
  }

  function getWsErrorMessage(data) {
    if (!data) {
      return "An error has occurred.";
    }

    if (typeof data.message === "string" && data.message.trim() !== "") {
      return data.message;
    }

    if (typeof data.result === "string" && data.result.trim() !== "") {
      return data.result;
    }

    if (
      data.result &&
      typeof data.result.message === "string" &&
      data.result.message.trim() !== ""
    ) {
      return data.result.message;
    }

    if (Array.isArray(data.errors) && data.errors.length > 0) {
      var firstError = data.errors[0];
      if (
        firstError &&
        typeof firstError.message === "string" &&
        firstError.message.trim() !== ""
      ) {
        return firstError.message;
      }
    }

    return "An error has occurred.";
  }

  function syncContactFieldValidity(profile) {
    var contactField = getContactNumberField(profile);
    if (!contactField) {
      return;
    }

    var rawPhone = contactField.value || "";
    if (!rawPhone.trim()) {
      contactField.setCustomValidity("");
      return;
    }

    if (normalizeSlovakPhoneNumber(rawPhone)) {
      contactField.setCustomValidity("");
      return;
    }

    contactField.setCustomValidity(
      contactField.getAttribute("data-invalid-phone-message") ||
        "Please add a valid contact phone number in My Profile first.",
    );
  }

  function initProfileValidation() {
    var profiles = document.querySelectorAll(".opp-owner-profile");
    for (var i = 0; i < profiles.length; i++) {
      var profile = profiles[i];
      var contactField = getContactNumberField(profile);
      if (!contactField) {
        continue;
      }

      syncContactFieldValidity(profile);
      contactField.addEventListener("input", function () {
        var currentProfile = this.closest(".opp-owner-profile");
        syncContactFieldValidity(currentProfile);
      });
      contactField.addEventListener("blur", function () {
        var currentProfile = this.closest(".opp-owner-profile");
        syncContactFieldValidity(currentProfile);
      });
    }
  }

  function showToaster(message, isError) {
    if (!message || typeof window.pwgToaster !== "function") {
      return;
    }

    window.pwgToaster({ text: message, icon: isError ? "error" : "success" });
  }

  function buildFragment(html) {
    var wrapper = document.createElement("div");
    wrapper.innerHTML = html;
    if (!wrapper.firstElementChild) {
      return null;
    }

    var fragment = document.createDocumentFragment();
    while (wrapper.firstChild) {
      fragment.appendChild(wrapper.firstChild);
    }
    return fragment;
  }

  function placePublicProfile() {
    if (
      typeof window.OPP_ALBUM_PAGE_HTML !== "string" ||
      window.OPP_ALBUM_PAGE_HTML.trim() === ""
    ) {
      return;
    }

    if (
      document.querySelector(
        ".opp-owner-profile-public, .opp-owner-profile-mobile, .opp-owner-profile-desktop",
      )
    ) {
      return;
    }

    var mobileAnchor = document.querySelector(
      "#content-description-mobile, #content-description-mobile-fallback",
    );
    var desktopAnchor = document.querySelector("#content-description-desktop");

    if (mobileAnchor) {
      var mobileContainer = document.createElement("div");
      mobileContainer.className =
        "opp-owner-profile-mobile col-outer col-12 py-3 d-lg-none";
      var mobileFragment = buildFragment(window.OPP_ALBUM_PAGE_HTML);
      if (mobileFragment) {
        mobileContainer.appendChild(mobileFragment);
        mobileAnchor.insertAdjacentElement("afterend", mobileContainer);
      }
    }

    if (desktopAnchor) {
      var desktopContainer = document.createElement("div");
      desktopContainer.className =
        "opp-owner-profile-desktop py-3 d-none d-lg-block";
      var desktopFragment = buildFragment(window.OPP_ALBUM_PAGE_HTML);
      if (desktopFragment) {
        desktopContainer.appendChild(desktopFragment);
        desktopAnchor.insertAdjacentElement("afterend", desktopContainer);
      }
    }

    if (mobileAnchor || desktopAnchor) {
      return;
    }

    var content = document.querySelector('#content, div[data-role="content"]');
    if (!content) {
      return;
    }

    var fallbackFragment = buildFragment(window.OPP_ALBUM_PAGE_HTML);
    if (fallbackFragment) {
      content.insertBefore(fallbackFragment, content.firstChild);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    placePublicProfile();
    initProfileValidation();
  });

  document.addEventListener("click", function (event) {
    var profileButton = event.target.closest(".opp-owner-profile-save-button");
    if (!profileButton) {
      return;
    }

    var profileCard = profileButton.closest(".opp-owner-profile");
    var tokenField = document.getElementById("pwg_token");
    if (!profileCard || !tokenField || !tokenField.value) {
      return;
    }

    var profilePayload = collectOwnerProfilePayload(profileCard);
    if (!profilePayload) {
      return;
    }

    var validationMessage = validateOwnerProfilePayload(
      profileCard,
      profilePayload,
    );
    if (validationMessage) {
      setStatusMessage(profileCard, validationMessage, true);
      showToaster(validationMessage, true);
      return;
    }

    setStatusMessage(profileCard, "", false);
    profileButton.disabled = true;

    submitWsRequest("owner_profile.update", tokenField.value, profilePayload)
      .then(function (data) {
        if (data && data.stat === "ok") {
          setStatusMessage(
            profileCard,
            data.result || "Your changes have been saved.",
            false,
          );
          showToaster(data.result, false);
          return;
        }

        var message = getWsErrorMessage(data);
        setStatusMessage(profileCard, message, true);
        showToaster(message, true);
      })
      .catch(function () {
        var message = "An error has occurred.";
        setStatusMessage(profileCard, message, true);
        showToaster(message, true);
      })
      .finally(function () {
        profileButton.disabled = false;
      });
  });
})();
