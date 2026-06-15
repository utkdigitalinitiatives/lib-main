(function ($) {
  'use strict';
  
  Drupal.behaviors.libcalHours = {
    attach: function (context, settings) {
      once('libcalHours', '[data-libcal-id]', context).forEach(function(element) {
        const locationId = element.getAttribute('data-libcal-id');
        const institutionId = element.getAttribute('data-libcal-iid') || 968;
        
        try {
          var weekWidget = new $.LibCalWeeklyGrid($(element), { 
            iid: institutionId,
            lid: locationId,
            systemTime: false 
          });
        } catch(e) {
          console.error('Error initializing LibCal widget:', e);
        }
      });
    }
  };
})(jQuery);