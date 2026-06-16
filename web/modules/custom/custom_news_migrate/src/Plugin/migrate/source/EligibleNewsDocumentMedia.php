<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for document media used by eligible news file attachments.
 *
 * @MigrateSource(
 *   id = "eligible_news_document_media"
 * )
 */
class EligibleNewsDocumentMedia extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $cutoff = $this->configuration["cutoff_date"] ?? "2021-06-08 00:00:00";

    $query = $this->select("media_field_data", "m");
    $query->distinct();

    $query->innerJoin(
      "media__field_media_document",
      "mmd",
      "mmd.entity_id = m.mid",
    );
    $query->innerJoin(
      "paragraph__field_file_upload",
      "pfu",
      "pfu.field_file_upload_target_id = m.mid",
    );
    $query->innerJoin(
      "paragraphs_item_field_data",
      "p",
      "p.id = pfu.entity_id",
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

    $query->fields("m", [
      "mid",
      "bundle",
      "name",
      "created",
      "changed",
      "status",
    ]);
    $query->addField("mmd", "field_media_document_target_id", "file_target_id");
    $query->addField("pd", "field_publishing_date_value", "publishing_date");

    $query->condition("m.bundle", "document");
    $query->condition("p.type", "section_file_attachment");
    $query->condition("n.type", "news_article");
    $query->condition("n.status", 1);
    $query->condition("pd.field_publishing_date_value", $cutoff, ">=");
    $query->orderBy("m.mid");

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      "mid" => $this->t("Source media ID"),
      "bundle" => $this->t("Source media bundle"),
      "name" => $this->t("Media name"),
      "created" => $this->t("Created timestamp"),
      "changed" => $this->t("Changed timestamp"),
      "status" => $this->t("Published status"),
      "file_target_id" => $this->t("Referenced document file ID"),
      "publishing_date" => $this->t("Owning news node publishing date"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return [
      "mid" => [
        "type" => "integer",
        "alias" => "m",
      ],
    ];
  }
}
