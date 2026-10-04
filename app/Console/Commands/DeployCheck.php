<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Run on the live server after deploying: php artisan deploy:check
 * Looks at the real setup and says, in plain words, what still needs fixing before visitors arrive.
 */
class DeployCheck extends Command
{
    protected $signature = 'deploy:check {--skip-http : Do not call the live website (use this when checking from a computer that cannot reach it)}';

    protected $description = 'Check that this server is set up safely for the public (run it after deploying)';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Deployment check for '.config('app.name').'</>');
        $this->newLine();

        $this->settings();
        $this->database();
        $this->files();

        if ($this->option('skip-http')) {
            $this->caution('Skipped the live website checks (--skip-http)', 'Run again without it once the site is online. The most important check there is whether /admin is password protected.');
        } else {
            $this->liveSite();
        }

        $this->newLine();
        if ($this->failures > 0) {
            $this->line("<fg=red;options=bold>NOT READY: {$this->failures} problem(s) to fix".($this->warnings ? ", {$this->warnings} warning(s)" : '').'.</>');
        } elseif ($this->warnings > 0) {
            $this->line("<fg=yellow;options=bold>Ready, with {$this->warnings} warning(s). Read them, then decide.</>");
        } else {
            $this->line('<fg=green;options=bold>All checks passed. You are ready for visitors.</>');
        }
        $this->newLine();

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ---------- groups of checks ----------

    private function settings(): void
    {
        $this->section('Settings (.env)');

        app()->environment('production')
            ? $this->pass('APP_ENV is "production"')
            : $this->problem('APP_ENV is "'.app()->environment().'", not "production"', 'Set APP_ENV=production in .env. Until then robots.txt tells Google to ignore your whole site.');

        config('app.debug') === false
            ? $this->pass('APP_DEBUG is off')
            : $this->problem('APP_DEBUG is on', 'Set APP_DEBUG=false in .env. With it on, visitors can see passwords and file paths when something breaks.');

        filled(config('app.key'))
            ? $this->pass('APP_KEY is set')
            : $this->problem('APP_KEY is empty', 'Run: php artisan key:generate --force');

        $url = (string) config('app.url');
        if (! str_starts_with($url, 'https://')) {
            $this->problem("APP_URL is \"{$url}\" (not https)", 'Set APP_URL=https://yourdomain.com in .env. It is used in the sitemap and in links.');
        } elseif (preg_match('#//(localhost|127\.0\.0\.1)#', $url)) {
            $this->problem("APP_URL is \"{$url}\" (a local address)", 'Set APP_URL to your real domain.');
        } else {
            $this->pass("APP_URL is {$url}");
        }

        config('session.secure') === true
            ? $this->pass('Session cookies are marked secure (https only)')
            : $this->problem('Session cookies are not marked secure', 'Set SESSION_SECURE_COOKIE=true in .env (your site must use https).');

        in_array(config('session.driver'), ['array', 'null'], true)
            ? $this->problem('SESSION_DRIVER is "'.config('session.driver').'"', 'Use SESSION_DRIVER=database. Forms (and their spam protection) need real sessions.')
            : $this->pass('Sessions are stored for real ('.config('session.driver').')');

        in_array(config('cache.default'), ['array', 'null'], true)
            ? $this->problem('CACHE_STORE is "'.config('cache.default').'"', 'Use CACHE_STORE=database. The rate limits that block spam live in the cache.')
            : $this->pass('Cache is stored for real ('.config('cache.default').')');

        $level = $this->logLevel();
        in_array($level, ['debug', 'info'], true)
            ? $this->caution("LOG_LEVEL is \"{$level}\"", 'Fine for testing. On the live site use LOG_LEVEL=warning so the log file does not fill up.')
            : $this->pass("Log level is \"{$level}\"");

        blank(config('trustedproxy.proxies'))
            ? $this->caution('TRUSTED_PROXIES is empty', 'Correct if visitors reach your server directly. If your host or Cloudflare sits in front, set it (see DEPLOYMENT.md), otherwise every visitor looks like the same person to the spam limits.')
            : $this->pass('TRUSTED_PROXIES is set');
    }

    private function database(): void
    {
        $this->section('Database');

        try {
            DB::connection()->getPdo();
            $this->pass('Connected to the database "'.DB::connection()->getDatabaseName().'"');
        } catch (Throwable $e) {
            $this->problem('Cannot connect to the database', 'Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');

            return;
        }

        $missing = array_values(array_filter(
            ['cities', 'cuisines', 'amenities', 'restaurants', 'restaurant_images', 'cuisine_restaurant', 'amenity_restaurant', 'opening_hours', 'reviews', 'submissions', 'contact_messages', 'sessions', 'cache'],
            fn (string $table) => ! Schema::hasTable($table),
        ));

        if ($missing) {
            $this->problem('Missing tables: '.implode(', ', $missing), 'Run: php artisan migrate --force');

            return;
        }

        $migrator = app('migrator');
        $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
        $pending = array_diff($files, $migrator->getRepository()->getRan());

        $pending
            ? $this->problem(count($pending).' migration(s) have not been run', 'Run: php artisan migrate --force')
            : $this->pass('All migrations have been run');

        $published = DB::table('restaurants')->where('status', 'published')->count();
        $published > 0
            ? $this->pass("{$published} published restaurant(s)")
            : $this->caution('No published restaurants yet', 'Visitors will see an empty directory. Add some in /admin before announcing the site.');
    }

