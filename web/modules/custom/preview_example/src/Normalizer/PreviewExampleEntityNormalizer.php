<?php
namespace Drupal\preview_example\Normalizer;

use Drupal\Component\Utility\UrlHelper;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\Normalizer\ContentEntityNormalizer;
use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\preview_example\Normalizer\Value\PreviewExampleEntityNormalizerValue;

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

    // If the fields to use were specified, only output those field values.
    $context['resource_type'] = $resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );
    $resource_type_name = $resource_type->getTypeName();
    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    $bundle = $resource_type->getBundle();
    if (!empty($context['sparse_fieldset'][$resource_type_name])) {
      $field_names = $context['sparse_fieldset'][$resource_type_name];
    }
    else {
      $field_names = $this->getFieldNames($entity, $bundle, $resource_type);
    }
    /* @var Value\FieldNormalizerValueInterface[] $normalizer_values */
    $normalizer_values = [];
    $relationship_field_names = array_keys($resource_type->getRelatableResourceTypes());
    foreach ($this->getFields($entity, $bundle, $resource_type) as $field_name => $field) {
      $normalized_field = $this->serializeField($field, $context, $format);
      assert($normalized_field instanceof FieldNormalizerValueInterface);

      $in_sparse_fieldset = in_array($field_name, $field_names);
      $is_relationship_field = in_array($field_name, $relationship_field_names);
      // Omit fields not listed in sparse fieldsets, except if they're fields
      // modeling relationships; despite a relationship field being omitted,
      // using `?include` to include related resources is still allowed.
      if (!$in_sparse_fieldset) {
        if ($is_relationship_field) {
          $is_null_field = $field instanceof NullFieldNormalizerValue;
          $has_includes = !empty($normalized_field->getIncludes());
          if (!$is_null_field && $has_includes) {
            $normalizer_values[$field_name] = new IncludeOnlyRelationshipNormalizerValue($normalized_field);
          }
        }
        continue;
      }
      $normalizer_values[$field_name] = $normalized_field;
    }

    $link_context = ['link_manager' => $this->linkManager];

    return new PreviewExampleEntityNormalizerValue($normalizer_values, $context, $entity, $link_context);
  }
}