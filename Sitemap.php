<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Sitemap;

use Propel\Runtime\Connection\ConnectionInterface;
use Sitemap\Model\SitemapPriority;
use Sitemap\Model\SitemapPriorityQuery;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class Sitemap extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'sitemap';

    const DEFAULT_PRIORITY_HOME_VALUE = 1;

    const DEFAULT_PRIORITY_BRAND_VALUE = 0.6;

    const DEFAULT_PRIORITY_CATEGORY_VALUE = 0.9;

    const DEFAULT_PRIORITY_PRODUCT_VALUE = 0.8;

    const DEFAULT_PRIORITY_FOLDER_VALUE = 0.6;

    const DEFAULT_FREQUENCY_UPDATE = 'weekly';

    public function postActivation(ConnectionInterface $con = null): void
    {
        try {
            SitemapPriorityQuery::create()->findOne();
        } catch (\Exception $ex) {
            $database = new Database($con->getWrappedConnection());
            $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));
        }
    }


    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $finder = (new Finder)
            ->files()
            ->name('#.*?\.sql#')
            ->sortByName()
            ->in(__DIR__ . DS . 'Config' . DS . 'update' . DS . 'sql');

        $database = new Database($con);

        /** @var \Symfony\Component\Finder\SplFileInfo $updateSQLFile */
        foreach ($finder as $updateSQLFile) {
            if (version_compare($currentVersion, str_replace('.sql', '', $updateSQLFile->getFilename()), '<')) {
                $database->insertSql(
                    null,
                    [
                        $updateSQLFile->getPathname()
                    ]
                );
            }
        }
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
