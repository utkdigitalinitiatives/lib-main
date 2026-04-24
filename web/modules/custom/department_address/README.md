# Department Address Visibility Module

## Overview

This module provides conditional visibility for the Department address field group based on the "Add Address" checkbox field.

## Functionality

When content editors work with the Department node form:

- The "Add Address" checkbox (displayed as "Yes/No") is shown at weight 8
- The "Address" fieldset group is shown at weight 9, containing:
  - Street address field
  - City field
  - State field
  - ZIP code field

### Behavior

**When "Add Address" is OFF:**

- All address fields are hidden using Drupal Forms API `#states`
- The Address fieldset wrapper is also hidden via custom JavaScript behavior

**When "Add Address" is ON:**

- All address fields become visible immediately (via JavaScript)
- Users can enter/edit address information

## Implementation Details

### Components

1. **Hook Implementation** (`department_address.module`):
   - Uses `hook_form_node_form_alter()` to target Department node forms only
   - Applies `#states` conditional visibility to individual address fields
   - Attempts to apply `#states` to the field group wrapper as primary method
   - Falls back to field-level hiding if group-level hiding is unreliable

2. **Selector Configuration**:
   - Monitor element: `:input[name="field_add_address[value]"]` (the boolean checkbox)
   - Visibility condition: `['checked' => TRUE]` (shows when checkbox is checked)

3. **Enhanced UX** (`js/conditional_visibility.js`):
   - Custom Drupal behavior that hides the entire fieldset wrapper
   - Listens for checkbox changes and state change events
   - Provides seamless hiding/showing of the Address section

4. **Library Definition** (`department_address.libraries.yml`):
   - Defines the JavaScript behavior library
   - Dependencies: `core/drupal` and `core/once`

## Installation

```bash
ddev exec drush en department_address
```

## Configuration

No configuration required. The module automatically applies to all Department node forms.

## Field Group Preservation

The existing Field Group configuration for `group_address` is preserved:

- The fieldset remains a fieldset
- The field structure remains unchanged
- Only visibility behavior is added

## Compatibility

- Drupal 11
- Field Group module (required, already installed)

## Developer Notes

- Uses only Form API `#states` and vanilla JavaScript (Drupal behaviors)
- No custom configuration or database entries required
- Purely form-level enhancement, no data storage changes
