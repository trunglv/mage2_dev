<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */
declare(strict_types=1);

namespace Betagento\Developer\Mcp;

/**
 * Minimal Model Context Protocol server over stdio (newline-delimited JSON-RPC 2.0).
 *
 * Each tool call runs the matching beta_dev:* command in a fresh `bin/magento` process,
 * so results always reflect the current compiled DI / config, and nothing Magento prints
 * can leak into the protocol stream. Depends on nothing but PHP itself.
 */
class Server
{
    public const SERVER_NAME = 'mage2-dev';
    public const SERVER_VERSION = '0.1.0';
    private const SUPPORTED_PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];
    private const MAX_OUTPUT_BYTES = 200000;

    /**
     * @param resource $in
     * @param resource $out
     */
    public function __construct(
        private string $phpBinary,
        private string $magentoBin,
        private string $workingDir,
        private bool $readOnly = false,
        private int $timeoutSeconds = 300,
        private $in = STDIN,
        private $out = STDOUT
    ) {
    }

    public function run(): void
    {
        while (($line = fgets($this->in)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $response = $this->handleLine($line);
            if ($response !== null) {
                fwrite($this->out, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
                fflush($this->out);
            }
        }
    }

    /**
     * Handle one JSON-RPC message. Returns null for notifications.
     */
    public function handleLine(string $line): ?array
    {
        $message = json_decode($line, true);
        if (!is_array($message) || !isset($message['method']) || !is_string($message['method'])) {
            return $this->error(null, -32700, 'Parse error: expected a JSON-RPC request object');
        }
        $isNotification = !array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            switch ($message['method']) {
                case 'initialize':
                    $result = $this->initialize($params);
                    break;
                case 'ping':
                    $result = new \stdClass();
                    break;
                case 'tools/list':
                    $result = ['tools' => ToolCatalog::toMcpTools($this->readOnly)];
                    break;
                case 'tools/call':
                    $result = $this->callTool($params);
                    break;
                default:
                    if ($isNotification) {
                        return null; // notifications/initialized, notifications/cancelled, ...
                    }
                    return $this->error($id, -32601, 'Method not found: ' . $message['method']);
            }
        } catch (InvalidParamsException $e) {
            return $isNotification ? null : $this->error($id, -32602, $e->getMessage());
        } catch (\Throwable $e) {
            return $isNotification ? null : $this->error($id, -32603, 'Internal error: ' . $e->getMessage());
        }

        return $isNotification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        return [
            'protocolVersion' => in_array($requested, self::SUPPORTED_PROTOCOL_VERSIONS, true)
                ? $requested
                : self::SUPPORTED_PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => self::SERVER_NAME, 'version' => self::SERVER_VERSION],
            'instructions' => 'Introspection tools for a Magento 2 / Mage-OS installation: plugins, DI, observers, '
                . 'controller routes and REST API reflection, plus targeted static-file deployment. '
                . 'Plugin and DI tools read compiled config, so run setup:di:compile first if results look stale.',
        ];
    }

    private function callTool(array $params): array
    {
        $name = $params['name'] ?? null;
        $definitions = ToolCatalog::definitions();
        if (!is_string($name) || !isset($definitions[$name]) || ($this->readOnly && !$definitions[$name]['read_only'])) {
            throw new InvalidParamsException('Unknown tool: ' . (is_string($name) ? $name : '(missing)'));
        }
        $def = $definitions[$name];
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $argv = [$this->phpBinary, $this->magentoBin, $def['command'], '--no-ansi', '--no-interaction'];
        $problems = [];
        foreach ($arguments as $argName => $value) {
            if (!isset($def['args'][$argName])) {
                $problems[] = sprintf('unknown argument "%s"', $argName);
            }
        }
        foreach ($def['args'] as $argName => $arg) {
            $value = $arguments[$argName] ?? null;
            if ($value === null || $value === '') {
                if ($arg['required']) {
                    $problems[] = sprintf('"%s" is required', $argName);
                }
                continue;
            }
            if (!is_string($value)) {
                $problems[] = sprintf('"%s" must be a string', $argName);
                continue;
            }
            if ($arg['enum'] && !in_array($value, $arg['enum'], true)) {
                $problems[] = sprintf('"%s" must be one of: %s', $argName, implode(', ', $arg['enum']));
                continue;
            }
            // --name=value keeps values that start with "-" from being read as options.
            $argv[] = $arg['option'] . '=' . $value;
        }
        if ($problems) {
            return $this->toolResult('Invalid arguments: ' . implode('; ', $problems), true);
        }

        [$exitCode, $output] = $this->runProcess($argv);
        if ($exitCode !== 0) {
            return $this->toolResult(sprintf("Command %s failed (exit code %d):\n%s", $def['command'], $exitCode, $output), true);
        }
        return $this->toolResult($output === '' ? '(no output)' : $output, false);
    }

    /**
     * @param string[] $argv
     * @return array{0: int, 1: string}
     */
    private function runProcess(array $argv): array
    {
        $stderrFile = tempnam(sys_get_temp_dir(), 'mage2dev_mcp_');
        $process = proc_open(
            $argv,
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderrFile, 'w']],
            $pipes,
            $this->workingDir
        );
        if (!is_resource($process)) {
            @unlink($stderrFile);
            return [127, 'Unable to start process: ' . implode(' ', $argv)];
        }

        stream_set_blocking($pipes[1], false);
        $stdout = '';
        $deadline = time() + $this->timeoutSeconds;
        $timedOut = false;
        while (!feof($pipes[1])) {
            if (time() >= $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
            $read = [$pipes[1]];
            $write = $except = null;
            if (@stream_select($read, $write, $except, 1) > 0) {
                $stdout .= (string) fread($pipes[1], 65536);
            }
        }
        fclose($pipes[1]);
        $exitCode = proc_close($process);
        $stderr = (string) @file_get_contents($stderrFile);
        @unlink($stderrFile);

        $output = $this->cleanOutput($stdout);
        $stderr = $this->cleanOutput($stderr);
        if ($stderr !== '') {
            $output .= ($output === '' ? '' : "\n\n") . "[stderr]\n" . $stderr;
        }
        if ($timedOut) {
            return [124, $output . "\n\n[timed out after {$this->timeoutSeconds}s]"];
        }
        return [$exitCode, $output];
    }

    private function cleanOutput(string $text): string
    {
        $text = (string) preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $text);
        $text = trim($text);
        if (strlen($text) > self::MAX_OUTPUT_BYTES) {
            $text = substr($text, 0, self::MAX_OUTPUT_BYTES) . "\n\n[output truncated at " . self::MAX_OUTPUT_BYTES . ' bytes]';
        }
        return $text;
    }

    private function toolResult(string $text, bool $isError): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError];
    }

    private function error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
