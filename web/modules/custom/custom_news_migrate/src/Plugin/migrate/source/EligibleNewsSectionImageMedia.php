<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for image media used by eligible news section paragraphs.
 *
 * @MigrateSource(
 *   id = "eligible_news_section_image_media"
 * )
 */
class EligibleNewsSectionImageMedia extends SqlBase
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
      "paragraph__field_image",
      "pi",
      "pi.field_image_target_id = m.mid",
    );
    $query->innerJoin("paragraphs_item_field_data", "p", "p.id = pi.entity_id");
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
    $query->leftJoin(
      "media__field_media_image",
      "mmi",
      "mmi.entity_id = m.mid",
    );

    $query->fields("m", [
      "mid",
      "bundle",
      "name",
      "created",
      "changed",
      "status",
    ]);
    $query->addField("mmi", "field_media_image_target_id", "file_target_id");

    $query->condition("m.bundle", "image");
    $query->condition(
      "p.type",
      ["section_image", "section_image_tall", "section_callout_image"],
      "IN",
    );
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
      "file_target_id" => $this->t("Referenced image file ID"),
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
