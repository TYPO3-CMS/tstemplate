<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Tstemplate\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\Icon;

/**
 * This class displays the Info/Modify screen of the Web > Template module
 *
 * @internal This is a specific Backend Controller implementation and is not considered part of the Public TYPO3 API.
 */
class InfoModifyController extends AbstractTemplateModuleController
{
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $pageUid = (int)($queryParams['id'] ?? 0);
        if ($pageUid === 0) {
            // Redirect to template record overview if on page 0.
            return new RedirectResponse($this->uriBuilder->buildUriFromRoute('web_typoscript_recordsoverview'));
        }

        if (($parsedBody['action'] ?? '') === 'createExtensionTemplate') {
            return $this->createExtensionTemplateAction($request, 'web_typoscript_infomodify');
        }
        if (($parsedBody['action'] ?? '') === 'createNewWebsiteTemplate') {
            return $this->createNewWebsiteTemplateAction($request, 'web_typoscript_infomodify');
        }

        $allTemplatesOnPage = $this->getAllTemplateRecordsOnPage($pageUid);
        if (empty($allTemplatesOnPage)) {
            return $this->noTemplateAction($request);
        }

        return $this->mainAction($request, $pageUid, $allTemplatesOnPage);
    }

    private function noTemplateAction(ServerRequestInterface $request): ResponseInterface
    {
        $languageService = $this->getLanguageService();
        $currentModule = $request->getAttribute('module');
        $currentModuleIdentifier = $currentModule->getIdentifier();
        $pageUid = (int)($request->getQueryParams()['id'] ?? 0);
        if ($pageUid === 0) {
            throw new \RuntimeException('No proper page uid given', 1661769346);
        }
        $pageRecord = BackendUtility::readPageAccess($pageUid, '1=1') ?: [];
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($languageService->sL($currentModule->getTitle()), $pageRecord['title']);
        $view->getDocHeaderComponent()->setMetaInformation($pageRecord);
        $this->addPreviewButtonToDocHeader($view, $pageUid, (int)$pageRecord['doktype']);
        $this->addShortcutButtonToDocHeader($view, $currentModuleIdentifier, $pageRecord, $pageUid);
        $view->makeDocHeaderModuleMenu(['id' => $pageUid]);
        $view->assignMultiple([
            'pageUid' => $pageUid,
            'previousPage' => $this->getClosestAncestorPageWithTemplateRecord($pageUid),
        ]);
        return $view->renderResponse('InfoModifyNoTemplate');
    }

    private function mainAction(ServerRequestInterface $request, int $pageUid, array $allTemplatesOnPage): ResponseInterface
    {
        $backendUser = $this->getBackendUser();
        $languageService = $this->getLanguageService();

        $pageRecord = BackendUtility::readPageAccess($pageUid, '1=1') ?: [];

        $currentModule = $request->getAttribute('module');
        $currentModuleIdentifier = $currentModule->getIdentifier();
        $moduleData = $request->getAttribute('moduleData');
        if ($moduleData->cleanUp([])) {
            $backendUser->pushModuleData($moduleData->getModuleIdentifier(), $moduleData->toArray());
        }

        if ($moduleData->clean('templatesOnPage', array_column($allTemplatesOnPage, 'uid') ?: [0])) {
            $backendUser->pushModuleData($currentModuleIdentifier, $moduleData->toArray());
        }

        $selectedTemplateRecord = (int)$moduleData->get('templatesOnPage');
        $templateRow = $this->getFirstTemplateRecordOnPage($pageUid, $selectedTemplateRecord);

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($languageService->sL($currentModule->getTitle()), $pageRecord['title']);
        $view->getDocHeaderComponent()->setMetaInformation($pageRecord);
        $this->addPreviewButtonToDocHeader($view, $pageUid, (int)$pageRecord['doktype']);
        $this->addShortcutButtonToDocHeader($view, $currentModuleIdentifier, $pageRecord, $pageUid);
        $view->makeDocHeaderModuleMenu(['id' => $pageUid]);
        $view->assignMultiple([
            'pageUid' => $pageUid,
            'previousPage' => $this->getClosestAncestorPageWithTemplateRecord($pageUid),
            'templateRecord' => $templateRow,
            'manyTemplatesMenu' => BackendUtility::getFuncMenu($pageUid, 'templatesOnPage', $moduleData->get('templatesOnPage'), array_column($allTemplatesOnPage, 'title', 'uid')),
            'numberOfConstantsLines' => trim((string)($templateRow['constants'] ?? '')) ? count(explode(LF, (string)$templateRow['constants'])) : 0,
            'numberOfSetupLines' => trim((string)($templateRow['config'] ?? '')) ? count(explode(LF, (string)$templateRow['config'])) : 0,
        ]);
        return $view->renderResponse('InfoModifyMain');
    }

    protected function addNewButtonToDocHeader(ModuleTemplate $view, string $moduleIdentifier, int $pageId): void
    {
        $languageService = $this->getLanguageService();
        if ($pageId) {
            $urlParameters = [
                'id' => $pageId,
                'template' => 'all',
                'createExtension' => 'new',
            ];
            $buttonBar = $view->getDocHeaderComponent()->getButtonBar();
            $newButton = $buttonBar->makeLinkButton()
                ->setHref((string)$this->uriBuilder->buildUriFromRoute($moduleIdentifier, $urlParameters))
                ->setTitle($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:db_new.php.pagetitle'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL));
            $buttonBar->addButton($newButton);
        }
    }
}
