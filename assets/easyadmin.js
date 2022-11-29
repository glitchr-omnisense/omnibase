import './styles/easyadmin.scss';

// import Tooltip from 'bootstrap';
// $(window).on("load.tooltip", function() { $('[data-toggle="tooltip"]').tooltip({html:true}) });
// $(window).on("onbeforeunload.tooltip",function() { $("div[id^='tooltip']").hide().remove(); });
// import Popover from 'bootstrap';
// $(window).on("onbeforeunload.popover",function() { $("div[id^='popover']").hide().remove(); });
// $(window).on("load.popover", function() { $('[data-toggle="popover"]').popover({html:true}) });

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


{/* <script>
$(function () {
    $('[data-toggle="popover"]').popover({html:true})
    $('[data-toggle="tooltip"]').tooltip({trigger:"manual", html:true})

    $('[data-toggle="tooltip"]').on('click.persistent', function () { $(this).tooltip('toggle'); });
    $('[data-toggle="tooltip"]').on('mouseleave.persistent', function ()
    {
        $(this).data("hover", false);
        setTimeout(function () {
            if(!$(this).data("hover") && !$("#"+this.getAttribute("aria-describedby")).data("hover"))  {
                $("#"+this.getAttribute("aria-describedby")).off('mouseenter.persistent');
                $("#"+this.getAttribute("aria-describedby")).off('mouseleave.persistent');
                $(this).tooltip('hide');
            }
        }.bind(this), 500);
    });

    $('[data-toggle="tooltip"]').on('mouseenter.persistent', function ()
    {
        var that = this;
        $(this).data("hover", true);
        if(!$(this).attr("aria-describedby")) {

            $(this).tooltip('show');
            $("#"+this.getAttribute("aria-describedby")).on('mouseenter.persistent', function () { $(this).data("hover", true ); });
            $("#"+this.getAttribute("aria-describedby")).on('mouseleave.persistent', function () { $(this).data("hover", false); $(that).trigger("mouseleave.persistent"); });
        }

        tooltip = $('[data-toggle="tooltip"]').not(this);
        tooltip.tooltip('hide');
    });
});
</script> */}