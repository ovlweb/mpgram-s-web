<?php
/*
Copyright (c) 2022-2026 Arman Jussupgaliyev
*/
ini_set('error_reporting', E_ERROR);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

if (!function_exists('exceptions_error_handler')) {
    function exceptions_error_handler($severity, $message, $filename, $lineno) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}
set_error_handler('exceptions_error_handler');
require_once 'vendor/autoload.php';

function vi_cache_path($user, $icon, $size) {
    $dir = __DIR__.'/cache/verifyicons';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return null;
    }
    return $dir.'/'.sha1('v6|'.$user.'|'.$icon.'|'.$size).'.png';
}

function vi_resize($image, $size) {
    $size = (int)$size;
    $oldw = imagesx($image);
    $oldh = imagesy($image);
    $scale = min($size / max(1, $oldw), $size / max(1, $oldh));
    $w = max(1, (int)round($oldw * $scale));
    $h = max(1, (int)round($oldh * $scale));
    $temp = imagecreatetruecolor($size, $size);
    imagealphablending($temp, false);
    imagesavealpha($temp, true);
    $clear = imagecolorallocatealpha($temp, 0, 0, 0, 127);
    imagefill($temp, 0, 0, $clear);
    imagecopyresampled($temp, $image, (int)(($size - $w) / 2), (int)(($size - $h) / 2), 0, 0, $w, $h, $oldw, $oldh);
    return $temp;
}

function vi_fallback($size) {
    $size = (int)$size;
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $clear);
    imagealphablending($img, true);
    $blue = imagecolorallocate($img, 51, 144, 236);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledellipse($img, (int)($size / 2), (int)($size / 2), $size - 2, $size - 2, $blue);
    imagesetthickness($img, max(2, (int)round($size / 8)));
    imageline($img, (int)round($size * 0.28), (int)round($size * 0.53), (int)round($size * 0.43), (int)round($size * 0.68), $white);
    imageline($img, (int)round($size * 0.43), (int)round($size * 0.68), (int)round($size * 0.74), (int)round($size * 0.34), $white);
    return $img;
}

function vi_output_png($img, $cacheFile = null) {
    header('Content-Type: image/png');
    header('Cache-Control: private, max-age=2592000');
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    if ($cacheFile !== null) {
        @file_put_contents($cacheFile, $data);
    }
    echo $data;
    imagedestroy($img);
    die;
}

function vi_png_from_tgs($rawPath, $outPrefix, $size) {
    if (!defined('CONVERT_TGS_STICKERS') || !CONVERT_TGS_STICKERS || !defined('LOTTIE_DIR')) {
        return null;
    }
    $script = rtrim(LOTTIE_DIR, '/').'/lottie_to_png.sh';
    if (!is_file($script)) {
        return null;
    }
    $cmd = 'bash '.escapeshellarg($script)
        .' --output '.escapeshellarg($outPrefix)
        .' --width '.(int)$size
        .' --height '.(int)$size
        .' --quality 70 --threads 1 --fps 10 '
        .escapeshellarg($rawPath);
    $lines = [];
    $code = 0;
    @exec($cmd.(defined('WINDOWS') && WINDOWS ? '' : ' 2>&1'), $lines, $code);
    if ($code !== 0) {
        return null;
    }
    if (is_file($outPrefix.'.png')) {
        return $outPrefix.'.png';
    }
    if (is_dir($outPrefix) && is_file($outPrefix.'/000.png')) {
        @rename($outPrefix.'/000.png', $outPrefix.'.png');
        foreach (@scandir($outPrefix) ?: [] as $n) {
            if ($n == '.' || $n == '..') continue;
            @unlink($outPrefix.'/'.$n);
        }
        @rmdir($outPrefix);
        return is_file($outPrefix.'.png') ? $outPrefix.'.png' : null;
    }
    return null;
}

