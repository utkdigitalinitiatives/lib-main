<?php

namespace Drupal\custom_news_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for eligible Drupal 10 news nodes.
 *
 * @MigrateSource(
 *   id = "eligible_news_nodes"
 * )
 */
class EligibleNewsNodes extends SqlBase
{
  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $cutoff = $this->configuration["cutoff_date"] ?? "2021-06-08 00:00:00";

    // query
    $query = $this->select("node_field_data", "n");

    // joins
    $query->innerJoin(
      "node__field_publishing_date",
      "pd",
      "pd.entity_id = n.nid",
    );
    $query->leftJoin("node__field_blurb", "b", "b.entity_id = n.nid");
    $query->leftJoin(
      "node__field_article_update",
      "au",
      "au.entity_id = n.nid",
    );
    $query->leftJoin(
      "paragraph__field_update_label",
      "aul",
      "aul.entity_id = au.field_article_update_target_id",
    );
    $query->leftJoin(
      "paragraph__field_update_text",
      "aut",
      "aut.entity_id = au.field_article_update_target_id",
    );
    $query->leftJoin(
      "node__field_featured_image",
      "nfi",
      "nfi.entity_id = n.nid",
    );
    $query->addField(
      "nfi",
      "field_featured_image_target_id",
      "source_featured_image_paragraph_id",
    );
    $query->leftJoin(
      "paragraph__field_featured_image_caption",
      "fic",
      "fic.entity_id = nfi.field_featured_image_target_id",
    );
    $query->addField(
      "fic",
      "field_featured_image_caption_value",
      "featured_image_caption",
    );

    // fields
    $query->fields("n", ["nid", "title", "status", "created", "changed"]);
    $query->addField("pd", "field_publishing_date_value", "publishing_date");
    $query->addField("b", "field_blurb_value", "blurb");
    $query->addField("aul", "field_update_label_value", "update_label");
    $query->addField("aut", "field_update_text_value", "update_text");

    //conditions
    $query->condition("n.type", "news_article");
    $query->condition("n.status", 1);
    $query->condition("pd.field_publishing_date_value", $cutoff, ">=");
    $query->orderBy("n.nid");

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      "nid" => $this->t("Source node ID"),
      "title" => $this->t("Headline / node title"),
      "status" => $this->t("Published status"),
      "created" => $this->t("Created timestamp"),
      "changed" => $this->t("Changed timestamp"),
      "publishing_date" => $this->t("Publishing date value"),
      "blurb" => $this->t("Source blurb text"),
      "update_label" => $this->t("Article update label"),
      "update_text" => $this->t("Article update text"),
      "source_featured_image_paragraph_id" => $this->t(
        "Source featured image paragraph ID",
      ),
      "featured_image_caption" => $this->t("Featured image caption"),
      "section_paragraph_ids" => $this->t(
        "Ordered source section paragraph IDs",
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row)
  {
    $section_paragraph_ids = $this->select("node__field_sections", "fs")
      ->fields("fs", ["field_sections_target_id"])
      ->condition("fs.entity_id", $row->getSourceProperty("nid"))
      ->orderBy("fs.delta")
      ->execute()
      ->fetchCol();

    $section_paragraph_items = array_map(
      static fn($id) => ["source_paragraph_id" => (int) $id],
      $section_paragraph_ids,
    );

    $row->setSourceProperty("section_paragraph_ids", $section_paragraph_items);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return [
      "nid" => [
        "type" => "integer",
        "alias" => "n",
      ],
    ];
  }
}
