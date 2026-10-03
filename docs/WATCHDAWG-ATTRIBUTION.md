# Watch-Dawg AI / Bobsome1 attribution

The compact footer is present on the restored Project Unveiled homepage, the
Bobsome1 services page, and the Trust-Worthy public command center. It links to
https://watchdawgai.com/ and https://bobsome1.com/ using ordinary text links.
The original generated 2172 × 724 badge is served locally and lazy-loaded.
There is no protection, certification, or monitoring claim.

The shared plain-text Journey footer adds the same neutral attribution while
preserving the sender address, consent explanation, and unsubscribe link.
No email is sent by this patch. The consent-confirmation message, privacy
contacts, checkout, client projects, and manuscript are unchanged.

Evidence Lab has its own exact SHA-256 release contract. It has not been
modified by this patch; adding branding there requires a separately validated
release manifest. The neutral snippet can be reused in other owned project
footers after checking their release and layout boundaries.

## Narrow deployment after review

This is a draft change, not a deployment. Keep the current homepage restoration
and upload only the branding versions of these files:

- `index.html`
- `services/index.html`
- `truth/index.php`
- `book/unveiled-journey-lib.php`
- `assets/brand/watchdawg-bobsome1.png`
- `assets/css/watchdawg-attribution.css`

`deployment/public-files.txt` includes the two public assets for the normal
allowlisted deployment system. Do not deploy a whole unrelated checkout or
turn on email automation for this change. Back up the four existing production
files outside `public_html` before publishing; rollback restores those files
and removes only the two new assets if nothing else uses them.

Check both destination sites before promotional rollout. Verify the badge and
links at mobile width and by keyboard, check the home/reader/Chapter 1/timeline/
research/signup/privacy routes, and verify the protected owner route remains
protected. Existing live hosting and browser acceptance remain release steps;
source validation alone is not production proof.