function vi_png_from_webm($rawPath, $outPath, $size) {
    if (!defined('CONVERT_WEBM_EMOJI_FRAMES') || !CONVERT_WEBM_EMOJI_FRAMES || !defined('FFMPEG_DIR')) {
        return null;
    }
    $ffmpeg = FFMPEG_DIR.'ffmpeg';
    if (FFMPEG_DIR === '') {
        $found = trim((string)@shell_exec('command -v ffmpeg'));
        if ($found !== '') {
            $ffmpeg = $found;
        }
    }
    if ($ffmpeg === '' || !@is_executable($ffmpeg)) {
        return null;
    }
    $cmd = escapeshellcmd($ffmpeg).' -y -i '.escapeshellarg($rawPath).' -frames:v 1 -vf '.escapeshellarg('scale='.(int)$size.':'.(int)$size.':force_original_aspect_ratio=decrease').' '.escapeshellarg($outPath).(defined('WINDOWS') && WINDOWS ? '' : ' 2>&1');
    @shell_exec($cmd);
    return is_file($outPath) ? $outPath : null;
}

function vi_prop_value($prop, $frame = 60) {
    if (!is_array($prop)) return $prop;
    if (!array_key_exists('k', $prop)) return $prop;
    $k = $prop['k'];
    if (!empty($prop['a']) && is_array($k) && isset($k[0]['t'])) {
        $pick = $k[0];
        foreach ($k as $kf) {
            if (($kf['t'] ?? 0) <= $frame) $pick = $kf;
            else break;
        }
        $v = $pick['s'] ?? ($pick['e'] ?? null);
        if (is_array($v) && count($v) == 1 && is_array($v[0]) && isset($v[0]['v'])) {
            return $v[0];
        }
        return $v;
    }
    return $k;
}

function vi_matrix_multiply($a, $b) {
    return [
        $a[0] * $b[0] + $a[2] * $b[1],
        $a[1] * $b[0] + $a[3] * $b[1],
        $a[0] * $b[2] + $a[2] * $b[3],
        $a[1] * $b[2] + $a[3] * $b[3],
        $a[0] * $b[4] + $a[2] * $b[5] + $a[4],
        $a[1] * $b[4] + $a[3] * $b[5] + $a[5],
    ];
}

function vi_transform_matrix($tr, $frame = 60) {
    $p = vi_prop_value($tr['p'] ?? ['k' => [0, 0]], $frame);
    $a = vi_prop_value($tr['a'] ?? ['k' => [0, 0]], $frame);
    $s = vi_prop_value($tr['s'] ?? ['k' => [100, 100]], $frame);
    $r = vi_prop_value($tr['r'] ?? ($tr['rz'] ?? ['k' => 0]), $frame);
    $px = (float)($p[0] ?? 0);
    $py = (float)($p[1] ?? 0);
    $ax = (float)($a[0] ?? 0);
    $ay = (float)($a[1] ?? 0);
    $sx = ((float)($s[0] ?? 100)) / 100;
    $sy = ((float)($s[1] ?? 100)) / 100;
    $rad = deg2rad((float)$r);
    $cos = cos($rad);
    $sin = sin($rad);
    $m = [$sx, 0, 0, $sy, -$ax * $sx, -$ay * $sy];
    $rot = [$cos, $sin, -$sin, $cos, 0, 0];
    $pos = [1, 0, 0, 1, $px, $py];
    return vi_matrix_multiply($pos, vi_matrix_multiply($rot, $m));
}

function vi_point($m, $p) {
    $x = (float)$p[0];
    $y = (float)$p[1];
    return [$m[0] * $x + $m[2] * $y + $m[4], $m[1] * $x + $m[3] * $y + $m[5]];
}

function vi_style_color($style, $fallback = [0.2, 0.55, 0.95, 1]) {
    if (!is_array($style)) return $fallback;
    if (($style['ty'] ?? '') == 'gf') {
        $g = vi_prop_value($style['g']['k'] ?? null);
        if (is_array($g) && count($g) >= 4) {
            $colors = [];
            for ($i = 0; $i + 3 < count($g); $i += 4) {
                $colors[] = [$g[$i + 1], $g[$i + 2], $g[$i + 3], 1];
            }
            return $colors ? $colors[(int)floor(count($colors) / 2)] : $fallback;
        }
    }
    return vi_prop_value($style['c'] ?? null) ?: $fallback;
}

function vi_find_style($items, $frame = 60) {
    $style = ['fill' => null, 'stroke' => null, 'strokeWidth' => 0];
    foreach ($items as $item) {
        $ty = $item['ty'] ?? '';
        if ($ty == 'fl' || $ty == 'gf') {
            $style['fill'] = vi_style_color($item);
        } elseif ($ty == 'st') {
            $style['stroke'] = vi_style_color($item, [0.8, 0.85, 0.92, 1]);
            $style['strokeWidth'] = (float)(vi_prop_value($item['w'] ?? ['k' => 2], $frame) ?: 2);
        }
    }
    return $style;
}

