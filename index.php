<?php
// セッションの有効期限を5分に設定
session_set_cookie_params(60 * 60 * 24 * 365);
// セッション管理開始
session_start();
?>
<html<?= !empty($_GET['vol']) ? ' class="rd_html"' : '' ?>>

<head>
    <meta charset="UTF-8">


    <?php
    //絶対パスから現在のファイルまでの導線    
    $url = $_SERVER['SCRIPT_NAME'];
    $meta = '<meta http-equiv="refresh" content="0;URL=' . $_SERVER['SCRIPT_NAME'] . '" />';
    $admin = dirname(__FILE__);
    $ip = $_SERVER["REMOTE_ADDR"];
    ?>

    <?php include("admin/error/func.php"); ?>

    <?php include("admin/user/func.php"); ?>
    <?php include("admin/user/login.php"); ?>
    <?php include("admin/news/func.php"); ?>


    <?php

    if (!empty($you)) {
        include("admin/action/func.php");
    }

    ?>


    <title>勝間</title>



    <?php //var_dump($error_post); 
    ?>

    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, minimal-ui, viewport-fit=cover">


    <!-- <meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black"> -->
    <!--	<meta name="viewport" content="initial-scale=1.0">-->


    <link rel="apple-touch-icon" href="home.png">
    <link rel="icon" type="image/svg+xml" href="asset/icon/app-icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="asset/icon/app-icon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="asset/icon/app-icon-192.png">
    <meta name="apple-mobile-web-app-title" content="自炊本">
    <meta name="theme-color" content="#0a3487">
    <meta name="robots" content="noindex">

    <link href="asset/reset.css" rel="stylesheet" type="text/css">

    <!--     <script src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>-->


    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js"></script>
    <script type="text/javascript" src="asset/plugin/simpleticker/ticker.js"></script>



    <!-- ページ送りは asset/reader.js（slick は不使用） -->


    <?php ?>

    <link href="asset/add.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="asset/pc.css" type="text/css" media="screen and (min-width:480px)">

    <link rel="stylesheet" href="asset/sp.css" type="text/css" media="screen and (max-width:479px)">
    <link rel="stylesheet" href="asset/top.css?v=<?= filemtime(__DIR__ . '/asset/top.css') ?>" type="text/css">
    <?php if (!empty($_GET['vol'])) { ?><link rel="stylesheet" href="asset/reader.css?v=<?= filemtime(__DIR__ . '/asset/reader.css') ?>" type="text/css"><?php } ?>

    <!--      -->
    <script type="text/javascript" src="asset/add.js"></script>
    <?php include("asset/temp/device_switch.php"); ?>

</head>


<!--ログインできた時 -->

