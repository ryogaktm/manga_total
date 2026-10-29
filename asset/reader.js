/*
 * 自炊本ビューア 本体（slick の代わり）
 *  - 右→左に読み進める（雑誌・漫画と同じ向き）
 *  - キーボード ← で次 / → で前（どこにフォーカスがあっても有効）
 *  - 巻の最後で次へ → 次の巻の1ページ目、巻の最初で前へ → 前の巻
 *  - PC（横長の広い画面）では見開き表示：表紙(1ページ目)だけ単ページ、以降は2ページずつ
 *    横長の画像（もともと見開きでスキャンされたページ）は1枚で表示
 *  - スマホなど縦長の画面では、横長の画像を「右半分 → 左半分」の順に1画面ずつ大きく表示
 *    （ページ右下の「全体表示／半分ずつ」ボタンで、そのページだけ切り替え可能）
 *  - ピンチ・中央ダブルタップで拡大、拡大中はドラッグで移動、端までいって更にドラッグでページめくり
 *  - 表示中の周辺だけ画像を読み込み、遠いページは解放する
 */
(function () {
    'use strict';

    // ===== 設定 =====
    var CONFIG = {
        PRELOAD_AHEAD: 3,        // 先読みする画面数（次方向）※見開き時は1画面=2ページ
        PRELOAD_BEHIND: 2,       // 先読みする画面数（前方向）
        LIVE_AHEAD: 3,           // 実際に画面として残す数（次方向）。これより遠い画面は描画しない
        LIVE_BEHIND: 2,          // 同（前方向）
        UNLOAD_DISTANCE: 5,      // これより離れた画面の画像はメモリから外す
        PREV_VOL_PAGE: 'first',  // 前の巻へ戻ったときのページ: 'first'=1ページ目 / 'last'=最終ページ
        WARM_NEXT_AT: 5,         // 巻の残りページがこの数になったら次の巻を裏で準備
        SWIPE_THRESHOLD: 50,     // スワイプと判定する距離(px)
        SAVE_DELAY: 1200,        // ページ停止後、閲覧履歴を自動保存するまでの時間(ms)
        SPREAD_MIN_WIDTH: 900,   // 見開きを自動でオンにする画面幅（横長の画面のみ）
        SPLIT_MIN_GAIN: 1.25,    // 横長画像を半分に割った方が、これ以上大きく見える画面でだけ自動で割る
        ZOOM_MAX: 4,             // 最大の拡大率（画面にぴったり収めた状態を1とする）
        DOUBLE_TAP_ZOOM: 2.5,    // ダブルタップした時の拡大率
        DOUBLE_TAP_MS: 300,      // ダブルタップと判定する間隔(ms)
        EDGE_TURN_PX: 80,        // 拡大中、端でさらにこの距離ドラッグするとページをめくる
        LOAD_TIMEOUT: 15000,     // この時間たっても読み込み終わらない画像は中断してやり直す(ms)
        LOAD_RETRIES: 4          // 自動やり直しの回数(1秒→2秒→4秒…と間隔を延ばす)
    };

    var dataEl = document.getElementById('reader-data');
    var viewer = document.getElementById('rd_viewer');
    var track = document.getElementById('rd_track');
    if (!dataEl || !viewer || !track) { return; }

    var D = JSON.parse(dataEl.textContent);
    var pages = D.pages || [];
    var total = pages.length;
    var cur = Math.min(Math.max(D.start || 0, 0), Math.max(total - 1, 0)); // 現在の画面の先頭ページ
    var leaving = false;

    var spreads = [];      // 画面ごとのページ番号の配列 例: [[0],[1,2],[3],[3],[4,5]...]（[3],[3]は横長ページを割った2画面）
    var spreadParts = [];  // 画面ごとの「割った時の部分」 null / 'R'(右半分) / 'L'(左半分)
    var pageToSpread = []; // ページ番号 → そのページの最初の画面
    var slides = [];       // 画面ごとの要素（._zoom と ._halves を持つ）
    var curSpread = 0;

    var $ = function (sel) { return document.querySelector(sel); };
    var $$ = function (sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); };
    var clamp = function (v, lo, hi) { return Math.min(hi, Math.max(lo, v)); };

    var counterCur = $$('.slick-counter .current');
    var counterTo = $$('.slick-counter .current_to');
    var counterTotal = $$('.slick-counter .total');
    var seek = document.getElementById('rd_seek');
    var toastEl = document.getElementById('rd_toast');
    var splitBtn = document.getElementById('rd_split_btn');

    // ===== ページごとの「割る／割らない」の手動設定（巻ごとにブラウザへ記憶） =====
    var ovKey = 'rd_split:' + D.title + '/' + D.vol;
    var ov = {};   // ページ番号 → 1(割る) / 0(割らない)。無ければ画面サイズで自動判定
    try { ov = JSON.parse(localStorage.getItem(ovKey) || '{}') || {}; } catch (e) { ov = {}; }
    function saveOv() { try { localStorage.setItem(ovKey, JSON.stringify(ov)); } catch (e) { } }

    function viewW() { return viewer.clientWidth || window.innerWidth; }
    function viewH() { return viewer.clientHeight || window.innerHeight; }

    // ===== 見開きは画面サイズで自動切り替え（横長でSPREAD_MIN_WIDTH以上のPC画面） =====
    function wideScreen() {
        return window.innerWidth >= CONFIG.SPREAD_MIN_WIDTH && window.innerWidth > window.innerHeight;
    }
    function canSpread() {
        return window.innerWidth > window.innerHeight; // 縦長の画面では見開きにしない
    }
    function spreadOn() {
        if (!canSpread()) { return false; }
        return wideScreen();
    }
    function isWide(i) {
        var p = pages[i];
        return !!(p && p.w && p.h && p.w > p.h);
    }

    // 横長の画像を割ると、この画面でどれだけ大きく見えるか。十分大きくなる時だけ自動で割る
    function autoSplit(i) {
        var p = pages[i];
        var W = viewW(), H = viewH();
        var whole = Math.min(W / p.w, H / p.h);
        var half = Math.min(W / (p.w / 2), H / p.h);
        return half >= whole * CONFIG.SPLIT_MIN_GAIN;
    }
    function isSplit(i, double) {
        if (double || !isWide(i)) { return false; }   // PCの見開き表示では割らない
        if (ov[i] !== undefined) { return ov[i] === 1; }
        return autoSplit(i);
    }

    // ===== 画面（スライド）の組み立て =====
    function buildSpreads(double) {
        var list = [];
        spreadParts = [];
        var add = function (sp, part) { list.push(sp); spreadParts.push(part || null); };
        if (!double) {
            for (var i = 0; i < total; i++) {
                if (isSplit(i, false)) { add([i], 'R'); add([i], 'L'); }   // 右半分 → 左半分の順
                else { add([i]); }
            }
            return list;
        }
        if (total > 0) { add([0]); }                       // 表紙は単ページ
        var k = 1;
        while (k < total) {
            if (isWide(k)) { add([k]); k++; continue; }
            if (k + 1 < total && !isWide(k + 1)) { add([k, k + 1]); k += 2; }
            else { add([k]); k++; }
        }
        return list;
    }

    // 画面の作りが変わるかの目印（これが変わったら組み立て直す）
    function layoutSig() {
        var d = spreadOn();
        var s = d ? 'D' : 'S';
        for (var i = 0; i < total; i++) { s += (isWide(i) && isSplit(i, d)) ? '1' : '0'; }
        return s;
    }

    var currentSig = null;
    function rebuild() {
        var double = spreadOn();
        currentSig = layoutSig();
        spreads = buildSpreads(double);
        pageToSpread = [];
        spreads.forEach(function (sp, si) {
            sp.forEach(function (p) { if (pageToSpread[p] === undefined) { pageToSpread[p] = si; } });
        });

        track.innerHTML = '';
        slides = [];
        Z = { s: 1, x: 0, y: 0 };
        spreads.forEach(function (sp, si) {
            var part = spreadParts[si];
            var s = document.createElement('div');
            s.className = 'rd_page' + (sp.length === 2 ? ' is-double' : '');
            // 右→左：画面が進むほど左に並べる
            s.style.transform = 'translate3d(' + (-si * 100) + '%,0,0)';
            var z = document.createElement('div');   // 拡大・移動はこの中身だけに掛ける（画面の並びとは別）
            z.className = 'rd_zoom';
            s.appendChild(z);
            s._zoom = z;
            s._halves = [];
            sp.forEach(function (p, j) {
                var h = document.createElement('div');
                h._p = p;
                // 見開きでは若いページ番号が右側（のど側に寄せて表示）
                h.className = 'rd_half' + (sp.length === 2 ? (j === 0 ? ' is-right' : ' is-left') : '');
                if (part) {
                    // 横長画像の右半分／左半分だけを見せる枠
                    h.classList.add('is-split');
                    var c = document.createElement('div');
                    c.className = 'rd_crop ' + (part === 'R' ? 'is-r' : 'is-l');
                    c.style.setProperty('--r', (pages[p].w / 2 / pages[p].h).toFixed(5));
                    h.appendChild(c);
                    h._crop = c;
                }
                z.appendChild(h);
                s._halves.push(h);
            });
            track.appendChild(s);
            slides.push(s);
        });

        document.documentElement.classList.toggle('rd_is_spread', double);
        goTo(cur, false, true);
    }

    // ===== 画像の読み込み・解放（画面ごと） =====
    function loadHalf(h, priority) {
        // 読み込み失敗のまま残っている画面は、表示対象になったときにやり直す
        if (h._img && h.classList.contains('is-error') && priority === 'high') { unloadHalf(h); }
        if (h._img) { return; }
        var pg = pages[h._p];
        var host = h._crop || h;
        if (pg.thumb) { host.style.backgroundImage = 'url("' + pg.thumb + '")'; } // 小さい画像を先に出しておく
        h.classList.add('is-loading');
        var tries = 0;

        function attempt() {
            var img = new Image();
            img.decoding = 'async';
            if ('fetchPriority' in img) { img.fetchPriority = priority; }
            img.alt = '';
            var stale = function () { return h._img !== img; };
            var done = function () {
                if (stale()) { return; }
                clearTimeout(h._timer);
                h.classList.remove('is-loading', 'is-error');
                h.classList.add('is-loaded');
            };
            var fail = function () {
                if (stale()) { return; }
                clearTimeout(h._timer);
                img.onload = img.onerror = null;
                if (tries < CONFIG.LOAD_RETRIES) {
                    // 回線が不安定なとき(外出先など)は少し待ってやり直す
                    var wait = 1000 * Math.pow(2, tries);
                    tries++;
                    h._img = null;
                    if (img.parentNode) { img.parentNode.removeChild(img); }
                    img.removeAttribute('src');
                    h._timer = setTimeout(function () { if (!h._img && h._alive) { attempt(); } }, wait);
                } else {
                    h.classList.remove('is-loading');
                    h.classList.add('is-error');
                }
            };
            img.onload = function () {
                clearTimeout(h._timer);
                // 先にデコード(絵に変換する重い処理)まで済ませておくと、めくった瞬間に引っかからない
                if (img.decode) { img.decode().then(done, done); } else { done(); }
            };
            img.onerror = fail;
            // 待っても終わらない読み込み(途中で止まった通信)は打ち切ってやり直す
            h._timer = setTimeout(fail, CONFIG.LOAD_TIMEOUT);
            img.src = tries ? pg.src + (pg.src.indexOf('?') < 0 ? '?' : '&') + 'r=' + tries : pg.src;
            host.appendChild(img);
            h._img = img;
        }

        h._alive = true;
        attempt();
    }
    function unloadHalf(h) {
        h._alive = false;
        clearTimeout(h._timer);
        if (!h._img) { h.classList.remove('is-loading', 'is-loaded', 'is-error'); return; }
        var host = h._crop || h;
        h._img.onload = h._img.onerror = null;
        h._img.removeAttribute('src');
        if (h._img.parentNode) { h._img.parentNode.removeChild(h._img); }
        h._img = null;
        host.style.backgroundImage = '';
        h.classList.remove('is-loading', 'is-loaded', 'is-error');
    }
    function loadSpread(si, priority) {
        if (si < 0 || si >= slides.length) { return; }
        slides[si]._halves.forEach(function (h) { loadHalf(h, priority); });
    }
    function unloadSpread(si) {
        slides[si]._halves.forEach(unloadHalf);
    }
    function updateWindow() {
        // 今の画面と前後だけ「存在する」ことにして、それ以外は描画対象から外す
        for (var si = 0; si < slides.length; si++) {
            var far = si < curSpread - CONFIG.LIVE_BEHIND || si > curSpread + CONFIG.LIVE_AHEAD;
            if (slides[si]._far !== far) { slides[si]._far = far; slides[si].classList.toggle('is-far', far); }
        }
        // 表示中 → めくり先（前後1画面）→ その先 の順に読み込む
        loadSpread(curSpread, 'high');
        loadSpread(curSpread + 1, 'high');
        loadSpread(curSpread - 1, 'high');
        for (var d = 2; d <= CONFIG.PRELOAD_AHEAD; d++) { loadSpread(curSpread + d, 'low'); }
        for (var b = 2; b <= CONFIG.PRELOAD_BEHIND; b++) { loadSpread(curSpread - b, 'low'); }
        for (var k = 0; k < slides.length; k++) {
            if (Math.abs(k - curSpread) > CONFIG.UNLOAD_DISTANCE) { unloadSpread(k); }
        }
    }

    // ===== 表示位置 =====
    function setTrack(offsetPx, animate) {
        track.style.transition = animate ? '' : 'none';
        var base = curSpread * 100;
        track.style.transform = offsetPx
            ? 'translate3d(calc(' + base + '% + ' + offsetPx + 'px),0,0)'
            : 'translate3d(' + base + '%,0,0)';
    }

    function lastPageOfSpread() {
        var sp = spreads[curSpread] || [cur];
        return sp[sp.length - 1];
    }

    function updateSplitBtn() {
        if (!splitBtn) { return; }
        var show = !!spreads.length && !spreadOn() && isWide(cur);
        splitBtn.style.display = show ? '' : 'none';
        if (show) { splitBtn.textContent = spreadParts[curSpread] ? '全体表示' : '半分ずつ'; }
    }

    function updateUI() {
        var n = cur + 1;
        var last = lastPageOfSpread() + 1;
        var part = spreadParts[curSpread];
        counterCur.forEach(function (el) { el.textContent = n; });
        counterTo.forEach(function (el) {
            el.textContent = part ? (part === 'R' ? '(右)' : '(左)') : (last > n ? '-' + last : '');
        });
        if (seek && document.activeElement !== seek) { seek.value = n; }
        var old = $('.p_num_list .cur_box');
        if (old) { old.classList.remove('cur_box'); }
        var box = $('.p_num_list .box' + n);
        if (box) { box.classList.add('cur_box'); }
        updateSplitBtn();
    }

    function hideBars() {
        $$('.main_slide .main_hd, .main_slide .main_ft').forEach(function (el) { el.classList.add('op0'); });
    }
    function toggleBars() {
        $$('.main_slide .main_hd, .main_slide .main_ft').forEach(function (el) { el.classList.toggle('op0'); });
    }

    // 画面を指定して移動
    function goToScreen(si, animate, silent) {
        if (total === 0 || !slides.length) { return; }
        si = clamp(si, 0, spreads.length - 1);
        var prevSi = curSpread;
        var changed = si !== curSpread || spreads[si][0] !== cur;
        if (si !== prevSi && slides[prevSi] && Z.s !== 1) {
            // 前の画面の拡大は、めくる動きに合わせて元に戻す
            var pz = slides[prevSi]._zoom;
            pz.style.transition = 'transform .28s cubic-bezier(.2,.7,.3,1)';
            pz.style.transform = '';
        }
        Z = { s: 1, x: 0, y: 0 };
        curSpread = si;
        cur = spreads[si][0];
        setTrack(0, animate !== false);
        updateUI();
        updateWindow();
        if (changed && !silent) {
            hideBars();
            scheduleSave();
            maybeWarmNext();
        }
    }
    // ページ番号を指定して移動（そのページの最初の画面へ）
    function goTo(page, animate, silent) {
        if (total === 0) { return; }
        page = clamp(page, 0, total - 1);
        goToScreen(pageToSpread[page], animate, silent);
    }
    function goSpread(si) { goToScreen(si); }

    // ===== 拡大・移動 =====
    var Z = { s: 1, x: 0, y: 0 };   // 拡大率と移動量（今の画面の中身に掛かる）
    function isZoomed() { return Z.s > 1.001; }
    function zoomEl() { var sl = slides[curSpread]; return sl && sl._zoom; }

    // 今の画面で「絵が実際に写っている範囲」（拡大前の座標）。余白の黒い部分までは動かさないために使う
    function contentBox() {
        var sl = slides[curSpread];
        var b = { x0: Infinity, y0: Infinity, x1: -Infinity, y1: -Infinity };
        var add = function (x0, y0, x1, y1) {
            b.x0 = Math.min(b.x0, x0); b.y0 = Math.min(b.y0, y0);
            b.x1 = Math.max(b.x1, x1); b.y1 = Math.max(b.y1, y1);
        };
        if (sl) {
            sl._halves.forEach(function (h) {
                var hx = h.offsetLeft, hy = h.offsetTop, hw = h.offsetWidth, hh = h.offsetHeight;
                var pg = pages[h._p];
                var a = (pg && pg.w && pg.h) ? pg.w / pg.h : 0;
                if (!a || !hw || !hh) { add(hx, hy, hx + hw, hy + hh); return; }
                if (h._crop) { a = a / 2; }
                var fw = Math.min(hw, hh * a), fh = fw / a;
                var fx = hx + (h.classList.contains('is-right') ? 0 : (h.classList.contains('is-left') ? hw - fw : (hw - fw) / 2));
                var fy = hy + (hh - fh) / 2;
                add(fx, fy, fx + fw, fy + fh);
            });
        }
        if (b.x0 === Infinity) { b = { x0: 0, y0: 0, x1: viewW(), y1: viewH() }; }
        return b;
    }
    // 片方向の移動量の制限。絵が画面より大きければ端まで、小さければ動かさない
    function axisClamp(t, lo, hi, size, s) {
        var len = (hi - lo) * s;
        if (len <= size) { return (lo + hi) / 2 * (1 - s); }
        return Math.min(-lo * s, Math.max(size - hi * s, t));
    }
    function clampZ(s, x, y) {
        var b = contentBox();
        return { x: axisClamp(x, b.x0, b.x1, viewW(), s), y: axisClamp(y, b.y0, b.y1, viewH(), s) };
    }
    function applyZ(animate) {
        var el = zoomEl();
        if (!el) { return; }
        el.style.transition = animate ? 'transform .22s cubic-bezier(.2,.7,.3,1)' : 'none';
        el.style.transform = (Z.s === 1 && !Z.x && !Z.y) ? '' : 'translate3d(' + Z.x + 'px,' + Z.y + 'px,0) scale(' + Z.s + ')';
    }
    // 指定した点（画面座標）を動かさないように拡大率を変える
    function setZoom(s, fx, fy, animate) {
        s = clamp(s, 1, CONFIG.ZOOM_MAX);
        var k = s / Z.s;
        var c = clampZ(s, fx - (fx - Z.x) * k, fy - (fy - Z.y) * k);
        Z = { s: s, x: c.x, y: c.y };
        applyZ(animate);
    }
    function resetZoom(animate) {
        Z = { s: 1, x: 0, y: 0 };
        applyZ(animate);
    }
    function snapZ() {
        var c = clampZ(Z.s, Z.x, Z.y);
        Z.x = c.x; Z.y = c.y;
        applyZ(true);
    }

    // 拡大中のドラッグ。端では抵抗をつけて、その分を overX/overY に覚えておく
    var panOrigin = { x: 0, y: 0 };
    var over = { x: 0, y: 0 };
    function panTo(rx, ry) {
        var c = clampZ(Z.s, rx, ry);
        over.x = rx - c.x;
        over.y = ry - c.y;
        Z.x = c.x + over.x / 3;
        Z.y = c.y + over.y / 3;
        applyZ(false);
    }
    // ドラッグを終えた時：端でさらに大きく引いていたらページをめくる。そうでなければ元の位置へ戻す
    function endPan() {
        var ox = over.x;
        over = { x: 0, y: 0 };
        if (Math.abs(ox) > CONFIG.EDGE_TURN_PX) {
            var before = curSpread;
            if (ox > 0) { next(); } else { prev(); }   // 右へ引いて端 → 次（スワイプと同じ向き）
            if (curSpread !== before || leaving) { return; }
        }
        snapZ();
    }

    // ===== 巻をまたぐ移動 =====
    function toast(msg, ms) {
        if (!toastEl) { return; }
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        clearTimeout(toast._t);
        if (ms !== 0) { toast._t = setTimeout(function () { toastEl.classList.remove('show'); }, ms || 1400); }
    }
    function leaveTo(url, msg) {
        if (leaving) { return; }
        leaving = true;
        saveNow();
        toast(msg, 0);
        location.href = url;
    }
    function next() {
        if (leaving) { return; }
        if (curSpread < spreads.length - 1) { goSpread(curSpread + 1); return; }
        if (D.nextUrl) { leaveTo(D.nextUrl, '次の巻へ…'); }
        else { setTrack(0, true); toast('最終巻の最後のページです'); }
    }
    function prev() {
        if (leaving) { return; }
        if (curSpread > 0) { goSpread(curSpread - 1); return; }
        if (D.prevUrl) {
            var url = D.prevUrl + (CONFIG.PREV_VOL_PAGE === 'last' ? '&p_num=last' : '');
            leaveTo(url, '前の巻へ…');
        } else { setTrack(0, true); toast('最初のページです'); }
    }

    // ===== 次の巻を裏で準備 =====
    var warmed = false;
    function maybeWarmNext() {
        if (warmed || !D.nextUrl || total - 1 - lastPageOfSpread() > CONFIG.WARM_NEXT_AT) { return; }
        warmed = true;
        if (!window.fetch || !window.DOMParser) { return; }
        fetch(D.nextUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var el = doc.getElementById('reader-data');
                if (!el) { return; }
                var nd = JSON.parse(el.textContent);
                (nd.pages || []).slice(0, 3).forEach(function (p) { var im = new Image(); im.src = p.src; });
            })
            .catch(function () { });
    }

    // ===== 閲覧履歴の自動保存 =====
    var saveTimer = null;
    var lastSaved = -1;
    function saveNow() {
        clearTimeout(saveTimer);
        if (!D.saveUrl || cur === lastSaved) { return; }
        lastSaved = cur;
        var body = new URLSearchParams();
        body.append('manga_title', D.title);
        body.append('vol', D.vol);
        body.append('page', String(cur + 1));
        if (navigator.sendBeacon) { navigator.sendBeacon(D.saveUrl, body); }
        else if (window.fetch) { fetch(D.saveUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }); }
    }
    function scheduleSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveNow, CONFIG.SAVE_DELAY);
    }
    window.addEventListener('pagehide', saveNow);
    window.addEventListener('online', function () { updateWindow(); });

    function popupOpen() {
        return $$('.pop_box').some(function (el) { return !el.classList.contains('off_box'); });
    }

    // ===== キーボード =====
    document.addEventListener('keydown', function (e) {
        var t = e.target;
        var tag = t && t.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (t && t.isContentEditable)) { return; }
        if (e.altKey || e.ctrlKey || e.metaKey) { return; }
        if (popupOpen()) { return; }
        if (e.key === 'ArrowLeft' || e.keyCode === 37) { e.preventDefault(); next(); }
        else if (e.key === 'ArrowRight' || e.keyCode === 39) { e.preventDefault(); prev(); }
        else if (e.key === '+' || e.key === '=') { e.preventDefault(); setZoom(Z.s * 1.4, viewW() / 2, viewH() / 2, true); }
        else if (e.key === '-' || e.key === '_') { e.preventDefault(); setZoom(Z.s / 1.4, viewW() / 2, viewH() / 2, true); }
        else if (e.key === '0') { e.preventDefault(); resetZoom(true); }
    });

    // ===== タップ／クリック：左側=次へ、右側=前へ、中央=メニュー（中央をダブルタップで拡大） =====
    var suppressClick = false;
    var suppressTimer = null;
    function suppress() {
        suppressClick = true;
        clearTimeout(suppressTimer);
        suppressTimer = setTimeout(function () { suppressClick = false; }, 400);
    }
    var lastTap = { t: 0, x: 0, y: 0 };
    var barTimer = null;
    viewer.addEventListener('click', function (e) {
        if (suppressClick) { return; }
        var rect = viewer.getBoundingClientRect();
        var px = e.clientX - rect.left, py = e.clientY - rect.top;
        var x = px / rect.width;
        var zone = x < 0.3 ? 'next' : (x > 0.7 ? 'prev' : 'center');
        var now = Date.now();
        var dbl = now - lastTap.t < CONFIG.DOUBLE_TAP_MS && Math.hypot(px - lastTap.x, py - lastTap.y) < 40;
        lastTap = dbl ? { t: 0, x: 0, y: 0 } : { t: now, x: px, y: py };

        if (isZoomed()) {
            // 拡大中：ダブルタップで元に戻す。1回タップではページをめくらない（メニューの表示切替だけ）
            if (dbl) { clearTimeout(barTimer); resetZoom(true); }
            else if (zone === 'center') { clearTimeout(barTimer); barTimer = setTimeout(toggleBars, CONFIG.DOUBLE_TAP_MS - 50); }
            return;
        }
        if (zone === 'center') {
            if (dbl) { clearTimeout(barTimer); setZoom(CONFIG.DOUBLE_TAP_ZOOM, px, py, true); }
            else { clearTimeout(barTimer); barTimer = setTimeout(toggleBars, CONFIG.DOUBLE_TAP_MS - 50); }
            return;
        }
        // 左右は待たずに即めくる（連続タップで素早くめくれるように）
        if (zone === 'next') { next(); } else { prev(); }
    });

    // ===== タッチ：1本指=スワイプ（拡大中は移動）、2本指=ピンチ =====
    var mode = 'none';   // 'swipe' | 'pan' | 'pinch' | 'none'
    var sx = 0, sy = 0, dx = 0, st = 0, horizontal = false, moved = false;
    var pinch = null;
    function dist(t) { return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY); }
    function mid(t) { return { x: (t[0].clientX + t[1].clientX) / 2, y: (t[0].clientY + t[1].clientY) / 2 }; }
    function startPinch(touches) {
        mode = 'pinch';
        moved = true;
        var m = mid(touches);
        pinch = { d: dist(touches) || 1, s: Z.s, x: Z.x, y: Z.y, mx: m.x, my: m.y };
        var el = zoomEl();
        if (el) { el.classList.add('is-gesture'); el.style.transition = 'none'; }
    }
    function endGesture() {
        var el = zoomEl();
        if (el) { el.classList.remove('is-gesture'); }
    }

    viewer.addEventListener('touchstart', function (e) {
        // 指が触れた時点で前後のページを確実に用意しておく
        loadSpread(curSpread + 1, 'high');
        loadSpread(curSpread - 1, 'high');
        if (e.touches.length >= 2) {
            if (mode === 'swipe') { dx = 0; setTrack(0, true); }
            startPinch(e.touches);
            return;
        }
        sx = e.touches[0].clientX; sy = e.touches[0].clientY;
        dx = 0; st = Date.now();
        horizontal = false; moved = false;
        if (isZoomed()) {
            mode = 'pan';
            panOrigin = { x: Z.x, y: Z.y };
            over = { x: 0, y: 0 };
            var el = zoomEl();
            if (el) { el.classList.add('is-gesture'); el.style.transition = 'none'; }
        } else {
            mode = 'swipe';
        }
    }, { passive: true });

    viewer.addEventListener('touchmove', function (e) {
        if (mode === 'pinch') {
            if (e.touches.length < 2) { return; }
            if (e.cancelable) { e.preventDefault(); }
            var m = mid(e.touches);
            var s = clamp(pinch.s * dist(e.touches) / pinch.d, 1, CONFIG.ZOOM_MAX);
            // 最初に2本指で挟んだ点が、今の2本指の中心に来るようにする
            var cx = (pinch.mx - pinch.x) / pinch.s, cy = (pinch.my - pinch.y) / pinch.s;
            var c = clampZ(s, m.x - cx * s, m.y - cy * s);
            Z = { s: s, x: c.x, y: c.y };
            applyZ(false);
            return;
        }
        if (e.touches.length !== 1) { return; }
        var t = e.touches[0];
        var mx = t.clientX - sx;
        var my = t.clientY - sy;
        if (mode === 'pan') {
            if (e.cancelable) { e.preventDefault(); }
            if (Math.abs(mx) > 6 || Math.abs(my) > 6) { moved = true; }
            panTo(panOrigin.x + mx, panOrigin.y + my);
            return;
        }
        if (mode !== 'swipe') { return; }
        if (Math.abs(mx) > 10 || Math.abs(my) > 10) { moved = true; }  // タップではない
        // 横に動いたら横スワイプ確定。縦に少しぶれていても無視しない
        if (!horizontal && Math.abs(mx) > 10 && Math.abs(mx) > Math.abs(my) * 0.5) { horizontal = true; }
        if (!horizontal) { return; }
        if (e.cancelable) { e.preventDefault(); }
        dx = mx;
        var atEdge = (dx > 0 && curSpread === spreads.length - 1 && !D.nextUrl) || (dx < 0 && curSpread === 0 && !D.prevUrl);
        setTrack(atEdge ? dx / 3 : dx, false);
    }, { passive: false });

    viewer.addEventListener('touchend', function (e) {
        // 指が動いたなら、直後に来るクリックは無視（タップ判定とのぶつかり防止）
        if (moved) { suppress(); }
        if (mode === 'pinch') {
            if (e.touches.length >= 2) { return; }
            endGesture();
            if (Z.s < 1.03) { resetZoom(true); } else { snapZ(); }
            if (e.touches.length === 1 && isZoomed()) {
                // 片方の指を離しても、残った指でそのまま移動を続けられる
                mode = 'pan';
                sx = e.touches[0].clientX; sy = e.touches[0].clientY;
                panOrigin = { x: Z.x, y: Z.y };
                over = { x: 0, y: 0 };
                var el = zoomEl();
                if (el) { el.classList.add('is-gesture'); el.style.transition = 'none'; }
            } else {
                mode = 'none';
            }
            return;
        }
        if (e.touches.length > 0) { return; }
        if (mode === 'pan') {
            mode = 'none';
            endGesture();
            endPan();
            return;
        }
        if (mode !== 'swipe') { mode = 'none'; return; }
        mode = 'none';
        if (!horizontal) { return; }
        var fast = Math.abs(dx) > 20 && Date.now() - st < 250;
        if (dx > CONFIG.SWIPE_THRESHOLD || (fast && dx > 0)) { next(); }
        else if (dx < -CONFIG.SWIPE_THRESHOLD || (fast && dx < 0)) { prev(); }
        else { setTrack(0, true); }
    });
    viewer.addEventListener('touchcancel', function () {
        var wasZoomGesture = mode === 'pan' || mode === 'pinch';
        mode = 'none';
        moved = true;
        endGesture();
        over = { x: 0, y: 0 };
        setTrack(0, true);
        if (wasZoomGesture) { if (Z.s < 1.03) { resetZoom(true); } else { snapZ(); } }
    });

    // iOS Safari のページ自体の拡大（ジェスチャーイベント）を止める
    ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (n) {
        document.addEventListener(n, function (e) { e.preventDefault(); }, { passive: false });
    });

    // ===== PC：Ctrl+ホイール（トラックパッドのピンチ）で拡大、拡大中はホイールとドラッグで移動 =====
    viewer.addEventListener('wheel', function (e) {
        if (popupOpen()) { return; }
        var rect = viewer.getBoundingClientRect();
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            var d = clamp(e.deltaY, -40, 40);
            setZoom(Z.s * Math.exp(-d * 0.01), e.clientX - rect.left, e.clientY - rect.top, false);
        } else if (isZoomed()) {
            e.preventDefault();
            var c = clampZ(Z.s, Z.x - e.deltaX, Z.y - e.deltaY);
            Z.x = c.x; Z.y = c.y;
            applyZ(false);
        }
    }, { passive: false });

    var mouseDown = null;
    viewer.addEventListener('mousedown', function (e) {
        if (e.button !== 0 || !isZoomed()) { return; }
        mouseDown = { x: e.clientX, y: e.clientY };
        panOrigin = { x: Z.x, y: Z.y };
        over = { x: 0, y: 0 };
        moved = false;
    });
    window.addEventListener('mousemove', function (e) {
        if (!mouseDown) { return; }
        var mx = e.clientX - mouseDown.x, my = e.clientY - mouseDown.y;
        if (Math.abs(mx) > 4 || Math.abs(my) > 4) { moved = true; }
        if (!moved) { return; }
        e.preventDefault();
        var el = zoomEl();
        if (el) { el.classList.add('is-gesture'); }
        panTo(panOrigin.x + mx, panOrigin.y + my);
    });
    window.addEventListener('mouseup', function () {
        if (!mouseDown) { return; }
        mouseDown = null;
        endGesture();
        if (moved) { suppress(); endPan(); }
    });

    // ===== ページバー =====
    if (seek) {
        seek.max = total;
        seek.addEventListener('input', function () {
            counterCur.forEach(function (el) { el.textContent = seek.value; });
            counterTo.forEach(function (el) { el.textContent = ''; });
        });
        seek.addEventListener('change', function () {
            goTo(parseInt(seek.value, 10) - 1, false);
            seek.blur();
        });
    }

    // ===== 「全体表示／半分ずつ」ボタン（横長ページを開いている時だけ表示） =====
    if (splitBtn) {
        splitBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            ov[cur] = spreadParts[curSpread] ? 0 : 1;   // 今割っているなら全体表示に、全体なら割る
            saveOv();
            rebuild();
            splitBtn.blur();
        });
    }

    // 画面サイズが変わったら、見開き／単ページ／割り表示を切り替え
    var resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (layoutSig() !== currentSig) { rebuild(); }
            else if (isZoomed()) { snapZ(); }
        }, 150);
    });

    // ===== ページ一覧（サムネイル） =====
    var thumbBox = $('.thumb_box');
    var thumbsLoaded = false;
    function loadThumbs() {
        if (thumbsLoaded || !thumbBox) { return; }
        thumbsLoaded = true;
        var boxes = thumbBox.querySelectorAll('[data-bg]');
        var set = function (el) { el.style.backgroundImage = 'url("' + el.getAttribute('data-bg') + '")'; el.removeAttribute('data-bg'); };
        if (!('IntersectionObserver' in window)) { Array.prototype.forEach.call(boxes, set); return; }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { set(en.target); io.unobserve(en.target); } });
        }, { root: thumbBox.querySelector('.pop_box_in'), rootMargin: '400px 0px' });
        Array.prototype.forEach.call(boxes, function (el) { io.observe(el); });
    }
    $$('.togle_list_btn[data-obj=".thumb_box"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            loadThumbs();
            setTimeout(function () {
                var c = thumbBox && thumbBox.querySelector('.cur_box');
                if (c && c.scrollIntoView) { c.scrollIntoView({ block: 'center' }); }
            }, 0);
        });
    });
    if (thumbBox) {
        thumbBox.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('.p_num_list .box') : null;
            if (!a) { return; }
            e.preventDefault();
            var n = parseInt(a.getAttribute('data-page'), 10);
            thumbBox.classList.add('off_box');
            var mb = $('.main_slide .main_body');
            if (mb) { mb.classList.remove('off_box'); }
            goTo(n - 1, false);
        });
    }

    // ===== 初期表示 =====
    counterTotal.forEach(function (el) { el.textContent = total; });
    track.style.transition = 'none';
    rebuild();
    requestAnimationFrame(function () { requestAnimationFrame(function () { track.style.transition = ''; }); });
    if (D.start > 0) { hideBars(); }
    maybeWarmNext();

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { leaving = false; if (toastEl) { toastEl.classList.remove('show'); } }
    });
})();
