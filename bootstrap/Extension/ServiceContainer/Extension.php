<?php

namespace FailAid\Extension\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use FailAid\Extension\Initializer\Initializer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Extension class.
 */
class Extension implements ExtensionInterface
{
    const CONTEXT_INITIALISER = 'failaid.context_initialiser';

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * Create definition object to handle in the context?
     */
    public function process(ContainerBuilder $container)
    {
        return;
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        return 'FailAidExtension';
    }

    /**
     * Initializes other extensions.
     *
     * This method is called immediately after all extensions are activated but
     * before any extension `configure()` method is called. This allows extensions
     * to hook into the configuration of other extensions providing such an
     * extension point.
     *
     */
    public function initialize(ExtensionManager $extensionManager)
    {
        return;
    }

    /**
     * Setups configuration for the extension.
     *
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->arrayNode('screenshot')
                    ->children()
                        ->scalarNode('directory')->defaultNull()->end()
                        ->scalarNode('mode')->defaultValue('html')->end()
                        ->booleanNode('autoClean')->defaultValue(false)->end()
                        ->scalarNode('size')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('trackJs')
                    ->children()
                        ->booleanNode('errors')->defaultValue(false)->end()
                        ->booleanNode('warns')->defaultValue(false)->end()
                        ->booleanNode('logs')->defaultValue(false)->end()
                        ->scalarNode('trim')->defaultValue(false)->end()
                    ->end()
                ->end()
                ->scalarNode('defaultSession')->defaultValue(null)->end()
                /**
                 * DEPRECATED in favour of screenshot option, to be removed in next major version bump.
                 */
                ->scalarNode('screenshotDirectory')
                    ->defaultNull()
                ->end()
                /**
                 * DEPRECATED in favour of screenshot option, to be removed in next major version bump.
                 */
                ->scalarNode('screenshotMode')
                    ->defaultValue('html')
                ->end()
                ->arrayNode('debugBarSelectors')
                    ->ignoreExtraKeys(false)
                ->end()
                ->arrayNode('siteFilters')
                    ->ignoreExtraKeys(false)
                ->end()
            ->end()
        ->end();
    }

    /**
     * Loads extension services into temporary container.
     *
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $container->setParameter('genesis.failaid.config.screenshot', $this->getScreenshotOptions($config));

        if (! isset($config['debugBarSelectors'])) {
            $config['debugBarSelectors'] = [];
        }
        $container->setParameter('genesis.failaid.config.debugBarSelectors', $config['debugBarSelectors']);

        if (! isset($config['siteFilters'])) {
            $config['siteFilters'] = [];
        }
        $container->setParameter('genesis.failaid.config.siteFilters', $config['siteFilters']);

        if (! isset($config['trackJs'])) {
            $config['trackJs'] = [];
        }
        $container->setParameter('genesis.failaid.config.defaultSession', $config['defaultSession']);
        $container->setParameter('genesis.failaid.config.trackJs', $config['trackJs']);

        $definition = new Definition(Initializer::class, [
            '%genesis.failaid.config.screenshot%',
            '%genesis.failaid.config.siteFilters%',
            '%genesis.failaid.config.debugBarSelectors%',
            '%genesis.failaid.config.trackJs%',
            '%genesis.failaid.config.defaultSession%',
        ]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG);
        $container->setDefinition(self::CONTEXT_INITIALISER, $definition);
    }

    /**
     *
     * @return string
     */
    private function getScreenshotOptions(array $config)
    {
        if (isset($config['screenshot'])) {
            return $config['screenshot'];
        }

        /**
         * DEPRECATED, to be removed in next major version bump.
         */
        return [
            'directory' => isset($config['screenshotDirectory']) ? $config['screenshotDirectory'] : null,
            'mode' => ($config['screenshotMode']) ? $config['screenshotMode'] : null,
        ];
    }
}
