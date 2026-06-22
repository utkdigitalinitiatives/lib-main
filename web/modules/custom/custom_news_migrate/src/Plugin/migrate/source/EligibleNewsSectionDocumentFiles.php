<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for document files used by eligible news file attachments.
 *
 * @MigrateSource(
 *   id = "eligible_news_section_document_files"
 * )
 */
class EligibleNewsSectionDocumentFiles extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $cutoff = $this->configuration["cutoff_date"] ?? "2021-06-08 00:00:00";

    $query = $this->select("file_managed", "f");
    $query->distinct();

    $query->innerJoin(
      "media__field_media_document",
      "mmd",
      "mmd.field_media_document_target_id = f.fid",
    );
    $query->innerJoin("media_field_data", "m", "m.mid = mmd.entity_id");
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

    $query->fields("f", [
      "fid",
      "uid",
      "filename",
      "uri",
      "filemime",
      "filesize",
      "status",
      "created",
      "changed",
    ]);
    $query->addField("m", "mid", "source_media_id");
    $query->addField("pd", "field_publishing_date_value", "publishing_date");

    $query->condition("m.bundle", "document");
    $query->condition("p.type", "section_file_attachment");
    $query->condition("n.type", "news_article");
    $query->condition("n.status", 1);
    $query->condition("pd.field_publishing_date_value", $cutoff, ">=");
    $query->orderBy("f.fid");

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      "fid" => $this->t("Source file ID"),
      "uid" => $this->t("File owner user ID"),
      "filename" => $this->t("Filename"),
      "uri" => $this->t("File URI"),
      "filemime" => $this->t("File MIME type"),
      "filesize" => $this->t("File size"),
      "status" => $this->t("File status"),
      "created" => $this->t("Created timestamp"),
      "changed" => $this->t("Changed timestamp"),
      "source_media_id" => $this->t("Source document media ID using this file"),
      "publishing_date" => $this->t("Owning news node publishing date"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return [
      "fid" => [
        "type" => "integer",
        "alias" => "f",
      ],
    ];
  }
}
