<?php

declare(strict_types=1);

namespace OCA\Comicpreviews\Preview;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IImage;
use OCP\Image;
use OCP\Preview\IProviderV2;
use ZipArchive;
use Throwable;

class CbzPreviewProvider implements IProviderV2 {
    private const MAX_ARCHIVE_SIZE = 1073741824;
    private const MAX_IMAGE_SIZE = 52428800;
    private const MAX_LISTING_SIZE = 10485760;

    public function getMimeType(): string {
        return '/^application\/(?:comicbook\+zip|vnd\.comicbook\+zip|zip|comicbook\+rar)$/';
    }

    public function isAvailable(FileInfo $file): bool {
        $extension = strtolower(
            pathinfo($file->getName(), PATHINFO_EXTENSION)
        );

        





        if ($extension === 'cbz') {
            return class_exists(ZipArchive::class);
        }

        if ($extension === 'cbr') {
            return function_exists('proc_open')
                && is_executable('/usr/bin/unrar');
        }

        return false;
    }

    public function getThumbnail(
        File $file,
        int $maxX,
        int $maxY
    ): ?IImage {
        

        if ($file->getSize() > self::MAX_ARCHIVE_SIZE) {
            
            return null;
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'nc_comic_');

        if ($archivePath === false) {
            
            return null;
        }

        $imagePath = null;
        $zip = null;

        try {
            $source = $file->fopen('r');
            $destination = fopen($archivePath, 'wb');

            if ($source === false || $destination === false) {
                if (is_resource($source)) {
                    fclose($source);
                }
                if (is_resource($destination)) {
                    fclose($destination);
                }

                
                return null;
            }

            try {
                $copied = stream_copy_to_stream(
                    $source,
                    $destination,
                    self::MAX_ARCHIVE_SIZE + 1
                );
            } finally {
                fclose($source);
                fclose($destination);
            }

            if ($copied === false
                || $copied > self::MAX_ARCHIVE_SIZE) {
                
                return null;
            }

            $extension = strtolower(
                pathinfo($file->getName(), PATHINFO_EXTENSION)
            );

            if ($extension === 'cbz') {
                $zip = new ZipArchive();

                if ($zip->open($archivePath) !== true) {
                    
                    return null;
                }

                $entries = [];

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    $stat = $zip->statIndex($i);

                    if ($name === false || $stat === false
                        || str_ends_with($name, '/')
                        || $stat['size'] < 12
                        || $stat['size'] > self::MAX_IMAGE_SIZE) {
                        continue;
                    }

                    if (preg_match(
                        '/\.(jpe?g|png|gif|webp|bmp)$/i',
                        $name
                    )) {
                        $entries[] = $name;
                    }
                }

                natcasesort($entries);
                

                foreach ($entries as $entry) {
                    $stream = $zip->getStream($entry);

                    if ($stream === false) {
                        continue;
                    }

                    $imagePath = tempnam(
                        sys_get_temp_dir(),
                        'nc_comic_img_'
                    );

                    if ($imagePath === false) {
                        fclose($stream);
                        return null;
                    }

                    $out = fopen($imagePath, 'wb');

                    if ($out === false) {
                        fclose($stream);
                        return null;
                    }

                    try {
                        $written = stream_copy_to_stream(
                            $stream,
                            $out,
                            self::MAX_IMAGE_SIZE + 1
                        );
                    } finally {
                        fclose($stream);
                        fclose($out);
                    }

                    if ($written === false
                        || $written < 12
                        || $written > self::MAX_IMAGE_SIZE) {
                        unlink($imagePath);
                        $imagePath = null;
                        continue;
                    }

                    $image = new Image();

                    if ($image->loadFromFile($imagePath) !== false
                        && $image->valid()) {
                        $image->scaleDownToFit(
                            max(1, $maxX),
                            max(1, $maxY)
                        );

                        
                        return $image;
                    }

                    
                    unlink($imagePath);
                    $imagePath = null;
                }
            } elseif ($extension === 'cbr') {
                $listing = $this->listRarEntries($archivePath);

                if ($listing === null
                    || strlen($listing) > self::MAX_LISTING_SIZE) {
                    
                    return null;
                }

                $entries = [];

                foreach (preg_split('/\r\n|\r|\n/', $listing) as $name) {
                    if ($name === ''
                        || str_ends_with($name, '/')
                        || !preg_match(
                            '/\.(jpe?g|png|gif|webp|bmp)$/i',
                            $name
                        )) {
                        continue;
                    }

                    $entries[] = $name;
                }

                natcasesort($entries);
                

                foreach ($entries as $entry) {
                    $imagePath = tempnam(
                        sys_get_temp_dir(),
                        'nc_comic_img_'
                    );

                    if ($imagePath === false) {
                        return null;
                    }

                    $out = fopen($imagePath, 'wb');

                    if ($out === false) {
                        unlink($imagePath);
                        $imagePath = null;
                        return null;
                    }

                    $process = proc_open(
                        [
                            '/usr/bin/unrar',
                            'p',
                            '-inul',
                            '-p-',
                            $archivePath,
                            $entry,
                        ],
                        [
                            0 => ['file', '/dev/null', 'r'],
                            1 => ['pipe', 'w'],
                            2 => ['file', '/dev/null', 'w'],
                        ],
                        $pipes
                    );

                    if (!is_resource($process)) {
                        fclose($out);
                        unlink($imagePath);
                        $imagePath = null;
                        return null;
                    }

                    try {
                        $written = stream_copy_to_stream(
                            $pipes[1],
                            $out,
                            self::MAX_IMAGE_SIZE + 1
                        );
                    } finally {
                        fclose($pipes[1]);
                        fclose($out);
                    }

                    $exitCode = proc_close($process);

                    if ($written === false
                        || $written < 12
                        || $written > self::MAX_IMAGE_SIZE
                        || $exitCode !== 0) {
                        




                        unlink($imagePath);
                        $imagePath = null;
                        continue;
                    }

                    $image = new Image();

                    if ($image->loadFromFile($imagePath) !== false
                        && $image->valid()) {
                        $image->scaleDownToFit(
                            max(1, $maxX),
                            max(1, $maxY)
                        );

                        
                        return $image;
                    }

                    
                    unlink($imagePath);
                    $imagePath = null;
                }
            }

            
            return null;
        } catch (Throwable $e) {
            


            return null;
        } finally {
            if ($zip instanceof ZipArchive) {
                $zip->close();
            }

            if ($imagePath !== null && is_file($imagePath)) {
                unlink($imagePath);
            }

            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    private function listRarEntries(string $archivePath): ?string {
        $process = proc_open(
            [
                '/usr/bin/unrar',
                'lb',
                '-p-',
                $archivePath,
            ],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            
            return null;
        }

        $listing = stream_get_contents(
            $pipes[1],
            self::MAX_LISTING_SIZE + 1
        );

        fclose($pipes[1]);

        if ($listing === false
            || strlen($listing) > self::MAX_LISTING_SIZE) {
            proc_terminate($process);
            proc_close($process);
            return null;
        }

        $exitCode = proc_close($process);

        return $exitCode === 0 ? $listing : null;
    }

}