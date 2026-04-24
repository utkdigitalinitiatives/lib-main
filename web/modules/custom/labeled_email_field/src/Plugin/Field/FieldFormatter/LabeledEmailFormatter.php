<?php

declare(strict_types=1);

namespace Drupal\labeled_email_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'labeled_email' formatter.
 *
 * @FieldFormatter(
 *   id = "labeled_email",
 *   label = @Translation("Labeled Email"),
 *   field_types = {
 *     "labeled_email"
 *   }
 * )
 */
class LabeledEmailFormatter extends FormatterBase
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
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        return parent::settingsForm($form, $form_state);
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
                    '#theme' => 'labeled_email_formatter',
                    '#label' => $item->label,
                    '#email' => $item->email,
                ];
            }
        }

        return $elements;
    }
}
