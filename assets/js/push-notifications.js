// ===== HealthDial Web Push Notifications =====
// Permission flow: location first → then notification banner → subscribe

(function () {
    'use strict';

    var SUBSCRIBED_KEY  = 'hd_push_subscribed_v1';
    var DISMISSED_KEY   = 'hd_push_dismissed_v1';
    var BANNER_ID       = 'hd-notif-banner';
    var locationFired   = false;
    var bannerShown     = false;

    // ---- Register service worker as early as possible ----
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function (e) {
            console.warn('[Push] SW registration failed:', e.message);
        });
    }

    // ---- Support check ----
    function isSupported() {
        return 'serviceWorker' in navigator &&
               'PushManager'   in window &&
               'Notification'  in window;
    }

    // ---- Convert VAPID public key to Uint8Array ----
    function urlBase64ToUint8Array(b64) {
        var padding = '='.repeat((4 - b64.length % 4) % 4);
        var base64  = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw     = atob(base64);
        var arr     = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    // ---- Save subscription to backend ----
    function saveSubscription(sub) {
        var config = window.HD_PUSH_CONFIG;
        if (!config || !config.saveEndpoint) return Promise.resolve();
        var data = sub.toJSON();
        return fetch(config.saveEndpoint, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({
                endpoint : data.endpoint,
                p256dh   : data.keys.p256dh,
                auth     : data.keys.auth
            })
        }).catch(function (e) {
            console.warn('[Push] Save failed:', e.message);
        });
    }

    // ---- Subscribe the browser to push ----
    function subscribeUser() {
        var config = window.HD_PUSH_CONFIG;
        if (!config || !config.vapidPublicKey) {
            console.warn('[Push] VAPID public key not configured');
            return Promise.resolve();
        }

        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                if (existing) return saveSubscription(existing);
                return reg.pushManager.subscribe({
                    userVisibleOnly    : true,
                    applicationServerKey: urlBase64ToUint8Array(config.vapidPublicKey)
                }).then(function (sub) {
                    return saveSubscription(sub);
                });
            });
        }).then(function () {
            localStorage.setItem(SUBSCRIBED_KEY, '1');
            console.log('[Push] Subscribed');
        }).catch(function (e) {
            console.warn('[Push] Subscribe error:', e.message);
        });
    }

    // ---- Inject banner CSS (once) ----
    function injectStyles() {
        if (document.getElementById('hd-push-css')) return;
        var css = [
            '#hd-notif-banner{',
            '  position:fixed;top:74px;left:50%;transform:translateX(-50%);',
            '  z-index:999;width:calc(100% - 32px);max-width:560px;',
            '  background:rgba(15,23,42,0.96);border:1px solid rgba(255,255,255,0.10);',
            '  border-radius:14px;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);',
            '  box-shadow:0 4px 24px rgba(0,0,0,0.40);padding:14px 16px;',
            '  animation:hdPushSlideIn 0.38s cubic-bezier(.22,1,.36,1) both;',
            '}',
            '[data-theme="light"] #hd-notif-banner{',
            '  background:rgba(255,255,255,0.97);border-color:rgba(0,0,0,0.08);',
            '}',
            '@keyframes hdPushSlideIn{from{transform:translateX(-50%) translateY(-120%);opacity:0}to{transform:translateX(-50%) translateY(0);opacity:1}}',
            '@keyframes hdPushSlideOut{from{transform:translateX(-50%) translateY(0);opacity:1}to{transform:translateX(-50%) translateY(-120%);opacity:0}}',
            '.hd-push-inner{display:flex;align-items:center;gap:12px;}',
            '.hd-push-icon{',
            '  width:38px;height:38px;border-radius:10px;flex-shrink:0;',
            '  background:linear-gradient(135deg,#2563eb,#10b981);',
            '  display:flex;align-items:center;justify-content:center;',
            '  color:#fff;font-size:1rem;',
            '}',
            '.hd-push-text{flex:1;min-width:0;}',
            '.hd-push-text strong{display:block;font-size:0.86rem;font-weight:700;color:#f1f5f9;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
            '[data-theme="light"] .hd-push-text strong{color:#0f172a;}',
            '.hd-push-text span{font-size:0.76rem;color:#94a3b8;}',
            '[data-theme="light"] .hd-push-text span{color:#64748b;}',
            '.hd-push-actions{display:flex;gap:8px;flex-shrink:0;}',
            '.hd-push-allow{',
            '  padding:7px 15px;border-radius:99px;border:none;cursor:pointer;',
            '  background:linear-gradient(135deg,#2563eb,#10b981);color:#fff;',
            '  font-size:0.80rem;font-weight:700;font-family:inherit;',
            '  box-shadow:0 3px 10px rgba(37,99,235,0.35);transition:transform 0.15s,box-shadow 0.15s;white-space:nowrap;',
            '}',
            '.hd-push-allow:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(37,99,235,0.50);}',
            '.hd-push-later{',
            '  padding:7px 11px;border-radius:99px;border:1px solid rgba(255,255,255,0.12);',
            '  background:transparent;color:#94a3b8;font-size:0.80rem;font-weight:600;',
            '  cursor:pointer;font-family:inherit;transition:color 0.15s,border-color 0.15s;white-space:nowrap;',
            '}',
            '[data-theme="light"] .hd-push-later{border-color:rgba(0,0,0,0.12);color:#64748b;}',
            '.hd-push-later:hover{color:#f1f5f9;border-color:rgba(255,255,255,0.25);}',
            '@media(max-width:480px){',
            '  #hd-notif-banner{top:64px;left:0;right:0;width:100%;max-width:100%;border-radius:0;border-left:none;border-right:none;transform:translateX(0);}',
            '  @keyframes hdPushSlideIn{from{transform:translateY(-120%);opacity:0}to{transform:translateY(0);opacity:1}}',
            '  @keyframes hdPushSlideOut{from{transform:translateY(0);opacity:1}to{transform:translateY(-120%);opacity:0}}',
            '  .hd-push-text strong{font-size:0.82rem;}',
            '  .hd-push-text span{font-size:0.72rem;}',
            '  .hd-push-allow{padding:6px 12px;font-size:0.78rem;}',
            '  .hd-push-later{padding:6px 10px;font-size:0.78rem;}',
            '}',
        ].join('');

        var el = document.createElement('style');
        el.id = 'hd-push-css';
        el.textContent = css;
        document.head.appendChild(el);
    }

    // ---- Dismiss banner with slide-down animation ----
    function removeBanner() {
        var b = document.getElementById(BANNER_ID);
        if (!b) return;
        b.style.animation = 'hdPushSlideOut 0.28s ease forwards';
        setTimeout(function () { if (b.parentNode) b.parentNode.removeChild(b); }, 300);
    }

    // ---- Show the custom notification permission banner ----
    function showNotificationBanner() {
        if (bannerShown) return;
        if (!isSupported()) return;
        if (localStorage.getItem(SUBSCRIBED_KEY)) {
            // Already subscribed — silently re-subscribe (handles token refresh)
            if (Notification.permission === 'granted') subscribeUser();
            return;
        }
        if (localStorage.getItem(DISMISSED_KEY)) return;
        if (Notification.permission === 'denied') return;
        if (document.getElementById(BANNER_ID)) return;

        // If browser already granted permission (e.g. returning user), subscribe immediately
        if (Notification.permission === 'granted') {
            subscribeUser();
            return;
        }

        bannerShown = true;
        injectStyles();

        var banner = document.createElement('div');
        banner.id = BANNER_ID;
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-label', 'Enable notifications');
        banner.innerHTML = [
            '<div class="hd-push-inner">',
            '  <div class="hd-push-icon"><i class="fas fa-bell"></i></div>',
            '  <div class="hd-push-text">',
            '    <strong>Get nearby health updates</strong>',
            '    <span>Enable notifications to stay informed</span>',
            '  </div>',
            '  <div class="hd-push-actions">',
            '    <button class="hd-push-allow" id="hdPushAllow">Allow</button>',
            '    <button class="hd-push-later" id="hdPushLater">Later</button>',
            '  </div>',
            '</div>',
        ].join('');

        document.body.appendChild(banner);

        document.getElementById('hdPushAllow').addEventListener('click', function () {
            removeBanner();
            Notification.requestPermission().then(function (permission) {
                if (permission === 'granted') {
                    subscribeUser();
                } else {
                    localStorage.setItem(DISMISSED_KEY, '1');
                }
            });
        });

        document.getElementById('hdPushLater').addEventListener('click', function () {
            removeBanner();
            localStorage.setItem(DISMISSED_KEY, '1');
        });
    }

    // ---- Trigger: listen for location result event from listings.js ----
    document.addEventListener('hd:locationresult', function () {
        locationFired = true;
        setTimeout(showNotificationBanner, 1500);
    });

    // ---- Fallback: make sure the banner still gets a chance even if the location
    //      result never arrived (e.g. the user ignored the location prompt). Shows
    //      once the page has had time to settle, regardless of the location flow. ----
    window.addEventListener('load', function () {
        setTimeout(function () {
            if (!bannerShown) showNotificationBanner();
        }, 12000);
    });

})();
