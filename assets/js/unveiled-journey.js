(function () {
  "use strict";

  const config = window.UNVEILED_FORM_CONFIG || {};
  const form = document.getElementById("unveiled-signup");
  const button = document.getElementById("signup-button");
  const status = document.getElementById("form-status");
  const firstName = document.getElementById("first-name");
  const email = document.getElementById("email-address");
  const consent = document.getElementById("email-consent");

  if (!form || !button || !status || !firstName || !email || !consent) return;

  button.disabled = true;

  function setStatus(message, state) {
    status.textContent = message;
    status.className = "form-status" + (state ? " is-" + state : "");
  }

  function addHidden(name, value) {
    if (!name || value === undefined || value === null) return;
    let input = Array.from(form.elements).find((field) => field.name === name);
    if (!input) {
      input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      form.appendChild(input);
    }
    if (input.type === "hidden") input.value = String(value);
  }

  function addCampaignFields() {
    const query = new URLSearchParams(window.location.search);
    ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"].forEach((key) => {
      addHidden(key, query.get(key) || "");
    });
    addHidden("journey", config.journeyTag || "7-day-unveiled");
    addHidden("source_url", window.location.href);
    addHidden("consent_version", "2026-08-unveiled-v1");
    addHidden("started_ms", Date.now());
    addHidden("website", "");
  }

  function configureExistingSite() {
    form.action = new URL(config.existingFormAction || "/book/subscribe.php", window.location.origin).toString();
    form.method = "post";
    firstName.name = "first_name";
    email.name = "email";
    consent.name = "consent";
    consent.value = "yes";
    addCampaignFields();
  }

  function validate() {
    [firstName, email, consent].forEach((field) => field.setCustomValidity(""));
    if (!firstName.value.trim()) firstName.setCustomValidity("Please enter your first name.");
    if (!email.validity.valid) email.setCustomValidity("Please enter a valid email address.");
    if (!consent.checked) consent.setCustomValidity("Please agree before joining the journey.");
    return form.reportValidity();
  }

  async function submitExistingSite() {
    const response = await fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      credentials: "same-origin",
      headers: { "Accept": "application/json" }
    });
    let result = {};
    try { result = await response.json(); } catch (error) { result = {}; }
    if (!response.ok || !result.ok) {
      throw new Error(result.message || "Signup could not be completed. Please try again.");
    }
    window.location.assign(config.successUrl || "/unveiled/confirmed.html");
  }

  form.addEventListener("submit", async function (event) {
    if (!validate()) {
      event.preventDefault();
      setStatus("Please complete all three fields.", "error");
      return;
    }

    button.disabled = true;
    button.textContent = "Opening the journey…";
    setStatus("Submitting securely. Please wait…", "ready");

    event.preventDefault();

    try {
      await submitExistingSite();
    } catch (error) {
      button.disabled = false;
      button.textContent = "Send Me Day One →";
      setStatus(error.message || "Signup could not be completed. Please try again.", "error");
    }
  });

  try {
    configureExistingSite();
    button.disabled = false;
    setStatus("Ready. We will email you a private confirmation link.", "ready");
  } catch (error) {
    console.error("Project Unveiled form setup:", error);
    button.disabled = true;
    setStatus("Signup connection is temporarily unavailable.", "error");
    const link = document.createElement("a");
    link.href = config.fallbackSignupUrl || "/book/";
    link.textContent = "Open the reader-list signup";
    link.style.display = "inline-block";
    link.style.marginTop = ".35rem";
    status.appendChild(document.createElement("br"));
    status.appendChild(link);
  }
})();
