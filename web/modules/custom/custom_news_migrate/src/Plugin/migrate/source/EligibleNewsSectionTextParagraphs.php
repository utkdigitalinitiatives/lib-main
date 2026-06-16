<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for section_text paragraphs used by eligible news nodes.
 *
 * @MigrateSource(
 *   id = "eligible_news_section_text_paragraphs"
 * )
 */
class EligibleNewsSectionTextParagraphs extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $cutoff = $this->configuration["cutoff_date"] ?? "2021-06-08 00:00:00";

    $query = $this->select("paragraphs_item_field_data", "p");
    $query->innerJoin("paragraph__field_content", "pc", "pc.entity_id = p.id");
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
    $query->addField("pc", "field_content_value", "content_value");
    $query->addField("pc", "field_content_format", "content_format");
    $query->addField("pd", "field_publishing_date_value", "publishing_date");

    $query->condition("p.type", "section_text");
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
      "content_value" => $this->t("Section text content"),
      "content_format" => $this->t("Section text format"),
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
