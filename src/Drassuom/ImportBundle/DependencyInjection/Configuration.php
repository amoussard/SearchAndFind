<?php

namespace Drassuom\ImportBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('drassuom_import');

        $node = $rootNode->children();

        $node = $node
            ->scalarNode('reencoding_path')->defaultValue('/tmp/reencoded/')->end()
            ->arrayNode('types')
                ->requiresAtLeastOneElement()
                ->useAttributeAsKey('type')
                ->prototype('array')
                ->children()
                    ->scalarNode('writer')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('encoding')->defaultNull()->end()
                    ->arrayNode('writer_options')->useAttributeAsKey('key')->prototype('scalar')->end()->end()
                    ->arrayNode('ref_fields')->useAttributeAsKey('name')->prototype('scalar')->end()->end()
                    ->scalarNode('ref_field_name')->end()
                    ->scalarNode('validator')->defaultValue('nova_import.validator.default')->end()
                    ->arrayNode("known_extensions")->useAttributeAsKey('name')->prototype('scalar')->end()->end();

        $this->addCsvConfiguration($node);
        $this->addXmlConfiguration($node);
        $this->addXlsConfiguration($node);
        $this->addConditions($node);
        $this->addFilters($node);
        $this->addConverterConfiguration($node);
        $this->addValidatorsConfiguration($node);

        return $treeBuilder;
    }

    /**
     * @param NodeBuilder $node
     */
    protected function addConditions(NodeBuilder $node) {
        $node
            ->arrayNode('conditions')->useAttributeAsKey('name')->prototype('array')
            ->children()
                ->scalarNode('name')->end()
                ->scalarNode('key')->end()
                ->scalarNode('call')->end()
                ->scalarNode('method')->end()
                ->arrayNode('params')->useAttributeAsKey('name')->prototype('scalar')->end()->end()
                ->scalarNode('required')->end()
                ->scalarNode('merge')->defaultValue(true)->end()
                ->scalarNode('required_value')->end();
    }

    /**
     * @param NodeBuilder $node
     */
    protected function addFilters(NodeBuilder $node) {
        $node
            ->arrayNode('filters')
            ->children()
                ->arrayNode('exclude')->prototype('scalar')->end()->end()
                ->arrayNode('include')->prototype('scalar')->end()->end();
    }

    /**
     * @param NodeBuilder $node
     */
    protected function addXlsConfiguration(NodeBuilder $node) {
        $xlsNode = $node->arrayNode('xls')->children();
        $xlsNode->scalarNode('use_column_header')->defaultFalse()->end();
        $xlsNode->scalarNode('column_header_row')->defaultValue(1)->end();
        $xlsNode->scalarNode('reader')->defaultValue('drassuom_import.reader.xls')->end();
        $xlsNode->scalarNode('mapper')->defaultValue('drassuom_import.mapper.default')->end();
        $this->addMappingConfiguration($xlsNode);
    }

    /**
     * @param NodeBuilder $node
     */
    protected function addXmlConfiguration(NodeBuilder $node) {
        $xmlNode = $node->arrayNode('xml')->children();
        $xmlNode->scalarNode('node_name')->defaultValue('/')->end();
        $xmlNode->scalarNode('reader')->defaultValue('drassuom_import.reader.xml')->end();
        $xmlNode->scalarNode('mapper')->defaultValue('drassuom_import.mapper.xpath')->end();
        $this->addMappingConfiguration($xmlNode);
    }

    /**
     * @param NodeBuilder $node
     */
    protected function addCsvConfiguration(NodeBuilder $node) {
        $csvNode = $node->arrayNode('csv')->children();
        $csvNode->scalarNode('reader')->defaultValue('drassuom_import.reader.csv')->end();
        $csvNode->scalarNode('use_column_header')->defaultFalse()->end();
        $csvNode->scalarNode('column_header_row')->defaultValue(0)->end();
        $csvNode->scalarNode('mapper')->defaultValue('drassuom_import.mapper.default')->end();
        $csvNode->scalarNode('delimiter')->defaultValue(',')->end();
        $this->addMappingConfiguration($csvNode);
    }

    /**
     * @param NodeBuilder $node
     * @param bool        $stopRecursivity
     */
    protected function addMappingConfiguration(NodeBuilder $node, $stopRecursivity = false) {
        $node->arrayNode('child')
            ->children()
                ->scalarNode('path')->end()
                ->scalarNode('type')->end();

        $typeNode = $node
            ->arrayNode('mapping')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
            ->scalarNode('path')->end()
            ->scalarNode('nullable')->defaultFalse()->end()
            ->scalarNode('map_with_attributes')->defaultFalse()->end()
            ->scalarNode('blank_on_null')->defaultFalse()->end()
            ->scalarNode('parent')->defaultNull()->end()
            ->scalarNode('multiple')->defaultFalse()->end();

        if (!$stopRecursivity) {
            $this->addMappingConfiguration($typeNode, true);
        }

        $node
            ->arrayNode('conditional_mapping')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
                ->scalarNode('path')->end()
                ->scalarNode('multiple')->defaultFalse()->end()
                ->scalarNode('nullable')->defaultFalse()->end()
                ->scalarNode('blank_on_null')->defaultFalse()->end()
                ->scalarNode('map_with_attributes')->defaultFalse()->end()
                ->scalarNode('parent')->defaultNull()->end();
        ;
    }

    /**
     * @param NodeBuilder $node
     */
    protected function addConverterConfiguration(NodeBuilder $node) {
        $node
        ->arrayNode('converters')
        ->useAttributeAsKey('key')
        ->prototype('array')
        ->useAttributeAsKey('key')
        ->prototype('array')
        ->children()
            ->scalarNode('value')->defaultNull()->end()
            ->scalarNode('service')->end()
            ->scalarNode('function')->end()
            ->scalarNode('class')->end()
            ->scalarNode('merge')->defaultValue(false)->end()
            ->scalarNode('method')->defaultValue('convert')->end()
            ->arrayNode('conditions')->prototype('scalar')->end()->end()
            ->arrayNode('fields')->useAttributeAsKey('name')->prototype('scalar')->end()->end()
            ->scalarNode('field')->end()
                ->arrayNode('params')
                ->useAttributeAsKey('name')->prototype('scalar')
            ->end();
    }

    /**
     * @param NodeBuilder $node
     */
    protected function addValidatorsConfiguration(NodeBuilder $node) {
        $node
            ->arrayNode('pre_validators')
            ->useAttributeAsKey('key')
            ->prototype('array')
            ->useAttributeAsKey('key')
            ->prototype('array')
            ->children()
                ->scalarNode('function')->end()
                ->scalarNode('message')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('required_value')->end()
                ->scalarNode('method')->end()
                ->scalarNode('nullable')->defaultValue(true)->end()
                ->scalarNode('trim')->defaultValue(false)->end()
                ->arrayNode('conditions')->prototype('scalar')->end()->end()
                ->arrayNode('fields')->useAttributeAsKey('name')->prototype('scalar')->end()->end()
                ->scalarNode('merge')->defaultValue(true)->end()
                ->scalarNode('allow_add_extra')->defaultValue(false)->end()
                ->arrayNode('params')
                ->useAttributeAsKey('name')
                ->prototype('scalar')->end()
            ->end();

        $node
            ->arrayNode('post_validators')
            ->useAttributeAsKey('key')
            ->prototype('array')
            ->useAttributeAsKey('key')
            ->prototype('array')
            ->children()
                ->scalarNode('function')->end()
                ->scalarNode('nullable')->defaultValue(false)->end()
                ->scalarNode('trim')->defaultValue(false)->end()
                ->scalarNode('required_value')->end()
                ->scalarNode('method')->end()
                ->scalarNode('message')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('conditions')->prototype('scalar')->end()->end()
                ->arrayNode('fields')->useAttributeAsKey('name')->prototype('scalar')->end()->end()
                ->scalarNode('merge')->defaultValue(true)->end()
                ->scalarNode('allow_add_extra')->defaultValue(false)->end()
                ->arrayNode('params')->useAttributeAsKey('name')->prototype('scalar')->end()
            ->end();
    }

}
