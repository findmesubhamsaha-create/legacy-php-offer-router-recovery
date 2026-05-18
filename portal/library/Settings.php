<?php
if (!defined('BASEPATH'))
    define('BASEPATH', dirname(dirname(__FILE__)));

// Load DB credentials from .env (project root, one level above portal/).
// Constants DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME are unchanged so all
// callers (Database.php, cron.php, etc.) require no modification.
if (!defined('DB_HOST')) {
    (static function () {
        $env_file = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($env_file)) {
            die(
                'Configuration error: .env file not found.' . PHP_EOL .
                'Copy .env.example to .env in the project root and fill in your credentials.' . PHP_EOL .
                'Expected path: ' . $env_file . PHP_EOL
            );
        }

        $env = [];
        foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            // Skip blank lines and comments
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Strip surrounding single or double quotes
            if (strlen($val) >= 2 &&
                (($val[0] === '"' && $val[-1] === '"') ||
                 ($val[0] === "'" && $val[-1] === "'"))) {
                $val = substr($val, 1, -1);
            }
            $env[$key] = $val;
        }

        // Fall back to the same values that were hardcoded before this change,
        // so a partial .env never silently breaks a running environment.
        define('DB_HOST',        $env['DB_HOST']     ?? 'localhost');
        define('DB_USERNAME',    $env['DB_USERNAME'] ?? 'root');
        define('DB_PASSWORD',    $env['DB_PASSWORD'] ?? '');
        define('DB_NAME',        $env['DB_NAME']     ?? 'efbhalvbhdsurl');
        define('DB_USER_TABLE',  'tbl_user');
        define('DB_OFFER_TABLE', 'tbl_offer_url');
    })();
}
?>
