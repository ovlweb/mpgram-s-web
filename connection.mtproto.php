<?php
/*
 * Public Telegram MTProto profile.
 * Keep this file intentionally small: MadelineProto's defaults already know
 * Telegram's public DC list, ports, transports, and RSA keys.
 */

function mpgram_apply_connection_settings(\danog\MadelineProto\Settings $settings): void
{
    $connection = $settings->getConnection();
    $connection->setIpv6(false);
    $connection->setTestMode(false);
}

function mpgram_connection_uses_private_dc(): bool
{
    return false;
}
