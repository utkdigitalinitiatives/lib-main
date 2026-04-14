<?php

/**
 * @file
 * Link Title + URL Plain Text Formatter plugin for Drupal Link fields.
 */

namespace Drupal\link_plaintext\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'link_title_url_plaintext' formatter.
 *
 * Renders Link field items as plain text: title and URL displayed separately.
 * No HTML anchors are used. Output is themeable via a Twig template so editors
 * can style title and URL independently.
 *
 * @FieldFormatter(
 *   id = "link_title_url_plaintext",
 *   label = @Translation("Link title + URL (plain text)"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkTitleUrlPlainTextFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'separator_mode' => 'space',
      'separator' => ' — ',
      'absolute_url' => TRUE,
      'wrap_parts' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['separator_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Separator mode'),
      '#description' => $this->t('How to separate the link title and URL.'),
      '#options' => [
        'space' => $this->t('Single space'),
        'newline' => $this->t('Newline (one per line)'),
        'custom' => $this->t('Custom separator'),
      ],
      '#default_value' => $this->getSetting('separator_mode'),
      '#required' => TRUE,
    ];

    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom separator'),
      '#description' => $this->t('Character(s) to display between title and URL when mode is "Custom separator". Leave blank to use a single space. Examples: " — ", " | ", " → ".'),
      '#default_value' => $this->getSetting('separator'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][separator_mode]"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force absolute URLs'),
      '#description' => $this->t('If enabled, converts internal links to their absolute (full) URL. If disabled, relative URLs are used for internal links.'),
      '#default_value' => $this->getSetting('absolute_url'),
    ];

    $form['wrap_parts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wrap title and URL in separate elements'),
      '#description' => $this->t('If enabled, each part is wrapped in an element (span for space/custom, div for newline) with a class so Twig/CSS can target them independently. If disabled, parts are rendered inline or stacked.'),
      '#default_value' => $this->getSetting('wrap_parts'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $mode = $this->getSetting('separator_mode');
    $modeLabels = [
      'space' => $this->t('Single space'),
      'newline' => $this->t('Newline'),
      'custom' => $this->t('Custom'),
    ];
    $summary[] = $this->t('Separator: @mode', ['@mode' => $modeLabels[$mode] ?? $mode]);

    if ($mode === 'custom') {
      $separator = $this->getSetting('separator');
      $summary[] = $this->t('Custom value: @sep', ['@sep' => $separator !== '' ? $separator : '(space)']);
    }

    $absolute = $this->getSetting('absolute_url');
    $summary[] = $this->t('Absolute URLs: @value', [
      '@value' => $absolute ? $this->t('Yes') : $this->t('No'),
    ]);

    $wrap = $this->getSetting('wrap_parts');
    $summary[] = $this->t('Wrapped parts: @value', [
      '@value' => $wrap ? $this->t('Yes') : $this->t('No'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $separator_mode = $this->getSetting('separator_mode');
    $force_absolute = $this->getSetting('absolute_url');
    $wrap_parts = $this->getSetting('wrap_parts');

    // Compute the actual separator string based on mode.
    $separator = '';
    if ($separator_mode === 'space') {
      $separator = ' ';
    } elseif ($separator_mode === 'newline') {
      $separator = "\n";
    } elseif ($separator_mode === 'custom') {
      $custom_sep = $this->getSetting('separator');
      // If custom separator is empty, treat as single space.
      $separator = ($custom_sep === '') ? ' ' : $custom_sep;
    }

    foreach ($items as $delta => $item) {
      // Extract title and URL from the LinkItem.
      $title = $item->title;
      $url_obj = $item->getUrl();

      // Convert URL object to string.
      // If force_absolute is TRUE, generate absolute URL; otherwise use relative/default.
      if ($force_absolute) {
        $url_string = $url_obj->setAbsolute(TRUE)->toString();
      } else {
        $url_string = $url_obj->toString();
      }

      // Prepare parts: title (may be empty/null) and URL.
      // These are passed to the Twig template separately so they can be
      // styled independently or omitted if desired.
      $elements[$delta] = [
        '#theme' => 'link_plaintext_formatter',
        '#title' => $title,
        '#url' => $url_string,
        '#separator' => $separator,
        '#separator_mode' => $separator_mode,
        '#wrap_parts' => $wrap_parts,
      ];
    }

    return $elements;
  }

}
