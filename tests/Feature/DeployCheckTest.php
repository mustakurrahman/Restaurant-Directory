<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeployCheckTest extends TestCase
{
    // Starts every test with an empty in-memory database (never touches MySQL)
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        // A pretend, correctly prepared public folder, so the result never depends on this computer's real files
        $this->publicDir = sys_get_temp_dir().'/deploycheck-'.uniqid();
        File::ensureDirectoryExists($this->publicDir.'/build');
        File::ensureDirectoryExists($this->publicDir.'/storage');
        File::put($this->publicDir.'/build/manifest.json', '{}');
        $this->app->usePublicPath($this->publicDir);

        // A correct production setup
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.url' => 'https://restaurantsdirectory.com',
            'session.secure' => true,
            'session.driver' => 'database',
            'cache.default' => 'database',
            'logging.default' => 'single',
            'logging.channels.single.level' => 'warning',
            'trustedproxy.proxies' => '*',
        ]);
        Restaurant::factory()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function check(array $options = ['--skip-http' => true])
    {
        return $this->artisan('deploy:check', $options);
    }

    public function test_a_correct_setup_passes(): void
    {
        $this->check()
            ->expectsOutputToContain('PASS  APP_ENV is "production"')
            ->expectsOutputToContain('PASS  APP_DEBUG is off')
            ->expectsOutputToContain('PASS  All migrations have been run')
            ->expectsOutputToContain('Ready, with 1 warning(s)') // only the "live site checks were skipped" warning
            ->assertExitCode(0);
    }

    #[DataProvider('brokenSettings')]
    public function test_each_unsafe_setting_fails_with_a_plain_explanation(callable $break, string $message): void
    {
        $break($this);

        $this->check()
            ->expectsOutputToContain($message)
            ->expectsOutputToContain('NOT READY')
            ->assertExitCode(1);
    }

    public static function brokenSettings(): array
    {
        return [
            'not production' => [fn ($t) => $t->app['env'] = 'local', 'APP_ENV is "local", not "production"'],
            'debug on' => [fn () => config(['app.debug' => true]), 'APP_DEBUG is on'],
            'no key' => [fn () => config(['app.key' => '']), 'APP_KEY is empty'],
            'http url' => [fn () => config(['app.url' => 'http://example.com']), 'not https'],
            'local url' => [fn () => config(['app.url' => 'https://localhost']), 'a local address'],
            'insecure cookies' => [fn () => config(['session.secure' => false]), 'Session cookies are not marked secure'],
            'array sessions' => [fn () => config(['session.driver' => 'array']), 'SESSION_DRIVER is "array"'],
            'array cache' => [fn () => config(['cache.default' => 'array']), 'CACHE_STORE is "array"'],
        ];
    }

    public function test_unfinished_files_are_reported(): void
    {
        File::deleteDirectory($this->publicDir.'/storage');
        File::delete($this->publicDir.'/build/manifest.json');
        File::put($this->publicDir.'/hot', 'http://localhost:5173');
        File::put($this->publicDir.'/robots.txt', 'User-agent: *');

        $this->check()
            ->expectsOutputToContain('public/storage is missing')
            ->expectsOutputToContain('public/build is missing')
            ->expectsOutputToContain('public/hot exists')
            ->expectsOutputToContain('A fixed public/robots.txt exists')
            ->assertExitCode(1);
    }

    public function test_warnings_do_not_stop_a_launch_but_are_shown(): void
    {
        config(['logging.channels.single.level' => 'debug', 'trustedproxy.proxies' => null]);
        Restaurant::query()->delete();

        $this->check()
            ->expectsOutputToContain('LOG_LEVEL is "debug"')
            ->expectsOutputToContain('TRUSTED_PROXIES is empty')
            ->expectsOutputToContain('No published restaurants yet')
            ->assertExitCode(0);
    }

    public function test_an_open_admin_is_a_failure(): void
    {
        Http::fake(['*/admin' => Http::response('<html>admin</html>', 200), '*' => Http::response('ok', 200)]);

        $this->check([])
            ->expectsOutputToContain('/admin is OPEN to everyone')
            ->assertExitCode(1);
    }

    public function test_a_password_protected_admin_passes_and_a_good_robots_file_passes(): void
    {
        Http::fake([
            '*/admin' => Http::response('Unauthorized', 401),
            '*/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\n\nSitemap: https://restaurantsdirectory.com/sitemap.xml\n", 200),
            '*/sitemap.xml' => Http::response('<urlset/>', 200),
            '*' => Http::response('home', 200, ['X-Content-Type-Options' => 'nosniff']),
        ]);

        $this->check([])
            ->expectsOutputToContain('/admin asks for a password')
            ->expectsOutputToContain('robots.txt welcomes search engines')
            ->expectsOutputToContain('sitemap.xml is available')
            ->expectsOutputToContain('All checks passed')
            ->assertExitCode(0);

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://restaurantsdirectory.com/admin');
        Http::assertNotSent(fn (ClientRequest $request) => $request->hasHeader('Authorization')); // it checks from the outside, with no password
    }

    public function test_a_robots_file_that_blocks_everything_is_a_failure(): void
    {
        Http::fake([
            '*/admin' => Http::response('Unauthorized', 401),
            '*/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200),
            '*' => Http::response('ok', 200, ['X-Content-Type-Options' => 'nosniff']),
        ]);

        $this->check([])->expectsOutputToContain('robots.txt blocks search engines')->assertExitCode(1);
    }

    public function test_an_unreachable_site_is_a_warning_not_a_crash(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('could not resolve host'));

        $this->check([])->expectsOutputToContain('Could not reach https://restaurantsdirectory.com')->assertExitCode(0);
    }

    public function test_an_unreachable_database_is_reported_clearly(): void
    {
        $original = config('database.default');
        config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '203.0.113.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => '', 'options' => [\PDO::ATTR_TIMEOUT => 1]]]);

        try {
            config(['database.default' => 'broken']);
            $this->check()->expectsOutputToContain('Cannot connect to the database')->assertExitCode(1);
        } finally {
            config(['database.default' => $original]); // PHPUnit's own clean-up needs the real test database back
        }
    }
}
