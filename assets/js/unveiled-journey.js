(function () {
  "use strict";

  const config = window.UNVEILED_FORM_CONFIG || {};
  const form = document.getElementById("unveiled-signup");
  const button = document.getElementById("signup-button");
  const status = document.getElementById("form-status");
  const firstName = document.getElementById("first-name");
  const email = document.getElementById("email-address");
  const consent = document.getElementById("email-consent");

  if (!form || !button || !status) return;

  button.disabled = true;

  function setStatus(message, state) {
    status.textContent = message;
    status.className = "form-status" + (state ? " is-" + state : "");
  }

  function addHidden(name, value) {
    if (!name || value === undefined || value === null || value === "") return;
    const existing = Array.from(form.elements).find((field) => field.name === name);
    if (existing) {
      if (existing.type === "hidden") existing.value = String(value);
      return;
    }
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = String(value);
    form.appendChild(input);
  }

  function addCampaignFields() {
    const query = new URLSearchParams(window.location.search);
    ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term"].forEach((key) => {
      addHidden(key, query.get(key));
    });
    addHidden("journey", config.journeyTag || "7-day-unveiled");
    addHidden("source_page", window.location.pathname);
    addHidden("referrer", document.referrer ? new URL(document.referrer).hostname : "direct");
  }

  function configureKit() {
    if (!config.kitFormAction || config.kitFormAction.includes("PASTE_")) {
      throw new Error("The Kit form action has not been added yet.");
    }
    form.action = config.kitFormAction;
    form.method = "post";
    firstName.name = "fields[first_name]";
    email.name = "email_address";
    consent.name = "consent";
    addHidden("redirect", config.successUrl);
    addCampaignFields();
  }

  function scoreForm(candidate) {
    if (!candidate.querySelector('input[type="email"]')) return -1;
    const text = (candidate.textContent || "").toLowerCase();
    let score = 1;
    if (text.includes("reader list")) score += 4;
    if (text.includes("unsubscribe")) score += 2;
    if (text.includes("project unveiled")) score += 1;
    return score;
  }

  async function configureExistingSite() {
    const sourceUrl = new URL(config.existingFormPage || "/book/", window.location.origin);
    const response = await fetch(sourceUrl.toString(), { credentials: "same-origin" });
    if (!response.ok) throw new Error("The existing reader-list form could not be loaded.");

    const html = await response.text();
    const page = new DOMParser().parseFromString(html, "text/html");
    const candidates = Array.from(page.querySelectorAll("form")).sort((a, b) => scoreForm(b) - scoreForm(a));
    const sourceForm = candidates.find((candidate) => scoreForm(candidate) > 0);
    if (!sourceForm) throw new Error("No existing reader-list form was found.");

    const rawAction = sourceForm.getAttribute("action");
    if (!rawAction) throw new Error("The existing form depends on page-specific code and needs a direct endpoint before launch.");

    form.action = new URL(rawAction, sourceUrl).toString();
    form.method = (sourceForm.getAttribute("method") || "post").toLowerCase();
    if (sourceForm.enctype) form.enctype = sourceForm.enctype;

    const sourceEmail = sourceForm.querySelector('input[type="email"]');
    const sourceCheckbox = sourceForm.querySelector('input[type="checkbox"]');
    const sourceTextFields = Array.from(sourceForm.querySelectorAll('input[type="text"], input:not([type])'));
    const sourceFirstName = sourceTextFields.find((field) => {
      const marker = ((field.name || "") + " " + (field.id || "") + " " + (field.autocomplete || "")).toLowerCase();
      return /first|given|name/.test(marker) && !/website|url|company/.test(marker);
    }) || sourceTextFields.find((field) => !/website|url|company/.test((field.name || "").toLowerCase()));

    if (sourceEmail && sourceEmail.name) email.name = sourceEmail.name;
    if (sourceFirstName && sourceFirstName.name) firstName.name = sourceFirstName.name;
    if (sourceCheckbox && sourceCheckbox.name) {
      consent.name = sourceCheckbox.name;
      consent.value = sourceCheckbox.value || "yes";
    }

    Array.from(sourceForm.elements).forEach((field) => {
      if (!field.name || field === sourceEmail || field === sourceFirstName || field === sourceCheckbox) return;
      const marker = ((field.name || "") + " " + (field.id || "") + " " + (field.autocomplete || "")).toLowerCase();
      if (field.type === "hidden") addHidden(field.name, field.value);
      if (/website|honeypot|company|url/.test(marker)) addHidden(field.name, "");
    });

    addCampaignFields();
  }

  function validate() {
    [firstName, email, consent].forEach((field) => field.setCustomValidity(""));
    if (!firstName.value.trim()) firstName.setCustomValidity("Please enter your first name.");
    if (!email.validity.valid) email.setCustomValidity("Please enter a valid email address.");
    if (!consent.checked) consent.setCustomValidity("Please agree before joining the journey.");
    return form.reportValidity();
  }

  form.addEventListener("submit", function (event) {
    if (!validate()) {
      event.preventDefault();
      setStatus("Please complete all three fields.", "error");
      return;
    }
    button.disabled = true;
    button.textContent = "Opening the journey…";
    setStatus("Submitting securely. Please wait…", "ready");
  });

  (async function initialize() {
    try {
      if (config.mode === "kit") configureKit();
      else await configureExistingSite();
      button.disabled = false;
      setStatus("Ready. Your information is sent through the secure Project Unveiled signup system.", "ready");
    } catch (error) {
      console.error("Project Unveiled form setup:", error);
      button.disabled = true;
      setStatus("Signup connection is awaiting final setup. Please use the reader-list form on the main book page for now.", "error");
      const link = document.createElement("a");
      link.href = config.fallbackSignupUrl || "/book/";
      link.textContent = "Open the current signup form";
      link.style.display = "inline-block";
      link.style.marginTop = ".35rem";
      status.appendChild(document.createElement("br"));
      status.appendChild(link);
    }
  })();
})();
