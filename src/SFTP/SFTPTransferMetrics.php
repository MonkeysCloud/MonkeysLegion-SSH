<?php

namespace MonkeysLegion\SSH\SFTP;

class SFTPTransferMetrics
{
    private int $uploadedBytes = 0;
    private int $downloadedBytes = 0;
    private int $uploadCount = 0;
    private int $downloadCount = 0;

    public function recordUpload(int $bytes): void
    {
        $this->uploadedBytes += $bytes;
        $this->uploadCount++;
    }

    public function recordDownload(int $bytes): void
    {
        $this->downloadedBytes += $bytes;
        $this->downloadCount++;
    }

    public function uploadedBytes(): int
    {
        return $this->uploadedBytes;
    }

    public function downloadedBytes(): int
    {
        return $this->downloadedBytes;
    }

    public function uploadCount(): int
    {
        return $this->uploadCount;
    }

    public function downloadCount(): int
    {
        return $this->downloadCount;
    }
}