    private function files(): void
    {
        $this->section('Files');

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            is_writable($path)
                ? $this->pass("{$label} is writable")
                : $this->problem("{$label} is not writable", "Give the web server permission to write to {$label} (see DEPLOYMENT.md, \"Permissions\").");
        }

        file_exists(public_path('storage'))
            ? $this->pass('Uploaded photos are public (public/storage exists)')
            : $this->problem('public/storage is missing', 'Run: php artisan storage:link');

        file_exists(public_path('build/manifest.json'))
            ? $this->pass('Styles and scripts are built (public/build)')
            : $this->problem('public/build is missing', 'Run: npm run build (on your computer, then upload public/build).');

        file_exists(public_path('hot'))
            ? $this->problem('public/hot exists', 'This file makes the site look for the development server, so styles will not load. Delete public/hot.')
            : $this->pass('No development leftovers (public/hot)');

        file_exists(public_path('robots.txt'))
            ? $this->problem('A fixed public/robots.txt exists', 'Delete it: the website generates its own robots.txt, and a file would hide it.')
            : $this->pass('robots.txt is generated by the website');
    }

    private function liveSite(): void
    {
        $this->section('The live website ('.config('app.url').')');

        $base = rtrim((string) config('app.url'), '/');

        // The most important check: is the admin panel really locked from the outside?
        try {
            $status = Http::timeout(15)->withoutRedirecting()->get("{$base}/admin")->status();

            match (true) {
                $status === 401 => $this->pass('/admin asks for a password (HTTP Basic Auth is working)'),
                $status === 200 => $this->problem('/admin is OPEN to everyone', 'Anyone who finds the address can edit or delete your whole directory. Set up HTTP Basic Auth now (DEPLOYMENT.md, step "Protect /admin").'),
                default => $this->caution("/admin answered with status {$status}", 'Expected 401 (password required). Check your Basic Auth setup.'),
            };
        } catch (Throwable $e) {
            $this->caution('Could not reach '.$base, 'Is the site online yet? Is APP_URL correct? Message: '.$e->getMessage());

            return;
        }

        try {
            $robots = Http::timeout(15)->get("{$base}/robots.txt")->body();
            str_contains($robots, 'Sitemap:') && ! preg_match('/^Disallow:\s*\/\s*$/m', $robots)
                ? $this->pass('robots.txt welcomes search engines and points to the sitemap')
                : $this->problem('robots.txt blocks search engines', 'This usually means APP_ENV is not "production". Google will not list your site.');

            Http::timeout(15)->get("{$base}/sitemap.xml")->successful()
                ? $this->pass('sitemap.xml is available')
                : $this->problem('sitemap.xml does not load', 'Open it in your browser and check the error.');

            $home = Http::timeout(15)->get($base);
            $home->successful() && $home->header('X-Content-Type-Options') === 'nosniff'
                ? $this->pass('The homepage loads and sends its security headers')
                : $this->problem('The homepage does not load properly (status '.$home->status().')', 'Open it in your browser and check storage/logs/laravel.log.');
        } catch (Throwable $e) {
            $this->caution('A live check could not finish', $e->getMessage());
        }
    }

    // ---------- helpers ----------

    private function logLevel(): string
    {
        $channel = config('logging.default');
        if ($channel === 'stack') {
            $channel = config('logging.channels.stack.channels')[0] ?? 'single';
        }

        return (string) config("logging.channels.{$channel}.level", 'debug');
    }

    private function section(string $title): void
    {
        $this->line("<options=bold>{$title}</>");
    }

    private function pass(string $message): void
    {
        $this->line("  <fg=green>PASS</>  {$message}");
    }

    private function problem(string $message, string $howToFix): void
    {
        $this->failures++;
        $this->line("  <fg=red;options=bold>FAIL</>  {$message}");
        $this->line("        <fg=gray>-> {$howToFix}</>");
    }

    // A warning: worth reading, but not a reason to stop
    private function caution(string $message, string $howToFix = ''): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow;options=bold>WARN</>  {$message}");
        if ($howToFix !== '') {
            $this->line("        <fg=gray>-> {$howToFix}</>");
        }
    }
}
