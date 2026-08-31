<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoTrainingVideo extends Model
{
    protected $fillable = ['topic', 'description', 'youtube_url', 'created_by'];

    public function youtubeVideoId(): ?string
    {
        $parts = parse_url($this->youtube_url);
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            return explode('/', $path)[0] ?: null;
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            if (! empty($query['v'])) {
                return (string) $query['v'];
            }

            $segments = explode('/', $path);
            if (in_array($segments[0] ?? '', ['embed', 'shorts', 'live'], true)) {
                return $segments[1] ?? null;
            }
        }

        return null;
    }

    public function youtubeThumbnailUrl(): ?string
    {
        $videoId = $this->youtubeVideoId();

        return $videoId ? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg" : null;
    }

    public function youtubeEmbedUrl(): ?string
    {
        $videoId = $this->youtubeVideoId();

        return $videoId ? "https://www.youtube-nocookie.com/embed/{$videoId}" : null;
    }
}
