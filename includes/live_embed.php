<?php
/**
 * Parsea un URL de live y devuelve plataforma, ícono, color y URL de embed.
 * Plataformas con embed: YouTube, Facebook.
 * Plataformas solo enlace: Instagram, TikTok.
 */
function parseLiveUrl(string $url): array
{
    $url = trim($url);
    $platform = null;
    $embedUrl = null;
    $icon  = 'fas fa-video';
    $color = '#ef4444';

    if (preg_match('%(?:youtube\.com/(?:watch\?(?:.*&)?v=|live/)|youtu\.be/)([a-zA-Z0-9_-]{11})%', $url, $m)) {
        $platform = 'YouTube';
        $embedUrl = 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1&rel=0';
        $icon  = 'fab fa-youtube';
        $color = '#FF0000';

    } elseif (preg_match('%facebook\.com%', $url)) {
        $platform = 'Facebook';
        $embedUrl = 'https://www.facebook.com/plugins/video.php?href=' . urlencode($url)
                  . '&show_text=0&autoplay=1&allowfullscreen=1';
        $icon  = 'fab fa-facebook';
        $color = '#1877F2';

    } elseif (preg_match('%instagram\.com%', $url)) {
        $platform = 'Instagram';
        $embedUrl = null;   // Instagram Live no permite iframe
        $icon  = 'fab fa-instagram';
        $color = '#E4405F';

    } elseif (preg_match('%tiktok\.com%', $url)) {
        $platform = 'TikTok';
        $embedUrl = null;   // TikTok Live no permite iframe
        $icon  = 'fab fa-tiktok';
        $color = '#010101';
    }

    return [
        'platform' => $platform,
        'embedUrl' => $embedUrl,
        'icon'     => $icon,
        'color'    => $color,
        'url'      => $url,
    ];
}
