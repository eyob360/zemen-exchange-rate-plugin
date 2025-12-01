jQuery(document).ready(function ($) {
  var toggleButton = $('#exr-toggle-table-btn');
  var tableContainer = $('.exr-table-container');
  var toggleExchange = $('.exr-toggle-exchange');
  var toggleClose = $('.exr-toggle-close');

  toggleButton.on('click', function (e) {
      e.stopPropagation(); // Prevent click event from bubbling up
      tableContainer.slideToggle(300);
      toggleExchange.toggle();
      toggleClose.toggle();
  });

  $(document).on('keydown', function (e) {
      if (e.key === 'Escape' || e.keyCode === 27) {
          if (tableContainer.is(':visible')) {
              tableContainer.slideUp(300);
              toggleExchange.show();
              toggleClose.hide();
          }
      }
  });

  $(document).on('click', function (e) {
      if (!$(e.target).closest('.exr-table-container').length && !$(e.target).is(toggleButton)) {
          if (tableContainer.is(':visible')) {
              tableContainer.slideUp(300);
              toggleExchange.show();
              toggleClose.hide();
          }
      }
  });
});