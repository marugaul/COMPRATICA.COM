<?php
/**
 * includes/bot_protection.php
 * Anti-bot / anti-spam helpers for email-based registration forms.
 *
 * Usage in a form page:
 *   require_once __DIR__ . '/includes/bot_protection.php';
 *   // On page load:
 *   $reg_token = bot_form_token();
 *   // In the HTML form:
 *   bot_form_hidden_fields($reg_token);
 *   // On POST / register:
 *   $bot_error = bot_check_registration(
 *       $_POST['email']    ?? '',
 *       $_POST['name']     ?? '',
 *       $_POST['_hp']      ?? '',   // honeypot
 *       $_POST['_ft']      ?? '',   // form token
 *       $_POST['phone']    ?? ''
 *   );
 *   if ($bot_error !== '') { $error = $bot_error; }
 */

// ── Blocked disposable / spam / bot-test email domains ───────────────────────
define('BOT_BLOCKED_DOMAINS', [
    'testform.xyz', 'mailinator.com', 'guerrillamail.com', 'guerrillamailblock.com',
    'sharklasers.com', 'spam4.me', 'yopmail.com', 'tempmail.com',
    'temp-mail.org', 'dispostable.com', 'trashmail.com', 'fakeinbox.com',
    'maildrop.cc', 'mailnull.com', 'discard.email', 'tempail.com',
    '10minutemail.com', 'throwaway.email', 'spamgourmet.com',
    'getairmail.com', 'filzmail.com', 'trashmail.at', 'trashmail.io',
    'trashmail.me', 'mytemp.email', 'tempr.email', 'discard.email',
    'mailnesia.com', 'spamgourmet.net', 'spamgourmet.org',
    'example.com', 'test.com', 'fake.com', 'noemail.com',
]);

/**
 * Generate a signed timing token: base64(timestamp|hmac)
 * The secret is derived from the site config so it doesn't need a
 * separate env variable.
 */
function bot_form_token(): string {
    $ts     = (string) time();
    $secret = defined('APP_KEY') ? APP_KEY : (defined('APP_SECRET') ? APP_SECRET : 'ct_bp_2025');
    $sig    = hash_hmac('sha256', $ts, $secret, false);
    return base64_encode($ts . '|' . $sig);
}

/**
 * Echo the hidden fields to embed in the form.
 *   _hp  = honeypot (must remain empty)
 *   _ft  = form token (signed timestamp)
 */
function bot_form_hidden_fields(string $token): void {
    echo '<!-- bot protection -->
<div style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;height:0;overflow:hidden;" aria-hidden="true">
  <label for="_hp_field">Sitio web (no completar)</label>
  <input type="text" id="_hp_field" name="_hp" value="" tabindex="-1" autocomplete="off" maxlength="0">
</div>
<input type="hidden" name="_ft" value="' . htmlspecialchars($token) . '">' . "\n";
}

/**
 * Returns error string if bot detected, empty string otherwise.
 *
 * @param string $email     Registrant email
 * @param string $name      Registrant name
 * @param string $honeypot  Value of _hp field (must be empty for real users)
 * @param string $ft_token  Value of _ft field (signed timing token)
 * @param string $phone     Phone (empty = not provided)
 */
function bot_check_registration(
    string $email,
    string $name,
    string $honeypot,
    string $ft_token,
    string $phone = ''
): string {

    // ── 1. Honeypot ──────────────────────────────────────────────────────────
    if ($honeypot !== '') {
        error_log('[bot_protection] Honeypot triggered: email=' . $email . ' name=' . $name);
        // Return silently-succeeding fake message so bots don't detect the block.
        // The caller should treat this as a bot and skip the INSERT.
        return '__BOT__';
    }

    // ── 2. Timing (bots submit < 2 seconds after page load) ──────────────────
    if ($ft_token !== '') {
        $decoded = base64_decode($ft_token);
        $parts   = explode('|', $decoded, 2);
        if (count($parts) === 2) {
            [$ts, $sig] = $parts;
            $secret = defined('APP_KEY') ? APP_KEY : (defined('APP_SECRET') ? APP_SECRET : 'ct_bp_2025');
            $expected = hash_hmac('sha256', $ts, $secret, false);
            if (hash_equals($expected, $sig)) {
                $elapsed = time() - (int) $ts;
                if ($elapsed < 2) {
                    return 'El registro fue demasiado rápido. Intentá de nuevo en un momento.';
                }
            }
        }
    }

    // ── 3. Blocked email domains ──────────────────────────────────────────────
    $atPos  = strrpos($email, '@');
    if ($atPos !== false) {
        $domain = strtolower(substr($email, $atPos + 1));
        if (in_array($domain, BOT_BLOCKED_DOMAINS, true)) {
            return 'No se puede registrar con ese dominio de correo. Usá tu email personal o corporativo.';
        }
    }

    // ── 4. Name heuristic — detect random-string bot names ───────────────────
    // E.g. "nkvtjylzpz", "ktlkiptpmw" — no spaces, all lowercase, < 20% vowels
    $stripped = preg_replace('/[^a-zA-Z]/', '', $name);
    if (strlen($stripped) >= 7 && strpos(trim($name), ' ') === false) {
        $lower   = strtolower($stripped);
        $vowels  = preg_match_all('/[aeiouáéíóúü]/u', $lower);
        $ratio   = $vowels / strlen($lower);
        if ($ratio < 0.18) {
            return 'El nombre ingresado no parece un nombre real. Por favor usá tu nombre completo.';
        }
    }

    return '';
}
