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
    return data && data.message ? data.message : "An error has occurred.";
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

  document.addEventListener("DOMContentLoaded", placePublicProfile);

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
