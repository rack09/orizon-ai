<?php
declare(strict_types=1);
namespace Orizon;

// Limiti tecnici condivisi dal backend. Le quote configurabili restano in Config.
final class Limits
{
    public const MAX_MESSAGE_CHARACTERS = 1200;
    public const MAX_REQUEST_BYTES = 8192;
    public const MAX_HISTORY_BYTES = 1000000;
    public const MAX_MODEL_ROUNDS = 3;
    public const MAX_TOOL_CALLS_PER_RESPONSE = 8;
    public const MAX_CALCULATIONS_PER_MESSAGE = 3;
    public const MAX_TOOL_CALL_ID_BYTES = 200;
    public const MAX_TOOL_NAME_BYTES = 100;

    public static function publicValues(): array
    {
        return ['message_characters' => self::MAX_MESSAGE_CHARACTERS];
    }
}
