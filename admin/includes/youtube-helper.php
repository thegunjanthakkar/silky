<?php
/**
 * YouTube Video Helper Functions
 * Reusable functions for YouTube video ID extraction and validation
 */

/**
 * Extract YouTube video ID from various URL formats
 * 
 * @param string $url YouTube URL
 * @return string|null Video ID or null if invalid
 */
function extractYouTubeVideoId($url) {
    if (empty($url)) {
        return null;
    }
    
    $url = trim($url);
    
    // Supported URL patterns
    $patterns = [
        '/(?:youtube\.com\/watch\?v=)([a-zA-Z0-9_-]{11})/',      // Standard watch URL
        '/(?:youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',        // Embed URL
        '/(?:youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/',       // Shorts URL
        '/(?:youtu\.be\/)([a-zA-Z0-9_-]{11})/',                  // Shortened URL
        '/(?:youtube\.com\/v\/)([a-zA-Z0-9_-]{11})/'             // Old embed URL
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

/**
 * Validate YouTube video ID format
 * YouTube video IDs are exactly 11 characters: letters, numbers, hyphens, underscores
 * 
 * @param string $videoId Video ID to validate
 * @return bool True if valid, false otherwise
 */
function isValidYouTubeVideoId($videoId) {
    if (empty($videoId)) {
        return false;
    }
    
    return preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId) === 1;
}

/**
 * Check if a URL is a valid YouTube URL and extract video ID
 * 
 * @param string $url URL to check
 * @return array ['valid' => bool, 'video_id' => string|null, 'error' => string|null]
 */
function validateYouTubeUrl($url) {
    $result = [
        'valid' => false,
        'video_id' => null,
        'error' => null
    ];
    
    if (empty($url)) {
        $result['error'] = 'URL is empty';
        return $result;
    }
    
    $videoId = extractYouTubeVideoId($url);
    
    if ($videoId === null) {
        $result['error'] = 'Invalid YouTube URL format';
        return $result;
    }
    
    if (!isValidYouTubeVideoId($videoId)) {
        $result['error'] = 'Invalid video ID format';
        return $result;
    }
    
    $result['valid'] = true;
    $result['video_id'] = $videoId;
    
    return $result;
}

/**
 * Get YouTube embed URL from video ID
 * 
 * @param string $videoId YouTube video ID
 * @return string|null Embed URL or null if invalid
 */
function getYouTubeEmbedUrl($videoId) {
    if (!isValidYouTubeVideoId($videoId)) {
        return null;
    }
    
    return "https://www.youtube.com/embed/" . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8');
}

/**
 * Get YouTube thumbnail URL from video ID
 * 
 * @param string $videoId YouTube video ID
 * @param string $quality Thumbnail quality: 'default', 'hq', 'mq', 'sd', 'maxres'
 * @return string|null Thumbnail URL or null if invalid
 */
function getYouTubeThumbnailUrl($videoId, $quality = 'hq') {
    if (!isValidYouTubeVideoId($videoId)) {
        return null;
    }
    
    $qualityMap = [
        'default' => 'default',      // 120x90
        'mq' => 'mqdefault',         // 320x180
        'hq' => 'hqdefault',         // 480x360
        'sd' => 'sddefault',         // 640x480
        'maxres' => 'maxresdefault'  // 1280x720
    ];
    
    $qualityKey = isset($qualityMap[$quality]) ? $qualityMap[$quality] : 'hqdefault';
    
    return "https://img.youtube.com/vi/" . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8') . "/" . $qualityKey . ".jpg";
}

/**
 * Get YouTube watch URL from video ID
 * 
 * @param string $videoId YouTube video ID
 * @return string|null Watch URL or null if invalid
 */
function getYouTubeWatchUrl($videoId) {
    if (!isValidYouTubeVideoId($videoId)) {
        return null;
    }
    
    return "https://www.youtube.com/watch?v=" . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate YouTube embed HTML code
 * 
 * @param string $videoId YouTube video ID
 * @param int $width Width in pixels (default: 560)
 * @param int $height Height in pixels (default: 315)
 * @param array $options Additional iframe options
 * @return string|null HTML iframe code or null if invalid
 */
function getYouTubeEmbedHtml($videoId, $width = 560, $height = 315, $options = []) {
    if (!isValidYouTubeVideoId($videoId)) {
        return null;
    }
    
    $embedUrl = getYouTubeEmbedUrl($videoId);
    
    $defaultOptions = [
        'frameborder' => '0',
        'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
        'allowfullscreen' => true
    ];
    
    $options = array_merge($defaultOptions, $options);
    
    $attributes = '';
    foreach ($options as $key => $value) {
        if ($value === true) {
            $attributes .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        } else {
            $attributes .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
    }
    
    return sprintf(
        '<iframe width="%d" height="%d" src="%s"%s></iframe>',
        intval($width),
        intval($height),
        $embedUrl,
        $attributes
    );
}

/**
 * Generate responsive YouTube embed HTML (using Bootstrap ratio)
 * 
 * @param string $videoId YouTube video ID
 * @param string $ratio Aspect ratio: '16x9', '4x3', '21x9', '1x1'
 * @return string|null HTML code or null if invalid
 */
function getYouTubeResponsiveEmbed($videoId, $ratio = '16x9') {
    if (!isValidYouTubeVideoId($videoId)) {
        return null;
    }
    
    $embedUrl = getYouTubeEmbedUrl($videoId);
    
    return sprintf(
        '<div class="ratio ratio-%s"><iframe src="%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>',
        htmlspecialchars($ratio, ENT_QUOTES, 'UTF-8'),
        $embedUrl
    );
}

// Example usage (commented out):
/*
// Extract video ID from URL
$url = "https://www.youtube.com/watch?v=dQw4w9WgXcQ";
$videoId = extractYouTubeVideoId($url);
echo "Video ID: " . $videoId . "\n";

// Validate video ID
if (isValidYouTubeVideoId($videoId)) {
    echo "Valid video ID!\n";
}

// Get embed URL
$embedUrl = getYouTubeEmbedUrl($videoId);
echo "Embed URL: " . $embedUrl . "\n";

// Get thumbnail URL
$thumbnailUrl = getYouTubeThumbnailUrl($videoId, 'hq');
echo "Thumbnail: " . $thumbnailUrl . "\n";

// Generate embed HTML
$embedHtml = getYouTubeEmbedHtml($videoId);
echo $embedHtml;

// Validate URL with detailed response
$validation = validateYouTubeUrl($url);
if ($validation['valid']) {
    echo "Valid! Video ID: " . $validation['video_id'];
} else {
    echo "Invalid: " . $validation['error'];
}
*/
?>
