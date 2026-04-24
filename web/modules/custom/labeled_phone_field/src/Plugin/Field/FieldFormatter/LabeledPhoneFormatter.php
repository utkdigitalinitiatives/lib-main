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

        foreach ($items as $delta => $item) {
            if (!$item->isEmpty()) {
                $elements[$delta] = [
                    '#theme' => 'labeled_phone_formatter',
                    '#label' => $item->label,
                    '#phone_display' => LabeledPhoneFieldType::formatPhoneDisplay($item->phone),
                    '#phone_tel' => $item->phone,
                ];
            }
        }

        return $elements;
    }
}
