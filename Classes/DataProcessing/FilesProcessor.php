<?php

declare(strict_types=1);

namespace KayStrobach\Themes\DataProcessing;

/***************************************************************
 *
 * Copyright notice
 *
 * (c) 2019 TYPO3 Themes-Team <team@typo3-themes.org>
 *
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\Resource\FileCollector;

/**
 * This data processor can be used for processing data for record which contain
 * relations to sys_file records (e.g. sys_file_reference records) or for fetching
 * files directly from UIDs or from folders or collections.
 *
 *
 * Example TypoScript configuration:
 *
 * 10 = TYPO3\CMS\Frontend\DataProcessing\FilesProcessor
 * 10 {
 *   references.fieldName = image
 *   collections = 13,15
 *   as = myfiles
 * }
 *
 * whereas "myfiles" can further be used as a variable {myfiles} inside a Fluid template for iteration.
 */
class FilesProcessor implements DataProcessorInterface
{
    /**
     * Process data of a record to resolve File objects to the view
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }
        //
        // gather data
        /** @var FileCollector $fileCollector */
        $fileCollector = GeneralUtility::makeInstance(FileCollector::class);
        //
        // references / relations
        if (!empty($processorConfiguration['references.'])) {
            $referenceConfiguration = $processorConfiguration['references.'];
            $relationField = $cObj->stdWrapValue('fieldName', $referenceConfiguration);
            //
            // If no reference fieldName is set, there's nothing to do
            if (!empty($relationField)) {
                // Fetch the references of the default element
                $relationTable = $cObj->stdWrapValue('table', $referenceConfiguration, $cObj->getCurrentTable());
                if (!empty($relationTable)) {
                    $fileCollector->addFilesFromRelation($relationTable, $relationField, $cObj->data);
                }
            }
        }
        //
        // files
        $files = $cObj->stdWrapValue('files', $processorConfiguration);
        if ($files) {
            $files = GeneralUtility::intExplode(',', $files, true);
            $fileCollector->addFiles($files);
        }
        //
        // collections
        $collections = $cObj->stdWrapValue('collections', $processorConfiguration);
        if (!empty($collections)) {
            $collections = GeneralUtility::trimExplode(',', $collections, true);
            $fileCollector->addFilesFromFileCollections($collections);
        }
        //
        // folders
        $folders = $cObj->stdWrapValue('folders', $processorConfiguration);
        if (!empty($folders)) {
            $folders = GeneralUtility::trimExplode(',', $folders, true);
            $fileCollector->addFilesFromFolders($folders, !empty($processorConfiguration['folders.']['recursive']));
        }
        //
        // make sure to sort the files
        $sortingProperty = $cObj->stdWrapValue('sorting', $processorConfiguration);
        if ($sortingProperty) {
            $sortingDirection = $cObj->stdWrapValue(
                'direction',
                $processorConfiguration['sorting.'] ?? [],
                'ascending'
            );
            $fileCollector->sort($sortingProperty, $sortingDirection);
        }
        //
        // set the files into a variable, default "files"
        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'files');
        //
        // Extract link data
        $files = $fileCollector->getFiles();
        $filesWithLinkData = [];
        if (count($files) > 0) {
            /** @var FileReference $file */
            foreach ($files as $file) {
                $fileProperties = $file->getProperties();
                $link = trim((string) $fileProperties['link']);
                $linkData = [];
                if ($link !== '') {
                    $typoLinkCodecService = GeneralUtility::makeInstance(TypoLinkCodecService::class);
                    $linkData = $typoLinkCodecService->decode($link);
                }
                $filesWithLinkData[] = [
                        'file' => $file,
                        'link' => $linkData,
                ];
            }
        }
        //
        $processedData[$targetVariableName] = $files;
        $processedData[$targetVariableName . 'WithLinkData'] = $filesWithLinkData;
        return $processedData;
    }
}
