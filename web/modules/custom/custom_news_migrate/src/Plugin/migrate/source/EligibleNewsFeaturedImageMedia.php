<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Eligible news featured-image media source.
 *
 * @MigrateSource(
 *   id = "eligible_news_featured_image_media"
 * )
 */
class EligibleNewsFeaturedImageMedia extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $query = $this->select("paragraphs_item_field_data", "p");
    $query->fields("p", ["id", "created"]);
    $query->condition("p.type", "featured_image");

    $query->innerJoin(
      "paragraph__field_featured_image_upload",
      "pfiu",
      "pfiu.entity_id = p.id",
    );
    $query->addField(
      "pfiu",
      "field_featured_image_upload_target_id",
      "source_file_id",
    );
    $query->addField("pfiu", "field_featured_image_upload_alt", "image_alt");
    $query->addField(
      "pfiu",
      "field_featured_image_upload_title",
      "image_title",
    );

    $query->innerJoin(
      "file_managed",
      "fm",
      "fm.fid = pfiu.field_featured_image_upload_target_id",
    );
    $query->addField("fm", "changed", "changed");

    $query->leftJoin(
      "paragraph__field_featured_image_caption",
      "pfic",
      "pfic.entity_id = p.id",
    );
    $query->addField("pfic", "field_featured_image_caption_value", "caption");

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
      "id" => $this->t("Source featured-image paragraph ID"),
      "source_file_id" => $this->t("Source file ID"),
      "image_alt" => $this->t("Image alt text"),
      "image_title" => $this->t("Image title text"),
      "caption" => $this->t("Caption text"),
      "created" => $this->t("Source paragraph created timestamp"),
      "changed" => $this->t("Source file changed timestamp"),
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
