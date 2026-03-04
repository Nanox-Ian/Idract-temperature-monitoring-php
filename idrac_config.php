<?php
// idrac_config.php - Configuration loader for iDRAC Monitor (hardened)

// -----------------------------
// Load environment variables from .env if present (local/dev only)
// -----------------------------
function loadEnv(): void {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and invalid lines
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Strip surrounding quotes if present
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Ignore empty keys
        if ($name === '') {
            continue;
        }

        // Export to PHP environments
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Load .env (if you have one locally). Not required in production (use real env vars).
loadEnv();

// -----------------------------
// Helper: required ENV fetcher
// -----------------------------
function env_required(string $key, ?string $hint = null): string {
    $val = getenv($key);
    if ($val === false || $val === '') {
        $msg = "Missing required environment variable: $key";
        if ($hint) {
            $msg .= " ($hint)";
        }
        // Fail fast without revealing sensitive info
        throw new RuntimeException($msg);
    }
    return $val;
}

// -----------------------------
// Helper: optional ENV fetcher
// -----------------------------
function env_optional(string $key, $default = null) {
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

// -----------------------------
// Configuration (from ENV only)
// -----------------------------
try {
    $CONFIG = [
        // iDRAC access (all required, no defaults)
        'idrac_url'   => env_required('IDRAC_URL', 'e.g., https://10.0.0.1'),
        'idrac_user'  => env_required('IDRAC_USER'),
        'idrac_pass'  => env_required('IDRAC_PASS'),

        // Email identity
        'email_from'       => env_optional('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'email_from_name'  => env_optional('MAIL_FROM_NAME', 'iDRAC Monitor'),

        // Multiple email recipients (comma-separated). Required for alerts.
        'email_to'         => env_required('MAIL_TO', 'comma-separated list, no spaces or with spaces OK'),

        // Thresholds / intervals / timezone
        'warning_temp'     => (int) env_optional('WARNING_TEMP', 25),
        'critical_temp'    => (int) env_optional('CRITICAL_TEMP', 30),
        'check_interval'   => (int) env_optional('CHECK_INTERVAL', 60),
        'timezone'         => env_optional('APP_TIMEZONE', 'UTC'),

        // Email transport (SMTP or other)
        'transport'        => env_optional('MAIL_TRANSPORT', 'smtp'),
        'smtp_host'        => env_optional('MAIL_HOST', ''),
        'smtp_port'        => (int) env_optional('MAIL_PORT', 25),
        'smtp_secure'      => env_optional('MAIL_ENCRYPTION', ''), // '', 'tls', or 'ssl'
        'smtp_user'        => env_optional('MAIL_USERNAME', ''),
        'smtp_pass'        => env_optional('MAIL_PASSWORD', ''),
        'smtp_timeout'     => (int) env_optional('MAIL_TIMEOUT', 20),

        // Additional email settings
        'smtp_debug'       => (int) env_optional('MAIL_DEBUG', 0),

        // Enable SMTP auth automatically if username is present
        'smtp_auth'        => (bool) (env_optional('MAIL_USERNAME', '') !== ''),
    ];

    // Apply timezone safely
    if (!@date_default_timezone_set($CONFIG['timezone'])) {
        date_default_timezone_set('UTC'); // fallback
    }
} catch (RuntimeException $e) {
    // Do not echo secrets; provide minimal actionable error
    http_response_code(500);
    // Log securely if you have a logger; avoid printing to response in production
    echo "Configuration error: " . htmlspecialchars($e->getMessage());
    exit(1);
}

// Export config
return $CONFIG;
