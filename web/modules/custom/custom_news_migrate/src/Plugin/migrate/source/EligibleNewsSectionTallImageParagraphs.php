<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for section_image_tall paragraphs used by eligible news nodes.
 *
 * @MigrateSource(
 *   id = "eligible_news_section_tall_image_paragraphs"
 * )
 */
class EligibleNewsSectionTallImageParagraphs extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $cutoff = $this->configuration["cutoff_date"] ?? "2021-06-08 00:00:00";

    $query = $this->select("paragraphs_item_field_data", "p");
    $query->innerJoin("paragraph__field_image", "pi", "pi.entity_id = p.id");
    $query->leftJoin(
      "paragraph__field_image_caption",
      "pic",
      "pic.entity_id = p.id",
    );
    $query->innerJoin(
      "node__field_sections",
      "s",
      "s.field_sections_target_id = p.id",
    );
    $query->innerJoin("node_field_data", "n", "n.nid = s.entity_id");
    $query->innerJoin(
      "node__field_publishing_date",
      "pd",
      "pd.entity_id = n.nid",
    );

    $query->fields("p", ["id", "revision_id", "type", "created"]);
    $query->addField("s", "entity_id", "parent_nid");
    $query->addField("s", "delta", "section_delta");
    $query->addField("pi", "field_image_target_id", "source_media_id");
    $query->addField("pic", "field_image_caption_value", "caption_value");
    $query->addField("pic", "field_image_caption_format", "caption_format");
    $query->addField("pd", "field_publishing_date_value", "publishing_date");

    $query->condition("p.type", "section_image_tall");
    $query->condition("n.type", "news_article");
    $query->condition("n.status", 1);
    $query->condition("pd.field_publishing_date_value", $cutoff, ">=");
    $query->orderBy("p.id");

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      "id" => $this->t("Source paragraph ID"),
      "revision_id" => $this->t("Source paragraph revision ID"),
      "type" => $this->t("Source paragraph bundle"),
      "created" => $this->t("Created timestamp"),
      "parent_nid" => $this->t("Parent source node ID"),
      "section_delta" => $this->t("Section field delta on parent node"),
      "source_media_id" => $this->t("Source image media ID"),
      "caption_value" => $this->t("Image caption text"),
      "caption_format" => $this->t("Image caption format"),
      "publishing_date" => $this->t("Parent node publishing date"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return [
      "id" => [
        "type" => "integer",
        "alias" => "p",
      ],
    ];
  }
}
