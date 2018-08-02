<?php
namespace Drupal\preview_example\Normalizer;

use Drupal\Component\Utility\UrlHelper;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\Normalizer\ContentEntityNormalizer;
use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PreviewExampleEntityNormalizer extends ContentEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {

    // If this is supposed to be a revision, load it.
    $context['preview'] = FALSE;
    $params = $context['request']->query->all();
    if (isset($params['revision_id'])) {
      // Revisions should only be enabled for individual routes. Ignore if not
      // an individual route.
      $route = $context['request']->attributes->get('_route');
      if (strpos($route, $entity->bundle() . '.individual') !== FALSE ||
        $route == 'edu_api.resource_resolver') {
        $revision_id = UrlHelper::filterBadProtocol($params['revision_id']);

        if (($revision = $this->entityTypeManager->getStorage('node')->loadRevision($revision_id)) && $revision->id() == $entity->id()) {
          $context['preview'] = TRUE;
          $canonical_url_slug = $entity->field_url_slug;
          $entity = $revision;

          // The account context is empty for some reason, so we need the user
          $user = $context['account'] ? $context['account'] : \Drupal::currentUser();
          if (!$user->hasPermission('view previews')) {
            $revision_access = $revision->access('view', $user, TRUE);
            throw new EntityAccessDeniedHttpException($entity, $revision_access, '/data', 'User does not have access to view previews.');
            //$exception = new HttpException(403, 'User does not have access to view previews.');
          }
        }
        else {
          $exception = new HttpException(400, 'The revision ID does not exist or does not belong to the requested resource.');
        }
      }

      if (isset($exception)) {
        $context['preview'] = FALSE;
        return $this->serializer->normalize($exception, $format, $context);
      }

    }

    return parent::normalize($entity, $format, $context);
  }
}