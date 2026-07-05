<?php

const SSH2_TERM_UNIT_CHARS = 0;
const SSH2_STREAM_STDERR = 1;

/**
 * @param resource|object $session
 */
function ssh2_auth_password(mixed $session, string $username, string $password): bool
{
}

/**
 * @param resource|object $session
 */
function ssh2_auth_pubkey_file(
    mixed $session,
    string $username,
    string $pubkeyfile,
    string $privkeyfile,
    string $passphrase = ''
): bool {
}

/**
 * @return resource|object|false
 */
function ssh2_connect(string $host, int $port = 22, array $methods = [], array $callbacks = []): mixed
{
}

/**
 * @param resource|object $session
 * @return resource|object|false
 */
function ssh2_exec(mixed $session, string $command, ?string $pty = null, array $env = [], int $width = 80, int $height = 25, int $width_height_type = SSH2_TERM_UNIT_CHARS): mixed
{
}

/**
 * @param resource|object $channel
 * @return resource|object|false
 */
function ssh2_fetch_stream(mixed $channel, int $streamid): mixed
{
}

/**
 * @param resource|object $channel
 */
function ssh2_channel_get_exit_status(mixed $channel): int|false
{
}

/**
 * @param resource|object $channel
 */
function ssh2_get_exit_status(mixed $channel): int|false
{
}

/**
 * @param resource|object $session
 * @return resource|object|false
 */
function ssh2_sftp(mixed $session): mixed
{
}

/**
 * @param resource|object $sftp
 */
function ssh2_sftp_chmod(mixed $sftp, string $filename, int $mode): bool
{
}
