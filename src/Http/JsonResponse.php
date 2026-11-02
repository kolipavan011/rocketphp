<?php

declare(strict_types=1);

namespace Rocket\Http;

use JsonException;

/**
 * Class JsonResponse
 *
 * Specialized HTTP response for JSON data with fast serialization.
 */
class JsonResponse extends Response
{
    public function __construct(
        mixed $data = [],
        int $statusCode = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) {
        $content = self::encodeData($data, $encodingOptions);
        $headers['Content-Type'] = 'application/json; charset=UTF-8';

        parent::__construct($content, $statusCode, $headers);
    }

    /**
     * Encode data to JSON securely.
     */
    protected static function encodeData(mixed $data, int $options): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | $options);
        } catch (JsonException $e) {
            return json_encode([
                'error'   => 'JSON Serialization Error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the response payload with new data.
     */
    public function setData(mixed $data, int $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): static
    {
        $this->setContent(self::encodeData($data, $encodingOptions));
        return $this;
    }
}

