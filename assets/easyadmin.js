import './styles/easyadmin.scss';

window.addEventListener('load', function(event) {
    
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => CookieConsent.setCookie("user", "necessary", getUser(), 30*24*3600));
});

//
// Apply bootstrap form validation
window.addEventListener('load', function(event) {

    //
    // Dock
    // $('#online-users li').resizeOnApproach({
    //     y: 0,
    //     split: 1,
    //     zoom: 2,
    //     jump: ,
    //     trigger: 1
    //   });
});