function vi_path_points($shape, $matrix, $frame = 60) {
    $ks = vi_prop_value($shape['ks'] ?? null, $frame);
    if (!is_array($ks) || empty($ks['v']) || !is_array($ks['v'])) return [];
    $v = $ks['v'];
    $i = $ks['i'] ?? [];
    $o = $ks['o'] ?? [];
    $closed = !empty($ks['c']);
    $pts = [];
    $n = count($v);
    for ($idx = 0; $idx < $n; $idx++) {
        $next = $idx + 1;
        if ($next >= $n) {
            if (!$closed) break;
            $next = 0;
        }
        $p0 = $v[$idx];
        $p3 = $v[$next];
        $p1 = [$p0[0] + ($o[$idx][0] ?? 0), $p0[1] + ($o[$idx][1] ?? 0)];
        $p2 = [$p3[0] + ($i[$next][0] ?? 0), $p3[1] + ($i[$next][1] ?? 0)];
        if ($idx == 0) $pts[] = vi_point($matrix, $p0);
        for ($step = 1; $step <= 8; $step++) {
            $t = $step / 8;
            $mt = 1 - $t;
            $x = $mt*$mt*$mt*$p0[0] + 3*$mt*$mt*$t*$p1[0] + 3*$mt*$t*$t*$p2[0] + $t*$t*$t*$p3[0];
            $y = $mt*$mt*$mt*$p0[1] + 3*$mt*$mt*$t*$p1[1] + 3*$mt*$t*$t*$p2[1] + $t*$t*$t*$p3[1];
            $pts[] = vi_point($matrix, [$x, $y]);
        }
    }
    return $pts;
}

function vi_ellipse_points($shape, $matrix, $frame = 60) {
    $pos = vi_prop_value($shape['p'] ?? ['k' => [0, 0]], $frame);
    $size = vi_prop_value($shape['s'] ?? ['k' => [0, 0]], $frame);
    $cx = (float)($pos[0] ?? 0);
    $cy = (float)($pos[1] ?? 0);
    $rx = abs((float)($size[0] ?? 0)) / 2;
    $ry = abs((float)($size[1] ?? 0)) / 2;
    if ($rx <= 0 || $ry <= 0) return [];
    $pts = [];
    for ($step = 0; $step < 40; $step++) {
        $a = ($step / 40) * M_PI * 2;
        $pts[] = vi_point($matrix, [$cx + cos($a) * $rx, $cy + sin($a) * $ry]);
    }
    return $pts;
}

function vi_rect_points($shape, $matrix, $frame = 60) {
    $pos = vi_prop_value($shape['p'] ?? ['k' => [0, 0]], $frame);
    $size = vi_prop_value($shape['s'] ?? ['k' => [0, 0]], $frame);
    $cx = (float)($pos[0] ?? 0);
    $cy = (float)($pos[1] ?? 0);
    $w = abs((float)($size[0] ?? 0));
    $h = abs((float)($size[1] ?? 0));
    if ($w <= 0 || $h <= 0) return [];
    $x = $cx - $w / 2;
    $y = $cy - $h / 2;
    $r = min((float)(vi_prop_value($shape['r'] ?? ['k' => 0], $frame) ?: 0), $w / 2, $h / 2);
    if ($r <= 0) {
        return [
            vi_point($matrix, [$x, $y]),
            vi_point($matrix, [$x + $w, $y]),
            vi_point($matrix, [$x + $w, $y + $h]),
            vi_point($matrix, [$x, $y + $h]),
        ];
    }
    $pts = [];
    $corners = [
        [$x + $w - $r, $y + $r, -90, 0],
        [$x + $w - $r, $y + $h - $r, 0, 90],
        [$x + $r, $y + $h - $r, 90, 180],
        [$x + $r, $y + $r, 180, 270],
    ];
    foreach ($corners as $c) {
        for ($step = 0; $step <= 5; $step++) {
            $deg = $c[2] + (($c[3] - $c[2]) * ($step / 5));
            $pts[] = vi_point($matrix, [$c[0] + cos(deg2rad($deg)) * $r, $c[1] + sin(deg2rad($deg)) * $r]);
        }
    }
    return $pts;
}

