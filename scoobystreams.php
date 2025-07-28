<?php
// Get the channel ID from URL parameter
$channelId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$channelId) {
    die('Channel ID is required. Use: player.php?id=1');
}

// Fetch data.txt from GitHub repo
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

// Redirect to Telegram if it's a browser and not a known IPTV player
if ($is_browser && !$is_ip_tv_app) {
    header("Location: https://t.me/+o3xpHLNH5nNkOTJl");
        exit;
        }
        
// Replace with your GitHub repo URL
$dataContent = file_get_contents("https://raw.githubusercontent.com/scoobyff/test/main/sportsdata.txt", false, $context);

if (!$dataContent) {
    die('Failed to fetch channel data');
}

// Parse data.txt content
$lines = explode("\n", $dataContent);
$channels = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) continue; // Skip empty lines and comments
    
    $parts = explode('|', $line);
    if (count($parts) >= 3) {
        $channels[$parts[0]] = [
            'id' => $parts[0],
            'name' => $parts[1] ?? 'Unknown',
            'url' => $parts[2] ?? '',
            'kid' => $parts[3] ?? '',
            'key' => $parts[4] ?? '',
            'user_agent' => $parts[5] ?? '',
            'referer' => $parts[6] ?? 'https://www.jiotv.com/',
            'cookie' => $parts[7] ?? ''
        ];
    }
}

// Find the requested channel
if (!isset($channels[$channelId])) {
    die("Channel with ID '$channelId' not found");
}

$channel = $channels[$channelId];
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($channel['name']); ?></title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.6.0/shaka-player.ui.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.6.0/controls.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
      background: #000;
      height: 100vh;
      width: 100vw;
      overflow: hidden;
      font-family: system-ui, -apple-system, sans-serif;
    }
    .shaka-video-container {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
    }
    video {
      width: 100%;
      height: 100%;
      background: #000;
      object-fit: contain;
    }
    .shaka-spinner-container {
      display: none !important;
    }
  </style>
</head>
<body>

<div class="shaka-video-container" data-shaka-player>
  <video autoplay playsinline preload="metadata"></video>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  shaka.polyfill.installAll();

  if (!shaka.Player.isBrowserSupported()) {
    console.error('Browser not supported');
    return;
  }

  const video = document.querySelector('video');
  const player = new shaka.Player();
  
  await player.attach(video);

  const container = document.querySelector('.shaka-video-container');
  const ui = new shaka.ui.Overlay(player, container, video);
  ui.getControls();

  // Player configuration with DRM if available
  const config = {
    streaming: {
      bufferingGoal: 30,
      rebufferingGoal: 5,
      bufferBehind: 30,
      retryParameters: {
        timeout: 15000,
        maxAttempts: 3,
        baseDelay: 500,
        backoffFactor: 1.5
      },
      segmentRequestTimeout: 10000,
      useNativeHlsOnSafari: true
    },
    manifest: {
      retryParameters: {
        timeout: 10000,
        maxAttempts: 2
      }
    }
  };

  // Add DRM configuration if kid and key are provided
  <?php if (!empty($channel['kid']) && !empty($channel['key'])): ?>
  config.drm = {
    clearKeys: {
      "<?php echo $channel['kid']; ?>": "<?php echo $channel['key']; ?>"
    }
  };
  <?php endif; ?>

  player.configure(config);

  // Request filter for headers
  player.getNetworkingEngine().registerRequestFilter((type, request) => {
    request.headers['Referer'] = '<?php echo addslashes($channel['referer']); ?>';
    
    <?php if (!empty($channel['user_agent'])): ?>
    request.headers['User-Agent'] = "<?php echo addslashes($channel['user_agent']); ?>";
    <?php endif; ?>
    
    <?php if (!empty($channel['cookie'])): ?>
    request.headers['Cookie'] = "<?php echo addslashes($channel['cookie']); ?>";
    <?php endif; ?>
  });

  // Error handling
  player.addEventListener('error', (event) => {
    console.error('Shaka Player Error:', event.detail);
  });

  // Auto fullscreen on play
  video.addEventListener('play', () => {
    if (!document.fullscreenElement) {
      container.requestFullscreen().catch(() => {});
    }
  }, { once: true });

  // Load stream
  try {
    await player.load("<?php echo addslashes($channel['url']); ?>");
  } catch (error) {
    console.error('Load error:', error);
  }
});
</script>

</body>
</html>