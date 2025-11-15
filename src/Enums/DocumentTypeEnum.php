<?php

namespace Nava\MyInvois\Enums;

enum DocumentTypeEnum: int
{
    case INVOICE = 4;
    case CREDIT_NOTE = 11;
    case DEBIT_NOTE = 12;

    public function description(): string
    {
        return match ($this) {
            self::INVOICE => 'Invoice',
            self::CREDIT_NOTE => 'Credit Note',
            self::DEBIT_NOTE => 'Debit Note',
        };
    }

    public static function getCodes(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }

    public static function fromCode(int $code): self
    {
        return self::from($code);
    }
}
