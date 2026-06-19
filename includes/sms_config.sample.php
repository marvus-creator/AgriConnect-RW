<?php
/**
 * Copy to "sms_config.php" to send REAL SMS via Africa's Talking.
 * Without it, SMS runs in simulation mode (logged to sms_log, not sent).
 *
 * 1. Create an account at https://africastalking.com
 * 2. For testing use the sandbox: username "sandbox" + your sandbox API key,
 *    and base_url https://api.sandbox.africastalking.com/version1/messaging
 * 3. For production: your live username, API key, and a registered sender ID.
 *
 * Keep sms_config.php out of git (already in .gitignore).
 */
return [
    'username'     => 'sandbox',
    'api_key'      => 'YOUR_AFRICASTALKING_API_KEY',
    'sender'       => '',  // registered sender ID / short code (optional in sandbox)
    'base_url'     => 'https://api.sandbox.africastalking.com/version1/messaging',
    'country_code' => '250',
];
