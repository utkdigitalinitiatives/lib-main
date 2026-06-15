(function (Drupal) {
  'use strict';

  Drupal.behaviors.primoSearch = {
    attach: function (context, settings) {
      // Only run once and only if the search bar exists
      once('primo-search', '#utk--search-bar', context).forEach(function (primoSearch) {
        // Now we know the element exists, so we can safely interact with it
        let primoSearchInput = document.getElementById('search-bar-input'); // Default to One Search input

        let searchOption = "primo";
        const currentHref = window.location.href;

        const optionObject = {
          "primo": {
            url: 'https://utk.primo.exlibrisgroup.com/discovery/search?vid=01UTN_KNOXVILLE:01UTK&search_scope=MyInst_and_CI&tab=Everything&onCampus=false&group=GUEST&query=any,contains,',
            placeholder: "Search for books, articles, and more...",
            inputId: 'search-bar-input' // ID of the input field for One Search
          },
          "site": {
            url: `/search-results?search_api_fulltext=`,
            placeholder: "Search our site for services, spaces, and more...",
            inputId: 'site-search-input' // ID of the input field for Site Search
          },
        };

        primoSearch.addEventListener("submit", (e) => {
          // Check if the target is the outer form or a nested form
          if (e.target.id === 'site-search-form') {
            // Let the site search form handle its own submission
            return true;
          }

          // Handle OneSearch functionality
          if (searchOption === "primo") {
            e.preventDefault();

            let searchURL = "";
            let activeInputId = optionObject[searchOption].inputId;
            let searchVal = document.getElementById(activeInputId).value;

            searchURL = optionObject[searchOption].url + searchVal;
            if (searchVal.length > 0) {
              window.location.assign(searchURL);
            }
          } else if (searchOption === "site") {
            // For site search, redirect to the search results page
            e.preventDefault();

            let searchVal = document.getElementById('site-search-input').value;
            if (searchVal.length > 0) {
              window.location.assign(`/search-results?search_api_fulltext=${encodeURIComponent(searchVal)}`);
            }
          }
        });

        // Add these functions to the global scope if needed elsewhere
        window.setOneSearch = function () {
          searchOption = "primo";
          primoSearchInput = document.getElementById(optionObject[searchOption].inputId); // Update input field reference
          primoSearchInput.placeholder = optionObject[searchOption].placeholder;

          const advanced = document.getElementById('advanced-search-link-container');
          if (advanced) {
            advanced.classList.remove('d-none');
          }
        };

        window.setSiteSearch = function () {
          searchOption = "site";
          primoSearchInput = document.getElementById(optionObject[searchOption].inputId); // Update input field reference
          primoSearchInput.placeholder = optionObject[searchOption].placeholder;

          const advanced = document.getElementById('advanced-search-link-container');
          if (advanced) {
            advanced.classList.add('d-none');
          }
        };
      });
    }
  };
})(Drupal);