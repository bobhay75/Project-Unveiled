/*
 * Project Unveiled signup connection.
 *
 * existing-site mode (default): discovers the working reader-list form on
 * /book/ and submits this page to the same first-party endpoint.
 *
 * kit mode: replace PASTE_KIT_FORM_ACTION_HERE with the public form action
 * copied from Kit, then set mode to "kit".
 */
window.UNVEILED_FORM_CONFIG = {
  mode: "existing-site",
  existingFormPage: "/book/",
  existingFormButtonText: "Join the reader list",
  kitFormAction: "PASTE_KIT_FORM_ACTION_HERE",
  successUrl: "https://bobsome1.com/unveiled/confirmed.html",
  journeyTag: "7-day-unveiled",
  fallbackSignupUrl: "https://bobsome1.com/book/"
};
