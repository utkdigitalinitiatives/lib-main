<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for remote video media used by eligible news sections.
 *
 * @MigrateSource(
 *   id = "eligible_news_remote_video_media"
 * )
 */
class EligibleNewsRemoteVideoMedia extends SqlBase
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
      "paragraph__field_embedded_video",
      "pev",
      "pev.field_embedded_video_target_id = m.mid",
    );
    $query->innerJoin(
      "paragraphs_item_field_data",
      "p",
      "p.id = pev.entity_id",
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
    $query->leftJoin(
      "media__field_media_oembed_video",
      "mov",
      "mov.entity_id = m.mid",
    );

    $query->fields("m", [
      "mid",
      "bundle",
      "name",
      "created",
      "changed",
      "status",
    ]);
    $query->addField("mov", "field_media_oembed_video_value", "video_url");
    $query->addField("pd", "field_publishing_date_value", "publishing_date");

    $query->condition("m.bundle", "remote_video");
    $query->condition("p.type", "section_embedded_video");
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
      "video_url" => $this->t("Remote video URL"),
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
