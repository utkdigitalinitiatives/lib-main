<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Eligible news file attachment paragraphs.
 *
 * @MigrateSource(
 *   id = "eligible_news_file_attachment_paragraphs"
 * )
 */
class EligibleNewsFileAttachmentParagraphs extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $query = $this->select("paragraphs_item_field_data", "p");
    $query->fields("p", ["id"]);
    $query->condition("p.type", "section_file_attachment");

    $query->leftJoin(
      "paragraph__field_file_name",
      "pfn",
      "pfn.entity_id = p.id",
    );
    $query->addField("pfn", "field_file_name_value", "file_name");

    $query->innerJoin(
      "paragraph__field_file_upload",
      "pfu",
      "pfu.entity_id = p.id",
    );
    $query->addField("pfu", "field_file_upload_target_id", "source_media_id");

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

    $query->condition("n.type", "news_article");
    $query->condition("n.status", 1);
    $query->condition(
      "pd.field_publishing_date_value",
      $this->configuration["cutoff_date"],
      ">=",
    );

    $query->distinct();

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      "id" => $this->t("Source paragraph ID"),
      "source_media_id" => $this->t("Source document media ID"),
      "file_name" => $this->t("File name text"),
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
