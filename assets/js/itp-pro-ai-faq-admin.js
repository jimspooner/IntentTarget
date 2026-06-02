/*!
 * IntentTarget Pro — AI FAQ Generator (admin metabox controller)
 *
 * Vanilla-JS controller bound to #itp-pro-ai-faq-metabox-root. Talks to the Pro REST endpoints
 * via wp.apiFetch (so the wp_rest nonce + cookies are handled by Core for us). No build step.
 *
 * Cost-safety contract: every "Generate" click goes through window.confirm() with explicit
 * cost-warning copy supplied from PHP via wp_localize_script() so the user is reminded that
 * their AI provider account is what gets billed.
 */
(function () {
  "use strict";

  if (typeof window === "undefined" || !window.itpProAiFaq) {
    return;
  }
  var BOOT = window.itpProAiFaq;

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  function getApiFetch() {
    if (typeof window.wp !== "undefined" && window.wp.apiFetch) {
      return window.wp.apiFetch;
    }
    return null;
  }

  function setProgress(root, isVisible, label) {
    var progress = root.querySelector(".itp-faq-progress");
    var text = root.querySelector(".itp-faq-progress-text");
    var btn = root.querySelector(".itp-faq-generate-btn");
    var removeBtn = root.querySelector(".itp-faq-remove-btn");
    if (progress) {
      if (isVisible) {
        progress.removeAttribute("hidden");
      } else {
        progress.setAttribute("hidden", "hidden");
      }
    }
    if (text && label) {
      text.textContent = label;
    }
    if (btn) {
      btn.disabled = !!isVisible;
    }
    if (removeBtn) {
      removeBtn.disabled = !!isVisible;
    }
  }

  function showError(root, message) {
    var existing = root.querySelector(".itp-faq-error");
    if (existing) {
      existing.remove();
    }
    var div = document.createElement("div");
    div.className = "itp-faq-error";
    div.setAttribute("role", "alert");
    div.textContent = message;
    root.appendChild(div);
    // Auto-dismiss after 12s so the metabox stays tidy.
    window.setTimeout(function () {
      if (div && div.parentNode) {
        div.remove();
      }
    }, 12000);
  }

  function clearError(root) {
    var existing = root.querySelector(".itp-faq-error");
    if (existing) {
      existing.remove();
    }
  }

  function replaceSummary(root, html, hasFaqs) {
    var resultEl = root.querySelector(".itp-faq-result");
    var actionsEl = root.querySelector(".itp-faq-result-actions");
    var btn = root.querySelector(".itp-faq-generate-btn");

    if (resultEl) {
      resultEl.innerHTML = html || "";
      resultEl.setAttribute("data-has-faqs", hasFaqs ? "1" : "0");
    }
    if (actionsEl) {
      if (hasFaqs) {
        actionsEl.removeAttribute("hidden");
      } else {
        actionsEl.setAttribute("hidden", "hidden");
      }
    }
    if (btn) {
      btn.textContent = hasFaqs
        ? BOOT.i18n && BOOT.i18n.regenerate
          ? BOOT.i18n.regenerate
          : "Regenerate FAQs"
        : BOOT.i18n && BOOT.i18n.generate
          ? BOOT.i18n.generate
          : "Scan page & generate FAQs";
    }
  }

  function callRest(path, method, body) {
    var apiFetch = getApiFetch();
    if (!apiFetch) {
      return Promise.reject(
        new Error("wp.apiFetch is not available on this screen."),
      );
    }
    var nonce =
      BOOT && BOOT.nonce
        ? BOOT.nonce
        : typeof window.wpApiSettings !== "undefined" &&
            window.wpApiSettings.nonce
          ? window.wpApiSettings.nonce
          : "";
    return apiFetch({
      path: path,
      method: method || "POST",
      data: body || {},
      headers: nonce ? { "X-WP-Nonce": nonce } : {},
    });
  }

  function handleGenerate(root) {
    clearError(root);
    var postId = parseInt(root.getAttribute("data-post-id"), 10);
    if (!postId) {
      showError(root, "Missing post ID.");
      return;
    }

    var confirmCopy = (BOOT.i18n && BOOT.i18n.confirm) || "Continue?";
    if (!window.confirm(confirmCopy)) {
      return;
    }

    var workingCopy = (BOOT.i18n && BOOT.i18n.working) || "Working…";
    setProgress(root, true, workingCopy);

    callRest("intenttargetpro/v1/generate-faqs", "POST", { post_id: postId })
      .then(function (response) {
        setProgress(root, false);
        if (!response || response.success !== true) {
          var msg =
            response && response.message
              ? response.message
              : BOOT.i18n.errorGeneric || "Unknown error";
          showError(root, msg);
          return;
        }
        var hasFaqs = !!(response.faqs && response.faqs.length);
        replaceSummary(root, response.summary_html || "", hasFaqs);
      })
      .catch(function (err) {
        setProgress(root, false);
        var msg =
          err && err.message
            ? err.message
            : BOOT.i18n.errorGeneric || "Unknown error";
        if (err && err.code) {
          msg = "[" + err.code + "] " + msg;
        }
        showError(root, msg);
      });
  }

  function handleRemove(root) {
    clearError(root);
    var postId = parseInt(root.getAttribute("data-post-id"), 10);
    if (!postId) {
      showError(root, "Missing post ID.");
      return;
    }
    var confirmCopy = (BOOT.i18n && BOOT.i18n.confirmRemove) || "Remove FAQs?";
    if (!window.confirm(confirmCopy)) {
      return;
    }

    var removingCopy = (BOOT.i18n && BOOT.i18n.removing) || "Removing…";
    setProgress(root, true, removingCopy);

    callRest("intenttargetpro/v1/remove-faqs", "POST", { post_id: postId })
      .then(function (response) {
        setProgress(root, false);
        if (!response || response.success !== true) {
          showError(root, BOOT.i18n.errorGeneric || "Unknown error");
          return;
        }
        replaceSummary(root, "", false);
      })
      .catch(function (err) {
        setProgress(root, false);
        var msg =
          err && err.message
            ? err.message
            : BOOT.i18n.errorGeneric || "Unknown error";
        showError(root, msg);
      });
  }

  function init() {
    var root = document.getElementById("itp-pro-ai-faq-metabox-root");
    if (!root) {
      return;
    }

    // Use event delegation so the controls can be re-rendered without losing handlers.
    root.addEventListener("click", function (ev) {
      var target = ev.target;
      if (!target) {
        return;
      }
      if (target.closest(".itp-faq-generate-btn")) {
        ev.preventDefault();
        handleGenerate(root);
        return;
      }
      if (target.closest(".itp-faq-remove-btn")) {
        ev.preventDefault();
        handleRemove(root);
        return;
      }
    });
  }

  ready(init);
})();
