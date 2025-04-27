<?php

namespace TailPress\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Helper\ProgressBar;
use ZipArchive;

class Release extends Command
{
    public Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('release')
            ->setDescription('Build the WordPress theme for production and create a zip archive')
            ->addArgument('destination', InputArgument::REQUIRED, 'Directory to save the zip file (e.g., /path/to/output)')
            ->addArgument('filename', InputArgument::REQUIRED, 'Name of the zip file (e.g., your-theme.zip)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = getcwd();
        $destination = rtrim($input->getArgument('destination'), DIRECTORY_SEPARATOR);
        $filename = $input->getArgument('filename');
        if (!str_ends_with($filename, '.zip')) {
            $filename .= '.zip';
        }

        if (!$this->filesystem->exists($destination)) {
            $this->filesystem->mkdir($destination);
        }

        $zipPath = $destination . DIRECTORY_SEPARATOR . $filename;
        $tempDir = $destination . DIRECTORY_SEPARATOR . uniqid('tailpress_build_');
        $themeDir = $tempDir . DIRECTORY_SEPARATOR . basename($projectDir);

        $io->title('[TailPress] WordPress Theme Build');

        $steps = [
            '🏗️ Copying theme files' => fn() => $this->filesystem->mirror($projectDir, $themeDir),
            '🏗️ Installing Composer dependencies' => function() use ($projectDir, $themeDir) {
                chdir($themeDir);
                exec('composer install --no-dev --optimize-autoloader --quiet 2>&1', $output, $status);
                chdir($projectDir);
                if ($status !== 0) throw new \Exception('Composer install failed: ' . implode("\n", $output));
            },
            '🏗️ Installing npm dependencies' => function() use ($projectDir, $themeDir) {
                chdir($themeDir);
                exec('npm ci 2>&1', $output, $status);
                chdir($projectDir);
                if ($status !== 0) throw new \Exception('npm ci failed: ' . implode("\n", $output));
            },
            '🏗️ Compiling assets' => function() use ($projectDir, $themeDir) {
                chdir($themeDir);
                exec('npm run build 2>&1', $output, $status);
                chdir($projectDir);
                if ($status !== 0) throw new \Exception('npm build failed: ' . implode("\n", $output));
            },
            '🏗️ Removing unneeded files' => fn() => $this->removeUnneededFiles($themeDir, $io),
            '🏗️ Creating zip file' => fn() => $this->createZip($themeDir, $zipPath, $io),
        ];

        foreach ($steps as $message => $task) {
            $progress = new ProgressBar($output);
            $progress->setFormat("%message% %bar%");
            $progress->setBarCharacter('█');
            $progress->setEmptyBarCharacter(' ');
            $progress->setProgressCharacter('>');
            $progress->setBarWidth(20);
            $progress->setMessage($message);
            $progress->start(100);

            try {
                for ($i = 0; $i < 100; $i += 20) {
                    $progress->advance(20);
                    usleep(100000); // Simulate work; adjust as needed
                }
                $task();
                $progress->setMessage($message . ' ✅');
                $progress->finish();
                $output->writeln('');
            } catch (\Exception $e) {
                $progress->setMessage($message . ' ❌');
                $progress->finish();
                $output->writeln('');
                $this->filesystem->remove($tempDir);
                $io->error($e->getMessage());
                return Command::FAILURE;
            }
        }

        $this->filesystem->remove($tempDir);
        $io->info("Zip file created at $zipPath");
        $io->success('🎉 Theme build completed!');
        return Command::SUCCESS;
    }

    private function removeUnneededFiles(string $dir, SymfonyStyle $io): void
    {
        $excludePatterns = [];
        $includePatterns = [];
        $distIgnoreFile = $dir . DIRECTORY_SEPARATOR . '.distignore';

        if ($this->filesystem->exists($distIgnoreFile)) {
            $patterns = array_filter(
                array_map('trim', file($distIgnoreFile)),
                fn($line) => $line && !str_starts_with($line, '#')
            );
            foreach ($patterns as $pattern) {
                if (str_starts_with($pattern, '!')) {
                    $includePatterns[] = substr($pattern, 1);
                } else {
                    $excludePatterns[] = $pattern;
                }
            }
        }

        $this->removeExcludedFiles($dir, $excludePatterns, $includePatterns, $io);
    }

    private function createZip(string $themeDir, string $zipPath, SymfonyStyle $io): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Failed to create zip archive');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themeDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($themeDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $realPath = realpath($item->getPathname());
                $zip->addFile($realPath ?: $item->getPathname(), $relativePath);
            }
        }

        $zip->close();
    }

    private function removeExcludedFiles(string $dir, array $excludePatterns, array $includePatterns, SymfonyStyle $io): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $item->getPathname());
            if ($this->isExcluded($relativePath, $excludePatterns) && !$this->isIncluded($relativePath, $includePatterns)) {
                try {
                    $this->filesystem->remove($item->getPathname());
                } catch (\Exception $e) {
                    $io->warning("Failed to remove: $relativePath ({$e->getMessage()})");
                }
            }
        }
    }

    private function isExcluded(string $path, array $patterns): bool
    {
        $path = ltrim($path, DIRECTORY_SEPARATOR);

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern, '/');
            if (str_ends_with($pattern, '/*')) {
                $dir = substr($pattern, 0, -2);
                if ($path === $dir || str_starts_with($path, "$dir/")) {
                    return true;
                }
            } elseif (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function isIncluded(string $path, array $patterns): bool
    {
        $path = ltrim($path, DIRECTORY_SEPARATOR);

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern, '/');
            if (str_ends_with($pattern, '/*')) {
                $dir = substr($pattern, 0, -2);
                if ($path === $dir || str_starts_with($path, "$dir/")) {
                    return true;
                }
            } elseif (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
