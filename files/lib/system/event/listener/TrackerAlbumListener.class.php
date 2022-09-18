<?php
namespace gallery\system\event\listener;
use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Listen to Gallery album action.
 * 
 * @author		2016-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.tracker.gallery
 */
class TrackerAlbumListener implements IParameterizedEventListener {
	/**
	 * tracker and link
	 */
	protected $tracker = null;
	protected $link = '';
	
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if (!MODULE_TRACKER) return;
		
		// only if user is to be tracked
		$user = WCF::getUser();
		if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) return;
		
		// only if trackers
		$trackers = TrackerCacheBuilder::getInstance()->getData();
		if (!isset($trackers[$user->userID])) return;
		
		$this->tracker = $trackers[$user->userID];
		if (!$this->tracker->wlgalAlbum && !$this->tracker->otherModeration) return;
		
		//  actions
		$action = $eventObj->getActionName();
		
		if ($action == 'create') {
			$returnValues = $eventObj->getReturnValues();
			$album = $returnValues['returnValues'];
			$this->link = $album->getLink();
			
			$this->store('wcf.uztracker.description.album.add', 'wcf.uztracker.type.wlgal');
		}
		
		if ($action == 'delete') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $album) {
				$this->link = '';
				$name = $album->title;
				
				if ($album->userID == $user->userID) {
					if ($this->tracker->wlgalAlbum) $this->store('wcf.uztracker.description.album.delete', 'wcf.uztracker.type.wlgal', $name);
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.album.delete', 'wcf.uztracker.type.moderation', $name);
				}
			}
		}
		
		if ($action == 'update') {
			$objects = $eventObj->getObjects();
			foreach ($objects as $album) {
				$this->link = $album->getLink();
				if ($album->userID == $user->userID) {
					if ($this->tracker->wlgalAlbum) $this->store('wcf.uztracker.description.album.update', 'wcf.uztracker.type.wlgal');
				}
				else {
					if ($this->tracker->otherModeration) $this->store('wcf.uztracker.description.album.update', 'wcf.uztracker.type.moderation');
				}
			}
		}
	}
	
	/**
	 * store log entry
	 */
	protected function store ($description, $type, $name = '') {
		$packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.gallery');
		TrackerLogEditor::create([
				'description' => $description,
				'link' => $this->link,
				'name' => $name,
				'trackerID' => $this->tracker->trackerID,
				'type' => $type,
				'packageID' => $packageID
		]);
	}
}
