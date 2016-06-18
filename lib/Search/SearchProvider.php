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

namespace OCA\SearchLight\Search;

use OC\DB\QueryBuilder\Literal;
use OCP\Files\FileInfo;
use OCP\Files\IHomeStorage;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\Search\Provider;

class SearchProvider extends Provider {

	/** @var  IDBConnection */
	private $connection;

	/** @var IUserSession */
	private $userSession;

	/** @var  IMountManager */
	private $mountManager;

	/** @var  IMimeTypeLoader */
	private $mimetypeLoader;

	public function __construct(array $options) {
		parent::__construct($options);
		$this->connection = $this->getOption('connection');
		$this->userSession = $this->getOption('userSession');
		$this->mountManager = $this->getOption('mountManager');
		$this->mimetypeLoader = $this->getOption('mimetypeLoader');
	}

	private function getStorageIds($mounts) {
		return array_map(function (IMountPoint $mount) {
			return $mount->getStorage()->getStorageCache()->getNumericId();
		}, $mounts);
	}

	/**
	 * Search for $query
	 *
	 * @param string $queryString
	 * @return array An array of OCP\Search\Result's
	 * @since 7.0.0
	 */
	public function search($queryString) {
		//we want more results
		$this->connection->executeQuery('SELECT set_limit(0.2);');

		$mounts = $this->mountManager->findIn('/' . $this->userSession->getUser()->getUID() . '/files');
		$mounts[] = $this->mountManager->find('/' . $this->userSession->getUser()->getUID() . '/files');

		$storageIds = array_map(function ($id) {
			return new Literal($id);
		}, $this->getStorageIds($mounts));

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('filecache')
			->where($query->createFunction('name ~* :query'))// fancy postgres fuzy matching
			->orWhere($query->createFunction('name % :query'))
			->andWhere($query->expr()->in('storage', $storageIds))
			->orderBy($query->createFunction('name <-> :query'))
			->setMaxResults(100);
		$query->setParameter(':query', $queryString);

		$data = $query->execute()->fetchAll();

		$files = array_filter(array_map(function ($fileData) use ($mounts) {
			return $this->getFileInfo($fileData, $mounts);
		}, $data));

		return array_filter(array_map([$this, 'formatResult'], $files));
	}

	/**
	 * @param array $data
	 * @param IMountPoint[] $mounts
	 * @return \OC\Files\FileInfo
	 */
	private function getFileInfo(array $data, $mounts) {
		foreach ($mounts as $mount) {
			$storage = $mount->getStorage();
			if ($storage->getStorageCache()->getNumericId() === $data['storage']) {
				$data['mimetype'] = $this->mimetypeLoader->getMimetypeById($data['mimetype']);
				$data['mimepart'] = $this->mimetypeLoader->getMimetypeById($data['mimepart']);

				$path = $this->getAbsolutePath($mount, $storage, $data['path']);
				if ($path === null) {
					return null;
				}

				return new \OC\Files\FileInfo(
					$path,
					$storage,
					$data['path'],
					$data,
					$mount,
					$this->userSession->getUser()
				);
			}
		}

		return null;
	}

	private function getAbsolutePath(IMountPoint $mount, IStorage $storage, $path) {
		if ($storage->instanceOfStorage('\OC\Files\Storage\Wrapper\Jail')) {
			/** @var \OC\Files\Storage\Wrapper\Jail $storage */
			$jailRoot = $storage->getSourcePath('');
			$rootLength = strlen($jailRoot) + 1;
			if ($path === $jailRoot) {
				return $mount->getMountPoint();
			} else if (substr($path, 0, $rootLength) === $jailRoot . '/') {
				return $mount->getMountPoint() . substr($path, $rootLength);
			} else {
				return null;
			}
		} else {
			return $mount->getMountPoint() . $path;
		}
	}

	private function formatResult(FileInfo $fileData) {
		// filter home folder
		if ($fileData->getStorage() instanceof IHomeStorage && substr($fileData->getPath(), 0, 6) !== 'files/') {
			return null;
		}

		// create audio result
		if ($fileData->getMimePart() === 'audio') {
			return new \OC\Search\Result\Audio($fileData);
		} // create image result
		elseif ($fileData->getMimePart() === 'image') {
			return new \OC\Search\Result\Image($fileData);
		} // create folder result
		elseif ($fileData->getMimetype() === 'httpd/unix-directory') {
			return new \OC\Search\Result\Folder($fileData);
		} // or create file result
		else {
			return new \OC\Search\Result\File($fileData);
		}
	}
}
