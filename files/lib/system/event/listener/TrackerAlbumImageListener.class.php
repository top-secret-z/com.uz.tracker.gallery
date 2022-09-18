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
 * Listen to Gallery image action.
 */
class TrackerAlbumImageListener implements IParameterizedEventListener
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
        if (!$this->tracker->wlgalImage && !$this->tracker->otherModeration) {
            return;
        }

        // actions / data
        $action = $eventObj->getActionName();

        if ($this->tracker->wlgalImage) {
            if ($action == 'upload') {
                $returnValues = $eventObj->getReturnValues();
                $images = $returnValues['returnValues']['images'];

                foreach ($images as $image) {
                    $this->link = $image['url'];
                    $this->store('wcf.uztracker.description.album.image.upload', 'wcf.uztracker.type.wlgal');
                }
            }

            if ($action == 'triggerPublication') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $image) {
                    $this->link = $image->getLink();
                    $this->store('wcf.uztracker.description.album.image.add', 'wcf.uztracker.type.wlgal');
                }
            }
        }

        if ($this->tracker->otherModeration) {
            if ($action == 'disable' || $action == 'enable') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $image) {
                    $this->link = $image->getLink();
                    if ($action == 'disable') {
                        $this->store('wcf.uztracker.description.album.image.disable', 'wcf.uztracker.type.moderation');
                    } else {
                        $this->store('wcf.uztracker.description.album.image.enable', 'wcf.uztracker.type.moderation');
                    }
                }
            }

            if ($action == 'delete') {
                $objects = $eventObj->getObjects();
                foreach ($objects as $image) {
                    $this->link = '';
                    $name = $image->title;
                    $this->store('wcf.uztracker.description.album.image.delete', 'wcf.uztracker.type.moderation', $name);
                }
            }
        }

        if ($action == 'trash' || $action == 'restore') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $image) {
                $this->link = $image->getLink();
                if ($action == 'trash') {
                    if ($image->userID == $user->userID) {
                        if ($this->tracker->wlgalImage) {
                            $this->store('wcf.uztracker.description.album.image.trash', 'wcf.uztracker.type.wlgal');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.album.image.trash', 'wcf.uztracker.type.moderation');
                        }
                    }
                } else {
                    if ($image->userID == $user->userID) {
                        if ($this->tracker->wlgalImage) {
                            $this->store('wcf.uztracker.description.album.image.restore', 'wcf.uztracker.type.wlgal');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.album.image.restore', 'wcf.uztracker.type.moderation');
                        }
                    }
                }
            }
        }

        if ($action == 'update') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $image) {
                $this->link = $image->getLink();

                // disabled upload
                $params = $eventObj->getParameters();
                if (isset($params['data']['isDisabled'])) {
                    if ($params['data']['isDisabled']) {
                        $this->store('wcf.uztracker.description.album.image.upload.disabled', 'wcf.uztracker.type.wlgal');
                    }
                } else {
                    if ($image->userID == $user->userID) {
                        if ($this->tracker->wlgalImage) {
                            $this->store('wcf.uztracker.description.album.image.update', 'wcf.uztracker.type.wlgal');
                        }
                    } else {
                        if ($this->tracker->otherModeration) {
                            $this->store('wcf.uztracker.description.album.image.update', 'wcf.uztracker.type.moderation');
                        }
                    }
                }
            }
        }

        if ($action == 'moveToAlbum') {
            $objects = $eventObj->getObjects();
            foreach ($objects as $image) {
                $this->link = $image->getLink();

                if ($image->userID == $user->userID) {
                    if ($this->tracker->wlgalImage) {
                        $this->store('wcf.uztracker.description.album.image.move', 'wcf.uztracker.type.wlgal');
                    }
                } else {
                    if ($this->tracker->otherModeration) {
                        $this->store('wcf.uztracker.description.album.image.move', 'wcf.uztracker.type.moderation');
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
