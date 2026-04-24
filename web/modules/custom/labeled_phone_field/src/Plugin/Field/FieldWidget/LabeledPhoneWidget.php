<?php

declare(strict_types=1);

namespace Drupal\labeled_phone_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\labeled_phone_field\Plugin\Field\FieldType\LabeledPhoneFieldType;

/**
 * Plugin implementation of the 'labeled_phone' widget.
 *
 * @FieldWidget(
 *   id = "labeled_phone",
 *   label = @Translation("Labeled Phone"),
 *   field_types = {
 *     "labeled_phone"
 *   }
 * )
 */
class LabeledPhoneWidget extends WidgetBase
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
        $elements = parent::settingsForm($form, $form_state);
        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $item = $items[$delta];

        $element['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#description' => $this->t('E.g., "Printing", "Requests", "Front Desk".'),
            '#default_value' => $item->label ?? '',
            '#size' => 30,
            '#maxlength' => 255,
            '#weight' => 0,
        ];

        $element['phone'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Phone Number'),
            '#description' => $this->t('Enter a 10-digit US phone number. Input will be auto-formatted to XXX-XXX-XXXX.'),
            '#default_value' => LabeledPhoneFieldType::formatPhoneDisplay($item->phone ?? ''),
            '#size' => 15,
            '#maxlength' => 14,
            '#attributes' => [
                'class' => ['labeled-phone-input'],
                'inputmode' => 'tel',
                'placeholder' => '555-555-1234',
            ],
            '#weight' => 1,
            '#element_validate' => [
                [$this, 'validatePhoneElement'],
            ],
        ];

        // Attach the input mask library and behavior.
        $element['#attached']['library'][] = 'labeled_phone_field/labeled_phone_widget';

        return $element;
    }

    /**
     * Validates the phone and label elements.
     *
     * @param array $element
     *   The phone element being validated.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state object.
     * @param array $form
     *   The complete form array.
     */
    public static function validatePhoneElement(&$element, FormStateInterface $form_state, &$form)
    {
        // Get the parent fieldset's values (which contains both label and phone).
        // The element #parents includes the phone element itself at the end, so we need the parent.
        $parent_path = array_slice($element['#parents'], 0, -1);
        $parent_values = $form_state->getValue($parent_path);

        // Extract label and phone from the parent fieldset.
        $phone = isset($parent_values['phone']) ? $parent_values['phone'] : '';
        $label = isset($parent_values['label']) ? $parent_values['label'] : '';

        // Both label and phone must be provided together, or both must be empty.
        $phone_normalized = !empty($phone) ? LabeledPhoneFieldType::normalizePhone($phone) : '';
        $label_trimmed = !empty($label) ? trim($label) : '';

        // If phone is provided but label is missing.
        if (!empty($phone_normalized) && empty($label_trimmed)) {
            $form_state->setError(
                $element,
                t('Label is required when a phone number is provided.')
            );
            return;
        }

        // If label is provided but phone is missing.
        if (!empty($label_trimmed) && empty($phone_normalized)) {
            $form_state->setError(
                $element,
                t('Phone number is required when a label is provided.')
            );
            return;
        }

        // If phone is provided, validate it's exactly 10 digits.
        if (!empty($phone_normalized)) {
            if (!preg_match('/^\d{10}$/', $phone_normalized)) {
                $form_state->setError(
                    $element,
                    t('Phone number must be exactly 10 digits. You entered: @phone', ['@phone' => $phone])
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
    {
        foreach ($values as &$item) {
            if (is_array($item)) {
                // Normalize the phone number to digits-only.
                if (!empty($item['phone'])) {
                    $item['phone'] = LabeledPhoneFieldType::normalizePhone($item['phone']);
                }
                // Trim label whitespace.
                if (!empty($item['label'])) {
                    $item['label'] = trim($item['label']);
                }
            }
        }
        return $values;
    }
}
