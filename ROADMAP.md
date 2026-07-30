# Roadmap

## v0.2.0 — File Operations
- SFTP directory listing (`ls` / `nlist` / `rawlist`)
- SFTP `rename`, `stat`, `symlink`, `file_exists`
- SCP support

## v0.3.0 — Connection & Transport
- Connection keepalive / heartbeat
- Interactive PTY shell (`ssh2_shell`)
- Per-channel command timeouts
- Channel multiplexing (reuse connection for multiple exec channels)

## v0.4.0 — Advanced Networking
- Local / remote port forwarding (tunnels)
- Jump host / bastion proxy support

## v0.5.0 — Ecosystem & DX
- MonkeysLegion service provider (auto-discovery)
- README badges (Packagist, CI, PHP version)
- `CONTRIBUTING.md` / `SECURITY.md` / issue templates

## Beyond 1.0
- Configuration profiles from file / directory
- Event hooks / middleware pipeline
- Async / parallel command execution
- Key deployment utility
