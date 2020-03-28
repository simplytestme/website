(function ($, Drupal, drupalSettings) {

  function doProvision(instance_id) {
    $.ajax({
      url: drupalSettings.basePath + 'tugboat/provision/' + instance_id,
      dataType: "json",
      timeout: 1000 * 60 * 30, // 30 minutes
      success: function( data ) {}
    });
  }

  Drupal.behaviors.simplytest_tugboat = {
    attach: function (context, settings) {
      if (drupalSettings.simplytest_tugboat !== undefined) {
        var progressbar = $('.simplytest-progress-bar', context);
        var reload = function (first_load) {
          first_load = first_load || false;

          $.ajax({
            url: drupalSettings.basePath + 'tugboat/status/' + drupalSettings.simplytest_tugboat.id + '/state',
            dataType: "json",
            success: function( data ) {
              // If the current state is equal to the ENQUEUE state.
              if (first_load && data.do_provision === true) {
                doProvision(drupalSettings.simplytest_tugboat.id);
              }

              $('.bar .filled', progressbar).stop().animate({
                width: data.percent + '%'
              }, 1000);
              $('.percentage', progressbar).html(data.percent + '%');
              $('.message', progressbar).html(data.message);
              $('.log', progressbar).html(data.log);
              if (data.percent == 100) {

                // Putting in an intentional delay to let the tugboat API return the URL and STM to process it.
                // Redirect to the tugboat "go to" page to get the tugboat URL.
                window.location.href = drupalSettings.basePath + 'tugboat/goto/' + drupalSettings.simplytest_tugboat.id;;
              } else if (data.percent < 100){
                setTimeout(reload, 2000);
              }
            }
          });
        }
        reload(true);
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
