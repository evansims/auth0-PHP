<?php

declare(strict_types=1);

namespace Auth0\SDK\API\Header;

class Header
{
    /**
     * Name of the header.
     *
     * @var string
     */
    protected $header;

    /**
     * Value of the header.
     *
     * @var string
     */
    protected $value;

    /**
     * Header constructor.
     *
     * @param string $header Header name to use.
     * @param string $value  Header value to use.
     */
    public function __construct(
        string $header,
        string $value
    ) {
        $this->header = $header;
        $this->value  = $value;
    }

    /**
     * Return the header name.
     *
     * @return string
     */
    public function getHeader(): string
    {
        return $this->header;
    }

    /**
     * Return the header value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Build and return the header string.
     *
     * @return string
     */
    public function get(): string
    {
        return "{$this->header}: {$this->value}\n";
    }
}
