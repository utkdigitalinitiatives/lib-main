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

        // Default the label to "Main" for new items so the single-contact case
        // is natural for editors. Existing saved labels are never overwritten.
        $label_default = $item->label ?? '';
        if ($label_default === '' && $delta === 0) {
            $label_default = 'Main';
        }

        $element['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#description' => $this->t('E.g., "Main", "Front Desk", "Requests", "Printing".'),
            '#default_value' => $label_default,
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
        // The element #parents path ends with 'phone'; strip it to get the
        // parent item's values, which contain both 'label' and 'phone'.
        $parent_path = array_slice($element['#parents'], 0, -1);
        $parent_values = $form_state->getValue($parent_path);

        // If the parent context cannot be resolved, bail out silently.
        if (!is_array($parent_values)) {
            return;
        }

        $phone = isset($parent_values['phone']) ? $parent_values['phone'] : '';
        $label = isset($parent_values['label']) ? $parent_values['label'] : '';

        $phone_normalized = !empty($phone) ? LabeledPhoneFieldType::normalizePhone($phone) : '';
        $label_trimmed = !empty($label) ? trim($label) : '';

        // If phone is empty there is nothing to validate. isEmpty() already
        // ensures an item with no phone is never persisted. Bailing here also
        // prevents a false error on the field config / default-value form if a
        // label default were ever added to this widget in the future.
        if (empty($phone_normalized)) {
            return;
        }

        // Phone is provided; a label is required.
        if (empty($label_trimmed)) {
            $form_state->setError(
                $element,
                t('Label is required when a phone number is provided.')
            );
            return;
        }

        // Validate the phone is exactly 10 digits.
        if (!preg_match('/^\d{10}$/', $phone_normalized)) {
            $form_state->setError(
                $element,
                t('Phone number must be exactly 10 digits. You entered: @phone', ['@phone' => $phone])
            );
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
