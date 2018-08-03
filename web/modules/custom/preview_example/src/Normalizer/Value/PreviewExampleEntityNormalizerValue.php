<?php
/**
 * Created by PhpStorm.
 * User: edutopia
 * Date: 8/3/18
 * Time: 9:00 AM
 */

namespace Drupal\preview_example\Normalizer\Value;

use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal;

class PreviewExampleEntityNormalizerValue extends EntityNormalizerValue {

  /**
   * Instantiate a EntityNormalizerValue object.
   *
   * @param FieldNormalizerValueInterface[] $values
   *   The normalized result.
   * @param array $context
   *   The context for the normalizer.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $link_context
   *   All the objects and variables needed to generate the links for this
   *   relationship.
   */
  public function __construct(array $values, array $context, EntityInterface $entity, array $link_context) {
    parent::__construct($values, $context, $entity, $link_context);
    $request = Drupal::request();
    $url = Url::createFromRequest($request);
    $this->setCacheability(static::mergeCacheableDependencies(array_merge([$entity, $url], $values)));

  }
}