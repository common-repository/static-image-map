(function($) {

    $( "div#static-image-map div.tt a" ).tooltip({ 
        position: {
            my: "left+10 bottom",
            at: "center top",
            collision: "fit"
        },
        disabled: true
    });

    $( "div#static-image-map div.tt a" ).on({
        "click" : function(){
            $(this).tooltip('enable').tooltip( "open" );
        },
        "mouseout" : function(){
            $(this).tooltip('disable');
        }
    });

})(jQuery);