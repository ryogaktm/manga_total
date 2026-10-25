<?php
//ここから独自関数
$no_img = "asset/icon/no_img.jpg";

// 画像パスの整形。
// 以前は半角スペース・( ) # だけを %20 などに置き換えていたが、ページ側で urlencode し、
// read_image.php でもう一度デコードするため、名前に + や % が混ざると別の名前になって
// 画像が取得できなかった。パスは生のまま渡し、ページ側で一度だけ urlencode する。
if (!function_exists('g4_moji_code')) {
    function g4_moji_code($s)
    {
        return str_replace('img_master', 'img', $s);
    }
}

// 画像を読み込む（拡張子で関数を切り替え）
if (!function_exists('g4_load_image')) {
    function g4_load_image($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'webp') {
            return @imagecreatefromwebp($path);
        } elseif ($ext === 'png') {
            return @imagecreatefrompng($path);
        }
        return @imagecreatefromjpeg($path);
    }
}

// フォルダの中身を一覧する（glob の代わり）
// glob は [ ] { } * ? を含む名前（例：「作品名 [完結]」）で検索できず、
// *.jpg は大文字の .JPG にも一致しないので、scandir で自前に一覧する。
if (!function_exists('g4_list')) {
    function g4_list($dir)
    {
        $items = @scandir($dir);
        if ($items === false) {
            return [];
        }
        $out = [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..' || $name[0] === '.') {
                continue;
            }
            $out[] = rtrim($dir, '/') . '/' . $name;
        }
        return $out;
    }
}

// サブフォルダだけ
if (!function_exists('g4_dirs')) {
    function g4_dirs($dir)
    {
        return array_values(array_filter(g4_list($dir), 'is_dir'));
    }
}

// 拡張子（大文字小文字は区別しない）で絞ったファイル一覧。$exts が空なら「拡張子のあるファイル全部」
if (!function_exists('g4_files')) {
    function g4_files($dir, $exts = [])
    {
        $out = [];
        foreach (g4_list($dir) as $path) {
            if (!is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === '') {
                continue;
            }
            if (empty($exts) || in_array($ext, $exts, true)) {
                $out[] = $path;
            }
        }
        return $out;
    }
}

// 縮小して JPEG 保存（プログレッシブJPEG＝読み込み途中から絵が出る）
if (!function_exists('g4_resize_save')) {
    function g4_resize_save($src_path, $dst_path, $w, $h, $quality = 80)
    {
        $size = @getimagesize($src_path);
        if (!$size || $size[0] == 0 || $size[1] == 0) {
            return false;
        }
        list($width, $hight) = $size;
        //横長対策
        if ($width >= $hight) {
            $w = ($width / $hight) * $h;
        }
        $base = g4_load_image($src_path);
        if (!$base) {
            return false;
        }
        $image = imagecreatetruecolor((int)round($w), (int)round($h));
        imagecopyresampled($image, $base, 0, 0, 0, 0, (int)round($w), (int)round($h), $width, $hight);
        imageinterlace($image, true);
        $ok = imagejpeg($image, $dst_path, $quality);
        // メモリ解放（しないと巻を初めて開いた時にメモリ不足で止まることがある）
        imagedestroy($image);
        imagedestroy($base);
        return $ok;
    }
}

/// 名前の昇順で並べる関数_old
$sort_by_name_old = function ($a, $b) {
    return strnatcmp($a, $b);
};



/// 名前の昇順で並べる関数仮名まじり
$sort_by_name = function ($a, $b) {
    $a = mb_convert_kana($a, 'c');
    $b = mb_convert_kana($b, 'c');
    return strcasecmp($a, $b);
};

/// 名前の昇順で並べる関数仮名まじり
$sort_by_name_down = function ($a, $b) {
    $a = mb_convert_kana($a, 'c');
    $b = mb_convert_kana($b, 'c');
    return -1 * strcasecmp($a, $b);
};


///時間の昇順で並べる関数     
$sort_by_time = function ($a, $b) {
    return filemtime($b) - filemtime($a);
};

