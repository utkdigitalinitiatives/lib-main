<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Eligible news featured-image files.
 *
 * @MigrateSource(
 *   id = "eligible_news_featured_image_files"
 * )
 */
class EligibleNewsFeaturedImageFiles extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $query = $this->select("file_managed", "fm");
    $query->fields("fm", [
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

    $query->innerJoin(
      "paragraph__field_featured_image_upload",
      "pfiu",
      "pfiu.field_featured_image_upload_target_id = fm.fid",
    );
    $query->innerJoin(
      "paragraphs_item_field_data",
      "p",
      "p.id = pfiu.entity_id",
    );
    $query->innerJoin(
      "node__field_featured_image",
      "fip",
      "fip.field_featured_image_target_id = p.id",
    );
    $query->innerJoin("node_field_data", "n", "n.nid = fip.entity_id");
    $query->innerJoin(
      "node__field_publishing_date",
      "pd",
      "pd.entity_id = n.nid",
    );

    $query->condition("p.type", "featured_image");
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
      "fid" => $this->t("File ID"),
      "uid" => $this->t("User ID"),
      "filename" => $this->t("Filename"),
      "uri" => $this->t("URI"),
      "filemime" => $this->t("File MIME type"),
      "filesize" => $this->t("File size"),
      "status" => $this->t("Status"),
      "created" => $this->t("Created timestamp"),
      "changed" => $this->t("Changed timestamp"),
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
        "alias" => "fm",
      ],
    ];
  }
}
