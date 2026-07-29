<?php

namespace Tests\Unit\SFTP;

use MonkeysLegion\SSH\SFTP\SFTPTransferMetrics;
use PHPUnit\Framework\TestCase;

class SFTPTransferMetricsTest extends TestCase
{
    public function test_metrics_start_at_zero(): void
    {
        $metrics = new SFTPTransferMetrics();

        $this->assertSame(0, $metrics->uploadedBytes());
        $this->assertSame(0, $metrics->downloadedBytes());
        $this->assertSame(0, $metrics->uploadCount());
        $this->assertSame(0, $metrics->downloadCount());
    }

    public function test_record_upload_accumulates_bytes_and_count(): void
    {
        $metrics = new SFTPTransferMetrics();

        $metrics->recordUpload(1024);
        $metrics->recordUpload(2048);

        $this->assertSame(3072, $metrics->uploadedBytes());
        $this->assertSame(2, $metrics->uploadCount());
    }

    public function test_record_download_accumulates_bytes_and_count(): void
    {
        $metrics = new SFTPTransferMetrics();

        $metrics->recordDownload(512);
        $metrics->recordDownload(256);

        $this->assertSame(768, $metrics->downloadedBytes());
        $this->assertSame(2, $metrics->downloadCount());
    }

    public function test_upload_and_download_are_tracked_independently(): void
    {
        $metrics = new SFTPTransferMetrics();

        $metrics->recordUpload(1000);
        $metrics->recordDownload(500);
        $metrics->recordUpload(2000);

        $this->assertSame(3000, $metrics->uploadedBytes());
        $this->assertSame(2, $metrics->uploadCount());
        $this->assertSame(500, $metrics->downloadedBytes());
        $this->assertSame(1, $metrics->downloadCount());
    }

    public function test_record_zero_byte_transfer(): void
    {
        $metrics = new SFTPTransferMetrics();

        $metrics->recordUpload(0);
        $metrics->recordDownload(0);

        $this->assertSame(0, $metrics->uploadedBytes());
        $this->assertSame(1, $metrics->uploadCount());
        $this->assertSame(0, $metrics->downloadedBytes());
        $this->assertSame(1, $metrics->downloadCount());
    }
}
