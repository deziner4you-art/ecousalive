/*
=====================================================
ECO A+ PRO — pwa.js
Service worker registration + PWA install banner.
Depends on: nothing (self-contained)
=====================================================
*/

if('serviceWorker' in navigator){
    window.addEventListener('load', function(){
        navigator.serviceWorker.register(PUBLIC_URL + '/assets/sw.js')
            .then(function(){ console.log('SW Registered'); })
            .catch(function(error){ console.log('SW Error:', error); });
    });
}

var deferredPrompt;
window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault();
    deferredPrompt = e;
    var banner = document.getElementById('install-banner');
    if(banner) banner.style.display = 'flex';
});

function installApp(){
    if(!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function(){
        deferredPrompt = null;
        var banner = document.getElementById('install-banner');
        if(banner) banner.style.display = 'none';
    });
}
