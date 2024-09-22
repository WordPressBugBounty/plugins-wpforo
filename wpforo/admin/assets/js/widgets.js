jQuery.ajaxSetup({
    url: ajaxurl,
    data:{
        referer: window.location.origin + window.location.pathname
    }
});

jQuery( document ).ready( function($){
    var body = $( 'body' );
    body.on( 'change', '.wpf_wdg_limit_per_topic', {}, function () {
        var wrap = $(this).parents('.wpf_wdg_form_wrap')
        var disabled = $(this).val() > 0
        $('.wpf_wdg_orderby', wrap).attr('disabled', disabled)
        $('.wpf_wdg_order', wrap).attr('disabled', disabled)
    });

    body.on( 'change', '.wpf_wdg_boardid', {}, function(){
        var $this = $( this );
        var wrap = $this.closest( '.wpf-wdg-wrapper' );
        var forumids_dd = $( '.wpf_wdg_forumids', wrap );
        var boardid = $this.val();
        forumids_dd.empty();

        $.ajax({
            type: 'POST',
            url: ajaxurl + '?wpforo_boardid=' + boardid,
            data: {
                action: 'wpforo_get_forum_tree'
            }
        }).done( function( r ){
            if( r.success ){
                forumids_dd.html( r.data['html'] );
            }
        } );
    } );
} );
