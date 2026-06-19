<?php
/**
 * Copy to "momo_config.php" to enable LIVE MTN MoMo sandbox payments.
 * Without it, MoMo runs in simulation mode (payments/payouts auto-succeed).
 *
 * HOW TO GET THESE VALUES
 * 1. Sign up at https://momodeveloper.mtn.com and subscribe to the
 *    "Collections" and "Disbursements" products. Each gives a Primary Key.
 * 2. Put those two keys below as collection_subkey / disbursement_subkey.
 * 3. Run the provisioning helper to create an API user + key for each:
 *       php momo_provision.php
 *    Paste the api_user (UUID) and api_key values it prints in below.
 *
 * Keep momo_config.php out of git (already in .gitignore).
 */
return [
    'base_url'             => 'https://sandbox.momodeveloper.mtn.com',
    'environment'          => 'sandbox',   // 'sandbox' | 'mtnrwanda' (live)
    'currency'             => 'EUR',       // sandbox requires EUR; use 'RWF' when live in Rwanda
    'country_code'         => '250',       // Rwanda — used to normalise 07.. -> 2507..

    // Collections (buyers pay)
    'collection_subkey'    => 'YOUR_COLLECTIONS_PRIMARY_KEY',
    'collection_apiuser'   => '',          // UUID from momo_provision.php
    'collection_apikey'    => '',          // from momo_provision.php

    // Disbursements (farmer/driver payouts)
    'disbursement_subkey'  => 'YOUR_DISBURSEMENTS_PRIMARY_KEY',
    'disbursement_apiuser' => '',
    'disbursement_apikey'  => '',
];
