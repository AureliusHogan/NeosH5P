<?php

namespace Sandstorm\NeosH5P\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Security\Account;
use Neos\Utility\Files;
use Sandstorm\NeosH5P\H5PAdapter\Core\FileAdapter;

/**
 * @Flow\Entity
 */
class Content
{
    /**
     * This is the "Content ID" we pass to H5P. H5P expects an int here, but we cannot use this as a technical primary
     * key because doctrine doesnt handle it correctly. So this is a unique key.
     *
     * @var int
     * @ORM\Column(nullable=false, columnDefinition="INT AUTO_INCREMENT UNIQUE")
     */
    protected $contentId;

    /**
     * @var Library
     * @ORM\ManyToOne(inversedBy="contents")
     * @ORM\Column(nullable=false)
     * @ORM\JoinColumn(onDelete="RESTRICT")
     */
    protected $library;

    /**
     * @var Account
     * @ORM\ManyToOne
     * @ORM\Column(nullable=false)
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $account;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetimetz", nullable=false)
     */
    protected $createdAt;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetimetz", nullable=false)
     */
    protected $updatedAt;

    /**
     * @var string
     * @ORM\Column(nullable=false)
     */
    protected $title;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=false)
     */
    protected $parameters;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=false)
     */
    protected $filtered;

    /**
     * @var string
     * @ORM\Column(nullable=false)
     */
    protected $slug;

    /**
     * @var string
     * @ORM\Column(nullable=false)
     */
    protected $embedType;

    /**
     * @var int
     * @ORM\Column(nullable=false)
     */
    protected $disable;

    /**
     * @var string
     * @ORM\Column(nullable=true)
     */
    protected $contentType;

    /**
     * @var string
     * @ORM\Column(nullable=true)
     */
    protected $author;

    /**
     * @var string
     * @ORM\Column(nullable=true)
     */
    protected $license;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    protected $keywords;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;

    /**
     * @var PersistentResource
     * @ORM\OneToOne(cascade={"persist", "remove"})
     * @ORM\Column(nullable=true)
     */
    protected $zippedContentFile;

    /**
     * @var PersistentResource
     * @ORM\OneToOne(cascade={"persist", "remove"})
     * @ORM\Column(nullable=true)
     */
    protected $exportFile;


    // Inversed relations (not in DB)

    /**
     * @var Collection<ContentDependency>
     * @ORM\OneToMany(mappedBy="content", cascade={"persist", "remove"})
     */
    protected $contentDependencies;

    /**
     * @var Collection<ContentUserData>
     * @ORM\OneToMany(mappedBy="content", cascade={"persist", "remove"})
     */
    protected $contentUserDatas;

    /**
     * @var Collection<ContentResult>
     * @ORM\OneToMany(mappedBy="content", cascade={"persist", "remove"})
     */
    protected $contentResults;


    // Transient properties

    /**
     * Whether or not this content has dumped the contents of its zipped content file
     * to disk during the current request.
     *
     * @var boolean
     * @Flow\Transient
     */
    protected $hasDumpedContentFile = false;

    /**
     * @var ResourceManager
     * @Flow\Inject
     */
    protected $resourceManager;

    /**
     * Creates a Content from a metadata array.
     *
     * @param array $contentData
     * @param Library $library
     * @param Account $account
     * @return Content
     */
    public static function createFromMetadata(array $contentData, Library $library, Account $account): Content
    {
        $content = new Content();
        $content->setLibrary($library);
        $content->setAccount($account);
        $content->setCreatedAt(new \DateTime());
        $content->setUpdatedAt(new \DateTime());
        $content->setTitle($contentData['title']);
        $content->setParameters($contentData['params']);
        $content->setFiltered('');
        $content->setDisable($contentData['disable']);
        // Set by h5p later, but must not be null
        $content->setSlug('');
        /**
         * The Wordpress plugin only determines this at render-time, but it always yields the same result unless the
         * library changes. So we should be fine with setting it here and triggering a re-determine if the
         * library is updated.
         * @see Library::updateFromMetadata()
         */
        $content->determineEmbedType();

        return $content;
    }

    /**
     * Returns an associative array containing the content in the form that
     * \H5PCore->filterParameters() expects.
     * @see H5PCore::filterParameters()
     */
    public function toAssocArray(): array
    {
        $contentArray = [
            'id' => $this->getContentId(),
            'title' => $this->getTitle(),
            'library' => $this->getLibrary()->toAssocArray(),
            'slug' => $this->getSlug(),
            'disable' => $this->getDisable(),
            'embedType' => $this->getEmbedType(),
            'params' => $this->getParameters(),
            'filtered' => $this->getFiltered()
        ];

        return $contentArray;
    }

    public function __construct()
    {
        $this->contentDependencies = new ArrayCollection();
        $this->contentUserDatas = new ArrayCollection();
        $this->contentResults = new ArrayCollection();
    }

    /**
     * @param array $contentData
     * @param Library $library
     */
    public function updateFromMetadata(array $contentData, Library $library)
    {
        $this->setUpdatedAt(new \DateTime());
        $this->setTitle($contentData['title']);
        $this->setFiltered("");
        $this->setLibrary($library);

        if (isset($contentData['params'])) {
            $this->setParameters($contentData['params']);
        }
        if (isset($contentData['disable'])) {
            $this->setDisable($contentData['disable']);
        }
    }

    public function determineEmbedType()
    {
        $this->setEmbedType(\H5PCore::determineEmbedType('div', $this->getLibrary()->getEmbedTypes()));
    }

    /**
     * Writes the contents of the zipped content file to disk, for easier
     * file operations during Content creation or update.
     */
    public function dumpContentFileToTemporaryDirectory()
    {
        // Don't dump more than once per request
        if ($this->hasDumpedContentFile()) {
            return;
        }
        $this->hasDumpedContentFile = true;

        $tempPath = $this->buildContentFileTempPath();
        Files::createDirectoryRecursively($tempPath);

        $zippedContentFolderResource = $this->getZippedContentFile();
        if ($zippedContentFolderResource === null) {
            // There is no content file yet, so return
            return;
        }
        $zippedContentFilePathAndFilename = $this->getZippedContentFile()->createTemporaryLocalCopy();
        $zipArchive = new \ZipArchive();
        $zipArchive->open($zippedContentFilePathAndFilename);
        $zipArchive->extractTo($tempPath);
        $zipArchive->close();
    }

    /**
     * Reads all the contents of the dumped content file and stores them as one zip.
     */
    public function createZippedContentFileFromTemporaryDirectory()
    {
        // First, try to remove the folder if it's empty
        Files::removeEmptyDirectoriesOnPath($this->buildContentFileTempPath());

        // If the directory does not exist now, we set the resource to null and return.
        $hasDirectoryWithExportFiles = true;
        if (!is_dir($this->buildContentFileTempPath())) {
            $hasDirectoryWithExportFiles = false;
        }

        // If the directory is empty, we set the resource to null and return.
        if ($hasDirectoryWithExportFiles) {
            $directoryIterator = new \RecursiveDirectoryIterator($this->buildContentFileTempPath(), \FilesystemIterator::SKIP_DOTS);
            if (iterator_count($directoryIterator) === 0) {
                $hasDirectoryWithExportFiles = false;
            }
        }

        if (!$hasDirectoryWithExportFiles) {
            if ($this->getZippedContentFile() !== null) {
                $this->resourceManager->deleteResource($this->getZippedContentFile());
            }
            $this->setZippedContentFile(null);
            return;
        }

        // We have a directory with something in it, so we add that as the new zipped content file.
        $zipfilePath = FileAdapter::H5P_TEMP_DIR . DIRECTORY_SEPARATOR . $this->contentId . '.zip';
        $zipArchive = new \ZipArchive();
        $zipArchive->open(
            $zipfilePath,
            \ZipArchive::CREATE | \ZipArchive::OVERWRITE
        );

        // Create recursive directory iterator
        /** @var \SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            $directoryIterator,
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they will be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($this->buildContentFileTempPath()) + 1);

                // Add current file to archive
                $zipArchive->addFile($filePath, $relativePath);
            }
        }

        // Zip archive will be created only after closing object
        $zipArchive->close();

        // Import the zipfile as a new resource and remove the old one, if it existed
        if ($this->getZippedContentFile() !== null) {
            $this->resourceManager->deleteResource($this->getZippedContentFile());
        }
        $this->setZippedContentFile($this->resourceManager->importResource($zipfilePath));

        // Cleanup the temp dir, deleting the zip file and the folder
        unlink($zipfilePath);
        Files::removeDirectoryRecursively($this->buildContentFileTempPath());
    }

    public function buildContentFileTempPath(): string
    {
        return FileAdapter::H5P_TEMP_DIR . DIRECTORY_SEPARATOR . $this->contentId;
    }

    /**
     * @return bool
     */
    public function hasDumpedContentFile(): bool
    {
        return $this->hasDumpedContentFile;
    }

    /**
     * @return Library
     */
    public function getLibrary(): Library
    {
        return $this->library;
    }

    /**
     * @param Library $library
     */
    public function setLibrary(Library $library): void
    {
        $this->library = $library;
    }

    /**
     * @return Account
     */
    public function getAccount(): Account
    {
        return $this->account;
    }

    /**
     * @param Account $account
     */
    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getParameters(): string
    {
        return $this->parameters;
    }

    /**
     * @param string $parameters
     */
    public function setParameters(string $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return string
     */
    public function getFiltered(): string
    {
        return $this->filtered;
    }

    /**
     * @param string $filtered
     */
    public function setFiltered(string $filtered): void
    {
        $this->filtered = $filtered;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * @return string
     */
    public function getEmbedType(): string
    {
        return $this->embedType;
    }

    /**
     * @param string $embedType
     */
    public function setEmbedType(string $embedType): void
    {
        $this->embedType = $embedType;
    }

    /**
     * @return int
     */
    public function getDisable(): int
    {
        return $this->disable;
    }

    /**
     * @param int $disable
     */
    public function setDisable(int $disable): void
    {
        $this->disable = $disable;
    }

    /**
     * @return string|null
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     */
    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    /**
     * @return string|null
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param string $author
     */
    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    /**
     * @return string|null
     */
    public function getLicense()
    {
        return $this->license;
    }

    /**
     * @param string $license
     */
    public function setLicense(string $license): void
    {
        $this->license = $license;
    }

    /**
     * @return string|null
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * @param string $keywords
     */
    public function setKeywords(string $keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Collection
     */
    public function getContentDependencies(): Collection
    {
        return $this->contentDependencies;
    }

    /**
     * @param Collection $contentDependencies
     */
    public function setContentDependencies(Collection $contentDependencies): void
    {
        $this->contentDependencies = $contentDependencies;
    }

    /**
     * @return Collection
     */
    public function getContentUserDatas(): Collection
    {
        return $this->contentUserDatas;
    }

    /**
     * @param Collection $contentUserDatas
     */
    public function setContentUserDatas(Collection $contentUserDatas): void
    {
        $this->contentUserDatas = $contentUserDatas;
    }

    /**
     * @return int
     */
    public function getContentId(): int
    {
        return $this->contentId;
    }

    /**
     * @param int $contentId
     */
    public function setContentId(int $contentId): void
    {
        $this->contentId = $contentId;
    }

    /**
     * @return Collection
     */
    public function getContentResults(): Collection
    {
        return $this->contentResults;
    }

    /**
     * @param Collection $contentResults
     */
    public function setContentResults(Collection $contentResults): void
    {
        $this->contentResults = $contentResults;
    }

    /**
     * @return PersistentResource|null
     */
    public function getExportFile()
    {
        return $this->exportFile;
    }

    /**
     * @param PersistentResource|null $exportFile
     */
    public function setExportFile($exportFile): void
    {
        $this->exportFile = $exportFile;
    }

    /**
     * @return PersistentResource|null
     */
    public function getZippedContentFile()
    {
        return $this->zippedContentFile;
    }

    /**
     * @param PersistentResource|null $zippedContentFile
     */
    public function setZippedContentFile($zippedContentFile): void
    {
        $this->zippedContentFile = $zippedContentFile;
    }
}
