(function ($, mw) {

    var specialPage = "Special:CustomRecentChanges";

    var filters = {
        title: specialPage,
        action: 'render'
    };

    $document = $(document);
    $body = $('body');
    $list = $('.rc-list');
    $toggleBtn = $('.rc-toggle-list');
    $namespaceLink = $('.rc-namespaces-links li a');
    $dropdowns = $('.rc-dropdowns select');

    // On open changes list
    $document.on('click', '.rc-toggle-list', function (e) {
        // Discard native browser events
        e.preventDefault();

        // Toggle classes
        $(this).toggleClass('opened');
        $(this).closest('.rc-action').find('.rc-list').toggleClass('opened');
    });

    // On namespace change
    $namespaceLink.on('click', function (e) {
        // Stop browser native event
        e.preventDefault();

        // Add focus style on selected namespace
        $namespaceLink.removeClass('active');
        $(this).addClass('active');

        // Change namespace
        filters['namespace'] = $(this).data('id');
        // Load result
        applyFilters();
    });

    // On dropdowns change
    $dropdowns.on('change', function (e) {
        var name = $(this).attr('name');
        // Change the correct filter according to the select name
        filters[name] = $(this).val();
        // Load result with filters changed
        applyFilters();
    });

    $document.on({
        ajaxStart: function() {$body.addClass("rc-loading");},
        ajaxStop: function() {$body.removeClass("rc-loading");}
    });
    
    
    function applyFilters() {
        console.log(filters);
        // Execute request
        $.ajax({
            url: mw.config.get('wgScript'),
            method: "GET",
            data: filters
        }).done(function (html) {
            output(html);
        }).fail(function (jqXHR) {
            if(jqXHR.status === 404)
                output(jqXHR.responseText);
        });
    }

    function output(html) {
        var dom = $('<output>').append($.parseHTML(html));
        $list.html($('.rc-list', dom).html());
    }

})(jQuery, mediaWiki);