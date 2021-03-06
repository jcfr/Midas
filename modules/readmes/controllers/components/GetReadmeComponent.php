<?php
/*=========================================================================
 Midas Server
 Copyright Kitware SAS, 26 rue Louis Guérin, 69100 Villeurbanne, France.
 All rights reserved.
 For more information visit http://www.kitware.com/.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

/** Exract readme text according to the folder or community */
class Readmes_GetReadmeComponent extends AppComponent
{
    /**
     * Get the readme text from the specified folder.
     */
    public function fromFolder($folder)
    {
        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');

        /** @var ItemModel $itemModel */
        $itemModel = MidasLoader::loadModel('Item');
        $readmeItem = null;
        $candidates = array('readme.md', 'readme.txt', 'readme');
        foreach ($candidates as $candidate) {
            $readmeItem = $folderModel->getItemByName($folder, $candidate, false);
            if ($readmeItem != null) {
                break;
            }
        }

        if ($readmeItem == null) {
            return array('text' => '');
        }
        $revisionDao = $itemModel->getLastRevision($readmeItem);
        if ($revisionDao === false) {
            return array('text' => '');
        }
        $bitstreams = $revisionDao->getBitstreams();
        if (count($bitstreams) === 0) {
            return array('text' => '');
        }
        $bitstream = $bitstreams[0];
        $path = $bitstream->getAssetstore()->getPath().'/'.$bitstream->getPath();
        $contents = file_get_contents($path);
        MidasLoader::loadComponent('Utility');
        $parsedContents = UtilityComponent::markdown(htmlspecialchars($contents, ENT_COMPAT, 'UTF-8'));

        return array('text' => $parsedContents);
    }

    /**
     * Get the readme text from the specified community.
     */
    public function fromCommunity($community)
    {
        if ($community == null) {
            throw new Zend_Exception('Invalid Community');
        }

        /** @var FolderModel $folderModel */
        $folderModel = MidasLoader::loadModel('Folder');
        $rootFolder = $community->getFolder();
        $publicFolder = $folderModel->getFolderByName($rootFolder, 'Public');

        return $this->fromFolder($publicFolder);
    }
}
