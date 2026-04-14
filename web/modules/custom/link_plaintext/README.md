# Link Plaintext Formatter

A custom Drupal 11 module that provides a new field formatter for Link fields.

## Overview

This module adds a **"Link title + URL (plain text)"** formatter for the Link field type. It renders the link title and URL as plain text, without HTML anchor tags or any other markup.

**Use Case:** When you need to display link information as plain text (e.g., in feeds, exports, or specific UX scenarios) rather than as clickable anchors.

## Features

- **Plain-text output:** No HTML anchors; all text is auto-escaped by Twig.
- **Three separator modes:** Single space (default), newline (on separate lines), or custom text.
- **Themeable:** Uses a Twig template; title and URL are separate, targetable components with CSS classes.
- **Configurable:** Adjust separator, absolute URLs, and element wrapping via the formatter UI.
- **Handles all cases:** Works with empty titles, multi-value fields, internal and external links.

## Installation

1. **Place the module:** Copy the `link_plaintext` folder to `web/modules/custom/` (or wherever your custom modules are located).

2. **Enable the module:**
   ```bash
   ddev exec drush en link_plaintext
   ```
   Or via the Drupal admin UI at `/admin/modules`.

3. **Configure:** Go to the entity's display settings > find a Link field > select **"Link title + URL (plain text)"** formatter > configure settings > save.

## Settings

| Setting | Default | Notes |
|---------|---------|-------|
| **Separator Mode** | Single space | **Single space** (same line), **Newline** (separate lines), or **Custom** (custom text) |
| **Custom Separator** | ` — ` | Text between title/URL when mode=Custom. Blank = space. Examples: ` | `, ` → ` |
| **Force Absolute URLs** | ✓ On | Convert internal links to absolute URLs (e.g., `https://example.com/node/123`) |
| **Wrap Parts** | ✓ On | Wrap title/URL in elements with CSS classes (spans for space/custom, divs for newline). Divs always used in newline mode for proper line breaks. |

## Example Output

**Space Mode (default, wrap parts on):**
```html
<span class="link-plaintext__title">Read more</span><span class="link-plaintext__separator"> </span><span class="link-plaintext__url">https://example.com/article</span>
```
→ `Read more https://example.com/article`

**Newline Mode:**
```html
<div class="link-plaintext__title">Read more</div>
<div class="link-plaintext__url">https://example.com/article</div>
```
→ Displays on two lines (divs are block-level)

**Custom Separator " — ":**
```html
<span class="link-plaintext__title">Read more</span><span class="link-plaintext__separator"> — </span><span class="link-plaintext__url">https://example.com/article</span>
```
→ `Read more — https://example.com/article`

**Empty Title:** Only URL is output (no separator).

## Theming & Customization

**Template:** `templates/link-plaintext-formatter.html.twig`

**Variables:** `title`, `url`, `separator`, `separator_mode`, `wrap_parts`

**CSS examples:**
```css
.link-plaintext__url { font-weight: bold; }
.link-plaintext__title { display: none; }
```

**Override:** Create `your-theme/templates/link-plaintext-formatter.html.twig`, then `ddev exec drush cache:rebuild`

## Code Notes

- **Formatter ID:** `link_title_url_plaintext`
- **Plugin Class:** `Drupal\link_plaintext\Plugin\Field\FieldFormatter\LinkTitleUrlPlainTextFormatter`
- **Theme Hook:** `link_plaintext_formatter` (defined in `link_plaintext.module`)
- **Field Types:** `link`
- **Drupal Version:** 11+ | **Dependencies:** `link` (core)

**How separator modes work:**
- `'space'` → single space ` `
- `'newline'` → newline `\n` (rendered as block `<div>` for proper stacking)
- `'custom'` → custom string, or space if field is empty

**Behavior:** All text is auto-escaped. Empty custom separator = space (prevents confusion). Empty title = no separator rendered.

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **Formatter not appearing** | 1. `ddev exec drush cache:rebuild` 2. `ddev exec drush en link_plaintext` 3. Verify you're on a Link field's display settings |
| **Custom separator field hidden** | Visible only when Separator Mode = "Custom separator". Refresh page if needed. |
| **Newline mode shows on same line** | Clear cache, verify mode set to "Newline", check CSS doesn't have `.link-plaintext__*` with `display: inline` |
| **Extra spacing in newline mode** | Your theme's CSS may add margins to `div`. Fix: `.link-plaintext__title, .link-plaintext__url { margin: 0; }` |
| **Empty custom field becomes space** | By design. Blank field = space to avoid confusion. Use "Newline" mode for separate lines. |
| **Template changes not visible** | Run `ddev exec drush cache:rebuild`, disable Twig caching in dev, verify override path with `drush theme:info` |
| **URLs auto-link in browser** | Browser/JS auto-linking. Customize template to obfuscate (e.g., "example DOT com") |
| **Divs/spans not wrapping** | Check "Wrap title and URL in separate elements" is enabled. Divs always render in newline mode. |

## License

Same as Drupal core (GPL-2.0-or-later).
