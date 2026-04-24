<?php

declare(strict_types=1);

namespace Drupal\labeled_phone_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\labeled_phone_field\Plugin\Field\FieldType\LabeledPhoneFieldType;

/**
 * Plugin implementation of the 'labeled_phone' formatter.
 *
 * @FieldFormatter(
 *   id = "labeled_phone",
 *   label = @Translation("Labeled Phone"),
 *   field_types = {
 *     "labeled_phone"
 *   }
 * )
 */
class LabeledPhoneFormatter extends FormatterBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state)
    {
        $elements = parent::settingsForm($form, $form_state);
        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $elements = [];

        // Collect non-empty items first so we know the total count before
        // deciding whether to suppress the 'Main' label.
        $non_empty = [];
        foreach ($items as $delta => $item) {
            if (!$item->isEmpty()) {
                $non_empty[$delta] = $item;
            }
        }

        // Suppress the label when there is exactly one item and its label is
        // 'Main' — the default single-contact case needs no visual label.
        // For multiple items, always show all labels (including 'Main') so
        // editors and visitors can distinguish the entries.
        $single_main = count($non_empty) === 1
            && strtolower(trim(reset($non_empty)->label)) === 'main';

        foreach ($non_empty as $delta => $item) {
            $show_label = !$single_main;
            $elements[$delta] = [
                '#theme' => 'labeled_phone_formatter',
                '#label' => $item->label,
                '#show_label' => $show_label,
                '#phone_display' => LabeledPhoneFieldType::formatPhoneDisplay($item->phone),
                '#phone_tel' => $item->phone,
            ];
        }

        return $elements;
    }
}
