<?php
/**
 * @author Trung Luu <luuvantrung@gmail.com> https://github.com/trunglv/mage2_dev
 */
declare(strict_types=1);

namespace Betagento\Developer\Mcp;

/**
 * JSON-RPC error -32602 (Invalid params).
 */
class InvalidParamsException extends JsonRpcException
{
    /**
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct($message, -32602);
    }
}
