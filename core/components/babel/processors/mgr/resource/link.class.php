<?php
/**
 * Link resource
 *
 * @package babel
 * @subpackage processors
 */

use mikrobi\Babel\Processors\ObjectUpdateProcessor;

class BabelResourceLinkProcessor extends ObjectUpdateProcessor
{
    public $classKey = 'modResource';
    public $objectType = 'resource';
    public $languageTopics = ['resource', 'babel:default'];

    /** @var modResource $object The link source */
    public $object;

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize()
    {
        $success = parent::initialize();

        $target = $this->getProperty('target', false);
        if (empty($target)) {
            return $this->modx->lexicon($this->objectType . '_err_ns');
        }
        $primaryKey = $this->getProperty($this->primaryKeyField, false);
        if ($target === $primaryKey) {
            return $this->modx->lexicon('babel.resource_err_link_of_selflink_not_possible');
        }
        $targetResource = $this->modx->getObject('modResource', $target);
        if (!$targetResource) {
            return $this->modx->lexicon('babel.resource_err_invalid_id', [
                'resource' => $target
            ]);
        }
        $contextKey = $this->getProperty('context', false);
        if (empty($contextKey)) {
            return $this->modx->lexicon('babel.context_err_ns');
        }
        $context = $this->modx->getObject('modContext', [
            'key' => $contextKey
        ]);
        if (!$context) {
            return $this->modx->lexicon('babel.context_err_invalid_key', [
                'context' => $contextKey
            ]);
        }
        if ($targetResource->get('context_key') !== $contextKey) {
            return $this->modx->lexicon('babel.resource_err_from_other_context', [
                'resource' => $target,
                'context' => $contextKey
            ]);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function process()
    {
        $target = $this->getProperty('target', false);
        $targetResource = $this->modx->getObject('modResource', $target);
        $targetResources = $this->babel->getLinkedResources($target);
        $linkedResources = $this->babel->getLinkedResources($this->object->get('id'));
        if (empty($linkedResources)) {
            // Always be sure that the Babel TV is set
            $linkedResources = $this->babel->initBabelTv($this->object);
        }

        $context = $this->getProperty('context');
        // Add or change a translation link
        if (isset($linkedResources[$context])) {
            // If the existing link has been changed, reset the Babel TV of the old resource
            $this->babel->initBabelTvById($linkedResources[$context]);
        }
        $linkedResources[$context] = $target;

        $syncLinkedTranslations = $this->getProperty('sync');
        if ($syncLinkedTranslations == 1) {
            // Join all existing linked resources from both resources
            $mergedResources = array_merge($targetResources, $linkedResources);
            $this->babel->updateBabelTv($mergedResources, $mergedResources);
        } else {
            // Only join between 2 resources
            $mergeLinked = array_merge($linkedResources, [
                $this->getProperty('context') => $target
            ]);
            $this->babel->updateBabelTv($this->object->get('id'), $mergeLinked);
            $mergeTarget = array_merge($targetResources, [
                $this->object->get('context_key') => $this->object->get('id')
            ]);
            $this->babel->updateBabelTv($target, $mergeTarget);
        }

        $copyTvValues = $this->getProperty('copy');
        if ($copyTvValues == 1) {
            // Copy values of synchronized TVs and resource fields to the target resource
            $this->babel->synchronizeTvs($this->object->get('id'));
            $this->babel->synchronizeFields($this->object->get('id'));
        }

        $this->fireLinkEvent($targetResource);
        return $this->cleanup();
    }

    /**
     * Fire the OnBabelLink event
     */
    public function fireLinkEvent($targetResource = null)
    {
        $this->modx->invokeEvent('OnBabelLink', [
            'context_key' => $this->getProperty('context'),
            'original_id' => $this->object->get('id'),
            'original_resource' => &$this->object,
            'target_id' => $targetResource->get('id'),
            'target_resource' => &$targetResource
        ]);
    }

    /**
     * {@inheritDoc}
     * @return array
     */
    public function cleanup()
    {
        $output = $this->object->toArray();
        $output['menu'] = $this->babel->getMenu($this->object);
        return $this->success('', $output);
    }
}

return 'BabelResourceLinkProcessor';
