<?php
/*
 * MyTelegram / MTG-server-compatible profile.
 * This profile pins MadelineProto to the private WebSocket endpoint configured
 * with PRIVATE_SERVER_HOST/PORT and swaps in the private server RSA key.
 */

function mpgram_apply_connection_settings(\danog\MadelineProto\Settings $settings): void
{
    $connection = $settings->getConnection();
    $useWss = defined('PRIVATE_SERVER_USE_WSS') && PRIVATE_SERVER_USE_WSS;
    $connection->setTransport($useWss
        ? \danog\MadelineProto\Stream\Transport\WssStream::class
        : \danog\MadelineProto\Stream\Transport\WsStream::class);
    $connection->setProtocol(\danog\MadelineProto\Stream\MTProtoTransport\AbridgedStream::class);
    $connection->setObfuscated(false);
    $connection->setIpv6(false);
    $connection->setTestMode(false);

    $modulusHex = defined('PRIVATE_SERVER_RSA_MODULUS_HEX') ? PRIVATE_SERVER_RSA_MODULUS_HEX : 'bbededbec7160c0944bd5ca54de32be45a54d808e0ab3a101cf8f3a7af6bd1802dab46bcad7d0c51eefc17f15102a05a11b656e960731770233a5358a4eb6fbf01a197dac60a0ce2ba76ddf67c1c28904c0d64bd3bb333ffcc63cffb30201e15e7a5dc8ce86b8d41c9fc69e214aa2e9b4d317847189ebe719cb7acbe954cabdec66ba6fec6ddc745fb4763f672d5d1b9cecf2ea6e8803a51222a2961bb522d85f323146dcd17a4e21ab3bd614dd88b115b272ebb8ed1e4bf915aaec70cd9f0b989643678fd72ea35d1eb8b065374239dcbe8cd839e3eb1fd8c67279b35268f8db1fc7dbc223250f448c4736dac3ceb9ab8ad0817642208687e4dfb0a08ad7cf7';
    $exponentHex = defined('PRIVATE_SERVER_RSA_EXPONENT_HEX') ? PRIVATE_SERVER_RSA_EXPONENT_HEX : '010001';
    $pem = MP::hexRsaToPem($modulusHex, $exponentHex);
    $connection->setRsaKeys([$pem]);
    $connection->setTestRsaKeys([$pem]);
}

function mpgram_connection_uses_private_dc(): bool
{
    return defined('PRIVATE_SERVER_HOST') && PRIVATE_SERVER_HOST !== '';
}