function vi_star_points($shape, $matrix, $frame = 60) {
    $pos = vi_prop_value($shape['p'] ?? ['k' => [0, 0]], $frame);
    $cx = (float)($pos[0] ?? 0);
    $cy = (float)($pos[1] ?? 0);
    $count = max(3, (int)round((float)(vi_prop_value($shape['pt'] ?? ['k' => 5], $frame) ?: 5)));
    $outer = abs((float)(vi_prop_value($shape['or'] ?? ['k' => 0], $frame) ?: 0));
    $inner = abs((float)(vi_prop_value($shape['ir'] ?? ['k' => ($outer / 2)], $frame) ?: ($outer / 2)));
    $rot = deg2rad((float)(vi_prop_value($shape['r'] ?? ['k' => 0], $frame) ?: 0) - 90);
    if ($outer <= 0) return [];
    $pts = [];
    $steps = (($shape['sy'] ?? 1) == 2) ? $count : $count * 2;
    for ($idx = 0; $idx < $steps; $idx++) {
        $radius = ($steps == $count || $idx % 2 == 0) ? $outer : $inner;
        $a = $rot + ($idx / $steps) * M_PI * 2;
        $pts[] = vi_point($matrix, [$cx + cos($a) * $radius, $cy + sin($a) * $radius]);
    }
    return $pts;
}

function vi_collect_shapes($items, $matrix, $parentStyle, &$draws, $frame = 60) {
    $style = vi_find_style($items, $frame);
    if ($style['fill'] === null) $style['fill'] = $parentStyle['fill'] ?? null;
    if ($style['stroke'] === null) {
        $style['stroke'] = $parentStyle['stroke'] ?? null;
        $style['strokeWidth'] = $parentStyle['strokeWidth'] ?? 0;
    }
    foreach ($items as $item) {
        if (($item['ty'] ?? '') == 'tr') {
            $matrix = vi_matrix_multiply($matrix, vi_transform_matrix($item, $frame));
        }
    }
    foreach ($items as $item) {
        $ty = $item['ty'] ?? '';
        if ($ty == 'gr') {
            vi_collect_shapes($item['it'] ?? [], $matrix, $style, $draws, $frame);
        } elseif ($ty == 'sh' || $ty == 'el' || $ty == 'rc' || $ty == 'sr') {
            if ($ty == 'el') {
                $pts = vi_ellipse_points($item, $matrix, $frame);
            } elseif ($ty == 'rc') {
                $pts = vi_rect_points($item, $matrix, $frame);
            } elseif ($ty == 'sr') {
                $pts = vi_star_points($item, $matrix, $frame);
            } else {
                $pts = vi_path_points($item, $matrix, $frame);
            }
            if (count($pts) >= 2) {
                $draws[] = ['points' => $pts, 'fill' => $style['fill'], 'stroke' => $style['stroke'], 'strokeWidth' => $style['strokeWidth']];
            }
        }
    }
}

function vi_alloc_color($img, $rgba) {
    $r = max(0, min(255, (int)round(($rgba[0] ?? 0) * 255)));
    $g = max(0, min(255, (int)round(($rgba[1] ?? 0) * 255)));
    $b = max(0, min(255, (int)round(($rgba[2] ?? 0) * 255)));
    $a = max(0, min(127, (int)round(127 - (($rgba[3] ?? 1) * 127))));
    return imagecolorallocatealpha($img, $r, $g, $b, $a);
}

