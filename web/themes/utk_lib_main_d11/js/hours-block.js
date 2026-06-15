(function ($, Drupal, once) {
    'use strict';

    // Helper function to get the appropriate anchor for the hours page
    function getHoursAnchorUrl(libraryTitle) {
        const hoursPageUrl = '/hours';
        let anchor = '';

        // Map library titles to their corresponding anchor IDs on the hours page
        if (libraryTitle.includes('Hodges') || libraryTitle === 'Main Library') {
            anchor = 'hodges';
        } else if (libraryTitle.includes('Studio') || libraryTitle.includes('Makerspace')) {
            anchor = 'hodges'; // Studio falls under hodges
        } else if (libraryTitle.includes('Special Collections') || libraryTitle.includes('University Archives')) {
            anchor = 'hodges'; // Special Collections falls under hodges
        } else if (libraryTitle.includes('Pendergrass') || libraryTitle.includes('Agriculture') || libraryTitle.includes('Veterinary')) {
            anchor = 'pendergrass';
        } else if (libraryTitle.includes('DeVine') || libraryTitle.includes('Music')) {
            anchor = 'devine';
        }

        return hoursPageUrl + (anchor ? '#' + anchor : '');
    }

    // Helper function to get the appropriate details URL
    function getDetailsUrl(libraryTitle) {
        if (libraryTitle.includes('Hodges') || libraryTitle === 'Main Library') {
            return '/about/about-the-libraries';
        } else if (libraryTitle.includes('Studio') || libraryTitle.includes('Makerspace')) {
            return '/services/studio';
        } else if (libraryTitle.includes('Special Collections') || libraryTitle.includes('University Archives')) {
            return '/department/special';
        } else if (libraryTitle.includes('Pendergrass') || libraryTitle.includes('Agriculture') || libraryTitle.includes('Veterinary')) {
            return '/department/agvet';
        } else if (libraryTitle.includes('DeVine') || libraryTitle.includes('Music')) {
            return '/department/music';
        }

        return '#';
    }

    Drupal.behaviors.libraryHours = {
        attach: function (context, settings) {
            // Process once
            once('library-hours', '.container-fluid', context).forEach(function (element) {
                // Store all library items for this container
                const $container = $(element);
                const $libraryItems = $container.find('.library-item');

                // Set the first item as active on page load and load its image and information
                if ($libraryItems.length > 0) {
                    $libraryItems.removeClass('active');
                    const $firstItem = $libraryItems.first();
                    $firstItem.addClass('active');

                    // Get data from the first item
                    const firstImageUrl = $firstItem.data('image-url') || 'https://www.lib.utk.edu/sites/default/files/2019-08/placeholder-building-new.jpg';
                    const firstTitle = $firstItem.data('title') || $firstItem.find('.library-info .fw-medium').text();
                    const firstDescription = $firstItem.data('description') || '';
                    const firstAddress = $firstItem.data('address') || '';
                    const firstUrl = getDetailsUrl(firstTitle);
                    const hoursUrl = getHoursAnchorUrl(firstTitle);

                    // Set the image with appropriate alt text
                    $('#library-active-image').attr({
                        'src': firstImageUrl,
                        'alt': firstTitle + ' building image'
                    });

                    // Set the overlay information
                    $('#library-overlay-title').text(firstTitle);

                    // Create description text and add location icon for address
                    let firstDescriptionText = '';
                    let firstAddressWithIcon = '';

                    if (firstDescription) {
                        firstDescriptionText = firstDescription;
                    }
                    // Removed subtitle

                    // Add address with icon if available
                    if (firstAddress) {
                        // Create the location icon SVG with accessibility attributes
                        const locationIcon = '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="me-2" style="vertical-align: middle;" role="img" aria-labelledby="location-icon-title"><title id="location-icon-title">Location</title><path d="m18.157 16.882-1.187 1.174c-.875.858-2.01 1.962-3.406 3.312a2.25 2.25 0 0 1-3.128 0l-3.491-3.396c-.439-.431-.806-.794-1.102-1.09a8.707 8.707 0 1 1 12.314 0ZM14.5 11a2.5 2.5 0 1 0-5 0 2.5 2.5 0 0 0 5 0Z" fill="#fa8200"/></svg>';

                        // Set the address with icon
                        firstAddressWithIcon = locationIcon + ' ' + firstAddress;
                        firstDescriptionText = firstAddressWithIcon;
                    }

                    // Use html() instead of text() to render the SVG
                    $('#library-overlay-description').html(firstDescriptionText);
                    $('#library-overlay-link').attr('href', hoursUrl);
                    $('#library-overlay-details-link').attr('href', firstUrl);
                }

                // Add click and keyboard event to library items
                $libraryItems.on('click keypress', function (e) {
                    // Handle keyboard events - only trigger on Enter or Space
                    if (e.type === 'keypress' && !(e.which === 13 || e.which === 32)) {
                        return;
                    }

                    e.preventDefault();
                    e.stopPropagation();

                    // Get the data attributes
                    const $this = $(this);
                    const imageUrl = $this.data('image-url') || 'https://www.lib.utk.edu/sites/default/files/2019-08/placeholder-building-new.jpg';
                    const libraryTitle = $this.find('.library-info .fw-medium').text();

                    // Get additional data attributes if available
                    const libraryDataTitle = $this.data('title') || libraryTitle;
                    const libraryDescription = $this.data('description') || '';
                    const libraryAddress = $this.data('address') || '';
                    const hoursUrl = getHoursAnchorUrl(libraryDataTitle);

                    // Update active state for library items
                    $libraryItems.removeClass('active');
                    $this.addClass('active');

                    // Update the image in the right column with appropriate alt text
                    $('#library-active-image').attr({
                        'src': imageUrl,
                        'alt': libraryDataTitle + ' building image'
                    });

                    // Update the overlay information
                    $('#library-overlay-title').text(libraryDataTitle);

                    // Create description text and add location icon for address
                    let descriptionText = '';
                    let addressWithIcon = '';

                    if (libraryDescription) {
                        descriptionText = libraryDescription;
                    }

                    // Add address with icon if available
                    if (libraryAddress) {
                        // Create the location icon SVG with accessibility attributes
                        const locationIcon = '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="me-2" style="vertical-align: middle;" role="img" aria-labelledby="location-icon-click-title"><title id="location-icon-click-title">Location</title><path d="m18.157 16.882-1.187 1.174c-.875.858-2.01 1.962-3.406 3.312a2.25 2.25 0 0 1-3.128 0l-3.491-3.396c-.439-.431-.806-.794-1.102-1.09a8.707 8.707 0 1 1 12.314 0ZM14.5 11a2.5 2.5 0 1 0-5 0 2.5 2.5 0 0 0 5 0Z" fill="#fa8200"/></svg>';

                        // Set the address with icon
                        addressWithIcon = locationIcon + ' ' + libraryAddress;
                        descriptionText = addressWithIcon;
                    }

                    // Use html() instead of text() to render the SVG
                    $('#library-overlay-description').html(descriptionText);
                    $('#library-overlay-link').attr('href', hoursUrl);
                    $('#library-overlay-details-link').attr('href', getDetailsUrl(libraryDataTitle));
                });

                // Add a separate event for navigation (double click or specific button)
                $libraryItems.find('.library-link').on('dblclick', function (e) {
                    const url = $(this).data('url');
                    if (url) {
                        window.location.href = url;
                    }
                });
            });
        }
    };
})(jQuery, Drupal, once);