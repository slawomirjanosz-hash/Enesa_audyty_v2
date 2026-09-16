<?php

namespace App\Support;

class CylinderVideoLink
{
    /** Resolve links without fetching URLs or accepting user-provided iframe markup. */
    public static function parse(string $url): ?array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\s\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || preg_match('/\.(local|localhost|internal|test)$/', $host)) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);
        if (in_array($host, ['youtu.be', 'www.youtu.be', 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com'], true)) {
            $id = null;
            if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
                $id = trim($path, '/');
            } elseif ($path === '/watch') {
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~D', $path, $match)) {
                $id = $match[1];
            }
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
                return null;
            }

            return ['provider' => 'YouTube', 'embed' => 'https://www.youtube-nocookie.com/embed/'.$id];
        }
        if ($host === 'drive.google.com') {
            $id = null;
            if (preg_match('~^/file/d/([A-Za-z0-9_-]{10,200})(?:/(?:view|preview))?/?$~D', $path, $match)) {
                $id = $match[1];
            } elseif (in_array($path, ['/open', '/uc'], true)) {
                $id = $query['id'] ?? null;
            }
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{10,200}$/D', $id)) {
                return null;
            }
            $key = $query['resourcekey'] ?? null;
            if ($key !== null && (! is_string($key) || ! preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $key))) {
                return null;
            }

            return ['provider' => 'Dysk Google', 'embed' => 'https://drive.google.com/file/d/'.$id.'/preview'.($key ? '?resourcekey='.rawurlencode($key) : '')];
        }

        return ['provider' => $host, 'embed' => null];
    }
}
