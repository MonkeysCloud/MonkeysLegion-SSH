<?php

namespace Tests\Feature;

use MonkeysLegion\SSH\Core\CommandPipeline;
use MonkeysLegion\SSH\Core\SSHManager;
use PHPUnit\Framework\TestCase;
use MonkeysLegion\SSH\Facades\SSH;
use MonkeysLegion\SSH\Stream\CommandResult;

class SSHConnectionFeatureTest extends TestCase
{
    public function test_can_connect_via_facade(): void
    {
        $this->skipUnlessIntegrationEnabled();

        SSH::setManager($this->integrationManager());
        $connection = SSH::connection('integration');

        $this->assertTrue($connection->isConnected());
    }

    public function test_can_execute_remote_command(): void
    {
        $this->skipUnlessIntegrationEnabled();

        SSH::setManager($this->integrationManager());
        $connection = SSH::connection('integration');
        $result = $connection->execute('sh -lc "echo STDOUT && echo STDERR 1>&2 && exit 7"');

        $this->assertInstanceOf(CommandResult::class, $result);
        $this->assertStringContainsString('STDOUT', $result->output);
        $this->assertStringContainsString('STDERR', $result->error);
        $this->assertSame(7, $result->exitCode);
        $this->assertTrue($result->failed());
    }

    public function test_pipeline_halts_on_non_zero_exit_code(): void
    {
        $this->skipUnlessIntegrationEnabled();

        SSH::setManager($this->integrationManager());
        $connection = SSH::connection('integration');

        $result = $connection->pipeline(static function (CommandPipeline $pipeline): void {
            $pipeline
                ->add('echo FIRST')
                ->add('false')
                ->add('echo THIRD');
        }, true);

        $this->assertTrue($result->halted);
        $this->assertSame(1, $result->failedStep);
        $this->assertCount(2, $result->results);
        $this->assertSame(0, $result->results[0]->exitCode);
        $this->assertNotSame(0, $result->results[1]->exitCode);
    }

    public function test_sftp_upload_download_and_metrics(): void
    {
        $this->skipUnlessIntegrationEnabled();

        SSH::setManager($this->integrationManager());
        $connection = SSH::connection('integration');
        $home = \trim($connection->execute('printf %s "$HOME"')->output);
        $this->assertNotSame('', $home);

        $remoteBaseDir = $home . '/mlssh-phase3-' . \bin2hex(\random_bytes(4));
        $remoteFile = $remoteBaseDir . '/nested/upload.txt';
        $downloadPath = \tempnam(\sys_get_temp_dir(), 'mlssh-dl-');
        $uploadPath = \tempnam(\sys_get_temp_dir(), 'mlssh-up-');
        $this->assertNotFalse($downloadPath);
        $this->assertNotFalse($uploadPath);

        $content = "phase3-sftp\n" . \bin2hex(\random_bytes(8));
        \file_put_contents($uploadPath, $content);

        $sftp = $connection->sftp();
        $sftp->mkdir($remoteBaseDir . '/nested', 0775, true);

        $uploaded = $sftp->upload($uploadPath, $remoteFile);
        $sftp->chmod($remoteFile, 0644);
        $downloaded = $sftp->download($remoteFile, $downloadPath);

        $this->assertSame(\strlen($content), $uploaded);
        $this->assertSame(\strlen($content), $downloaded);
        $this->assertSame($content, (string) \file_get_contents($downloadPath));

        $metrics = $sftp->metrics();
        $this->assertSame(\strlen($content), $metrics->uploadedBytes());
        $this->assertSame(\strlen($content), $metrics->downloadedBytes());
        $this->assertSame(1, $metrics->uploadCount());
        $this->assertSame(1, $metrics->downloadCount());

        $sftp->delete($remoteFile);
        $connection->execute('rm -rf ' . \escapeshellarg($remoteBaseDir));
        \unlink($uploadPath);
        \unlink($downloadPath);
    }

    private function integrationManager(): SSHManager
    {
        $host = \getenv('INTEGRATION_SSH_HOST');
        $port = \getenv('INTEGRATION_SSH_PORT');
        $username = \getenv('INTEGRATION_SSH_USERNAME');
        $password = \getenv('INTEGRATION_SSH_PASSWORD');

        return new SSHManager([
            'default' => 'integration',
            'connections' => [
                'integration' => [
                    'host' => \is_string($host) && $host !== '' ? $host : '127.0.0.1',
                    'port' => \is_string($port) && $port !== '' ? (int) $port : 2222,
                    'username' => \is_string($username) && $username !== '' ? $username : 'integration',
                    'auth' => 'password',
                    'password' => \is_string($password) ? $password : 'integration-password',
                    'timeout' => 10,
                ],
            ],
        ]);
    }

    private function skipUnlessIntegrationEnabled(): void
    {
        if (\getenv('RUN_INTEGRATION_TESTS') === '1') {
            return;
        }

        $this->markTestSkipped('Set RUN_INTEGRATION_TESTS=1 to run Docker-backed SSH integration tests.');
    }
}
