;(($) => {
  // Declare the ultraInstinct variable
  var ultraInstinct = window.ultraInstinct

  $(document).ready(() => {
    // Generate API key
    $("#generate-key, #regenerate-key").on("click", function () {
      var $button = $(this)
      var originalText = $button.text()

      $button.prop("disabled", true).text(ultraInstinct.strings.generating)

      $.ajax({
        url: ultraInstinct.ajaxUrl,
        type: "POST",
        data: {
          action: "ultra_instinct_generate_key",
          nonce: ultraInstinct.nonce,
        },
        success: (response) => {
          if (response.success) {
            $("#api-key-value").val(response.data.api_key)
            $("#key-display").slideDown()
            updateConnectionStatus("connected")
            showNotice(response.data.message, "success")

            // Auto-select the key for easy copying
            setTimeout(() => {
              $("#api-key-value").focus().select()
            }, 300)

            // Refresh page after 3 seconds
            setTimeout(() => {
              location.reload()
            }, 3000)
          } else {
            showNotice(response.data.message, "error")
          }
        },
        error: () => {
          showNotice("An error occurred while generating the API key.", "error")
        },
        complete: () => {
          $button.prop("disabled", false).text(originalText)
        },
      })
    })

    // Revoke API key
    $("#revoke-key").on("click", function () {
      if (!confirm(ultraInstinct.strings.confirmRevoke)) {
        return
      }

      var $button = $(this)
      var originalText = $button.text()

      $button.prop("disabled", true).text(ultraInstinct.strings.revoking)

      $.ajax({
        url: ultraInstinct.ajaxUrl,
        type: "POST",
        data: {
          action: "ultra_instinct_revoke_key",
          nonce: ultraInstinct.nonce,
        },
        success: (response) => {
          if (response.success) {
            showNotice(response.data.message, "success")
            updateConnectionStatus("disconnected")

            // Refresh page after 2 seconds
            setTimeout(() => {
              location.reload()
            }, 2000)
          } else {
            showNotice(response.data.message, "error")
          }
        },
        error: () => {
          showNotice("An error occurred while revoking the API key.", "error")
        },
        complete: () => {
          $button.prop("disabled", false).text(originalText)
        },
      })
    })

    // Test connection
    $("#test-connection").on("click", function () {
      var $button = $(this)
      var originalText = $button.text()

      $button.prop("disabled", true).text(ultraInstinct.strings.testing)
      updateConnectionStatus("testing")

      $.ajax({
        url: ultraInstinct.ajaxUrl,
        type: "POST",
        data: {
          action: "ultra_instinct_test_connection",
          nonce: ultraInstinct.nonce,
        },
        success: (response) => {
          if (response.success) {
            updateConnectionStatus(response.data.status)
            showNotice(ultraInstinct.strings.connectionSuccess, "success")
          } else {
            updateConnectionStatus("disconnected")
            showNotice(ultraInstinct.strings.connectionFailed, "error")
          }
        },
        error: () => {
          updateConnectionStatus("disconnected")
          showNotice("Connection test failed.", "error")
        },
        complete: () => {
          $button.prop("disabled", false).text(originalText)
        },
      })
    })

    // Validate platform API key
    $("#validate-key").on("click", function () {
      var apiKey = $("#platform-api-key").val().trim()

      if (!apiKey) {
        showNotice("Please enter an API key.", "error")
        $("#platform-api-key").focus()
        return
      }

      if (apiKey.length < 32) {
        showNotice("API key appears to be too short. Please check and try again.", "error")
        $("#platform-api-key").focus()
        return
      }

      var $button = $(this)
      var originalText = $button.text()

      $button.prop("disabled", true).text(ultraInstinct.strings.validating)

      $.ajax({
        url: ultraInstinct.ajaxUrl,
        type: "POST",
        data: {
          action: "ultra_instinct_validate_key",
          nonce: ultraInstinct.nonce,
          api_key: apiKey,
        },
        success: (response) => {
          if (response.success) {
            showNotice(response.data.message, "success")
            $("#platform-api-key").val("")
            updateConnectionStatus("connected")

            // Refresh page after 2 seconds
            setTimeout(() => {
              location.reload()
            }, 2000)
          } else {
            showNotice(response.data.message, "error")
            $("#platform-api-key").focus()
          }
        },
        error: () => {
          showNotice("An error occurred while validating the API key.", "error")
        },
        complete: () => {
          $button.prop("disabled", false).text(originalText)
        },
      })
    })

    // Disconnect agent
    $(".disconnect-agent").on("click", function () {
      if (!confirm(ultraInstinct.strings.confirmDisconnectAgent)) {
        return
      }

      var $button = $(this)
      var originalText = $button.text()
      var agentId = $button.data("agent-id")

      $button.prop("disabled", true).text(ultraInstinct.strings.disconnecting)

      $.ajax({
        url: ultraInstinct.ajaxUrl,
        type: "POST",
        data: {
          action: "ultra_instinct_disconnect_agent",
          nonce: ultraInstinct.nonce,
          agent_id: agentId,
        },
        success: (response) => {
          if (response.success) {
            showNotice(response.data.message, "success")
            $button.closest("tr").fadeOut()
          } else {
            showNotice(response.data.message, "error")
          }
        },
        error: () => {
          showNotice("An error occurred while disconnecting the agent.", "error")
        },
        complete: () => {
          $button.prop("disabled", false).text(originalText)
        },
      })
    })

    // Copy API key to clipboard
    $("#copy-key").on("click", () => {
      var apiKey = $("#api-key-value").val()

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard
          .writeText(apiKey)
          .then(() => {
            showNotice(ultraInstinct.strings.copySuccess, "success")
            animateCopyButton()
          })
          .catch(() => {
            fallbackCopyTextToClipboard(apiKey)
          })
      } else {
        fallbackCopyTextToClipboard(apiKey)
      }
    })

    // Helper functions
    function updateConnectionStatus(status) {
      var $indicator = $(".status-indicator")
      var $statusText = $(".status-text")

      $indicator.removeClass("status-connected status-disconnected status-testing")
      $indicator.addClass("status-" + status)

      var statusTexts = {
        connected: "Connected",
        disconnected: "Disconnected",
        testing: "Testing...",
      }

      $statusText.text(statusTexts[status] || "Unknown")
    }

    function animateCopyButton() {
      var $button = $("#copy-key")
      var originalText = $button.text()

      $button.text("Copied!").addClass("button-success")

      setTimeout(() => {
        $button.text(originalText).removeClass("button-success")
      }, 2000)
    }

    function fallbackCopyTextToClipboard(text) {
      var textArea = document.createElement("textarea")
      textArea.value = text
      textArea.style.top = "0"
      textArea.style.left = "0"
      textArea.style.position = "fixed"

      document.body.appendChild(textArea)
      textArea.focus()
      textArea.select()

      try {
        var successful = document.execCommand("copy")
        if (successful) {
          showNotice(ultraInstinct.strings.copySuccess, "success")
          animateCopyButton()
        } else {
          showNotice(ultraInstinct.strings.copyError, "error")
        }
      } catch (err) {
        showNotice(ultraInstinct.strings.copyError, "error")
      }

      document.body.removeChild(textArea)
    }

    function showNotice(message, type) {
      // Remove existing notices
      $(".ultra-instinct-notice").remove()

      var noticeClass = "notice-" + type
      var notice = $(
        '<div class="notice ' + noticeClass + ' ultra-instinct-notice is-dismissible"><p>' + message + "</p></div>",
      )

      $(".ultra-instinct-wrap h1").after(notice)

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        notice.fadeOut(() => {
          notice.remove()
        })
      }, 5000)
    }
  })
})(window.jQuery)
