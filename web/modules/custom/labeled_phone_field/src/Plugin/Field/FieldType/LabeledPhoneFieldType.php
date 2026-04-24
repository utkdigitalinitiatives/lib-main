<?php

declare(strict_types=1);

namespace Drupal\labeled_phone_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines a Labeled Phone field type.
 *
 * @FieldType(
 *   id = "labeled_phone",
 *   label = @Translation("Labeled Phone"),
 *   description = @Translation("Multi-value field for storing labeled phone numbers (label + phone pairs)."),
 *   default_widget = "labeled_phone",
 *   default_formatter = "labeled_phone"
 * )
 */
class LabeledPhoneFieldType extends FieldItemBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultFieldSettings()
    {
        return [] + parent::defaultFieldSettings();
    }

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties = [];

        $properties['label'] = DataDefinition::create('string')
            ->setLabel(new TranslatableMarkup('Label'))
            ->setDescription(new TranslatableMarkup('The label for this phone number (e.g., "Main", "Sales", "Support").'))
            ->setSetting('max_length', 255);

        $properties['phone'] = DataDefinition::create('string')
            ->setLabel(new TranslatableMarkup('Phone'))
            ->setDescription(new TranslatableMarkup('The phone number (stored as digits only, e.g., "5555551234").'))
            ->setSetting('max_length', 20);

        return $properties;
    }

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        return [
            'columns' => [
                'label' => [
                    'type' => 'varchar',
                    'length' => 255,
                    'not null' => FALSE,
                ],
                'phone' => [
                    'type' => 'varchar',
                    'length' => 20,
                    'not null' => FALSE,
                ],
            ],
            'indexes' => [
                'phone' => ['phone'],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $phone = $this->get('phone')->getValue();
        $label = $this->get('label')->getValue();
        return empty($phone) || empty($label);
    }

    /**
     * Normalizes a phone number to digits only.
     *
     * @param string $phone
     *   The phone input (may contain formatting, spaces, etc.).
     *
     * @return string
     *   The normalized phone string with only digits (e.g., "5555551234").
     */
    public static function normalizePhone($phone)
    {
        // Remove all non-digit characters.
        $normalized = preg_replace('/[^\d]/', '', $phone);
        // Return only the first 10 digits (truncate excess).
        return substr($normalized, 0, 10);
    }

    /**
     * Formats a phone number for display as XXX-XXX-XXXX.
     *
     * @param string $phone
     *   The phone string (digits only, e.g., "5555551234").
     *
     * @return string
     *   The formatted phone string (e.g., "555-555-1234").
     */
    public static function formatPhoneDisplay($phone)
    {
        $normalized = self::normalizePhone($phone);
        if (strlen($normalized) === 10) {
            return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $normalized);
        }
        return $phone;
    }
}
