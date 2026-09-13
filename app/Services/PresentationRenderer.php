<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use ZipArchive;

class PresentationRenderer
{
    public function validatePptx(string $path): int
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->invalid('Plik nie jest prawidłową prezentacją PPTX.');
        }
        try {
            if ($zip->numFiles > 3000 || $zip->locateName('ppt/presentation.xml') === false || $zip->locateName('[Content_Types].xml') === false) {
                $this->invalid('Nieprawidłowa struktura prezentacji PPTX.');
            }
            $total = 0;
            $slides = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                $total += $stat['size'];
                if ($total > 100 * 1024 * 1024 || $stat['size'] > 20 * 1024 * 1024 || str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/')) {
                    $this->invalid('Prezentacja jest zbyt duża po rozpakowaniu lub zawiera nieprawidłowe ścieżki.');
                }
                if (preg_match('~(^|/)(embeddings|activeX)/|vbaProject|\.bin$|^ppt/media/.*\.(svg|wmf|emf|mp4|avi|mov|mp3|wav)$~i', $name)) {
                    $this->invalid('Prezentacja zawiera osadzone obiekty, makra lub multimedia. Zapisz wersję PPTX ze statycznymi obrazami PNG/JPG.');
                }
                if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                    $xml = $zip->getFromIndex($i);
                    if ($xml === false || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
                        $this->invalid('Prezentacja zawiera niedozwolone deklaracje XML.');
                    }
                    $dom = new DOMDocument;
                    if (! @$dom->loadXML($xml, LIBXML_NONET)) {
                        $this->invalid('Nie można odczytać struktury prezentacji.');
                    }
                    if (str_ends_with($name, '.rels')) {
                        foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
                            $target = rawurldecode($relationship->getAttribute('Target'));
                            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0 || preg_match('~^[a-z][a-z0-9+.-]*:|^[/\\\\]~i', $target)) {
                                $this->invalid('Prezentacja zawiera odnośniki zewnętrzne. Usuń linki i osadź obrazy bezpośrednio w pliku przed wgraniem.');
                            }
                            $parts = $name === '_rels/.rels' ? [] : explode('/', dirname(dirname($name)));
                            if (str_contains($target, '\\') || str_contains($target, "\0")) {
                                $this->invalid('Prezentacja zawiera nieprawidłowy odnośnik.');
                            }
                            foreach (explode('/', explode('#', $target)[0]) as $part) {
                                if ($part === '..') {
                                    if ($parts === []) {
                                        $this->invalid('Odnośnik wychodzi poza plik prezentacji.');
                                    }
                                    array_pop($parts);
                                } elseif ($part !== '' && $part !== '.') {
                                    $parts[] = $part;
                                }
                            }
                        }
                    }
                }
                $slides += (int) preg_match('~^ppt/slides/slide\d+\.xml$~', $name);
            }
            if ($slides < 1 || $slides > config('presentations.max_slides')) {
                $this->invalid('Prezentacja musi zawierać od 1 do '.config('presentations.max_slides').' slajdów.');
            }

            return $slides;
        } finally {
            $zip->close();
        }
    }

    /** @return list<string> JPEG bytes in slide order. */
    public function render(string $path): array
    {
        $expected = $this->validatePptx($path);
        if (! is_executable(config('presentations.office_binary')) || ! is_executable(config('presentations.raster_binary'))) {
            $this->invalid('Konwerter prezentacji nie jest dostępny na serwerze. Administrator hostingu musi włączyć LibreOffice Impress i Poppler.');
        }
        $root = storage_path('app/private/presentation-conversion');
        $work = $root.'/'.Str::uuid();
        File::ensureDirectoryExists($work, 0700);
        try {
            File::copy($path, $work.'/slides.pptx');
            File::ensureDirectoryExists($work.'/profile/user', 0700);
            File::put($work.'/profile/user/registrymodifications.xcu', '<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry"><item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop></item></oor:items>');
            $profileUri = 'file:///'.ltrim(str_replace('\\', '/', $work.'/profile'), '/');
            $office = new Process([config('presentations.office_binary'), '-env:UserInstallation='.$profileUri, '--headless', '--nologo', '--nodefault', '--norestore', '--convert-to', 'pdf:impress_pdf_Export', '--outdir', $work, $work.'/slides.pptx'], $work, ['SAL_USE_VCLPLUGIN' => 'svp'], null, 45);
            $office->mustRun();
            if (! is_file($work.'/slides.pdf')) {
                $this->invalid('Nie udało się przekonwertować prezentacji. Sprawdź, czy plik nie jest chroniony hasłem.');
            }
            $raster = new Process([config('presentations.raster_binary'), '-jpeg', '-jpegopt', 'quality=88', '-scale-to', '1600', '-f', '1', '-l', (string) ($expected + 1), $work.'/slides.pdf', $work.'/slide'], $work, null, null, 45);
            $raster->mustRun();
            $files = glob($work.'/slide-*.jpg') ?: [];
            natsort($files);
            if (count($files) !== $expected) {
                $this->invalid('Nie udało się przygotować wszystkich slajdów. Usuń ukryte slajdy lub zapisz prezentację ponownie.');
            }
            $slides = [];
            $total = 0;
            foreach ($files as $file) {
                $bytes = File::get($file);
                $total += strlen($bytes);
                if ($total > 40 * 1024 * 1024 || ! @getimagesizefromstring($bytes)) {
                    $this->invalid('Podgląd prezentacji przekracza dopuszczalny rozmiar.');
                }
                $slides[] = $bytes;
            }

            return $slides;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            $this->invalid('Nie udało się przygotować podglądu w wymaganym czasie. Spróbuj mniejszego pliku PPTX.');
        } finally {
            // Only the generated UUID directory is ever removed, never an uploaded/user-selected path.
            File::deleteDirectory($work);
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['presentation_file' => $message]);
    }
}
