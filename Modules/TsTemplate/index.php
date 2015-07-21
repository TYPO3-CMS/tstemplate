<?php
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

$GLOBALS['SOBE'] = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Tstemplate\Controller\TypoScriptTemplateModuleController::class);
$GLOBALS['SOBE']->init();

// Checking for first level external objects
$GLOBALS['SOBE']->checkExtObj();
$GLOBALS['SOBE']->clearCache();
$GLOBALS['SOBE']->main();
$GLOBALS['SOBE']->printContent();
