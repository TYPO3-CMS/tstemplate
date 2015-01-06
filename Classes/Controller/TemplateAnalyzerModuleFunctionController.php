<?php
namespace TYPO3\CMS\Tstemplate\Controller;

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

use TYPO3\CMS\Backend\Module\AbstractFunctionModule;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * TypoScript template analyzer
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class TemplateAnalyzerModuleFunctionController extends AbstractFunctionModule {

	/**
	 * @var TypoScriptTemplateModuleController
	 */
	public $pObj;

	/**
	 * Init
	 *
	 * @param TypoScriptTemplateModuleController $pObj
	 * @param array $conf
	 * @return void
	 */
	public function init(&$pObj, $conf) {
		parent::init($pObj, $conf);
		$this->getLanguageService()->includeLLFile('EXT:tstemplate/Resources/Private/Language/locallang_analyzer.xlf');
		$this->pObj->modMenu_setDefaultList .= ',ts_analyzer_checkLinenum,ts_analyzer_checkSyntax';
	}

	/**
	 * Mod menu
	 *
	 * @return array
	 */
	public function modMenu() {
		return array(
			'ts_analyzer_checkSetup' => '1',
			'ts_analyzer_checkConst' => '1',
			'ts_analyzer_checkLinenum' => '1',
			'ts_analyzer_checkComments' => '1',
			'ts_analyzer_checkCrop' => '1',
			'ts_analyzer_checkSyntax' => '1'
		);
	}

	/**
	 * Initialize editor
	 *
	 * @param int $pageId
	 * @param int $template_uid
	 * @return bool
	 */
	public function initialize_editor($pageId, $template_uid = 0) {
		// Initializes the module. Done in this function because we may need to re-initialize if data is submitted!
		$templateService = GeneralUtility::makeInstance(ExtendedTemplateService::class);
		$GLOBALS['tmpl'] = $templateService;

		// Do not log time-performance information
		$templateService->tt_track = FALSE;
		$templateService->init();

		// Gets the rootLine
		$sys_page = GeneralUtility::makeInstance(PageRepository::class);
		$GLOBALS['rootLine'] = $sys_page->getRootLine($pageId);

		// This generates the constants/config + hierarchy info for the template.
		$templateService->runThroughTemplates($GLOBALS['rootLine'], $template_uid);

		// Get the row of the first VISIBLE template of the page. whereclause like the frontend.
		$GLOBALS['tplRow'] = $templateService->ext_getFirstTemplate($pageId, $template_uid);
		return is_array($GLOBALS['tplRow']);
	}

	/**
	 * Main
	 *
	 * @return string
	 */
	public function main() {
		$theOutput = '';

		// Initializes the module. Done in this function because we may need to re-initialize if data is submitted!
		// Checking for more than one template an if, set a menu...
		$manyTemplatesMenu = $this->pObj->templateMenu();
		$template_uid = 0;
		if ($manyTemplatesMenu) {
			$template_uid = $this->pObj->MOD_SETTINGS['templatesOnPage'];
		}

		$existTemplate = $this->initialize_editor($this->pObj->id, $template_uid);

		// initialize
		$lang = $this->getLanguageService();
		if ($existTemplate) {
			$siteTitle = trim($GLOBALS['tplRow']['sitetitle']);
			$theOutput .= $this->pObj->doc->section(
				$lang->getLL('currentTemplate', TRUE),
				IconUtility::getSpriteIconForRecord('sys_template', $GLOBALS['tplRow'])
					. '<strong>' . $this->pObj->linkWrapTemplateTitle($GLOBALS['tplRow']['title']) . '</strong>'
					. htmlspecialchars($siteTitle ? ' (' . $siteTitle . ')' : '')
			);
		}
		if ($manyTemplatesMenu) {
			$theOutput .= $this->pObj->doc->section('', $manyTemplatesMenu);
		}
		$templateService = $this->getExtendedTemplateService();
		$templateService->clearList_const_temp = array_flip($templateService->clearList_const);
		$templateService->clearList_setup_temp = array_flip($templateService->clearList_setup);
		$pointer = count($templateService->hierarchyInfo);
		$hierarchyInfo = $templateService->ext_process_hierarchyInfo(array(), $pointer);
		$head = '<thead><tr>';
		$head .= '<th>' . $lang->getLL('title', TRUE) . '</th>';
		$head .= '<th>' . $lang->getLL('rootlevel', TRUE) . '</th>';
		$head .= '<th>' . $lang->getLL('clearSetup', TRUE) . '</th>';
		$head .= '<th>' . $lang->getLL('clearConstants', TRUE) . '</th>';
		$head .= '<th>' . $lang->getLL('pid', TRUE) . '</th>';
		$head .= '<th>' . $lang->getLL('rootline', TRUE) . '</th>';
		$head .= '<th>' . $lang->getLL('nextLevel', TRUE) . '</th>';
		$head .= '</tr></thead>';
		$hierar = implode(array_reverse($templateService->ext_getTemplateHierarchyArr($hierarchyInfo, '', array(), 1)), '');
		$hierar = '<div class="table-fit"><table class="table table-striped table-hover" id="ts-analyzer">' . $head . $hierar . '</table></div>';
		$theOutput .= $this->pObj->doc->spacer(5);
		$theOutput .= $this->pObj->doc->section($lang->getLL('templateHierarchy', TRUE), $hierar, 0, 1);
		$urlParameters = array(
			'id' => $GLOBALS['SOBE']->id,
			'template' => 'all'
		);
		$aHref = BackendUtility::getModuleUrl('web_ts', $urlParameters);

		$completeLink = '<p><a href="' . htmlspecialchars($aHref) . '" class="btn btn-default">' . $lang->getLL('viewCompleteTS', TRUE) . '</a></p>';
		$theOutput .= $this->pObj->doc->spacer(5);
		$theOutput .= $this->pObj->doc->section($lang->getLL('completeTS', TRUE), $completeLink, 0, 1);
		$theOutput .= $this->pObj->doc->spacer(15);
		// Output options
		$theOutput .= $this->pObj->doc->section($lang->getLL('displayOptions', TRUE), '', FALSE, TRUE);

		$template = GeneralUtility::_GET('template');
		$addParams = $template ? '&template=' . $template : '';
		$theOutput .= '<div class="tst-analyzer-options">' .
			'<div class="checkbox"><label for="checkTs_analyzer_checkLinenum">' .
				BackendUtility::getFuncCheck($this->pObj->id, 'SET[ts_analyzer_checkLinenum]', $this->pObj->MOD_SETTINGS['ts_analyzer_checkLinenum'], '', $addParams, 'id="checkTs_analyzer_checkLinenum"') .
				$lang->getLL('lineNumbers', TRUE) .
			'</label></div>' .
			'<div class="checkbox"><label for="checkTs_analyzer_checkSyntax">' .
				BackendUtility::getFuncCheck($this->pObj->id, 'SET[ts_analyzer_checkSyntax]', $this->pObj->MOD_SETTINGS['ts_analyzer_checkSyntax'], '', $addParams, 'id="checkTs_analyzer_checkSyntax"') .
				$lang->getLL('syntaxHighlight', TRUE) . '</label> ' .
			'</label></div>';
		if (!$this->pObj->MOD_SETTINGS['ts_analyzer_checkSyntax']) {
			$theOutput .=
				'<div class="checkbox"><label for="checkTs_analyzer_checkComments">' .
					BackendUtility::getFuncCheck($this->pObj->id, 'SET[ts_analyzer_checkComments]', $this->pObj->MOD_SETTINGS['ts_analyzer_checkComments'], '', $addParams, 'id="checkTs_analyzer_checkComments"') .
					$lang->getLL('comments', TRUE) .
				'</label></div>' .
				'<div class="checkbox"><label for="checkTs_analyzer_checkCrop">' .
					BackendUtility::getFuncCheck($this->pObj->id, 'SET[ts_analyzer_checkCrop]', $this->pObj->MOD_SETTINGS['ts_analyzer_checkCrop'], '', $addParams, 'id="checkTs_analyzer_checkCrop"') .
					$lang->getLL('cropLines', TRUE) .
				'</label></div>';
		}
		$theOutput .=  '</div>';
		$theOutput .= $this->pObj->doc->spacer(25);

		if ($template) {
			// Output Constants
			$theOutput .= $this->pObj->doc->section($lang->getLL('constants', TRUE), '', 0, 1);
			$theOutput .= $this->pObj->doc->sectionEnd();

			$templateService->ext_lineNumberOffset = 0;
			$templateService->ext_lineNumberOffset_mode = 'const';
			foreach ($templateService->constants as $key => $val) {
				$currentTemplateId = $templateService->hierarchyInfo[$key]['templateID'];
				if ($currentTemplateId == $template || $template === 'all') {
					$theOutput .= '
						<h3>' . htmlspecialchars($templateService->hierarchyInfo[$key]['title']) . '</h3>
						<div class="nowrap">' .
							$templateService->ext_outputTS(array($val), $this->pObj->MOD_SETTINGS['ts_analyzer_checkLinenum'], $this->pObj->MOD_SETTINGS['ts_analyzer_checkComments'], $this->pObj->MOD_SETTINGS['ts_analyzer_checkCrop'], $this->pObj->MOD_SETTINGS['ts_analyzer_checkSyntax'], 0) .
						'</div>
					';
					if ($template !== 'all') {
						break;
					}
				}
				$templateService->ext_lineNumberOffset += count(explode(LF, $val)) + 1;
			}

			// Output Setup
			$theOutput .= $this->pObj->doc->spacer(15);
			$theOutput .= $this->pObj->doc->section($lang->getLL('setup', TRUE), '', 0, 1);
			$theOutput .= $this->pObj->doc->sectionEnd();
			$templateService->ext_lineNumberOffset = 0;
			$templateService->ext_lineNumberOffset_mode = 'setup';
			foreach ($templateService->config as $key => $val) {
				$currentTemplateId = $templateService->hierarchyInfo[$key]['templateID'];
				if ($currentTemplateId == $template || $template == 'all') {
					$theOutput .= '
						<h3>' . htmlspecialchars($templateService->hierarchyInfo[$key]['title']) . '</h3>
						<div class="nowrap">' .
							$templateService->ext_outputTS(array($val), $this->pObj->MOD_SETTINGS['ts_analyzer_checkLinenum'], $this->pObj->MOD_SETTINGS['ts_analyzer_checkComments'], $this->pObj->MOD_SETTINGS['ts_analyzer_checkCrop'], $this->pObj->MOD_SETTINGS['ts_analyzer_checkSyntax'], 0) .
						'</div>
					';
					if ($template !== 'all') {
						break;
					}
				}
				$templateService->ext_lineNumberOffset += count(explode(LF, $val)) + 1;
			}
		}
		return $theOutput;
	}

	/**
	 * @return ExtendedTemplateService
	 */
	protected function getExtendedTemplateService() {
		return $GLOBALS['tmpl'];
	}

}
