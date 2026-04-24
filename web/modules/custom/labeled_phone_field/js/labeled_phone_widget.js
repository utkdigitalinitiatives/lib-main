/**
 * @file
 * Handles input mask behavior for labeled phone field widget.
 */

(function () {
  "use strict";

  Drupal.behaviors.labeledPhoneWidget = {
    attach: function (context) {
      // Select all phone input elements within the context.
      const inputs = context.querySelectorAll(".labeled-phone-input");

      inputs.forEach(function (input) {
        // Use once() to ensure behavior is attached only once.
        if (input.getAttribute("data-labeled-phone-processed")) {
          return;
        }
        input.setAttribute("data-labeled-phone-processed", "true");

        /**
         * Formats a phone number string to XXX-XXX-XXXX format.
         *
         * @param {string} value
         *   The phone value (may be digits only or partially formatted).
         * @return {string}
         *   The formatted phone value as XXX-XXX-XXXX.
         */
        function formatPhoneDisplay(value) {
          // Extract only digits.
          const digits = value.replace(/\D/g, "");
          // Limit to 10 digits.
          const limited = digits.substring(0, 10);
          // Format as XXX-XXX-XXXX only if we have the right number of digits.
          if (limited.length === 10) {
            return limited.replace(/^(\d{3})(\d{3})(\d{4})$/, "$1-$2-$3");
          }
          return limited;
        }

        /**
         * Handle input event to apply formatting as user types.
         */
        function handleInput(e) {
          const formatted = formatPhoneDisplay(e.target.value);
          e.target.value = formatted;
        }

        /**
         * Handle paste event to normalize pasted content.
         */
        function handlePaste(e) {
          e.preventDefault();
          const pastedText = e.clipboardData.getData("text/plain");
          const formatted = formatPhoneDisplay(pastedText);
          e.target.value = formatted;
          // Trigger change event to notify form of update.
          e.target.dispatchEvent(new Event("change", { bubbles: true }));
        }

        /**
         * Handle change event to ensure value is normalized.
         */
        function handleChange(e) {
          const formatted = formatPhoneDisplay(e.target.value);
          e.target.value = formatted;
        }

        // Attach event listeners.
        input.addEventListener("input", handleInput);
        input.addEventListener("paste", handlePaste);
        input.addEventListener("change", handleChange);
      });
    },
  };
})();
