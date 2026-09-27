<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\RemoteImageDownloader;
use App\Support\DownloadedImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RemoteImageDownloaderTest extends TestCase
{
    private function download(string $url): DownloadedImage
    {
        return app(RemoteImageDownloader::class)->download($url);
    }

    private function assertRejected(string $url, string $langKey): void
    {
        try {
            $this->download($url);
            $this->fail("Expected download of {$url} to be rejected with {$langKey}");
        } catch (ValidationException $e) {
            $errors = $e->errors()['photo_url'] ?? [];
            $this->assertSame([__("app.{$langKey}")], $errors);
        }
    }

    // ── happy path ───────────────────────────────────────────────────────────

    public function test_downloads_jpeg_image_and_sniffs_mime(): void
    {
        Http::preventStrayRequests();
        $bytes = (string) UploadedFile::fake()->image('p.jpg')->getContent();
        Http::fake(['https://93.184.216.34/photo.jpg' => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg'])]);

        $image = $this->download('https://93.184.216.34/photo.jpg');

        $this->assertSame('image/jpeg', $image->mimeType);
        $this->assertStringEndsWith('.jpg', $image->fileName);
        $image->discard();
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent') && $request->hasHeader('Accept'));
    }

    public function test_downloads_png_image_and_sniffs_mime(): void
    {
        Http::preventStrayRequests();
        $bytes = (string) UploadedFile::fake()->image('p.png')->getContent();
        Http::fake(['https://93.184.216.34/photo.png' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);

        $image = $this->download('https://93.184.216.34/photo.png');

        $this->assertSame('image/png', $image->mimeType);
        $this->assertStringEndsWith('.png', $image->fileName);
        $image->discard();
    }

    public function test_downloads_webp_image_and_sniffs_mime(): void
    {
        Http::preventStrayRequests();
        $bytes = (string) UploadedFile::fake()->image('p.webp')->getContent();
        Http::fake(['https://93.184.216.34/photo.webp' => Http::response($bytes, 200, ['Content-Type' => 'image/webp'])]);

        $image = $this->download('https://93.184.216.34/photo.webp');

        $this->assertSame('image/webp', $image->mimeType);
        $this->assertStringEndsWith('.webp', $image->fileName);
        $image->discard();
    }

    // ── failure: not allowed, no HTTP sent ──────────────────────────────────

    public function test_rejects_loopback_ipv4(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('http://127.0.0.1/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_private_ipv4(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('http://10.0.0.5/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_link_local_metadata_ip(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('http://169.254.169.254/latest', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_loopback_ipv6(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('http://[::1]/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_ipv4_mapped_loopback_ipv6(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('http://[::ffff:127.0.0.1]/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_nat64_embedded_loopback(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('http://[64:ff9b::7f00:1]/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_localhost_hostname(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('http://localhost/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_ftp_scheme(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('ftp://93.184.216.34/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_file_scheme(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('file:///etc/passwd', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_disallowed_port(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('https://93.184.216.34:8080/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    public function test_rejects_userinfo_in_url(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('https://user:pw@93.184.216.34/a.jpg', 'photo_url_not_allowed');
        Http::assertNothingSent();
    }

    // ── failure: redirects ──────────────────────────────────────────────────

    public function test_redirect_to_disallowed_ip_is_rejected(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/start.jpg' => Http::response('', 302, ['Location' => 'http://127.0.0.1/x.jpg']),
        ]);

        $this->assertRejected('https://93.184.216.34/start.jpg', 'photo_url_not_allowed');
    }

    public function test_redirect_chain_exceeding_limit_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/r0.jpg' => Http::response('', 302, ['Location' => 'https://93.184.216.34/r1.jpg']),
            'https://93.184.216.34/r1.jpg' => Http::response('', 302, ['Location' => 'https://93.184.216.34/r2.jpg']),
            'https://93.184.216.34/r2.jpg' => Http::response('', 302, ['Location' => 'https://93.184.216.34/r3.jpg']),
            'https://93.184.216.34/r3.jpg' => Http::response('', 302, ['Location' => 'https://93.184.216.34/r4.jpg']),
        ]);

        $this->assertRejected('https://93.184.216.34/r0.jpg', 'photo_url_download_failed');
    }

    public function test_single_redirect_to_public_ip_succeeds(): void
    {
        Http::preventStrayRequests();
        $bytes = (string) UploadedFile::fake()->image('p.jpg')->getContent();
        Http::fake([
            'https://93.184.216.34/start.jpg' => Http::response('', 302, ['Location' => 'https://93.184.216.34/final.jpg']),
            'https://93.184.216.34/final.jpg' => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $image = $this->download('https://93.184.216.34/start.jpg');

        $this->assertSame('image/jpeg', $image->mimeType);
        $image->discard();
    }

    // ── failure: transport / status ─────────────────────────────────────────

    public function test_not_found_status_fails_as_download_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/missing.jpg' => Http::response('', 404)]);

        $this->assertRejected('https://93.184.216.34/missing.jpg', 'photo_url_download_failed');
    }

    public function test_connection_exception_fails_as_download_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => fn () => throw new ConnectionException('Connection timed out')]);

        $this->assertRejected('https://93.184.216.34/a.jpg', 'photo_url_download_failed');
    }

    public function test_unresolvable_host_fails_as_download_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->assertRejected('https://no-such-host.invalid/a.jpg', 'photo_url_download_failed');
        Http::assertNothingSent();
    }

    // ── failure: content sniffing and size ──────────────────────────────────

    public function test_html_body_served_as_jpeg_fails_as_unsupported_type(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/fake.jpg' => Http::response('<html><body>not an image</body></html>', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->assertRejected('https://93.184.216.34/fake.jpg', 'photo_url_unsupported_type');
    }

    public function test_body_over_size_cap_fails_as_too_large(): void
    {
        $bytes = (string) UploadedFile::fake()->image('big.png', 500, 500)->getContent();
        $maxBytes = strlen($bytes) - 1;
        config(['media-library.max_file_size' => $maxBytes]);
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/big.png' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);

        try {
            $this->download('https://93.184.216.34/big.png');
            $this->fail('Expected download to be rejected as too large');
        } catch (ValidationException $e) {
            $expected = __('app.photo_url_too_large', ['max' => intdiv($maxBytes, 1024 * 1024)]);
            $this->assertSame([$expected], $e->errors()['photo_url'] ?? []);
        }
    }

    public function test_tmp_file_is_removed_on_download_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/missing.jpg' => Http::response('', 404)]);
        $before = glob(sys_get_temp_dir().'/rimg*') ?: [];

        $this->assertRejected('https://93.184.216.34/missing.jpg', 'photo_url_download_failed');

        $after = glob(sys_get_temp_dir().'/rimg*') ?: [];
        $this->assertCount(count($before), $after);
    }
}
