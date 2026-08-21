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

## Activate the self-hosted 7-Day Unveiled Journey

The journey uses the existing first-party subscriber storage and PHP mail service. No third-party newsletter account is required.

1. In cPanel File Manager, create this private text file:

   `/home/bobsocdw/site-private/project-unveiled/mailing-address.txt`

2. Put one valid physical postal address in that file. A registered PO box or private mailbox is acceptable. Do not commit a private home address to GitHub.
3. Set the file permissions to `0640`.
4. In cPanel Cron Jobs, add this command to run every 15 minutes:

   `*/15 * * * * /usr/local/bin/php -q /home/bobsocdw/public_html/book/unveiled-journey-cron.php >/dev/null 2>&1`

5. Submit a test at `https://bobsome1.com/unveiled/`, open the confirmation email, click the private link, and confirm that Day One arrives within an hour.
6. Test the unsubscribe link before promoting the journey publicly.

The signup endpoint refuses new journey signups until `mailing-address.txt` exists. The cron runner also stops safely when that file is missing.

## Files that should not remain publicly downloadable

- Hosting backup ZIPs
- Dated HTML backup pages
- Installer scripts
- Error logs
- SQL exports
- Subscriber or analytics data
- Deployment notes and checksum files

The root `.htaccess` blocks common deployment artifacts as a second layer of protection, but sensitive files should still be removed from `public_html`.
