<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */
declare(strict_types=1);

namespace Betagento\Developer\Mcp;

/**
 * MCP tool definitions. Each tool maps 1:1 to a beta_dev:* console command;
 * every argument maps to a long option of that command.
 */
class ToolCatalog
{
    private const PLUGIN_SCOPES = ['global', 'frontend', 'adminhtml', 'webapi_rest', 'graphql'];
    private const EVENT_SCOPES = [
        'global', 'frontend', 'adminhtml', 'crontab', 'webapi_rest', 'webapi_soap', 'graphql'
    ];
    private const SCOPE_HINT = 'Limit to one area scope. Omit to show every scope.';
    private const LOCALE_HINT = 'Locale, e.g. en_US. Omit for all deployed locales.';

    /**
     * Tool name => command, description, whether it is read-only, and its arguments
     *
     * @return array<string, array{command: string, description: string, read_only: bool, args: array<string, array>}>
     */
    public function definitions(): array
    {
        return [
            'show_plugins' => [
                'command' => 'beta_dev:show_plugins',
                'description' => 'List all plugins (interceptors) injected into a class or interface, per area scope. '
                    . 'Requires setup:di:compile to have run.',
                'read_only' => true,
                'args' => [
                    'type' => $this->arg(
                        '--type',
                        'Fully qualified class/interface, e.g. Magento\\Quote\\Api\\CartManagementInterface',
                        true
                    ),
                    'scope' => $this->arg('--scope_code', self::SCOPE_HINT, false, self::PLUGIN_SCOPES),
                ],
            ],
            'show_plugins_by_listener_type' => [
                'command' => 'beta_dev:show_plugins_by_listener_type',
                'description' => 'List every plugin of a given listener type (before, around, after) '
                    . 'across the codebase.',
                'read_only' => true,
                'args' => [
                    'plugin_type' => $this->arg(
                        '--plugin_type',
                        'Plugin listener type.',
                        true,
                        ['before', 'around', 'after']
                    ),
                    'scope' => $this->arg('--scope_code', self::SCOPE_HINT, false, self::PLUGIN_SCOPES),
                ],
            ],
            'check_di' => [
                'command' => 'beta_dev:check_di',
                'description' => 'Inspect the constructor dependencies of a class: the resolved preference/instance '
                    . 'for each injected param, and warnings where a concrete class is injected instead of an '
                    . 'interface. Requires setup:di:compile to have run.',
                'read_only' => true,
                'args' => [
                    'type' => $this->arg(
                        '--type',
                        'Fully qualified class name, e.g. Magento\\Quote\\Model\\QuoteManagement',
                        true
                    ),
                    'scope' => $this->arg('--scope_code', 'Area scope (default: global).', false, self::PLUGIN_SCOPES),
                ],
            ],
            'show_observers' => [
                'command' => 'beta_dev:show_observers',
                'description' => 'List all observers registered for an event, per area scope, and check each '
                    . 'implements ObserverInterface.',
                'read_only' => true,
                'args' => [
                    'event' => $this->arg('--event', 'Event name, e.g. catalog_product_get_final_price', true),
                    'scope' => $this->arg('--scope_code', self::SCOPE_HINT, false, self::EVENT_SCOPES),
                ],
            ],
            'show_controller_actions' => [
                'command' => 'beta_dev:show_controller_action',
                'description' => 'List all controller action classes reachable under a route frontName in an area.',
                'read_only' => true,
                'args' => [
                    'frontname' => $this->arg('--frontname', 'Route frontName, e.g. checkout or catalog', true),
                    'area' => $this->arg('--area', 'Area code.', true, ['frontend', 'adminhtml']),
                ],
            ],
            'api_reflection' => [
                'command' => 'beta_dev:api_reflection',
                'description' => 'Describe a REST Web API route: service class, preference class, method, '
                    . 'input params and output type.',
                'read_only' => true,
                'args' => [
                    'api' => $this->arg(
                        '--api',
                        'Route path as declared in webapi.xml, e.g. /V1/carts/:cartId/shipping-information',
                        true
                    ),
                    'http_method' => $this->arg(
                        '--http_method',
                        'HTTP method.',
                        true,
                        ['GET', 'POST', 'PUT', 'DELETE']
                    ),
                ],
            ],
            'deploy_static_file' => [
                'command' => 'beta_dev:deploy_static',
                'description' => 'Deploy a single static file (JS, CSS, HTML, js-translation.json) into pub/static '
                    . 'for a theme, without running a full setup:static-content:deploy. Writes files.',
                'read_only' => false,
                'args' => [
                    'file' => $this->arg(
                        '--file_path',
                        'File path relative to the module/theme web dir, e.g. js/view/shipping.js, '
                            . 'css/styles-l.css, js-translation.json',
                        true
                    ),
                    'theme' => $this->arg('--theme_path', 'Theme path, e.g. Magento/luma', true),
                    'area' => $this->arg('--area', 'Area (default: frontend).', false, ['frontend', 'adminhtml']),
                    'module' => $this->arg('--module_name', 'Module the file belongs to, e.g. Magento_Checkout', false),
                    'locale' => $this->arg('--locale_code', self::LOCALE_HINT, false),
                ],
            ],
            'deploy_requirejs' => [
                'command' => 'beta_dev:deploy_requirejs',
                'description' => 'Regenerate requirejs-config.js for a theme in pub/static. Writes files.',
                'read_only' => false,
                'args' => [
                    'theme' => $this->arg('--theme_path', 'Theme path, e.g. Magento/luma', true),
                    'locale' => $this->arg('--locale_code', self::LOCALE_HINT, false),
                ],
            ],
        ];
    }

    /**
     * Tool list in MCP tools/list format.
     *
     * @param bool $readOnlyOnly
     * @return array<int, array>
     */
    public function toMcpTools(bool $readOnlyOnly): array
    {
        $tools = [];
        foreach ($this->definitions() as $name => $def) {
            if ($readOnlyOnly && !$def['read_only']) {
                continue;
            }
            $properties = [];
            $required = [];
            foreach ($def['args'] as $argName => $arg) {
                $property = ['type' => 'string', 'description' => $arg['description']];
                if ($arg['enum']) {
                    $property['enum'] = $arg['enum'];
                }
                $properties[$argName] = $property;
                if ($arg['required']) {
                    $required[] = $argName;
                }
            }
            $tools[] = [
                'name' => $name,
                'description' => $def['description'],
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => (object) $properties,
                    'required' => $required,
                    'additionalProperties' => false,
                ],
                'annotations' => [
                    'readOnlyHint' => $def['read_only'],
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ];
        }
        return $tools;
    }

    /**
     * Describe one tool argument and the console option it maps to
     *
     * @param string $option
     * @param string $description
     * @param bool $required
     * @param string[] $enum
     * @return array
     */
    private function arg(string $option, string $description, bool $required, array $enum = []): array
    {
        return ['option' => $option, 'description' => $description, 'required' => $required, 'enum' => $enum];
    }
}
