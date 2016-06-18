<?php
/**
 * @author Robin Appelman <robin@icewind.nl>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\SearchLight\AppInfo;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use OCP\AppFramework\App;

class Application extends App {
	public function __construct(array $urlParams = array()) {
		parent::__construct('searchlight', $urlParams);
	}

	public function isPostgres() {
		$container = $this->getContainer();
		$connection = $container->getServer()->getDatabaseConnection();
		return $connection->getDatabasePlatform() instanceof PostgreSqlPlatform;
	}

	public function setUpIndex() {
		$server = $this->getContainer()->getServer();
		$config = $server->getConfig();
		if ($config->getAppValue('searchlight', 'index_version', 0) < 1) {
			$config->setAppValue('searchlight', 'index_version', 1);
			$connection = $server->getDatabaseConnection();
			try {
				$connection->executeQuery('CREATE EXTENSION pg_trgm');
				$connection->executeQuery('CREATE INDEX *PREFIX*path_tri_idx ON *PREFIX*filecache USING gin(name gin_trgm_ops)');
			} catch (DriverException $e) {
				$server->getLogger()->logException($e);
			}
		}
	}

	public function registerSearchProvider() {
		if ($this->isPostgres()) {
			$this->setUpIndex();
			$container = $this->getContainer();
			$server = $container->getServer();
			$server->getSearch()->removeProvider('OC\Search\Provider\File'); // we replace the build in search
			$server->getSearch()->registerProvider('\OCA\SearchLight\Search\SearchProvider', [
				'apps' => ['files'],
				'connection' => $server->getDatabaseConnection(),
				'userSession' => $server->getUserSession(),
				'mountManager' => $server->getMountManager(),
				'mimetypeLoader' => $server->getMimeTypeLoader()
			]);
		}
	}
}
