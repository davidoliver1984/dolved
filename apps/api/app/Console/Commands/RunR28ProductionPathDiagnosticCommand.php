<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\RunR28ProductionPathDiagnostic;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class RunR28ProductionPathDiagnosticCommand extends Command
{
    protected $signature = 'evaluation:r28:production-path
        {--input= : Absolute question-only execution-input JSON path}
        {--output= : New immutable observation JSON path}
        {--workspace= : S03 workspace public ID}
        {--user= : Authorised S03 user ID}';

    protected $description = 'Execute the bounded R28 diagnostic through production conversation services';

    public function handle(RunR28ProductionPathDiagnostic $run): int
    {
        try {
            $inputPath = $this->absoluteFile((string) $this->option('input'));
            $outputPath = (string) $this->option('output');
            if ($outputPath === '' || file_exists($outputPath)) {
                throw new RuntimeException('The R28 output path must be new and explicit.');
            }
            $input = json_decode((string) file_get_contents($inputPath), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                throw new RuntimeException('The R28 execution input must be a JSON object.');
            }
            $user = User::query()->whereKey((int) $this->option('user'))->sole();
            $result = $run->handle($user, (string) $this->option('workspace'), $input);
            $parent = dirname($outputPath);
            if (! is_dir($parent) || ! is_writable($parent)) {
                throw new RuntimeException('The R28 output directory must already exist and be writable.');
            }
            $temporary = $outputPath.'.partial';
            if (file_exists($temporary)) {
                throw new RuntimeException('The R28 temporary output path already exists.');
            }
            file_put_contents($temporary, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
            if (! rename($temporary, $outputPath)) {
                throw new RuntimeException('The R28 observation could not be finalised atomically.');
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Captured 12 production-path observations at '.$outputPath.'.');

        return self::SUCCESS;
    }

    private function absoluteFile(string $path): string
    {
        if ($path === '' || ! str_starts_with($path, '/') || ! is_file($path)) {
            throw new RuntimeException('The R28 input must be an existing absolute file path.');
        }

        return $path;
    }
}
