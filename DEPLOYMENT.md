# Safe Production Deployment

## Before deployment

1. Download a fresh `public_html` backup from cPanel.
2. Confirm the site is loading before making any change.
3. Upload the prepared deployment ZIP into `public_html`.
4. Extract it there and allow matching files to be overwritten.
5. Delete the uploaded ZIP from the server after extraction.

## Immediate checks

1. Open `https://bobsome1.com/` and confirm it remains on HTTPS without `www`.
2. Open the reader, Chapter 1, timeline, research page, and both question files.
3. Submit the signup form using an email address you control.
4. Use the welcome email's unsubscribe link and confirm it works.
5. Confirm `/owner/` still requests authentication.

## Files that should not remain publicly downloadable

- Hosting backup ZIPs
- Dated HTML backup pages
- Installer scripts
- Error logs
- SQL exports
- Subscriber or analytics data
- Deployment notes and checksum files

The root `.htaccess` blocks common deployment artifacts as a second layer of protection, but sensitive files should still be removed from `public_html`.
