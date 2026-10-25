<?php
// 読書中のページを閲覧履歴(しおり)に保存する軽量エンドポイント
// （以前は index.php 全体に POST していて、そのたびに全作品の一覧処理が走っていた）
ob_start();
session_set_cookie_params(60 * 60 * 24 * 365);
session_start();
$you = $_SESSION['you'] ?? null;
session_write_close();

if (empty($you) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['manga_title']) || empty($_POST['vol'])) {
    ob_end_clean();
    http_response_code(403);
    exit;
}

chdir(__DIR__);
$link = "";
$page = max(1, (int)($_POST['page'] ?? 1));
$_POST = [
    'manga_title'  => (string)$_POST['manga_title'],
    'vol'          => (string)$_POST['vol'],
    'page'         => $page,
    'date'         => date('Y/m/d H:i:s'),
    'auther'       => $you['login_user'],
    'action_write' => 1,
];
$_GET = [];

// 既存の書き込み処理をそのまま利用
include(__DIR__ . "/admin/action/func.php");

if (function_exists('header_remove')) {
    header_remove('Location');
}
ob_end_clean();
http_response_code(204);
