<?php
if (!empty($action_post)) {
    //rsort($action_post);
    foreach ($action_post as $key2 => $val2) { ?>
        <a href="?title=<?= rawurlencode($val2['manga_title']) ?>&vol=<?= rawurlencode($val2['vol']) ?>&p_num=<?= (int)$val2['page'] ?>" class="book_mark_box" target="_blank">

            <div class="cont">
                <p class="p1">前回のつづき</p>
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
<?php break;
    }
} ?>