//3階層PHP
if (!empty($_GET['vol'])) {
    //3階層画像
    $file_name = $_GET['title'];
    $file_child_name = $_GET['vol'];


    // フォルダ名に ../ などを含むものは受け付けない
    foreach ([$file_name, $file_child_name] as $g4_part) {
        if (strpos($g4_part, '..') !== false || strpos($g4_part, '/') !== false || strpos($g4_part, '\\') !== false || strpos($g4_part, "\0") !== false) {
            http_response_code(400);
            exit;
        }
    }

    $vol_dir = 'img/' . $file_name . '/' . $file_child_name;

    //不要なファイルを消す（html / cmczip.com.url / cmczip.com / *.db）。大文字の拡張子も対象
    foreach (g4_list($vol_dir) as $val_del) {
        $base_lc = strtolower(basename($val_del));
        $ext_lc = strtolower(pathinfo($val_del, PATHINFO_EXTENSION));
        if (is_file($val_del) && (in_array($ext_lc, ['html', 'db'], true) || $base_lc === 'cmczip.com.url' || $base_lc === 'cmczip.com')) {
            @unlink($val_del);
        }
    }

    //pngをjpgに変換（.PNG も対象）
    foreach (g4_files($vol_dir, ['png']) as $val_del) {
        // 変換前ファイル読み込み
        $image = @imagecreatefrompng($val_del);
        if ($image) {
            // 指定形式に変換
            imagejpeg($image, $val_del . '.jpg');
            unlink($val_del);
            // メモリの解放
            imagedestroy($image);
        }
    }

    //jpg / jpeg / webp（大文字の拡張子も可）を一覧にする
    $files_img = g4_files($vol_dir, ['jpg', 'jpeg', 'webp']);

    // img_small のフォルダが無いと縮小画像を作れないので先に作る
    $small_dir = 'img_small/' . $file_name . '/' . $file_child_name;
    if (!is_dir($small_dir)) {
        @mkdir($small_dir, 0755, true);
    }

    natcasesort($files_img);
    $files_img = array_merge($files_img);

    //var_dump($files_img);



    //第3層の画像の並び順はそのまま
    //usort($files_img, $sort_by_name );
    $files_img = array_values($files_img);



    $i = 0;
    foreach ($files_img as $file_img) {
        $img_name = basename($file_img);
        $img_path = 'img/' . $file_name . '/' . $file_child_name . '/' . $img_name;
        $img_path_s = 'img_small/' . $file_name . '/' . $file_child_name . '/' . $img_name;

        $rk_manga[$file_name]['in'][$file_child_name]['img'][$img_name][$i] = $img_path;

        //画像URLをエンコード
        $rk_manga[$file_name]['in'][$file_child_name]['img_small'][$img_name][$i] = g4_moji_code($img_path_s);

        //第三層img file作成（大きい画像は 640x930 に縮小して上書き。初回だけ）
        if (filesize($img_path) > 400000) {
            g4_resize_save($img_path, $img_path, 640, 930, 80);
            clearstatcache(true, $img_path);
        }

        //第三層 img_small file作成（ページ一覧・読み込み中の仮表示用）
        if (!file_exists($img_path_s)) {
            g4_resize_save($img_path, $img_path_s, 180, 260, 75);
        }

        $i++;
    }
}

