<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'smashbook:backup';

    protected $description = 'Dump database MySQL ke storage/app/private/backups dan rotasi 30 hari';

    public function handle(): int
    {
        $db = config('database.connections.mysql');
        $binary = $db['dump_binary'] ?: 'mysqldump';

        $args = [
            $binary,
            '--host='.$db['host'],
            '--port='.$db['port'],
            '--user='.$db['username'],
            '--single-transaction',
            '--skip-lock-tables',
            '--routines',
            '--no-tablespaces',
            $db['database'],
        ];

        $process = new Process($args, base_path(), [
            'MYSQL_PWD' => $db['password'] ?? '',
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Backup gagal: '.trim($process->getErrorOutput() ?: $process->getOutput()));

            return self::FAILURE;
        }

        $filename = 'backups/backup-'.now()->format('Ymd-His').'.sql.gz';
        Storage::disk('local')->put($filename, gzencode($process->getOutput(), 9));

        $cutoff = now()->subDays(30)->getTimestamp();
        foreach (Storage::disk('local')->files('backups') as $file) {
            if (Storage::disk('local')->lastModified($file) < $cutoff) {
                Storage::disk('local')->delete($file);
            }
        }

        $this->info('Backup dibuat: '.Storage::disk('local')->path($filename));

        return self::SUCCESS;
    }
}
