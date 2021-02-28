<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class used for creating ZIP archives.
 *
 * @package   core_files
 * @copyright 2020 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_files\local\archive_writer;

use ZipStream\Option\Archive;
use ZipStream\ZipStream;
use core_files\archive_writer;
use core_files\local\archive_writer\file_writer_interface as file_writer_interface;
use core_files\local\archive_writer\stream_writer_interface as stream_writer_interface;

/**
 * Class used for creating ZIP archives.
 *
 * @package   core_files
 * @copyright 2020 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zip_writer extends archive_writer implements file_writer_interface, stream_writer_interface {

    /**
     * @var resource File resource for the file handle for a file-based zip stream
     */
    private $zipfilehandle = null;

    /**
     * @var String The location of the zip file.
     */
    private $zipfilepath = null;

    /**
     * @var ZipStream The zip stream.
     */
    private $archive;

    /**
     * The zip_writer constructor.
     *
     * @param ZipStream $archive
     */
    protected function __construct(ZipStream $archive) {
        parent::__construct();
        $this->archive = $archive;
    }

    public static function stream_instance(string $filename): stream_writer_interface {
        $options = new Archive();
        $options->setSendHttpHeaders(true);
        $options->setContentDisposition('attachment');
        $options->setContentType('application/x-zip');
        $zipwriter = new ZipStream($filename, $options);

        return new static($zipwriter);
    }

    public static function file_instance(string $filename): file_writer_interface {
        $dir = make_request_directory();
        $filepath = "$dir/$filename";
        $fh = fopen($filepath, 'w');

        $exportoptions = new Archive();
        $exportoptions->setOutputStream($fh);
        $exportoptions->setSendHttpHeaders(false);
        $zipstream = new ZipStream($filename, $exportoptions);

        $zipwriter = new static($zipstream);
        // ZipStream only takes a file handle resource.
        // It does not close this resource itself, and it does not know the location of this resource on disk.
        // Store references to the filehandle, and the location of the filepath in the new class so that the `finish()`
        // function can close the fh, and move the temporary file into place.
        // The filehandle must be closed when finishing the archive. ZipStream does not close it automatically.
        $zipwriter->zipfilehandle = $fh;
        $zipwriter->zipfilepath = $filepath;

        return $zipwriter;
    }

    public function add_file_from_filepath(string $name, string $path): void {
        $this->archive->addFileFromPath($this->sanitise_filepath($name), $path);
    }

    public function add_file_from_string(string $name, string $data): void {
        $this->archive->addFile($this->sanitise_filepath($name), $data);
    }

    public function add_file_from_stream(string $name, $stream): void {
        $this->archive->addFileFromStream($this->sanitise_filepath($name), $stream);
        fclose($stream);
    }

    public function add_file_from_stored_file(string $name, \stored_file $file): void {
        $filehandle = $file->get_content_file_handle();
        $this->archive->addFileFromStream($this->sanitise_filepath($name), $filehandle);
        fclose($filehandle);
    }

    /**
     * Add an empty directory to zip.
     *
     * @param string $name The path of directory in zip.
     */
    public function add_directory(string $name): void {
        $this->archive->addFile(rtrim($this->sanitise_filepath($name), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '');
    }

    /**
     * Add file to zip.
     *
     * @param string $archivepath Path in zip file.
     * @param ?string|\stored_file $file \stored_file, null, pathname, or array('content_as_string')
     * @return void
     */
    public function add_file(string $archivepath, $file): void {
        $archivepath = trim($archivepath, '/');

        if ($file instanceof \stored_file) {
            $this->add_file_from_stored_file($archivepath, $file);
        } else if (is_null($file)) {
            // Save $file as empty directory.
            $this->add_directory($archivepath);
        } else if (is_string($file)) {
            // Save $file as source filepath.
            $this->add_file_from_filepath($archivepath, $file);
        } else if (is_array($file)) {
            // Save $file as contents.
            $content = reset($file);
            $this->add_file_from_string($archivepath, $content);
        }
    }

    public function finish(): void {
        $this->archive->finish();

        if ($this->zipfilehandle) {
            fclose($this->zipfilehandle);
        }
    }

    public function get_path_to_zip(): string {
        return $this->zipfilepath;
    }

    public function sanitise_filepath(string $filepath): string {
        $filepath = parent::sanitise_filepath($filepath);

        return \ZipStream\File::filterFilename($filepath);
    }
}
