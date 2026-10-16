<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */
declare(strict_types=1);

namespace Betagento\Developer\Mcp;

// phpcs:disable Magento2.Functions.DiscouragedFunction
// This is a CLI stdio server: it talks to its own STDIN/STDOUT and to child processes,
// never to the Magento filesystem, so Filesystem\DriverInterface does not apply.

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
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @var string
     */
    private $phpBinary;

    /**
     * @var string
     */
    private $magentoBin;

    /**
     * @var string
     */
    private $workingDir;

    /**
     * @var bool
     */
    private $readOnly;

    /**
     * @var int
     */
    private $timeoutSeconds;

    /**
     * @var resource
     */
    private $in;

    /**
     * @var resource
     */
    private $out;

    /**
     * @var ToolCatalog
     */
    private $catalog;

    /**
     * @param string $phpBinary
     * @param string $magentoBin
     * @param string $workingDir
     * @param bool $readOnly
     * @param int $timeoutSeconds
     * @param resource|null $in
     * @param resource|null $out
     */
    public function __construct(
        string $phpBinary,
        string $magentoBin,
        string $workingDir,
        bool $readOnly = false,
        int $timeoutSeconds = 300,
        $in = null,
        $out = null
    ) {
        $this->phpBinary = $phpBinary;
        $this->magentoBin = $magentoBin;
        $this->workingDir = $workingDir;
        $this->readOnly = $readOnly;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->in = $in ?? STDIN;
        $this->out = $out ?? STDOUT;
        $this->catalog = new ToolCatalog();
    }

    /**
     * Serve requests until STDIN closes
     *
     * @return void
     */
    public function run(): void
    {
        while (($line = fgets($this->in)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $response = $this->handleLine($line);
            if ($response !== null) {
                fwrite($this->out, json_encode($response, self::JSON_FLAGS) . "\n");
                fflush($this->out);
            }
        }
    }

    /**
     * Handle one JSON-RPC message. Returns null for notifications.
     *
     * @param string $line
     * @return array|null
     */
    public function handleLine(string $line): ?array
    {
        $message = json_decode($line, true);
        if (!is_array($message) || !isset($message['method']) || !is_string($message['method'])) {
            return $this->error(null, -32700, 'Parse error: expected a JSON-RPC request object');
        }
        $isNotification = !array_key_exists('id', $message);
        $id = $message['id'] ?? null;

        try {
            $result = $this->dispatch($message['method'], $this->arrayOrEmpty($message['params'] ?? null));
            $response = ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (JsonRpcException $e) {
            $response = $this->error($id, $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            $response = $this->error($id, -32603, 'Internal error: ' . $e->getMessage());
        }

        // Notifications (no id) never get a response, not even an error.
        return $isNotification ? null : $response;
    }

    /**
     * Route a request to its handler
     *
     * @param string $method
     * @param array $params
     * @return array|\stdClass
     * @throws JsonRpcException
     */
    private function dispatch(string $method, array $params)
    {
        switch ($method) {
            case 'initialize':
                return $this->initialize($params);
            case 'ping':
                return new \stdClass();
            case 'tools/list':
                return ['tools' => $this->catalog->toMcpTools($this->readOnly)];
            case 'tools/call':
                return $this->callTool($params);
            default:
                throw new JsonRpcException('Method not found: ' . $method, -32601);
        }
    }

    /**
     * Answer the MCP initialize handshake
     *
     * @param array $params
     * @return array
     */
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

    /**
     * Validate a tools/call request and run the mapped console command
     *
     * @param array $params
     * @return array
     * @throws InvalidParamsException
     */
    private function callTool(array $params): array
    {
        $def = $this->findTool($params['name'] ?? null);
        $arguments = $this->arrayOrEmpty($params['arguments'] ?? null);

        $problems = $this->validateArguments($def['args'], $arguments);
        if ($problems) {
            return $this->toolResult('Invalid arguments: ' . implode('; ', $problems), true);
        }

        $argv = [$this->phpBinary, $this->magentoBin, $def['command'], '--no-ansi', '--no-interaction'];
        foreach ($def['args'] as $argName => $arg) {
            if (($arguments[$argName] ?? '') !== '') {
                // --name=value keeps values that start with "-" from being read as options.
                $argv[] = $arg['option'] . '=' . $arguments[$argName];
            }
        }

        [$exitCode, $output] = $this->runProcess($argv);
        if ($exitCode !== 0) {
            $message = sprintf("Command %s failed (exit code %d):\n%s", $def['command'], $exitCode, $output);
            return $this->toolResult($message, true);
        }
        return $this->toolResult($output === '' ? '(no output)' : $output, false);
    }

    /**
     * Look up an exposed tool definition by name
     *
     * @param mixed $name
     * @return array
     * @throws InvalidParamsException
     */
    private function findTool($name): array
    {
        $definitions = $this->catalog->definitions();
        $def = is_string($name) ? ($definitions[$name] ?? null) : null;
        if ($def === null || ($this->readOnly && !$def['read_only'])) {
            throw new InvalidParamsException('Unknown tool: ' . (is_string($name) ? $name : '(missing)'));
        }
        return $def;
    }

    /**
     * Check call arguments against a tool's schema; returns a list of problems
     *
     * @param array $schema
     * @param array $arguments
     * @return string[]
     */
    private function validateArguments(array $schema, array $arguments): array
    {
        $problems = [];
        foreach (array_keys(array_diff_key($arguments, $schema)) as $argName) {
            $problems[] = sprintf('unknown argument "%s"', $argName);
        }
        foreach ($schema as $argName => $arg) {
            $problem = $this->validateArgument($argName, $arg, $arguments[$argName] ?? null);
            if ($problem !== null) {
                $problems[] = $problem;
            }
        }
        return $problems;
    }

    /**
     * Check one argument value; returns a problem description or null when valid
     *
     * @param string $argName
     * @param array $arg
     * @param mixed $value
     * @return string|null
     */
    private function validateArgument(string $argName, array $arg, $value): ?string
    {
        if ($value === null || $value === '') {
            return $arg['required'] ? sprintf('"%s" is required', $argName) : null;
        }
        if (!is_string($value)) {
            return sprintf('"%s" must be a string', $argName);
        }
        if ($arg['enum'] && !in_array($value, $arg['enum'], true)) {
            return sprintf('"%s" must be one of: %s', $argName, implode(', ', $arg['enum']));
        }
        return null;
    }

    /**
     * Run a command without a shell; return its exit code and combined, cleaned output
     *
     * @param string[] $argv
     * @return array{0: int, 1: string}
     */
    private function runProcess(array $argv): array
    {
        // phpcs:disable Magento2.Security.InsecureFunction
        // proc_* is how this server runs bin/magento itself: a fixed argv array (no shell),
        // with user input passed only as --option=value strings validated against the tool schema.
        $stderrFile = tempnam(sys_get_temp_dir(), 'mage2dev_mcp_');
        try {
            $process = proc_open(
                $argv,
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderrFile, 'w']],
                $pipes,
                $this->workingDir
            );
            if (!is_resource($process)) {
                return [127, 'Unable to start process: ' . implode(' ', $argv)];
            }
            [$stdout, $timedOut] = $this->readUntilExit($process, $pipes[1]);
            $exitCode = proc_close($process);
            $stderr = (string) file_get_contents($stderrFile);
        } finally {
            if (is_file($stderrFile)) {
                unlink($stderrFile);
            }
        }
        // phpcs:enable Magento2.Security.InsecureFunction

        $output = $this->joinOutput($this->cleanOutput($stdout), $this->cleanOutput($stderr));
        if ($timedOut) {
            return [124, $output . "\n\n[timed out after {$this->timeoutSeconds}s]"];
        }
        return [$exitCode, $output];
    }

    /**
     * Read a process's stdout until it exits, killing it once the timeout passes
     *
     * @param resource $process
     * @param resource $stdoutPipe
     * @return array{0: string, 1: bool} stdout and whether it timed out
     */
    private function readUntilExit($process, $stdoutPipe): array
    {
        $stdout = '';
        $timedOut = false;
        $deadline = time() + $this->timeoutSeconds;
        try {
            stream_set_blocking($stdoutPipe, false);
            while (!feof($stdoutPipe)) {
                if (time() >= $deadline) {
                    $timedOut = true;
                    // phpcs:disable Magento2.Security.InsecureFunction -- kill our own child process on timeout
                    proc_terminate($process, 9);
                    // phpcs:enable Magento2.Security.InsecureFunction
                    break;
                }
                $read = [$stdoutPipe];
                $write = null;
                $except = null;
                if (stream_select($read, $write, $except, 1) > 0) {
                    $stdout .= (string) fread($stdoutPipe, 65536);
                }
            }
        } finally {
            fclose($stdoutPipe);
        }
        return [$stdout, $timedOut];
    }

    /**
     * Append stderr (if any) to stdout under a marker
     *
     * @param string $stdout
     * @param string $stderr
     * @return string
     */
    private function joinOutput(string $stdout, string $stderr): string
    {
        if ($stderr === '') {
            return $stdout;
        }
        return ($stdout === '' ? '' : $stdout . "\n\n") . "[stderr]\n" . $stderr;
    }

    /**
     * Return the value if it is an array, otherwise an empty array
     *
     * @param mixed $value
     * @return array
     */
    private function arrayOrEmpty($value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Strip ANSI escapes and cap the size of command output
     *
     * @param string $text
     * @return string
     */
    private function cleanOutput(string $text): string
    {
        $text = (string) preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $text);
        $text = trim($text);
        if (strlen($text) > self::MAX_OUTPUT_BYTES) {
            $text = substr($text, 0, self::MAX_OUTPUT_BYTES)
                . "\n\n[output truncated at " . self::MAX_OUTPUT_BYTES . ' bytes]';
        }
        return $text;
    }

    /**
     * Wrap text as an MCP tool result
     *
     * @param string $text
     * @param bool $isError
     * @return array
     */
    private function toolResult(string $text, bool $isError): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError];
    }

    /**
     * Build a JSON-RPC error response
     *
     * @param mixed $id
     * @param int $code
     * @param string $message
     * @return array
     */
    private function error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
