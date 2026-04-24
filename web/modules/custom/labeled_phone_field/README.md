# Labeled Phone Field

Provides a reusable Drupal field type for storing multiple phone numbers with labels.

## Features

- Multi-value field supporting unlimited phone entries
- Each entry contains a label (e.g., "Sales", "Service") and a phone number
- US phone numbers only (10 digits)
- Input mask behavior: `XXX-XXX-XXXX` format while typing
- Normalizes stored values to digits-only for consistency (`5555551234`)
- Server-side validation enforcing 10-digit US phone numbers
- Default formatter for semantic HTML rendering

## Usage

1. Create a field of type "Labeled Phone" on any entity bundle
2. Configure cardinality (typically Unlimited)
3. Use the provided widget which auto-formats phone input
4. Use the provided formatter or override in Twig

## Field Schema

Each field item stores:

- `label` (string, max 255 chars) - e.g., "Main", "Sales", "Support"
- `phone` (string, digits-only) - e.g., "5555551234"

## Twig Usage Example

```twig
{% for item in node.field_department_phones %}
  <div class="phone-entry">
    <span class="label">{{ item.label }}</span>
    <a href="tel:{{ item.phone }}">
      {{ item.phone|format_phone }}
    </a>
  </div>
{% endfor %}
```

Or use the default formatter which handles formatting automatically.
