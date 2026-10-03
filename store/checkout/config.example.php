<?php
declare(strict_types=1);

/* TEMPLATE ONLY. Copy OUTSIDE public_html and Git, chmod 0600, then configure
 * BOBSOME1_COMMERCE_CONFIG with the absolute private config path in cPanel.
 * Never place live values in this file. PHP 8.1+, cURL, ZIP (ZipArchive), JSON, sessions required.
 * No checkout is enabled merely by deploying these files.
 */
return [
    'enabled' => false,
    'mode' => 'sandbox', // Exactly sandbox or live. Verify sandbox before live.
    'client_id' => '',
    'client_secret' => '',
    'merchant_id' => '', // PayPal merchant ID, NOT an email address.
    'private_dir' => '', // Existing absolute owner-only directory (0700).
    'asset_path' => '', // Complete bundle ZIP outside public_html and Git (0600).
    'asset_sha256' => '', // SHA-256 of the exact complete bundle ZIP.
    // Leave BOTH approval fields empty until Robert reviews and explicitly
    // approves these exact ZIP bytes. Packaging/test success is not approval.
    'owner_approved_sha256' => '', // Must match asset_sha256; never autofill.
    'owner_approved_at' => '', // Actual approval time: YYYY-MM-DDTHH:MM:SSZ (UTC).
];
