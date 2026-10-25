$(window).scroll(function() {
    //console.log($(this).scrollTop(50));
  
})


$(document).ready(function () {
    
    //WEBアプリ時にaタグを押したら別タブにならないようにする
		// ページ一覧のサムネイルは reader.js がページ遷移なしで処理する
		$('a').not('.p_num_list .box').click(function(){
			location.href = $(this).attr('href');
			return false;
		});

    
    //詳細ページのヘッダー、フッターが消える処理
    function op_toggle() {
                $(".main_hd").toggleClass("op0");
                $(".main_ft").toggleClass("op0");
              
    }
    
    
    //消すファンクション
    function op0() {
            $(".main_hd, .main_ft").hide();
            $(".main_hd, .main_ft").addClass("op0");
            setTimeout(function(){$(".main_hd, .main_ft").show();},300);
    }
    
    // ※ スライダー関連（ページ送り・ページ一覧・ページバー・中央タップ）は asset/reader.js に移行しました
    
//ポップアップ用のボタンを押した時、data-objを読み込んで任意のボックスをポップアップ
$('.togle_list_btn').click(function(){
    
        var obj = $(this).data("obj");
        $(obj).toggleClass("off_box");
        $(".main_body").toggleClass("off_box");
    
    //var cur_num = 100;
    // 現在ページのサムネイルまでスクロール（reader.js 側でも処理）
    var cur = document.querySelector(obj + ' .cur_box');
    if (cur && cur.scrollIntoView) { cur.scrollIntoView({block: 'center'}); }
    
});
    
    
    
//ページリストポップアップさせる時、現在のページにスクロールダウンさせる

    

    
//ラジオの”その他ボタン”を押した時
$('.other_input').click(function(){
    //$('#radio4').focus(function(){
   $('.radio_area_txt').attr("name","cont");
   $('.radio_area_radio radio').attr("name","");
   $('.radio_area_radio radio').removeAttr('checked');
   $('.radio_area_txt').removeClass("non");
 
});
        
//ラジオの”その他ボタン以外のボタン”を押した時    
$('.radio_area_radio').not("#other_label").click(function(){
      $('.radio_area_txt').attr("name","");
         $('.radio_area_radio radio').attr("name","cont");    
         $('.radio_area_txt').addClass("non");    
});
    
    
//エラーページのサブミットをクリックしたときに現在のページ数をinputに挿入、更に任意のページに戻る
$('.page_form .submit').click(function(){ 

        
    var cur_num = $('.slick-counter .current').html();
   // $('.page_input').val(cur_num);
    
    $(this).parent(".page_form").find('.page_input').val(cur_num);
    
    var action = $(this).parent(".page_form").attr("action");

    $(this).parent(".page_form").attr("action",action+"&p_num="+cur_num);
});

    
    
    
    
    
    
    
    
       $(".del_btn_group input[type=checkbox]").click(function () {
            $(".del_btn").addClass("non");
           
            // li要素をループ処理
            $(".del_btn_group input[type=checkbox]").each(function() {

      if ($(this).prop("checked") == true) {
              //alert('チェックされた');
              $(".del_btn").removeClass("non");
          } else {
         //alert('チェックされてない');
        
          }
      
      
      
 
  });
           

        }) 
       
       
  $('#search-text').click(function() {
    $(window).scrollTop(0);
});

    
$("#search-text").focus(function(){
    $(".main_body").addClass("top_vis");
  }).blur(function(){
    
    var val =  $(this).val();
    
if (val.length == 0) {
 
    $(".main_body").removeClass("top_vis");
}
    
   
  });
       

});




//サーチボックス
$(function () {
  searchWord = function(){
    var searchText = $(this).val(), // 検索ボックスに入力された値
        targetText;
      
      
    
      

    $('.list_body .box').each(function() {
      targetText = $(this).find(".title").text();

      // 検索対象となるリストに入力された文字列が存在するかどうかを判断
      if (targetText.indexOf(searchText) != -1) {
        $(this).removeClass('non');
          
            $(".book_mark_box").removeClass('non');
      } else {
        $(this).addClass('non');
          
            $(".book_mark_box").addClass('non');
      }
    });
  };

  // searchWordの実行
  $('#search-text').on('input', searchWord);
});    

$(document).ready(function () {

$(function(){
  $('a[href^="#"]').click(function(){
    var speed = 500;
    var href= $(this).attr("href");
    var target = $(href == "#" || href == "" ? 'html' : href);
    var position = target.offset().top;
    var position2 = 5000;
    
    $("html, body").animate({scrollTop:position2}, speed, "swing");
    return false;
  });
});

});



$(function(){
  $('#side_nav+label+.a_total+.a_total_bg,.side_nav a').click(function(){

$("#side_nav+label").trigger('click');
  });
});    





