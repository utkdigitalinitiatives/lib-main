<?php

declare(strict_types=1);

namespace Drupal\labeled_email_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines a Labeled Email field type.
 *
 * @FieldType(
 *   id = "labeled_email",
 *   label = @Translation("Labeled Email"),
 *   description = @Translation("Multi-value field for storing labeled email addresses (label + email pairs)."),
 *   default_widget = "labeled_email",
 *   default_formatter = "labeled_email"
 * )
 */
class LabeledEmailFieldType extends FieldItemBase
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
            ->setDescription(new TranslatableMarkup('The label for this email address (e.g., "Main", "Support", "Admissions").'))
            ->setSetting('max_length', 255);

        $properties['email'] = DataDefinition::create('string')
            ->setLabel(new TranslatableMarkup('Email'))
            ->setDescription(new TranslatableMarkup('The email address (e.g., "info@example.edu").'))
            ->setSetting('max_length', 254);

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
                'email' => [
                    // RFC 5321 maximum email address length is 254 characters.
                    'type' => 'varchar',
                    'length' => 254,
                    'not null' => FALSE,
                ],
            ],
            'indexes' => [
                'email' => ['email'],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $email = $this->get('email')->getValue();
        $label = $this->get('label')->getValue();
        return empty($email) || empty($label);
    }
}
