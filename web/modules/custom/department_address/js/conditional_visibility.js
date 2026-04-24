/**
 * @file
 * Drupal behavior for conditional visibility of the Address fieldset.
 *
 * Hides/shows the entire Address fieldset wrapper based on the state of the
 * "Add Address" checkbox. This works in conjunction with Drupal #states
 * on individual fields to provide a complete hiding of the section.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Handles visibility of the Address fieldset wrapper based on checkbox state.
   */
  Drupal.behaviors.departmentAddressVisibility = {
    attach: function (context) {
      const checkboxes = once(
        'department-address-visibility',
        'input[name="field_add_address[value]"]',
        context
      );

      if (checkboxes.length === 0) {
        return;
      }

      const $checkbox = checkboxes[0];
      const $form = $checkbox.closest('form');

      if (!$form) {
        return;
      }

      // Find the fieldset wrapper for the address group.
      // Field Group wraps fields in a fieldset with an ID like "edit-group-address".
      let $fieldsetWrapper = $form.querySelector(
        'fieldset[id*="group-address"], [data-drupal-selector*="group-address"]'
      );

      // If not found by that selector, try finding it by the presence of address field children.
      if (!$fieldsetWrapper) {
        const $firstAddressField = $form.querySelector(
          'input[name="field_address_street[0][value]"]'
        );
        if ($firstAddressField) {
          // Walk up the DOM tree to find the fieldset.
          let current = $firstAddressField;
          while (current && current !== $form) {
            if (current.tagName === 'FIELDSET') {
              $fieldsetWrapper = current;
              break;
            }
            current = current.parentElement;
          }
        }
      }

      if (!$fieldsetWrapper) {
        // Unable to find fieldset, but the field-level #states will still work.
        return;
      }

      /**
       * Updates fieldset wrapper visibility based on checkbox state.
       */
      const updateFieldsetVisibility = function () {
        if ($checkbox.checked) {
          $fieldsetWrapper.style.display = '';
        } else {
          $fieldsetWrapper.style.display = 'none';
        }
      };

      // Set initial visibility based on checkbox state.
      updateFieldsetVisibility();

      // Listen for changes to the checkbox.
      $checkbox.addEventListener('change', updateFieldsetVisibility);

      // Also listen for state change events in case the checkbox is changed
      // programmatically (e.g., via Drupal states).
      $checkbox.addEventListener('stateChange', updateFieldsetVisibility);
    },
  };
})(Drupal, once);
