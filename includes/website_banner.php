<?php
function get_website_banners(string $page, string $position = 'top'): array
{
    $conn = getDbConnection();
    if (!$conn) return [];

    $conn->query("CREATE TABLE IF NOT EXISTS website_banners (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        title       VARCHAR(255)  DEFAULT NULL,
        page        ENUM('home','explore','promotion','add_listing') NOT NULL,
        position    ENUM('top','bottom') NOT NULL DEFAULT 'top',
        image_url   VARCHAR(500)  NOT NULL,
        link_url    VARCHAR(500)  DEFAULT NULL,
        sort_order  INT           NOT NULL DEFAULT 0,
        status      TINYINT(1)    NOT NULL DEFAULT 1,
        created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare(
        "SELECT title, image_url, link_url
         FROM website_banners
         WHERE page = ? AND position = ? AND status = 1
         ORDER BY sort_order ASC, id DESC"
    );
    $stmt->bind_param("ss", $page, $position);
    $stmt->execute();
    $result = $stmt->get_result();
    $banners = [];
    while ($row = $result->fetch_assoc()) {
        $banners[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $banners;
}

function render_website_banner(string $page, string $position = 'top'): void
{
    $banners = get_website_banners($page, $position);

    if (empty($banners)) return;

    $count  = count($banners);
    $uid    = 'wbc_' . substr(md5($page . $position . $count), 0, 7);
    $single = $count === 1;
    ?>
<div class="wb-carousel-wrap">
    <div class="wb-carousel<?= $single ? ' wb-single' : '' ?>" id="<?= $uid ?>" data-count="<?= $count ?>">
        <div class="wb-track">
            <?php foreach ($banners as $i => $b): ?>
            <div class="wb-slide<?= $i === 0 ? ' wb-active' : '' ?>">
                <?php if (!empty($b['link_url'])): ?>
                <a href="<?= htmlspecialchars($b['link_url']) ?>" target="_blank" rel="noopener">
                    <img src="<?= htmlspecialchars($b['image_url']) ?>"
                         alt="<?= htmlspecialchars($b['title'] ?: 'Banner') ?>" loading="lazy" />
                </a>
                <?php else: ?>
                <img src="<?= htmlspecialchars($b['image_url']) ?>"
                     alt="<?= htmlspecialchars($b['title'] ?: 'Banner') ?>" loading="lazy" />
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$single): ?>
        <button class="wb-btn wb-prev" onclick="wbPrev('<?= $uid ?>')" aria-label="Previous">&#8249;</button>
        <button class="wb-btn wb-next" onclick="wbNext('<?= $uid ?>')" aria-label="Next">&#8250;</button>
        <div class="wb-dots">
            <?php for ($i = 0; $i < $count; $i++): ?>
            <span class="wb-dot<?= $i === 0 ? ' wb-dot-active' : '' ?>"
                  onclick="wbGoTo('<?= $uid ?>',<?= $i ?>)"></span>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
    <?php

    // CSS + JS output once per page load
    static $wbAssetsOutput = false;
    if (!$wbAssetsOutput) {
        $wbAssetsOutput = true;
        ?>
<style>
.wb-carousel-wrap{max-width:1200px;margin:0 auto 40px;padding:0 20px;}
.wb-carousel{position:relative;overflow:hidden;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.10);}
.wb-track{display:flex;transition:transform .45s cubic-bezier(.4,0,.2,1);will-change:transform;}
.wb-slide{flex-shrink:0;overflow:hidden;}
.wb-slide img,.wb-slide a img{width:100%;height:360px;object-fit:fill;display:block;border-radius:16px;}
.wb-slide a{display:block;}
.wb-btn{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.45);color:#fff;
    border:none;border-radius:50%;width:40px;height:40px;font-size:24px;cursor:pointer;z-index:2;
    opacity:0;transition:opacity .2s;display:flex;align-items:center;justify-content:center;padding:0;}
.wb-carousel:hover .wb-btn{opacity:1;}
.wb-prev{left:12px;}
.wb-next{right:12px;}
.wb-dots{position:absolute;bottom:10px;left:50%;transform:translateX(-50%);display:flex;gap:6px;z-index:2;}
.wb-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.55);cursor:pointer;
    transition:background .2s,transform .2s;}
.wb-dot-active{background:#fff;transform:scale(1.3);}
@media(max-width:768px){
    .wb-carousel-wrap{padding:0 12px;margin-bottom:28px;}
    .wb-slide img,.wb-slide a img{height:110px;object-fit:cover;border-radius:12px;}
    .wb-carousel{border-radius:12px;}
    .wb-btn{width:28px;height:28px;font-size:16px;}
}
</style>
<script>
var _wbState={};
function wbInit(id,count,interval){
    var el=document.getElementById(id);if(!el)return;
    var w=el.offsetWidth;
    el.querySelectorAll('.wb-slide').forEach(function(s){s.style.width=w+'px';});
    el.querySelector('.wb-track').style.width=(w*count)+'px';
    _wbState[id]={cur:0,count:count,timer:null};
    if(count>1&&interval){_wbState[id].timer=setInterval(function(){wbNext(id);},interval);}
}
function wbGoTo(id,idx){
    var s=_wbState[id];if(!s)return;
    s.cur=(idx+s.count)%s.count;
    var el=document.getElementById(id);
    var w=el.offsetWidth;
    el.querySelector('.wb-track').style.transform='translateX(-'+(s.cur*w)+'px)';
    el.querySelectorAll('.wb-dot').forEach(function(d,i){d.classList.toggle('wb-dot-active',i===s.cur);});
    if(s.timer){clearInterval(s.timer);s.timer=setInterval(function(){wbNext(id);},4000);}
}
function wbNext(id){var s=_wbState[id];if(s)wbGoTo(id,s.cur+1);}
function wbPrev(id){var s=_wbState[id];if(s)wbGoTo(id,s.cur-1);}
window.addEventListener('resize',function(){
    Object.keys(_wbState).forEach(function(id){
        var s=_wbState[id];var el=document.getElementById(id);
        if(s&&el){
            var w=el.offsetWidth;
            el.querySelectorAll('.wb-slide').forEach(function(sl){sl.style.width=w+'px';});
            el.querySelector('.wb-track').style.width=(w*s.count)+'px';
            el.querySelector('.wb-track').style.transform='translateX(-'+(s.cur*w)+'px)';
        }
    });
});
</script>
        <?php
    }

    ?>
<script>wbInit('<?= $uid ?>',<?= $count ?>,4000);</script>
    <?php
}
?>