//2階層PHP
if (!empty($_GET['title'])) {
    $files_child = g4_dirs('img/' . $_GET['title']);
    //usort($files_child, $sort_by_name );

    //$files_child = array_values($files_child);

    //気持ち悪い数字順をもとに戻す
    natcasesort($files_child);
    $files_child = array_merge($files_child);

    foreach ($files_child as $file_child) {


        $file_child_name = str_replace('img/' . $_GET['title'] . '/', '', $file_child);



        //2階層表紙画像PHP（読書ページでは表紙は使わないので省略して高速化）
        $cover_img = empty($_GET['vol']) ? g4_files($file_child, ['jpg', 'jpeg', 'png', 'webp']) : [];
        $cover_img = array_map('g4_moji_code', $cover_img);



        if (!empty($cover_img[0])) {

            //小さい画像に変換
            $cv_img_small = str_replace('img/', 'img_small/', $cover_img[0]);

            $manga_vol[$_GET['title']]['in'][$file_child_name]["cover_img"]  = $cv_img_small;
        } else if (empty($_GET['vol'])) {
            $manga_vol[$_GET['title']]['in'][$file_child_name]["cover_img"]  = $no_img;
        }



        $manga_vol[$_GET['title']]['in'][$file_child_name]["name"] = $file_child_name;
        if (empty($_GET['vol'])) {
            $manga_vol[$_GET['title']]['in'][$file_child_name]["date"] = date('Y-m-d', filemtime($file_child));
        }
        //echo($rk_manga[$key]['in'][basename($file_child)]["name"]);
        //echo($rk_manga[$key]['in'][basename($file_child)]["date"]);



        //第二層 img file作成
        if (file_exists('img/' . $_GET['title'] . '/' . $file_child_name)) {
            //echo "$file_child_name が存在します";
        } else {
            //echo "$file_child_name は存在しません<br>";
            mkdir('img/' . $_GET['title'] . '/' . $file_child_name, 0755);
        }



        //第二層 img_small file作成
        if (file_exists('img_small/' . $_GET['title'] . '/' . $file_child_name)) {

            //echo "$file_child_name が存在します";
        } else {

            //echo "$file_child_name は存在しません<br>";
            mkdir('img_small/' . $_GET['title'] . '/' . $file_child_name, 0755);
        }
    }
}

//1階層PHP
else {

    $files = g4_dirs('img');

    //並び順
    if (!empty($_GET['sort']) && $_GET['sort'] == 'name_up') {
        usort($files, $sort_by_name);
    } else if (!empty($_GET['sort']) && $_GET['sort'] == 'name_down') {

        usort($files, $sort_by_name_down);
    } else if (!empty($_GET['sort']) && $_GET['sort'] == 'time_down') {
        //逆並び
        rsort($files, $sort_by_time);
    } else {
        usort($files, $sort_by_time);
    }


    //var_dump($files);

    foreach ($files as $file) {

        $file_name = str_replace('img/', '', $file);
        //echo($file_name."<br>");
        //echo($file."<br>");



        //1階層表紙画像
        $cover_img0 = g4_dirs($file);
        $cover_img = !empty($cover_img0[0]) ? g4_files($cover_img0[0], ['jpg', 'jpeg', 'png', 'webp']) : [];
        $cover_img = array_map('g4_moji_code', $cover_img);

        if (!empty($cover_img[0])) {

            //小さい画像に変換
            $cv_img_small = str_replace('img/', 'img_small/', $cover_img[0]);

            //$rk_manga[$file_name]["cover_img"]  = $cover_img[0];

            $rk_manga[$file_name]["cover_img"]  = $cv_img_small;
        } else {
            $rk_manga[$file_name]["cover_img"]  = $no_img;
        }



        $rk_manga[$file_name]["name"] = $file_name;
        //文字化けするので強引に変換

        //$file_name = str_replace('img/', '', $file);    
        //$rk_manga[basename($file)]["name"] = $file_name;

        $rk_manga[$file_name]["date"] = date('Y年m月d日', filemtime($file));
        //echo($rk_manga[basename($file)]["name"]);
        //echo($rk_manga[basename($file)]["date"]);

        //第一層 img file作成
        if (file_exists('img/' . $file_name)) {
            //echo "$file_name が存在します";
        } else {
            //echo "$file_name は存在しません<br>";
            mkdir('img/' . $file_name, 0755);
        }


        //第一層img_small file作成
        if (file_exists('img_small/' . $file_name)) {
            //echo "$file_name が存在します";
        } else {
            mkdir('img_small/' . $file_name, 0755);
        }
    }


    //var_dump($rk_manga);

}
