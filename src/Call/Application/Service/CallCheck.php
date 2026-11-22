<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

/**
 * One line of the calls check: what was checked, the outcome, what was seen and,
 * when something is wrong, what to do about it. Never carries secrets.
 */
final readonly class CallCheck
{
    public function __construct(
        public string $key,
        public string $label,
        public CallCheckStatus $status,
        public string $detail,
        public ?string $hint = null,
    ) {
    }

    public static function ok(string $key, string $label, string $detail, ?string $hint = null): self
    {
        return new self($key, $label, CallCheckStatus::Ok, $detail, $hint);
    }

    public static function warn(string $key, string $label, string $detail, string $hint): self
    {
        return new self($key, $label, CallCheckStatus::Warn, $detail, $hint);
    }

    public static function fail(string $key, string $label, string $detail, string $hint): self
    {
        return new self($key, $label, CallCheckStatus::Fail, $detail, $hint);
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string, hint: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status->value,
            'detail' => $this->detail,
            'hint' => $this->hint,
        ];
    }
}
