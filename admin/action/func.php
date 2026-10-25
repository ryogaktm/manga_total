<?php
//設定

$url = $_SERVER['SCRIPT_NAME'];
$meta = '<meta http-equiv="refresh" content="0;URL='.$_SERVER['SCRIPT_NAME'].'" />';   
    // (5)現在のディレクトリ名
$folder = "action";

//ユーザーごとのアクションを設定
$you_path = $you["login_user"];

//GETでユーザーを書き換え（アクション管理画面用）
if(!empty($_GET['look_user'])){
  $you_path = $_GET['look_user'];
}     


// (2)現在のディレクトリまでのパス
$admin = dirname(__FILE__);
$ip = $_SERVER["REMOTE_ADDR"];







//エラー読み込み（基本）

 if(empty($_GET['volxxxxx'])){ 
     
//action読み込み
setlocale(LC_ALL, 'ja_JP.UTF-8'); 

if(empty($link)){$link=""; }
      
$data = file_get_contents($link."admin/".$folder."/user_csv/".$you_path.".csv", "w");
//$data = mb_convert_encoding($data, 'UTF-8', 'sjis-win');

    
$temp = tmpfile();
$meta = stream_get_meta_data($temp);
fwrite($temp, $data);
rewind($temp);


$file = new SplFileObject($meta['uri']);
$file->setFlags(SplFileObject::READ_CSV);
 
$csv  = array();
 
foreach($file as $line) {
    $csv[] = $line;
}
fclose($temp);
$file = null;

/*UTF-8に変換 */
//mb_convert_variables('UTF-8', 'SJIS-win', $csv);

//var_dump($csv);
	

/*##########################################*/
//最初を削除_CSVがバグった
//$csv_1st = array_shift($csv);

    $i=0;
     foreach ($csv as $val) {
  
//var_dump($val); 
         
       
         
         if(!empty($val[0])){
             $action[$val[3]."_".$val[4]] = $val;
             //$max_id = $val[0]+1;
             
           }

  $i++;   }   
 
    //var_dump($action["13"]);
     
     
        //$max_id = max(array_keys($action))+1;
        //echo($max_id);
        
        
  //アクションリストを読み込む（閲覧履歴の並びを日時で正しく作り直す）
  // ・ヘッダー行など日時として読めない行は除外（以前は array_shift で先頭1件を捨てていた）
  // ・日時の前後の空白を取り除いてから比較
  // ・$action_post  … 新しい順（キーは日時+巻で重複しない）
  // ・$action_vol   … 「作品_巻」→ 最新の記録（巻一覧のしおり表示用）
  // ・$action_post2 … 作品 → 巻 の古い順（array_reverse して先頭＝最後に読んだ巻）
  $action_post = array();
  $action_post2 = array();
  $action_vol = array();
  $action_rows = array();
  if (empty($action)) { $action = array(); }
  foreach ($action as $val) {
      if (count($val) < 6) { continue; }
      $ts = strtotime(trim((string)$val[1]));
      if ($ts === false) { continue; }
      $action_rows[] = array(
          "id" => $val[3] . "_" . $val[4],
          "date" => trim((string)$val[1]),
          "ts" => $ts,
          "auther" => trim((string)$val[2]),
          "manga_title" => $val[3],
          "vol" => $val[4],
          "page" => $val[5],
      );
  }
  // 新しい順
  usort($action_rows, function ($a, $b) {
      if ($a["ts"] === $b["ts"]) { return strcmp($b["id"], $a["id"]); }
      return $b["ts"] - $a["ts"];
  });
  foreach ($action_rows as $row) {
      $action_post[date('Y/m/d H:i:s', $row["ts"]) . "_" . $row["id"]] = $row;
      if (!isset($action_vol[$row["id"]])) { $action_vol[$row["id"]] = $row; }
  }
  // 作品ごと・巻ごと（古い順に並べる）
  foreach (array_reverse($action_rows) as $row) {
      $action_post2[$row["manga_title"]][$row["vol"]] = $row;
  }
     
     
  
        //$action_list = array_pop($action_post);
       
        
    }


//var_dump($action); 
//書き込み
if(!empty($_POST['action_write'])){
         
          $ary[0] = $_POST["manga_title"]."_".$_POST["vol"];
          $ary[1] = $_POST["date"];
          $ary[2] = $_POST["auther"];
          $ary[3] = $_POST["manga_title"];
          $ary[4] = $_POST["vol"];
          $ary[5] = $_POST["page"];
    
// CSVファイルに書き込む配列を定義します。
//$ary = array("id", "日時","種類","内容","タイトル","巻","ページ");
                   
 //$action = $action + $ary;
//var_dump($ary);
array_push($action,$ary);
    

    
    
    
    
                
// ファイルを書き込み用に開きます。
$f = fopen($link."admin/".$folder."/user_csv/".$you_path.".csv", "w");
// 正常にファイルを開くことができていれば、書き込みます。
if ( $f ) {
  // $ary から順番に配列を呼び出して書き込みます。
  foreach($action as $line){
    // fputcsv関数でファイルに書き込みます。
    fputcsv($f, $line);
     // echo("書いた。<br>");
      
  }
}
    
//POSTをクリアにする
$_POST = array();
//リロード                
//header("Location: " . $_SERVER['PHP_SELF']);
if(isset($_GET['title'])){
    header('Location: '.$_SERVER['PHP_SELF'].'?title='.$_GET['title'].'&vol='.$_GET['vol'].'&p_num='.$ary[6]);
}else{
   header("Location: " . $_SERVER['PHP_SELF']);
}        
// ファイルを閉じます。
fclose($f);
    
 }

//エラーリセット
if(!empty($_GET['reset_action'])){
       
       
    // CSVファイルに書き込む配列を定義します。
$ary = array(
  array("1", "ID","日付","種類","内容","タイトル","巻数","ページ","状態","報告者")
 );
// ファイルを書き込み用に開きます。
$f = fopen($link."admin/".$folder."/user_csv/".$you_path.".csv", "w");
//$f = fopen("zzz.csv", "w");
// 正常にファイルを開くことができていれば、書き込みます。
if ( $f ) {
  // $ary から順番に配列を呼び出して書き込みます。
  foreach($ary as $line){
    // fputcsv関数でファイルに書き込みます。
    fputcsv($f, $line);
  } 
}
// ファイルを閉じます。
fclose($f);
    
       
       

    echo("リセットー");
header('location: index.php');
exit();//←忘れずに！
   }





 ?>
 
