<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use RuntimeException;

final class ConsolePasswordReader
{
    public function read(string $prompt): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->readOnWindows($prompt);
        }

        return $this->readOnUnix($prompt);
    }

    private function readOnWindows(string $prompt): string
    {
        $escapedPrompt = str_replace("'", "''", $prompt);
        $script = sprintf(
            "\$secure = Read-Host '%s' -AsSecureString; "
            . '$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); '
            . 'try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }',
            $escapedPrompt
        );
        $process = proc_open(
            ['powershell.exe', '-NoProfile', '-Command', $script],
            [
                0 => ['file', 'php://stdin', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Secure password prompt could not start.');
        }

        $password = trim((string) stream_get_contents($pipes[1]));
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $error !== '') {
            throw new RuntimeException('Secure password prompt failed.');
        }

        return $password;
    }

    private function readOnUnix(string $prompt): string
    {
        fwrite(STDOUT, $prompt . ': ');
        shell_exec('stty -echo');
        $password = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);

        return $password;
    }
}
