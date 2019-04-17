<?php

namespace Sandstorm\NeosH5P\Controller\Backend;

use Neos\Error\Messages\Message;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Flow\Annotations as Flow;
use Sandstorm\NeosH5P\Domain\Model\Content;
use Sandstorm\NeosH5P\Domain\Model\ContentResult;
use Sandstorm\NeosH5P\Domain\Repository\ContentRepository;
use Sandstorm\NeosH5P\Domain\Repository\ContentResultRepository;
use Sandstorm\NeosH5P\Domain\Service\CRUD\ContentCRUDService;
use Sandstorm\NeosH5P\Domain\Service\H5PIntegrationService;
use Sandstorm\NeosH5P\Domain\Service\UriGenerationService;

class ContentController extends AbstractModuleController
{
    /**
     * @Flow\Inject
     * @var H5PIntegrationService
     */
    protected $h5pIntegrationService;

    /**
     * @Flow\Inject(lazy=false)
     * @var \H5PCore
     */
    protected $h5pCore;

    /**
     * @Flow\Inject
     * @var ContentCRUDService
     */
    protected $contentCRUDService;

    /**
     * @Flow\Inject
     * @var ContentRepository
     */
    protected $contentRepository;

    /**
     * @Flow\Inject
     * @var ContentResultRepository
     */
    protected $contentResultRepository;

    /**
     * @Flow\Inject
     * @var UriGenerationService
     */
    protected $uriGenerationService;

    /**
     * @param string $orderBy
     * @param string $orderDirection
     * @param string $search
     */
    public function indexAction($orderBy = 'contentId', $orderDirection = QueryInterface::ORDER_DESCENDING, $search = '')
    {
        $contents = $this->contentRepository->findByContainsTitle($search, $orderBy, $orderDirection);

        $this->view->assign('contents', $contents);
        $this->view->assign('isRenderedInFullscreenEditor', $this->isRenderedInFullscreenEditor());
        $this->view->assign('orderBy', $orderBy);
        $this->view->assign('possibleOrderBy', [
            'title' => 'Title',
            'library' => 'Library',
            'contentId' => 'Content ID',
            'createdAt' => 'Created',
            'updatedAt' => 'Updated',
            'account' => 'Author'
        ]);
        $this->view->assign('orderDirection', $orderDirection);
        $this->view->assign('possibleOrderDirection', [
            QueryInterface::ORDER_DESCENDING => 'Descending',
            QueryInterface::ORDER_ASCENDING => 'Ascending'
        ]);
        $this->view->assign('search', $search);
    }

    /**
     * @param Content $content
     */
    public function displayAction(Content $content)
    {
        $h5pIntegrationSettings = $this->h5pIntegrationService->getSettings($this->controllerContext, [$content->getContentId()]);
        $this->view->assign('content', $content);
        $this->view->assign('settings', json_encode($h5pIntegrationSettings));
        $this->view->assign('scripts', $this->h5pIntegrationService->getMergedScripts($h5pIntegrationSettings));
        $this->view->assign('styles', $this->h5pIntegrationService->getMergedStyles($h5pIntegrationSettings));
    }

    /**
     * @param Content $content
     */
    public function resultsAction(Content $content)
    {
        $this->view->assign('content', $content);
        $this->view->assign('contentResults', $this->contentResultRepository->findByContent($content));
        $this->view->assign('perUser', true);
    }

    /**
     * @param ContentResult $contentResult
     */
    public function deleteSingleResultAction(ContentResult $contentResult)
    {
        $this->contentResultRepository->remove($contentResult);
        $this->addFlashMessage('The content result has been deleted.', 'Result deleted', Message::SEVERITY_OK);
        $this->redirect('display', null, null, ['content' => $contentResult->getContent()]);
    }

    /**
     * @param Content $content
     */
    public function deleteResultsAction(Content $content)
    {
        foreach ($content->getContentResults() as $contentResult) {
            $this->contentResultRepository->remove($contentResult);
        }
        $this->addFlashMessage('All results for content "%s" have been deleted.', 'Results deleted', Message::SEVERITY_OK, [$content->getTitle()]);
        $this->redirect('display', null, null, ['content' => $content]);
    }

    public function newAction()
    {
        $h5pIntegrationSettings = $this->h5pIntegrationService->getSettingsWithEditor($this->controllerContext);

        $this->view->assign('settings', json_encode($h5pIntegrationSettings));
        $this->view->assign('scripts', $h5pIntegrationSettings['core']['scripts']);
        $this->view->assign('styles', $h5pIntegrationSettings['core']['styles']);
        $this->view->assign('isRenderedInFullscreenEditor', $this->isRenderedInFullscreenEditor());
    }

    /**
     * @param string $action
     * @param string $library
     * @param string $parameters
     * @throws StopActionException
     */
    public function createAction(string $action, string $library, string $parameters)
    {
        // We only handle $action == 'create' so far
        if ($action === 'upload') {
            // TODO: not implemented yet
        }

        $content = $this->contentCRUDService->handleCreateOrUpdate($library, $parameters);
        if ($content === null) {
            $this->showH5pErrorMessages();
            $this->redirect('index');
        } else {
            $this->addFlashMessage('The content "%s" has been created.', 'Content created', Message::SEVERITY_OK, [$content->getTitle()]);
            $this->redirect('display', null, null, ['content' => $content]);
        }
    }

    /**
     * @param Content $content
     */
    public function editAction(Content $content)
    {
        $h5pIntegrationSettings = $this->h5pIntegrationService->getSettingsWithEditor($this->controllerContext, $content->getContentId());

        $this->view->assign('settings', json_encode($h5pIntegrationSettings));
        $this->view->assign('scripts', $h5pIntegrationSettings['core']['scripts']);
        $this->view->assign('styles', $h5pIntegrationSettings['core']['styles']);
        $this->view->assign('content', $content);
    }

    /**
     * @param int $contentId
     * @param string $library
     * @param string $parameters
     * @throws StopActionException
     * @return bool
     */
    public function updateAction(int $contentId, string $library, string $parameters)
    {
        $content = $this->contentCRUDService->handleCreateOrUpdate($library, $parameters, $contentId);
        if ($content === null) {
            $this->showH5pErrorMessages();
            $this->redirect('index');
        } else {
            $this->addFlashMessage('The content "%s" has been updated.', 'Content updated', Message::SEVERITY_OK, [$content->getTitle()]);
            $this->redirect('display', null, null, ['content' => $content]);
        }

        return false;
    }

    /**
     * @param Content $content
     * @throws StopActionException
     * @return bool
     */
    public function deleteAction(Content $content)
    {
        $this->contentCRUDService->handleDelete($content);

        $this->addFlashMessage('The content "%s" has been deleted.', 'Content deleted', Message::SEVERITY_OK, [$content->getTitle()]);
        $this->redirect('index', null, null);
        return false;
    }

    private function showH5pErrorMessages()
    {
        foreach ($this->h5pCore->h5pF->getMessages('error') as $errorMessage) {
            $this->addFlashMessage($errorMessage->message, $errorMessage->code ?: 'H5P error', Message::SEVERITY_ERROR);
        }
    }

    private function isRenderedInFullscreenEditor()
    {
        return $this->request->isMainRequest();
    }
}
