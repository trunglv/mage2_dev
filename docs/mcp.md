# MCP server

mage2_dev ships a [Model Context Protocol](https://modelcontextprotocol.io) (MCP) server. It lets AI agents such as Claude Code, Claude Desktop, Cursor, VS Code Copilot and Codex call the mage2_dev tools themselves. You ask *"which plugins wrap `CartManagementInterface::placeOrder` in the frontend?"* and the agent runs `show_plugins`, reads the result, and answers.

- [Requirements](#requirements)
- [Install](#install)
- [Running the server](#running-the-server)
- [Tools](#tools)
- [Connect a client](#connect-a-client)
- [Test it without a client](#test-it-without-a-client)
- [How it works](#how-it-works)
- [Troubleshooting](#troubleshooting)

## Requirements

- PHP 8.0 or later. CI lints every file on PHP 8.0 through 8.5.
- Magento 2.4.x or Mage-OS, including Magento 2.4.9 / Mage-OS 3.x with Symfony Console 7.
- No extra Composer packages. The server is plain PHP.

## Install

```bash
composer require trunglv/mage2-dev:dev-main
```

```bash
bin/magento setup:upgrade
```

```bash
bin/magento setup:di:compile
```

`setup:di:compile` matters. The plugin and DI tools read the compiled configuration, so run it again after you change `di.xml` files.

To check that the module works before you connect an agent, run one command by hand:

```bash
bin/magento beta_dev:show_plugins -t "Magento\Quote\Api\CartManagementInterface"
```

## Running the server

You don't normally start the server yourself: the MCP client launches it and talks to it over stdin/stdout. This is the command it runs:

```
bin/magento beta_dev:mcp_server [--read-only] [--timeout=SECONDS]
```

| Option | Default | Effect |
|---|---|---|
| `--read-only` | off | Hides `deploy_static_file` and `deploy_requirejs`, the two tools that write to `pub/static`. Use it when the agent only needs to inspect code. |
| `--timeout` | `300` | Seconds a single tool call may run before its process is killed. |

## Tools

Every tool wraps one `beta_dev:*` console command. Arguments are strings; an argument with listed values only accepts those values.

| Tool | Runs | Arguments (* = required) | Writes files |
|---|---|---|---|
| `show_plugins` | `beta_dev:show_plugins` | `type`* class or interface; `scope`: global, frontend, adminhtml, webapi_rest, graphql (omit for all) | no |
| `show_plugins_by_listener_type` | `beta_dev:show_plugins_by_listener_type` | `plugin_type`*: before, around, after; `scope` | no |
| `check_di` | `beta_dev:check_di` | `type`* class; `scope` (default global) | no |
| `show_observers` | `beta_dev:show_observers` | `event`* event name; `scope`: global, frontend, adminhtml, crontab, webapi_rest, webapi_soap, graphql | no |
| `show_controller_actions` | `beta_dev:show_controller_action` | `frontname`* route frontName; `area`*: frontend, adminhtml | no |
| `api_reflection` | `beta_dev:api_reflection` | `api`* route path from `webapi.xml`, e.g. `/V1/carts/:cartId/shipping-information`; `http_method`*: GET, POST, PUT, DELETE | no |
| `deploy_static_file` | `beta_dev:deploy_static` | `file`* path under the module/theme `web` dir; `theme`* e.g. `Magento/luma`; `area`: frontend, adminhtml; `module` e.g. `Magento_Checkout`; `locale` e.g. `en_US` | yes, `pub/static` |
| `deploy_requirejs` | `beta_dev:deploy_requirejs` | `theme`*; `locale` | yes, `pub/static` |

The server tells clients which tools are read-only, so a client can ask you before it runs a deploy tool.

Questions that work well:

- *Which plugins run around `Magento\Quote\Api\CartManagementInterface` in the frontend, and in what order?*
- *What observers fire on `sales_order_place_after`, and does each one implement `ObserverInterface`?*
- *What does `POST /V1/carts/:cartId/shipping-information` take and return, and which class handles it?*
- *Is anything injected into `Magento\Quote\Model\QuoteManagement` as a concrete class instead of an interface?*
- *List every controller under the `checkout` frontName.*

## Connect a client

Every client needs the same thing: one command that starts the server. Pick the form that matches where PHP runs.

**PHP on the host.** Use the absolute path to `bin/magento`, because some clients (Claude Desktop, for example) don't start the server from your project folder:

```
php /path/to/magento/bin/magento beta_dev:mcp_server
```

**Magento in Docker.** Run it in the PHP container:

```
docker exec -i <php-container> php /var/www/html/bin/magento beta_dev:mcp_server
```

Keep `-i` and never add `-t`. A TTY mixes terminal control codes into the stream and the client can't read it. With `docker compose`, use `docker compose -f /path/to/docker-compose.yml exec -T <service> php bin/magento beta_dev:mcp_server`. `-T` disables the TTY, and `-f` makes it work from any folder.

The examples below use the Docker form with a container named `magento-php`. Replace it with your own command.

### Claude Code

Run this in the Magento project. `--scope project` writes a `.mcp.json` that you can commit for your team:

```bash
claude mcp add mage2-dev --scope project -- docker exec -i magento-php php /var/www/html/bin/magento beta_dev:mcp_server
```

Run `/mcp` in a Claude Code terminal session to check that it's connected.

### Claude Desktop

Edit `~/Library/Application Support/Claude/claude_desktop_config.json` on macOS, or `%APPDATA%\Claude\claude_desktop_config.json` on Windows, then restart Claude Desktop:

```json
{
  "mcpServers": {
    "mage2-dev": {
      "command": "docker",
      "args": ["exec", "-i", "magento-php", "php", "/var/www/html/bin/magento", "beta_dev:mcp_server"]
    }
  }
}
```

### Cursor and Windsurf

Both use the same `mcpServers` block as Claude Desktop:

- Cursor: `.cursor/mcp.json` in the project, or `~/.cursor/mcp.json` for every project.
- Windsurf: `~/.codeium/windsurf/mcp_config.json`.

### VS Code (GitHub Copilot)

`.vscode/mcp.json` uses a `servers` key and needs `"type": "stdio"`:

```json
{
  "servers": {
    "mage2-dev": {
      "type": "stdio",
      "command": "docker",
      "args": ["exec", "-i", "magento-php", "php", "/var/www/html/bin/magento", "beta_dev:mcp_server"]
    }
  }
}
```

### OpenAI Codex CLI

`~/.codex/config.toml`:

```toml
[mcp_servers.mage2-dev]
command = "docker"
args = ["exec", "-i", "magento-php", "php", "/var/www/html/bin/magento", "beta_dev:mcp_server"]
```

### Read-only setup

To give an agent only the inspection tools, add `--read-only` as the last argument in any of the configs above:

```json
"args": ["exec", "-i", "magento-php", "php", "/var/www/html/bin/magento", "beta_dev:mcp_server", "--read-only"]
```

## Test it without a client

The [MCP Inspector](https://github.com/modelcontextprotocol/inspector) opens a web page where you can list the tools and call them by hand:

```bash
npx @modelcontextprotocol/inspector docker exec -i magento-php php /var/www/html/bin/magento beta_dev:mcp_server
```

Or send raw JSON-RPC. Each line is one message, and each reply is one line of JSON:

```bash
printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' | php bin/magento beta_dev:mcp_server
```

## How it works

- **One process per call.** Each tool call runs `php bin/magento <command>` as a new process, so results always reflect the latest `setup:di:compile` and config without restarting the server. A call takes about as long as running the command yourself.
- **No shell.** The command runs from a fixed argument list. Arguments are checked against the tool's schema (unknown names, wrong types and values outside the allowed list are rejected) and passed as `--option=value`, so a value starting with `-` can't turn into another option.
- **Output.** The command's text output is returned as-is, with ANSI colours removed. Anything the command writes to stderr is appended under `[stderr]`. Output over 200 KB is cut off and marked as truncated.
- **Errors.** If the command exits with a non-zero code, the result is marked as an error and includes the output, so the agent can see what went wrong. A call that runs past `--timeout` is killed and reported as timed out.
- **Protocol.** MCP over stdio using newline-delimited JSON-RPC 2.0, protocol versions `2025-06-18`, `2025-03-26` and `2024-11-05`. The server offers tools only, with no resources or prompts.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| The client says the server failed to start, or shows invalid JSON | Something besides the protocol is printing to stdout. Most often the cause is `docker exec -t` or `docker compose exec` without `-T`; remove the TTY. PHP warnings already go to stderr. |
| `Command "beta_dev:mcp_server" is not defined` | The module isn't enabled, or the DI cache is stale. Run `bin/magento module:status Betagento_Developer`, then `setup:upgrade` and `cache:flush`. |
| `Declaration of ...::execute(...) must be compatible with ...: int` | You have a version of this module from before the Symfony 7 fix (Magento 2.4.9 / Mage-OS 3.x). Update to `dev-main`. |
| Plugin or DI results are empty or out of date | Run `bin/magento setup:di:compile`. |
| A tool returns `[timed out after 300s]` | The command was slow, often a deploy on a cold cache. Raise `--timeout`. |
| The server is connected but the client lists no tools | Restart the client after changing its config. In Claude Code, run `/mcp` to see the server's status. |
| You don't see the deploy tools | The server was started with `--read-only`. |
