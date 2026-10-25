<?php
// 画像配信（ログインユーザーのみ）
// 高速化ポイント：
//  - ユーザーCSVを毎回読まず、セッションだけでログイン確認
//  - ETag を md5_file（ファイル全体を読む）ではなく 更新日時+サイズ で作る
//  - 304 判定をファイルを読む前に行う
session_set_cookie_params(60 * 60 * 24 * 365);
session_start();
$you = $_SESSION['you'] ?? null;
// セッションロックをすぐ解除（画像の並列読み込みが詰まらないように）
session_write_close();

if (empty($you)) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

$base_dir = __DIR__ . '/';
$path = $_GET['path'] ?? '';
if (strpos($path, "\0") !== false) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

// PHP は $_GET を1回デコード済み。ページ側が「%20」などを埋め込んでいる場合に備えて、
// まず受け取った名前のまま探し、無ければもう1回デコードして探す。
// （ファイル名に % や + を含む場合に、2回デコードして別の名前になってしまうのを防ぐ）
$candidates = [$path];
$decoded = urldecode($path);
if ($decoded !== $path && strpos($decoded, "\0") === false) {
    $candidates[] = $decoded;
}

// 縮小画像を元画像から作る（まだ無い場合）。新しく追加した本の表紙が出ない問題の対策
function make_small($src, $dst)
{
    $size = @getimagesize($src);
    if (!$size || $size[0] == 0 || $size[1] == 0) {
        return false;
    }
    list($width, $height) = $size;
    $h = 260;
    $w = ($width >= $height) ? ($width / $height) * $h : 180; // 横長の画像は縦に合わせる
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        $base = @imagecreatefromwebp($src);
    } elseif ($ext === 'png') {
        $base = @imagecreatefrompng($src);
    } else {
        $base = @imagecreatefromjpeg($src);
    }
    if (!$base) {
        return false;
    }
    $img = imagecreatetruecolor((int)round($w), $h);
    imagecopyresampled($img, $base, 0, 0, 0, 0, (int)round($w), $h, $width, $height);
    imageinterlace($img, true);
    @mkdir(dirname($dst), 0755, true);
    $tmp = $dst . '.tmp' . getmypid();      // 途中まで書いたファイルが配信されないように一時ファイルに書く
    if ($ext === 'png') {
        $ok = imagepng($img, $tmp);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $ok = imagewebp($img, $tmp, 75);
    } else {
        $ok = imagejpeg($img, $tmp, 75);
    }
    imagedestroy($img);
    imagedestroy($base);
    if ($ok) {
        $ok = @rename($tmp, $dst);
    }
    @unlink($tmp);
    return $ok;
}

$full_path = false;
foreach ($candidates as $cand) {
    $rp = realpath($base_dir . $cand);
    if ($rp !== false && is_file($rp)) {
        $full_path = $rp;
        break;
    }
}
if ($full_path === false) {
    foreach ($candidates as $cand) {
        if (strpos($cand, 'img_small/') !== 0 || strpos($cand, '..') !== false) {
            continue;
        }
        $rel = substr($cand, strlen('img_small/'));
        $src = realpath($base_dir . 'img/' . $rel);
        $img_root = realpath($base_dir . 'img');
        if ($src !== false && $img_root && strpos($src, $img_root . DIRECTORY_SEPARATOR) === 0 && is_file($src)) {
            $dst = $base_dir . 'img_small/' . $rel;
            if (make_small($src, $dst)) {
                $rp = realpath($dst);
                if ($rp !== false) {
                    $full_path = $rp;
                    break;
                }
            }
        }
    }
}

$is_allowed = false;
if ($full_path && is_file($full_path)) {
    foreach (['img', 'img_small', 'asset'] as $root) {
        $root_path = realpath($base_dir . $root);
        if ($root_path && strpos($full_path, $root_path . DIRECTORY_SEPARATOR) === 0) {
            $is_allowed = true;
            break;
        }
    }
}

if (!$is_allowed) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

$mtime = filemtime($full_path);
$size = filesize($full_path);
$etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';

// ログイン必須の画像なので private。30日キャッシュ
header('Cache-Control: private, max-age=2592000');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);

$inm = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
$ims = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
if (($inm !== '' && $inm === $etag) || ($inm === '' && $ims !== false && $ims >= $mtime)) {
    header("HTTP/1.1 304 Not Modified");
    exit;
}

$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
];
$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
$mime = $types[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($full_path) : 'application/octet-stream');

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
readfile($full_path);
exit;
