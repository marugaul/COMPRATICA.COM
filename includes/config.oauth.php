<?php
// public_html/includes/config.oauth.php
// IMPORTANTE: Usa constantes (sin comillas) definidas en config.php o config.local.php
return [
  'google' => [
    'id'     => GOOGLE_CLIENT_ID,
    'secret' => GOOGLE_CLIENT_SECRET,
  ],
  'facebook' => [
    'id'     => FACEBOOK_APP_ID,
    'secret' => FACEBOOK_APP_SECRET,
  ],
  'apple' => [
    // Para cuando activemos Apple Sign-In (ES256)
    'client_id' => 'com.tu.bundle.serviceid',
    // 'team_id'   => 'TEAMID123',
    // 'key_id'    => 'KEYID123',
    // 'private_key_pem' => "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  ],
];
