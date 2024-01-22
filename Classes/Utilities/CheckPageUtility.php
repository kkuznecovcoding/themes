<?php

declare(strict_types=1);

namespace KayStrobach\Themes\Utilities;

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

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CheckPageUtility.
 */
class CheckPageUtility
{
    /**
     * @param $pid
     *
     * @return bool
     * @throws DBALException
     */
    public static function hasThemeableSysTemplateRecord($pid): bool
    {
        self::hasTheme($pid);
        $themeable = false;
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_template');
        $queryBuilder->select('*')
                ->from('sys_template')
                ->where(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter((int)$pid, PDO::PARAM_INT)
                    )
                );
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $themeableSysTemplateRecordsRequireRootFlag = true;
        try {
            $site = $siteFinder->getSiteByPageId($pid);
            $configuration = $site->getConfiguration();
            if (isset($configuration['themeableSysTemplateRecordsRequireRootFlag'])) {
                $themeableSysTemplateRecordsRequireRootFlag = (bool)$configuration['themeableSysTemplateRecordsRequireRootFlag'];
            }
        } catch (SiteNotFoundException) {
            // simple keep variable value
        }
        if ($themeableSysTemplateRecordsRequireRootFlag === true) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'root',
                    $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)
                )
            );
        }
        /** @var Statement $statement */
        $statement = $queryBuilder->execute();
        if ($statement->rowCount() > 0) {
            $themeable = true;
        }
        return $themeable;
    }

    /**
     * @param $pid
     *
     * @return bool
     * @throws DBALException
     */
    public static function hasTheme($pid): bool
    {
        $hasTheme = false;
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_template');
        $queryBuilder->select('*')
                ->from('sys_template')
                ->andWhere(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter((int)$pid, PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'root',
                        $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->neq(
                        'tx_themes_skin',
                        $queryBuilder->createNamedParameter('')
                    )
                );
        /** @var Statement $statement */
        $statement = $queryBuilder->execute();
        if ($statement->rowCount() > 0) {
            $hasTheme = true;
        }
        return $hasTheme;
    }

    /**
     * @param $pid
     *
     * @return mixed
     * @throws DBALException
     */
    public static function getThemeableSysTemplateRecord($pid)
    {
        $themeable = false;
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_template');
        $queryBuilder->select('*')
                ->from('sys_template')
                ->andWhere(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter((int)$pid, PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'root',
                        $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)
                    )
                );
        /** @var Statement $statement */
        $statement = $queryBuilder->execute();
        if ($statement->rowCount() > 0) {
            $row = $statement->fetch();
            $themeable = $row['uid'];
        }
        return $themeable;
    }
}
