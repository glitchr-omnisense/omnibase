import $ from 'jquery';
import './styles/easyadmin-async.scss';

var spinnerTimeout = setTimeout(function () { $(".content").addClass("spinner"); }, 1000);
$(window).on("load", function (e) {

    $(".content").addClass("spinner");
    $(".spinner").addClass("spinner loaded");
    clearTimeout(spinnerTimeout);
});

//
// Apply bootstrap form validation
window.addEventListener('load', function (event) {

    $("form :input").on("change", function () {
    // Reactivate button when a form is changed
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    $("form :input").on("input", function () {
    // Reactivate button when a form is changed
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    // Input event is not working sometimes for select2
    const observer = new MutationObserver(() => {
        $(".page-actions button").removeAttr("disabled").removeClass("disabled");
    });

    $("form select").each(function () {
        observer.observe(this, {subtree: true, childList: true});
    });
    
    // Look for <a data-ea-filter-scheme="..."> buttons
    const html = document.documentElement;

    // Helper to apply or remove filter
    function applyFilter(scheme) {
        switch (scheme) {
            case 'mono':
                html.style.filter = 'grayscale(1)';
                break;
            case 'sepia':
                html.style.filter = 'sepia(1)';
                break;
            case 'none':
            default:
                html.style.filter = '';
        }
        localStorage.setItem('ea-filter-scheme', scheme);
        updateEyeDropperIcons(scheme);
    }

    // Update <i class="fa-solid fa-droplet"> icon based on filter state
    function updateEyeDropperIcons(scheme) {
        $('i.fa-solid.fa-droplet, i.fa-solid.fa-droplet-slash').each(function () {
            if (scheme && scheme !== 'none') {
                $(this).removeClass('fa-droplet').addClass('fa-droplet-slash');
            } else {
                $(this).removeClass('fa-droplet-slash').addClass('fa-droplet');
            }
        });
    }

    // On page load, check localStorage first
    let stored = localStorage.getItem('ea-filter-scheme');
    if (stored) {
        applyFilter(stored);
    } else {
        updateEyeDropperIcons('none');
    }

    // Listen for filter scheme button clicks
    $(document).on('click', '[data-ea-filter-scheme]', function (e) {
        e.preventDefault();
        const scheme = $(this).attr('data-ea-filter-scheme');
        let current = localStorage.getItem('ea-filter-scheme');
        if (current === scheme) {
            // Toggle off if already active
            applyFilter('none');
        } else {
            applyFilter(scheme);
        }
    });
});