function vi_lottie_frame($data, $size) {
    $json = @gzdecode($data);
    if (!$json) return null;
    $lottie = json_decode($json, true);
    if (!is_array($lottie) || empty($lottie['layers'])) return null;
    $frame = 60;
    $draws = [];
    foreach (array_reverse($lottie['layers']) as $layer) {
        if (($layer['ty'] ?? 4) != 4 || empty($layer['shapes'])) continue;
        if (isset($layer['ip']) && $frame < $layer['ip']) continue;
        if (isset($layer['op']) && $frame > $layer['op']) continue;
        $m = vi_transform_matrix($layer['ks'] ?? [], $frame);
        vi_collect_shapes($layer['shapes'], $m, ['fill' => null, 'stroke' => null, 'strokeWidth' => 0], $draws, $frame);
    }
    if (!$draws) return null;
    $minX = $minY = 1000000;
    $maxX = $maxY = -1000000;
    foreach ($draws as $d) foreach ($d['points'] as $p) {
        $minX = min($minX, $p[0]); $maxX = max($maxX, $p[0]);
        $minY = min($minY, $p[1]); $maxY = max($maxY, $p[1]);
    }
    if ($maxX <= $minX || $maxY <= $minY) return null;
    $pad = max(1, (int)round($size * 0.08));
    $scale = min(($size - $pad * 2) / ($maxX - $minX), ($size - $pad * 2) / ($maxY - $minY));
    $offX = ($size - (($maxX - $minX) * $scale)) / 2 - $minX * $scale;
    $offY = ($size - (($maxY - $minY) * $scale)) / 2 - $minY * $scale;
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $clear);
    imagealphablending($img, true);
    foreach ($draws as $d) {
        $flat = [];
        foreach ($d['points'] as $p) {
            $flat[] = (int)round($p[0] * $scale + $offX);
            $flat[] = (int)round($p[1] * $scale + $offY);
        }
        $count = (int)(count($flat) / 2);
        if ($count < 2) continue;
        if ($d['fill'] !== null && $count >= 3) {
            imagefilledpolygon($img, $flat, vi_alloc_color($img, $d['fill']));
        }
        if ($d['stroke'] !== null) {
            imagesetthickness($img, max(1, (int)round(($d['strokeWidth'] ?: 2) * $scale)));
            imagepolygon($img, $flat, vi_alloc_color($img, $d['stroke']));
        }
    }
    return $img;
}

if (defined('VERIFYICON_LIB_ONLY')) {
    return;
}

try {
    include 'mp.php';
    $user = MP::getUser();
    if (!$user) {
        http_response_code(401);
        die;
    }
    $icon = $_GET['i'] ?? null;
    if ($icon === null || !is_numeric($icon)) {
        http_response_code(400);
        die;
    }
    $size = (int)($_GET['s'] ?? 24);
    if ($size < 16) $size = 16;
    if ($size > 96) $size = 96;

    $cacheFile = vi_cache_path($user, $icon, $size);
    if ($cacheFile !== null && is_file($cacheFile)) {
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=2592000');
        readfile($cacheFile);
        die;
    }

    $MP = MP::getMadelineAPI($user);
    $docs = $MP->messages->getCustomEmojiDocuments(['document_id' => [(int)$icon]]);
    $doc = $docs[0] ?? null;
    if (!$doc) {
        vi_output_png(vi_fallback($size), $cacheFile);
    }

    $payload = new Amp\ByteStream\Payload($MP->downloadToReturnedStream($doc));
    $data = $payload->buffer();
    $payload->close();

    try {
        $img = @imagecreatefromstring($data);
    } catch (Throwable $e) {
        $img = false;
    }
    if ($img) {
        vi_output_png(vi_resize($img, $size), $cacheFile);
    }

    $tmpDir = sys_get_temp_dir().'/mp/';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0775, true);
    }
    $prefix = $tmpDir.'verifyicon_'.sha1($user.'|'.$icon.'|'.$size);
    $rawPath = $prefix.'.raw';
    $pngPath = $prefix.'.png';
    @file_put_contents($rawPath, $data);

    $mime = $doc['mime_type'] ?? '';
    $converted = null;
    if ($mime === 'application/x-tgsticker') {
        $converted = vi_png_from_tgs($rawPath, $prefix, $size);
    } elseif ($mime === 'video/webm') {
        $converted = vi_png_from_webm($rawPath, $pngPath, $size);
    }
    if ($converted && is_file($converted)) {
        try {
            $convertedImg = @imagecreatefromstring(file_get_contents($converted));
        } catch (Throwable $e) {
            $convertedImg = false;
        }
        if ($convertedImg) {
            @unlink($rawPath);
            @unlink($converted);
            vi_output_png(vi_resize($convertedImg, $size), $cacheFile);
        }
    }
    if ($mime === 'application/x-tgsticker') {
        $lottieImg = vi_lottie_frame($data, $size);
        if ($lottieImg) {
            @unlink($rawPath);
            vi_output_png($lottieImg, $cacheFile);
        }
    }
    @unlink($rawPath);
    @unlink($pngPath);

    vi_output_png(vi_fallback($size), $cacheFile);
} catch (Throwable $e) {
    $size = isset($size) ? $size : 24;
    $cacheFile = isset($cacheFile) ? $cacheFile : null;
    vi_output_png(vi_fallback($size), $cacheFile);
}
