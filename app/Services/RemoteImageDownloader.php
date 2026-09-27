<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DownloadedImage;
use finfo;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

final readonly class RemoteImageDownloader
{
    private const array MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
    ];

    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    private const array REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function download(string $url): DownloadedImage
    {
        $uri = new Uri($url);
        $maxRedirects = Config::integer('services.remote_images.max_redirects');

        for ($redirect = 0; true; $redirect++) {
            if ($redirect > $maxRedirects) {
                $this->reject($uri->getHost(), 'redirect limit exceeded', 'photo_url_download_failed');
            }

            ['host' => $host, 'port' => $port, 'ip' => $ip] = $this->assertAllowedUrl($uri);

            $result = $this->fetch($uri, $host, $port, $ip);

            if ($result instanceof DownloadedImage) {
                return $result;
            }

            $uri = $result;
        }
    }

    /**
     * @return array{host: string, port: int, ip: string|null}
     */
    private function assertAllowedUrl(UriInterface $uri): array
    {
        $scheme = strtolower($uri->getScheme());

        if (! in_array($scheme, ['http', 'https'], true)) {
            $this->reject($uri->getHost(), "scheme {$scheme} not allowed", 'photo_url_not_allowed');
        }

        if ($uri->getUserInfo() !== '') {
            $this->reject($uri->getHost(), 'userinfo present', 'photo_url_not_allowed');
        }

        $host = $uri->getHost();

        if ($host === '') {
            $this->reject($host, 'missing host', 'photo_url_not_allowed');
        }

        $port = $uri->getPort();

        if ($port !== null && ! in_array($port, [80, 443], true)) {
            $this->reject($host, "port {$port} not allowed", 'photo_url_not_allowed');
        }

        $resolvedPort = $port ?? ($scheme === 'https' ? 443 : 80);

        $bareHost = trim($host, '[]');
        $isIpLiteral = filter_var($bareHost, FILTER_VALIDATE_IP) !== false;

        $ips = $this->resolvePublicIps($host, $bareHost, $isIpLiteral);

        foreach ($ips as $candidate) {
            if (! $this->isPublicIp($candidate)) {
                $this->reject($host, "ip {$candidate} not public", 'photo_url_not_allowed');
            }
        }

        return [
            'host' => $host,
            'port' => $resolvedPort,
            'ip' => $isIpLiteral ? null : $ips[0],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePublicIps(string $host, string $bareHost, bool $isIpLiteral): array
    {
        if ($isIpLiteral) {
            return [$bareHost];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        /** @var list<string> $ips */
        $ips = [];

        if ($records !== false) {
            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $this->reject($host, 'DNS resolution failed', 'photo_url_download_failed');
        }

        return $ips;
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $packed = inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return $packed !== false;
        }

        // NAT64 (64:ff9b::/96, 64:ff9b:1::/48) and 6to4 (2002::/16) embed arbitrary addresses — deny regardless of payload.
        if (substr($packed, 0, 12) === substr((string) inet_pton('64:ff9b::'), 0, 12)) {
            return false;
        }

        if (substr($packed, 0, 6) === substr((string) inet_pton('64:ff9b:1::'), 0, 6)) {
            return false;
        }

        if (substr($packed, 0, 2) === substr((string) inet_pton('2002::'), 0, 2)) {
            return false;
        }

        return true;
    }

    private function fetch(UriInterface $uri, string $host, int $port, ?string $ip): DownloadedImage|UriInterface
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'rimg');

        if ($tmpPath === false) {
            $this->reject($host, 'tmp file creation failed', 'photo_url_download_failed');
        }

        $maxBytes = Config::integer('media-library.max_file_size');
        $tooLarge = false;

        $curlOptions = [];

        if ($ip !== null) {
            $formattedIp = str_contains($ip, ':') ? "[{$ip}]" : $ip;
            $curlOptions[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$formattedIp}"];
        }

        try {
            $response = Http::withoutRedirecting()
                ->connectTimeout(Config::integer('services.remote_images.connect_timeout'))
                ->timeout(Config::integer('services.remote_images.timeout'))
                ->withHeaders([
                    'User-Agent' => Config::string('services.remote_images.user_agent'),
                    'Accept' => 'image/webp,image/png,image/jpeg;q=0.9,image/*;q=0.5',
                    'Accept-Language' => 'sk,en;q=0.8',
                    'Referer' => "{$uri->getScheme()}://{$host}/",
                ])
                ->sink($tmpPath)
                ->withOptions([
                    'protocols' => ['http', 'https'],
                    'curl' => $curlOptions,
                    'on_headers' => function (ResponseInterface $headersResponse) use ($maxBytes, &$tooLarge): void {
                        $length = $headersResponse->getHeaderLine('Content-Length');

                        if ($length !== '' && (int) $length > $maxBytes) {
                            $tooLarge = true;

                            throw new RuntimeException('remote image exceeds size cap (content-length)');
                        }
                    },
                    'progress' => function (int|float $downloadTotal, int|float $downloadedBytes) use ($maxBytes, &$tooLarge): void {
                        if ((int) $downloadedBytes > $maxBytes) {
                            $tooLarge = true;

                            throw new RuntimeException('remote image exceeds size cap (progress)');
                        }
                    },
                ])
                ->get((string) $uri);
        } catch (Throwable $e) {
            @unlink($tmpPath);

            if ($tooLarge) {
                $this->rejectTooLarge($host, $maxBytes);
            }

            $this->reject($host, $e->getMessage(), 'photo_url_download_failed');
        }

        if (in_array($response->status(), self::REDIRECT_STATUSES, true)) {
            @unlink($tmpPath);

            $location = $response->header('Location');

            if ($location === '') {
                $this->reject($host, 'redirect without Location header', 'photo_url_download_failed');
            }

            return UriResolver::resolve($uri, new Uri($location));
        }

        if (! $response->successful()) {
            @unlink($tmpPath);

            $this->reject($host, "unexpected status {$response->status()}", 'photo_url_download_failed');
        }

        $size = filesize($tmpPath);

        if ($size === false || $size > $maxBytes) {
            @unlink($tmpPath);

            $this->rejectTooLarge($host, $maxBytes);
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: null;

        if ($mimeType === null || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            @unlink($tmpPath);

            $this->reject($host, "unsupported mime {$mimeType}", 'photo_url_unsupported_type');
        }

        return new DownloadedImage(
            path: $tmpPath,
            fileName: $this->buildFileName($uri, $mimeType),
            mimeType: $mimeType,
        );
    }

    private function buildFileName(UriInterface $uri, string $mimeType): string
    {
        $name = Str::slug(pathinfo($uri->getPath(), PATHINFO_FILENAME)) ?: 'photo';
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? 'jpg';

        return "{$name}.{$extension}";
    }

    private function rejectTooLarge(string $host, int $maxBytes): never
    {
        logger()->info('remote_image.rejected', ['host' => $host, 'reason' => 'too large']);

        throw ValidationException::withMessages([
            'photo_url' => [__('app.photo_url_too_large', ['max' => intdiv($maxBytes, 1024 * 1024)])],
        ]);
    }

    private function reject(string $host, string $reason, string $key): never
    {
        logger()->info('remote_image.rejected', ['host' => $host, 'reason' => $reason]);

        throw ValidationException::withMessages([
            'photo_url' => [__("app.{$key}")],
        ]);
    }
}
