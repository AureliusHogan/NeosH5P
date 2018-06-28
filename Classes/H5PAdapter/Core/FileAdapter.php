<?php

namespace Sandstorm\NeosH5P\H5PAdapter\Core;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Utility\Exception\FilesException;
use Neos\Utility\Files;
use Sandstorm\NeosH5P\Domain\Model\Library;
use Sandstorm\NeosH5P\Domain\Repository\LibraryRepository;

/**
 * @Flow\Scope("singleton")
 */
class FileAdapter implements \H5PFileStorage
{
    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var LibraryRepository
     */
    protected $libraryRepository;

    /**
     * Store the library folder.
     *
     * @param array $libraryData
     *  Library properties
     * @throws Exception
     */
    public function saveLibrary($libraryData)
    {
        $zipFileName = $this->zipDirectory($libraryData['uploadDirectory']);
        $resource = $this->resourceManager->importResource($zipFileName);

        /** @var Library $library */
        $library = $this->libraryRepository->findOneByLibraryId($libraryData['libraryId']);
        $library->setZippedLibraryFile($resource);
        $this->libraryRepository->update($library);
    }

    /**
     * @param $directoryPath string directory to zip.
     * @return string name of zipped file
     */
    private function zipDirectory($directoryPath): string
    {
        // ZIP everything in the library's upload directory and save it as a resource
        $zipFileName = $directoryPath . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipFileName, \ZipArchive::CREATE);

        // Create recursive directory iterator
        /** @var \SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directoryPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if ($file->isDir()) {
                continue;
            }

            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($directoryPath) + 1);

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }

        // Zip archive will be created only after closing object
        $zip->close();
        return $zipFileName;
    }

    /**
     * Store the content folder.
     *
     * @param string $source
     *  Path on file system to content directory.
     * @param array $content
     *  Content properties
     */
    public function saveContent($source, $content)
    {
        // TODO: Implement saveContent() method.
    }

    /**
     * Remove content folder.
     *
     * @param array $content
     *  Content properties
     */
    public function deleteContent($content)
    {
        // TODO: Implement deleteContent() method.
    }

    /**
     * Creates a stored copy of the content folder.
     *
     * @param string $id
     *  Identifier of content to clone.
     * @param int $newId
     *  The cloned content's identifier
     */
    public function cloneContent($id, $newId)
    {
        // TODO: Implement cloneContent() method.
    }

    /**
     * Get path to a new unique tmp folder.
     *
     * @return string Path
     * @throws FilesException
     */
    public function getTmpPath()
    {
        $tmpDir = FLOW_PATH_DATA . 'Temporary/H5P';
        Files::createDirectoryRecursively($tmpDir);
        return $tmpDir . '/' . uniqid('h5p-');
    }

    /**
     * Fetch content folder and save in target directory.
     *
     * @param int $id
     *  Content identifier
     * @param string $target
     *  Where the content folder will be saved
     */
    public function exportContent($id, $target)
    {
        // TODO: Implement exportContent() method.
    }

    /**
     * Fetch library folder and save in target directory.
     *
     * @param array $library
     *  Library properties
     * @param string $target
     *  Where the library folder will be saved
     */
    public function exportLibrary($library, $target)
    {
        // TODO: Implement exportLibrary() method.
    }

    /**
     * Save export in file system
     *
     * @param string $source
     *  Path on file system to temporary export file.
     * @param string $filename
     *  Name of export file.
     */
    public function saveExport($source, $filename)
    {
        // TODO: Implement saveExport() method.
    }

    /**
     * Removes given export file
     *
     * @param string $filename
     */
    public function deleteExport($filename)
    {
        // TODO: Implement deleteExport() method.
    }

    /**
     * Check if the given export file exists
     *
     * @param string $filename
     * @return boolean
     */
    public function hasExport($filename)
    {
        // TODO: Implement hasExport() method.
    }

    /**
     * Will concatenate all JavaScrips and Stylesheets into two files in order
     * to improve page performance.
     *
     * @param array $files
     *  A set of all the assets required for content to display
     * @param string $key
     *  Hashed key for cached asset
     */
    public function cacheAssets(&$files, $key)
    {
        // TODO: Implement cacheAssets() method.
    }

    /**
     * Will check if there are cache assets available for content.
     *
     * @param string $key
     *  Hashed key for cached asset
     * @return array
     */
    public function getCachedAssets($key)
    {
        // TODO: Implement getCachedAssets() method.
    }

    /**
     * Remove the aggregated cache files.
     *
     * @param array $keys
     *   The hash keys of removed files
     */
    public function deleteCachedAssets($keys)
    {
        /**
         * This is called right after H5PFramework->deleteCachedAssets and is supposed to remove the actual asset files.
         * Since we have cascade="remove" set on the relation CachedAsset->PersistentResource, the Resource should be
         * removed automatically by Doctrine. This means we have to do nothing here.
         */
    }

    /**
     * Read file content of given file and then return it.
     *
     * @param string $file_path
     * @return string contents
     */
    public function getContent($file_path)
    {
        // TODO: Implement getContent() method.
    }

    /**
     * Save files uploaded through the editor.
     * The files must be marked as temporary until the content form is saved.
     *
     * @param \H5peditorFile $file
     * @param int $contentId
     */
    public function saveFile($file, $contentId)
    {
        // TODO: Implement saveFile() method.
    }

    /**
     * Copy a file from another content or editor tmp dir.
     * Used when copy pasting content in H5P.
     *
     * @param string $file path + name
     * @param string|int $fromId Content ID or 'editor' string
     * @param int $toId Target Content ID
     */
    public function cloneContentFile($file, $fromId, $toId)
    {
        // TODO: Implement cloneContentFile() method.
    }

    /**
     * Copy a content from one directory to another. Defaults to cloning
     * content from the current temporary upload folder to the editor path.
     *
     * @param string $source path to source directory
     * @param string $contentId Id of content
     *
     * @return object Object containing h5p json and content json data
     */
    public function moveContentDirectory($source, $contentId = NULL)
    {
        // TODO: Implement moveContentDirectory() method.
    }

    /**
     * Checks to see if content has the given file.
     * Used when saving content.
     *
     * @param string $file path + name
     * @param int $contentId
     * @return string|int File ID or NULL if not found
     */
    public function getContentFile($file, $contentId)
    {
        // TODO: Implement getContentFile() method.
    }

    /**
     * Remove content files that are no longer used.
     * Used when saving content.
     *
     * @param string $file path + name
     * @param int $contentId
     */
    public function removeContentFile($file, $contentId)
    {
        // TODO: Implement removeContentFile() method.
    }

    /**
     * Check if server setup has write permission to
     * the required folders
     *
     * @return bool True if server has the proper write access
     */
    public function hasWriteAccess()
    {
        // TODO: Implement hasWriteAccess() method.
    }

}
