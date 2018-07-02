<?php

namespace Sandstorm\NeosH5P\Controller\Backend;

use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosH5P\Domain\Model\Content;
use Sandstorm\NeosH5P\Domain\Repository\ContentRepository;
use Sandstorm\NeosH5P\Domain\Service\ContentCreationService;
use Sandstorm\NeosH5P\Domain\Service\H5PIntegrationService;

class ContentController extends AbstractModuleController
{
    /**
     * @Flow\Inject
     * @var H5PIntegrationService
     */
    protected $h5pIntegrationService;

    /**
     * @Flow\Inject
     * @var ContentCreationService
     */
    protected $contentCreationService;

    /**
     * @Flow\Inject
     * @var ContentRepository
     */
    protected $contentRepository;

    public function indexAction()
    {
        $contents = $this->contentRepository->findAll();
        $this->view->assign('contents', $contents);
    }

    public function newAction()
    {
        $coreSettings = $this->h5pIntegrationService->getCoreSettings();
        $coreSettings['editor'] = $this->h5pIntegrationService->getEditorSettings();

        $this->view->assign('settings', json_encode($coreSettings));
        $this->view->assign('scripts', $coreSettings['core']['scripts']);
        $this->view->assign('styles', $coreSettings['core']['styles']);
    }

    /**
     * @param string $title
     * @param string $action
     * @param string $library
     * @param string $parameters
     * @throws StopActionException
     * @return bool
     */
    public function createAction(string $action, string $title, string $library, string $parameters)
    {
        // We only handle $action == 'create' so far
        if ($action === 'upload') {
            // TODO
        }

        $content = $this->contentCreationService->handleContentCreation($title, $library, $parameters);
        if ($content === null) {
            return false;
        }
        // TODO flashmessage
        $this->redirect('display', null, null, ['content' => $content]);
    }

    /**
     * @param Content $content
     */
    public function displayAction(Content $content)
    {
        $this->view->assign('content', $content);
    }
}