<?php if (!empty($you)) { ?>

    <body>
        <div class="body_in">
            <?php
            // ディレクトリのパス
            $dir = "img";
            $check_dirs = [$dir];
            $file_paths = [];
            //ここから独自
            include("g4.php");
            ?>
            <?php if (!empty($_GET['title'])) { ?>
                <?php $key_name = $_GET['title']; ?>
                <?php if (!empty($_GET['vol'])) { ?>

                    <!-- ######## 詳細ページHTML ######-->
                    <style>
                        .body_in {
                            background: #000;
                        }
                    </style>
                    <?php $key_vol = $_GET['vol']; ?>

                    <?php
                    // ===== 前の巻・次の巻を求める =====
                    $key_past = "";
                    $key_next = "";
                    $key_past0 = "";
                    $found_cur = false;
                    if (!empty($manga_vol[$key_name]['in'])) {
                        foreach ($manga_vol[$key_name]['in'] as $val_nav) {
                            if ($found_cur) {
                                $key_next = $val_nav['name'];
                                break;
                            }
                            if ($val_nav['name'] == $key_vol) {
                                $found_cur = true;
                                $key_past = $key_past0;
                            } else {
                                $key_past0 = $val_nav['name'];
                            }
                        }
                    }

                    // ===== ページ一覧（画像パス）=====
                    $img_list = $rk_manga[$key_name]['in'][$key_vol]['img'] ?? [];
                    $small_list = $rk_manga[$key_name]['in'][$key_vol]['img_small'] ?? [];
                    $reader_pages = [];
                    foreach ($img_list as $img_base => $img_val) {
                        $src = reset($img_val);
                        $thumb = isset($small_list[$img_base]) ? reset($small_list[$img_base]) : '';
                        // 横長（見開きスキャン）判定用に縦横サイズを渡す（ヘッダーだけ読むので軽い）
                        $img_size = @getimagesize($src);
                        $reader_pages[] = [
                            'src' => 'read_image.php?path=' . urlencode($src),
                            'thumb' => $thumb !== '' ? 'read_image.php?path=' . urlencode($thumb) : '',
                            'w' => $img_size ? $img_size[0] : 0,
                            'h' => $img_size ? $img_size[1] : 0,
                        ];
                    }
                    $page_total = count($reader_pages);

                    // ===== 開始ページ（?p_num=5 / ?p_num=last）=====
                    $start_index = 0;
                    if (!empty($_GET['p_num'])) {
                        if ($_GET['p_num'] === 'last') {
                            $start_index = max($page_total - 1, 0);
                        } else {
                            $start_index = min(max((int)$_GET['p_num'] - 1, 0), max($page_total - 1, 0));
                        }
                    }

                    $vol_url = function ($vol) use ($key_name) {
                        return '?title=' . rawurlencode($key_name) . '&vol=' . rawurlencode($vol);
                    };

                    $reader_data = [
                        'title'   => $key_name,
                        'vol'     => $key_vol,
                        'start'   => $start_index,
                        'pages'   => $reader_pages,
                        'prevUrl' => $key_past !== '' ? $vol_url($key_past) : null,
                        'nextUrl' => $key_next !== '' ? $vol_url($key_next) : null,
                        'saveUrl' => 'save_page.php',
                    ];
                    ?>

                    <div class="main main_slide">
                        <!-- ページ一覧（サムネイルは開いた時に読み込む） -->
                        <div class="thumb_box pop_box off_box">
                            <div class="pop_box_in">
                                <div class="pop_hd">
                                    <p class="back list_back togle_list_btn" data-obj=".thumb_box">
                                        <?php include("asset/icon/times.svg"); ?>
                                    </p>
                                    <p class="main_title">
                                        <?php echo ($key_vol); ?>
                                    </p>
                                </div>
                                <div class="pop_body p_num_list">
                                    <?php foreach ($reader_pages as $pi => $pg) { $pn = $pi + 1; ?>
                                        <a id="page_box<?= $pn ?>" class="box box<?= $pn ?><?= $pi === $start_index ? ' cur_box' : '' ?>" data-page="<?= $pn ?>" href="<?= htmlspecialchars($vol_url($key_vol)) ?>&amp;p_num=<?= $pn ?>" data-bg="<?= htmlspecialchars($pg['thumb']) ?>">
                                            <p class="num"><?= $pn ?></p>
                                        </a>
                                    <?php } ?>
                                </div>
                                <div class="pop_ft"></div>
                            </div>
                            <div class="div bg"></div>
                        </div>

                        <div class="main_total">
                            <div class="main_hd tra">
                                <a class="hd_home" href="index.php">
                                    <?php include("asset/icon/home.svg"); ?>
                                </a>
                                <p class="vol_title">
                                    <?php echo ($key_vol); ?>
                                </p>
                                <?php include("admin/error/error_box.php"); ?>
                            </div>

                            <div class="main_body">
                                <!-- ビューア本体（左タップ=次 / 右タップ=前 / 中央タップ=メニュー） -->
                                <div class="rd_viewer" id="rd_viewer">
                                    <div class="rd_track" id="rd_track"></div>
                                </div>
                                <div class="rd_toast" id="rd_toast"></div>
                            </div>

                            <div class="main_ft tra">
                                <div class="slick-counter togle_list_btn" data-obj=".thumb_box">
                                    <span class="current"><?= $start_index + 1 ?></span><span class="current_to"></span> / <span class="total"><?= $page_total ?></span>
                                    <?php include("asset/icon/layers.svg"); ?>
                                </div>

                                <!-- 横長ページを開いている時だけ表示：割る／割らないを、そのページだけ切り替え -->
                                <button type="button" class="rd_split_btn" id="rd_split_btn" style="display:none">半分ずつ</button>

                                <!-- ページバー（右→左） -->
                                <div class="rd_seek_wrap">
                                    <input type="range" class="rd_seek" id="rd_seek" min="1" max="<?= max($page_total, 1) ?>" value="<?= $start_index + 1 ?>" aria-label="ページ">
                                </div>

                                <!-- 右→左に読むので「次の巻」は左、「前の巻」は右 -->
                                <div class="nav">
                                    <?php if ($key_next !== '') { ?>
                                        <a class="a_next" href="<?= htmlspecialchars($vol_url($key_next)) ?>">
                                            <?php include("asset/icon/angle-double-left.svg"); ?>次の巻
                                        </a>
                                    <?php } else { ?>
                                        <span class="rd_nav_ph"></span>
                                    <?php } ?>

                                    <div class="nav_group">
                                        <div class="ft_shiori">
                                            <?php include("admin/action/box.php"); ?>
                                        </div>
                                        <a href="?title=<?= rawurlencode($key_name) ?>" class="vol_title">
                                            <?php include("asset/icon/rack-server.svg"); ?>巻一覧
                                        </a>
                                    </div>

                                    <?php if ($key_past !== '') { ?>
                                        <a class="a_back" href="<?= htmlspecialchars($vol_url($key_past)) ?>">
                                            前の巻<?php include("asset/icon/angle-double-right.svg"); ?>
                                        </a>
                                    <?php } else { ?>
                                        <span class="rd_nav_ph"></span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script type="application/json" id="reader-data"><?= json_encode($reader_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                    <script src="asset/reader.js?v=<?= filemtime(__DIR__ . '/asset/reader.js') ?>"></script>

                <?php } else { ?>


                    <div class="main main_list snd_main top_main">
                        <div class="main_hd">
                            <p class="title">
                                <?php echo ($_GET['title']); ?>
                            </p>


                            <a href="?" class="back">
                                <?php include("asset/icon/home.svg"); ?>
                            </a>
                        </div>

                        <div class="body main_body">
                            <!--######## リスト2HTML ##########-->
                            <div class="list_body body list2">

                                <?php foreach ($manga_vol[$key_name]['in'] as $key => $val) {
                                    if (!empty($key)) {  ?>

                                        <?php //しおり
                                        //var_dump($action_post); 
                                        $ac_key = $key_name . "_" . $val['name'];
                                        if (!empty($action_vol[$ac_key])) {
                                            $page_link = "&p_num=" . (int)$action_vol[$ac_key]["page"];
                                        } else {
                                            $page_link = "";
                                        }
                                        ?>


                                        <a class="box" href="?title=<?php echo ($key_name); ?>&vol=<?php echo ($val['name']); ?><?php echo ($page_link); ?>">


                                            <!-- <div class="img" style="background-image:url(<?php echo ($val["cover_img"]); ?>);"> -->

                                            <div class="img" style="background-image:url(read_image.php?path=<?php echo urlencode($val["cover_img"]); ?>);">

                                                <?php if (!empty($action_vol[$ac_key])) { ?>
                                                    <div class="book_mark">
                                                        <?php include("asset/icon/bookmark.svg"); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="info">
                                                <p class="date"><?php echo ($val["date"]); ?></p>
                                                <p class="title"><?php echo ($val["name"]); ?></p>
                                            </div>
                                        </a>


                                <?php  }
                                } ?>

                            </div>
                        </div>

                        <div class="main_ft"></div>

                    </div>


        </div>
    <?php } ?>
<?php } else { ?>
    <!-- ######### TOP リスト1HTML #############-->







    <div class="main main_list top_main">


        <div class="main_hd">

            <!--      サーチ          -->
            <div class="sch_box">

                <div class="search-area main_body">

                    <a class="title" href="../manga/">
                        <?= $you['label'] ?>村</a>
                    <div class="sch_in">
                        <form>
                            <input type="text" id="search-text" placeholder="検索ワードを入力">
                        </form>
                        <div class="search-result">
                            <div class="search-result__hit-num"></div>
                            <div id="search-result__list"></div>
                        </div>
                    </div>
                    <form action="" method="post">
                        <button class="btn logout_btn" type='submit' name='action' value='logout'>ログアウト</button>
                    </form>


                </div>
            </div>



        </div>
        <?php
                //var_dump($rk_manga);
                //var_dump($action_post);
        ?>



        <?php if (!empty($_GET["page_type"]) && $_GET["page_type"] == "read_list") { ?>
            <!-- ######### TOP 閲覧履歴のBODY read_body #############-->
            <div class="body main_body read_body">

                <?php
                    if (!empty($action_post)) {

                        // 作品ごとに「最後に読んだ巻・ページ」だけを新しい順に表示
                        // （$action_post は新しい順なので、各作品で最初に出てきたものが最新）
                        $history_list = [];
                        foreach ($action_post as $val0) {
                            if (!isset($history_list[$val0['manga_title']])) {
                                $history_list[$val0['manga_title']] = $val0;
                            }
                        }
                        //rsort($action_post2);
                        foreach ($history_list as $key2 => $val2) { ?>
                        <a href="?title=<?= rawurlencode($val2['manga_title']) ?>&vol=<?= rawurlencode($val2['vol']) ?>&p_num=<?= (int)$val2['page'] ?>" class="book_mark_box" target="_blank">

                            <div class="cont">
                                <p class="p2">
                                    <?= $val2['manga_title'] ?>
                                </p>
                                <p class="p3">
                                    <?= $val2['vol'] ?> |
                                    <?= $val2['page'] ?>P</p>
                            </div>
                            <div class="img" style="background-image: url(read_image.php?path=<?= urlencode($rk_manga[$val2['manga_title']]['cover_img'] ?? 'asset/icon/no_img.jpg') ?>);">

                            </div>

                        </a>
                    <?php  }
                    } else { ?>
                    <p class="center_p">
                        閲覧履歴はありません。
                    </p>
                <?php } ?>
            </div>

        <?php } else if (!empty($_GET["page_type"]) && $_GET["page_type"] == "post_list") { ?>
            <?php include("admin/news/func.php"); ?>
            <link rel="stylesheet" href="asset/front_news.css">

            <?php include("admin/news/post_list_cate.php"); ?>


        <?php } else if (!empty($_GET["page_type"]) && $_GET["page_type"] == "post_detail") { ?>
            <?php include("admin/news/func.php"); ?>
            <link rel="stylesheet" href="asset/front_news.css">

            <?php include("admin/news/post_detail.php"); ?>


        <?php } else if (!empty($_GET["page_type"]) && $_GET["page_type"] == "atoz") { ?>

            <?php include('body_atoz.php'); ?>




        <?php } else { ?>
            <!-- ######### TOP 通常のBODY def_body #############-->
            <?php include('body_def.php'); ?>







        <?php } ?>



        <div class="main_ft">



            <div class="a_group">
                <a class="a_name<?= $_GET['sort'] == 'name_up' ? ' active' : '' ?>" href="?page_type=atoz">
                    <?php include("asset/icon/alpha-a-box.svg"); ?>
                    <p>あ-Z</p>
                </a>

                <a class="a_time<?= $_GET['sort'] == 'time_up' ? ' active' : '' ?>" href="?sort=time_up">
                    <?php include("asset/icon/clock-time-eight-outline.svg"); ?>
                    <p>更新順</p>
                </a>


                <a class="a_read<?= $_GET['page_type'] == 'read_list' ? ' active' : '' ?>" href="?page_type=read_list">
                    <?php include("asset/icon/bookmark.svg"); ?>
                    <p>閲覧履歴</p>
                </a>


                <a class="a_read<?= $_GET['page_type'] == 'post_list' ? ' active' : '' ?>" href="?page_type=post_list">
                    <?php include("asset/icon/new.svg"); ?>
                    <p>新着情報</p>
                </a>

                <?php if ($you["role"] == "管理人") { ?>

                    <a class="a_setting" href="admin/user" target="_blank">
                        <?php include("asset/icon/cog.svg"); ?>
                        <p>設定</p>
                    </a>
                <?php } ?>
            </div>


        </div>


    </div>




    <!--サーチボックスJS-->
    <script>
    </script>
<?php } ?>
    </body>
<?php } else { ?>
    <!--ログインできなかった時 -->

    <body class="login_body">
        <div class="login_cont">
            <form action="?<?= uniqid(); ?>" method="post">
                <p class="title">ログイン</p>

                <?php //var_dump($user_list); 
                ?>



                <input type="text" name="login_user" value="" placeholder="ユーザー名">
                <input type="text" name="login_pass" value="" placeholder="パスワード">
                <button class="btn" type='submit' name='action' value='login'>送信</button>
            </form>


        </div>

        <script>
            window.addEventListener('DOMContentLoaded', function() {
                window.scrollTo(0, 0);
            });
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.scrollTo(0, 0);
                }
            });

            $(document).ready(function() {
                // 1. スライドの画像や矢印などを「クリック・タップ」した時に一番上に戻す
                $(document).on('click', '.slick-slide, .slick-arrow, .slick-dots', function() {
                    window.scrollTo(0, 0);
                });

                // 2. スワイプなどでスライドが「完全に切り替わった後」に一番上に戻す
                // （Slickスライダーの初期化が終わるのを少し待ってから紐付けます）
                setTimeout(function() {
                    $('.slick-slider').on('afterChange', function(event, slick, currentSlide) {
                        window.scrollTo(0, 0);
                    });
                }, 1000);
            });
        </script>

    </body>
<?php } ?>




</html>