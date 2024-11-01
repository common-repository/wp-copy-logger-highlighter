;

var CPLH_ADMIN = {};

(function ( $ ) {

    /* Main object. Can also be seen in the DOM panel */
    CPLH_ADMIN = {
        init: function () {
            var _this = CPLH_ADMIN;

            /* These vars are passed by wp_localize_script in CPLH->logger_enqueue() */
            _this.wpAjax = typeof CPLH_VARS !== 'undefined' && CPLH_VARS.wp_ajax !== 'undefined' ? CPLH_VARS.wp_ajax : false;

            _this.$body = $( 'body' );

            _this.bindEvents();

            $("#highlight_color").iris({
                change: function(event, ui) {
                    $("#highlight_color_val").val(ui.color.toString());
                    $("#highlight_color").css("border-color", ui.color.toString());
                }
            });

        },
        bindEvents: function () {
            var _this = CPLH_ADMIN;

            // Bind the 'copy' event to our body
            _this.$body.on('click', ".load_copy_logs", function (e) {
                var returned_logs = _this.ajaxGetLogs();
                $("#display_logs").html(returned_logs);
            });
            _this.$body.on('click', ".del_copy_logs", function (e) {
                var conf = confirm("Are you sure you want to delete selected logs?");
                if(conf) {
                    _this.ajaxDelLogs();
                }
            });
            _this.$body.on('click', "#logsonoff", function(e) {
                if($(this).prop('checked')) {
                    $(".remlog").prop('checked', true);
                } else {
                    $(".remlog").prop('checked', false);
                }
            });
        },
        ajaxGetLogs: function () {
            var _this = CPLH_ADMIN;

            if (!_this.wpAjax) {
                console.error('[CPLH] WP ajax endpoint not defined');
                return;
            }
            console.log("POST: " + $("#cp_filter_post").val());
            $.ajax({
                    method: "POST",
                    url: _this.wpAjax,
                    dataType: 'JSON',
                    data: {
                        action: 'cplh_get_copied_logs', // Must match 'wp_ajax_cplh_get_copied_logs'
                        data: 'valid',
                        pid: $("#cp_filter_post").val()
                    }
                })

                .done(function (_data) {
                    console.log('[CPLH] Ajax response after sending text: ' + _data);
                })

                .always(function (_data) {
                    if (typeof _data === 'undefined' || typeof _data.success === 'undefined') {
                        console.error('[CPLH] Something went wrong trying to send the AJAX to WP', _data);
                    } else {
                        $("#display_logs").html("");
                        $("#display_logs").append("<li><input type='checkbox' id='logsonoff' /> <strong>Select All/None</strong> [<a href='#delSelected' class='del_copy_logs'>Delete Selected</a>]</li>");
                        for(var i=0;i<_data['data'].length;i++) {
                            var cplog = _data['data'][i];
                            $("#display_logs").append("<li id='log" + i + "'> <input type='checkbox' class='remlog' log='" + cplog['id'] + "' />" + cplog['highlighted'] + "</li>");
                        }
                    }
                });
        },
        ajaxDelLogs: function () {
            var _this = CPLH_ADMIN;
            var remlogs = $("input.remlog:checked").map(function() {return this.getAttribute('log');}).get().join(',');

            if (!_this.wpAjax) {
                console.error('[CPLH] WP ajax endpoint not defined');
                return;
            }
            $.ajax({
                    method: "POST",
                    url: _this.wpAjax,
                    dataType: 'JSON',
                    data: {
                        action: 'cplh_del_copied_logs',
                        ids: remlogs
                    }
                })

                .done(function (_data) {
                    console.log('[CPLH] Ajax response after sending text: ' + _data);
                })

                .always(function (_data) {
                    if (typeof _data === 'undefined' || typeof _data.success === 'undefined') {
                        console.error('[CPLH] Something went wrong trying to send the AJAX to WP', _data);
                    } else if(_data['success']) {
                        $("input.remlog:checked").each(function(){
                            $(this).parent().remove();
                        });
                    }
                });
        }
    }
    /* Let's roll! */
    CPLH_ADMIN.init();

})( jQuery );