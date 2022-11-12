<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace gallery\system\event\listener;

use wcf\data\package\PackageCache;
use wcf\data\user\tracker\log\TrackerLogEditor;
use wcf\system\cache\builder\TrackerCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Listen to Gallery album action.
 */
class TrackerAlbumListener implements IParameterizedEventListener
{
    /**
     * tracker and link
     */
    protected $tracker;

    protected $link = '';

    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if (!MODULE_TRACKER) {
            return;
        }

        // only if user is to be tracked
        $user = WCF::getUser();
        if (!$user->userID || !$user->isTracked || WCF::getSession()->getPermission('mod.tracking.noTracking')) {
            return;
        }

        // only if trackers
        $trackers = TrackerCacheBuilder::getInstance()->getData();
        if (!isset($trackers[$user->userID])) {
            return;
        }

        $this->tracker = $trackers[$user->userID];
        if (!$this->tracker->wlgalAlbum && !$this->tracker->otherModeration) {
            return;
        }

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
                    if ($this->tracker->wlgalAlbum) {
                        $this->store('wcf.uztracker.description.album.delete', 'wcf.uztracker.type.wlgal', $name);
                    }
                } else {
                    if ($this->tracker->otherModeration) {
                        $this->store('wcf.uztracker.description.album.delete', 'wcf.uztracker.type.moderation', $name);
                    }
                }
            }
        }

        if ($action == 'update') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $album) {
                $this->link = $album->getLink();
                if ($album->userID == $user->userID) {
                    if ($this->tracker->wlgalAlbum) {
                        $this->store('wcf.uztracker.description.album.update', 'wcf.uztracker.type.wlgal');
                    }
                } else {
                    if ($this->tracker->otherModeration) {
                        $this->store('wcf.uztracker.description.album.update', 'wcf.uztracker.type.moderation');
                    }
                }
            }
        }
    }

    /**
     * store log entry
     */
    protected function store($description, $type, $name = '')
    {
        $packageID = PackageCache::getInstance()->getPackageID('com.uz.tracker.gallery');
        TrackerLogEditor::create([
            'description' => $description,
            'link' => $this->link,
            'name' => $name,
            'trackerID' => $this->tracker->trackerID,
            'type' => $type,
            'packageID' => $packageID,
        ]);
    }
}
