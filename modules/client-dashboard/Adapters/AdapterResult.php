<?php
/**
 * Normalized adapter envelope (partial failure safe).
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_AdapterResult
{
    /** @var bool */
    public $ok;

    /** @var string */
    public $source;

    /** @var mixed */
    public $data;

    /** @var string|null */
    public $message;

    /**
     * @param mixed $data
     */
    public function __construct(bool $ok, string $source, $data, ?string $message = null)
    {
        $this->ok = $ok;
        $this->source = $source;
        $this->data = $data;
        $this->message = $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'source' => $this->source,
            'data' => $this->data,
            'message' => $this->message,
        ];
    }
}
