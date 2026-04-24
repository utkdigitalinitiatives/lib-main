<?php

declare(strict_types=1);

namespace Drupal\labeled_email_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'labeled_email' widget.
 *
 * @FieldWidget(
 *   id = "labeled_email",
 *   label = @Translation("Labeled Email"),
 *   field_types = {
 *     "labeled_email"
 *   }
 * )
 */
class LabeledEmailWidget extends WidgetBase
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
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $item = $items[$delta];

        // Default the label to "Main" for the first item when it has no saved
        // value, so the common single-email case requires minimal effort from
        // editors.
        $label_default = $item->label ?? '';
        if ($label_default === '' && $delta === 0) {
            $label_default = 'Main';
        }

        $element['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#description' => $this->t('E.g., "Main", "Support", "Admissions", "HR".'),
            '#default_value' => $label_default,
            '#size' => 30,
            '#maxlength' => 255,
            '#weight' => 0,
        ];

        $element['email'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Email Address'),
            '#description' => $this->t('Enter a valid email address.'),
            '#default_value' => $item->email ?? '',
            '#size' => 40,
            '#maxlength' => 254,
            '#attributes' => [
                'inputmode' => 'email',
                'placeholder' => 'name@example.edu',
            ],
            '#weight' => 1,
            '#element_validate' => [
                [$this, 'validateEmailElement'],
            ],
        ];

        return $element;
    }

    /**
     * Validates the email and label elements.
     *
     * @param array $element
     *   The email element being validated.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The form state object.
     * @param array $form
     *   The complete form array.
     */
    public static function validateEmailElement(&$element, FormStateInterface $form_state, &$form)
    {
        // The element #parents path ends with 'email'; strip it to get the
        // parent item's values, which contain both 'label' and 'email'.
        $parent_path = array_slice($element['#parents'], 0, -1);
        $parent_values = $form_state->getValue($parent_path);

        // If the parent context cannot be resolved, bail out silently.
        if (!is_array($parent_values)) {
            return;
        }

        $email = isset($parent_values['email']) ? trim($parent_values['email']) : '';
        $label = isset($parent_values['label']) ? trim($parent_values['label']) : '';

        // If email is empty there is nothing to validate. The widget may have
        // pre-filled the label (e.g. the 'Main' default on delta 0), so we
        // cannot treat a non-empty label alone as an error — that would fire on
        // the field config / default-value form whenever no default email is
        // supplied. isEmpty() already ensures an item with no email is never
        // persisted.
        if (empty($email)) {
            return;
        }

        // Email is provided; a label is required.
        if (empty($label)) {
            $form_state->setError(
                $element,
                t('Label is required when an email address is provided.')
            );
            return;
        }

        // Server-side email format validation.
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
            $form_state->setError(
                $element,
                t('@email is not a valid email address.', ['@email' => $email])
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
                if (!empty($item['email'])) {
                    $item['email'] = trim($item['email']);
                }
                if (!empty($item['label'])) {
                    $item['label'] = trim($item['label']);
                }
            }
        }
        return $values;
    }
}
