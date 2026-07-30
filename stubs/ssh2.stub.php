<?php

const SSH2_TERM_UNIT_CHARS = 0;
const SSH2_STREAM_STDERR = 1;

const SSH2_FINGERPRINT_MD5 = 0;
const SSH2_FINGERPRINT_SHA1 = 1;
const SSH2_FINGERPRINT_HEX = 0;
const SSH2_FINGERPRINT_RAW = 2;

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
 * @param resource|object $session
 */
function ssh2_auth_agent(mixed $session, string $username): bool
{
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
 * @param resource|object $session
 * @return resource|object|false
 */
function ssh2_shell(mixed $session, ?string $term_type = null, array $env = [], int $width = 80, int $height = 25, int $width_height_type = SSH2_TERM_UNIT_CHARS): mixed
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
 * @param int $flags
 */
function ssh2_fingerprint(mixed $session, int $flags = 0): string|false
{
}

/**
 * @param resource|object $session
 */
function ssh2_disconnect(mixed $session): bool
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

/**
 * @param resource|object $sftp
 * @return array<string, int>|false
 */
function ssh2_sftp_stat(mixed $sftp, string $path): array|false
{
}

/**
 * @param resource|object $sftp
 * @return array<string, int>|false
 */
function ssh2_sftp_lstat(mixed $sftp, string $path): array|false
{
}

/**
 * @param resource|object $sftp
 */
function ssh2_sftp_rename(mixed $sftp, string $from, string $to): bool
{
}

/**
 * @param resource|object $sftp
 */
function ssh2_sftp_symlink(mixed $sftp, string $target, string $link): bool
{
}

/**
 * @param resource|object $sftp
 * @return string|false
 */
function ssh2_sftp_readlink(mixed $sftp, string $link): string|false
{
}

/**
 * @param resource|object $session
 */
function ssh2_scp_send(mixed $session, string $local_file, string $remote_file, int $create_mode = 0o644): bool
{
}

/**
 * @param resource|object $session
 */
function ssh2_scp_recv(mixed $session, string $remote_file, string $local_file): bool
{
}
