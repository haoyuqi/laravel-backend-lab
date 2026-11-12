<?php

// Explicit first-install operation; never called by a container entrypoint.
$path = $argv[1] ?? '/deployment.env';
$handle = fopen($path, 'r+');
if ($handle === false || ! flock($handle, LOCK_EX)) {
    throw new RuntimeException('Cannot lock the deployment configuration.');
}
$content = stream_get_contents($handle);
foreach (['APP_KEY', 'DB_PASSWORD', 'REVERB_APP_KEY', 'REVERB_APP_SECRET'] as $key) {
    $pattern = '/^'.preg_quote($key, '/').'=[ \t]*$/m';
    if (preg_match($pattern, $content)) {
        $value = $key === 'APP_KEY' ? 'base64:'.base64_encode(random_bytes(32)) : bin2hex(random_bytes(24));
        $content = preg_replace($pattern, $key.'='.$value, $content);
    }
}
rewind($handle);
fwrite($handle, $content);
ftruncate($handle, strlen($content));
flock($handle, LOCK_UN);
fclose($handle);
echo "Empty installation keys initialized; existing values preserved.\n";
